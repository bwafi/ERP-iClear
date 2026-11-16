<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi picker POS (#200/#203): harga jual efektif berasal dari
 * `harga_jual_unit.harga_jual` yang bertipe decimal(15,2), sehingga MySQL
 * mengembalikannya sebagai string "55000.00". Nilai itu harus dikirim ke
 * markup sebagai rupiah bulat, karena `formatToRupiah()` di halaman membuang
 * semua karakter non-digit — "55000.00" akan menjadi 5.500.000.
 */
final class PenjualanPickerMoneyTest extends CIUnitTestCase
{
    /**
     * @param mixed $hargaProduk Nilai `harga_jual` produk (decimal dari DB).
     * @param mixed $hargaJasa   Nilai `harga_jual` jasa (decimal dari DB).
     */
    private function render($hargaProduk = '55000.00', $hargaJasa = '125000.00'): string
    {
        $produk = (object) [
            'idbarang'      => 1,
            'kode_barang'   => 'P01',
            'nama_barang'   => 'LCD iPhone 11',
            'harga_jual'    => $hargaProduk,
            'nama_kategori' => 'Sparepart',
            'input'         => 'IMEI-1',
            'status_barang' => 'Baru',
            'stok_akhir'    => 5,
            'kondisi'       => 0,
            'imei'          => 'IMEI-1',
            'internal'      => '128GB',
            'warna'         => 'Hitam',
            'jenis_hp'      => 'iPhone',
        ];

        $jasa = (object) [
            'idbarang'    => 900,
            'kode_barang' => 'J01',
            'nama_barang' => 'Ganti LCD',
            'harga_jual'  => $hargaJasa,
        ];

        $bundle = (object) [
            'idbundle'    => 7,
            'nama_bundle' => 'Paket Case + Tempered Glass',
            'harga_jual'  => '75000',
            'status_stok' => 'Tersedia',
            'detail'      => [],
        ];

        return view('transaksi/penjualan', [
            'akun'       => (object) ['ID_AKUN' => 43, 'NAMA_AKUN' => 'Kasir Satu'],
            'produk'     => [$produk],
            'jasa'       => [$jasa],
            'bundle'     => [$bundle],
            'frontliner' => [(object) ['ID_AKUN' => 43, 'NAMA_AKUN' => 'Kasir Satu']],
            'pelanggan'  => [(object) ['id_pelanggan' => 1, 'nama' => 'Pelanggan', 'no_hp' => '0812']],
            'provinsi'   => [(object) ['name' => 'Jawa Timur']],
            'bank'       => [(object) ['idbank' => 1, 'nama_bank' => 'BCA', 'atas_nama' => 'Toko', 'norek' => '123']],
        ]);
    }

    public function testHargaProdukDesimalDikirimSebagaiRupiahBulat(): void
    {
        $html = $this->render('55000.00');

        $this->assertStringContainsString('data-harga="55000"', $html);
        $this->assertStringNotContainsString('data-harga="55000.00"', $html);
    }

    public function testHargaJasaDesimalDikirimSebagaiRupiahBulat(): void
    {
        $html = $this->render('55000.00', '125000.00');

        $this->assertStringContainsString('data-harga="125000"', $html);
        $this->assertStringNotContainsString('data-harga="125000.00"', $html);
    }

    public function testHargaBerseparuhDibulatkanSesuaiTampilanHarga(): void
    {
        // Kolom harga di tabel yang sama dirender dengan number_format(..., 0),
        // jadi nilai yang masuk keranjang harus mengikuti pembulatan yang sama.
        $html = $this->render('55000.50', '55000.50');

        $this->assertStringContainsString('data-harga="55001"', $html);
        $this->assertStringContainsString('Rp 55.001', $html);
    }

    public function testHargaBulatTetapSamaSetelahNormalisasi(): void
    {
        $html = $this->render(175000, 125000);

        $this->assertStringContainsString('data-harga="175000"', $html);
        $this->assertStringContainsString('data-harga="125000"', $html);
    }

    public function testFormatToRupiahTetapMembuangTitikDesimalDariInputLain(): void
    {
        // Kontrak JS tidak diubah: nilai yang sudah bulat tetap diformat benar,
        // sedangkan input ber-titik ribuan dari kasir tetap dibersihkan.
        $html = $this->render();

        $this->assertStringContainsString("angka.replace(/[^\\d]/g, '')", $html);
    }
}
