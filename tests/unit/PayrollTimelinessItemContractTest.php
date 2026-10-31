<?php

namespace Tests\unit;

use App\Services\Finance\PayrollTimelinessCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi kontrak item PayrollTimelinessCalculator.
 *
 * Bug: view dashboard/dashboard_finance.php membaca $it['period'] pada tabel
 * "Rincian Payroll", tetapi calculator tidak pernah mengembalikan key 'period',
 * sehingga halaman /dashboard/finance melempar "Undefined index: period" begitu
 * ada baris finance_payroll pada periode yang dievaluasi.
 *
 * @internal
 */
final class PayrollTimelinessItemContractTest extends CIUnitTestCase
{
    private const KOLOM_WAJIB = ['id', 'period', 'due_date', 'paid_date', 'total', 'status', 'klasifikasi'];

    /** ID akun uji untuk pegawai Finance (jabatan 0). */
    private const FINANCE_ID = 900001;

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->db->query('DROP TABLE IF EXISTS finance_payroll');
        $this->db->query('CREATE TABLE finance_payroll (
                id INT PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL,
                pegawai_id INT NULL,
                due_date DATE NULL,
                paid_date DATE NULL,
                status VARCHAR(20) NULL,
                total BIGINT NULL,
                notes TEXT NULL)');

        $this->db->table('finance_payroll')->insertBatch([
            ['unit_id' => 1, 'due_date' => '2026-10-31', 'paid_date' => null, 'status' => 'rencana', 'total' => 1_500_000],
            ['unit_id' => 1, 'due_date' => '2026-10-10', 'paid_date' => '2026-10-09', 'status' => 'dibayar', 'total' => 2_000_000],
            ['unit_id' => 2, 'due_date' => '2026-11-30', 'paid_date' => null, 'status' => 'rencana', 'total' => 9_999_999],
        ]);

        $this->db->table('akun')->where('ID_AKUN', self::FINANCE_ID)->delete();
        $this->db->table('akun')->insert([
            'ID_AKUN' => self::FINANCE_ID,
            'ID_UNIT' => 1,
            'ID_JABATAN' => 0,
            'NAMA_AKUN' => 'Finance Test',
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('akun')->where('ID_AKUN', self::FINANCE_ID)->delete();
        $this->db->query('DROP TABLE IF EXISTS finance_payroll');
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testSetiapItemPunyaKeyYangDibacaView(): void
    {
        $result = (new PayrollTimelinessCalculator())->calculate(1, 10, 2026);
        $items  = $result['detail']['items'];

        $this->assertCount(2, $items, 'Hanya baris due_date dalam bulan 2026-10 milik unit 1.');

        foreach ($items as $item) {
            foreach (self::KOLOM_WAJIB as $kolom) {
                $this->assertArrayHasKey(
                    $kolom,
                    $item,
                    "Item payroll wajib punya key '{$kolom}' (dibaca view dashboard_finance)."
                );
            }
        }
    }

    public function testPeriodDiturunkanDariBulanJatuhTempo(): void
    {
        $items = (new PayrollTimelinessCalculator())->calculate(1, 10, 2026)['detail']['items'];

        $this->assertSame(['2026-10', '2026-10'], array_column($items, 'period'));
    }

    public function testSkorKetepatanTetapTerhitung(): void
    {
        $result = (new PayrollTimelinessCalculator())->calculate(1, 10, 2026);

        // 1 tepat (10 Okt dibayar 9 Okt), 1 open (31 Okt belum jatuh tempo) →
        // dinilai = 1 → skor 100.
        $this->assertSame('ok', $result['status']);
        $this->assertSame(100.0, (float) $result['score']);
        $this->assertSame(1, (int) $result['detail']['tepat']);
        $this->assertSame(1, (int) $result['detail']['open']);
    }

    public function testUnitTanpaBarisMengembalikanItemsKosong(): void
    {
        $result = (new PayrollTimelinessCalculator())->calculate(3, 10, 2026);

        $this->assertSame('data_kosong', $result['status']);
        $this->assertNull($result['score']);
        $this->assertSame([], $result['detail']['items']);
    }

    public function testBarisPayrollFinanceDikecualikanDariPenilaian(): void
    {
        // Due date sudah lewat dan belum dibayar: kalau ikut dinilai, skor
        // akan turun. Baris Finance harus dikecualikan.
        $this->db->table('finance_payroll')->insert([
            'unit_id'   => 1,
            'pegawai_id' => self::FINANCE_ID,
            'due_date'  => '2026-10-05',
            'paid_date' => null,
            'status'    => 'rencana',
            'total'     => 999_000,
        ]);

        $result = (new PayrollTimelinessCalculator())->calculate(1, 10, 2026);
        $items  = $result['detail']['items'];

        $this->assertCount(3, $items, 'Baris Finance tetap tampil di rincian.');
        $this->assertSame(
            ['Dikecualikan (Finance)'],
            array_values(array_column(
                array_filter($items, static fn ($item) => $item['klasifikasi'] === 'Dikecualikan (Finance)'),
                'klasifikasi'
            ))
        );

        $this->assertSame(1, (int) $result['detail']['tepat']);
        $this->assertSame(0, (int) $result['detail']['terlambat']);
        $this->assertSame(1, (int) $result['detail']['open']);
        $this->assertSame(100.0, (float) $result['score']);
        $this->assertSame(4_499_000.0, (float) $result['detail']['total_payroll']);
    }
}
