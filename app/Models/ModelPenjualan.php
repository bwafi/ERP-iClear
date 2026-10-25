<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPenjualan extends Model
{
    protected $table = 'penjualan';
    protected $primaryKey = 'idpenjualan';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idpenjualan',
        'kode_invoice',
        'tanggal',
        'keterangan',
        'total_penjualan',
        'diskon',
        'harus_dibayar',
        'waktu_penjualan',
        'bayar',
        'bayar_tunai',
        'bayar_bank',
        'bank_idbank',
        'id_pelanggan',
        'service_idservice',
        'total_ppn',
        'created_on',
        'input_by',
        'sales_by',
        'unit_idunit'
    ];



    public function getPenjualan()
    {
        return $this->findAll();
    }

    public function getByIdPelanggan($id_pelanggan)
    {
        return $this->select('penjualan.*, unit.NAMA_UNIT, pelanggan.nama as nama_pelanggan, akun.NAMA_AKUN as nama_sales')
            ->join('unit', 'unit.idunit = penjualan.unit_idunit', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = penjualan.id_pelanggan', 'left')
            ->join('akun', 'akun.ID_AKUN = penjualan.sales_by', 'left')
            ->where('penjualan.id_pelanggan', $id_pelanggan)
            ->orderBy('penjualan.tanggal', 'DESC')
            ->findAll();
    }






    public function insert_Penjualan($data)
    {
        return $this->insert($data);
    }

    public function getById($idpenjulan)
    {
        return $this->where(['idpenjulan' => $idpenjulan])->first();
    }


    public function getByKodeInvoice($kode_invoice)
    {
        return $this->where(['kode_invoice' => $kode_invoice])->first();
    }

    public function getPendapatan($unit_id = null, $per_bulan = false)
    {
        if ($per_bulan) {
            $this->select("DATE_FORMAT(created_at, '%Y-%m') AS bulan, SUM(total_penjualan) AS total")
                ->groupBy("bulan")
                ->orderBy("bulan", "ASC");

            if ($unit_id) {
                $this->where('unit_idunit', $unit_id);
            }

            return $this->findAll();
        }

        $this->selectSum('total_penjualan');

        if ($unit_id) {
            $this->where('unit_idunit', $unit_id);
        }

        return $this->first()->total_penjualan ?? 0;
    }

    public function getOmsetBySales($sales_by, $bulan)
    {
        return $this->selectSum('total_penjualan')
            ->where('sales_by', $sales_by)
            ->like('tanggal', $bulan) // YYYY-MM
            ->first()
            ->total_penjualan ?? 0;
    }

    /** Daftar invoice untuk DataTables server-side (satu baris per invoice). */
    public function getPenjualanServerSide($start, $length, $searchValue, $orderColumn, $orderDir, $startDate = '', $endDate = '', $unitFilter = '')
    {
        $orderColumns = [
            0 => 'penjualan.kode_invoice',
            1 => 'penjualan.tanggal',
            2 => 'nama_barang',
            3 => 'unit.NAMA_UNIT',
            4 => 'total',
        ];
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = $orderColumns[(int) $orderColumn] ?? 'penjualan.tanggal';

        [$where, $params] = $this->penjualanFilters($searchValue, $startDate, $endDate, $unitFilter);

        $sql = "SELECT
                penjualan.kode_invoice,
                penjualan.tanggal,
                penjualan.id_pelanggan,
                unit.NAMA_UNIT,
                COUNT(detail_penjualan.iddetail_penjualan) AS jumlah_item,
                (SELECT b_first.nama_barang
                    FROM detail_penjualan dp_first
                    JOIN barang b_first ON b_first.idbarang = dp_first.barang_idbarang
                    WHERE dp_first.penjualan_idpenjualan = penjualan.idpenjualan
                    ORDER BY dp_first.iddetail_penjualan ASC
                    LIMIT 1) AS nama_barang,
                COALESCE(SUM(detail_penjualan.sub_total), 0) AS total,
                COALESCE(MAX(penjualan.total_ppn), 0) AS total_ppn
            FROM penjualan
            JOIN detail_penjualan ON detail_penjualan.penjualan_idpenjualan = penjualan.idpenjualan
            JOIN unit ON unit.idunit = detail_penjualan.unit_idunit
            WHERE {$where}
            GROUP BY penjualan.idpenjualan, penjualan.kode_invoice, penjualan.tanggal, penjualan.id_pelanggan, unit.NAMA_UNIT
            ORDER BY {$orderBy} {$orderDir}
            LIMIT ? OFFSET ?";

        $params[] = (int) $length;
        $params[] = (int) $start;
        $data = $this->db->query($sql, $params)->getResult();

        $countSql = "SELECT COUNT(DISTINCT penjualan.idpenjualan) AS total
            FROM penjualan
            JOIN detail_penjualan ON detail_penjualan.penjualan_idpenjualan = penjualan.idpenjualan
            JOIN unit ON unit.idunit = detail_penjualan.unit_idunit
            WHERE {$where}";
        $totalFiltered = (int) $this->db->query($countSql, array_slice($params, 0, -2))->getRow()->total;

        return [
            'data' => $data,
            'recordsFiltered' => $totalFiltered,
        ];
    }

    public function countPenjualan()
    {
        return $this->db->table('penjualan')->countAllResults();
    }

    private function penjualanFilters($searchValue, $startDate, $endDate, $unitFilter)
    {
        $where = ['1=1'];
        $params = [];

        if (is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $where[] = 'DATE(penjualan.tanggal) >= ?';
            $params[] = $startDate;
        }
        if (is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $where[] = 'DATE(penjualan.tanggal) <= ?';
            $params[] = $endDate;
        }
        if (is_string($unitFilter) && $unitFilter !== '') {
            $where[] = 'unit.NAMA_UNIT = ?';
            $params[] = $unitFilter;
        }
        if (is_string($searchValue) && $searchValue !== '') {
            // Cari di level invoice lewat EXISTS agar total invoice tidak ikut
            // terpotong hanya karena satu barang cocok.
            $where[] = '(penjualan.kode_invoice LIKE ? OR unit.NAMA_UNIT LIKE ? OR EXISTS (
                SELECT 1 FROM detail_penjualan dp_search
                JOIN barang b_search ON b_search.idbarang = dp_search.barang_idbarang
                WHERE dp_search.penjualan_idpenjualan = penjualan.idpenjualan
                AND b_search.nama_barang LIKE ?
            ))';
            $like = '%' . $searchValue . '%';
            array_push($params, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }
}
