<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class GroupAbsensiMenuTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->query('CREATE TABLE menu (
            idmenu INT PRIMARY KEY, urutan INT NOT NULL, nama_menu TEXT NULL,
            roles TEXT NULL, url TEXT NULL, show_menu INT NULL, sub INT NULL,
            parent INT NULL, utama INT NULL, categories INT NULL, icon TEXT NULL)');

        $dasar = [
            'roles' => null, 'url' => null, 'show_menu' => 1, 'sub' => 0,
            'parent' => 0, 'utama' => 1, 'categories' => 0, 'icon' => null,
        ];
        $baris = [
            ['idmenu' => 100, 'urutan' => 100, 'nama_menu' => 'KEPEGAWAIAN', 'utama' => 0, 'categories' => 1],
            ['idmenu' => 112, 'urutan' => 101, 'nama_menu' => 'Presensi', 'url' => 'absensi', 'categories' => 1],
            ['idmenu' => 116, 'urutan' => 101, 'nama_menu' => 'Approval Presensi', 'url' => 'approval_presensi', 'categories' => 1],
            ['idmenu' => 115, 'urutan' => 115, 'nama_menu' => 'Riwayat Presensi', 'url' => 'riwayat_presensi', 'parent' => 10015],
            ['idmenu' => 117, 'urutan' => 117, 'nama_menu' => 'Riwayat Presensi(admin)', 'url' => 'semua_riwayat_presensi', 'parent' => 10015],
            ['idmenu' => 118, 'urutan' => 118, 'nama_menu' => 'Jadwal Masuk', 'url' => 'jadwal_masuk', 'categories' => 1],
            ['idmenu' => 10015, 'urutan' => 115, 'nama_menu' => 'Laporan Kepegawaian', 'categories' => 1],
        ];

        foreach ($baris as $row) {
            $this->db->table('menu')->insert(array_merge($dasar, $row));
        }
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-02-000500_GroupAbsensiMenu.php';
        (new \App\Database\Migrations\GroupAbsensiMenu())->up();
    }

    private function baris(int $id): object
    {
        return $this->db->table('menu')->where('idmenu', $id)->get()->getRow();
    }

    public function testAnakPresensiPindahKeGrupAbsensi(): void
    {
        $this->migrasi();

        $grup = $this->baris(10161);
        $this->assertSame('Absensi', $grup->nama_menu);
        $this->assertSame(1, (int) $grup->categories);
        $this->assertSame(0, (int) $grup->parent);
        $this->assertSame(1, (int) $grup->show_menu);

        foreach ([112, 116, 115, 117, 118] as $id) {
            $this->assertSame(10161, (int) $this->baris($id)->parent, 'menu ' . $id);
            $this->assertSame(0, (int) $this->baris($id)->categories, 'menu ' . $id);
        }

        $this->assertSame(99, (int) $this->baris(100)->urutan);
    }

    public function testTidakMenambahMenuMobileFirst(): void
    {
        $this->migrasi();

        foreach (['absen', 'pengajuan_izin'] as $url) {
            $this->assertSame(0, $this->db->table('menu')->where('url', $url)->countAllResults());
        }
    }

    public function testIdempoten(): void
    {
        $this->migrasi();
        $this->migrasi();

        $this->assertSame(1, $this->db->table('menu')->where('idmenu', 10161)->countAllResults());
        $this->assertSame(5, $this->db->table('menu')->where('parent', 10161)->countAllResults());
    }

    public function testDownMengembalikanSusunanAwal(): void
    {
        $this->migrasi();
        (new \App\Database\Migrations\GroupAbsensiMenu())->down();

        $this->assertSame(0, $this->db->table('menu')->where('idmenu', 10161)->countAllResults());
        $this->assertSame(100, (int) $this->baris(100)->urutan);
        $this->assertSame(0, (int) $this->baris(112)->parent);
        $this->assertSame(1, (int) $this->baris(112)->categories);
        $this->assertSame(10015, (int) $this->baris(115)->parent);
        $this->assertSame(117, (int) $this->baris(117)->urutan);
    }
}
