<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kontrak markup halaman /penjualan.
 *
 * Halaman boleh berubah tampilan, tetapi field, id, modal, dan hook JS yang
 * dipakai controller/JS lama harus tetap ada. Test ini juga menjaga agar bug
 * `bayar_bank` (elemen yang tidak pernah ada) tidak kembali.
 */
final class PenjualanPageTest extends CIUnitTestCase
{
    private function render(): string
    {
        $produk = (object) [
            'idbarang' => 1,
            'kode_barang' => 'P01',
            'nama_barang' => 'LCD iPhone 11',
            'harga_jual' => '55000.00',
            'nama_kategori' => 'Sparepart',
            'input' => 'IMEI-1',
            'status_barang' => 'Baru',
            'stok_akhir' => 5,
            'kondisi' => 0,
            'imei' => 'IMEI-1',
            'internal' => '128GB',
            'warna' => 'Hitam',
            'jenis_hp' => 'iPhone',
            'hpp_unit' => 400000,
            'nama_suplier' => 'PT Sparepart Jaya',
        ];

        $jasa = (object) [
            'idbarang' => 900,
            'kode_barang' => 'J01',
            'nama_barang' => 'Ganti LCD',
            'harga_jual' => '125000.00',
        ];

        $bundle = (object) [
            'idbundle' => 7,
            'nama_bundle' => 'Paket Case + Tempered Glass',
            'harga_jual' => '75000',
            'status_stok' => 'Tersedia',
            'detail' => [
                (object) ['nama_barang' => 'Case', 'jumlah' => 1, 'stok_akhir' => 3],
            ],
        ];

        return view('transaksi/penjualan', [
            'akun' => (object) ['ID_AKUN' => 43, 'NAMA_AKUN' => 'Kasir Satu'],
            'produk' => [$produk],
            'jasa' => [$jasa],
            'bundle' => [$bundle],
            'frontliner' => [(object) ['ID_AKUN' => 43, 'NAMA_AKUN' => 'Kasir Satu']],
            'pelanggan' => [
                (object) [
                    'id_pelanggan' => 1,
                    'nama' => 'Pelanggan',
                    'no_hp' => '0812',
                    'provinsi' => 'Jawa Timur',
                    'kabupaten' => 'Sidoarjo',
                    'kecamatan' => 'Waru',
                ],
            ],
            'provinsi' => [(object) ['name' => 'Jawa Timur']],
            'bank' => [
                (object) ['idbank' => 1, 'nama_bank' => 'BCA', 'atas_nama' => 'Toko', 'norek' => '123'],
            ],
        ]);
    }

    public function testShellSatuLembarDanTigaBagian(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="penjualan-page"', $html);
        $this->assertStringContainsString('pj-head', $html);
        $this->assertStringContainsString('id="form_penjualan"', $html);
        $this->assertSame(3, substr_count($html, 'class="pj-section"'));
        $this->assertStringContainsString('class="pj-dock"', $html);
    }

