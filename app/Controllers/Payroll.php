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
                ->with('gagal', 'ID payroll tidak ditemukan.');
        }

        $locks = $this->getPayrollLocks();

        $locks[(string) $id] = true;

        $this->savePayrollLocks($locks);

        return redirect()->back()
            ->with('sukses', 'Payroll berhasil dikunci.');
    }

    public function unlockPayroll()
    {
        $id = $this->request->getPost('idkas_keluar');

        $locks = $this->getPayrollLocks();

        unset($locks[(string) $id]);

        $this->savePayrollLocks($locks);

        return redirect()->back()
            ->with('sukses', 'Payroll berhasil dibuka kembali.');
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
            ->with('sukses', 'Data kas keluar berhasil ditambahkan.');
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
                ->with('gagal', 'Payroll ini sudah dikunci dan tidak dapat diubah.');
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
            ->with('sukses', 'Data kas keluar berhasil diperbarui.');
    }


    /**
     * Susun draft payroll gaji dari master salary_structures.
     *
     * Finance tidak lagi mengetik nominal satu per satu: sistem menghitung
     * dari master gaji + skor KPI bulan itu, membuat baris berstatus
     * 'rencana', lalu Finance tinggal mengoreksi bila perlu dan menekan
     * "Sudah Dibayar". Baris yang sudah ada tidak pernah ditimpa, jadi
     * generator ini aman dijalankan berulang kali untuk bulan yang sama.
     */
    public function generateRegister()
    {
        $scope = new \App\Services\Finance\FinanceScopeService();

        if (!$scope->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak menyusun payroll.');
        }

        $bulan = trim((string) ($this->request->getPost('bulan') ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            return redirect()->back()->with('gagal', 'Bulan payroll tidak valid.');
        }

        // Unit di luar lingkup Finance tidak boleh ikut tersusun diam-diam.
        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $scope->resolveAllowedUnits()),
            'idunit'
        ));

        $unitIds = array_values(array_filter(array_map(
            'intval',
            (array) ($this->request->getPost('unit_ids') ?? [])
        )));

        foreach ($unitIds as $unitId) {
            if (!in_array($unitId, $allowedIds, true)) {
                return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
            }
        }

        try {
            $generator = new \App\Services\Payroll\PayrollGenerator();
            $hasil = $generator->generate($bulan, [
                'due_date' => (string) ($this->request->getPost('due_date') ?? ''),
                'unit_ids' => $unitIds,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Payroll: generate register gagal: ' . $e->getMessage());
            return redirect()->back()->with('gagal', 'Payroll gagal disusun: ' . $e->getMessage());
        }

        return redirect()
            ->to(base_url('payroll2') . '?bulan=' . $bulan)
            ->with('sukses', $this->pesanGenerate($hasil));
    }

    /** Ringkasan hasil generate dalam bahasa yang bisa langsung dibaca. */
    private function pesanGenerate(array $hasil): string
    {
        $bulanLabel = date('F Y', strtotime($hasil['bulan'] . '-01'));

        $pesan = sprintf(
            'Payroll %s disusun dari salary_structures: %d karyawan jadi baris baru',
            $bulanLabel,
            $hasil['created']
        );

        if ($hasil['created'] > 0) {
            $pesan .= sprintf(' (total Rp %s)', number_format($hasil['total_nominal'], 0, ',', '.'));
        }

        if ($hasil['skipped'] > 0) {
            $pesan .= sprintf(', %d dilewati karena sudah ada', $hasil['skipped']);
        }

        if (($hasil['dikecualikan'] ?? 0) > 0) {
            $excluded = array_values(array_filter($hasil['rows'], static function ($row) {
                return $row['status'] === 'excluded';
            }));
            $nama = array_slice(array_column($excluded, 'nama'), 0, 3);

            $pesan .= sprintf(
                ', %d tidak digaji karena jabatannya (%s%s)',
                $hasil['dikecualikan'],
                implode(', ', $nama),
                count($excluded) > 3 ? ', ...' : ''
            );
        }

        if ($hasil['errors'] !== []) {
            $nama = array_slice(array_column($hasil['errors'], 'nama'), 0, 3);
            $pesan .= sprintf(
                ', %d gagal (%s)',
                count($hasil['errors']),
                implode(', ', $nama) . (count($hasil['errors']) > 3 ? ', ...' : '')
            );
        }

        if ($hasil['created'] === 0) {
            return $pesan . '. Tidak ada angka baru yang perlu ditinjau.';
        }

        return $pesan . '. Tinjau nominalnya, lalu tandai yang sudah dibayar.';
    }

    /**
     * Koreksi satu baris register gaji (total / jatuh tempo / catatan).
     *
     * Baris yang sudah lunas dikunci: nominal dan jatuh temponya sudah
     * terpakai untuk memotong kasbon dan menghitung KPI, jadi mengubahnya
     * diam-diam akan membuat angka yang tercatat jadi tidak cocok.
     */
    public function updateRegister()
    {
        $scope = new \App\Services\Finance\FinanceScopeService();

        if (!$scope->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengubah payroll.');
        }

        $id       = (int) ($this->request->getPost('id') ?? 0);
        $total    = preg_replace('/[^0-9]/', '', (string) ($this->request->getPost('total') ?? ''));
        $dueDate  = trim((string) ($this->request->getPost('due_date') ?? ''));
        $notes    = trim((string) ($this->request->getPost('notes') ?? ''));

        if ($id <= 0) {
            return redirect()->back()->with('gagal', 'Data payroll tidak valid.');
        }
        if ($total === '' || $total === '0') {
            return redirect()->back()->with('gagal', 'Total gaji harus lebih dari nol.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return redirect()->back()->with('gagal', 'Tanggal jatuh tempo tidak valid.');
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

        if ($row->status === 'dibayar') {
            return redirect()->back()->with('gagal', 'Payroll yang sudah dibayar tidak bisa diubah.');
        }

        $model->updateRegister($id, [
            'total'    => (int) $total,
            'due_date' => $dueDate,
            'notes'    => $notes,
        ]);

        return redirect()->back()->with('sukses', 'Perubahan payroll tersimpan.');
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
                ->with('gagal', 'Payroll ini sudah dikunci dan tidak dapat dihapus.');
        }

        $this->db->table('kas_keluar')
            ->where('idkas_keluar', $id)
            ->delete();

        return redirect()->to(base_url('payroll2'))
            ->with('sukses', 'Data kas keluar berhasil dihapus.');
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

        // Potong penuh sisa kasbon pegawai (idempotent per payroll).
        $potonganKasbon = 0;
        try {
            $service = new \App\Services\Finance\HutangPiutangService();
            $settle = $service->settleKasbonFromPayroll($id, (int) $row->pegawai_id, (int) $row->unit_id, (int) session('ID_AKUN'));
            if (!empty($settle['success'])) {
                $potonganKasbon = (int) ($settle['potongan'] ?? 0);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Settlement kasbon payroll #' . $id . ' gagal: ' . $e->getMessage());
        }

        $totalBersih = (int) ($row->total ?? 0) - $potonganKasbon;
        $model->update($id, [
            'potongan_kasbon' => $potonganKasbon,
            'total_bersih'    => $totalBersih,
        ]);

        $pesan = 'Payroll ditandai sudah dibayar (' . $paidDate . ').';
        if ($potonganKasbon > 0) {
            $pesan .= ' Potongan kasbon: Rp ' . number_format($potonganKasbon, 0, ',', '.') . '.';
        }

        return redirect()->back()->with('sukses', $pesan);
    }
}