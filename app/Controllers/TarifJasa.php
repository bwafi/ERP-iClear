<?php

namespace App\Controllers;

use App\Models\ModelBarang;
use App\Models\ModelAuth;

/**
 * Master Tarif Jasa (/tarif_jasa).
 *
 * Jasa hidup sebagai baris `barang` dengan jenis_barang = 'jasa' supaya
 * detail_penjualan, retur, DP, tutup kasir, dan KPI tetap berjalan tanpa
 * perubahan query. Halaman ini mengganti master barang untuk mengelola
 * jasa: tidak ada field stok, hanya kode/nama/harga.
 */
class TarifJasa extends BaseController
{
    protected $BarangModel;
    protected $AuthModel;

    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->AuthModel   = new ModelAuth();
    }

    public function index()
    {
        $data = [
            'akun' => $this->AuthModel->getById(session('ID_AKUN')),
            'jasa' => $this->BarangModel
                ->where('jenis_barang', 'jasa')
                ->where('deleted', 0)
                ->orderBy('nama_barang', 'ASC')
                ->findAll(),
            'body' => 'jasa/tarif',
        ];

        return view('template', $data);
    }

    public function simpan()
    {
        $nama  = trim((string) $this->request->getPost('nama_barang'));
        $kode  = trim((string) $this->request->getPost('kode_barang'));
        $harga = (int) str_replace(['.', ','], '', (string) $this->request->getPost('harga'));

        if ($nama === '' || $kode === '' || $harga <= 0) {
            session()->setFlashdata('gagal', 'Kode, nama, dan harga jasa wajib diisi.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        if ($this->BarangModel->getBykode($kode)) {
            session()->setFlashdata('gagal', 'Kode jasa sudah dipakai barang lain.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $this->BarangModel->insert([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'harga'        => $harga,
            'harga_beli'   => (int) str_replace(['.', ','], '', (string) $this->request->getPost('harga_beli')),
            'idkategori'   => 3,
            'jenis_barang' => 'jasa',
            'deleted'      => 0,
            'input'        => session('ID_AKUN'),
        ]);

        session()->setFlashdata('sukses', 'Jasa berhasil disimpan.');
        return redirect()->to(base_url('tarif_jasa'));
    }

    public function ubah()
    {
        $id    = (int) $this->request->getPost('idbarang');
        $nama  = trim((string) $this->request->getPost('nama_barang'));
        $harga = (int) str_replace(['.', ','], '', (string) $this->request->getPost('harga'));

        if ($id <= 0 || $nama === '' || $harga <= 0) {
            session()->setFlashdata('gagal', 'Nama dan harga jasa wajib diisi.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $this->BarangModel->update($id, [
            'nama_barang' => $nama,
            'harga'       => $harga,
        ]);

        session()->setFlashdata('sukses', 'Jasa berhasil diubah.');
        return redirect()->to(base_url('tarif_jasa'));
    }
}
