<?php

namespace App\Services\Service;

use App\Models\ModelJurnal;
use App\Services\Finance\BankRekeningValidator;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Refund uang service yang sudah dibayar pelanggan.
 *
 * Satu transaksi: kunci service, catat kas keluar + jurnal seimbang, putuskan
 * kondisi tiap sparepart (normal maupun pengganti), lalu simpan audit refund di
 * `service`. Nominal selalu `dp_bayar + bayar`; angka dari form tidak dipercaya.
 * Syaratnya garansi masih aktif; service yang sudah diklaim tetap boleh direfund.
 */
class GaransiRefundService
{
    private const TEMPLATE = [
        'tunai' => 'refund_garansi_tunai',
        'bank'  => 'refund_garansi_bank',
    ];

    private const COA_DITERIMA_DI_MUKA = '2010302000';
    private const COA_KAS_BESAR        = '1010101000';
    private const COA_KAS_BANK         = '1010102000';

    // shortcut: pembayaran non-DP service belum dijurnal (#37), jadi seluruh
    // refund mendebit akun diterima di muka; pisahkan porsi pendapatan saat
    // posting pembayaran service sudah ada.

    private BaseConnection $db;
    private ModelJurnal $jurnal;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->jurnal = new ModelJurnal();
    }

    public function refundSiap(): bool
    {
        foreach (['service', 'service_sparepart', 'kas_keluar', 'jurnal', 'template_jurnal', 'kategori_kas', 'no_akun'] as $tabel) {
            if (! $this->db->tableExists($tabel)) {
                return false;
            }
        }

        foreach ([
            'refund_garansi_idkas_keluar', 'refund_garansi_tanggal', 'refund_garansi_metode',
            'refund_garansi_idbank', 'refund_garansi_referensi', 'refund_garansi_alasan',
            'refund_garansi_jumlah', 'refund_garansi_omset', 'refund_garansi_by', 'refund_garansi_at',
        ] as $field) {
            if (! $this->db->fieldExists($field, 'service')) {
                return false;
            }
        }

        if (! $this->db->fieldExists('jumlah_refund_garansi', 'service_sparepart')) {
            return false;
        }

        foreach (self::TEMPLATE as $template) {
            if ($this->db->table('template_jurnal')->where('kode_template', $template)->countAllResults() < 2) {
                return false;
            }
        }

        if ($this->db->table('kategori_kas')
            ->where('kategori', 'REFUND')
            ->where('jenis_kas', 'kas_keluar')
            ->countAllResults() === 0) {
            return false;
        }

        foreach ([self::COA_DITERIMA_DI_MUKA, self::COA_KAS_BESAR, self::COA_KAS_BANK] as $coa) {
            if ($this->db->table('no_akun')->where('no_akun', $coa)->countAllResults() === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int,string> $kondisi map idservice_sparepart => 'baik'|'rusak'
     *
     * @return array{idkas_keluar:int,jumlah:int,metode:string}
     */
    public function refund(
        int $idservice,
        string $metode,
        ?string $idbank,
        ?string $referensi,
        string $alasan,
        array $kondisi,
        int $inputBy,
        ?int $unitDiizinkan = null
    ): array {
        $metode    = strtolower(trim($metode));
        $alasan    = trim($alasan);
        $referensi = $referensi === null ? null : trim($referensi);
        $idbank    = $idbank === null ? null : trim($idbank);

        if ($idservice <= 0) {
            throw new \RuntimeException('Service tidak valid.');
        }
        if (! isset(self::TEMPLATE[$metode])) {
            throw new \RuntimeException('Metode refund tidak valid.');
        }
        if ($alasan === '' || mb_strlen($alasan) > 255) {
            throw new \RuntimeException('Alasan refund wajib diisi (maksimal 255 karakter).');
        }
        if ($referensi !== null && mb_strlen($referensi) > 100) {
            throw new \RuntimeException('Referensi refund maksimal 100 karakter.');
        }
        if (! $this->refundSiap()) {
            throw new \RuntimeException('Skema atau jurnal refund garansi belum siap. Jalankan migrasi refund garansi atau hubungi admin.');
        }

        $this->db->transBegin();

        try {
            $serviceTable   = $this->db->prefixTable('service');
            $pelangganTable = $this->db->prefixTable('pelanggan');
            $service = $this->db->query(
                "SELECT s.*, p.nama AS nama_pelanggan
                   FROM {$serviceTable} s
                   LEFT JOIN {$pelangganTable} p ON p.id_pelanggan = s.pelanggan_id_pelanggan
                  WHERE s.idservice = ?
                  FOR UPDATE",
                [$idservice]
            )->getRow();

            if ($service === null) {
                throw new \RuntimeException('Service tidak ditemukan.');
            }
            if (! GaransiClaimService::isWarrantyActive($service)) {
                throw new \RuntimeException('Garansi service ini sudah kadaluarsa atau belum aktif.');
            }

            $jumlah = (int) ($service->dp_bayar ?? 0) + (int) ($service->bayar ?? 0);
            if ($jumlah <= 0) {
                throw new \RuntimeException('Service ini tidak punya pembayaran untuk direfund.');
            }
            if (! empty($service->refund_garansi_at)) {
                throw new \RuntimeException('Service ini sudah direfund.');
            }

            $unitId = (int) ($service->unit_idunit ?? 0);
            if ($unitDiizinkan !== null && $unitId !== $unitDiizinkan) {
                throw new \RuntimeException('Service ini bukan milik unit Anda.');
            }

            $bankId = null;
            if ($metode === 'bank') {
                if ($referensi === null || $referensi === '') {
                    throw new \RuntimeException('Referensi transfer refund wajib diisi.');
                }

                $bankId = $idbank;
                $validasiBank = (new BankRekeningValidator())->validateUntukUnit(
                    $bankId,
                    $unitId,
                    session('ID_JABATAN') === null || session('ID_JABATAN') === ''
                        ? null
                        : (int) session('ID_JABATAN'),
                    false
                );
                if (! $validasiBank['ok']) {
                    throw new \RuntimeException('Rekening refund tidak dapat digunakan: ' . $validasiBank['alasan']);
                }
            }

            $sparepart = $this->db->table('service_sparepart')
                ->where('service_idservice', $idservice)
                ->groupStart()
                    ->where('jumlah >', 0)
                    ->orWhere('jumlah_tambahan_garansi >', 0)
                ->groupEnd()
                ->orderBy('idservice_sparepart', 'ASC')
                ->get()
                ->getResult();

            $idBaris = [];
            foreach ($sparepart as $baris) {
                $idBaris[] = (int) $baris->idservice_sparepart;
            }

            $sudahReturSupplier = [];
            if ($idBaris !== [] && $this->db->tableExists('retur_klaim_item')) {
                $retur = $this->db->table('retur_klaim_item')
                    ->whereIn('service_sparepart_idservice_sparepart', $idBaris)
                    ->get()
                    ->getResult();
                foreach ($retur as $barisRetur) {
                    $sudahReturSupplier[(int) $barisRetur->service_sparepart_idservice_sparepart] = true;
                }
            }

            $barisRefund = [];
            foreach ($sparepart as $baris) {
                $id = (int) $baris->idservice_sparepart;
                $qtyNormal = max(0, (int) ($baris->jumlah ?? 0));
                $qtyKlaim  = max(0, (int) ($baris->jumlah_tambahan_garansi ?? 0));

                if (! isset($kondisi[$id])) {
                    throw new \RuntimeException('Kondisi setiap sparepart refund wajib dipilih.');
                }

                $pilihan = (string) $kondisi[$id];
                if (! in_array($pilihan, ['baik', 'rusak'], true)) {
                    throw new \RuntimeException('Kondisi sparepart tidak valid.');
                }

                $statusRetur = (string) ($baris->status_retur ?? '');
                $hppLoss     = (int) ($baris->hpp_omset_garansi ?? 0);

                if (isset($sudahReturSupplier[$id]) || ($qtyKlaim > 0 && $statusRetur === 'diterima')) {
                    throw new \RuntimeException('Sparepart klaim sudah diretur ke supplier.');
                }

                if ($pilihan === 'baik' && $qtyKlaim > 0) {
                    if ($hppLoss > 0 || in_array($statusRetur, ['tidak_bisa', 'ditolak'], true)) {
                        throw new \RuntimeException('Sparepart ini sudah tercatat rusak. Ubah kondisinya lewat menu Retur Barang Rusak.');
                    }
                }

                $barisRefund[] = [
                    'row'        => $baris,
                    'qty_normal' => $qtyNormal,
                    'qty_klaim'  => $qtyKlaim,
                    'kondisi'    => $pilihan,
                ];
            }

            if (count($kondisi) !== count($barisRefund)) {
                throw new \RuntimeException('Kondisi sparepart tidak sesuai dengan daftar sparepart service.');
            }

            // Porsi omset = gross profit yang dulu masuk saat sparepart dijual.
            // Barang baik kembali ke stok, jadi HPP-nya ikut dibalik; barang
            // rusak tidak kembali, jadi HPP tetap menjadi beban.
            $omsetRefund = 0;
            foreach ($barisRefund as $item) {
                $qtyNormal = (int) $item['qty_normal'];
                if ($qtyNormal <= 0) {
                    continue;
                }

                $baris   = $item['row'];
                $revenue = (int) ($baris->sub_total ?? 0);
                if ($revenue === 0) {
                    $revenue = (int) ($baris->harga_penjualan ?? 0) * $qtyNormal
                        - (int) ($baris->diskon_penjualan ?? 0);
                }

                $hpp = (int) ($baris->hpp_penjualan ?? 0) * $qtyNormal;
                $omsetRefund += $item['kondisi'] === 'baik' ? $revenue - $hpp : $revenue;
            }

            $kategoriRefund = $this->db->table('kategori_kas')
                ->where('kategori', 'REFUND')
                ->where('jenis_kas', 'kas_keluar')
                ->get()
                ->getRow();
            if (! $kategoriRefund) {
                throw new \RuntimeException('Kategori kas keluar REFUND belum tersedia.');
            }

            $jumlah = (int) $service->dp_bayar + (int) $service->bayar;
            $waktu  = (new \DateTime('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
            $tanggal = substr($waktu, 0, 10);
            $deskripsi = 'Refund garansi ' . trim((string) ($service->no_service ?? ('#' . $idservice))) . ' - ' . $alasan;

            $tersimpan = $this->db->table('kas_keluar')->insert([
                'tanggal'             => $tanggal,
                'no_akun'             => self::COA_DITERIMA_DI_MUKA,
                'kategori_idkategori' => (int) $kategoriRefund->idkategori_kas,
                'deskripsi'           => $deskripsi,
                'jumlah'              => $jumlah,
                'jenis'               => 'debet',
                'penerima'            => trim((string) ($service->nama_pelanggan ?? '')),
                'idbank'              => $bankId,
                'idunit'              => $unitId,
                'created_on'          => $waktu,
            ]);

            if (! $tersimpan) {
                throw new \RuntimeException('Gagal mencatat kas keluar refund garansi.');
            }

            $idKasKeluar = (int) $this->db->insertID();
            if ($idKasKeluar <= 0) {
                throw new \RuntimeException('Nomor transaksi kas keluar refund tidak terbentuk.');
            }

            $maks = $this->db->table('jurnal')->selectMax('idjurnal', 'maks')->get()->getRow();
            $jurnalSebelum = (int) ($maks->maks ?? 0);
            $this->jurnal->insertJurnal(
                $tanggal,
                self::TEMPLATE[$metode],
                [$jumlah],
                $deskripsi,
                $idKasKeluar,
                'kas_keluar',
                $unitId
            );

            $ringkas = $this->jurnal->ringkasByReferensi('kas_keluar', $idKasKeluar, $jurnalSebelum);
            if ($ringkas['jumlah'] !== 2 || ! $ringkas['seimbang']
                || $ringkas['debet'] !== $jumlah || $ringkas['kredit'] !== $jumlah) {
                throw new \RuntimeException('Jurnal refund garansi gagal terbentuk seimbang. Refund dibatalkan.');
            }

            foreach ($barisRefund as $item) {
                $baris       = $item['row'];
                $idSparepart = (int) $baris->idservice_sparepart;
                $statusRetur = (string) ($baris->status_retur ?? '');

                if ($item['kondisi'] === 'baik') {
                    if ($item['qty_klaim'] > 0) {
                        $this->db->table('service_sparepart')
                            ->where('idservice_sparepart', $idSparepart)
                            ->update(['jumlah_refund_garansi' => $item['qty_klaim']]);
                    }
                    if ($item['qty_normal'] > 0) {
                        $this->catatReturSparepartNormal(
                            $idservice,
                            $baris,
                            $item['qty_normal'],
                            $waktu,
                            $inputBy,
                            $unitId
                        );
                    }
                    continue;
                }

                $data = [];
                if ($item['qty_klaim'] > 0) {
                    $data['jumlah_refund_garansi'] = 0;

                    if (! in_array($statusRetur, ['tidak_bisa', 'ditolak'], true)) {
                        $data['status_retur'] = 'tidak_bisa';
                    }
                    if ((int) ($baris->hpp_omset_garansi ?? 0) === 0) {
                        $data['hpp_omset_garansi'] = (int) ($baris->hpp_penjualan_garansi ?? 0);
                        $data['tanggal_hpp_klaim'] = $tanggal;
                    }

                    $this->db->table('service_sparepart')
                        ->where('idservice_sparepart', $idSparepart)
                        ->update($data);
                }

                $qtyRusak = $item['qty_normal'] + $item['qty_klaim'];
                if ($qtyRusak <= 0) {
                    continue;
                }

                $this->db->table('barang_rusak_claim')->insert([
                    'service_idservice' => $idservice,
                    'barang_idbarang'   => (int) ($baris->barang_idbarang ?? 0),
                    'jumlah'            => $qtyRusak,
                    'keterangan'        => 'Refund: ' . $alasan,
                    'unit_idunit'       => $unitId,
                    'input_by'          => $inputBy > 0 ? $inputBy : null,
                    'created_at'        => $waktu,
                ]);
            }

            $this->db->table('service')
                ->where('idservice', $idservice)
                ->where('refund_garansi_at', null)
                ->where('refund_garansi_idkas_keluar', null)
                ->update([
                    'refund_garansi_idkas_keluar' => $idKasKeluar,
                    'refund_garansi_tanggal'      => $tanggal,
                    'refund_garansi_metode'       => $metode,
                    'refund_garansi_idbank'       => $bankId,
                    'refund_garansi_referensi'    => $metode === 'bank' ? $referensi : null,
                    'refund_garansi_alasan'       => $alasan,
                    'refund_garansi_jumlah'       => $jumlah,
                    'refund_garansi_omset'        => $omsetRefund,
                    'refund_garansi_by'           => $inputBy > 0 ? $inputBy : null,
                    'refund_garansi_at'           => $waktu,
                ]);

            if ($this->db->affectedRows() !== 1) {
                throw new \RuntimeException('Service ini sudah direfund oleh pengguna lain. Muat ulang daftar garansi.');
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Refund garansi gagal disimpan.');
            }
            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Refund garansi gagal di-commit.');
            }

            return [
                'idkas_keluar' => $idKasKeluar,
                'jumlah'       => $jumlah,
                'metode'       => $metode,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Sparepart normal keluar lewat `detail_penjualan`, bukan `jumlah_tambahan_garansi`.
     * Barang baik dikembalikan sebagai retur pelanggan; detail penjualan asal tetap
     * utuh supaya riwayat dan KPI tanggal penjualan tidak ditulis ulang.
     */
    private function catatReturSparepartNormal(
        int $idservice,
        object $baris,
        int $qty,
        string $waktu,
        int $inputBy,
        int $unitId
    ): void {
        $barangId = (int) ($baris->barang_idbarang ?? 0);
        if ($qty <= 0 || $barangId <= 0) {
            return;
        }

        $detail = $this->db->table('detail_penjualan dp')
            ->select('dp.iddetail_penjualan, dp.jumlah, dp.satuan_jual')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->where('p.service_idservice', $idservice)
            ->where('dp.barang_idbarang', $barangId)
            ->orderBy('dp.iddetail_penjualan', 'ASC')
            ->get()
            ->getRow();

        // Tanpa detail penjualan, stok barang ini tidak pernah keluar lewat
        // invoice service (data lama atau sudah dihapus manual), jadi tidak ada
        // stok yang perlu dikembalikan.
        if ($detail === null) {
            return;
        }

        $detailId = (int) $detail->iddetail_penjualan;
        $sudahRetur = (int) ($this->db->table('retur_pelanggan')
            ->selectSum('jumlah', 'total')
            ->where('detail_penjualan_iddetail_penjualan', $detailId)
            ->get()
            ->getRow()->total ?? 0);

        $qty = min($qty, max(0, (int) $detail->jumlah - $sudahRetur));
        if ($qty <= 0) {
            return;
        }

        $tersimpan = $this->db->table('retur_pelanggan')->insert([
            'no_retur_pelanggan'                     => 'RFS' . $idservice . '-' . $barangId . '-' . date('YmdHis', strtotime($waktu)),
            'tanggal'                                => $waktu,
            'jumlah'                                 => $qty,
            'satuan'                                 => $detail->satuan_jual ?? ($baris->satuan_jual ?? 'pcs'),
            'barang_idbarang'                        => $barangId,
            'detail_penjualan_iddetail_penjualan'    => $detailId,
            'unit_idunit'                            => $unitId,
            'input_by'                               => $inputBy > 0 ? (string) $inputBy : null,
        ]);

        if (! $tersimpan) {
            throw new \RuntimeException('Gagal mencatat pengembalian stok sparepart refund.');
        }
    }
}
