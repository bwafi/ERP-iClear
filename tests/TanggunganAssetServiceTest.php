<?php

namespace Tests;

use App\Services\Finance\TanggunganAssetService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tanggungan Asset bulanan: satu baris per unit+bulan, hanya unit franchise,
 * dipotong maksimal sebesar sisa saldo `unit.tanggungan`.
 */
class TanggunganAssetServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS tanggungan_asset_bulanan');
        $this->db->query('DROP TABLE IF EXISTS unit');
        $this->db->query('CREATE TABLE unit (
            idunit INTEGER PRIMARY KEY,
            NAMA_UNIT TEXT NULL,
            jenis TEXT NULL,
            tanggungan REAL NULL
        )');
        $this->db->query('CREATE TABLE tanggungan_asset_bulanan (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            unit_id INT NULL,
            periode TEXT NULL,
            nominal REAL NULL,
            created_at TEXT NULL,
            UNIQUE KEY uniq_tanggungan_asset_unit_periode (unit_id, periode)
        )');
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function unit(int $id, string $jenis, float $tanggungan): void
    {
        $this->db->table('unit')->insert([
            'idunit'     => $id,
            'NAMA_UNIT'  => 'Unit ' . $id,
            'jenis'      => $jenis,
            'tanggungan' => $tanggungan,
        ]);
    }

    private function saldo(int $id): float
    {
        return (float) $this->db->table('unit')
            ->select('tanggungan')
            ->where('idunit', $id)
            ->get()->getRow()->tanggungan;
    }

    private function jumlahBaris(int $id): int
    {
        return $this->db->table('tanggungan_asset_bulanan')
            ->where('unit_id', $id)
            ->countAllResults();
    }

    public function testFranchiseMenyimpanSekaliDanMengurangiSaldo(): void
    {
        $this->unit(4, 'franchise', 80000000);

        $hasil = (new TanggunganAssetService())->simpan(4, '2026-10', 14000000);

        $this->assertTrue($hasil['baru']);
        $this->assertSame(14000000.0, $hasil['nominal']);
        $this->assertSame(66000000.0, $hasil['sisa']);
        $this->assertSame(66000000.0, $this->saldo(4));
        $this->assertSame(1, $this->jumlahBaris(4));

        // Klik/buka kedua untuk bulan yang sama tidak boleh memotong lagi.
        $ulang = (new TanggunganAssetService())->simpan(4, '2026-10', 20000000);

        $this->assertFalse($ulang['baru']);
        $this->assertSame(14000000.0, $ulang['nominal']);
        $this->assertSame(66000000.0, $this->saldo(4));
        $this->assertSame(1, $this->jumlahBaris(4));
    }

    public function testBulanBerjalanDapatDiperbaruiTanpaBarisBaru(): void
    {
        $this->unit(4, 'franchise', 80000000);
        $service = new TanggunganAssetService();
        $service->simpan(4, '2026-10', 14000000);

        // Bulan berjalan masih bisa berubah sampai bulan berganti.
        $naik = $service->simpan(4, '2026-10', 20000000, true);

        $this->assertFalse($naik['baru']);
        $this->assertSame(20000000.0, $naik['nominal']);
        $this->assertSame(60000000.0, $naik['sisa']);
        $this->assertSame(60000000.0, $this->saldo(4));
        $this->assertSame(1, $this->jumlahBaris(4));

        // Laba turun juga ikut menyesuaikan saldo.
        $turun = $service->simpan(4, '2026-10', 10000000, true);

        $this->assertSame(10000000.0, $turun['nominal']);
        $this->assertSame(70000000.0, $turun['sisa']);
        $this->assertSame(70000000.0, $this->saldo(4));
        $this->assertSame(1, $this->jumlahBaris(4));
    }

    public function testPotonganDibatasiSisaSaldo(): void
    {
        $this->unit(5, 'franchise', 5000000);

        $hasil = (new TanggunganAssetService())->simpan(5, '2026-10', 13000000);

        $this->assertSame(5000000.0, $hasil['nominal']);
        $this->assertSame(0.0, $hasil['sisa']);
        $this->assertSame(0.0, $this->saldo(5));
    }

    public function testBulanBerikutnyaNolSetelahSaldoHabis(): void
    {
        $this->unit(5, 'franchise', 5000000);
        $service = new TanggunganAssetService();
        $service->simpan(5, '2026-10', 13000000);

        $hasil = $service->simpan(5, '2026-11', 13000000);

        $this->assertSame(0.0, $hasil['nominal']);
        $this->assertSame(0.0, $this->saldo(5));
    }

    public function testUnitPribadiTidakDisentuh(): void
    {
        $this->unit(1, 'pribadi', 80000000);

        $hasil = (new TanggunganAssetService())->simpan(1, '2026-10', 14000000);

        $this->assertFalse($hasil['baru']);
        $this->assertSame(0.0, $hasil['nominal']);
        $this->assertSame(80000000.0, $this->saldo(1));
        $this->assertSame(0, $this->jumlahBaris(1));
    }

    public function testLabaMinusTidakMenambahSaldo(): void
    {
        $this->unit(4, 'franchise', 10000000);

        $hasil = (new TanggunganAssetService())->simpan(4, '2026-10', -5000000);

        $this->assertSame(0.0, $hasil['nominal']);
        $this->assertSame(10000000.0, $this->saldo(4));
        $this->assertSame(1, $this->jumlahBaris(4));
    }

    public function testNominalTersimpanDanSisa(): void
    {
        $this->unit(4, 'franchise', 80000000);
        $service = new TanggunganAssetService();

        $this->assertNull($service->nominalTersimpan(4, '2026-10'));
        $this->assertSame(80000000.0, $service->sisa(4));

        $service->simpan(4, '2026-10', 14000000);

        $this->assertSame(14000000.0, $service->nominalTersimpan(4, '2026-10'));
        $this->assertSame(66000000.0, $service->sisa(4));
    }
}
