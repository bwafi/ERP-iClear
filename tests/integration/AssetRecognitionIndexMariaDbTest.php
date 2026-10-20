<?php

namespace Tests\integration;

use App\Database\Migrations\IndexServiceJournalRecognition;
use App\Services\Finance\ServiceCashRecognition;
use App\Services\Payroll\HoSalaryDistributionService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class AssetRecognitionIndexMariaDbTest extends CIUnitTestCase
{
    public function testIndexPreservesRecognitionAndIsRepeatable(): void
    {
        if (getenv('ERP_SYNTHETIC_MARIADB_HOST') !== 'mariadb-test') {
            $this->markTestSkipped('Memerlukan MariaDB sintetis terisolasi.');
        }
        $db = Database::connect([
            'DBDriver' => 'MySQLi', 'hostname' => 'mariadb-test', 'port' => 3306,
            'database' => 'erp_finance_test', 'username' => 'root', 'password' => '',
            'DBPrefix' => '', 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
        ], false);
        self::assertSame('erp_finance_test', $db->getDatabase());
        self::assertStringContainsString('MariaDB', $db->query('SELECT VERSION() AS version')->getRow()->version);
        $db->query('CREATE TEMPORARY TABLE jurnal (idjurnal INT PRIMARY KEY, tabel_referensi VARCHAR(50), id_referensi VARCHAR(255), tanggal DATE)');
        $db->query('CREATE TEMPORARY TABLE service (idservice INT PRIMARY KEY, status_service INT, tanggal_selesai DATE, unit_idunit INT, bayar_tunai DECIMAL(18,2))');
        $db->query("INSERT INTO service VALUES (1,4,'2026-10-01',1,100),(2,2,NULL,1,200),(3,2,NULL,2,300),(4,1,NULL,1,400)");
        $db->query("INSERT INTO jurnal VALUES (1,'service','02','2026-10-02'),(2,'service','2','2026-10-03'),(3,'service','3','2026-09-30'),(4,'other','4','2026-10-01')");
        $sql = 'SELECT idservice, ' . ServiceCashRecognition::dateExpression()
            . ' AS recognized_date FROM service WHERE ' . ServiceCashRecognition::recognizedExpression() . ' ORDER BY idservice';
        $before = $db->query($sql)->getResultArray();
        $legacy = $db->query("SELECT idservice, COALESCE(service.tanggal_selesai, (SELECT MIN(j.tanggal) FROM jurnal j WHERE j.tabel_referensi='service' AND j.id_referensi=service.idservice)) AS recognized_date FROM service WHERE (status_service=4 OR (status_service=2 AND EXISTS (SELECT 1 FROM jurnal j WHERE j.tabel_referensi='service' AND j.id_referensi=service.idservice))) ORDER BY idservice")->getResultArray();
        self::assertSame($legacy, $before);
        require_once APPPATH . 'Database/Migrations/2026-10-20-000700_IndexServiceJournalRecognition.php';
        $migration = new IndexServiceJournalRecognition(Database::forge($db));
        $migration->up();
        $migration->up();
        self::assertSame($before, $db->query($sql)->getResultArray());
        self::assertSame([1, 2, 3], array_map('intval', array_column($before, 'idservice')));
        self::assertSame('2026-10-02', $before[1]['recognized_date']);
        $index = $db->query("SHOW INDEX FROM jurnal WHERE Key_name='idx_jurnal_source_date'")->getResultArray();
        self::assertCount(3, $index);
        self::assertSame(['tabel_referensi', 'id_referensi', 'tanggal'], array_column($index, 'Column_name'));
        $plan = $db->query("EXPLAIN SELECT MIN(tanggal) FROM jurnal WHERE tabel_referensi='service' AND id_referensi='2'")->getResultArray();
        self::assertNotSame('ALL', $plan[0]['type']);
    }

    public function testSharesReuseExactlyTheProvidedDistribution(): void
    {
        $distribution = [
            'divisor' => 2, 'per_employee_share' => 123.45, 'per_employee_actual' => 100.0,
            'unit_detail' => [
                ['idunit' => 1, 'nama' => 'UAT-TEAM A', 'jenis' => 'pribadi', 'is_counted' => true],
                ['idunit' => 2, 'nama' => 'UAT-TEAM B', 'jenis' => 'franchise', 'is_counted' => false],
            ],
        ];
        $shares = HoSalaryDistributionService::sharesFromDistribution($distribution);
        self::assertSame(123.45, $shares[1]['share_nominal_perkiraan']);
        self::assertSame(100.0, $shares[1]['share_nominal_actual']);
        self::assertEquals(50, $shares[1]['share_pct']);
        self::assertSame(0, $shares[2]['share_nominal_perkiraan']);
        $distribution['per_employee_share'] = 999.0;
        self::assertSame(999.0, HoSalaryDistributionService::sharesFromDistribution($distribution)[1]['share_nominal_perkiraan']);
    }
}
