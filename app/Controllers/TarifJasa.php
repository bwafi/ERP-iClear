<?php

namespace App\Controllers;

use App\Models\ModelBarang;
use App\Models\ModelAuth;
use App\Models\ModelHargaJualUnit;

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
    protected $HargaJualUnitModel;

    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->AuthModel   = new ModelAuth();
        $this->HargaJualUnitModel = new ModelHargaJualUnit();
    }

    public function index()
    {
        $data = [
            'akun' => $this->AuthModel->getById(session('ID_AKUN')),
            'can_manage_harga_jual' => Kartu_Stok::bolehKelolaProdukUnit((int) session('ID_JABATAN')),
            'jasa' => $this->BarangModel
                ->select('barang.*, COALESCE(NULLIF(barang.harga_beli, 0), NULLIF(hpp_barang.hpp, 0), NULLIF(fee_unit.hpp, 0), 0) AS fee_teknisi,
                    COALESCE(hju.harga_jual, barang.harga) AS harga_jual_unit')
                ->join('hpp_barang', 'hpp_barang.idbarang = barang.idbarang', 'left')
                ->join('(SELECT barang_idbarang, MAX(hpp) AS hpp FROM hpp_unit GROUP BY barang_idbarang) fee_unit',
                    'fee_unit.barang_idbarang = barang.idbarang', 'left')
                ->join('harga_jual_unit hju',
                    'hju.barang_idbarang = barang.idbarang AND hju.unit_idunit = ' . (int) session('ID_UNIT'), 'left')
                ->where('barang.jenis_barang', 'jasa')
                ->where('barang.deleted', 0)
                ->orderBy('barang.nama_barang', 'ASC')
                ->findAll(),
            'body' => 'jasa/tarif',
        ];

        return view('template', $data);
    }

    public function simpan()
    {
        $nama  = trim((string) $this->request->getPost('nama_barang'));
        $kode  = trim((string) $this->request->getPost('kode_barang'));
        $harga = $this->angka($this->request->getPost('harga'));
        $fee   = $this->angka($this->request->getPost('harga_beli'));

        if ($nama === '' || $kode === '' || $harga <= 0) {
            session()->setFlashdata('gagal', 'Kode, nama, dan harga jasa wajib diisi.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $feePersen = $this->persen($this->request->getPost('fee_persen'));
        if ($feePersen < 0 || $feePersen > 100) {
            session()->setFlashdata('gagal', 'Persen HPP harus antara 0 dan 100.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        if ($feePersen > 0) {
            $fee = (int) round($harga * $feePersen / 100);
        }

        if ($fee < 0 || $fee > $harga) {
            session()->setFlashdata('gagal', 'HPP tidak boleh negatif atau melebihi harga jasa.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        if ($this->BarangModel->getBykode($kode)) {
            session()->setFlashdata('gagal', 'Kode jasa sudah dipakai barang lain.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $idbarang = (int) $this->BarangModel->insert([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'harga'        => $harga,
            'harga_beli'   => $fee,
            'idkategori'   => 3,
            'jenis_barang' => 'jasa',
            'deleted'      => 0,
            'input'        => session('ID_AKUN'),
        ]);

        if ($idbarang > 0) {
            $this->sinkronFeeTeknisi($idbarang, $fee);
        }

        session()->setFlashdata('sukses', 'Jasa berhasil disimpan.');
        return redirect()->to(base_url('tarif_jasa'));
    }

    public function ubah()
    {
        $id    = (int) $this->request->getPost('idbarang');
        $nama  = trim((string) $this->request->getPost('nama_barang'));
        $harga = $this->angka($this->request->getPost('harga'));
        $fee   = $this->angka($this->request->getPost('harga_beli'));

        if ($id <= 0 || $nama === '' || $harga <= 0) {
            session()->setFlashdata('gagal', 'Nama dan harga jasa wajib diisi.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $feePersen = $this->persen($this->request->getPost('fee_persen'));
        if ($feePersen < 0 || $feePersen > 100) {
            session()->setFlashdata('gagal', 'Persen HPP harus antara 0 dan 100.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        if ($feePersen > 0) {
            $fee = (int) round($harga * $feePersen / 100);
        }

        if ($fee < 0 || $fee > $harga) {
            session()->setFlashdata('gagal', 'HPP tidak boleh negatif atau melebihi harga jasa.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        if ($this->BarangModel->where(['idbarang' => $id, 'jenis_barang' => 'jasa'])->first() === null) {
            session()->setFlashdata('gagal', 'Jasa tidak ditemukan.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        // Harga unit opsional: kosong = ikut master, angka = override unit akun.
        $bolehHargaUnit = Kartu_Stok::bolehKelolaProdukUnit((int) session('ID_JABATAN'));
        $hargaUnitPost  = trim((string) $this->request->getPost('harga_unit'));
        $hargaUnit      = $this->angka($hargaUnitPost);
        if ($bolehHargaUnit && $hargaUnitPost !== '' && ($hargaUnit <= 0 || $hargaUnit > 9999999999999)) {
            session()->setFlashdata('gagal', 'Harga jual unit tidak valid.');
            return redirect()->to(base_url('tarif_jasa'));
        }

        $this->BarangModel->update($id, [
            'nama_barang' => $nama,
            'harga'       => $harga,
            'harga_beli'  => $fee,
        ]);

        $this->sinkronFeeTeknisi($id, $fee);

        if ($bolehHargaUnit) {
            $idunit = (int) session('ID_UNIT');
            $ok = ($hargaUnitPost === '' || $hargaUnit === $harga)
                ? $this->HargaJualUnitModel->clear($id, $idunit)
                : $this->HargaJualUnitModel->setHarga($id, $idunit, (float) $hargaUnit, (int) session('ID_AKUN'));

            if (!$ok) {
                session()->setFlashdata('gagal', 'Harga jual unit gagal disimpan.');
                return redirect()->to(base_url('tarif_jasa'));
            }
        }

        session()->setFlashdata('sukses', 'Jasa berhasil diubah.');
        return redirect()->to(base_url('tarif_jasa'));
    }

    private function angka($nilai): int
    {
        return (int) str_replace(['.', ','], '', (string) $nilai);
    }

    private function persen($nilai): float
    {
        return (float) str_replace(',', '.', (string) $nilai);
    }

    /**
     * HPP jasa adalah bagian teknisi. `barang.harga_beli` menjadi sumber,
     * dicerminkan ke hpp_barang/hpp_unit supaya resolver transaksi memakai
     * angka yang sama dan fee tidak ikut dihitung sebagai omset.
     */
    private function sinkronFeeTeknisi(int $idbarang, int $fee): void
    {
        $barang = $this->BarangModel->find($idbarang);
        if ($barang === null) {
            return;
        }

        $db = \Config\Database::connect();

        $db->table('hpp_barang')->upsert([
            'idbarang'    => $idbarang,
            'kode_barang' => $barang->kode_barang,
            'nama_barang' => $barang->nama_barang,
            'harga'       => (int) $barang->harga,
            'harga_beli'  => $fee,
            'idkategori'  => 3,
            'hpp'         => $fee,
        ]);

        $db->table('hpp_unit')
            ->where('barang_idbarang', $idbarang)
            ->update([
                'hpp'        => $fee,
                'sumber'     => 'manual',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
