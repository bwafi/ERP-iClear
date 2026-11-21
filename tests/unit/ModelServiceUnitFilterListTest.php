<?php

namespace Tests\unit;

use App\Models\ModelService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Filter unit daftar service menerima satu ID maupun daftar ID (cabang SPV),
 * dan total DataTables mengikuti scope yang sama.
 */
final class ModelServiceUnitFilterListTest extends CIUnitTestCase
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
            NAMA_UNIT VARCHAR(100) NULL,
            KABUPATEN_UNIT VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE pelanggan (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE service (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(40) NULL,
            created_at DATETIME NULL,
            tanggal_selesai DATETIME NULL,
            no_hp VARCHAR(32) NULL,
            tipe_hp VARCHAR(100) NULL,
            keterangan TEXT NULL,
            garansi_hari INT NULL DEFAULT 0,
            tanggal_claim_garansi DATETIME NULL,
            status_service INT NULL,
            status_proses INT NULL,
            prioritas INT NULL,
            unit_idunit INT NULL,
            pelanggan_id_pelanggan INT NULL)');
        $this->db->query('CREATE TABLE service_kerusakan (
            idservice_kerusakan INT PRIMARY KEY,
            service_idservice INT NULL)');
        $this->db->query('CREATE TABLE service_sparepart (
            idservice_sparepart INT PRIMARY KEY,
            service_idservice INT NULL)');

        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit Satu', 'KABUPATEN_UNIT' => 'Kota A'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua', 'KABUPATEN_UNIT' => 'Kota B'],
            ['idunit' => 3, 'NAMA_UNIT' => 'Unit Tiga', 'KABUPATEN_UNIT' => 'Kota C'],
            ['idunit' => 4, 'NAMA_UNIT' => 'Unit, Satu', 'KABUPATEN_UNIT' => 'Kota D'],
        ]);
        $this->db->table('pelanggan')->insert([
            'id_pelanggan' => 1,
            'nama' => 'Pelanggan',
        ]);
        $this->db->table('service')->insertBatch([
            ['idservice' => 1, 'no_service' => 'SRV-1', 'created_at' => '2026-11-01 09:00:00', 'status_service' => 1, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 2, 'no_service' => 'SRV-2', 'created_at' => '2026-11-02 09:00:00', 'status_service' => 1, 'unit_idunit' => 2, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 3, 'no_service' => 'SRV-3', 'created_at' => '2026-11-03 09:00:00', 'status_service' => 1, 'unit_idunit' => 3, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 4, 'no_service' => 'SRV-4', 'created_at' => '2026-11-04 09:00:00', 'status_service' => 5, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
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
        foreach (['service', 'service_kerusakan', 'service_sparepart', 'pelanggan', 'unit'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function model(): ModelService
    {
        return new ModelService();
    }

    public function testFilterKosongBerartiSemuaUnit(): void
    {
        $this->assertSame([], $this->model()->resolveUnitFilterIds(''));
    }

    public function testFilterIdTunggal(): void
    {
        $this->assertSame([2], $this->model()->resolveUnitFilterIds('2'));
    }

    public function testFilterDaftarDipisahKoma(): void
    {
        $this->assertSame([1, 2], $this->model()->resolveUnitFilterIds('1,2'));
    }

    public function testFilterArray(): void
    {
        $this->assertSame([1, 2], $this->model()->resolveUnitFilterIds([1, 2]));
    }

    public function testFilterNamaUnit(): void
    {
        $this->assertSame([1], $this->model()->resolveUnitFilterIds('Unit Satu'));
    }

    public function testFilterNamaUnitDenganKomaTetapSatuUnit(): void
    {
        $this->assertSame([4], $this->model()->resolveUnitFilterIds('Unit, Satu'));
    }

    public function testSentinelMinusSatuTetapDipertahankan(): void
    {
        $this->assertSame([-1], $this->model()->resolveUnitFilterIds('-1'));
    }

    public function testRiwayatMembatasiDuaUnit(): void
    {
        $hasil = $this->model()->getRiwayatServiceServerSide(0, 50, '', 1, 'asc', '', '', [1, 2]);

        $unit = array_map(static fn ($row) => (int) $row->unit_idunit, $hasil['data']);
        sort($unit);

        $this->assertSame([1, 1, 2], $unit);
    }

    public function testTotalRiwayatMengikutiScope(): void
    {
        $this->assertSame(3, $this->model()->getRiwayatServiceTotal([1, 2]));
    }

    public function testProsesServiceMengabaikanUnitSentinel(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('ProsesServiceAktifServerSide memakai DATEDIFF/NOW (MySQL).');
        }

        $hasil = $this->model()->ProsesServiceAktifServerSide(0, 50, '', 0, 'asc', '', '', [-1]);

        $this->assertSame([], $hasil['data']);
    }

    public function testTotalProsesServiceMengikutiScope(): void
    {
        $this->assertSame(2, $this->model()->ProsesServiceAktifTotal([1, 2]));
    }
}
