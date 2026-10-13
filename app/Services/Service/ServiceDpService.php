<?php

namespace App\Services\Service;

use App\Models\ModelBank;
use App\Models\ModelJurnal;
use App\Models\ModelService;
use App\Models\ModelServiceDp;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * ServiceDpService — pencatatan DP (uang muka) service ke ledger `service_dp`.
 *
 * Ruang lingkup (tahap fondasi):
 *  - catatDp(): validasi + INSERT baris ledger + jurnal DP + proyeksi
 *    `service.dp_bayar`.
 *  - totalDpAktif(): jumlah DP berstatus aktif per service.
 *  - proyeksiDpBayar(): tulis ulang `service.dp_bayar` dari total DP aktif.
 *
 * BUKAN ledger pelunasan. Pelunasan, pembatalan/reversal, invoice, dan UI
 * belum menjadi bagian dari service ini.
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
                'total'   => $this->totalDpAktif($idservice),
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
        ], true);

        if (! $id) {
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
        $service = $this->db->table('service')
            ->select('no_service')
            ->where('idservice', $idservice)
            ->get()
            ->getRow();

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
