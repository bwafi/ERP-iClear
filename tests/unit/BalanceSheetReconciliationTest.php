<?php

namespace Tests\unit;

use App\Services\Finance\BalanceSheetReconciliation;
use PHPUnit\Framework\TestCase;

final class BalanceSheetReconciliationTest extends TestCase
{
    public function testBalancedJournalSeparatesCurrentProfit(): void
    {
        $summary = BalanceSheetReconciliation::summarize([
            ['no_akun' => '1010101000', 'debet' => 1000, 'kredit' => 0],
            ['no_akun' => '2010101000', 'debet' => 0, 'kredit' => 300],
            ['no_akun' => '3010101000', 'debet' => 0, 'kredit' => 500],
            ['no_akun' => '4010101000', 'debet' => 0, 'kredit' => 300],
            ['no_akun' => '5010101000', 'debet' => 100, 'kredit' => 0],
        ]);

        $this->assertSame(1000, $summary['aset']);
        $this->assertSame(300, $summary['kewajiban']);
        $this->assertSame(500, $summary['ekuitas']);
        $this->assertSame(200, $summary['laba_berjalan']);
        $this->assertSame(0, $summary['selisih']);
        $this->assertSame(0, $summary['selisih_jurnal']);
    }

    public function testUnclassifiedAccountsRemainVisible(): void
    {
        $summary = BalanceSheetReconciliation::summarize([
            ['no_akun' => '8010101000', 'debet' => 0, 'kredit' => 100],
        ]);

        $this->assertSame(['8010101000'], $summary['akun_tidak_terklasifikasi']);
        $this->assertSame(-100, $summary['selisih_jurnal']);
    }

    public function testAsOfSummarySeparatesCurrentYearProfitFromBalanceAccounts(): void
    {
        $summary = BalanceSheetReconciliation::summarizeAsOf([
            ['no_akun' => '1010101000', 'debet' => 1200, 'kredit' => 0],
            ['no_akun' => '2010101000', 'debet' => 0, 'kredit' => 200],
            ['no_akun' => '3010101000', 'debet' => 0, 'kredit' => 500],
        ], [
            ['no_akun' => '4010101000', 'debet' => 0, 'kredit' => 600],
            ['no_akun' => '5010101000', 'debet' => 100, 'kredit' => 0],
        ], [
            ['no_akun' => '1010101000', 'debet' => 1200, 'kredit' => 0],
            ['no_akun' => '2010101000', 'debet' => 0, 'kredit' => 200],
            ['no_akun' => '3010101000', 'debet' => 0, 'kredit' => 500],
            ['no_akun' => '4010101000', 'debet' => 0, 'kredit' => 1500],
            ['no_akun' => '5010101000', 'debet' => 900, 'kredit' => 0],
        ]);

        $this->assertSame(1200, $summary['aset']);
        $this->assertSame(200, $summary['kewajiban']);
        $this->assertSame(500, $summary['ekuitas']);
        $this->assertSame(500, $summary['laba_berjalan']);
        $this->assertSame(0, $summary['selisih']);
    }

    public function testSourceExceptionsFindsUnbalancedReferencesAndUnreferencedRows(): void
    {
        $result = BalanceSheetReconciliation::sourceExceptions([
            ['id_unit' => 2, 'tabel_referensi' => 'pembelian', 'id_referensi' => '15', 'jumlah_baris' => 3, 'debet' => 500, 'kredit' => 400],
            ['id_unit' => 2, 'tabel_referensi' => null, 'id_referensi' => null, 'jumlah_baris' => 2, 'debet' => 100, 'kredit' => 0],
            ['id_unit' => 2, 'tabel_referensi' => 'service', 'id_referensi' => '8', 'jumlah_baris' => 2, 'debet' => 200, 'kredit' => 200],
        ]);

        $this->assertCount(1, $result['tidak_seimbang']);
        $this->assertSame(100, $result['tidak_seimbang'][0]['selisih']);
        $this->assertSame(2, $result['baris_tanpa_referensi']);
    }

    public function testInvalidAccountsIncludesMalformedCodesAndMissingMasterRows(): void
    {
        $invalid = BalanceSheetReconciliation::invalidAccounts([
            ['no_akun' => '1010101000', 'akun_master' => '1010101000', 'jumlah_baris' => 1],
            ['no_akun' => 'ABC', 'akun_master' => 'ABC', 'jumlah_baris' => 2],
            ['no_akun' => '1099999999', 'akun_master' => null, 'jumlah_baris' => 3],
            ['no_akun' => null, 'akun_master' => null, 'jumlah_baris' => 4],
        ]);

        $this->assertCount(3, $invalid);
        $this->assertSame(['ABC', '1099999999', null], array_column($invalid, 'no_akun'));
    }

    public function testGapBridgeExplainsLegacyProfitAndUnclassifiedDebitWithoutAdjustingBooks(): void
    {
        $summary = BalanceSheetReconciliation::summarizeAsOf([
            ['no_akun' => '1010100000', 'debet' => 1208409817, 'kredit' => 0],
            ['no_akun' => '2010100000', 'debet' => 0, 'kredit' => 9007903],
            ['no_akun' => '3010100000', 'debet' => 1000000, 'kredit' => 0],
        ], [
            ['no_akun' => '6010100000', 'debet' => 871389450, 'kredit' => 0],
        ], [
            ['no_akun' => '1010100000', 'debet' => 1208409817, 'kredit' => 0],
            ['no_akun' => '2010100000', 'debet' => 0, 'kredit' => 9007903],
            ['no_akun' => '3010100000', 'debet' => 1000000, 'kredit' => 0],
            ['no_akun' => '6010100000', 'debet' => 871389450, 'kredit' => 0],
            ['no_akun' => '4010100000', 'debet' => 0, 'kredit' => 95722000],
            ['no_akun' => '', 'debet' => 23500, 'kredit' => 0],
        ]);
        $this->assertSame(1976092864, $summary['selisih_jurnal']);
        $this->assertSame(23500, $summary['saldo_akun_tidak_terklasifikasi']);
        $this->assertSame(95722000, $summary['laba_sebelum_tahun_berjalan']);
        $this->assertSame(2071791364, $summary['selisih']);
        $this->assertSame(-1000000, $summary['ekuitas']);
        $this->assertSame($summary['selisih'], $summary['selisih_jurnal']
            - $summary['saldo_akun_tidak_terklasifikasi'] + $summary['laba_sebelum_tahun_berjalan']);
    }
}
