<?php

namespace Tests\unit;

use App\Services\Finance\SupplierCreditReconciliation;
use PHPUnit\Framework\TestCase;

final class SupplierCreditReconciliationTest extends TestCase
{
    public function testApprovedCreditMatchesAllocationAndJournal(): void
    {
        $summary = SupplierCreditReconciliation::summarize([
            ['id' => 1, 'status' => 'disetujui', 'nominal' => 200000, 'sisa_kredit' => 50000,
                'akun_debet' => '2010000000', 'akun_piutang' => '1010000000', 'akun_kredit' => '5010000000'],
        ], [
            ['kredit_id' => 1, 'nominal' => 150000],
        ], [
            ['id_referensi' => 1, 'no_akun' => '1010000000', 'debet' => 200000, 'kredit' => 0],
            ['id_referensi' => 1, 'no_akun' => '5010000000', 'debet' => 0, 'kredit' => 200000],
            ['id_referensi' => 1, 'no_akun' => '2010000000', 'debet' => 150000, 'kredit' => 0],
            ['id_referensi' => 1, 'no_akun' => '1010000000', 'debet' => 0, 'kredit' => 150000],
        ]);

        $this->assertSame(50000, $summary['sisa_kredit']);
        $this->assertSame(0, $summary['alokasi_tidak_sesuai']);
        $this->assertSame(0, $summary['jurnal_tidak_sesuai']);
        $this->assertSame(0, $summary['referensi_yatim']);
    }

    public function testMissingAllocationAndDoubleJournalAreDetected(): void
    {
        $summary = SupplierCreditReconciliation::summarize([
            ['id' => 1, 'status' => 'disetujui', 'nominal' => 200000, 'sisa_kredit' => 0,
                'akun_debet' => '2010000000', 'akun_piutang' => '1010000000', 'akun_kredit' => '5010000000'],
        ], [], [
            ['id_referensi' => 1, 'no_akun' => '1010000000', 'debet' => 400000, 'kredit' => 0],
        ]);

        $this->assertSame(1, $summary['alokasi_tidak_sesuai']);
        $this->assertSame(1, $summary['jurnal_tidak_sesuai']);
    }
}
