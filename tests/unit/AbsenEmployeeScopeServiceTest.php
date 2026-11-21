<?php

namespace Tests\unit;

use App\Services\Kpi\AbsenEmployeeScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Scope karyawan halaman /penilaian/absen untuk SPV (40): seluruh pegawai
 * aktif di unit binaan (spv_units, fallback unit sesi) plus dirinya sendiri.
 *
 * @internal
 */
final class AbsenEmployeeScopeServiceTest extends CIUnitTestCase
{
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->matikanForeignKey();
        $this->hapus();
        $this->nyalakanForeignKey();

        $this->db->query('CREATE TABLE unit (
            idunit INT PRIMARY KEY,
            NAMA_UNIT VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE jabatan (
            ID_JABATAN INT PRIMARY KEY,
            NAMA_JABATAN VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE akun (
            ID_AKUN INT PRIMARY KEY,
            ID_JABATAN INT NULL,
            ID_UNIT INT NULL,
            NAMA_AKUN VARCHAR(100) NULL,
            STATUS_PEGAWAI INT NULL,
            deleted INT NULL DEFAULT 0)');
        $this->db->query('CREATE TABLE spv_units (
            id INT PRIMARY KEY,
            spv_id INT NULL,
            unit_id INT NULL)');

        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit Satu'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ['idunit' => 3, 'NAMA_UNIT' => 'Unit Tiga'],
        ]);
        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 35, 'NAMA_JABATAN' => 'Admin Cabang'],
            ['ID_JABATAN' => 36, 'NAMA_JABATAN' => 'Teknisi'],
            ['ID_JABATAN' => 40, 'NAMA_JABATAN' => 'SPV'],
            ['ID_JABATAN' => 41, 'NAMA_JABATAN' => 'Kepala Toko'],
        ]);
        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => 400, 'ID_JABATAN' => 40, 'ID_UNIT' => 1, 'NAMA_AKUN' => 'SPV', 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => 401, 'ID_JABATAN' => 41, 'ID_UNIT' => 1, 'NAMA_AKUN' => 'Kepala Unit 1', 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => 402, 'ID_JABATAN' => 35, 'ID_UNIT' => 2, 'NAMA_AKUN' => 'Admin Unit 2', 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => 403, 'ID_JABATAN' => 36, 'ID_UNIT' => 3, 'NAMA_AKUN' => 'Teknisi Unit 3', 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => 404, 'ID_JABATAN' => 36, 'ID_UNIT' => 2, 'NAMA_AKUN' => 'Teknisi Unit 2', 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => 405, 'ID_JABATAN' => 36, 'ID_UNIT' => 2, 'NAMA_AKUN' => 'Teknisi Nonaktif', 'STATUS_PEGAWAI' => 0, 'deleted' => 0],
            ['ID_AKUN' => 406, 'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'NAMA_AKUN' => 'Teknisi Dihapus', 'STATUS_PEGAWAI' => 1, 'deleted' => 1],
        ]);
        $this->db->table('spv_units')->insertBatch([
            ['id' => 1, 'spv_id' => 400, 'unit_id' => 1],
            ['id' => 2, 'spv_id' => 400, 'unit_id' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        $this->matikanForeignKey();
        $this->hapus();
        $this->nyalakanForeignKey();
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function matikanForeignKey(): void
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    private function nyalakanForeignKey(): void
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function hapus(): void
    {
        foreach (['spv_units', 'akun', 'jabatan', 'unit'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
    }

    public function testSpvMelihatTimDiDuaUnitBinaanDanDirinya(): void
    {
        $rows = (new AbsenEmployeeScopeService($this->db))->daftarKaryawanSpv(400, 1);
        $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $rows);
        sort($ids);

        $this->assertSame([400, 401, 402, 404], $ids);
    }

    public function testSpvTanpaMappingMemakaiUnitSesi(): void
    {
        $this->db->table('spv_units')->where('spv_id', 400)->delete();
        $rows = (new AbsenEmployeeScopeService($this->db))->daftarKaryawanSpv(400, 1);
        $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $rows);
        sort($ids);

        $this->assertSame([400, 401], $ids);
    }

    public function testCanViewMenolakUnitDiLuarBinaan(): void
    {
        $this->assertTrue(AbsenEmployeeScopeService::canView(40, 400, 402, 2, [1, 2]));
        $this->assertFalse(AbsenEmployeeScopeService::canView(40, 400, 403, 3, [1, 2]));
        $this->assertTrue(AbsenEmployeeScopeService::canView(40, 400, 400, 3, [1, 2]));
        $this->assertFalse(AbsenEmployeeScopeService::canView(35, 350, 401, 1, [1, 2]));
    }
}
