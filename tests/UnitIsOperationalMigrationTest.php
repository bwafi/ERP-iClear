<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Database\Migrations\AddIsOperationalToUnit;

/**
 * Migrasi AddIsOperationalToUnit:
 * - idempoten (bisa di-up berulang tanpa error),
 * - menambahkan unit.is_operational dengan default 1 (unit lama tetap
 *   operasional), dan
 * - menandai hanya Head Office (idunit = 50, terverifikasi) sebagai 0.
 *
 * Skema unit di sini dibuat ulang meniru schema sebelum migrasi
 * (tanpa kolom is_operational) supaya `up()` benar-benar menguji ADD COLUMN.
 * Hanya boleh jalan terhadap `erp_finance_test` (DBPrefix = '').
 */
class UnitIsOperationalMigrationTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        if ($this->db->getDatabase() !== 'erp_finance_test') {
            $this->fail('Test hanya boleh jalan pada database erp_finance_test, bukan: '
                . $this->db->getDatabase());
        }

        $this->q('SET FOREIGN_KEY_CHECKS = 0');
        $this->q('DROP TABLE IF EXISTS unit');
        $this->q("CREATE TABLE unit (
                idunit int(11) NOT NULL AUTO_INCREMENT,
                NAMA_UNIT varchar(150) DEFAULT NULL,
                kode_unit varchar(10) DEFAULT NULL,
                NOID_UNIT varchar(100) DEFAULT NULL,
                PRIMARY KEY (idunit)
            ) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=latin1");
        $this->q("INSERT INTO unit (idunit, NAMA_UNIT, kode_unit) VALUES
                (1, 'ICLEAR Probolinggo', 'PRO'),
                (2, 'ICLEAR Jember', 'JBR'),
                (50, 'Head Office', 'HO')");
        $this->q('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        // Tabel lain di erp_finance_test masih punya FK ke unit.idunit, jadi
        // FK check dimatikan dulu supaya DROP bisa jalan.
        $this->q('SET FOREIGN_KEY_CHECKS = 0');
        $this->q('DROP TABLE IF EXISTS unit');
        $this->q('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
    }

    private function q(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            $this->fail('Query gagal: ' . $this->db->error()['message']);
        }
    }

    /**
     * Cek keberadaan kolom lewat information_schema. Tidak memakai
     * Connection::fieldExists() karena cache per-koneksinya bisa basi setelah
     * DDL dijalankan lewat koneksi yang sama dalam satu proses.
     */
    private function kolomAda(string $kolom, string $tabel): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabel, $kolom]
        )->getRow();

        return (int) ($row->n ?? 0) > 0;
    }

    /**
     * File migrasi memakai konvensi nama bertimestamp (tidak PSR-4), jadi
     * kelasnya tidak bisa diautoload. Dimuat manual, sama seperti yang
     * dilakukan MigrationRunner (include_once path).
     */
    private function migrasi(): AddIsOperationalToUnit
    {
        require_once APPPATH . 'Database/Migrations/2026-10-11-000000_AddIsOperationalToUnit.php';

        return new AddIsOperationalToUnit();
    }

    /** @return array<int, int> idunit => is_operational */
    private function isOperasional(): array
    {
        $out = [];

        foreach ($this->db->query('SELECT idunit, is_operational FROM unit ORDER BY idunit')->getResultArray() as $r) {
            $out[(int) $r['idunit']] = (int) $r['is_operational'];
        }

        return $out;
    }

    public function testMigrasiMenambahkanKolomDanMenandaiHeadOffice(): void
    {
        $this->migrasi()->up();

        $this->assertTrue($this->kolomAda('is_operational', 'unit'));

        $byId = $this->isOperasional();
        $this->assertSame(1, $byId[1], 'unit operasional lama tetap operasional');
        $this->assertSame(1, $byId[2], 'unit operasional lama tetap operasional');
        $this->assertSame(0, $byId[50], 'Head Office (unit 50) ditandai non-operasional');
    }

    public function testMigrasiIdempotent(): void
    {
        $migrasi = $this->migrasi();

        $migrasi->up();
        $migrasi->up();

        $this->assertTrue($this->kolomAda('is_operational', 'unit'));

        $byId = $this->isOperasional();
        $this->assertSame(1, $byId[1]);
        $this->assertSame(0, $byId[50]);
    }

    public function testDownMenghapusKolom(): void
    {
        $migrasi = $this->migrasi();

        $migrasi->up();
        $migrasi->down();

        $this->assertFalse($this->kolomAda('is_operational', 'unit'));
    }
}