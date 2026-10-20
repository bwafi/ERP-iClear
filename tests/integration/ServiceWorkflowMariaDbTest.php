<?php

namespace Tests\integration;

use App\Database\Migrations\CreateTechnicianCashierServiceQueues;
use App\Services\Service\ServiceWorkflow;
use App\Services\Service\ServiceWorkflowAccess;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class ServiceWorkflowMariaDbTest extends CIUnitTestCase
{
    public function testQueueTransitionsPermissionsAndMigrationAreMariaDbSafe(): void
    {
        $host = getenv('ERP_SYNTHETIC_MARIADB_HOST');
        if ($host !== '127.0.0.1') {
            $this->markTestSkipped('Set ERP_SYNTHETIC_MARIADB_HOST=127.0.0.1 untuk MariaDB lokal terisolasi.');
        }
        $port = (int) (getenv('ERP_SYNTHETIC_MARIADB_PORT') ?: 3307);
        $base = [
            'DBDriver' => 'MySQLi', 'hostname' => $host, 'port' => $port,
            'database' => 'erp_finance_test', 'username' => 'root', 'password' => '',
            'DBPrefix' => '', 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
        ];
        $admin = Database::connect($base, false);
        self::assertSame('erp_finance_test', $admin->getDatabase());
        self::assertStringContainsString('MariaDB', (string) $admin->query('SELECT VERSION() AS version')->getRow()->version);

        $fixtureName = 'erp_finance_test_workflow_' . bin2hex(random_bytes(4));
        $admin->query('CREATE DATABASE ' . $fixtureName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $base['database'] = $fixtureName;
        $db = Database::connect($base, false);
        try {
            $this->createPermissionFixtures($db);
            require_once APPPATH . 'Database/Migrations/2026-10-20-000800_CreateTechnicianCashierServiceQueues.php';
            $migration = new CreateTechnicianCashierServiceQueues(Database::forge($base));
            $migration->up();
            $migration->up();

            self::assertSame(0, (int) $db->table('service_workflow')->where('service_idservice', 7001)->countAllResults(), 'Service lama tidak dipindahkan otomatis ke antrean baru.');
            self::assertSame(0, (int) $db->table('menu')->where('url', 'service')->get()->getRow()->show_menu);
            self::assertSame([31, ServiceWorkflowAccess::TECHNICIAN_MENU], array_map('intval', json_decode($db->table('jabatan')->where('ID_JABATAN', 701)->get()->getRow()->ROLES_JABATAN, true)));
            self::assertSame([32, ServiceWorkflowAccess::CASHIER_MENU], array_map('intval', json_decode($db->table('jabatan')->where('ID_JABATAN', 702)->get()->getRow()->ROLES_JABATAN, true)));
            self::assertSame([], json_decode($db->table('jabatan')->where('ID_JABATAN', 703)->get()->getRow()->ROLES_JABATAN, true));
            self::assertSame([31, ServiceWorkflowAccess::TECHNICIAN_MENU], array_map('intval', json_decode($db->table('akun')->where('ID_AKUN', 14)->get()->getRow()->ROLES, true)));
            self::assertTrue((new ServiceWorkflowAccess($db))->can(11, 701, ServiceWorkflowAccess::TECHNICIAN_MENU));
            self::assertFalse((new ServiceWorkflowAccess($db))->can(12, 702, ServiceWorkflowAccess::TECHNICIAN_MENU));
            self::assertTrue((new ServiceWorkflowAccess($db))->can(12, 702, ServiceWorkflowAccess::CASHIER_MENU));
            self::assertTrue((new ServiceWorkflowAccess($db))->can(14, 703, ServiceWorkflowAccess::TECHNICIAN_MENU), 'Izin akun langsung tetap dihormati.');
            self::assertFalse((new ServiceWorkflowAccess($db))->can(14, 703, ServiceWorkflowAccess::CASHIER_MENU));

            $db->table('service_workflow')->insert([
                'service_idservice' => 8001, 'queue_status' => ServiceWorkflow::DRAFT,
                'technician_idakun' => 11, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $workflow = new ServiceWorkflow($db);
            $workflow->sendToCashier(8001, 11, 'UAT-TEAM: pemeriksaan sintetis');
            self::assertSame(ServiceWorkflow::QUEUED, $this->status($db, 8001));
            try {
                $workflow->sendToCashier(14, 11, 'Serah terima milik teknisi lain');
                self::fail('Teknisi tidak boleh menyerahkan service milik teknisi lain.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('tidak dapat dikirim ulang', $e->getMessage());
            }
            try {
                $workflow->sendToCashier(8001, 11, 'duplikat');
                self::fail('Kirim ulang saat antrean aktif harus ditolak.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('tidak dapat dikirim ulang', $e->getMessage());
            }
            self::assertSame(1, (int) $db->table('service_workflow_event')->where('event_type', 'sent_to_cashier')->countAllResults());

            $workflow->claimByCashier(8001, 12);
            try {
                $workflow->claimByCashier(8001, 13);
                self::fail('Kasir kedua tidak boleh mengambil transaksi yang sedang dikunci kasir pertama.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('kasir lain', $e->getMessage());
            }
            try {
                $workflow->returnToTechnician(8001, 12, ' ');
                self::fail('Alasan pengembalian kosong harus ditolak.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('wajib diisi', $e->getMessage());
            }
            $workflow->returnToTechnician(8001, 12, 'Diagnosis perlu dilengkapi');
            self::assertSame(ServiceWorkflow::RETURNED, $this->status($db, 8001));
            $workflow->sendToCashier(8001, 11, 'Rekomendasi revisi');
            $workflow->claimByCashier(8001, 12);
            $workflow->markDone(8001, 12);
            self::assertSame(ServiceWorkflow::DONE, $this->status($db, 8001));
            self::assertSame(6, (int) $db->table('service_workflow_event')->where('service_idservice', 8001)->countAllResults());

            $migration->down();
            self::assertTrue($db->tableExists('service_workflow'), 'Rollback mempertahankan data antrean yang sudah tercatat.');
            self::assertSame(ServiceWorkflow::DONE, $this->status($db, 8001));
            self::assertSame(1, (int) $db->table('menu')->where('url', 'service')->get()->getRow()->show_menu);
            self::assertSame(0, (int) $db->table('menu')->whereIn('idmenu', [ServiceWorkflowAccess::TECHNICIAN_MENU, ServiceWorkflowAccess::CASHIER_MENU])->countAllResults());
            self::assertSame([31], array_map('intval', json_decode($db->table('jabatan')->where('ID_JABATAN', 701)->get()->getRow()->ROLES_JABATAN, true)));
            $migration->up();
            self::assertSame(ServiceWorkflow::DONE, $this->status($db, 8001), 'Migrasi ulang reaproveita os dados preservados.');
            self::assertSame(0, (int) $db->table('menu')->where('url', 'service')->get()->getRow()->show_menu);
            self::assertSame(1, (int) $db->table('menu')->where('url', 'antrean-kasir')->countAllResults(), 'Pemasangan ulang tidak menggandakan menu.');
        } finally {
            $admin->query('DROP DATABASE ' . $fixtureName);
        }
    }

    private function createPermissionFixtures($db): void
    {
        $db->query('CREATE TABLE menu (idmenu INT PRIMARY KEY, urutan INT, nama_menu VARCHAR(100), roles VARCHAR(100), url VARCHAR(200), show_menu TINYINT, sub TINYINT, parent INT, utama TINYINT, categories TINYINT, icon TEXT NULL, manualbook TEXT NULL) ENGINE=InnoDB');
        $db->query('CREATE TABLE service (idservice INT PRIMARY KEY, unit_idunit INT NOT NULL) ENGINE=InnoDB');
        $db->query('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, ROLES_JABATAN TEXT NULL) ENGINE=InnoDB');
        $db->query('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_JABATAN INT, ROLES TEXT NULL) ENGINE=InnoDB');
        $db->query("INSERT INTO menu VALUES (31,30,'Service','service','service',1,0,9,0,0,'',''), (32,31,'Proses Service','proses_service','proses_service',1,0,9,0,0,'','')");
        $db->query("INSERT INTO jabatan VALUES (701,'[31]'),(702,'[32]'),(703,'[]')");
        $db->query("INSERT INTO akun VALUES (11,701,'[]'),(12,702,'[]'),(13,703,'[]'),(14,703,'[31]')");
        $db->query('INSERT INTO service VALUES (7001,1)');
    }

    private function status($db, int $serviceId): string
    {
        return (string) $db->table('service_workflow')->where('service_idservice', $serviceId)->get()->getRow()->queue_status;
    }
}
