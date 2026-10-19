<?php

namespace Tests\unit;

use App\Services\Finance\InventoryBaselineReport;
use PHPUnit\Framework\TestCase;

final class InventoryBaselineReportTest extends TestCase
{
    public function testSeparatesValuationGapFromExistingJournalImbalance(): void
    {
        $report = InventoryBaselineReport::summarize([
            ['idbarang' => 1, 'id_unit' => 2, 'stok_akhir' => 3, 'hpp' => 100],
            ['idbarang' => 2, 'id_unit' => 2, 'stok_akhir' => 1, 'hpp' => null],
            ['idbarang' => 3, 'id_unit' => 2, 'stok_akhir' => -1, 'hpp' => 50],
        ], [
            ['no_akun' => '1010000001', 'debet' => 200, 'kredit' => 0],
            ['no_akun' => '8010000001', 'debet' => 0, 'kredit' => 50],
        ], '1010000001');

        $this->assertSame(250, $report['stock_value_at_current_master_cost']);
        $this->assertSame(50, $report['stock_vs_journal_difference']);
        $this->assertSame([['barang_id' => 2, 'unit_id' => 2]], $report['missing_cost']);
        $this->assertSame([['barang_id' => 3, 'unit_id' => 2]], $report['negative_stock']);
        $this->assertSame(150, $report['balance_sheet']['selisih_jurnal']);
        $this->assertSame(['8010000001'], $report['balance_sheet']['akun_tidak_terklasifikasi']);
    }
}
