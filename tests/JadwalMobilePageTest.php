<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Halaman jadwal mobile read-only dan navigasi empat tab.
 *
 * @internal
 */
final class JadwalMobilePageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        foreach (['unit', 'presensi_roster'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['unit', 'presensi_roster'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NOT NULL, tanggal DATE NOT NULL, shift VARCHAR(10) NOT NULL,
                unit_idunit INT NULL,
                keterangan TEXT NULL, created_by INT NULL, updated_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal))');

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu'), (2, 'Unit Dua')");
    }

    private function sesi(int $akun = 10, int $unit = 1): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => $akun,
            'ID_UNIT'    => $unit,
            'ID_JABATAN' => 36,
            'NAMA'       => 'Budi Santoso',
        ]);
    }

    private function roster(int $akunId, string $tanggal, string $shift, ?string $ket = null, int $unit = 1): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => $akunId,
            'tanggal'     => $tanggal,
            'shift'       => $shift,
            'unit_idunit' => $unit,
            'keterangan'  => $ket,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function testButuhLogin(): void
    {
        $this->get('jadwal')->assertRedirectTo(base_url('/login'));
    }

    public function testHanyaMenampilkanRosterMilikAkunSendiri(): void
    {
        $this->sesi(10);
        $this->roster(10, date('Y-m') . '-05', 'PAGI', 'Buka kasir');
        $this->roster(20, date('Y-m') . '-05', 'SIANG', 'Rahasia pegawai lain');

        $result = $this->get('jadwal');
        $result->assertStatus(200);
        $result->assertSee('Buka kasir');
        $result->assertDontSee('Rahasia pegawai lain');
    }

    public function testParameterAkunDiabaikan(): void
    {
        $this->sesi(10);
        $this->roster(10, date('Y-m') . '-05', 'PAGI', 'Milik sendiri');
        $this->roster(20, date('Y-m') . '-05', 'SIANG', 'Milik orang lain');

        $result = $this->get('jadwal?akun=20&unit=2');
        $result->assertSee('Milik sendiri');
        $result->assertDontSee('Milik orang lain');
    }

    public function testNavigasiBulanMembacaBulanYangDiminta(): void
    {
        $this->sesi(10);
        $this->roster(10, '2026-01-05', 'PAGI', 'Catatan Januari');
        $this->roster(10, '2026-02-05', 'SIANG', 'Catatan Februari');

        $result = $this->get('jadwal?bulan=2026-01');
        $result->assertSee('Januari 2026');
        $result->assertSee('Catatan Januari');
        $result->assertDontSee('Catatan Februari');
        $result->assertSee('jadwal?bulan=2025-12');
        $result->assertSee('jadwal?bulan=2026-02');
    }

    public function testBulanTidakValidKembaliKeBulanIni(): void
    {
        $this->sesi(10);
        $namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $label = $namaBulan[(int) date('n')] . ' ' . date('Y');

        $this->get('jadwal?bulan=2026-13')->assertSee($label);
        $this->get('jadwal?bulan=bukan-bulan')->assertSee($label);
    }

    public function testReadOnlyTanpaFormAtauKontrolSimpan(): void
    {
        $this->sesi(10);
        $this->roster(10, date('Y-m') . '-05', 'PAGI');

        $body = (string) $this->get('jadwal')->getBody();
        $this->assertSame(1, preg_match('/<main\b.*?<\/main>/s', $body, $main));
        $isi = $main[0];

        $this->assertStringNotContainsString('<form', $isi);
        $this->assertStringNotContainsString('<input', $isi);
        $this->assertStringNotContainsString('<select', $isi);
        $this->assertStringNotContainsString('<textarea', $isi);
        $this->assertStringNotContainsString('<button', $isi);
        $this->assertStringNotContainsString('method="post"', $isi);
        $this->assertStringNotContainsString('csrf', $isi);
    }

    public function testHariIniDitandaiSekali(): void
    {
        $this->sesi(10);
        $this->roster(10, date('Y-m-d'), 'PAGI');

        $body = (string) $this->get('jadwal')->getBody();

        $this->assertSame(1, substr_count($body, 'aria-current="date"'));
    }

    public function testCatatanUnitLainTampilDiDaftarCatatan(): void
    {
        $this->sesi(10);
        $this->roster(10, date('Y-m') . '-07', 'PAGI', null, 2);

        $result = $this->get('jadwal');
        $result->assertSee('Catatan');
        $result->assertSee('Jadwal di Unit Dua');
    }

    public function testNavEmpatTabDanJadwalAktif(): void
    {
        $this->sesi(10);

        $body = (string) $this->get('jadwal')->getBody();

        $this->assertSame(4, substr_count($body, 'class="mob-nav__item'));
        $this->assertSame(1, substr_count($body, 'aria-current="page"'));
        $this->assertStringContainsString('href="' . base_url('jadwal') . '"', $body);
    }
}
