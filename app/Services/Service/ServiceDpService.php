<?php

namespace App\Services\Service;

use App\Models\ModelBank;
use App\Models\ModelJurnal;
use App\Models\ModelService;
use App\Models\ModelServiceDp;
use App\Services\Finance\BankRekeningValidator;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * ServiceDpService — pencatatan DP (uang muka) service ke ledger `service_dp`.
 *
 * Ruang lingkup (tahap fondasi):
 *  - catatDp(): validasi + INSERT baris ledger + jurnal DP + proyeksi
 *    `service.dp_bayar`.
 *  - refund(): refund penuh untuk service yang batal sebelum dikerjakan,
 *    dicatat ke kas keluar dan jurnal pembalik tanpa menghapus penerimaan asal.
 *  - totalDpAktif(): jumlah DP berstatus aktif per service.
 *  - proyeksiDpBayar(): tulis ulang `service.dp_bayar` dari total DP aktif.
 *
 * BUKAN ledger pelunasan. Pelunasan dan invoice tetap memakai alur service
 * yang sudah ada.
 *
 * TRANSAKSI
 * ---------
 * Service ini TIDAK membuka transaksi sendiri. Semua tulisan memakai satu
 * koneksi (default group, yang di-cache CI sehingga identik dengan koneksi
 * pemanggil). Pemanggil — nantinya `Service::insert_service` — WAJIB membungkus
 * pemanggilan `catatDp()` dalam satu transaksi bersama insert service, agar
 * tidak ada data parsial. Rollback pemanggil otomatis membatalkan ledger dan
 * jurnal DP.
 *
 * JURNAL
 * ------
 * `ModelJurnal::insertJurnal()` selalu mengembalikan true walau template tidak
 * ada (loop tidak jalan). Karena itu keberhasilannya DIVERIFIKASI eksplisit:
 * harus ada baris jurnal untuk (tabel_referensi='service_dp', id_referensi=id
 * baris ledger) dengan total debet == total kredit == nominal DP.
 *
 * IDEMPOTENSI
 * -----------
 * Pemanggil boleh mengirim `kode_dp` sebagai kunci idempotensi. Bila kode itu
 * sudah ada, pemanggilan tidak menulis apa pun dan mengembalikan status
 * 'duplikat' — mencegah baris ledger ganda akibat pengulangan request.
 */
class ServiceDpService
{
    /** Pemetaan metode -> kode template jurnal DP yang sudah ada di proyek. */
    public const TEMPLATE = [
        'tunai'    => 'dp_service_tunai',
        'transfer' => 'dp_service_bank',
    ];

    /** Template pembalik kewajiban DP saat dana dikembalikan penuh. */
    public const TEMPLATE_REFUND = [
        'tunai'    => 'refund_dp_service_tunai',
        'transfer' => 'refund_dp_service_bank',
    ];

    private const COA_PENDAPATAN_DITERIMA_DI_MUKA = '2010302000';

    public const STATUS_OK = 'ok';
    public const STATUS_SKIP = 'skip';
    public const STATUS_DUPLIKAT = 'duplikat';

    private const TABEL_REFERENSI = 'service_dp';

    /** @var BaseConnection */
    private $db;

    /** @var ModelServiceDp */
    private $DpModel;

    /** @var ModelService */
    private $ServiceModel;

    /** @var ModelJurnal */
    private $JurnalModel;

    /** @var ModelBank */
    private $BankModel;

    public function __construct(
        ?BaseConnection $db = null,
        ?ModelServiceDp $dpModel = null,
        ?ModelService $serviceModel = null,
        ?ModelJurnal $jurnalModel = null,
        ?ModelBank $bankModel = null
    ) {
        $this->db           = $db ?? Database::connect();
        $this->DpModel      = $dpModel ?? new ModelServiceDp();
        $this->ServiceModel = $serviceModel ?? new ModelService();
        $this->JurnalModel  = $jurnalModel ?? new ModelJurnal();
        $this->BankModel    = $bankModel ?? new ModelBank();
    }

    /**
     * Koneksi database yang dipakai service (untuk pemanggil yang perlu
     * menyatukan transaksi).
     */
    public function getConnection(): BaseConnection
    {
        return $this->db;
    }

