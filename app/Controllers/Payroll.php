<?php

namespace App\Controllers;
use App\Models\ModelBank;

class Payroll extends BaseController
{
    protected $db;
    protected $BankModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function getPayrollLocks()
    {
        $file = WRITEPATH . 'locks/payroll.json';

        if (!file_exists($file)) {
            return [];
        }

        $data = json_decode(
            file_get_contents($file),
            true
        );

        return is_array($data) ? $data : [];
    }

    private function savePayrollLocks(array $locks)
    {
        $file = WRITEPATH . 'locks/payroll.json';

        file_put_contents(
            $file,
            json_encode($locks, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public function lockPayroll()
    {
        $id = $this->request->getPost('idkas_keluar');

        if (empty($id)) {
            return redirect()->back()
                ->with('error', 'ID payroll tidak ditemukan.');
        }

        $locks = $this->getPayrollLocks();

        $locks[(string) $id] = true;

        $this->savePayrollLocks($locks);

        return redirect()->back()
            ->with('success', 'Payroll berhasil dikunci.');
    }

    public function unlockPayroll()
    {
        $id = $this->request->getPost('idkas_keluar');

        $locks = $this->getPayrollLocks();

        unset($locks[(string) $id]);

        $this->savePayrollLocks($locks);

        return redirect()->back()
            ->with('success', 'Payroll berhasil dibuka kembali.');
    }

    public function index()
    {
        $payrollLocks = $this->getPayrollLocks();

        // --- Periode seleksi (default bulan berjalan) ---
        $bulanParam = $this->request->getGet('bulan');
        if ($bulanParam === null) {
            $bulan   = date('Y-m');
            $showAll = false;
        } elseif ($bulanParam === '') {
            $bulan   = date('Y-m');
            $showAll = true;
        } else {
            $bulan   = trim((string) $bulanParam);
            $showAll = false;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = date('Y-m');
        }
        $tahun = (int) substr($bulan, 0, 4);
        $bln   = (int) substr($bulan, 5, 2);
        $from  = $bulan . '-01';
        $to    = $showAll ? '' : date('Y-m-t', strtotime($from));

        // --- Jadwal & realisasi gaji (finance_payroll) ---
        $builder = $this->db->table('finance_payroll fp')
            ->select('fp.*, u.NAMA_UNIT, a.NAMA_AKUN')
            ->join('unit u', 'u.idunit = fp.unit_id', 'left')
            ->join('akun a', 'a.ID_AKUN = fp.pegawai_id', 'left');

        if (!$showAll) {
            $builder->where('fp.due_date >=', $from)
                ->where('fp.due_date <=', $to);
        }

        $payrollItems = $builder
            ->orderBy('fp.due_date', 'DESC')
            ->orderBy('u.NAMA_UNIT', 'ASC')
            ->get()
            ->getResult();

        // --- Ringkasan KPI per unit (hanya saat bulan dipilih) ---
        $payrollSummary = [];
        if (!$showAll) {
            $calc = new \App\Services\Finance\PayrollTimelinessCalculator();
            foreach ($payrollItems as $row) {
                $unitId = (int) $row->unit_id;
                if (isset($payrollSummary[$unitId])) {
                    continue;
                }
                $r = $calc->calculate($unitId, $bln, $tahun);
                $r['nama_unit'] = $row->NAMA_UNIT;
                $payrollSummary[$unitId] = $r;
            }
        }

        $scope = new \App\Services\Finance\FinanceScopeService();

        $builder = $this->db->table('kas_keluar kk');

        $builder->select('
            kk.*,
            u.NAMA_UNIT,
            k.kategori,
            a.NAMA_AKUN,
            b.nama_bank,
            b.atas_nama
        ');

        $builder->join(
            'unit u',
            'u.idunit = kk.idunit',
            'left'
        );

        $builder->join(
            'kategori_kas k',
            'k.idkategori_kas = kk.kategori_idkategori',
            'left'
        );

        $builder->join(
            'akun a',
            'a.id_akun = kk.penerima',
            'left'
        );

        $builder->join(
            'bank b',
            'b.idbank = kk.idbank',
            'left'
        );

        $builder->where('kk.kategori_idkategori', 10)->groupStart()->like('kk.deskripsi', 'bon')->orLike('kk.deskripsi', 'lembur')->groupEnd()->orderBy('kk.tanggal', 'DESC');
        $builder->orderBy('kk.idkas_keluar', 'DESC');

        $kas_keluar = $builder->get()->getResult();

        // Data unit
        $unit = $this->db->table('unit')
            ->orderBy('NAMA_UNIT', 'ASC')
            ->get()
            ->getResult();

        // Data kategori
        $kategori_kas = $this->db->table('kategori_kas')
            ->orderBy('kategori', 'ASC')
            ->get()
            ->getResult();

        // Data akun untuk penerima / karyawan
        $akun = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, ID_UNIT')
            ->orderBy('NAMA_AKUN', 'ASC')
            ->get()
            ->getResult();
        
        $this->BankModel = new ModelBank();
        

        return view('template', [
            'payrollLocks'  => $payrollLocks,
            'payrollItems'  => $payrollItems,
            'payrollSummary' => array_values($payrollSummary),
            'bulan'         => $bulan,
            'show_all'      => $showAll,
            'can_input'     => $scope->canInput(),
            'kas_keluar'    => $kas_keluar,
            'unit'          => $unit,
            'kategori_kas'  => $kategori_kas,
            'akun'          => $akun,
            'bank'          => $this->BankModel->getBank(),
            'body'          => 'jurnal/payroll'
        ]);
    }


    /**
     * INSERT
     */
    public function insert()
    {
        $tanggal             = $this->request->getPost('tanggal');
        $noRekening = $this->request->getPost('no_rekening') ?? null;
            if (empty($noRekening)) {
                $noRekening = null;
            }
        $kategori_idkategori = 10;
        $deskripsi           = $this->request->getPost('deskripsi');
        $jumlah              = $this->request->getPost('jumlah');
        $penerima            = $this->request->getPost('penerima');
        $idunit              = $this->request->getPost('idunit');
        $jenis               = $this->request->getPost('jenis');

        // Bersihkan format rupiah jika ada
        $jumlah = preg_replace('/[^0-9]/', '', $jumlah);

        $data = [
            'tanggal'             => $tanggal,
            'kategori_idkategori' => $kategori_idkategori,
            'deskripsi'           => $deskripsi,
            'jumlah'              => $jumlah ?: 0,
            'penerima'            => $penerima,
            'idunit'              => $idunit,
            'jenis'               => $jenis,
            'idbank'              => $noRekening,
            'created_on'          => date('Y-m-d H:i:s'),
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        $this->db->table('kas_keluar')->insert($data);

        return redirect()->to(base_url('payroll2'))
            ->with('success', 'Data kas keluar berhasil ditambahkan.');
    }


    /**
     * UPDATE
     */
    public function update()
    {
        $id = $this->request->getPost('idkas_keluar');

        $locks = $this->getPayrollLocks();

        if (!empty($locks[(string) $id])) {
            return redirect()->back()
                ->with('error', 'Payroll ini sudah dikunci dan tidak dapat diubah.');
        }

        $jumlah = $this->request->getPost('jumlah');
        $jumlah = preg_replace('/[^0-9]/', '', $jumlah);

        $noRekening = $this->request->getPost('no_rekening') ?? null;
            if (empty($noRekening)) {
                $noRekening = null;
            }

        $data = [
            'tanggal'             => $this->request->getPost('tanggal'),
            'deskripsi'           => $this->request->getPost('deskripsi'),
            'jumlah'              => $jumlah ?: 0,
            'penerima'            => $this->request->getPost('penerima'),
            'idbank'              => $noRekening,
            'idunit'              => $this->request->getPost('idunit'),
            'jenis'               => $this->request->getPost('jenis'),
            'no_akun'             => $this->request->getPost('no_akun'),
            'updated_on'          => date('Y-m-d H:i:s')
        ];

        $this->db->table('kas_keluar')
            ->where('idkas_keluar', $id)
            ->update($data);

        return redirect()->to(base_url('payroll2'))
            ->with('success', 'Data kas keluar berhasil diperbarui.');
    }


    /**
     * DELETE
     */
    public function delete()
    {
        $id = $this->request->getPost('idkas_keluar');

        $locks = $this->getPayrollLocks();

        if (!empty($locks[(string) $id])) {
            return redirect()->back()
                ->with('error', 'Payroll ini sudah dikunci dan tidak dapat dihapus.');
        }

        $this->db->table('kas_keluar')
            ->where('idkas_keluar', $id)
            ->delete();

        return redirect()->to(base_url('payroll2'))
            ->with('success', 'Data kas keluar berhasil dihapus.');
    }


    /**
     * Tandai satu baris payroll gaji (finance_payroll) sebagai sudah dibayar.
     */
    public function bayar()
    {
        $scope = new \App\Services\Finance\FinanceScopeService();

        if (!$scope->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengubah payroll.');
        }

        $id       = (int) ($this->request->getPost('id') ?? 0);
        $paidDate = (string) ($this->request->getPost('paid_date') ?? '');
        if ($paidDate === '') {
            $paidDate = date('Y-m-d');
        }

        if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
            return redirect()->back()->with('gagal', 'Data pembayaran tidak valid.');
        }

        $model = new \App\Models\ModelFinancePayroll();
        $row   = $model->find($id);

        if (!$row) {
            return redirect()->back()->with('gagal', 'Data payroll tidak ditemukan.');
        }

        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $scope->resolveAllowedUnits()),
            'idunit'
        ));

        if (!in_array((int) $row->unit_id, $allowedIds, true)) {
            return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
        }

        $model->update($id, [
            'paid_date' => $paidDate,
            'status'    => 'dibayar',
        ]);

        return redirect()->back()->with('sukses', 'Payroll ditandai sudah dibayar (' . $paidDate . ').');
    }
}