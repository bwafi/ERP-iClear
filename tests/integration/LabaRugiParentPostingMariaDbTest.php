<?php

namespace Tests\integration;

use App\Controllers\LabaRugi;
use App\Models\ModelJurnal;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use ReflectionClass;
use ReflectionMethod;

final class LabaRugiParentPostingMariaDbTest extends CIUnitTestCase
{
    public function testDirectParentPostingsAreIncludedOnceWithinPeriodAndUnit(): void
    {
        if (getenv('ERP_SYNTHETIC_MARIADB_HOST') !== 'mariadb-test') {
            $this->markTestSkipped('Memerlukan container MariaDB privat mariadb-test.');
        }

        $db = Database::connect([
            'DBDriver' => 'MySQLi', 'hostname' => 'mariadb-test',
            'port' => (int) (getenv('ERP_SYNTHETIC_MARIADB_PORT') ?: 3306),
            'database' => 'erp_finance_test',
            'username' => getenv('ERP_SYNTHETIC_MARIADB_USER') ?: 'root',
            'password' => '', 'DBPrefix' => '', 'charset' => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
        ], false);
        self::assertSame('erp_finance_test', $db->getDatabase());
        self::assertSame('', $db->getPrefix());
        self::assertStringContainsString('MariaDB', (string) $db->query('SELECT VERSION() AS version')->getRow()->version);

        $db->query('CREATE TEMPORARY TABLE no_akun (no_akun VARCHAR(10) PRIMARY KEY, nama_akun VARCHAR(100))');
        $db->query('CREATE TEMPORARY TABLE jurnal (tanggal DATE, no_akun VARCHAR(10), nama_akun VARCHAR(100), debet BIGINT, kredit BIGINT, id_unit INT, keterangan VARCHAR(100), id_referensi INT, tabel_referensi VARCHAR(50))');
        $db->query('CREATE TEMPORARY TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(50))');

        $instances = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
        $previous = $instances->getValue();
        $instances->setValue(null, array_replace($previous, ['tests' => $db]));
        try {
            $db->query("INSERT INTO no_akun VALUES
                ('4000000000', 'Pendapatan'), ('4001000001', 'Penjualan'),
                ('5000000000', 'HPP'), ('5001000001', 'HPP Anak'),
                ('6000000000', 'Beban Operasional'), ('6001000001', 'Beban Anak'),
                ('7010000000', 'Pendapatan Lain'), ('7010000001', 'Pendapatan Lain Anak'),
                ('7020000000', 'Beban Lain'), ('7020000001', 'Beban Lain Anak')");
            $db->query("INSERT INTO unit VALUES (1, 'Unit 1'), (2, 'Unit 2')");
            $db->query("INSERT INTO jurnal (tanggal, no_akun, nama_akun, debet, kredit, id_unit) VALUES
                ('2026-01-01', '4000000000', 'Pendapatan', 0, 1000, 1),
                ('2026-01-02', '4001000001', 'Penjualan', 0, 200, 1),
                ('2026-01-02', '5000000000', 'HPP', 100, 0, 1),
                ('2026-01-02', '5001000001', 'HPP Anak', 50, 0, 1),
                ('2026-01-02', '6000000000', 'Beban Operasional', 300, 0, 1),
                ('2026-01-02', '6001000001', 'Beban Anak', 40, 0, 1),
                ('2026-01-02', '7010000000', 'Pendapatan Lain', 0, 30, 1),
                ('2026-01-02', '7010000001', 'Pendapatan Lain Anak', 0, 10, 1),
                ('2026-01-02', '7020000000', 'Beban Lain', 20, 0, 1),
                ('2026-01-02', '7020000001', 'Beban Lain Anak', 5, 0, 1),
                ('2025-12-31', '6000000000', 'Beban Operasional', 9000, 0, 1),
                ('2026-01-02', '6000000000', 'Beban Operasional', 8000, 0, 2)");

            $single = (new ModelJurnal())->getLabaRugiFromJurnal('2026-01-01', '2026-10-18', 1);
            $controller = (new ReflectionClass(LabaRugi::class))->newInstanceWithoutConstructor();
            $multiple = (new ReflectionMethod(LabaRugi::class, 'getLabaRugiMultipleUnits'))
                ->invoke($controller, '2026-01-01', '2026-10-18', [1]);

            foreach ([$single, $multiple] as $result) {
                self::assertSame(1200, (int) $result['total_pendapatan']);
                self::assertSame(150, (int) $result['total_beban_pokok_penjualan']);
                self::assertSame(340, (int) $result['total_beban_operasional']);
                self::assertSame(40, (int) $result['total_pendapatan_non_operasional']);
                self::assertSame(25, (int) $result['total_beban_non_operasional']);
                self::assertSame(725, (int) $result['laba_rugi']);
                self::assertSame(['6000000000', '6001000001'], array_column($result['beban_operasional'], 'no_akun'));
            }

            $allUnits = (new ReflectionMethod(LabaRugi::class, 'getLabaRugiMultipleUnits'))
                ->invoke($controller, '2026-01-01', '2026-10-18', [1, 2]);
            self::assertSame(8340, (int) $allUnits['total_beban_operasional']);
            self::assertSame(-7275, (int) $allUnits['laba_rugi']);
            $balanceSheet = new \App\Services\Finance\BalanceSheetReport($db);
            self::assertSame((int) $single['laba_rugi'], $balanceSheet->summarizeAsOf('2026-10-18', 1)['laba_berjalan']);
            self::assertSame((int) $allUnits['laba_rugi'], $balanceSheet->summarizeAsOf('2026-10-18')['laba_berjalan']);
            self::assertSame(12, $db->table('jurnal')->countAllResults());
        } finally {
            $instances->setValue(null, $previous);
            $db->query('DROP TEMPORARY TABLE IF EXISTS unit');
            $db->query('DROP TEMPORARY TABLE IF EXISTS jurnal');
            $db->query('DROP TEMPORARY TABLE IF EXISTS no_akun');
        }
    }
}
