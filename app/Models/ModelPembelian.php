<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPembelian extends Model
{
    protected $table = 'pembelian';
    protected $primaryKey = 'idpembelian';
    protected $returnType = 'object';
    protected $allowedFields = ['idpembelian', 'no_nota_supplier', 'jatuh_tempo', 'foto_nota', 'tanggal_masuk', 'sisa', 'status', 'total_transaksi', 'total_diskon', 'total_ppn', 'total_bayar', 'bayar', 'bayar_bank', 'bank_idbank', 'bayar_tunai', 'unit_idunit', 'suplier_id_suplier', 'pelanggan_id_pelanggan', 'input_by', 'frontliner'];


    public function getPembelian()
    {
        return $this->findAll();
    }

    public function insert_Pembelian($data)
    {
        if (! $this->insert($data)) {
            return false;
        }

        return (int) $this->insertID();
    }



    public function getById($id_pembelian)
    {
        return $this->where(['idpembelian' => $id_pembelian])->first();
    }

    public function getBelumLunas()
    {
        return $this->select('
                pembelian.*,
                unit.NAMA_UNIT
            ')
            ->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left')
            ->where('pembelian.status', 'Belum Lunas')
            ->findAll();
    }


    public function getBelumLunasFiltered($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null)
    {
        $builder = $this->select('
            pembelian.*,
            unit.NAMA_UNIT
        ')
            ->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left')
            ->where('pembelian.status', 'Belum Lunas');

        // Filter tanggal masuk
        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
            $builder->where('pembelian.tanggal_masuk >=', $tanggalAwal)
                ->where('pembelian.tanggal_masuk <=', $tanggalAkhir);
        } elseif (!empty($tanggalAwal)) {
            $builder->where('pembelian.tanggal_masuk >=', $tanggalAwal);
        } elseif (!empty($tanggalAkhir)) {
            $builder->where('pembelian.tanggal_masuk <=', $tanggalAkhir);
        }

        // Filter nama unit
        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->findAll();
    }




    public function getAgingHutang()
    {
        $builder = $this->db->table('pembelian');
        $builder->select("
        pembelian.idpembelian,
        pembelian.no_nota_supplier,
        pembelian.tanggal_masuk,
        pembelian.jatuh_tempo,
        pembelian.total_bayar,
        pembelian.sisa,
        DATEDIFF(CURDATE(), pembelian.jatuh_tempo) AS umur_hari,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) <= 30 THEN pembelian.sisa ELSE 0 END AS `0_30_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 31 AND 60 THEN pembelian.sisa ELSE 0 END AS `31_60_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 61 AND 90 THEN pembelian.sisa ELSE 0 END AS `61_90_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) > 90 THEN pembelian.sisa ELSE 0 END AS `lebih_90_hari`,
        unit.NAMA_UNIT as nama_unit,
        akun.NAMA_AKUN as nama_akun
    ");

        $builder->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left');
        $builder->join('akun', 'akun.ID_AKUN = pembelian.input_by', 'left');

        $builder->where('pembelian.status', 'Belum Lunas');
        $builder->orderBy('pembelian.jatuh_tempo', 'ASC');

        return $builder->get()->getResult();
    }


    public function getAgingHutangfiltered($tanggal_awal = null, $tanggal_akhir = null, $nama_unit = null)
    {
        $builder = $this->db->table('pembelian');
        $builder->select("
        pembelian.idpembelian,
        pembelian.no_nota_supplier,
        pembelian.tanggal_masuk,
        pembelian.jatuh_tempo,
        pembelian.total_bayar,
        pembelian.sisa,
        DATEDIFF(CURDATE(), pembelian.jatuh_tempo) AS umur_hari,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) <= 30 THEN pembelian.sisa ELSE 0 END AS `0_30_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 31 AND 60 THEN pembelian.sisa ELSE 0 END AS `31_60_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 61 AND 90 THEN pembelian.sisa ELSE 0 END AS `61_90_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) > 90 THEN pembelian.sisa ELSE 0 END AS `lebih_90_hari`,
        unit.NAMA_UNIT as nama_unit,
        akun.NAMA_AKUN as nama_akun
    ");

        $builder->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left');
        $builder->join('akun', 'akun.ID_AKUN = pembelian.input_by', 'left');

        // hanya hutang yang belum lunas
        $builder->where('pembelian.status', 'Belum Lunas');

        // filter tanggal
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $builder->where("pembelian.tanggal_masuk >=", $tanggal_awal);
            $builder->where("pembelian.tanggal_masuk <=", $tanggal_akhir);
        }

        // filter unit
        if (!empty($nama_unit)) {
            $builder->where('unit.NAMA_UNIT', $nama_unit);
        }

        $builder->orderBy('pembelian.jatuh_tempo', 'ASC');

        return $builder->get()->getResult();
    }

    /**
     * Daftar nota untuk /riwayat_pembelian (DataTables server-side).
     *
     * Satu baris = satu `pembelian`, bukan satu detail. Detail barang diambil
     * terpisah lewat ModelDetailPembelian::getByPembelianId() supaya halaman
     * tidak menarik seluruh isi detail_pembelian.
     *
     * `no_batch` TIDAK unik (baris lama bisa tertinggal setelah header
     * dihapus), jadi identitas nota selalu `pembelian.idpembelian`.
     *
     * @param  int[] $allowedUnitIds unit dalam scope user; kosong = tidak ada data
     * @return array{data: object[], recordsFiltered: int}
     */
    public function getRiwayatServerSide(
        $start,
        $length,
        $searchValue,
        $orderColumn,
        $orderDir,
        $startDate = '',
        $endDate = '',
        $unitId = 0,
        array $allowedUnitIds = []
    ) {
        $orderColumns = [
            0 => 'no_batch',
            1 => 'pembelian.tanggal_masuk',
            2 => 'nama_barang',
            3 => 'unit.NAMA_UNIT',
            4 => 'pembelian.suplier_id_suplier',
            5 => 'pembelian.total_transaksi',
        ];
        $orderDir = strtoupper((string) $orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = $orderColumns[(int) $orderColumn] ?? 'pembelian.tanggal_masuk';

        [$where, $params] = $this->riwayatFilters(
            $searchValue,
            $startDate,
            $endDate,
            $unitId,
            $allowedUnitIds
        );

        $sql = "SELECT
                pembelian.idpembelian,
                COALESCE(
                    pembelian.no_nota_supplier,
                    (SELECT dp_batch.no_batch
                        FROM detail_pembelian dp_batch
                        WHERE dp_batch.pembelian_idpembelian = pembelian.idpembelian
                        ORDER BY dp_batch.iddetail_pembelian ASC
                        LIMIT 1)
                ) AS no_batch,
                pembelian.tanggal_masuk,
                pembelian.suplier_id_suplier,
                pembelian.total_transaksi,
                pembelian.status,
                pembelian.sisa,
                unit.NAMA_UNIT,
                suplier.nama_suplier,
                (SELECT COUNT(*)
                    FROM detail_pembelian dp_count
                    WHERE dp_count.pembelian_idpembelian = pembelian.idpembelian) AS jumlah_item,
                (SELECT b_first.nama_barang
                    FROM detail_pembelian dp_first
                    JOIN barang b_first ON b_first.idbarang = dp_first.barang_idbarang
                    WHERE dp_first.pembelian_idpembelian = pembelian.idpembelian
                    ORDER BY dp_first.iddetail_pembelian ASC
                    LIMIT 1) AS nama_barang
            FROM pembelian
            JOIN unit ON unit.idunit = pembelian.unit_idunit
            LEFT JOIN suplier ON suplier.id_suplier = pembelian.suplier_id_suplier
            WHERE {$where}
            ORDER BY {$orderBy} {$orderDir}, pembelian.idpembelian DESC
            LIMIT ? OFFSET ?";

        $dataParams = array_merge($params, [(int) $length, (int) $start]);
        $data = $this->db->query($sql, $dataParams)->getResult();

        $countSql = "SELECT COUNT(*) AS total
            FROM pembelian
            JOIN unit ON unit.idunit = pembelian.unit_idunit
            LEFT JOIN suplier ON suplier.id_suplier = pembelian.suplier_id_suplier
            WHERE {$where}";
        $totalFiltered = (int) $this->db->query($countSql, $params)->getRow()->total;

        return [
            'data' => $data,
            'recordsFiltered' => $totalFiltered,
        ];
    }

    /**
     * Jumlah seluruh nota dalam scope user (tanpa filter tanggal/cari/unit).
     *
     * @param int[] $allowedUnitIds
     */
    public function countRiwayat(array $allowedUnitIds = []): int
    {
        if ($allowedUnitIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($allowedUnitIds), '?'));

        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM pembelian WHERE unit_idunit IN ({$placeholders})",
            array_map('intval', $allowedUnitIds)
        )->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * @param  int[] $allowedUnitIds
     * @return array{0: string, 1: array}
     */
    private function riwayatFilters(
        $searchValue,
        $startDate,
        $endDate,
        $unitId,
        array $allowedUnitIds
    ): array {
        $where = ['1=1'];
        $params = [];

        // Scope unit selalu menang; unit dari request hanya mempersempit.
        if ($allowedUnitIds === []) {
            return ['1=0', []];
        }

        $placeholders = implode(',', array_fill(0, count($allowedUnitIds), '?'));
        $where[] = "pembelian.unit_idunit IN ({$placeholders})";
        foreach ($allowedUnitIds as $allowed) {
            $params[] = (int) $allowed;
        }

        $unitId = (int) $unitId;
        if ($unitId > 0 && in_array($unitId, $allowedUnitIds, true)) {
            $where[] = 'pembelian.unit_idunit = ?';
            $params[] = $unitId;
        }

        if (is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $where[] = 'pembelian.tanggal_masuk >= ?';
            $params[] = $startDate;
        }
        if (is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            // Rentang eksklusif sehari setelah tanggal akhir: tetap benar untuk
            // kolom DATE maupun DATETIME, dan tidak mematikan index seperti DATE().
            $where[] = 'pembelian.tanggal_masuk < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $endDate;
        }

        if (is_string($searchValue) && $searchValue !== '') {
            // Cari di level nota lewat EXISTS agar satu nota tidak terpecah
            // hanya karena satu barang cocok.
            $where[] = '(pembelian.no_nota_supplier LIKE ?
                OR unit.NAMA_UNIT LIKE ?
                OR suplier.nama_suplier LIKE ?
                OR EXISTS (
                    SELECT 1 FROM detail_pembelian dp_search
                    LEFT JOIN barang b_search ON b_search.idbarang = dp_search.barang_idbarang
                    WHERE dp_search.pembelian_idpembelian = pembelian.idpembelian
                    AND (b_search.nama_barang LIKE ? OR dp_search.no_batch LIKE ?)
                ))';
            $like = '%' . $searchValue . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }
}
