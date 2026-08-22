<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelJurnal extends Model
{
    protected $table = 'jurnal';
    protected $primaryKey = 'idjurnal';
    protected $returnType = 'object';
    protected $allowedFields = ['idjurnal', 'tanggal', 'no_akun', 'nama_akun', 'debet', 'kredit', 'keterangan', 'id_referensi', 'tabel_referensi', 'id_unit', 'id_akun'];

    public function insert_biasah($data)
    {
        $this->insert($data);
    }

    public function insertJurnal($tanggal, $kode_template, $ar_value, $keterangan, $id_referensi, $tabel_referensi, $id_unit = null)
    {
        $id_akun = session('ID_AKUN');
        if ($id_unit == null) {
            $id_unit = session('ID_UNIT');
        }
        $template = db_connect()->table('template_jurnal')->where('kode_template', $kode_template)->get()->getResult();
        $ar_insert = array();
        foreach ($template as $value) {
            $data = array(
                'tanggal'           => date('Y-m-d', strtotime($tanggal)),
                'no_akun'           => $value->no_akun,
                'nama_akun'         => $value->nama_akun,
                'keterangan'        => $keterangan,
                'id_referensi'      => $id_referensi,
                'tabel_referensi'   => $tabel_referensi,
                'id_unit'           => $id_unit,
                'id_akun'           => $id_akun
            );
            $nilai = isset($ar_value[$value->array_value]) ? $ar_value[$value->array_value] : 0;

            if ($value->debet_kredit == 'debet') {
                $data['debet'] = $nilai;
                $data['kredit'] = 0;
            } else {
                $data['kredit'] = $nilai;
                $data['debet'] = 0;
            }
            $this->insert($data);
        }
        return true;
    }


    public function getJurnalWithUnit()
    {
        return $this->select('jurnal.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = jurnal.id_unit', 'left')
            ->orderBy('jurnal.tanggal', 'DESC')
            ->findAll();
    }



    public function filterexport($tanggal_awal = null, $tanggal_akhir = null, $nama_unit = null)
    {
        $builder = $this->select('jurnal.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = jurnal.id_unit', 'left');

        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $builder->where('tanggal >=', $tanggal_awal)
                ->where('tanggal <=', $tanggal_akhir);
        }

        if (!empty($nama_unit)) {
            $builder->where('unit.NAMA_UNIT', $nama_unit);
        } else {

            $builder->where('unit.NAMA_UNIT IS NOT NULL');
        }

        return $builder->findAll();
    }


    public function insertJurnalfleksibel($tanggal, $kode_template, $ar_value, $keterangan, $id_referensi, $tabel_referensi, $id_unit = null)
    {
        $id_akun = session('ID_AKUN');
        if ($id_unit == null) {
            $id_unit = session('ID_UNIT');
        }

        $template = db_connect()->table('template_jurnal')
            ->where('kode_template', $kode_template)
            ->orderBy('idtemplate_jurnal', 'asc') // urutkan jika perlu
            ->get()->getResult();

        foreach ($template as $value) {
            $index = $value->array_value;

            // Validasi nilai, gunakan 0 jika offset tidak ditemukan
            $nilai = isset($ar_value[$index]) ? $ar_value[$index] : 0;

            $data = [
                'tanggal'         => date('Y-m-d', strtotime($tanggal)),
                'no_akun'         => $value->no_akun,
                'nama_akun'       => $value->nama_akun,
                'keterangan'      => $keterangan,
                'id_referensi'    => $id_referensi,
                'tabel_referensi' => $tabel_referensi,
                'id_unit'         => $id_unit,
                'id_akun'         => $id_akun,
                'debet'           => ($value->debet_kredit == 'debet') ? $nilai : 0,
                'kredit'          => ($value->debet_kredit == 'kredit') ? $nilai : 0,
            ];

            $this->insert($data);
        }

        return true;
    }


    public function getSummaryPerParent($tanggal_awal = null, $tanggal_akhir = null, $unit = null)
    {
        $db = \Config\Database::connect();

        // Ambil akun parent: prefix 3 digit + 7 nol
        $builderParent = $db->table('no_akun')
            ->select('no_akun, nama_akun')
            ->where('CHAR_LENGTH(no_akun)', 10)
            ->where('RIGHT(no_akun, 7)', '0000000')
            ->where('no_akun !=', '1000000000'); // <--- tambahkan ini


        $parents = $builderParent->get()->getResult();

        $result = [];

        foreach ($parents as $parent) {
            $parent_no = $parent->no_akun;

            $builder = $db->table('jurnal j');
            $builder->select("
            COALESCE(SUM(j.debet), 0) as total_debet,
            COALESCE(SUM(j.kredit), 0) as total_kredit
        ");
            $builder->like('j.no_akun', substr($parent_no, 0, 3), 'after'); // filter semua yang prefix sama

            // Tambah filter unit jika ada
            if ($unit !== null) {
                $builder->where('j.id_unit', $unit);
            }

            // Tambah filter tanggal jika ada
            if ($tanggal_awal !== null && $tanggal_akhir !== null) {
                $builder->where('j.tanggal >=', $tanggal_awal);
                $builder->where('j.tanggal <=', $tanggal_akhir);
            }

            $total = $builder->get()->getRow();

            $result[] = [
                'parent_no_akun' => $parent_no,
                'parent_nama_akun' => $parent->nama_akun,
                'total_debet' => $total->total_debet ?? 0,
                'total_kredit' => $total->total_kredit ?? 0,
            ];
        }

        return $result;
    }

    public function getChildByParent($prefix, $tanggal_awal = null, $tanggal_akhir = null, $unit = null)
    {
        $db = \Config\Database::connect();

        $builder = $db->table('no_akun na');
        $builder->select("
        na.no_akun,
        na.nama_akun,
        COALESCE(SUM(j.debet), 0) as total_debet,
        COALESCE(SUM(j.kredit), 0) as total_kredit
    ");
        $builder->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder->like('na.no_akun', $prefix, 'after');
        $builder->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder->where('RIGHT(na.no_akun, 7) !=', '0000000');
        $builder->where('na.no_akun !=', '1000000000');
        // pastikan bukan parent

        if ($unit !== null) {
            $builder->groupStart();
            $builder->where('j.id_unit', $unit);
            $builder->orWhere('j.id_unit IS NULL'); // agar akun tanpa jurnal tetap tampil
            $builder->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder->groupStart();
            $builder->where('j.tanggal >=', $tanggal_awal);
            $builder->where('j.tanggal <=', $tanggal_akhir);
            $builder->orWhere('j.tanggal IS NULL'); // agar akun tetap tampil meski tidak ada transaksi
            $builder->groupEnd();
        }

        $builder->groupBy('na.no_akun, na.nama_akun');
        $builder->orderBy('na.no_akun', 'asc');

        return $builder->get()->getResult();
    }
    public function getTotalGrandparent($tanggal_awal = null, $tanggal_akhir = null, $unit = null)
    {
        $db = \Config\Database::connect();

        $builder = $db->table('jurnal j');
        $builder->select("
        '1000000000' as no_akun,
        'ASET' as nama_akun,
        COALESCE(SUM(j.debet), 0) as total_debet,
        COALESCE(SUM(j.kredit), 0) as total_kredit
    ");
        $builder->like('j.no_akun', '10', 'after'); // prefix 10 = ASET

        if ($unit !== null) {
            $builder->where('j.id_unit', $unit);
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder->where('j.tanggal >=', $tanggal_awal);
            $builder->where('j.tanggal <=', $tanggal_akhir);
        }

        return $builder->get()->getRow();
    }

    /**
     * Mendapatkan data laba rugi dari jurnal
     * Akun pendapatan biasanya prefix 4, akun biaya prefix 5 atau 6
     */
    public function getLabaRugiFromJurnal($tanggal_awal = null, $tanggal_akhir = null, $unit = null, $show_saldo_0 = 9)
    {
        $db = \Config\Database::connect();

        $applyFilters = function ($builder) use ($tanggal_awal, $tanggal_akhir, $unit) {
            if (!empty($unit)) {
                $builder->where('j.id_unit', $unit);
            }
            if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
                $builder->where('DATE(j.tanggal) >=', $tanggal_awal)
                    ->where('DATE(j.tanggal) <=', $tanggal_akhir);
            } elseif (!empty($tanggal_awal)) {
                $builder->where('DATE(j.tanggal) >=', $tanggal_awal);
            } elseif (!empty($tanggal_akhir)) {
                $builder->where('DATE(j.tanggal) <=', $tanggal_akhir);
            }
        };

        // 1. Ambil akun pendapatan (prefix 4)
        $builder_pendapatan = $db->table('no_akun na');
        $builder_pendapatan->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as saldo
        ");
        $builder_pendapatan->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_pendapatan->like('na.no_akun', '4', 'after'); // prefix 4 = pendapatan
        $builder_pendapatan->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_pendapatan->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if ($unit !== null) {
            $builder_pendapatan->groupStart();
            $builder_pendapatan->where('j.id_unit', $unit);
            $builder_pendapatan->orWhere('j.id_unit IS NULL');
            $builder_pendapatan->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_pendapatan->groupStart();
            $builder_pendapatan->where('j.tanggal >=', $tanggal_awal);
            $builder_pendapatan->where('j.tanggal <=', $tanggal_akhir);
            $builder_pendapatan->orWhere('j.tanggal IS NULL');
            $builder_pendapatan->groupEnd();
        }

        $builder_pendapatan->groupBy('na.no_akun, na.nama_akun');
        if ($show_saldo_0 != 1) {
            $builder_pendapatan->having('saldo !=', 0);
        }
        $builder_pendapatan->orderBy('na.no_akun', 'asc');
        $pendapatan = $builder_pendapatan->get()->getResult();

        // 2. Ambil akun biaya/beban - PISAHKAN menjadi 2 kategori
        // 2a. Beban Pokok Penjualan (prefix 5)
        $builder_beban_pokok = $db->table('no_akun na');
        $builder_beban_pokok->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_pokok->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_pokok->like('na.no_akun', '5', 'after'); // prefix 5 = BEBAN POKOK PENJUALAN
        $builder_beban_pokok->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_pokok->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if ($unit !== null) {
            $builder_beban_pokok->groupStart();
            $builder_beban_pokok->where('j.id_unit', $unit);
            $builder_beban_pokok->orWhere('j.id_unit IS NULL');
            $builder_beban_pokok->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_pokok->groupStart();
            $builder_beban_pokok->where('j.tanggal >=', $tanggal_awal);
            $builder_beban_pokok->where('j.tanggal <=', $tanggal_akhir);
            $builder_beban_pokok->orWhere('j.tanggal IS NULL');
            $builder_beban_pokok->groupEnd();
        }

        $builder_beban_pokok->groupBy('na.no_akun, na.nama_akun');
        if ($show_saldo_0 != 1) {
            $builder_beban_pokok->having('saldo !=', 0);
        }
        $builder_beban_pokok->orderBy('na.no_akun', 'asc');
        $beban_pokok_penjualan = $builder_beban_pokok->get()->getResult();

        // 2b. Beban Operasional (prefix 6 dan 7)
        $builder_beban_operasional = $db->table('no_akun na');
        $builder_beban_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_operasional->groupStart();
        $builder_beban_operasional->like('na.no_akun', '6', 'after'); // prefix 6 = BEBAN OPERASIONAL
        $builder_beban_operasional->groupEnd();
        $builder_beban_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if ($unit !== null) {
            $builder_beban_operasional->groupStart();
            $builder_beban_operasional->where('j.id_unit', $unit);
            $builder_beban_operasional->orWhere('j.id_unit IS NULL');
            $builder_beban_operasional->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_operasional->groupStart();
            $builder_beban_operasional->where('j.tanggal >=', $tanggal_awal);
            $builder_beban_operasional->where('j.tanggal <=', $tanggal_akhir);
            $builder_beban_operasional->orWhere('j.tanggal IS NULL');
            $builder_beban_operasional->groupEnd();
        }

        $builder_beban_operasional->groupBy('na.no_akun, na.nama_akun');
        if ($show_saldo_0 != 1) {
            $builder_beban_operasional->having('saldo !=', 0);
        }
        $builder_beban_operasional->orderBy('na.no_akun', 'asc');
        $beban_operasional = $builder_beban_operasional->get()->getResult();

        // 3. Pendapatan Non Operasional (prefix 701)
        $builder_pendapatan_non_operasional = $db->table('no_akun na');
        $builder_pendapatan_non_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as saldo
        ");
        $builder_pendapatan_non_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_pendapatan_non_operasional->like('na.no_akun', '701', 'after'); // prefix 701 = PENDAPATAN NON OPERASIONAL
        $builder_pendapatan_non_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_pendapatan_non_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if ($unit !== null) {
            $builder_pendapatan_non_operasional->groupStart();
            $builder_pendapatan_non_operasional->where('j.id_unit', $unit);
            $builder_pendapatan_non_operasional->orWhere('j.id_unit IS NULL');
            $builder_pendapatan_non_operasional->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_pendapatan_non_operasional->groupStart();
            $builder_pendapatan_non_operasional->where('j.tanggal >=', $tanggal_awal);
            $builder_pendapatan_non_operasional->where('j.tanggal <=', $tanggal_akhir);
            $builder_pendapatan_non_operasional->orWhere('j.tanggal IS NULL');
            $builder_pendapatan_non_operasional->groupEnd();
        }

        $builder_pendapatan_non_operasional->groupBy('na.no_akun, na.nama_akun');
        // $builder_pendapatan_non_operasional->having('saldo !=', 0);
        $builder_pendapatan_non_operasional->orderBy('na.no_akun', 'asc');
        $pendapatan_non_operasional = $builder_pendapatan_non_operasional->get()->getResult();

        // 4. Beban Non Operasional (prefix 702)
        $builder_beban_non_operasional = $db->table('no_akun na');
        $builder_beban_non_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_non_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_non_operasional->like('na.no_akun', '702', 'after'); // prefix 702 = BEBAN NON OPERASIONAL
        $builder_beban_non_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_non_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if ($unit !== null) {
            $builder_beban_non_operasional->groupStart();
            $builder_beban_non_operasional->where('j.id_unit', $unit);
            $builder_beban_non_operasional->orWhere('j.id_unit IS NULL');
            $builder_beban_non_operasional->groupEnd();
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_non_operasional->groupStart();
            $builder_beban_non_operasional->where('j.tanggal >=', $tanggal_awal);
            $builder_beban_non_operasional->where('j.tanggal <=', $tanggal_akhir);
            $builder_beban_non_operasional->orWhere('j.tanggal IS NULL');
            $builder_beban_non_operasional->groupEnd();
        }

        $builder_beban_non_operasional->groupBy('na.no_akun, na.nama_akun');
        if ($show_saldo_0 != 1) {
            $builder_beban_non_operasional->having('saldo !=', 0);
        }
        $builder_beban_non_operasional->orderBy('na.no_akun', 'asc');
        $beban_non_operasional = $builder_beban_non_operasional->get()->getResult();

        // Detail pendapatan
        $builder_detail_pendapatan = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '4', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_pendapatan);
        $detail_pendapatan = $builder_detail_pendapatan->get()->getResult();

        // Detail biaya - juga perlu dipisah
        // Detail beban pokok penjualan (kode 5)
        $builder_detail_beban_pokok = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '5', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_pokok);
        $detail_beban_pokok = $builder_detail_beban_pokok->get()->getResult();

        // Detail beban operasional (kode 6 dan 7)
        $builder_detail_beban_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->groupStart()
            ->like('j.no_akun', '6', 'after')
            ->groupEnd()
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_operasional);
        $detail_beban_operasional = $builder_detail_beban_operasional->get()->getResult();

        // Detail pendapatan non operasional (kode 701)
        $builder_detail_pendapatan_non_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '701', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_pendapatan_non_operasional);
        $detail_pendapatan_non_operasional = $builder_detail_pendapatan_non_operasional->get()->getResult();

        // Detail beban non operasional (kode 702)
        $builder_detail_beban_non_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '702', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_non_operasional);
        $detail_beban_non_operasional = $builder_detail_beban_non_operasional->get()->getResult();

        // Hitung total
        $total_pendapatan = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $pendapatan));

        $total_beban_pokok_penjualan = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_pokok_penjualan));

        $total_beban_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_operasional));

        $total_pendapatan_non_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $pendapatan_non_operasional));

        $total_beban_non_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_non_operasional));

        $total_biaya = $total_beban_pokok_penjualan + $total_beban_operasional;
        $laba_rugi = $total_pendapatan - $total_biaya + $total_pendapatan_non_operasional - $total_beban_non_operasional;

        return [
            'pendapatan' => $pendapatan,
            'total_pendapatan' => $total_pendapatan,
            'beban_pokok_penjualan' => $beban_pokok_penjualan,
            'total_beban_pokok_penjualan' => $total_beban_pokok_penjualan,
            'beban_operasional' => $beban_operasional,
            'total_beban_operasional' => $total_beban_operasional,
            'pendapatan_non_operasional' => $pendapatan_non_operasional,
            'total_pendapatan_non_operasional' => $total_pendapatan_non_operasional,
            'beban_non_operasional' => $beban_non_operasional,
            'total_beban_non_operasional' => $total_beban_non_operasional,
            'biaya' => array_merge($beban_pokok_penjualan, $beban_operasional), // untuk backward compatibility
            'total_biaya' => $total_biaya,
            'laba_rugi' => $laba_rugi,
            'detail' => [
                'pendapatan' => $detail_pendapatan,
                'beban_pokok_penjualan' => $detail_beban_pokok,
                'beban_operasional' => $detail_beban_operasional,
                'pendapatan_non_operasional' => $detail_pendapatan_non_operasional,
                'beban_non_operasional' => $detail_beban_non_operasional,
                'biaya' => array_merge($detail_beban_pokok, $detail_beban_operasional), // untuk backward compatibility
            ],
        ];
    }

}
