<?php

namespace Tests\unit;

use App\Services\Pembelian\PembelianPaymentSplit;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi normalisasi pembayaran pembelian Lunas: tunai, transfer, atau
 * keduanya, termasuk rekening ganda dan rekening kosong/"0".
 */
final class PembelianPaymentSplitTest extends CIUnitTestCase
{
    private PembelianPaymentSplit $split;

    protected function setUp(): void
    {
        parent::setUp();
        $this->split = new PembelianPaymentSplit();
    }

    public function testTunaiPenuhTanpaBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai',
            'bayar' => 'Rp 240.000',
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(240000, $hasil['cash']);
        $this->assertSame([], $hasil['banks']);
        $this->assertSame(240000, $hasil['total']);
    }

    public function testTransferSatuRekening(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bayar' => 'Rp 999.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 240.000'],
            ],
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(0, $hasil['cash'], 'tunai diabaikan saat transfer');
        $this->assertSame([['id' => '2', 'jumlah' => 240000]], $hasil['banks']);
        $this->assertSame(240000, $hasil['total']);
    }

    public function testTunaiDanDuaBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp 50.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 100.000'],
                ['id' => '3', 'jumlah' => 'Rp 90.000'],
            ],
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(50000, $hasil['cash']);
        $this->assertSame(240000, $hasil['total']);
        $this->assertCount(2, $hasil['banks']);
    }

    public function testMetodeKosongDitolak(): void
    {
        $hasil = $this->split->normalize(['bayar' => 'Rp 240.000']);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertStringContainsString('Metode pembayaran', $hasil['errors'][0]);
    }

    public function testNominalNegatifDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp -1.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 240.000'],
            ],
        ]);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertStringContainsString('tunai', $hasil['errors'][0]);
    }

    public function testBarisBankNolDilewatiDanTanpaRekeningDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 0'],
                ['id' => '', 'jumlah' => 'Rp 100.000'],
            ],
        ]);

        $this->assertStringContainsString('Rekening bank wajib dipilih', $hasil['errors'][0]);
    }

    public function testRekeningGandaDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 100.000'],
                ['id' => '2', 'jumlah' => 'Rp 140.000'],
            ],
        ]);

        $this->assertStringContainsString('lebih dari sekali', $hasil['errors'][0]);
    }

    public function testTanpaKomponenDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp 0',
            'bank' => [],
        ]);

        $this->assertStringContainsString('minimal satu', $hasil['errors'][0]);
    }

    public function testRekeningNolDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '0', 'jumlah' => 'Rp 240.000'],
            ],
        ]);

        $this->assertStringContainsString('Rekening bank wajib dipilih', $hasil['errors'][0]);
        $this->assertSame([], $hasil['banks'], 'rekening "0" tidak boleh lolos sebagai bank');
    }
}
