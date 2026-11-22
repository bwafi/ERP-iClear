<?php

namespace Tests\unit;

use App\Services\Finance\RekonDailyCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class RekonKasMasukManualTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-12-01';
    private const D2 = '2026-12-02';

    private const TABEL = ['penjualan', 'service', 'service_dp', 'kas_keluar', 'kas_masuk'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';
    private RekonDailyCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }

        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, kode_invoice VARCHAR(64) NULL,
            bayar_tunai BIGINT DEFAULT 0, bayar_bank BIGINT DEFAULT 0,
            tanggal DATE NULL)');
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, status_service INT DEFAULT 1,
            bayar_tunai BIGINT DEFAULT 0, harus_dibayar BIGINT DEFAULT 0,
            tanggal_selesai DATE NULL)');
        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL, jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            metode VARCHAR(15) NULL, tanggal DATE NULL)');
        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            idunit INT NULL, idbank VARCHAR(20) NULL,
            jumlah BIGINT DEFAULT 0, tanggal DATE NULL)');
        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL, deskripsi TEXT NULL, jumlah BIGINT DEFAULT 0,
            idunit INT NULL, idbank VARCHAR(20) NULL, jenis VARCHAR(15) NULL)');

        $this->db->table('kas_masuk')->insertBatch([
            ['tanggal' => self::D1, 'deskripsi' => 'Modal tunai', 'jumlah' => 10000, 'idunit' => self::UNIT, 'idbank' => null, 'jenis' => 'debet'],
            ['tanggal' => self::D1, 'deskripsi' => 'Transfer masuk', 'jumlah' => 20000, 'idunit' => self::UNIT, 'idbank' => 'BNI-001', 'jenis' => 'debet'],
            ['tanggal' => self::D2, 'deskripsi' => 'kas awal', 'jumlah' => 999000, 'idunit' => self::UNIT, 'idbank' => null, 'jenis' => 'debet'],
        ]);

        $this->calc = new RekonDailyCalculator();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testErpValuesDanRangeMemuatKasMasukManual(): void
    {
        $d1 = $this->calc->erpValues(self::UNIT, self::D1);
        $this->assertSame(10000, $d1['cash_masuk']);
        $this->assertSame(20000, $d1['transfer_masuk']);

        $range = $this->calc->erpValuesRange(self::UNIT, self::D1, self::D2);

        foreach ([self::D1, self::D2] as $d) {
            $this->assertSame(
                $this->calc->erpValues(self::UNIT, $d),
                $range[$d] ?? ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0],
                "Range harus identik dengan per-hari pada {$d}."
            );
        }

        $this->assertSame(10000, $range[self::D1]['cash_masuk']);
        $this->assertSame(20000, $range[self::D1]['transfer_masuk']);
    }

    public function testKasAwalTidakMasukRange(): void
    {
        $range = $this->calc->erpValuesRange(self::UNIT, self::D2, self::D2);
        $this->assertArrayNotHasKey(self::D2, $range, 'Baris kas awal tidak boleh membuat hari berisi kas masuk.');
    }
}
