<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * Arus kas harian untuk drill-down "Detail Omset Harian" (/omset_bulanan).
 *
 * Prinsip:
 *  - Read-only. Tidak membuat tabel, tidak menulis transaksi, tidak menyentuh
 *    perhitungan omset maupun cutoff Finance.
 *  - Hanya memakai sumber kas/bank yang sudah ada di ERP:
 *      MASUK  : penjualan (bayar_tunai / bayar_bank),
 *               service status 4 (bayar_tunai / harus_dibayar - bayar_tunai),
 *               kas_masuk non-"kas awal", transaksi_kas_bank (ledger).
 *      KELUAR : kas_keluar, transaksi_kas_bank (ledger).
 *  - Transaksi penjualan/service TIDAK dibuat ulang di ledger. Baris yang sudah
 *    dipromosikan ke transaksi_kas_bank (sumber_tipe + sumber_id) dari
 *    kas_masuk/kas_keluar tidak dihitung dua kali.
 *  - Baris deskripsi 'kas awal' (carry-over TutupKasir) dibuang: itu saldo
 *    awal periode berikutnya, bukan penerimaan kas baru.
 *  - Transfer internal antar kas/rekening (jenis TRANSFER_INTERNAL atau
 *    PEMBAYARAN_ANTAR_UNIT) ditandai terpisah dan tidak masuk subtotal/net
 *    karena bukan pendapatan maupun beban.
 *  - Klasifikasi tunai vs transfer mengikuti TutupKasir: idbank NULL = tunai,
 *    idbank terisi = transfer. Baris ledger memakai akun_kas_bank.tipe
 *    (KAS = tunai, BANK = transfer).
 */
