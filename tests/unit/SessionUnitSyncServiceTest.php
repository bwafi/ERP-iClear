<?php

namespace Tests\unit;

use App\Services\Auth\SessionUnitSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class SessionUnitSyncServiceTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    private $koneksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->koneksi = Database::connect('tests', false);
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=0');
        $this->koneksi->query('DROP TABLE IF EXISTS db_akun');
        $this->koneksi->query('DROP TABLE IF EXISTS db_unit');
        $this->koneksi->query('CREATE TABLE db_akun (ID_AKUN INT PRIMARY KEY, ID_UNIT VARCHAR(50) NULL)');
        $this->koneksi->query('CREATE TABLE db_unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(150) NULL)');
        $this->koneksi->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu'), (2, 'Unit Dua'), (5, 'Unit Lima')");
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=1');

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=0');
        $this->koneksi->query('DROP TABLE IF EXISTS db_akun');
        $this->koneksi->query('DROP TABLE IF EXISTS db_unit');
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=1');
        $_SESSION = [];

        parent::tearDown();
    }

    private function hapusTabelAkun(): void
    {
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=0');
        $this->koneksi->query('DROP TABLE IF EXISTS db_akun');
        $this->koneksi->query('SET FOREIGN_KEY_CHECKS=1');
    }

    private function sync(string $method = 'GET', bool $isAjax = false): ?array
    {
        return (new SessionUnitSyncService($this->koneksi))->sync($method, $isAjax);
    }

    public function testGetMenyinkronkanUnitDanMerisetTiket(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '5']);
        $_SESSION = [
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'NAMA_UNIT'  => 'Unit Satu',
            'idservice'  => 99,
        ];

        $hasil = $this->sync();

        $this->assertSame([
            'old'          => 1,
            'new'          => 5,
            'old_name'     => 'Unit Satu',
            'new_name'     => 'Unit Lima',
            'ticket_reset' => true,
        ], $hasil);
        $this->assertSame(5, (int) session('ID_UNIT'));
        $this->assertSame('Unit Lima', session('NAMA_UNIT'));
        $this->assertNull(session('idservice'));
    }

    public function testPostTidakMenyinkronkanUnit(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '5']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
            'idservice' => 99,
        ];

        $this->assertNull($this->sync('POST'));
        $this->assertSame(1, (int) session('ID_UNIT'));
        $this->assertSame('Unit Satu', session('NAMA_UNIT'));
        $this->assertSame(99, (int) session('idservice'));
    }

    public function testAjaxGetTidakMenyinkronkanUnit(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '5']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $this->assertNull($this->sync('GET', true));
        $this->assertSame(1, (int) session('ID_UNIT'));
    }

    public function testUnitSamaTidakMengubahSesiMaupunTiket(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '1']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
            'idservice' => 99,
        ];

        $this->assertNull($this->sync());
        $this->assertSame(1, (int) session('ID_UNIT'));
        $this->assertSame(99, (int) session('idservice'));
    }

    public function testAkunTidakDitemukanTidakMengubahSesi(): void
    {
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $this->assertNull($this->sync());
        $this->assertSame(1, (int) session('ID_UNIT'));
    }

    public function testIdAkunKosongTidakMenyentuhDatabase(): void
    {
        $this->hapusTabelAkun();
        $_SESSION = ['ID_UNIT' => 1, 'NAMA_UNIT' => 'Unit Satu'];

        $this->assertNull($this->sync());
        $this->assertSame(1, (int) session('ID_UNIT'));
    }

    public function testNamaUnitFallbackBilaBarisUnitTidakAda(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '9']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $hasil = $this->sync();

        $this->assertSame(9, $hasil['new']);
        $this->assertSame('Unit 9', $hasil['new_name']);
        $this->assertSame('Unit 9', session('NAMA_UNIT'));
    }

    public function testUnitNolTetapDisinkronkan(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '0']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $hasil = $this->sync();

        $this->assertSame(0, $hasil['new']);
        $this->assertSame('Unit 0', $hasil['new_name']);
        $this->assertSame(0, (int) session('ID_UNIT'));
    }

    public function testNilaiUnitBerupaJsonArrayMengambilElemenPertama(): void
    {
        $this->koneksi->table('akun')->insert(['ID_AKUN' => 43, 'ID_UNIT' => '[2,5]']);
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $hasil = $this->sync();

        $this->assertSame(2, $hasil['new']);
        $this->assertSame('Unit Dua', $hasil['new_name']);
    }

    public function testTabelAkunTidakAdaTidakMelemparException(): void
    {
        $this->hapusTabelAkun();
        $_SESSION = [
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit Satu',
        ];

        $this->assertNull($this->sync());
        $this->assertSame(1, (int) session('ID_UNIT'));
    }
}
