<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi markup halaman /pembelian setelah redesign: struktur baru harus
 * tetap mempertahankan semua hook yang dipakai JavaScript dan form POST.
 */
final class PembelianPageTest extends CIUnitTestCase
{
    private function render(bool $canManageHargaJual = false): string
    {
        service('session')->set([
            'ID_AKUN' => 43,
            'ID_UNIT' => 1,
            'NAMA'    => 'Kasir Satu',
        ]);

        $produk = (object) [
            'idbarang'       => 1,
            'kode_barang'    => 'P01',
            'nama_barang'    => 'LCD iPhone 11',
            'harga'          => 150000,
            'harga_jual'     => 175000,
            'harga_beli'     => 100000,
            'hpp_unit'       => 120000,
            'nama_kategori'  => 'Sparepart',
            'input'          => 'IMEI-1',
            'imei'           => 'IMEI-1',
            'internal'       => '128GB',
            'warna'          => 'Hitam',
        ];

        return view('transaksi/pembelian', [
            'akun'           => (object) ['NAMA_AKUN' => 'Kasir Satu'],
            'produk'         => [$produk],
            'kategori'       => [(object) ['id' => 3, 'nama_kategori' => 'Sparepart']],
            'suplier'        => [(object) ['id_suplier' => 1, 'nama_suplier' => 'Supplier']],
            'bank'           => [(object) ['idbank' => 1, 'nama_bank' => 'BCA', 'atas_nama' => 'Toko', 'norek' => '123']],
            'pelanggan'      => [(object) ['id_pelanggan' => 1, 'nama' => 'Pelanggan', 'no_hp' => '0812']],
            'nama_handphone' => [],
            'sub_kategori'   => [],
            'can_manage_harga_jual' => $canManageHargaJual,
        ]);
    }

    public function testStrukturRedesignDirender(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('pembelian-page', $html);
        $this->assertStringContainsString('pb-head', $html);
        $this->assertStringContainsString('pb-section', $html);
        $this->assertStringContainsString('pb-dock', $html);
        $this->assertStringContainsString('Ringkasan pembayaran', $html);
        $this->assertStringContainsString('Simpan Pembelian', $html);
    }

    public function testHookFormDanProdukTetapAda(): void
    {
        $html = $this->render();

        foreach ([
            'id="form_pembelian"',
            'id="tipe_pihak"',
            'id="suplier"',
            'id="id_suplier_text"',
            'id="pelanggan"',
            'id="pelanggan-container"',
            'id="pelanggan-section"',
            'id="notaFileInput"',
            'id="previewNota"',
            'id="tanggal_masuk"',
            'id="jatuh_tempo"',
            'id="metode_bayar"',
            'id="frontliner"',
            'id="produk-table-wrap"',
            'id="selected-produk-table"',
            'id="produk-empty"',
            'id="pilih-produk-modal"',
            'id="confirm-produk-btn"',
            'id="pb-produk-list"',
            'id="pb-produk-empty"',
            'id="select-all-ppn"',
            'id="tambah-bank"',
            'id="bank-payment-container"',
            'id="bayar"',
            'id="total-diskon"',
            'id="total-ppn"',
            'id="total-harga"',
            'id="hutang"',
            'id="kembalian"',
            'id="pb-dibayar"',
            'id="input-produk-modal"',
            'id="pelangganModal"',
            'id="modalTambahPelanggan"',
            'id="pb-produk-search"',
            'id="pb-produk-summary"',
            'id="pb-produk-total-hpp"',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook . ' hilang dari halaman');
        }
    }

    public function testNamaFieldPostPembelianTetap(): void
    {
        $html = $this->render();

        foreach ([
            'name="suplier"',
            'name="id_suplier_text"',
            'name="nota_file"',
            'name="tanggal_masuk"',
            'name="jatuh_tempo"',
            'name="metode_bayar"',
            'name="bayar"',
            'name="bank[${index}][id]"',
            'name="bank[${index}][jumlah]"',
            'name="hutang"',
            'name="kembalian"',
            'name="total-diskon"',
            'name="total-ppn"',
            'name="total-harga"',
        ] as $name) {
            $this->assertStringContainsString($name, $html, $name . ' hilang dari halaman');
        }
    }

    public function testTemaDanModalMemakaiScopeBaru(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('pb-modal', $html);
        $this->assertStringContainsString('pb-picker', $html);
        $this->assertStringContainsString('--pb-accent', $html);
        $this->assertStringNotContainsString('shadow-none position-relative overflow-hidden', $html);
    }

    public function testMetodeBayarBeradaDiBagianPembayaran(): void    {
        $html = $this->render();

        $this->assertGreaterThan(
            strpos($html, 'id="pb-bayar-title"'),
            strpos($html, 'id="metode_bayar"'),
            'metode pembayaran tampil setelah judul Pembayaran'
        );
        $this->assertLessThan(
            strpos($html, 'name="bayar"'),
            strpos($html, 'id="metode_bayar"'),
            'metode pembayaran tampil sebelum input bayar tunai'
        );
    }

    public function testHargaModalTambahProdukDiformatRibuan(): void
    {
        $html = $this->render();

        // Modal Tambah Produk Baru: Harga PL & HPP tampil dengan pemisah ribuan
        // titik (bukan "Rp " di dalam value) agar server hanya perlu membuang titik.
        $this->assertStringContainsString('class="form-control currency" id="harga"', $html);
        $this->assertStringContainsString('class="form-control currency" id="harga_beli"', $html);
        $this->assertStringContainsString("#input-produk-modal .currency", $html);
        $this->assertStringNotContainsString("#input_produk_modal", $html);
        $this->assertStringContainsString("Number(numeric).toLocaleString('id-ID')", $html);
    }

    public function testHargaJualDesimalDariDatabaseDikirimBulat(): void
    {
        $html = $this->render();

        // harga_jual_unit bertipe decimal(15,2) -> "50000.00". Kalau nilai itu
        // dikirim mentah, cleanRupiah() membuang titiknya menjadi 5000000.
        $this->assertStringContainsString("harga: parseInt(cb.getAttribute('data-harga')) || 0,", $html);
        $this->assertStringContainsString("harga_beli: parseInt(cb.getAttribute('data-harga_beli')) || 0,", $html);
    }

    public function testTombolSimpanPembelianDikunciSaatSubmit(): void
    {
        $html = $this->render();

        // Klik/Enter ganda tidak boleh mengirim form pembelian dua kali.
        $this->assertStringContainsString('let pembelianTersimpan = false;', $html);
        $this->assertStringContainsString('tombol.disabled = true;', $html);
        $this->assertStringContainsString("tombol.setAttribute('aria-busy', 'true')", $html);
    }

    public function testHargaJualEditHanyaUntukRolePengelola(): void
    {
        $terbuka = $this->render(true);
        $this->assertStringContainsString('const canEditHargaJual = true;', $terbuka);
        $this->assertStringContainsString('harga-jual-input', $terbuka);
        $this->assertStringContainsString('name="produk[${id}][harga]"', $terbuka);
        $this->assertStringContainsString('Harga jual yang diubah berlaku untuk unit ini.', $terbuka);
        $this->assertStringContainsString('data-harga="175000"', $terbuka);
        $this->assertStringNotContainsString('data-harga="150000"', $terbuka);

        $tertutup = $this->render(false);
        $this->assertStringContainsString('const canEditHargaJual = false;', $tertutup);
        $this->assertStringNotContainsString('Harga jual yang diubah berlaku untuk unit ini.', $tertutup);
    }
}
