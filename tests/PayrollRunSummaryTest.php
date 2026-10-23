<?php

namespace Tests;

use App\Services\Payroll\PayrollRunSummary;
use PHPUnit\Framework\TestCase;

class PayrollRunSummaryTest extends TestCase
{
    private function row(array $values): object
    {
        return (object) $values;
    }

    public function testBucketsDoNotOverlapAndPaidWins(): void
    {
        $rows = [
            $this->row(['pegawai_id' => 1, 'status' => 'rencana', 'sumber' => 'auto', 'total' => 100, 'due_date' => '2026-10-31']),
            $this->row(['pegawai_id' => 2, 'status' => 'rencana', 'sumber' => 'manual', 'total' => 200, 'due_date' => '2026-10-31']),
            $this->row(['pegawai_id' => 3, 'status' => 'rencana', 'sumber' => null, 'total' => 300, 'due_date' => '2026-10-31']),
            $this->row(['pegawai_id' => 4, 'status' => 'dibayar', 'sumber' => 'auto', 'total' => 400, 'due_date' => '2026-10-31']),
            $this->row(['pegawai_id' => 4, 'status' => 'dibayar', 'sumber' => 'manual', 'total' => 500, 'due_date' => '2026-10-31']),
        ];

        $summary = PayrollRunSummary::build($rows, [], '2026-10-23');
        $run = $summary['run'];

        $this->assertSame(1, $run['auto']['count']);
        $this->assertSame(100, $run['auto']['total']);
        $this->assertSame(2, $run['manual']['count']);
        $this->assertSame(500, $run['manual']['total']);
        $this->assertSame(2, $run['paid']['count']);
        $this->assertSame(900, $run['paid']['total']);
        $this->assertSame(5, $summary['totals']['count']);
        $this->assertSame(1500, $summary['totals']['total']);
        $this->assertSame(4, $summary['totals']['employees']);
    }

    public function testOverdueOnlyCountsUnpaidRows(): void
    {
        $rows = [
            $this->row(['pegawai_id' => 1, 'status' => 'rencana', 'sumber' => 'auto', 'total' => 100, 'due_date' => '2026-10-20']),
            $this->row(['pegawai_id' => 2, 'status' => 'dibayar', 'sumber' => 'auto', 'total' => 200, 'due_date' => '2026-10-20']),
            $this->row(['pegawai_id' => 3, 'status' => 'rencana', 'sumber' => 'manual', 'total' => 300, 'due_date' => '2026-10-24']),
        ];

        $totals = PayrollRunSummary::build($rows, [], '2026-10-23')['totals'];

        $this->assertSame(1, $totals['overdue_count']);
        $this->assertSame(100, $totals['overdue_total']);
    }

    public function testBonusIsSummedSeparately(): void
    {
        $bonus = [
            $this->row(['jumlah' => 75000]),
            $this->row(['jumlah' => 125000]),
        ];

        $summary = PayrollRunSummary::build([], $bonus, '2026-10-23');

        $this->assertSame(2, $summary['run']['bonus']['count']);
        $this->assertSame(200000, $summary['run']['bonus']['total']);
        $this->assertSame(0, $summary['totals']['count']);
    }
}
