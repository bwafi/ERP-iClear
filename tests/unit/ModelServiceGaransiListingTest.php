<?php

namespace Tests\unit;

use App\Models\ModelService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Listing Garansi Service: hanya service bergaransi, filter tanggal memakai
 * tanggal selesai, dan unit non-HO dibatasi ke unitnya.
 */
final class ModelServiceGaransiListingTest extends CIUnitTestCase
{
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = ['service', 'pelanggan', 'unit'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->hapus();

        $this->db->query('CREATE TABLE service (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(40) NULL,
            created_at DATETIME NULL,
            tanggal_selesai DATETIME NULL,
            no_hp VARCHAR(32) NULL,
            alamat TEXT NULL,
            garansi_hari INT NULL DEFAULT 0,
            tanggal_claim_garansi DATETIME NULL,
            status_service INT NULL,
            unit_idunit INT NULL,
            pelanggan_id_pelanggan INT NULL)');
        $this->db->query('CREATE TABLE pelanggan (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE unit (
            idunit INT PRIMARY KEY,
            NAMA_UNIT VARCHAR(100) NULL,
            KABUPATEN_UNIT VARCHAR(100) NULL)');

        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit Satu', 'KABUPATEN_UNIT' => 'Kota A'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua', 'KABUPATEN_UNIT' => 'Kota B'],
        ]);
        $this->db->table('pelanggan')->insertBatch([
            ['id_pelanggan' => 1, 'nama' => 'Ayu'],
            ['id_pelanggan' => 2, 'nama' => 'Bima'],
        ]);
        $this->db->table('service')->insertBatch([
            ['idservice' => 1, 'no_service' => 'SRV-001', 'created_at' => '2026-09-01 10:00:00', 'tanggal_selesai' => '2026-09-20 10:00:00', 'no_hp' => '0811', 'alamat' => 'Alamat A', 'garansi_hari' => 30, 'tanggal_claim_garansi' => null, 'status_service' => 4, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 2, 'no_service' => 'SRV-002', 'created_at' => '2026-09-02 10:00:00', 'tanggal_selesai' => '2026-10-02 10:00:00', 'no_hp' => '0822', 'alamat' => 'Alamat B', 'garansi_hari' => 30, 'tanggal_claim_garansi' => null, 'status_service' => 4, 'unit_idunit' => 2, 'pelanggan_id_pelanggan' => 2],
            ['idservice' => 3, 'no_service' => 'SRV-003', 'created_at' => '2026-09-03 10:00:00', 'tanggal_selesai' => '2026-09-21 10:00:00', 'no_hp' => '0833', 'alamat' => 'Alamat C', 'garansi_hari' => 0, 'tanggal_claim_garansi' => null, 'status_service' => 4, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        $this->hapus();
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function hapus(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }
    }

    public function testTotalDanDaftarMenerapkanUnitDanStatusGaransi(): void
    {
        $model = new ModelService();

        $this->assertSame(2, (int) $model->getGaransiServiceTotal());
        $this->assertSame(1, (int) $model->getGaransiServiceTotal(1));

        $hasil = $model->getGaransiServiceServerSide(0, 10, '', 2, 'desc', '', '', 1);

        $this->assertSame(1, (int) $hasil['recordsFiltered']);
        $this->assertCount(1, $hasil['data']);
        $this->assertSame('SRV-001', $hasil['data'][0]->no_service);
    }

    public function testFilterTanggalMemakaiTanggalSelesai(): void
    {
        $hasil = (new ModelService())->getGaransiServiceServerSide(
            0,
            10,
            '',
            2,
            'desc',
            '2026-10-01',
            '2026-10-31',
            ''
        );

        $this->assertSame(1, (int) $hasil['recordsFiltered']);
        $this->assertCount(1, $hasil['data']);
        $this->assertSame('SRV-002', $hasil['data'][0]->no_service);
    }

    public function testRiwayatKlaimFilterUnitTanggalDanUrutTerbaru(): void
    {
        $this->db->table('service')->insertBatch([
            ['idservice' => 4, 'no_service' => 'SRV-004', 'created_at' => '2026-10-01 09:00:00', 'tanggal_selesai' => '2026-10-01 09:00:00', 'tanggal_claim_garansi' => '2026-10-24 10:00:00', 'status_service' => 4, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 5, 'no_service' => 'SRV-005', 'created_at' => '2026-09-01 09:00:00', 'tanggal_selesai' => '2026-09-01 09:00:00', 'tanggal_claim_garansi' => '2026-09-15 10:00:00', 'status_service' => 4, 'unit_idunit' => 1, 'pelanggan_id_pelanggan' => 1],
            ['idservice' => 6, 'no_service' => 'SRV-006', 'created_at' => '2026-10-02 09:00:00', 'tanggal_selesai' => '2026-10-02 09:00:00', 'tanggal_claim_garansi' => '2026-10-02 10:00:00', 'status_service' => 4, 'unit_idunit' => 2, 'pelanggan_id_pelanggan' => 2],
        ]);

        $model = new ModelService();

        $oktober = $model->getRiwayatServiceGaransi('', '2026-10-01', '2026-10-31');
        $this->assertSame(['SRV-004', 'SRV-006'], array_column($oktober, 'no_service'));
        $this->assertSame('Unit Satu', $oktober[0]->nama_unit);

        $satuHari = $model->getRiwayatServiceGaransi('', '2026-10-02', '2026-10-02');
        $this->assertSame(['SRV-006'], array_column($satuHari, 'no_service'));

        $unitSatu = $model->getRiwayatServiceGaransi(1, '', '');
        $this->assertSame(['SRV-004', 'SRV-005'], array_column($unitSatu, 'no_service'));

        $semua = $model->getRiwayatServiceGaransi('', '', '');
        $this->assertSame(['SRV-004', 'SRV-006', 'SRV-005'], array_column($semua, 'no_service'));
    }
}
