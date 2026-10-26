<?php

namespace Tests\unit;

use App\Models\ModelRefundGaransiLoss;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Refund garansi mengurangi omzet pada bulan tanggal refund, per unit dan
 * per teknisi (service.service_by).
 */
final class ModelRefundGaransiLossTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_service');
        $this->db->query('CREATE TABLE db_service (
            idservice INT PRIMARY KEY,
            unit_idunit INT NULL,
            service_by INT NULL,
            refund_garansi_tanggal DATE NULL,
            refund_garansi_jumlah INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB');

        $this->db->table('service')->insertBatch([
            ['idservice' => 1, 'unit_idunit' => 1, 'service_by' => 7, 'refund_garansi_tanggal' => '2026-10-05', 'refund_garansi_jumlah' => 100000],
            ['idservice' => 2, 'unit_idunit' => 1, 'service_by' => 8, 'refund_garansi_tanggal' => '2026-10-20', 'refund_garansi_jumlah' => 50000],
            ['idservice' => 3, 'unit_idunit' => 1, 'service_by' => 7, 'refund_garansi_tanggal' => '2026-11-01', 'refund_garansi_jumlah' => 70000],
            ['idservice' => 4, 'unit_idunit' => 2, 'service_by' => 7, 'refund_garansi_tanggal' => '2026-10-10', 'refund_garansi_jumlah' => 25000],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_service');

        parent::tearDown();
    }

    public function testTotalDanRincianPerTanggal(): void
    {
        $model = new ModelRefundGaransiLoss();

        $this->assertSame(175000, $model->getTotal('2026-10-01', '2026-10-31'));
        $this->assertSame(150000, $model->getTotal('2026-10-01', '2026-10-31', 1));
        $this->assertSame(100000, $model->getTotal('2026-10-01', '2026-10-31', 1, 7));

        $this->assertSame([
            '2026-10-05' => 100000,
            '2026-10-20' => 50000,
        ], $model->getByDateRange('2026-10-01', '2026-10-31', 1));
    }

    public function testKolomBelumAdaMengembalikanNilaiNetral(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_service');
        $this->db->query('CREATE TABLE db_service (
            idservice INT PRIMARY KEY,
            unit_idunit INT NULL,
            service_by INT NULL
        ) ENGINE=InnoDB');

        $model = new ModelRefundGaransiLoss();

        $this->assertSame(0, $model->getTotal('2026-10-01', '2026-10-31'));
        $this->assertSame([], $model->getByDateRange('2026-10-01', '2026-10-31'));
    }
}
