<?php

namespace Tests\unit;

use App\Services\Sales\PenjualanPaymentSplit;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Normalisasi pembayaran penjualan: tunai, transfer, atau keduanya.
 *
 * Service ini adalah satu-satunya tempat aturan split pembayaran. Controller
 * hanya meneruskan hasilnya ke insert penjualan/pembayaran_bank/jurnal.
 *
 * @internal
 */
final class PenjualanPaymentSplitTest extends CIUnitTestCase
{
    private PenjualanPaymentSplit $split;

    protected function setUp(): void
    {
        parent::setUp();
        $this->split = new PenjualanPaymentSplit();
    }

    public function testSplitTunaiTransferMenjumlahkanTunaiDanBanyakBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp 150.000',
            'bank' => [
                ['id' => '1', 'jumlah' => 'Rp 250.000'],
                ['id' => '2', 'jumlah' => 'Rp 100.000'],
            ],
        ], ['1', '2']);

        $this->assertSame(500000.0, $hasil['total']);
        $this->assertSame(150000.0, $hasil['cash']);
        $this->assertCount(2, $hasil['banks']);
        $this->assertSame('1', $hasil['banks'][0]['id']);
        $this->assertSame(250000.0, $hasil['banks'][0]['jumlah']);
        $this->assertSame('2', $hasil['banks'][1]['id']);
        $this->assertSame(100000.0, $hasil['banks'][1]['jumlah']);
        $this->assertSame([], $hasil['errors']);
    }

    public function testMetodeTunaiMengabaikanBarisBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai',
            'bayar' => 'Rp 100.000',
            'bank' => [['id' => '1', 'jumlah' => 'Rp 50.000']],
        ], ['1']);

        $this->assertSame(100000.0, $hasil['total']);
        $this->assertSame(100000.0, $hasil['cash']);
        $this->assertSame([], $hasil['banks']);
        $this->assertSame([], $hasil['errors']);
    }

    public function testMetodeTransferMengabaikanTunai(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bayar' => 'Rp 100.000',
            'bank' => [['id' => '1', 'jumlah' => 'Rp 250.000']],
        ], ['1']);

        $this->assertSame(250000.0, $hasil['total']);
        $this->assertSame(0.0, $hasil['cash']);
        $this->assertCount(1, $hasil['banks']);
        $this->assertSame([], $hasil['errors']);
    }

    public function testBarisBankNolDiabaikan(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '', 'jumlah' => 'Rp 0'],
                ['id' => '1', 'jumlah' => ''],
            ],
        ], ['1']);

        $this->assertSame([], $hasil['banks']);
        $this->assertSame(0.0, $hasil['total']);
        $this->assertSame([], $hasil['errors']);
    }

    public function testNominalBankTanpaRekeningDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [['id' => '', 'jumlah' => 'Rp 50.000']],
        ], ['1']);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertSame([], $hasil['banks']);
    }

    public function testRekeningDiLuarScopeDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [['id' => '99', 'jumlah' => 'Rp 50.000']],
        ], ['1']);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertSame([], $hasil['banks']);
    }

    public function testBankDitolakSaatUnitTidakPunyaRekening(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [['id' => '1', 'jumlah' => 'Rp 50.000']],
        ], []);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertSame([], $hasil['banks']);
    }

    public function testNominalNegatifDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai',
            'bayar' => '-5000',
        ], []);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertSame(0.0, $hasil['cash']);
    }

    public function testNominalBankNegatifDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [['id' => '1', 'jumlah' => '-50.000']],
        ], ['1']);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertSame([], $hasil['banks']);
    }

    public function testMetodeKosongMemakaiPerilakuLama(): void
    {
        $hasil = $this->split->normalize([
            'bayar' => 'Rp 10.000',
            'bank' => [['id' => '1', 'jumlah' => 'Rp 20.000']],
        ], ['1']);

        $this->assertSame(30000.0, $hasil['total']);
        $this->assertSame(10000.0, $hasil['cash']);
        $this->assertCount(1, $hasil['banks']);
        $this->assertSame([], $hasil['errors']);
    }

    public function testBarisBankBukanArrayDiabaikan(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => '10.000',
            'bank' => [null, 'bukan-baris', ['id' => '1', 'jumlah' => '25.000']],
        ], ['1']);

        $this->assertSame(35000.0, $hasil['total']);
        $this->assertCount(1, $hasil['banks']);
    }

    public function testFormatNominalUmumDibacaSebagaiRupiah(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => '150000.00',
            'bank' => [['id' => '1', 'jumlah' => '1.234.567']],
        ], ['1']);

        $this->assertSame(150000.0, $hasil['cash']);
        $this->assertSame(1234567.0, $hasil['banks'][0]['jumlah']);
        $this->assertSame(1384567.0, $hasil['total']);
    }

    /**
     * Penjualan tidak boleh lagi mempercayai hidden `hutang` dari klien dan
     * daftar rekening harus mengikuti scope unit.
     */
    public function testControllerMemakaiServiceDanScopeBankUnit(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Penjualan.php');

        $this->assertStringContainsString('PenjualanPaymentSplit', $source);
        $this->assertStringContainsString('getBankDalamScopeUnit', $source);
        $this->assertStringNotContainsString("getPost('hutang')", $source);
    }
}
