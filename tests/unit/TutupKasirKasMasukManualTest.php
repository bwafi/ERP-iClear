<?php

namespace Tests\unit;

use App\Services\Finance\TutupKasirSourceDefinition;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kas Masuk manual (bukan 'kas awal') adalah sumber movement.
 *
 * Kontrak:
 *  - idbank NULL  -> tunai
 *  - idbank terisi -> transfer
 *  - deskripsi 'kas awal' (case-insensitive, trim) tidak dihitung
 *  - jenis TIDAK menentukan arah
 *
 * @internal
 */
final class TutupKasirKasMasukManualTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-12-01';
    private const D2 = '2026-12-02';

    private const TABEL = ['kas_masuk', 'penjualan', 'service', 'service_dp', 'kas_keluar'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';
    private TutupKasirSourceDefinition $src;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }

        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            deskripsi TEXT NULL,
            jumlah BIGINT DEFAULT 0,
            idunit INT NULL,
            idbank VARCHAR(20) NULL,
            jenis VARCHAR(15) NULL)');

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

        $this->src = new TutupKasirSourceDefinition($this->db);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function masuk(string $tanggal, int $jumlah, ?string $bank, string $deskripsi = 'Setoran', int $unit = self::UNIT): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => $tanggal,
            'deskripsi' => $deskripsi,
            'jumlah' => $jumlah,
            'idunit' => $unit,
            'idbank' => $bank,
            'jenis' => 'debet',
        ]);
    }

    public function testTunaiDanTransferDipisah(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $this->assertSame(50000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
        $this->assertSame(70000, $this->src->kasMasukManualTransfer(self::UNIT, self::D1));
    }

    public function testKasAwalTidakDihitung(): void
    {
        $this->masuk(self::D1, 900000, null, 'kas awal');
        $this->masuk(self::D1, 100000, null, ' KAS AWAL ');
        $this->masuk(self::D1, 25000, null, 'Setoran');

        $this->assertSame(25000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualTransfer(self::UNIT, self::D1));
    }

    public function testJenisTidakMenentukanArah(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => self::D1, 'deskripsi' => 'Kredit tetap masuk',
            'jumlah' => 40000, 'idunit' => self::UNIT, 'idbank' => null,
            'jenis' => 'kredit',
        ]);

        $this->assertSame(40000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
    }

    public function testUnitLainDanRentang(): void
    {
        $this->masuk(self::D1, 10000, null, 'Unit 1');
        $this->masuk(self::D2, 20000, null, 'Unit 1');
        $this->masuk(self::D1, 99000, null, 'Unit 2', 2);

        $this->assertSame(30000, $this->src->kasMasukManualCashRange(self::UNIT, self::D1, self::D2));
        $this->assertSame(10000, $this->src->kasMasukManualCashRange(self::UNIT, self::D1, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualCashRange(3, self::D1, self::D2));
    }

    public function testBankRangeMemfilterRekening(): void
    {
        $this->masuk(self::D1, 10000, 'BNI-001');
        $this->masuk(self::D1, 20000, 'BCA-001');
        $this->masuk(self::D1, 5000, 'BNI-001', 'Unit 2', 2);

        $this->assertSame(10000, $this->src->kasMasukManualTransferBankRange('BNI-001', self::UNIT, self::D1));
        $this->assertSame(15000, $this->src->kasMasukManualTransferBankRange('BNI-001', null, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualTransferBankRange('', self::UNIT, self::D1));
    }

    public function testHarianRangeMengelompokkanPerTanggal(): void
    {
        $this->masuk(self::D1, 10000, null);
        $this->masuk(self::D1, 20000, 'BNI-001');
        $this->masuk(self::D2, 30000, null);

        $hasil = $this->src->kasMasukManualHarianRange(self::UNIT, self::D1, self::D2);

        $this->assertSame(10000, $hasil['cash'][self::D1]);
        $this->assertSame(20000, $hasil['transfer'][self::D1]);
        $this->assertSame(30000, $hasil['cash'][self::D2]);
        $this->assertArrayNotHasKey(self::D2, $hasil['transfer']);
    }

    public function testKasMasukDpMemuatKasMasukManual(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $this->assertSame(50000, $this->src->kasMasukDp(self::UNIT, self::D1));
        $this->assertSame(70000, $this->src->transferMasukDp(self::UNIT, self::D1));
    }

    public function testRingkasanRangeMemuatKasMasukManual(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $r = $this->src->ringkasanRange(self::UNIT, self::D1, self::D1);

        $this->assertSame(50000, $r['kas_masuk_tunai']);
        $this->assertSame(70000, $r['kas_masuk_transfer']);
        $this->assertSame(50000, $r['cash']);
        $this->assertSame(70000, $r['transfer']);
        $this->assertSame(120000, $r['total_pendapatan']);
    }
}