    /**
     * Catat satu DP untuk sebuah service.
     *
     * WAJIB dipanggil di dalam transaksi database yang sama dengan penyimpanan
     * service (lihat docblock kelas). Bila validasi atau penulisan jurnal gagal,
     * melempar RuntimeException agar pemanggil me-rollback.
     *
     * Nominal 0 BUKAN error: tidak menghasilkan baris ledger maupun jurnal
     * (status 'skip'). Nominal negatif DITOLAK.
     *
     * @param array{
     *     service_idservice:int,
     *     jumlah:int,
     *     metode?:string,
     *     bank_idbank?:string|int|null,
     *     referensi?:string|null,
     *     kode_dp?:string|null,
     *     tanggal?:string,
     *     keterangan?:string,
     *     unit_id?:int,
     *     input_by?:int
     * } $opts
     *
     * @return array{status:string,id:?int,total:int,kode_dp:?string}
     */
    public function catatDp(array $opts): array
    {
        $idservice  = (int) ($opts['service_idservice'] ?? 0);
        $jumlah     = (int) ($opts['jumlah'] ?? 0);
        $metode     = $this->normalisasiMetode($opts['metode'] ?? 'tunai');
        $bankIdbank = $this->teksOpsional($opts['bank_idbank'] ?? null);
        $referensi  = $this->teksOpsional($opts['referensi'] ?? null);
        $kodeDiminta = $this->teksOpsional($opts['kode_dp'] ?? null);
        $keterangan = (string) ($opts['keterangan'] ?? 'Pembayaran Uang Muka Jasa Service');
        $unitId     = (int) ($opts['unit_id'] ?? 0);
        $inputBy    = (int) ($opts['input_by'] ?? 0);
        $tanggal    = (string) ($opts['tanggal'] ?? date('Y-m-d'));

        if ($jumlah < 0) {
            throw new \RuntimeException('DP service: nominal DP tidak boleh negatif.');
        }

        // DP 0: tidak ada baris ledger maupun jurnal.
        if ($jumlah === 0) {
            return [
                'status'  => self::STATUS_SKIP,
                'id'      => null,
                'total'   => 0,
                'kode_dp' => null,
            ];
        }

        $this->validasi($idservice, $metode, $bankIdbank, $unitId);

        // Kunci idempotensi: kode yang sudah ada tidak ditulis ulang.
        if ($kodeDiminta !== null && $this->DpModel->kodeDpSudahAda($kodeDiminta)) {
            return [
                'status'  => self::STATUS_DUPLIKAT,
                'id'      => null,
                'total'   => $this->totalDpAktif($idservice),
                'kode_dp' => $kodeDiminta,
            ];
        }

        $template = self::TEMPLATE[$metode];
        $this->pastikanTemplateAda($template);

        $kodeDp = $kodeDiminta ?? $this->generateKodeDp($idservice);
        $now    = date('Y-m-d H:i:s');

        $template = self::TEMPLATE[$metode];
        $this->pastikanTemplateAda($template);

        $kodeDp = $kodeDiminta ?? $this->generateKodeDp($idservice);
        $now    = date('Y-m-d H:i:s');

        try {
            $id = $this->DpModel->insert([
                'service_idservice' => $idservice,
                'kode_dp'           => $kodeDp,
                'tanggal'           => date('Y-m-d', strtotime($tanggal)),
                'jumlah'            => $jumlah,
                'metode'            => $metode,
                // Bank & referensi hanya relevan untuk transfer.
                'bank_idbank'       => $metode === 'transfer' ? $bankIdbank : null,
                'referensi'         => $metode === 'transfer' ? $referensi : null,
                'status'            => 'aktif',
                'keterangan'        => $keterangan,
                'unit_id'           => $unitId,
                'input_by'          => $inputBy > 0 ? $inputBy : null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'ServiceDpService::catatDp gagal insert: ' . $e->getMessage());
            throw new \RuntimeException('DP service: gagal menyimpan baris ledger DP.');
        }

        if (! $id) {
            $error = $this->db->error();
            log_message('error', 'ServiceDpService::catatDp gagal insert ke service_dp: ' . json_encode($error));
            throw new \RuntimeException('DP service: gagal menyimpan baris ledger DP.');
        }

        $this->JurnalModel->insertJurnal($tanggal, $template, [$jumlah], $keterangan, $id, self::TABEL_REFERENSI, $unitId);
        $this->pastikanJurnalSeimbang((int) $id, $jumlah);

        $total = $this->proyeksiDpBayar($idservice);

        return [
            'status'  => self::STATUS_OK,
            'id'      => (int) $id,
            'total'   => $total,
            'kode_dp' => $kodeDp,
        ];
    }

    /**
     * Refund penuh DP sekaligus membatalkan service yang belum dikerjakan.
     *
     * Satu transaksi atomik membuat sumber kas keluar, jurnal pembalik DP,
     * metadata audit, dan proyeksi `service.dp_bayar`. Dana dikembalikan melalui
     * metode/rekening asal; syarat status aktif + conditional update mencegah
     * refund ganda saat request bersamaan.
     *
     * @return array{status:string,idkas_keluar:int,jumlah:int,metode:string}
     */
    public function refund(
        int $idDp,
        string $alasan,
        ?string $referensi,
        int $inputBy,
        ?int $unitDiizinkan = null
    ): array {
        $alasan = trim($alasan);
        $referensi = $referensi === null ? null : trim($referensi);

        if ($idDp <= 0) {
            throw new \RuntimeException('DP tidak valid.');
        }
        if ($alasan === '' || mb_strlen($alasan) > 120) {
            throw new \RuntimeException('Alasan refund wajib diisi (maksimal 120 karakter).');
        }
        if ($referensi !== null && mb_strlen($referensi) > 100) {
            throw new \RuntimeException('Referensi refund maksimal 100 karakter.');
        }

        if (! $this->refundSiap()) {
            throw new \RuntimeException('Skema atau jurnal refund DP belum siap. Jalankan migrasi refund DP atau hubungi admin.');
        }

        $this->db->transBegin();

        try {
            $dp = $this->db->table('service_dp sd')
                ->select('sd.*, s.no_service, s.unit_idunit, s.status_service, s.bayar_tunai, p.nama AS nama_pelanggan')
                ->join('service s', 's.idservice = sd.service_idservice')
                ->join('pelanggan p', 'p.id_pelanggan = s.pelanggan_id_pelanggan', 'left')
                ->where('sd.id', $idDp)
                ->get()
                ->getRow();

            if (! $dp) {
                throw new \RuntimeException('DP tidak ditemukan.');
            }

            $unitId = (int) ($dp->unit_idunit ?? 0);
            if ($unitId <= 0 || ($unitDiizinkan !== null && $unitId !== $unitDiizinkan)) {
                throw new \RuntimeException('Anda tidak memiliki akses ke unit service ini.');
            }
            $statusService = (int) ($dp->status_service ?? 0);
            if (! in_array($statusService, [1, 2, 90], true)) {
                throw new \RuntimeException('Refund DP hanya tersedia selama service belum masuk tahap siap diambil atau selesai.');
            }
            if ((string) $dp->status !== 'aktif' || ! empty($dp->refund_idkas_keluar) || ! empty($dp->refund_at)) {
                throw new \RuntimeException('DP ini sudah dibatalkan atau direfund.');
            }

            $bayarTunaiSelainDp = (int) ($dp->bayar_tunai ?? 0);
            $bankRow = $this->db->table('pembayaran_bank')
                ->selectSum('jumlah', 'total')
                ->where('tabel_referensi', 'service_baru')
                ->where('id_referensi', (int) $dp->service_idservice)
                ->get()
                ->getRow();
            $bayarBankSelainDp = (int) ($bankRow->total ?? 0);
            if ($bayarTunaiSelainDp > 0 || $bayarBankSelainDp > 0) {
                throw new \RuntimeException(
                    'Service ini juga memiliki pembayaran selain DP (tunai Rp '
                    . number_format($bayarTunaiSelainDp, 0, ',', '.')
                    . ', transfer Rp ' . number_format($bayarBankSelainDp, 0, ',', '.')
                    . '). Selesaikan refund pembayaran tersebut sebelum membatalkan service.'
                );
            }

            // Stok yang masih keluar ditunjukkan oleh detail_penjualan milik
            // invoice service. Seperti hard delete di menu Sparepart Keluar,
            // proses refund mengembalikan semua detail itu dan membersihkan
            // invoice/jurnal terkait dalam transaksi yang sama.
            if (! $this->db->fieldExists('service_idservice', 'penjualan')) {
                $adaSparepartHistoris = $this->db->tableExists(
                    $this->db->getPrefix() . 'service_sparepart',
                    false
                ) && $this->db->table('service_sparepart')
                    ->where('service_idservice', (int) $dp->service_idservice)
                    ->countAllResults() > 0;

                if ($adaSparepartHistoris) {
                    throw new \RuntimeException(
                        'Skema database belum menautkan invoice sparepart ke service. Jalankan migrasi terbaru sebelum refund DP.'
                    );
                }

                $sparepartBelumDiretur = 0;
            } else {
                $sparepartBelumDiretur = $this->db->table('detail_penjualan dp')
                    ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
                    ->where('p.service_idservice', (int) $dp->service_idservice)
                    ->countAllResults();
            }

            $jumlah = (int) ($dp->jumlah ?? 0);
            $metode = strtolower(trim((string) ($dp->metode ?? '')));
            if ($jumlah <= 0 || ! isset(self::TEMPLATE_REFUND[$metode])) {
                throw new \RuntimeException('Nominal atau metode DP tidak valid untuk refund.');
            }
            if ($metode === 'transfer' && ($referensi === null || $referensi === '')) {
                throw new \RuntimeException('Referensi transfer refund wajib diisi.');
            }

            $bankId = null;
            if ($metode === 'transfer') {
                $bankId = trim((string) ($dp->bank_idbank ?? ''));
                if ($bankId === '') {
                    throw new \RuntimeException('Rekening asal DP transfer tidak tersedia. Refund dibatalkan.');
                }

                $validasiBank = (new BankRekeningValidator())->validateUntukUnit(
                    $bankId,
                    $unitId,
                    session('ID_JABATAN') === null || session('ID_JABATAN') === ''
                        ? null
                        : (int) session('ID_JABATAN'),
                    false
                );
                if (! $validasiBank['ok']) {
                    throw new \RuntimeException('Rekening asal DP tidak dapat digunakan untuk refund: ' . $validasiBank['alasan']);
                }
            }

            $kategoriRefund = $this->db->table('kategori_kas')
                ->where('kategori', 'REFUND')
                ->where('jenis_kas', 'kas_keluar')
                ->get()
                ->getRow();
            if (! $kategoriRefund) {
                throw new \RuntimeException('Kategori kas keluar REFUND belum tersedia.');
            }
            if (! $this->db->table('no_akun')
                ->where('no_akun', self::COA_PENDAPATAN_DITERIMA_DI_MUKA)
                ->countAllResults()) {
                throw new \RuntimeException('Akun Pendapatan Diterima di Muka belum tersedia di daftar COA.');
            }

            $template = self::TEMPLATE_REFUND[$metode];
            $jumlahTemplate = $this->db->table('template_jurnal')
                ->where('kode_template', $template)
                ->countAllResults();
            if ($jumlahTemplate < 2) {
                throw new \RuntimeException('Template jurnal refund DP belum lengkap. Jalankan migrasi refund DP.');
            }

            if ($sparepartBelumDiretur > 0) {
                $this->returSparepartService((int) $dp->service_idservice);
            }

            $wib = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
            $tanggal = $wib->format('Y-m-d');
            $waktu = $wib->format('Y-m-d H:i:s');

            // Batalkan ticket dalam transaksi refund yang sama. Conditional
            // update menolak refund jika ticket selesai bersamaan di request lain.
            if ($statusService !== 90) {
                $this->db->table('service')
                    ->where('idservice', (int) $dp->service_idservice)
                    ->whereIn('status_service', [1, 2, 3])
                    ->update([
                        'status_service' => 90,
                        'tanggal_bisa_diambil' => $waktu,
                        'updated_at' => $waktu,
                    ]);

                if ($this->db->affectedRows() !== 1) {
                    throw new \RuntimeException('Status service berubah. Muat ulang halaman sebelum refund.');
                }
            }

            $kodeDp = trim((string) ($dp->kode_dp ?? ('DP-' . $idDp)));
            $deskripsi = 'Refund DP ' . $kodeDp . ' - ' . $alasan;
            $namaPenerima = trim((string) ($dp->nama_pelanggan ?? ''));

            // Kunci refund di transaksi tanpa mengubah status penerimaan DP.
            // Status tetap aktif agar kas masuk pada tanggal DP diterima tidak
            // hilang dari histori; refund_idkas_keluar menjadi jejak refund dan
            // penanda bahwa saldo DP ini tidak bisa dipakai untuk settlement.
            $this->db->table('service_dp')
                ->where('id', $idDp)
                ->where('status', 'aktif')
                ->where('refund_idkas_keluar', null)
                ->where('refund_at', null)
                ->update([
                    'refund_at'  => $waktu,
                    'refund_by'  => $inputBy > 0 ? $inputBy : null,
                    'updated_at' => $waktu,
                ]);

            if ($this->db->affectedRows() !== 1) {
                throw new \RuntimeException('DP ini sudah diproses refund oleh pengguna lain. Muat ulang daftar DP.');
            }

            $tersimpan = $this->db->table('kas_keluar')->insert([
                'tanggal'             => $tanggal,
                'no_akun'             => self::COA_PENDAPATAN_DITERIMA_DI_MUKA,
                'kategori_idkategori' => (int) $kategoriRefund->idkategori_kas,
                'deskripsi'           => $deskripsi,
                'jumlah'              => $jumlah,
                'jenis'               => 'debet',
                'penerima'            => $namaPenerima,
                'idbank'              => $bankId,
                'idunit'              => $unitId,
                'created_on'          => $waktu,
            ]);

            if (! $tersimpan) {
                throw new \RuntimeException('Gagal mencatat kas keluar refund DP.');
            }

            $idKasKeluar = (int) $this->db->insertID();
            if ($idKasKeluar <= 0) {
                throw new \RuntimeException('Nomor transaksi kas keluar refund tidak terbentuk.');
            }

            $maks = $this->db->table('jurnal')->selectMax('idjurnal', 'maks')->get()->getRow();
            $jurnalSebelum = (int) ($maks->maks ?? 0);
            $this->JurnalModel->insertJurnal(
                $tanggal,
                $template,
                [$jumlah],
                $deskripsi,
                $idKasKeluar,
                'kas_keluar',
                $unitId
            );

            $ringkas = $this->JurnalModel->ringkasByReferensi('kas_keluar', $idKasKeluar, $jurnalSebelum);
            if ($ringkas['jumlah'] !== 2 || ! $ringkas['seimbang']
                || $ringkas['debet'] !== $jumlah || $ringkas['kredit'] !== $jumlah) {
                throw new \RuntimeException('Jurnal refund DP gagal terbentuk seimbang. Refund dibatalkan.');
            }

            $this->db->table('service_dp')
                ->where('id', $idDp)
                ->update([
                    'refund_idkas_keluar' => $idKasKeluar,
                    'refund_tanggal'      => $tanggal,
                    'refund_metode'       => $metode,
                    'refund_bank_idbank'  => $bankId,
                    'refund_referensi'    => $metode === 'transfer' ? $referensi : null,
                    'refund_alasan'       => $alasan,
                    'refund_by'           => $inputBy > 0 ? $inputBy : null,
                    'refund_at'           => $waktu,
                    'updated_at'          => $waktu,
                ]);

            $saldoDpAktif = $this->proyeksiDpBayar((int) $dp->service_idservice);
            $service = $this->db->table('service')
                ->select('dp_bayar')
                ->where('idservice', (int) $dp->service_idservice)
                ->get()
                ->getRow();
            if (! $service || (int) $service->dp_bayar !== $saldoDpAktif) {
                throw new \RuntimeException('Gagal memperbarui saldo DP service setelah refund.');
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Refund DP gagal disimpan.');
            }

            if (! $this->db->transCommit()) {
                throw new \RuntimeException('Refund DP gagal di-commit.');
            }

            return [
                'status'       => 'ok',
                'idkas_keluar' => $idKasKeluar,
                'jumlah'       => $jumlah,
                'metode'       => $metode,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** Return seluruh detail Sparepart Keluar milik service via mekanisme bersama. */
    private function returSparepartService(int $serviceId): void
    {
        $details = $this->db->table('detail_penjualan dp')
            ->select('dp.iddetail_penjualan')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->where('p.service_idservice', $serviceId)
            ->orderBy('dp.iddetail_penjualan', 'ASC')
            ->get()
            ->getResultArray();
        $hardDelete = new SparepartKeluarHardDeleteService($this->db);

        foreach ($details as $detail) {
            $hasil = $hardDelete->hapusDetail((int) ($detail['iddetail_penjualan'] ?? 0), $serviceId);
            if (! $hasil['ok']) {
                throw new \RuntimeException('Sparepart service berubah saat refund. Muat ulang lalu coba lagi.');
            }
        }
    }

    /**
     * Total DP aktif (status='aktif') untuk sebuah service. 0 bila tidak ada.
     */
    public function totalDpAktif(int $idservice): int
    {
        if ($idservice <= 0) {
            return 0;
        }

        return $this->DpModel->totalAktif($idservice);
    }

    /**
     * Apakah tabel ledger `service_dp` sudah ada?
     *
     * Dicek langsung (non-cache) dengan prefix diterapkan, mengikuti pola
     * `ModelService::hasDpLedger()` — daftar tabel CI4 di-cache per koneksi
     * sehingga hasil bisa basi setelah DDL.
     */
    public function punyaTabelLedger(): bool
    {
        return $this->db->tableExists($this->db->getPrefix() . 'service_dp', false);
    }

    /** Apakah skema dan template jurnal untuk refund penuh sudah tersedia? */
    public function refundSiap(): bool
    {
        if (! $this->punyaTabelLedger() || ! $this->db->tableExists('template_jurnal')) {
            return false;
        }

        foreach ([
            'refund_idkas_keluar', 'refund_tanggal', 'refund_metode',
            'refund_bank_idbank', 'refund_referensi', 'refund_alasan',
            'refund_by', 'refund_at',
        ] as $field) {
            if (! $this->db->fieldExists($field, 'service_dp')) {
                return false;
            }
        }

        foreach (self::TEMPLATE_REFUND as $template) {
            if ($this->db->table('template_jurnal')->where('kode_template', $template)->countAllResults() < 2) {
                return false;
            }
        }

        if (! $this->db->tableExists('kategori_kas') || ! $this->db->tableExists('no_akun')) {
            return false;
        }
        if ($this->db->table('kategori_kas')->where('kategori', 'REFUND')->where('jenis_kas', 'kas_keluar')->countAllResults() === 0
            || $this->db->table('no_akun')->where('no_akun', self::COA_PENDAPATAN_DITERIMA_DI_MUKA)->countAllResults() === 0) {
            return false;
        }

        return true;
    }

    /**
     * Sumber saldo DP efektif sebuah service untuk pengambilan (settlement).
     *
     * Kompatibilitas baru vs historis:
     *  - `ledger`   : ada tabel `service_dp` DAN minimal satu baris ledger untuk
     *                 service ini -> saldo = total DP aktif (per metode).
     *  - `historis` : tidak ada baris ledger (service dibuat sebelum ledger ada)
     *                 -> saldo = kolom `service.dp_bayar` (nilai lama).
     *  - `none`     : tidak ada DP sama sekali.
     *
     * Bila tabel ledger ada tetapi baris untuk service ini nol (mis. DP sudah
     * dibatalkan), sumbernya `historis` bila kolom `dp_bayar` > 0; JANGAN
     * diam-diam memakai ledger kosong lalu menimpa kolom historis.
     *
     * @return array{sumber:string,punya_ledger:bool,saldo:int,tunai:int,transfer:int}
     */
    public function statusSumberDp(int $idservice): array
    {
        $hasil = [
            'sumber'       => 'none',
            'punya_ledger' => false,
            'saldo'        => 0,
            'tunai'        => 0,
            'transfer'     => 0,
        ];

        if ($idservice <= 0) {
            return $hasil;
        }

        $adaLedger = $this->punyaTabelLedger() && $this->DpModel->jumlahBaris($idservice) > 0;

        if ($adaLedger) {
            $hasil['sumber']       = 'ledger';
            $hasil['punya_ledger'] = true;

            foreach ($this->DpModel->getAktifByService($idservice) as $row) {
                $jumlah = (int) ($row->jumlah ?? 0);
                $metode = strtolower(trim((string) ($row->metode ?? '')));
                if ($metode === 'transfer') {
                    $hasil['transfer'] += $jumlah;
                } else {
                    $hasil['tunai'] += $jumlah;
                }
            }

            $hasil['saldo'] = $hasil['tunai'] + $hasil['transfer'];

            return $hasil;
        }

        $query = $this->db->table('service')
            ->select('dp_bayar')
            ->where('idservice', $idservice)
            ->get();

        if ($query === false) {
            $error = $this->db->error();
            log_message('error', 'Gagal query statusSumberDp: ' . json_encode($error));
            throw new \RuntimeException('Gagal membaca service pada statusSumberDp.');
        }

        $row = $query->getRow();
        $dpBayar = (int) ($row->dp_bayar ?? 0);

        if ($dpBayar > 0) {
            $hasil['sumber'] = 'historis';
            $hasil['saldo']  = $dpBayar;
        }

        return $hasil;
    }

    /**
     * Apakah service memakai ledger DP baru (`service_dp`) sebagai sumber saldo.
     */
    public function isLedgerBased(int $idservice): bool
    {
        return $this->statusSumberDp($idservice)['sumber'] === 'ledger';
    }

    /**
     * Hitung ulang total DP aktif lalu tulis sebagai proyeksi `service.dp_bayar`.
     *
     * @return int total DP aktif (nilai yang diproyeksikan)
     */
    public function proyeksiDpBayar(int $idservice): int
    {
        $total = $this->totalDpAktif($idservice);

        if ($idservice > 0) {
            $this->ServiceModel->update($idservice, [
                'dp_bayar'   => $total,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $total;
    }

    private function normalisasiMetode($metode): string
    {
        $metode = strtolower(trim((string) $metode));

        return $metode === '' ? 'tunai' : $metode;
    }

    private function teksOpsional($value): ?string
    {
        if (is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function validasi(int $idservice, string $metode, ?string $bankIdbank, int $unitId): void
    {
        if ($idservice <= 0) {
            throw new \RuntimeException('DP service: idservice tidak valid.');
        }

        if (! array_key_exists($metode, self::TEMPLATE)) {
            throw new \RuntimeException('DP service: metode harus "tunai" atau "transfer".');
        }

        if ($unitId <= 0) {
            throw new \RuntimeException('DP service: unit tidak valid.');
        }

        if ($this->db->table('service')->where('idservice', $idservice)->countAllResults() === 0) {
            throw new \RuntimeException('DP service: service tidak ditemukan.');
        }

        if ($metode === 'transfer') {
            if ($bankIdbank === null) {
                throw new \RuntimeException('DP service: DP transfer wajib menyertakan bank.');
            }
            if ($this->BankModel->getById($bankIdbank) === null) {
                throw new \RuntimeException('DP service: bank "' . $bankIdbank . '" tidak terdaftar.');
            }
        }
    }

    private function pastikanTemplateAda(string $kodeTemplate): void
    {
        $jumlah = $this->db->table('template_jurnal')
            ->where('kode_template', $kodeTemplate)
            ->countAllResults();

        if ($jumlah <= 0) {
            throw new \RuntimeException("DP service: template jurnal '{$kodeTemplate}' tidak ditemukan.");
        }
    }

    /**
     * Verifikasi jurnal DP benar-benar tertulis dan seimbang. insertJurnal()
     * tidak memberi sinyal kegagalan, jadi ini satu-satunya jaminan.
     */
    private function pastikanJurnalSeimbang(int $idDp, int $jumlah): void
    {
        $rows = $this->db->table('jurnal')
            ->where('tabel_referensi', self::TABEL_REFERENSI)
            ->where('id_referensi', (string) $idDp)
            ->get()
            ->getResult();

        if (count($rows) === 0) {
            throw new \RuntimeException('DP service: jurnal DP tidak terbentuk.');
        }

        $debet  = 0;
        $kredit = 0;
        foreach ($rows as $row) {
            $debet  += (int) $row->debet;
            $kredit += (int) $row->kredit;
        }

        if ($debet !== $kredit) {
            throw new \RuntimeException('DP service: jurnal DP tidak seimbang (debet != kredit).');
        }
        if ($debet !== $jumlah) {
            throw new \RuntimeException('DP service: nominal jurnal DP tidak sesuai nominal DP.');
        }
    }

    private function generateKodeDp(int $idservice): string
    {
        $query = $this->db->table('service')
            ->select('no_service')
            ->where('idservice', $idservice)
            ->get();

        if ($query === false) {
            $error = $this->db->error();
            log_message('error', 'Gagal query generateKodeDp: ' . json_encode($error));
            throw new \RuntimeException('Gagal membaca service pada generateKodeDp.');
        }

        $service = $query->getRow();

        $dasar = 'DP' . ($service->no_service ?? $idservice);

        $kode = $dasar;
        $urut = 1;
        while ($this->DpModel->kodeDpSudahAda($kode)) {
            $urut++;
            $kode = substr($dasar, 0, 34) . '-' . $urut;
        }

        return $kode;
    }
}