class DailyCashFlowService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Struktur arus kas satu tanggal untuk satu unit.
     *
     * @return array{
     *   tanggal: string, unit_id: int,
     *   masuk: array{tunai: array, transfer: array},
     *   keluar: array{tunai: array, transfer: array},
     *   transfer_internal: array,
     *   subtotal: array, total_masuk: int, total_keluar: int, net: int
     * }
     */
    public function getForDate(int $unitId, string $tanggal): array
    {
        $tanggal = date('Y-m-d', strtotime($tanggal));

        return $this->assemble([
            'tanggal'    => $tanggal,
            'unit_id'    => $unitId,
            'penjualan' => $this->fetchPenjualan($unitId, $tanggal),
            'service'   => $this->fetchService($unitId, $tanggal),
            'kas_masuk' => $this->fetchKasMasuk($unitId, $tanggal),
            'kas_keluar' => $this->fetchKasKeluar($unitId, $tanggal),
            'ledger'    => $this->fetchLedger($unitId, $tanggal),
        ]);
    }

    /**
     * Menggolongkan baris mentah dari setiap sumber menjadi arus kas + subtotal.
     *
     * Dipisah dari query agar logika klasifikasi bisa diuji tanpa database.
     *
     * @param array $sources ['penjualan','service','kas_masuk','kas_keluar','ledger']
     */
    public function assemble(array $sources): array
    {
        $masuk = ['tunai' => [], 'transfer' => []];
        $keluar = ['tunai' => [], 'transfer' => []];
        $internal = [];

        // Baris kas_masuk/kas_keluar yang sudah dipromosikan ke ledger
        // dihitung dari ledger (bukan dari tabel asalnya) supaya tidak dobel.
        $ledgerRefs = [];
        foreach ($sources['ledger'] ?? [] as $row) {
            if (! empty($row->sumber_tipe) && isset($row->sumber_id)) {
                $ledgerRefs[$row->sumber_tipe . '#' . (int) $row->sumber_id] = true;
            }
        }

        foreach ($sources['penjualan'] ?? [] as $row) {
            $referensi = trim((string)($row->kode_invoice ?? ''));
            $keterangan = $referensi !== ''
                ? 'Penjualan ' . $referensi
                : 'Penjualan';

            $tunai = (int)($row->bayar_tunai ?? 0);
            $transfer = (int)($row->bayar_bank ?? 0);

            if ($tunai > 0) {
                $masuk['tunai'][] = $this->row(
                    $this->waktu($row->tanggal ?? null),
                    'Penjualan',
                    $keterangan,
                    $tunai
                );
            }
            if ($transfer > 0) {
                $masuk['transfer'][] = $this->row(
                    $this->waktu($row->tanggal ?? null),
                    'Penjualan',
                    $keterangan,
                    $transfer
                );
            }
        }

        foreach ($sources['service'] ?? [] as $row) {
            $referensi = trim((string)($row->no_service ?? ''));
            $keterangan = $referensi !== ''
                ? 'Service ' . $referensi
                : 'Service';

            $waktu = $this->waktu($row->tanggal_selesai ?? null);

            $tunai = (int)($row->bayar_tunai ?? 0);
            $transfer = max(0, (int)($row->harus_dibayar ?? 0) - $tunai);

            if ($tunai > 0) {
                $masuk['tunai'][] = $this->row($waktu, 'Service', $keterangan, $tunai);
            }
            if ($transfer > 0) {
                $masuk['transfer'][] = $this->row($waktu, 'Service', $keterangan, $transfer);
            }
        }

        foreach ($sources['kas_masuk'] ?? [] as $row) {
            if ($this->sudahDiLedger($ledgerRefs, 'kas_masuk', $row->idkas_masuk ?? null)) {
                continue;
            }

            $jumlah = (int)($row->jumlah ?? 0);
            if ($jumlah === 0) {
                continue;
            }

            $metode = $this->isTunai($row) ? 'tunai' : 'transfer';
            $keterangan = trim((string)($row->deskripsi ?? ''));
            if ($keterangan === '') {
                $keterangan = 'Penerimaan kas';
            }
            $penerima = trim((string)($row->penerima ?? ''));
            if ($penerima !== '') {
                $keterangan .= ' — ' . $penerima;
            }

            $masuk[$metode][] = $this->row(
                $this->waktu($row->created_on ?? null, $row->updated_on ?? null),
                'Kas Masuk',
                $keterangan,
                $jumlah
            );
        }

        foreach ($sources['kas_keluar'] ?? [] as $row) {
            if ($this->sudahDiLedger($ledgerRefs, 'kas_keluar', $row->idkas_keluar ?? null)) {
                continue;
            }

            $jumlah = (int)($row->jumlah ?? 0);
            if ($jumlah === 0) {
                continue;
            }

            $metode = $this->isTunai($row) ? 'tunai' : 'transfer';
            $keterangan = trim((string)($row->deskripsi ?? ''));
            $kategori = trim((string)($row->kategori ?? ''));
            if ($kategori !== '' && stripos($keterangan, $kategori) === false) {
                $keterangan = $kategori . ($keterangan !== '' ? ' — ' . $keterangan : '');
            }
            if ($keterangan === '') {
                $keterangan = 'Pengeluaran kas';
            }

            $keluar[$metode][] = $this->row(
                $this->waktu($row->created_on ?? null, $row->updated_on ?? null),
                'Kas Keluar',
                $keterangan,
                $jumlah
            );
        }

        foreach ($sources['ledger'] ?? [] as $row) {
            $jumlah = (int)($row->jumlah ?? 0);
            $arah = strtoupper((string)($row->arah ?? ''));
            if ($jumlah === 0 || ($arah !== 'MASUK' && $arah !== 'KELUAR')) {
                continue;
            }

            $metode = strtoupper((string)($row->tipe ?? '')) === 'KAS' ? 'tunai' : 'transfer';
            $keterangan = trim((string)($row->keterangan ?? ''));
            if ($keterangan === '') {
                $keterangan = trim((string)($row->nama_akun ?? ''));
            }
            if ($keterangan === '') {
                $keterangan = 'Transaksi kas/bank';
            }

            if ($this->isTransferInternal($row)) {
                $internal[] = $this->row(
                    $this->waktu($row->created_at ?? null),
                    $arah === 'MASUK' ? 'Kas Masuk' : 'Kas Keluar',
                    $keterangan,
                    $jumlah,
                    true
                );
                continue;
            }

            $baris = $this->row(
                $this->waktu($row->created_at ?? null),
                $arah === 'MASUK' ? 'Kas Masuk' : 'Kas Keluar',
                $keterangan,
                $jumlah
            );

            if ($arah === 'MASUK') {
                $masuk[$metode][] = $baris;
            } else {
                $keluar[$metode][] = $baris;
            }
        }

        $subtotal = [
            'masuk_tunai'    => $this->total($masuk['tunai']),
            'masuk_transfer' => $this->total($masuk['transfer']),
            'keluar_tunai'   => $this->total($keluar['tunai']),
            'keluar_transfer' => $this->total($keluar['transfer']),
        ];

        $totalMasuk = $subtotal['masuk_tunai'] + $subtotal['masuk_transfer'];
        $totalKeluar = $subtotal['keluar_tunai'] + $subtotal['keluar_transfer'];

        return [
            'tanggal' => $sources['tanggal'] ?? null,
            'unit_id' => $sources['unit_id'] ?? null,
            'masuk' => [
                'tunai' => $this->urutkan($masuk['tunai']),
                'transfer' => $this->urutkan($masuk['transfer']),
            ],
            'keluar' => [
                'tunai' => $this->urutkan($keluar['tunai']),
                'transfer' => $this->urutkan($keluar['transfer']),
            ],
            'transfer_internal' => $this->urutkan($internal),
            'subtotal' => $subtotal,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'net' => $totalMasuk - $totalKeluar,
        ];
    }

    /**
     * Penjualan non-service: invoice "srv..." sudah tercakup tabel service,
     * jadi dikecualikan agar tidak dobel (memakai aturan TutupKasir).
     */
    protected function fetchPenjualan(int $unitId, string $tanggal): array
    {
        return $this->db->table('penjualan')
            ->select('kode_invoice, tanggal, bayar_tunai, bayar_bank')
            ->where('unit_idunit', $unitId)
            ->where('DATE(tanggal)', $tanggal)
            ->groupStart()
                ->where('kode_invoice IS NULL')
                ->orNotLike('kode_invoice', 'srv', 'after')
            ->groupEnd()
            ->orderBy('tanggal', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Service selesai (status 4): tunai = bayar_tunai, sisanya dianggap transfer
     * (aturan TutupKasir).
     */
    protected function fetchService(int $unitId, string $tanggal): array
    {
        return $this->db->table('service')
            ->select('no_service, tanggal_selesai, harus_dibayar, bayar_tunai')
            ->where('unit_idunit', $unitId)
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->orderBy('tanggal_selesai', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * kas_masuk manual (bukan carry-over TutupKasir), untuk tanggal+unit ini.
     */
    protected function fetchKasMasuk(int $unitId, string $tanggal): array
    {
        return $this->db->table('kas_masuk')
            ->select('kas_masuk.idkas_masuk, kas_masuk.tanggal, kas_masuk.deskripsi, kas_masuk.jumlah, kas_masuk.penerima, kas_masuk.idbank, kas_masuk.created_on, kas_masuk.updated_on')
            ->where('kas_masuk.idunit', $unitId)
            ->where('DATE(kas_masuk.tanggal)', $tanggal)
            ->groupStart()
                ->where('kas_masuk.deskripsi IS NULL')
                ->orWhere('kas_masuk.deskripsi !=', 'kas awal')
            ->groupEnd()
            ->where('NOT EXISTS (
                SELECT 1 FROM transaksi_kas_bank tkb_m
                WHERE tkb_m.sumber_tipe = \'kas_masuk\'
                  AND tkb_m.sumber_id = kas_masuk.idkas_masuk
            )', null, false)
            ->orderBy('kas_masuk.tanggal', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * kas_keluar untuk tanggal+unit ini; kategori dipakai sebagai keterangan.
     */
    protected function fetchKasKeluar(int $unitId, string $tanggal): array
    {
        return $this->db->table('kas_keluar')
            ->select('kas_keluar.idkas_keluar, kas_keluar.tanggal, kas_keluar.deskripsi, kas_keluar.jumlah, kas_keluar.penerima, kas_keluar.idbank, kas_keluar.created_on, kas_keluar.updated_on, kategori_kas.kategori')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left')
            ->where('kas_keluar.idunit', $unitId)
            ->where('DATE(kas_keluar.tanggal)', $tanggal)
            ->groupStart()
                ->where('kas_keluar.deskripsi IS NULL')
                ->orWhere('kas_keluar.deskripsi !=', 'kas awal')
            ->groupEnd()
            ->where('NOT EXISTS (
                SELECT 1 FROM transaksi_kas_bank tkb_k
                WHERE tkb_k.sumber_tipe = \'kas_keluar\'
                  AND tkb_k.sumber_id = kas_keluar.idkas_keluar
            )', null, false)
            ->orderBy('kas_keluar.tanggal', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Ledger kas/bank. Baris asal kas_masuk/kas_keluar tetap ikut diambil karena
     * ledger-lah yang dipakai untuk tampilan; baris legacy-nya yang dibuang
     * (lihat assemble()) supaya satu transaksi tidak terhitung dua kali.
     */
    protected function fetchLedger(int $unitId, string $tanggal): array
    {
        return $this->db->table('transaksi_kas_bank')
            ->select('transaksi_kas_bank.tanggal, transaksi_kas_bank.jenis, transaksi_kas_bank.arah, transaksi_kas_bank.jumlah, transaksi_kas_bank.keterangan, transaksi_kas_bank.created_at, transaksi_kas_bank.sumber_tipe, transaksi_kas_bank.sumber_id, akun_kas_bank.nama_akun, akun_kas_bank.tipe')
            ->join('akun_kas_bank', 'akun_kas_bank.idakun_kas_bank = transaksi_kas_bank.akun_kas_bank_id', 'left')
            ->where('transaksi_kas_bank.unit_id', $unitId)
            ->where('transaksi_kas_bank.tanggal', $tanggal)
            ->orderBy('transaksi_kas_bank.created_at', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Apakah baris legacy ini sudah punya Salinan di transaksi_kas_bank?
     *
     * @param array<string, bool> $ledgerRefs
     */
    protected function sudahDiLedger(array $ledgerRefs, string $tipe, $id): bool
    {
        if ($id === null || $id === '') {
            return false;
        }

        return isset($ledgerRefs[$tipe . '#' . (int) $id]);
    }

    protected function row(?string $waktu, string $sumber, string $keterangan, int $jumlah, bool $transferInternal = false): array
    {
        return [
            'waktu' => $waktu,
            'jenis' => $sumber === 'Penjualan' || $sumber === 'Service' ? 'Kas Masuk' : $sumber,
            'sumber' => $sumber,
            'keterangan' => $keterangan,
            'nominal' => $jumlah,
            'transfer_internal' => $transferInternal,
        ];
    }

    /**
     * idbank NULL = tunai (konvensi TutupKasir).
     */
    protected function isTunai($row): bool
    {
        return $row->idbank === null || $row->idbank === '';
    }

    protected function isTransferInternal($row): bool
    {
        return in_array(
            strtoupper((string)($row->jenis ?? '')),
            ['TRANSFER_INTERNAL', 'PEMBAYARAN_ANTAR_UNIT'],
            true
        );
    }

    /**
     * Jam transaksi (HH:MM) atau null bila sumbernya tidak menyimpan jam.
     */
    protected function waktu($primary, $fallback = null): ?string
    {
        foreach ([$primary, $fallback] as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('H:i');
            }
            $timestamp = strtotime((string)$value);
            if ($timestamp !== false) {
                return date('H:i', $timestamp);
            }
        }

        return null;
    }

    /**
     * @param array $rows
     */
    protected function total(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += (int)($row['nominal'] ?? 0);
        }

        return $sum;
    }

    /**
     * Urut berdasarkan jam; baris tanpa jam di akhir.
     *
     * @param array $rows
     */
    protected function urutkan(array $rows): array
    {
        usort($rows, function ($a, $b) {
            $wa = $a['waktu'];
            $wb = $b['waktu'];
            if ($wa === $wb) {
                return $b['nominal'] <=> $a['nominal'];
            }
            if ($wa === null) {
                return 1;
            }
            if ($wb === null) {
                return -1;
            }
            return strcmp($wa, $wb);
        });

        return $rows;
    }
}