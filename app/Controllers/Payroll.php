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

        // Data akun untuk penerima
        $akun = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN')
            ->orderBy('NAMA_AKUN', 'ASC')
            ->get()
            ->getResult();
        
        $this->BankModel = new ModelBank();
        

        return view('template', [
            'payrollLocks' => $payrollLocks,
            'kas_keluar'   => $kas_keluar,
            'unit'         => $unit,
            'kategori_kas' => $kategori_kas,
            'akun'         => $akun,
            'bank'         => $this->BankModel->getBank(),
            'body'         => 'jurnal/payroll'
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
}