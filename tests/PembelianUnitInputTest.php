<?php

namespace Tests;

use App\Controllers\Pembelian;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan unit pada form /pembelian.
 *
 * Finance (0), Admin root (1), dan Manager (34) boleh memilih unit. Tim toko
 * selalu memakai unit akunnya — nilai unit dari request tidak boleh dipercaya,
 * karena unit menentukan stok, HPP, harga jual, dan nomor nota pembelian.
 */
final class PembelianUnitInputTest extends CIUnitTestCase
{
    public function testHanyaRoleLintasUnitYangBolehMemilih(): void
    {
        foreach ([0, 1, 34] as $role) {
            $this->assertTrue(
                Pembelian::bolehPilihUnitUntukRole($role),
                'role ' . $role . ' seharusnya boleh memilih unit'
            );
        }

        // Tim toko & role lain: kasir, frontliner, SPV, kepala toko, teknisi.
        foreach ([2, 35, 37, 40, 41, 47, 50] as $role) {
            $this->assertFalse(
                Pembelian::bolehPilihUnitUntukRole($role),
                'role ' . $role . ' tidak boleh memilih unit'
            );
        }
    }

    public function testTimTokoSelaluMemakaiUnitAkunWalauRequestUnitLain(): void
    {
        // Kasir unit 2 mengirim unit 5 lewat POST: harus diabaikan.
        $this->assertSame(2, Pembelian::resolveUnitInput(35, 2, '5', [1, 2, 3, 4, 5]));
        $this->assertSame(2, Pembelian::resolveUnitInput(41, 2, 5, [1, 2, 3, 4, 5]));
        $this->assertSame(2, Pembelian::resolveUnitInput(40, 2, '1', [1, 2, 3, 4, 5]));
    }

    public function testRoleLintasUnitBolehMemilihUnitYangValid(): void
    {
        $this->assertSame(3, Pembelian::resolveUnitInput(0, 50, '3', [1, 2, 3, 50]));
        $this->assertSame(4, Pembelian::resolveUnitInput(1, 1, '4', [1, 2, 3, 4, 5]));
        $this->assertSame(5, Pembelian::resolveUnitInput(34, 1, 5, [1, 2, 3, 4, 5]));
    }

    public function testUnitTidakValidAtauKosongJatuhKeUnitAkun(): void
    {
        $this->assertSame(2, Pembelian::resolveUnitInput(1, 2, '99', [1, 2, 3]));
        $this->assertSame(2, Pembelian::resolveUnitInput(1, 2, '', [1, 2, 3]));
        $this->assertSame(2, Pembelian::resolveUnitInput(1, 2, null, [1, 2, 3]));
        $this->assertSame(2, Pembelian::resolveUnitInput(1, 2, '0', [1, 2, 3]));
        $this->assertSame(2, Pembelian::resolveUnitInput(1, 2, '3', []));
    }

    public function testRoleLintasUnitSelaluMendapatUnitOperasional(): void
    {
        // Hanya unit toko yang operasional; Head Office (50) tidak ikut.
        $operasional = [1, 2, 3, 4, 5];

        // Akun Finance/Manager di Head Office: default ke unit operasional
        // pertama, bukan Head Office yang bukan unit operasional.
        $this->assertSame(1, Pembelian::resolveUnitPembelian(0, 50, null, $operasional));
        $this->assertSame(1, Pembelian::resolveUnitPembelian(34, 50, '', $operasional));

        // Unit operasional yang dipilih tetap dipakai; Head Office dari request
        // tidak boleh lolos.
        $this->assertSame(3, Pembelian::resolveUnitPembelian(0, 50, '3', $operasional));
        $this->assertSame(1, Pembelian::resolveUnitPembelian(0, 50, '50', $operasional));

        // Unit akun yang sudah operasional tetap jadi default.
        $this->assertSame(2, Pembelian::resolveUnitPembelian(0, 2, null, $operasional));

        // Tim toko tidak berubah: selalu unit akunnya.
        $this->assertSame(50, Pembelian::resolveUnitPembelian(35, 50, '3', $operasional));
        $this->assertSame(2, Pembelian::resolveUnitPembelian(41, 2, '3', $operasional));
    }
}
