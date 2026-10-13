<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ledger DP (uang muka) service.
 *
 * Hanya mencatat DP, bukan pelunasan. `service.dp_bayar` dipelihara sebagai
 * proyeksi oleh ServiceDpService, bukan sumber otoritatif lagi untuk service
 * baru.
 *
 * Catatan: method baca memakai `$this->db->table()` (builder segar) agar tidak
 * mengotori builder internal Model — Model hanya dipakai untuk `insert()`.
 */
class ModelServiceDp extends Model
{
    protected $table = 'service_dp';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'service_idservice',
        'kode_dp',
        'tanggal',
        'jumlah',
        'metode',
        'bank_idbank',
        'referensi',
        'status',
        'keterangan',
        'unit_id',
        'input_by',
        'created_at',
        'updated_at',
    ];

    public function getByService($idservice)
    {
        return $this->db->table($this->table)
            ->where('service_idservice', (int) $idservice)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();
    }

    public function getAktifByService($idservice)
    {
        $builder = $this->db->table($this->table)
            ->where('service_idservice', (int) $idservice)
            ->where('status', 'aktif')
            ->orderBy('id', 'ASC');

        $this->kecualikanYangDirefund($builder);

        return $builder->get()->getResult();
    }

    public function totalAktif($idservice): int
    {
        $builder = $this->db->table($this->table)
            ->selectSum('jumlah')
            ->where('service_idservice', (int) $idservice)
            ->where('status', 'aktif');

        $this->kecualikanYangDirefund($builder);
        $row = $builder->get()->getRow();

        return (int) ($row->jumlah ?? 0);
    }

    /**
     * Jumlah baris ledger DP untuk sebuah service (semua status).
     *
     * Dipakai untuk membedakan service yang memang memakai ledger DP baru
     * dari service historis yang hanya punya kolom `service.dp_bayar`.
     */
    public function jumlahBaris($idservice): int
    {
        return $this->db->table($this->table)
            ->where('service_idservice', (int) $idservice)
            ->countAllResults();
    }

    public function kodeDpSudahAda(string $kodeDp): bool
    {
        return $this->db->table($this->table)
            ->where('kode_dp', $kodeDp)
            ->countAllResults() > 0;
    }

    /** Refund tidak menghapus penerimaan historis, tetapi mengosongkan saldo DP yang masih dapat dipakai. */
    private function kecualikanYangDirefund($builder): void
    {
        if ($this->db->fieldExists('refund_idkas_keluar', $this->table)) {
            $builder->where('refund_idkas_keluar', null);
        }
    }

    /**
     * Daftar DP untuk tabel server-side pada menu DP Service.
     *
     * @return array{recordsTotal:int,recordsFiltered:int,data:array<int,array<string,mixed>>}
     */
    public function datatable(
        int $start,
        int $length,
        string $search,
        string $tanggalAwal,
        string $tanggalAkhir,
        ?int $unitId,
        int $orderColumn,
        string $orderDir
    ): array {
        $base = fn () => $this->db->table($this->table . ' sd')
            ->join('service s', 's.idservice = sd.service_idservice')
            ->join('pelanggan p', 'p.id_pelanggan = s.pelanggan_id_pelanggan', 'left');

        $totalBuilder = $base();
        if ($unitId !== null && $unitId > 0) {
            $totalBuilder->where('s.unit_idunit', $unitId);
        }
        $recordsTotal = $totalBuilder->countAllResults();

        $filterBuilder = $base();
        if ($unitId !== null && $unitId > 0) {
            $filterBuilder->where('s.unit_idunit', $unitId);
        }
        if ($tanggalAwal !== '') {
            $filterBuilder->where('DATE(sd.tanggal) >=', $tanggalAwal);
        }
        if ($tanggalAkhir !== '') {
            $filterBuilder->where('DATE(sd.tanggal) <=', $tanggalAkhir);
        }
        if ($search !== '') {
            $filterBuilder->groupStart()
                ->like('sd.kode_dp', $search)
                ->orLike('s.no_service', $search)
                ->orLike('p.nama', $search)
                ->orLike('sd.referensi', $search)
                ->groupEnd();
        }

        $recordsFiltered = $filterBuilder->countAllResults();

        $refundFields = [
            'refund_idkas_keluar',
            'refund_tanggal',
            'refund_metode',
            'refund_referensi',
        ];
        $refundSelect = [];
        foreach ($refundFields as $field) {
            $refundSelect[] = $this->db->fieldExists($field, $this->table)
                ? 'sd.' . $field
                : 'NULL AS ' . $field;
        }

        $builder = $this->db->table($this->table . ' sd')
            ->select('sd.id, sd.kode_dp, sd.tanggal, sd.jumlah, sd.metode, sd.bank_idbank, sd.referensi, sd.status, sd.keterangan, s.idservice, s.no_service, s.status_service, s.tipe_hp, p.nama AS nama_pelanggan, u.NAMA_UNIT AS nama_unit, b.nama_bank, ' . implode(', ', $refundSelect))
            ->join('service s', 's.idservice = sd.service_idservice')
            ->join('pelanggan p', 'p.id_pelanggan = s.pelanggan_id_pelanggan', 'left')
            ->join('unit u', 'u.idunit = s.unit_idunit', 'left')
            ->join('bank b', 'b.idbank = sd.bank_idbank', 'left');

        if ($unitId !== null && $unitId > 0) {
            $builder->where('s.unit_idunit', $unitId);
        }
        if ($tanggalAwal !== '') {
            $builder->where('DATE(sd.tanggal) >=', $tanggalAwal);
        }
        if ($tanggalAkhir !== '') {
            $builder->where('DATE(sd.tanggal) <=', $tanggalAkhir);
        }
        if ($search !== '') {
            $builder->groupStart()
                ->like('sd.kode_dp', $search)
                ->orLike('s.no_service', $search)
                ->orLike('p.nama', $search)
                ->orLike('sd.referensi', $search)
                ->groupEnd();
        }

        $columns = [
            0 => 'sd.tanggal',
            1 => 'sd.kode_dp',
            2 => 's.no_service',
            3 => 'p.nama',
            4 => 'sd.metode',
            5 => 'u.NAMA_UNIT',
            6 => 'sd.jumlah',
            7 => 'sd.status',
        ];
        $orderBy = $columns[$orderColumn] ?? 'sd.tanggal';
        $direction = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $data = $builder
            ->orderBy($orderBy, $direction)
            ->orderBy('sd.id', 'DESC')
            ->limit(max(1, $length), max(0, $start))
            ->get()
            ->getResultArray();

        return [
            'recordsTotal'    => (int) $recordsTotal,
            'recordsFiltered' => (int) $recordsFiltered,
            'data'            => $data,
        ];
    }
}
