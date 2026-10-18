<?php

namespace Tests\unit;

use App\Services\Finance\WarrantyJournalReconciliation;
use PHPUnit\Framework\TestCase;

final class WarrantyJournalReconciliationTest extends TestCase
{
    public function testMatchedMissingAndOrphanJournals(): void
    {
        $summary = WarrantyJournalReconciliation::summarize([
            ['id' => 1, 'jumlah' => 2, 'hpp_satuan' => 230000],
            ['id' => 2, 'jumlah' => 1, 'hpp_satuan' => 100000],
        ], [
            ['id_referensi' => 1, 'debet' => 460000, 'kredit' => 0],
            ['id_referensi' => 1, 'debet' => 0, 'kredit' => 460000],
            ['id_referensi' => 99, 'debet' => 1000, 'kredit' => 0],
        ]);

        $this->assertSame(2, $summary['jumlah_mutasi']);
        $this->assertSame(560000, $summary['hpp_total']);
        $this->assertSame(1, $summary['tanpa_jurnal']);
        $this->assertSame(0, $summary['tidak_sesuai']);
        $this->assertSame(1, $summary['jurnal_tanpa_mutasi']);
    }

    public function testDoublePostingIsDetected(): void
    {
        $summary = WarrantyJournalReconciliation::summarize([
            ['id' => 1, 'jumlah' => 1, 'hpp_satuan' => 100],
        ], [
            ['id_referensi' => 1, 'debet' => 100, 'kredit' => 0],
            ['id_referensi' => 1, 'debet' => 0, 'kredit' => 100],
            ['id_referensi' => 1, 'debet' => 100, 'kredit' => 0],
            ['id_referensi' => 1, 'debet' => 0, 'kredit' => 100],
        ]);

        $this->assertSame(1, $summary['tidak_sesuai']);
    }
}
