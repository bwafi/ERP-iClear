<?php

namespace App\Controllers;

use App\Models\ModelStokAwal;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\ModelStokOpname;
use App\Models\ModelStokOpnameDraft;
use App\Controllers\StokAwal;
use App\Models\ModelBarang;
use App\Models\ModelStokBarang;
use App\Models\ModelHppBarang;
use App\Models\ModelUnit;



class StokOpname extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $StokOpnameModel;
    protected $StokOpnameDraftModel;
    protected $PeriodeModel;
    protected $StokAwalModel;
    protected $BarangModel;
    protected $StokBarangModel;
    protected $HppBarangModel;
    protected $UnitModel;
    protected $svc;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->StokOpnameModel = new ModelStokOpname();
        $this->StokOpnameDraftModel = new ModelStokOpnameDraft();
        $this->PeriodeModel = new \App\Models\ModelStokOpnamePeriode();
        $this->StokAwalModel = new ModelStokAwal();
        $this->BarangModel = new ModelBarang();
        $this->StokBarangModel = new ModelStokBarang();
        $this->HppBarangModel = new ModelHppBarang();
        $this->UnitModel = new ModelUnit();
        $this->svc = new \App\Services\StokOpnameService();
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));

        // Hanya Admin Center/Root, Direktur, dan Manager yang boleh memilih unit & tanggal.
        // Operator/input stok opname mengikuti unit & tanggal miliknya sendiri (hari ini),
        // sehingga tidak ada tanggal/unit yang membingungkan saat input.
        $myJabatan = (int)session('ID_JABATAN');
        $isCrossUnit = in_array($myJabatan, [0, 1, 2, 34], true);
        $canPickUnit = (bool)$isCrossUnit;

        // Filter selisih disediakan untuk role pengawas:
        // Admin Center/Root, Direktur, Manager, dan SPV.
        $canFilterSelisih = (bool)in_array($myJabatan, [0, 1, 2, 34, 40], true);

        // Role pengawas HANYA boleh melihat (tidak boleh mulai/simpan/finalisasi/reopen).
        $supervisorRoles = [0, 1, 2, 34, 40];
        $canMutate = (bool)!in_array($myJabatan, $supervisorRoles, true);

        $myUnit = (int)session('ID_UNIT');
        $unit = $isCrossUnit ? (int)($this->request->getGet('unit') ?: $myUnit) : $myUnit;
        if ($unit <= 0) {
            $unitList0 = $this->UnitModel->getUnit();
            $unit = !empty($unitList0) ? (int)($unitList0[0]->idunit ?? 1) : 1;
        }

        $tanggal = $isCrossUnit && $this->request->getGet('tanggal') !== null
            ? (string)$this->request->getGet('tanggal')
            : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }

        return view('template', [
            'akun'             => $akun,
            'unitList'         => $this->UnitModel->getUnit(),
            'unit'             => $unit,
            'myUnit'           => $myUnit,
            'tanggal'          => $tanggal,
            'canPickUnit'      => $canPickUnit,
            'canFilterSelisih' => $canFilterSelisih,
            'canMutate'        => $canMutate,
            'periode'          => $this->svc->periode($unit, $tanggal),
            'items'            => $this->svc->periodeItems($unit, $tanggal),
            'historis'         => $this->PeriodeModel->getByUnit($unit, 20),
            'body'             => 'stok/stok_opname',
        ]);
    }

    /**
     * Role pengawas (Admin Center/Root, Direktur, Manager, SPV) hanya boleh melihat.
     * Guard dipakai di semua aksi mutasi (mulai/simpan/finalisasi/reopen).
     */
    private function isSupervisorOnly(): bool
    {
        return in_array((int)session('ID_JABATAN'), [0, 1, 2, 34, 40], true);
    }

    /**
     * Mulai stok opname: buat periode DRAFT + seed daftar barang.
     */
    public function mulai()
    {
        if ($this->isSupervisorOnly()) {
            return redirect()->to(base_url('stok_opname'))->with('gagal', 'Mode lihat: pengawas tidak dapat memulai stok opname.');
        }

        $isCrossUnit = in_array((int)session('ID_JABATAN'), [0, 1, 2, 34], true);
        $unit = (int)$this->request->getPost('unit');
        $tanggal = (string)($this->request->getPost('tanggal') ?: date('Y-m-d'));

        // Operator dibatasi unit & tanggal sendiri, tidak bisa memaksa unit lain.
        if (!$isCrossUnit) {
            $unit = (int)session('ID_UNIT');
            $tanggal = date('Y-m-d');
        }

        $url = base_url("stok_opname?unit=$unit&tanggal=$tanggal");

        $r = $this->svc->createPeriode($unit, $tanggal, (int)session('ID_AKUN'));
        if ($r['success']) {
            return redirect()->to($url)->with('sukses', 'Stok opname dimulai sebagai DRAFT. Silakan isi jumlah real secara bertahap.');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    /**
     * Simpan DRAFT: update jumlah_real (boleh dicicil/parsial).
     * Jika aksi = finalisasi, simpan dulu lalu finalisasi (validasi lengkap).
     */
    public function simpan()
    {
        if ($this->isSupervisorOnly()) {
            return redirect()->to(base_url('stok_opname'))->with('gagal', 'Mode lihat: pengawas tidak dapat menyimpan draft stok opname.');
        }

        $isCrossUnit = in_array((int)session('ID_JABATAN'), [0, 1, 2, 34], true);
        $unit = (int)$this->request->getPost('unit');
        $tanggal = (string)($this->request->getPost('tanggal') ?: date('Y-m-d'));
        if (!$isCrossUnit) {
            $unit = (int)session('ID_UNIT');
            $tanggal = date('Y-m-d');
        }
        $aksi = (string)($this->request->getPost('aksi') ?: 'simpan');
        $url = base_url("stok_opname?unit=$unit&tanggal=$tanggal");
        $userId = (int)session('ID_AKUN');

        $rows = (array)($this->request->getPost('items') ?: []);
        $realRows = [];
        foreach ($rows as $bid => $v) {
            if (is_array($v) && isset($v['jumlah_real'])) {
                $realRows[(int)$bid] = $v['jumlah_real'];
            }
        }
        if (empty($realRows) && $aksi !== 'finalisasi') {
            return redirect()->to($url)->with('gagal', 'Tidak ada data jumlah real yang disimpan.');
        }

        $r = $this->svc->saveDraft($unit, $tanggal, $realRows, $userId);
        if (!$r['success']) {
            return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
        }

        if ($aksi === 'finalisasi') {
            $rf = $this->svc->finalize($unit, $tanggal, $userId);
            if (!$rf['success']) {
                return redirect()->to($url)->with('gagal', implode(' ', $rf['errors']) . ' Draft tetap tersimpan.');
            }
            $msg = 'Stok opname berhasil disimpan & difinalisasi (FINAL).';
            if (!empty($rf['warning'])) {
                $msg .= ' ' . $rf['warning'];
            }
            return redirect()->to($url)->with('sukses', $msg);
        }

        $msg = count($r['errors']) > 0
            ? implode(' ', $r['errors'])
            : 'Draft stok opname berhasil disimpan (' . (int)$r['saved'] . ' barang ter-update).';
        return redirect()->to($url)->with('sukses', $msg);
    }

    /**
     * Finalisasi: semua barang wajib terisi, salin ke stok_opname & kunci FINAL.
     */
    public function finalisasi()
    {
        if ($this->isSupervisorOnly()) {
            return redirect()->to(base_url('stok_opname'))->with('gagal', 'Mode lihat: pengawas tidak dapat memfinalisasi stok opname.');
        }

        $isCrossUnit = in_array((int)session('ID_JABATAN'), [0, 1, 2, 34], true);
        $unit = (int)$this->request->getPost('unit');
        $tanggal = (string)($this->request->getPost('tanggal') ?: date('Y-m-d'));
        if (!$isCrossUnit) {
            $unit = (int)session('ID_UNIT');
            $tanggal = date('Y-m-d');
        }
        $url = base_url("stok_opname?unit=$unit&tanggal=$tanggal");

        $r = $this->svc->finalize($unit, $tanggal, (int)session('ID_AKUN'));
        if ($r['success']) {
            $msg = 'Stok opname berhasil difinalisasi. Data tercatat di riwayat & KPI.';
            if (!empty($r['warning'])) {
                $msg .= ' ' . $r['warning'];
            }
            return redirect()->to($url)->with('sukses', $msg);
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    /**
     * Reopen: koreksi hasil final -> kembali DRAFT.
     */
    public function reopen()
    {
        if ($this->isSupervisorOnly()) {
            return redirect()->to(base_url('stok_opname'))->with('gagal', 'Mode lihat: pengawas tidak dapat membuka kembali (koreksi) stok opname.');
        }

        $isCrossUnit = in_array((int)session('ID_JABATAN'), [0, 1, 2, 34], true);
        $unit = (int)$this->request->getPost('unit');
        $tanggal = (string)($this->request->getPost('tanggal') ?: date('Y-m-d'));
        if (!$isCrossUnit) {
            $unit = (int)session('ID_UNIT');
            $tanggal = date('Y-m-d');
        }
        $url = base_url("stok_opname?unit=$unit&tanggal=$tanggal");

        $r = $this->svc->reopen($unit, $tanggal, (int)session('ID_AKUN'));
        if ($r['success']) {
            return redirect()->to($url)->with('sukses', 'Stok opname dibuka kembali (DRAFT). Koreksi jumlah real lalu finalisasi ulang.');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    public function loadTable()
    {
        $table = $this->request->getGet('table');

        $draw = (int) $this->request->getGet('draw');
        $start = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');

        // DataTables mengirim search[value] sebagai nested param
        $searchParam = $this->request->getGet('search');
        $search = '';
        if (is_array($searchParam)) {
            $search = trim($searchParam['value'] ?? '');
        } else {
            $search = trim((string) $searchParam);
        }

        $unitFilter = trim($this->request->getGet('unit') ?? '');

        $orderCol = $this->request->getGet('order') ? $this->request->getGet('order')[0]['column'] : 1;
        $orderDir = $this->request->getGet('order') ? $this->request->getGet('order')[0]['dir'] : 'desc';

        $columnMap = [
            0 => null, // checkbox — not orderable
            1 => 'barang.kode_barang',
            2 => 'barang.nama_barang',
            3 => 'unit.NAMA_UNIT',
            4 => 'jumlah_komp',
            5 => 'jumlah_real',
            6 => 'jumlah_selisih',
        ];

        $orderCol = $columnMap[$orderCol] ?? 'barang.nama_barang';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $totalRecords = 0;
        $filteredRecords = 0;
        $data = [];

        if ($table === 'tabledaraft') {
            $totalRecords = $this->StokOpnameDraftModel->countStokOpnameDraftDT('', '');
            $filteredRecords = $this->StokOpnameDraftModel->countStokOpnameDraftDT($search, $unitFilter);
            $results = $this->StokOpnameDraftModel->getStokOpnameDraftDT($length, $start, $search, $orderCol, $orderDir, $unitFilter);

            foreach ($results as $row) {
                $data[] = [
                    'idstok_opname' => $row->idstok_opname,
                    'tanggal' => date('Y-m-d', strtotime($row->tanggal)),
                    'jumlah_real' => $row->jumlah_real,
                    'jumlah_komp' => $row->jumlah_komp,
                    'jumlah_selisih' => $row->jumlah_selisih,
                    'kode_barang' => $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'jenis_hp' => $row->jenis_hp,
                    'warna' => $row->warna,
                    'NAMA_UNIT' => $row->NAMA_UNIT,
                    'barang_idbarang' => $row->barang_idbarang,
                    'unit_idunit' => $row->unit_idunit,
                ];
            }
        } elseif ($table === 'tablefix') {
            $totalRecords = $this->StokOpnameModel->countStokOpnameAllDT('', '');
            $filteredRecords = $this->StokOpnameModel->countStokOpnameAllDT($search, $unitFilter);
            $results = $this->StokOpnameModel->getStokOpnameAllDT($length, $start, $search, $orderCol, $orderDir, $unitFilter);

            foreach ($results as $row) {
                $data[] = [
                    'idstok_opname' => $row->idstok_opname,
                    'tanggal' => date('Y-m-d', strtotime($row->tanggal)),
                    'jumlah_real' => $row->jumlah_real,
                    'jumlah_komp' => $row->jumlah_komp,
                    'jumlah_selisih' => $row->jumlah_selisih,
                    'kode_barang' => $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'jenis_hp' => $row->jenis_hp,
                    'warna' => $row->warna,
                    'NAMA_UNIT' => $row->NAMA_UNIT,
                    'barang_idbarang' => $row->barang_idbarang,
                    'unit_idunit' => $row->unit_idunit,
                ];
            }
        } else {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid table name']);
        }

        return $this->response->setJSON([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }
}

