<?php

namespace Tests;

use App\Controllers\PenilaianKPI;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman "KPI Saya": guard khusus cabang, normalisasi periode, dan view
 * yang hanya memuat skor (tanpa istilah/angka gaji).
 *
 * @internal
 */
final class KpiSayaPageTest extends CIUnitTestCase
{
    public function testGuardHanyaUntukUnitCabang(): void
    {
        $this->assertTrue(PenilaianKPI::kpiSayaUnitAllowed(1));
        $this->assertTrue(PenilaianKPI::kpiSayaUnitAllowed('5'));
        $this->assertFalse(PenilaianKPI::kpiSayaUnitAllowed(50), 'Head Office tidak boleh');
        $this->assertFalse(PenilaianKPI::kpiSayaUnitAllowed(0), 'Tanpa unit tidak boleh');
        $this->assertFalse(PenilaianKPI::kpiSayaUnitAllowed(null), 'Unit kosong tidak boleh');
    }

    public function testPeriodeDinormalisasi(): void
    {
        $this->assertSame(['09', '2026'], PenilaianKPI::kpiSayaPeriod('9', '2026'));
        $this->assertSame(['10', '2026'], PenilaianKPI::kpiSayaPeriod('10', '2026'));

        [$bulan, $tahun] = PenilaianKPI::kpiSayaPeriod('13', '1900');
        $this->assertSame(date('m'), $bulan);
        $this->assertSame(date('Y'), $tahun);
    }

    public function testMenuMigrationIdempotentDanHanyaUntukJabatanCabang(): void
    {
        $db = \Config\Database::connect();
        $prefixAsli = (string) $db->DBPrefix;
        $db->setPrefix('');

        try {
            $db->query('DROP TABLE IF EXISTS menu');
            $db->query('DROP TABLE IF EXISTS akun');
            $db->query('DROP TABLE IF EXISTS jabatan');
            $db->query('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL)');
            $db->query('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_JABATAN INT NULL, ID_UNIT INT NULL,
                STATUS_PEGAWAI INT NULL)');
            $db->query('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, ROLES_JABATAN TEXT NULL)');

            $db->table('jabatan')->insertBatch([
                ['ID_JABATAN' => 41, 'ROLES_JABATAN' => '[]'],
                ['ID_JABATAN' => 0, 'ROLES_JABATAN' => '[]'],
            ]);
            $db->table('akun')->insertBatch([
                ['ID_AKUN' => 1, 'ID_JABATAN' => 41, 'ID_UNIT' => 1, 'STATUS_PEGAWAI' => 1],
                ['ID_AKUN' => 2, 'ID_JABATAN' => 0, 'ID_UNIT' => 50, 'STATUS_PEGAWAI' => 1],
            ]);

            $path = APPPATH . 'Database/Migrations/2026-11-01-000100_AddKpiSayaMenu.php';
            require_once $path;
            $migration = new \App\Database\Migrations\AddKpiSayaMenu();
            $migration->up();
            $migration->up();

            $this->assertSame(1, $db->table('menu')->where('idmenu', 10105)->countAllResults());

            $rolesCabang = json_decode($db->table('jabatan')->where('ID_JABATAN', 41)->get()->getRow()->ROLES_JABATAN, true);
            $rolesHo = json_decode($db->table('jabatan')->where('ID_JABATAN', 0)->get()->getRow()->ROLES_JABATAN, true);
            $this->assertContains(10105, $rolesCabang);
            $this->assertNotContains(10105, $rolesHo, 'Jabatan HO tidak boleh dapat menu');

            $migration->down();
            $this->assertSame(0, $db->table('menu')->where('idmenu', 10105)->countAllResults());
            $rolesCabang = json_decode($db->table('jabatan')->where('ID_JABATAN', 41)->get()->getRow()->ROLES_JABATAN, true);
            $this->assertNotContains(10105, $rolesCabang);
        } finally {
            $db->setPrefix($prefixAsli);
        }
    }

    public function testViewHanyaMenampilkanSkorTanpaGaji(): void
    {
        $html = (string) view('penilaian/kpi_saya', [
            'nama'         => 'Budi Santoso',
            'skor_total'   => 82.5,
            'skor_absen'   => 91.5,
            'bulan'        => '10',
            'tahun'        => '2026',
            'detail_absen' => [
                ['nama' => 'Kehadiran', 'bobot' => 80, 'nilai' => 95],
                ['nama' => 'Kebersihan', 'bobot' => 5, 'nilai' => 80],
                ['nama' => 'Seragam', 'bobot' => 10, 'nilai' => 90],
                ['nama' => 'Kepatuhan SOP', 'bobot' => 5, 'nilai' => 85],
            ],
            'detail_kpi'   => [
                [
                    'nama' => 'Omzet Toko', 'bobot' => 40, 'format' => 'currency',
                    'target' => 50000000, 'actual' => 45000000, 'shortfall' => 5000000,
                    'unit_count' => null, 'reached' => null, 'ho' => false,
                    'cabang' => null, 'nilai' => 90,
                ],
            ],
        ]);

        $this->assertStringContainsString('KPI Saya', $html);
        $this->assertStringContainsString('82,50', $html);
        $this->assertStringContainsString('Omzet Toko', $html);
        $this->assertStringContainsString('Rp 50.000.000', $html);

        // Absensi tampil sebagai section terpisah dengan 4 komponennya.
        $this->assertStringContainsString('Penilaian Absensi', $html);
        $this->assertStringContainsString('91,50', $html);
        foreach (['Kehadiran', 'Kebersihan', 'Seragam', 'Kepatuhan SOP'] as $komponen) {
            $this->assertStringContainsString($komponen, $html);
        }

        foreach (['Gaji', 'THP', 'Tunjangan', 'Insentif', 'Take Home'] as $kata) {
            $this->assertStringNotContainsString($kata, $html, 'View tidak boleh memuat ' . $kata);
        }
    }
}
