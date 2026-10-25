<?php

namespace Tests\unit;

use App\Models\ModelHppKlaimLoss;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class ModelHppKlaimLossTest extends CIUnitTestCase
{
    private const TABEL = [
        'db_service_sparepart',
        'db_service',
        'db_akun',
        'db_barang',
        'db_unit',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests', false);

        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        $this->db->query('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            service_by INT NULL,
            unit_idunit INT NULL,
            status_service INT NULL,
            tanggal_selesai DATETIME NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $this->db->query('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            unit_idunit INT NULL,
            jumlah_tambahan_garansi INT NULL DEFAULT 0,
            hpp_omset_garansi INT NOT NULL DEFAULT 0,
            status_retur VARCHAR(20) NULL,
            tanggal_hpp_klaim DATE NULL,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $this->db->query('CREATE TABLE db_akun (
            ID_AKUN INT NOT NULL,
            NAMA_AKUN VARCHAR(64) NULL,
            PRIMARY KEY (ID_AKUN)
        ) ENGINE=InnoDB');

        $this->db->query('CREATE TABLE db_barang (
            idbarang INT NOT NULL,
            nama_barang VARCHAR(128) NULL,
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $this->db->query('CREATE TABLE db_unit (
            idunit INT NOT NULL,
            NAMA_UNIT VARCHAR(64) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => 1, 'NAMA_AKUN' => 'Teknisi A'],
            ['ID_AKUN' => 2, 'NAMA_AKUN' => 'Teknisi B'],
        ]);
        $this->db->table('barang')->insert(['idbarang' => 10, 'nama_barang' => 'LCD']);
        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit 2'],
        ]);
        $this->db->table('service')->insertBatch([
            ['idservice' => 1, 'no_service' => 'SRV-1', 'service_by' => 1, 'unit_idunit' => 1, 'status_service' => 4],
            ['idservice' => 2, 'no_service' => 'SRV-2', 'service_by' => 2, 'unit_idunit' => 2, 'status_service' => 4],
        ]);
        $this->db->table('service_sparepart')->insertBatch([
            ['idservice_sparepart' => 1, 'service_idservice' => 1, 'barang_idbarang' => 10, 'unit_idunit' => 1, 'jumlah_tambahan_garansi' => 2, 'hpp_omset_garansi' => 1000, 'status_retur' => 'tidak_bisa', 'tanggal_hpp_klaim' => '2026-09-15'],
            ['idservice_sparepart' => 2, 'service_idservice' => 2, 'barang_idbarang' => 10, 'unit_idunit' => 2, 'jumlah_tambahan_garansi' => 3, 'hpp_omset_garansi' => 2000, 'status_retur' => 'ditolak', 'tanggal_hpp_klaim' => '2026-10-05'],
            ['idservice_sparepart' => 3, 'service_idservice' => 1, 'barang_idbarang' => 10, 'unit_idunit' => 1, 'jumlah_tambahan_garansi' => 1, 'hpp_omset_garansi' => 500, 'status_retur' => 'diterima', 'tanggal_hpp_klaim' => '2026-10-10'],
            ['idservice_sparepart' => 4, 'service_idservice' => 1, 'barang_idbarang' => 10, 'unit_idunit' => 1, 'jumlah_tambahan_garansi' => 1, 'hpp_omset_garansi' => 0, 'status_retur' => 'bisa', 'tanggal_hpp_klaim' => null],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        parent::tearDown();
    }

    public function testLossIkutBulanTanggalHppKlaim(): void
    {
        $model = new ModelHppKlaimLoss();

        $september = $model->getLoss('2026-09-01', '2026-09-30');
        $oktober = $model->getLoss('2026-10-01', '2026-10-31');

        $this->assertCount(1, $september);
        $this->assertSame('SRV-1', $september[0]->no_service);
        $this->assertSame(2000, (int) $september[0]->total);

        $this->assertCount(1, $oktober);
        $this->assertSame('SRV-2', $oktober[0]->no_service);
        $this->assertSame(6000, (int) $oktober[0]->total);
    }

    public function testFilterUnitTeknisiDanTotal(): void
    {
        $model = new ModelHppKlaimLoss();

        $this->assertSame(2000, $model->getTotalLoss('2026-09-01', '2026-09-30'));
        $this->assertSame(6000, $model->getTotalLoss('2026-10-01', '2026-10-31', 2));
        $this->assertSame(2000, $model->getTotalLoss('2026-09-01', '2026-10-31', null, 1));
        $this->assertSame(8000, $model->getTotalLoss('2026-09-01', '2026-10-31'));
    }
}
