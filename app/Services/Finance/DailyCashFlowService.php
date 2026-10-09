<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * Arus kas harian untuk drill-down "Detail Omset Harian" (/omset_bulanan).
 *
 * SATU DEFINISI SAJA dengan TutupKasirSourceDefinition. Drill-down ini
 * menampilkan source table yang sama, bukan salinan lain dari ledger.
 *
 * Prinsip:
 *  - Read-only. Tidak membuat tabel, tidak menulis transaksi, tidak menyentuh
 *    perhitungan omset maupun cutoff Finance.
 *  - Sumber arus operasional (MASUK & KELUAR):
 *      MASUK  : penjualan non-"srv" (bayar_tunai / bayar_bank),
 *               service status 4 per tanggal_selesai
 *               (bayar_tunai / harus_dibayar - bayar_tunai).
 *      KELUAR : seluruh kas_keluar.
 *  - `transaksi_kas_bank` HANYA dibaca untuk perpindahan uang antar rekening
 *    fisik (`transfer_ref IS NOT NULL`) — Setor/Tarik, Pindah Saldo, dan
 *    pelunasan H/P antar unit yang menyentuh rekening berbeda. Predicate-nya
 *    dipinjam dari `KasBankSourceMovement::scopeInternalUnion()` supaya
 *    definisinya hanya ada di satu tempat. Mirror lama kas_masuk/kas_keluar
 *    yang tidak punya `transfer_ref` diabaikan supaya satu transaksi tidak
 *    tampil dua kali. Tidak ada lagi penggantian baris sumber dengan
 *    baris mirror.
 *  - Baris deskripsi 'kas awal' TIDAK dipakai sebagai penerimaan harian:
 *    itu saldo awal periode berikutnya. `kas_masuk` tidak menjadi subtotal
 *    masuk karena TutupKasir, core Finance movement, dan RekonDaily
 *    sama-sama tidak menghitungnya — kalau dihitung di sini, drill-down
 *    tidak akan sama dengan angka tutup kasir.
 *  - Perpindahan antar kas/rekening ditandai terpisah dan tidak masuk
 *    subtotal/net karena bukan pendapatan maupun beban.
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
            'kas_keluar' => $this->fetchKasKeluar($unitId, $tanggal),
            'ledger'    => $this->fetchLedger($unitId, $tanggal),
        ]);
    }

    /**
     * Menggolongkan baris mentah dari setiap sumber menjadi arus kas + subtotal.
     *
     * Dipisah dari query agar logika klasifikasi bisa diuji tanpa database.
     *
     * @param array $sources ['penjualan','service','kas_keluar','ledger']
     */
    public function assemble(array $sources): array
    {
        $masuk = ['tunai' => [], 'transfer' => []];
        $keluar = ['tunai' => [], 'transfer' => []];
        $internal = [];

        foreach ($sources['penjualan'] ?? [] as $row) {
            $referensi = trim((string)($row->kode_invoice ?? ''));
            $keterangan = $referensi !== ''
                ? 'Penjualan ' . $referensi
                : 'Penjualan';

            $tunai = (int)($row->bayar_tunai ?? 0);
            $transfer = (int)($row->bayar_bank ?? 0);

            if ($tunai !== 0) {
                $masuk['tunai'][] = $this->row(
                    $this->waktu($row->tanggal ?? null),
                    'Penjualan',
                    $keterangan,
                    $tunai
                );
            }
            if ($transfer !== 0) {
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

            // Residual transfer MENGIKUTI TutupKasir apa adanya: tidak di
            // max(0, ...). Overpay (residual negatif) harus mengurangi
            // subtotal transfer supaya totals sama dengan tutup kasir.
            $tunai = (int)($row->bayar_tunai ?? 0);
            $transfer = (int)($row->harus_dibayar ?? 0) - $tunai;

            if ($tunai !== 0) {
                $masuk['tunai'][] = $this->row($waktu, 'Service', $keterangan, $tunai);
            }
            if ($transfer !== 0) {
                $masuk['transfer'][] = $this->row($waktu, 'Service', $keterangan, $transfer);
            }
        }

        foreach ($sources['kas_keluar'] ?? [] as $row) {
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

        // Ledger HANYA boleh menyumbang perpindahan antar rekening. Query
        // fetchLedger() sudah memakai predicate scopeInternalUnion() yang sama;
        // gerbang di bawah menjaga kelas ini tetap aman kalau dipanggil dengan
        // row mentah.
        foreach ($sources['ledger'] ?? [] as $row) {
            if (! $this->isTransferInternal($row)) {
                continue;
            }

            $jumlah = (int)($row->jumlah ?? 0);
            $arah = strtoupper((string)($row->arah ?? ''));
            if ($jumlah === 0 || ($arah !== 'MASUK' && $arah !== 'KELUAR')) {
                continue;
            }

            $keterangan = trim((string)($row->keterangan ?? ''));
            if ($keterangan === '') {
                $keterangan = trim((string)($row->nama_akun ?? ''));
            }
            if ($keterangan === '') {
                $keterangan = 'Transfer antar kas/bank';
            }

            $internal[] = $this->row(
                $this->waktu($row->created_at ?? null),
                $arah === 'MASUK' ? 'Kas Masuk' : 'Kas Keluar',
                $keterangan,
                $jumlah,
                true
            );
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
     *
     * `kode_invoice` NULL juga TIDAK ikut: TutupKasir memakai `notLike`
     * yang di SQL mengecualikan NULL, sehingga drill-down harus sama.
     * Sebelumnya NULL ikut masuk dan summarised 6 baris / Rp2.750.000.
     *
     * CATATAN SEMANTIKA: side 'after' pada CodeIgniter4 versi ini = PREFIX,
     * jadi SQL-nya `NOT LIKE 'srv%'`, bukan `NOT LIKE '%srv'`. Invoice
     * berawalan SRV dibuang; kode berakhiran 'srv' tetap dihitung.
     */
    protected function fetchPenjualan(int $unitId, string $tanggal): array
    {
        return $this->db->table('penjualan')
            ->select('kode_invoice, tanggal, bayar_tunai, bayar_bank')
            ->where('unit_idunit', $unitId)
            ->where('DATE(tanggal)', $tanggal)
            ->notLike('kode_invoice', 'srv', 'after')
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
     * kas_keluar untuk tanggal+unit ini; kategori dipakai sebagai keterangan.
     *
     * Tidak menyaring deskripsi 'kas awal' dan tidak lagi memakai
     * `NOT EXISTS` mirror ledger: TutupKasir menjumlahkan seluruh
     * kas_keluar apa adanya, dan mirror ledger-lama tidak boleh
     * menggantikan/suppress baris sumber.
     */
    protected function fetchKasKeluar(int $unitId, string $tanggal): array
    {
        return $this->db->table('kas_keluar')
            ->select('kas_keluar.idkas_keluar, kas_keluar.tanggal, kas_keluar.deskripsi, kas_keluar.jumlah, kas_keluar.penerima, kas_keluar.idbank, kas_keluar.created_on, kas_keluar.updated_on, kategori_kas.kategori')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left')
            ->where('kas_keluar.idunit', $unitId)
            ->where('DATE(kas_keluar.tanggal)', $tanggal)
            ->orderBy('kas_keluar.tanggal', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Baris ledger yang memindahkan uang antar rekening fisik — drill-down.
     *
     * Bedanya dengan agregat di `KasBankSourceMovement::movementInternalUnion()`:
     * method itu menjumlahkan, sedangkan drill-down ini butuh detail per baris
     * (nama rekening, tipe, keterangan, jam) untuk ditampilkan. Karena itu
     * query-nya tetap di sini, TAPI Predicate-nya diambil dari
     * `KasBankSourceMovement::scopeInternalUnion()` — definisi
     * `transfer_ref IS NOT NULL` hanya boleh ada di satu tempat, supaya
     * Finance dan Cash Flow tidak diam-diam berbeda saat definisinya berubah.
     *
     * Cakupannya: TRANSFER_INTERNAL (Setor/Tarik + Pindah Saldo) DAN
     * `PEMBAYARAN_ANTAR_UNIT`. Keduanya benar-benar memindahkan uang antar
     * rekening. Yang TIDAK ikut: baris mirror legacy `PEMASUKAN`/
     * `PENGELUARAN` (tanpa `transfer_ref`), arus operasional (sudah dibaca
     * dari tabel source di atas), dan pasangan leg mutasi hak
     * (`SUMBER_TIPE_ATRIBUSI`) — pelunasan H/P rekening SAMA yang net-nol
     * terhadap fisik dan tidak boleh tampil sebagai perpindahan bank palsu.
     */
    protected function fetchLedger(int $unitId, string $tanggal): array
    {
        $builder = $this->db->table('transaksi_kas_bank')
            ->select('transaksi_kas_bank.tanggal, transaksi_kas_bank.jenis, transaksi_kas_bank.arah, transaksi_kas_bank.jumlah, transaksi_kas_bank.keterangan, transaksi_kas_bank.created_at, transaksi_kas_bank.sumber_tipe, transaksi_kas_bank.sumber_id, transaksi_kas_bank.transfer_ref, akun_kas_bank.nama_akun, akun_kas_bank.tipe')
            ->join('akun_kas_bank', 'akun_kas_bank.idakun_kas_bank = transaksi_kas_bank.akun_kas_bank_id', 'left')
            ->where('transaksi_kas_bank.unit_id', $unitId)
            ->where('transaksi_kas_bank.tanggal', $tanggal);

        return KasBankSourceMovement::scopeInternalUnion($builder)
            ->groupStart()
                ->where('transaksi_kas_bank.sumber_tipe IS NULL', null, false)
                ->orWhere('transaksi_kas_bank.sumber_tipe !=', KasBankSourceMovement::SUMBER_TIPE_ATRIBUSI)
            ->groupEnd()
            ->orderBy('transaksi_kas_bank.created_at', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Baris ini movement antar rekening fisik, bukan mirror legacy.
     *
     * Sama dengan predicate di `fetchLedger()`: `transfer_ref IS NOT NULL`,
     * yang ditulis `KasBankSourceMovement` — hanya Setor/Tarik, Pindah Saldo,
     * dan pelunasan H/P antar unit yang menyertainya.
     */
    protected function isTransferInternal($row): bool
    {
        $ref = $row->transfer_ref ?? null;

        return $ref !== null && $ref !== '';
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