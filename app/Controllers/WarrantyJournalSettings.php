<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Services\Finance\WarrantyExpenseJournal;
use Config\Database;

class WarrantyJournalSettings extends BaseController
{
    public function index()
    {
        if ((int) session('ID_JABATAN') !== 1) {
            return redirect()->to(base_url('/'))->with('gagal', 'Akses ditolak.');
        }

        $db = Database::connect();
        return view('template', [
            'akun' => (new ModelAuth())->getById(session('ID_AKUN')),
            'accounts' => $db->table('no_akun')->select('no_akun, nama_akun')->orderBy('no_akun')->get()->getResult(),
            'mapping' => $db->table('template_jurnal')->select('no_akun, debet_kredit')
                ->where('kode_template', 'garansi_sparepart_keluar')->get()->getResultArray(),
            'body' => 'jurnal/akun_garansi',
        ]);
    }

    public function save()
    {
        if ((int) session('ID_JABATAN') !== 1) {
            return redirect()->to(base_url('/'))->with('gagal', 'Akses ditolak.');
        }

        $expense = (string) $this->request->getPost('akun_beban');
        $inventory = (string) $this->request->getPost('akun_persediaan');
        $transactionStarted = false;
        try {
            $lines = WarrantyExpenseJournal::lines([
                ['no_akun' => $expense, 'debet_kredit' => 'debet'],
                ['no_akun' => $inventory, 'debet_kredit' => 'kredit'],
            ], 1);
            $db = Database::connect();
            $accounts = $db->table('no_akun')->select('no_akun, nama_akun')
                ->whereIn('no_akun', array_column($lines, 0))->get()->getResult();
            $names = [];
            foreach ($accounts as $account) {
                $names[$account->no_akun] = $account->nama_akun;
            }
            if (count($names) !== 2 || ! preg_match('/persediaan|stok|inventory/i', $names[$inventory])) {
                throw new \RuntimeException('Pilih akun persediaan yang terdaftar pada daftar akun.');
            }

            $db->transBegin();
            $transactionStarted = true;
            if (! $db->table('template_jurnal')->where('kode_template', 'garansi_sparepart_keluar')->delete()) {
                throw new \RuntimeException('Template lama gagal dihapus.');
            }
            foreach ($lines as $line) {
                if (! $db->table('template_jurnal')->insert([
                    'kode_template' => 'garansi_sparepart_keluar',
                    'no_akun' => $line[0],
                    'nama_akun' => $names[$line[0]],
                    'debet_kredit' => $line[1] > 0 ? 'debet' : 'kredit',
                    'array_value' => 0,
                ])) {
                    throw new \RuntimeException('Template garansi gagal disimpan.');
                }
            }
            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Template garansi gagal disimpan.');
            }
            return redirect()->to(base_url('akun_jurnal_garansi'))->with('sukses', 'Akun jurnal garansi tersimpan.');
        } catch (\Throwable $error) {
            if ($transactionStarted) {
                $db->transRollback();
            }
            return redirect()->to(base_url('akun_jurnal_garansi'))->with('gagal', $error->getMessage());
        }
    }
}
