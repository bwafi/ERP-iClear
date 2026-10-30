<?php

namespace Tests\unit;

use App\Services\Finance\OmsetDailyCalculator;
use App\Services\Kpi\OmsetCabangCalculator;
use App\Services\Kpi\OmsetTeknisiCalculator;
use App\Services\Kpi\OmsetTokoCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Refund garansi mengurangi omzet Toko/Cabang/Teknisi pada bulan refund, dan
 * omzet harian pada tanggal refund. Tiap test memakai unit berbeda karena
 * kalkulator KPI menyimpan memo statis per (unit, bulan, tahun).
 */
final class GaransiRefundKpiTest extends CIUnitTestCase
{
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = ['penjualan', 'detail_penjualan', 'service', 'service_sparepart', 'akun', 'barang', 'unit'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT PRIMARY KEY, tanggal DATETIME NULL, unit_idunit INT NULL)');
        $this->db->query('CREATE TABLE detail_penjualan (
            iddetail_penjualan INT AUTO_INCREMENT PRIMARY KEY,
            penjualan_idpenjualan INT NULL, sub_total DECIMAL(15,2) NULL, hpp_penjualan DECIMAL(15,2) NULL)');
        $this->db->query('CREATE TABLE service (
            idservice INT PRIMARY KEY, no_service VARCHAR(40) NULL, unit_idunit INT NULL, service_by INT NULL,
            status_service INT NULL, tanggal_selesai DATETIME NULL, harus_dibayar DECIMAL(15,2) NULL,
            refund_garansi_tanggal DATE NULL, refund_garansi_jumlah INT NOT NULL DEFAULT 0,
            refund_garansi_omset INT NOT NULL DEFAULT 0)');
        $this->db->query('CREATE TABLE service_sparepart (
            idservice_sparepart INT AUTO_INCREMENT PRIMARY KEY, service_idservice INT NULL,
            barang_idbarang INT NULL, unit_idunit INT NULL, jumlah DECIMAL(15,2) NULL DEFAULT 0,
            hpp_penjualan DECIMAL(15,2) NULL DEFAULT 0, jumlah_tambahan_garansi DECIMAL(15,2) NULL DEFAULT 0,
            status_retur VARCHAR(20) NULL, hpp_omset_garansi DECIMAL(15,2) NULL DEFAULT 0,
            tanggal_hpp_klaim DATE NULL)');
        $this->db->query('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, NAMA_AKUN VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE barang (idbarang INT PRIMARY KEY, nama_barang VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL)');
    }

    private function seed(int $unitId, int $refundOktober = 0, int $refundNovember = 0, int $hppLoss = 0): void
    {
        $this->db->table('unit')->insert(['idunit' => $unitId, 'NAMA_UNIT' => 'Unit ' . $unitId]);
        $this->db->table('akun')->insert(['ID_AKUN' => 7, 'NAMA_AKUN' => 'Teknisi 7']);

        $this->db->table('penjualan')->insert([
            'idpenjualan' => $unitId,
            'tanggal' => '2026-10-03 10:00:00',
            'unit_idunit' => $unitId,
        ]);
        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => $unitId,
            'sub_total' => 1000000,
            'hpp_penjualan' => 200000,
        ]);

        $this->db->table('service')->insert([
            'idservice' => $unitId,
            'unit_idunit' => $unitId,
            'service_by' => 7,
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-04 10:00:00',
            'harus_dibayar' => 500000,
            'refund_garansi_tanggal' => $refundOktober > 0 ? '2026-10-25' : null,
            'refund_garansi_jumlah' => $refundOktober,
            'refund_garansi_omset' => $refundOktober,
        ]);

        if ($refundNovember > 0) {
            $this->db->table('service')->insert([
                'idservice' => $unitId + 1000,
                'unit_idunit' => $unitId,
                'service_by' => 7,
                'status_service' => 4,
                'tanggal_selesai' => '2026-10-04 10:00:00',
                'harus_dibayar' => 0,
                'refund_garansi_tanggal' => '2026-11-02',
                'refund_garansi_jumlah' => $refundNovember,
                'refund_garansi_omset' => $refundNovember,
            ]);
        }

        if ($hppLoss > 0) {
            $this->db->table('barang')->insert(['idbarang' => 10, 'nama_barang' => 'Sparepart']);
            $this->db->table('service_sparepart')->insert([
                'service_idservice' => $unitId,
                'unit_idunit' => $unitId,
                'jumlah_tambahan_garansi' => 1,
                'status_retur' => 'tidak_bisa',
                'hpp_omset_garansi' => $hppLoss,
                'tanggal_hpp_klaim' => '2026-10-25',
            ]);
        }
    }

    public function testTanpaRefundOmsetNormal(): void
    {
        $this->seed(201);

        $this->assertSame(800000.0, (new OmsetTokoCalculator())->calculate(7, 201, 10, 2026));
        $this->assertSame(800000.0, (new OmsetCabangCalculator())->calculate(7, 201, 10, 2026));
        $this->assertSame(500000.0, (new OmsetTeknisiCalculator())->calculate(7, 201, 10, 2026));
    }

    public function testRefundMengurangiTokoCabangDanTeknisi(): void
    {
        $this->seed(202, 100000);

        $this->assertSame(700000.0, (new OmsetTokoCalculator())->calculate(7, 202, 10, 2026));
        $this->assertSame(700000.0, (new OmsetCabangCalculator())->calculate(7, 202, 10, 2026));
        $this->assertSame(400000.0, (new OmsetTeknisiCalculator())->calculate(7, 202, 10, 2026));
    }

    public function testRefundBulanBerikutnyaTidakMengurangiBulanRefund(): void
    {
        $this->seed(203, 100000, 50000);

        $this->assertSame(700000.0, (new OmsetTokoCalculator())->calculate(7, 203, 10, 2026));
        $this->assertSame(700000.0, (new OmsetCabangCalculator())->calculate(7, 203, 10, 2026));
        $this->assertSame(400000.0, (new OmsetTeknisiCalculator())->calculate(7, 203, 10, 2026));
    }

    public function testHppKlaimTetapMengurangiTeknisiSaja(): void
    {
        $this->seed(204, 100000, 0, 10000);

        $this->assertSame(700000.0, (new OmsetTokoCalculator())->calculate(7, 204, 10, 2026));
        $this->assertSame(700000.0, (new OmsetCabangCalculator())->calculate(7, 204, 10, 2026));
        $this->assertSame(390000.0, (new OmsetTeknisiCalculator())->calculate(7, 204, 10, 2026));
    }

    public function testOmsetHanyaDikurangiPorsiGrossProfit(): void
    {
        $this->seed(206, 100000);
        $this->db->table('service')->where('idservice', 206)->update(['refund_garansi_omset' => 30000]);

        // Kas refund 100.000, tetapi omset hanya turun 30.000 (porsi gross profit).
        $this->assertSame(770000.0, (new OmsetTokoCalculator())->calculate(7, 206, 10, 2026));
        $this->assertSame(770000.0, (new OmsetCabangCalculator())->calculate(7, 206, 10, 2026));
    }

    public function testOmzetHarianIkutBerkurang(): void
    {
        $this->seed(205, 100000);

        $harian = new OmsetDailyCalculator();
        $this->assertSame(-100000.0, $harian->calculateDaily(205, '2026-10-25'));

        $rentang = $harian->calculateDailyByRange(205, '2026-10-01', '2026-10-31');
        $this->assertSame(-100000.0, $rentang['2026-10-25']);
        $this->assertSame(800000.0, $rentang['2026-10-03']);
    }
}