    public function testMetodePembayaranDanZonaSplit(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('name="metode_bayar" value="tunai"', $html);
        $this->assertStringContainsString('name="metode_bayar" value="transfer"', $html);
        $this->assertStringContainsString('name="metode_bayar" value="tunai_transfer"', $html);
        $this->assertStringContainsString('id="bayar"', $html);
        $this->assertStringContainsString('name="bayar"', $html);
        $this->assertStringContainsString('id="bayar" name="bayar"', $html);
        $this->assertStringContainsString('id="bank-payment-container"', $html);
        $this->assertStringContainsString('id="tambah-bank"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    public function testFieldKontrakDanDockTersimpan(): void
    {
        $html = $this->render();

        foreach ([
            'id="tanggal_masuk"',
            'name="tanggal_masuk"',
            'id="sales_by"',
            'name="sales_by"',
            'id="total-diskon"',
            'name="total-diskon"',
            'id="total-ppn"',
            'name="total-ppn"',
            'id="total-harga"',
            'name="total-harga"',
            'id="hutang"',
            'name="hutang"',
            'id="kembalian"',
            'name="kembalian"',
            'name="action" value="simpan"',
            'name="action" value="simpan_thermal"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function testTidakAdaReferensiBayarBankYangMati(): void
    {
        $this->assertStringNotContainsString('bayar_bank', $this->render());
    }

    public function testPickerMempertahankanKontrakHarga(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="pj-picker-search"', $html);
        $this->assertStringContainsString('produk-checkbox', $html);
        $this->assertStringContainsString('data-harga="55000"', $html);
        $this->assertStringContainsString('data-harga="125000"', $html);
        $this->assertStringContainsString('id="confirm-produk-btn"', $html);
        $this->assertStringContainsString('id="produk-modal-table"', $html);
        $this->assertStringContainsString('id="jasa-modal-table"', $html);
        $this->assertStringContainsString('id="bundle-modal-table"', $html);
        $this->assertStringContainsString('id="detailBundleModal"', $html);
        $this->assertStringContainsString('id="bundle-detail-body"', $html);
        $this->assertStringContainsString('btn-check-bundle', $html);
        $this->assertStringContainsString('id="selected-produk-table"', $html);
    }

    public function testPickerMenampilkanHppDanSuplierTanpaPilihSemua(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('HPP Rp 400.000', $html);
        $this->assertStringContainsString('PT Sparepart Jaya', $html);
        $this->assertStringContainsString('bi-truck', $html);
        $this->assertStringContainsString('bi-tag', $html);
        $this->assertStringContainsString('service-sp-item__box', $html);
        $this->assertStringContainsString('service-sp-tick', $html);
        $this->assertStringContainsString('service-sp-code', $html);
        $this->assertStringContainsString('service-sp-search', $html);
        $this->assertStringNotContainsString('select-all-produk', $html);
        $this->assertStringNotContainsString('select-all-bundle', $html);
    }

    public function testModalPelangganDanTambahPelanggan(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="pelangganModal"', $html);
        $this->assertStringContainsString('id="pelanggan-select"', $html);
        $this->assertStringContainsString('name="selectedidpelanggan"', $html);
        $this->assertStringContainsString('id="btnPilihPelanggan"', $html);
        $this->assertStringContainsString('id="btnTambahPelanggan"', $html);
        $this->assertStringContainsString('id="modalTambahPelanggan"', $html);
        $this->assertStringContainsString('id="formTambahPelanggan"', $html);
        $this->assertStringContainsString('id="modal_provinsi"', $html);
        $this->assertStringContainsString('id="modal_kabupaten"', $html);
        $this->assertStringContainsString('id="modal_kecamatan"', $html);
    }

    public function testSelectDomisiliFormUtamaDihapus(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('id="form_provinsi"', $html);
        $this->assertStringNotContainsString('id="form_kabupaten"', $html);
        $this->assertStringNotContainsString('id="form_kecamatan"', $html);
    }

    public function testModalPelangganDiLuarFormDanIdLewatHiddenInput(): void
    {
        $html = $this->render();

        $formTutup = strpos($html, '</form>');
        $modalPelanggan = strpos($html, 'id="pelangganModal"');

        $this->assertNotFalse($formTutup);
        $this->assertNotFalse($modalPelanggan);
        $this->assertGreaterThan($formTutup, $modalPelanggan, 'Modal pelanggan harus di luar form agar tidak terpotong overflow lembar.');
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" id="selectedidpelanggan" name="selectedidpelanggan"/',
            $html
        );
        $this->assertStringContainsString('id="pj-staff-error"', $html);
    }

    public function testKontrakFormatRupiahDipertahankan(): void
    {
        $html = $this->render();

        $this->assertStringContainsString("angka.replace(/[^\\d]/g, '')", $html);
    }
}
