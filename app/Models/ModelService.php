<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use DateTimeZone;

class ModelService extends Model
{
    protected $table = 'service';
    protected $primaryKey = 'idservice';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idservice',
        'no_service',
        'no_hp',
        'imei',
        'alamat',
        'keluhan',
        'keterangan',
        'passcode',
        'type_passcode',
        'tipe_hp',
        'email_icloud',
        'password_icloud',
        'status_service',
        'status_proses',
        'total_service',
        'total_diskon',
        'total_diskon_garansi',
        'harus_dibayar',
        'dp_bayar',
        'estimasi_biaya',
        'bayar',
        'garansi_hari',
        'pelanggan_id_pelanggan',
        'unit_idunit',
        'service_by',
        'service_by_garansi',
        'input_by',
        'tanggal_selesai',
        'tanggal_bisa_diambil',
        'created_at',
        'updated_at',
        'biaya_tambahan_garansi',
        'total_service_garansi',
        'tanggal_claim_garansi',
        'bayar_garansi',
        'harus_dibayar_garansi',
        'bayar_tunai',
        'bayar_bank',
        'bayar_tunai_garansi',
        'prioritas'
    ];

    public function getAllService()
    {
        return $this->findAll();
    }

    public function getServiceById($id)
    {
        return $this->where('idservice', $id)
            ->get()
            ->getRow();
    }


    public function getByIdWithPelanggan($id)
    {
        return $this->select('service.*, pelanggan.nama')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->where('service.idservice', $id)
            ->first();
    }


    public function getRiwayatService()
    {
        return $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->findAll();
    }

    public function getRiwayatServiceTanpaClaimGaransi()
    {
        return $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->where('(tanggal_claim_garansi IS NULL OR tanggal_claim_garansi = "0000-00-00" OR tanggal_claim_garansi = "1970-01-01")')
            ->findAll();
    }

    public function getRiwayatServiceGaransi()
    {
        return $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->where('tanggal_claim_garansi IS NOT NULL')
            ->where('tanggal_claim_garansi !=', '0000-00-00')
            ->where('tanggal_claim_garansi !=', '1970-01-01')
            ->findAll();
    }



    public function getExpiredService()
    {
        // Hitung tanggal 3 bulan yang lalu dari sekarang
        $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

        return $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->groupStart()
            ->where('tanggal_selesai IS NULL')
            ->orWhere('tanggal_selesai', '0000-00-00')
            ->orWhere('tanggal_selesai', '1970-01-01')
            ->groupEnd()
            ->where('tanggal_bisa_diambil <', $threeMonthsAgo)
            ->findAll();
    }


    public function getExpiredproses()
    {
        return $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->groupStart()
            ->where('tanggal_bisa_diambil IS NULL', null, false)
            ->orWhere('tanggal_bisa_diambil', '0000-00-00')
            ->orWhere('tanggal_bisa_diambil', '1970-01-01')
            ->groupEnd()
            // gunakan DATE_ADD di SQL: created_at + 1 month < NOW()
            ->where('DATE_ADD(created_at, INTERVAL 1 MONTH) < NOW()', null, false)
            ->findAll();
    }




    public function getExpiredServiceByRange($tanggal_awal, $tanggal_akhir)
    {
        $tanggal_awal .= ' 00:00:00';
        $tanggal_akhir .= ' 23:59:59';

        // Hitung tanggal 3 bulan yang lalu dari hari ini
        $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

        return $this->select('service.*, pelanggan.nama as nama_pelanggan, akun.NAMA_AKUN as nama_service_by')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->join('akun', 'akun.ID_AKUN = service.service_by')
            ->groupStart()
            ->where('tanggal_selesai IS NULL')
            ->orWhere('tanggal_selesai', '0000-00-00')
            ->orWhere('tanggal_selesai', '1970-01-01')
            ->groupEnd()
            ->where('tanggal_bisa_diambil <', $threeMonthsAgo)
            ->where('tanggal_bisa_diambil >=', $tanggal_awal)
            ->where('tanggal_bisa_diambil <=', $tanggal_akhir)
            ->findAll();
    }




    public function insertService($data)
    {
        return $this->insert($data);
    }

    public function getServiceByStatus($status)
    {
        return $this->where('status_service', $status)->findAll();
    }

    public function updateService($id, $data)
    {
        return $this->update($id, $data);
    }

    public function deleteService($id)
    {
        return $this->delete($id);
    }


    public function getServiceWithLaba()
    {
        return $this->select("
            service.*,
            akun.NAMA_AKUN AS nama_teknisi,

            COALESCE(SUM(service_sparepart.hpp_penjualan * service_sparepart.jumlah), 0) AS total_hpp_penjualan,

            COALESCE(SUM(service_sparepart.hpp_penjualan_garansi * service_sparepart.jumlah_tambahan_garansi), 0) AS total_hpp_garansi,

            COALESCE(SUM(service_sparepart.harga_penjualan * service_sparepart.jumlah), 0)
                - COALESCE(SUM(service_sparepart.hpp_penjualan * service_sparepart.jumlah), 0)
                - COALESCE(SUM(service_sparepart.diskon_penjualan), 0) AS laba_service,

            COALESCE(SUM(service_sparepart.harga_penjualan_garansi * service_sparepart.jumlah_tambahan_garansi), 0)
                - COALESCE(SUM(service_sparepart.hpp_penjualan_garansi * service_sparepart.jumlah_tambahan_garansi), 0)
                - COALESCE(SUM(service_sparepart.diskon_penjualan_garansi), 0) AS laba_garansi,

            COALESCE(MAX(service.harus_dibayar), 0)
                - COALESCE(SUM(service_sparepart.hpp_penjualan), 0) AS omset
        ")
        ->join(
            'service_sparepart',
            'service_sparepart.service_idservice = service.idservice',
            'right'
        )
        ->join(
            'akun',
            'akun.ID_AKUN = service.service_by',
            'right'
        )
        ->where('service.status_service', 4)
        ->where('akun.ID_JABATAN !=', 1)
        ->where('MONTH(service.tanggal_selesai)', date('m'))
        ->where('YEAR(service.tanggal_selesai)', date('Y'))
        ->groupBy('service.idservice')
        ->findAll();
    }

    //kerusakan
    public function getKerusakanWithFungsi($idservice)
    {
        return $this->db->table('service_kerusakan')
            ->select('service_kerusakan.idservice_kerusakan, service_kerusakan.keterangan, fungsi.idfungsi, fungsi.nama_fungsi')
            ->join('fungsi', 'fungsi.idfungsi = service_kerusakan.fungsi_idfungsi')
            ->where('service_kerusakan.service_idservice', $idservice)
            ->get()
            ->getResult();
    }

    //sparepart
    public function getSparepartWithBarang($idservice)
    {
        return $this->db->table('service_sparepart')
            ->select('
            service_sparepart.*,
            barang.nama_barang
        ')
            ->join('barang', 'barang.idbarang = service_sparepart.barang_idbarang')
            ->where('service_sparepart.service_idservice', $idservice)
            ->get()
            ->getResult();
    }

    //pelanggan teknisi
    public function getServiceWithAkunAndPelanggan($idservice)
    {
        return $this->db->table('service')
            ->select('
            service.*,
            akun.NAMA_AKUN AS nama_service_by,
            pelanggan.nama AS nama_pelanggan
        ')
            ->join('akun', 'akun.ID_AKUN = service.service_by', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan', 'left')
            ->where('service.idservice', $idservice)
            ->get()
            ->getRow();
    }




    public function filterexport($tanggal_awal = null, $tanggal_akhir = null)
    {
        $builder = $this->db->table('service')
            ->select('
            service.*,
            akun.NAMA_AKUN AS nama_service_by,
            pelanggan.nama AS nama_pelanggan
        ')
            ->join('akun', 'akun.ID_AKUN = service.service_by', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan', 'left');



        if ($tanggal_awal && $tanggal_akhir) {

            $tanggal_akhir .= ' 23:59:59';
            $builder->where('service.created_at >=', $tanggal_awal)
                ->where('service.created_at <=', $tanggal_akhir);
        }

        return $builder->get()->getResult();
    }


    public function filterExportGaransi($tanggal_awal = null, $tanggal_akhir = null)
    {
        $builder = $this->db->table('service')
            ->select('
            service.*,
            akun.NAMA_AKUN AS nama_service_by_garansi,
            pelanggan.nama AS nama_pelanggan
        ')
            ->join('akun', 'akun.ID_AKUN = service.service_by_garansi', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan', 'left');

        if ($tanggal_awal && $tanggal_akhir) {
            $tanggal_akhir .= ' 23:59:59';
            $builder->where('service.tanggal_claim_garansi >=', $tanggal_awal)
                ->where('service.tanggal_claim_garansi <=', $tanggal_akhir);
        }

        return $builder->get()->getResult();
    }


    public function filterexportlaba($tanggal_awal = null, $tanggal_akhir = null)
    {
        $builder = $this->select('
            service.*,
            SUM(service_sparepart.hpp_penjualan) AS total_hpp_penjualan,
            (service.harus_dibayar - SUM(service_sparepart.hpp_penjualan)) AS laba_service,
            akun.NAMA_AKUN AS nama_teknisi
        ')
            ->join('service_sparepart', 'service_sparepart.service_idservice = service.idservice', 'left')
            ->join('akun', 'akun.ID_AKUN = service.service_by', 'left')
            ->where('service.status_service', 4);

        if ($tanggal_awal && $tanggal_akhir) {

            $tanggal_akhir .= ' 23:59:59';
            $builder->where('service.created_at >=', $tanggal_awal)
                ->where('service.created_at <=', $tanggal_akhir);
        }

        return $builder->groupBy('service.idservice')->findAll();
    }

    //untuk proses service
    public function ProsesServiceAktif()
    {
        // ambil semua data service dengan join nama pelanggan
        $services = $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->whereIn('status_service', [1, 2])
            ->findAll();

        // load model lain
        $modelKerusakan = new \App\Models\ModelServiceKerusakan();
        $modelSparepart = new \App\Models\ModelServiceSparepart();

        foreach ($services as &$service) {
            // hitung lama service
            if ($service->created_at) {
                $created = new \DateTime($service->created_at, new \DateTimeZone('Asia/Jakarta'));
                $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
                $interval = $created->diff($now);

                $service->lama_service = $interval->format('%a hari, %h jam, %i menit');
            } else {
                $service->lama_service = 'Tanggal tidak tersedia';
            }

            // hitung jumlah kerusakan untuk service ini
            $service->jumlah_kerusakan = $modelKerusakan
                ->where('service_idservice', $service->idservice)
                ->countAllResults();

            // hitung jumlah sparepart untuk service ini
            $service->jumlah_sparepart = $modelSparepart
                ->where('service_idservice', $service->idservice)
                ->countAllResults();
        }

        return $services;
    }

    public function ProsesServiceAktifServerSide($start, $length, $searchValue, $orderColumn, $orderDir, $startDate, $endDate, $unitFilter)
    {
        $allowedColumns = [
            0 => 'rank',
            1 => 'service.no_service',
            2 => 'service.prioritas',
            3 => 'service.created_at',
            4 => 'pelanggan.nama',
            5 => 'service.no_hp',
            6 => 'service.tipe_hp',
            7 => 'service.unit_idunit',
            8 => 'lama_service_days',
            9 => 'service.status_proses'
        ];

        $orderColumnName = $allowedColumns[$orderColumn] ?? 'service.created_at';

        $whereConditions = ['service.status_service IN (1, 2)'];
        $params = [];

        if (!empty($startDate)) {
            $whereConditions[] = 'DATE(service.created_at) >= ?';
            $params[] = $startDate;
        }
        if (!empty($endDate)) {
            $whereConditions[] = 'DATE(service.created_at) <= ?';
            $params[] = $endDate;
        }
        if (!empty($unitFilter)) {
            $unitMap = ['Probolinggo' => 1, 'Jember' => 2, 'Banyuwangi' => 3, 'Pandaan' => 4];
            if (isset($unitMap[$unitFilter])) {
                $whereConditions[] = 'service.unit_idunit = ?';
                $params[] = $unitMap[$unitFilter];
            }
        }

        $searchCondition = '';
        if (!empty($searchValue)) {
            $searchCondition = ' AND (service.no_service LIKE ? OR pelanggan.nama LIKE ? OR service.no_hp LIKE ? OR service.tipe_hp LIKE ?)';
            $searchParam = "%{$searchValue}%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }

        $whereClause = implode(' AND ', $whereConditions) . $searchCondition;

        $sql = "SELECT 
                service.idservice,
                service.no_service,
                service.prioritas,
                service.created_at,
                service.tanggal_claim_garansi,
                pelanggan.nama as nama_pelanggan,
                service.no_hp,
                service.tipe_hp,
                service.unit_idunit,
                service.status_proses,
                service.status_service,
                DATEDIFF(NOW(), service.created_at) as lama_service_days,
                (SELECT COUNT(*) FROM service_kerusakan WHERE service_kerusakan.service_idservice = service.idservice) as jumlah_kerusakan,
                (SELECT COUNT(*) FROM service_sparepart WHERE service_sparepart.service_idservice = service.idservice) as jumlah_sparepart,
                CASE 
                    WHEN service.tanggal_claim_garansi IS NOT NULL AND service.tanggal_claim_garansi > '1971-01-01' THEN 0
                    WHEN service.prioritas = 1 THEN 1
                    ELSE 2
                END as rank
            FROM service
            JOIN pelanggan ON pelanggan.id_pelanggan = service.pelanggan_id_pelanggan
            WHERE {$whereClause}
            ORDER BY {$orderColumnName} {$orderDir}
            LIMIT ? OFFSET ?";

        $params[] = (int)$length;
        $params[] = (int)$start;

        $query = $this->db->query($sql, $params);
        $data = $query->getResult();

        $countSql = "SELECT COUNT(*) as total
            FROM service
            JOIN pelanggan ON pelanggan.id_pelanggan = service.pelanggan_id_pelanggan
            WHERE {$whereClause}";

        $countQuery = $this->db->query($countSql, array_slice($params, 0, -2));
        $totalFiltered = $countQuery->getRow()->total;

        foreach ($data as &$row) {
            if ($row->lama_service_days !== null) {
                $days = (int)$row->lama_service_days;
                $hours = 0;
                $minutes = 0;
                $row->lama_service = "{$days} hari, {$hours} jam, {$minutes} menit";
            } else {
                $row->lama_service = 'Tanggal tidak tersedia';
            }
        }

        return [
            'data' => $data,
            'recordsFiltered' => $totalFiltered
        ];
    }

    public function ProsesServiceAktifTotal()
    {
        return $this->whereIn('status_service', [1, 2])->countAllResults();
    }


    public function ProsesServiceDibatalkan()
    {
        // ambil semua data service dengan join nama pelanggan
        $services = $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->whereIn('status_service', [90, 91])
            ->findAll();

        // load model lain
        $modelKerusakan = new \App\Models\ModelServiceKerusakan();
        $modelSparepart = new \App\Models\ModelServiceSparepart();

        foreach ($services as &$service) {
            // hitung lama service
            if ($service->created_at) {
                $created = new \DateTime($service->created_at, new \DateTimeZone('Asia/Jakarta'));
                $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
                $interval = $created->diff($now);

                $service->lama_service = $interval->format('%a hari, %h jam, %i menit');
            } else {
                $service->lama_service = 'Tanggal tidak tersedia';
            }

            // hitung jumlah kerusakan untuk service ini
            $service->jumlah_kerusakan = $modelKerusakan
                ->where('service_idservice', $service->idservice)
                ->countAllResults();

            // hitung jumlah sparepart untuk service ini
            $service->jumlah_sparepart = $modelSparepart
                ->where('service_idservice', $service->idservice)
                ->countAllResults();
        }

        return $services;
    }

    public function ServiceBisaDiambil()
    {
        // Ambil data service dengan status 3
        $services = $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->whereIn('status_service', [3])
            ->findAll();

        // Load model lain
        $modelKerusakan = new \App\Models\ModelServiceKerusakan();
        $modelSparepart = new \App\Models\ModelServiceSparepart();

        foreach ($services as &$service) {
            // Hitung lama service berdasarkan tanggal bisa diambil
            if ($service->tanggal_bisa_diambil) {
                $created = new \DateTime($service->tanggal_bisa_diambil, new \DateTimeZone('Asia/Jakarta'));
                $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
                $interval = $created->diff($now);

                $service->lama_service = $interval->format('%a hari, %h jam, %i menit');
            } else {
                $service->lama_service = 'Tanggal tidak tersedia';
            }

            // Hitung jumlah kerusakan
            $service->jumlah_kerusakan = $modelKerusakan
                ->where('service_idservice', $service->idservice)
                ->countAllResults();

            // Hitung jumlah sparepart
            $service->jumlah_sparepart = $modelSparepart
                ->where('service_idservice', $service->idservice)
                ->countAllResults();
        }

        return $services;
    }



    public function ServiceSudahDiambil()
    {
        $services = $this->select('service.*, pelanggan.nama as nama_pelanggan')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->whereIn('status_service', [4])
            ->findAll();

        foreach ($services as &$service) {
            if ($service->tanggal_selesai) {
                $created = new \DateTime($service->tanggal_selesai, new \DateTimeZone('Asia/Jakarta'));
                $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
                $interval = $created->diff($now);

                $service->lama_service = $interval->format('%a hari, %h jam, %i menit');
            } else {
                $service->lama_service = 'Tanggal tidak tersedia';
            }
        }


        return $services;
    }

    public function ServiceSudahDiambilServerSide($start, $length, $searchValue, $orderColumn, $orderDir, $startDate, $endDate)
    {
        $allowedColumns = [
            0 => 'service.no_service',
            1 => 'service.created_at',
            2 => 'service.tanggal_selesai',
            3 => 'pelanggan.nama',
            4 => 'service.no_hp',
            5 => 'service.unit_idunit',
            6 => 'lama_service_days',
            7 => 'service.garansi_hari'
        ];

        $orderColumnName = $allowedColumns[$orderColumn] ?? 'service.tanggal_selesai';

        $builder = $this->db->table('service')
            ->select('
                service.idservice,
                service.no_service,
                service.created_at,
                service.tanggal_selesai,
                pelanggan.nama as nama_pelanggan,
                service.no_hp,
                service.unit_idunit,
                service.garansi_hari,
                DATEDIFF(NOW(), service.tanggal_selesai) as lama_service_days
            ')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan')
            ->where('service.status_service', 4);

        if (!empty($startDate)) {
            $builder->where('DATE(service.created_at) >=', $startDate);
        }
        if (!empty($endDate)) {
            $builder->where('DATE(service.created_at) <=', $endDate);
        }

        if (!empty($searchValue)) {
            $builder->groupStart()
                ->like('service.no_service', $searchValue)
                ->orLike('pelanggan.nama', $searchValue)
                ->orLike('service.no_hp', $searchValue)
                ->groupEnd();
        }

        $totalFiltered = $builder->countAllResults(false);

        $builder->orderBy($orderColumnName, $orderDir)
            ->limit($length, $start);

        $data = $builder->get()->getResult();

        foreach ($data as &$row) {
            if ($row->lama_service_days !== null) {
                $days = (int)$row->lama_service_days;
                $hours = 0;
                $minutes = 0;
                $row->lama_service = "{$days} hari, {$hours} jam, {$minutes} menit";
            } else {
                $row->lama_service = 'Tanggal tidak tersedia';
            }
        }

        return [
            'data' => $data,
            'recordsFiltered' => $totalFiltered
        ];
    }

    public function ServiceSudahDiambilTotal()
    {
        return $this->where('status_service', 4)->countAllResults();
    }

    public function getTotalPendapatanService($unit_id = null, $per_bulan = false)
    {
        if ($per_bulan) {
            $this->select("DATE_FORMAT(created_at, '%Y-%m') AS bulan, SUM(harus_dibayar) AS total")
                ->where('status_service', 4)
                ->groupBy('bulan')
                ->orderBy('bulan', 'ASC');

            if ($unit_id) {
                $this->where('unit_idunit', $unit_id);
            }

            return $this->findAll();
        }

        return $this->selectSum('harus_dibayar')
            ->where('status_service', 4)
            ->where($unit_id ? ['unit_idunit' => $unit_id] : [])
            ->get()
            ->getRow()
            ->harus_dibayar ?? 0;
    }
}
