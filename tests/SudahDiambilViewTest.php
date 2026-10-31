<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman Pengambilan Service hanya menampilkan aksi "Kembalikan ke Proses"
 * untuk Admin Root.
 */
final class SudahDiambilViewTest extends CIUnitTestCase
{
    public function testAdminRootMelihatTombolKembalikan(): void
    {
        $html = view('riwayat/sudah_diambil', ['is_admin_root' => true]);

        $this->assertStringContainsString('const IS_ADMIN_ROOT = true;', $html);
        $this->assertStringContainsString('service/kembalikan_ke_proses', $html);
        $this->assertStringContainsString('Kembalikan ke Proses', $html);
        $this->assertStringContainsString('name="alasan"', $html);
    }

    public function testBukanAdminRootTidakMelihatTombol(): void
    {
        $html = view('riwayat/sudah_diambil', ['is_admin_root' => false]);

        $this->assertStringContainsString('const IS_ADMIN_ROOT = false;', $html);
        $this->assertStringContainsString('IS_ADMIN_ROOT ?', $html, 'Tombol tetap dibungkus kondisi runtime.');
    }
}
