<?php

namespace Tests;

use App\Libraries\ManualBook;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Parser `menu.manualbook` untuk modal Manual Book (issue #213).
 *
 * Kolom ini awalnya hanya berisi satu nama file. Sekarang boleh berisi
 * beberapa nama file dengan pemisah `|`, dan nilai lama tidak boleh hilang.
 */
class ManualBookTest extends CIUnitTestCase
{
    public function testNilaiKosongMenghasilkanDaftarKosong(): void
    {
        $this->assertSame([], ManualBook::daftarBerkas(null));
        $this->assertSame([], ManualBook::daftarBerkas(''));
        $this->assertSame([], ManualBook::daftarBerkas('   '));
        $this->assertSame([], ManualBook::daftarBerkas('||'));
    }

    public function testSatuBerkasTetapSatuBerkas(): void
    {
        $this->assertSame(['Stok Produk.pdf'], ManualBook::daftarBerkas('Stok Produk.pdf'));
        $this->assertSame(['Stok Produk.pdf'], ManualBook::daftarBerkas('  Stok Produk.pdf  '));
    }

    public function testBeberapaBerkasDipisahPipa(): void
    {
        $this->assertSame(
            ['Panduan Pembelian.pdf', 'Pembelian Barang.pdf'],
            ManualBook::daftarBerkas('Panduan Pembelian.pdf|Pembelian Barang.pdf')
        );
    }

    public function testPemisahBarisBaruIkutDidukung(): void
    {
        $this->assertSame(
            ['Panduan HPP.pdf', 'Stok Produk.pdf'],
            ManualBook::daftarBerkas("Panduan HPP.pdf\r\nStok Produk.pdf")
        );
    }

    public function testDuplikatDanPemisahGandaDibuang(): void
    {
        $this->assertSame(
            ['A.pdf', 'B.pdf'],
            ManualBook::daftarBerkas('A.pdf||B.pdf| A.pdf ')
        );
    }

    public function testPathDiambilNamaFileSaja(): void
    {
        $this->assertSame(
            ['Panduan.pdf'],
            ManualBook::daftarBerkas('../../etc/Panduan.pdf')
        );
    }

    public function testJudulTanpaEkstensi(): void
    {
        $this->assertSame('Panduan HPP dan Harga Jual per Unit', ManualBook::judul('Panduan HPP dan Harga Jual per Unit.pdf'));
    }
}
