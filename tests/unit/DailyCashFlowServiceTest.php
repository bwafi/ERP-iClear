<?php

namespace Tests\unit;

use App\Services\Finance\DailyCashFlowService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Klasifikasi arus kas harian pada Detail Omset Harian.
 *
 * Fokus: pemisahan tunai vs transfer, pengecualian baris 'kas awal', dan
 * transfer internal yang tidak boleh jadi pendapatan/biaya.
 *
 * @internal
 */
final class DailyCashFlowServiceTest extends CIUnitTestCase
{
    private function service(): DailyCashFlowService
    {
        return new DailyCashFlowService();
    }

    public function testPenjualanTerpisahTunaiDanTransfer(): void
    {
        $result = $this->service()->assemble([
            'penjualan' => [
                (object) ['kode_invoice' => 'SLL001', 'tanggal' => '2026-10-01 09:15:00', 'bayar_tunai' => 100000, 'bayar_bank' => 250000],
            ],
            'service' => [],
            'kas_masuk' => [],
            'kas_keluar' => [],
            'ledger' => [],
        ]);

        $this->assertCount(1, $result['masuk']['tunai']);
        $this->assertCount(1, $result['masuk']['transfer']);
        $this->assertSame(100000, $result['subtotal']['masuk_tunai']);
        $this->assertSame(250000, $result['subtotal']['masuk_transfer']);
        $this->assertSame('09:15', $result['masuk']['tunai'][0]['waktu']);
        $this->assertSame('Penjualan SLL001', $result['masuk']['tunai'][0]['keterangan']);
    }

    public function testServiceTransferDiturunkanDariHarusDibayar(): void
    {
        $result = $this->service()->assemble([
            'service' => [
                (object) ['no_service' => 'SRV77', 'tanggal_selesai' => '2026-10-02 13:40:00', 'harus_dibayar' => 800000, 'bayar_tunai' => 300000],
            ],
        ]);

        $this->assertSame(300000, $result['subtotal']['masuk_tunai']);
        $this->assertSame(500000, $result['subtotal']['masuk_transfer']);
        $this->assertSame('13:40', $result['masuk']['transfer'][0]['waktu']);
    }

    public function testKasMasukDanKasKeluarDibedakanIdbank(): void
    {
        $result = $this->service()->assemble([
            'kas_masuk' => [
                (object) ['deskripsi' => 'Setoran modal', 'jumlah' => 500000, 'penerima' => 'Pemilik', 'idbank' => null, 'created_on' => '2026-10-01 08:00:00'],
                (object) ['deskripsi' => 'Transfer masuk', 'jumlah' => 700000, 'penerima' => '', 'idbank' => '1', 'created_on' => '2026-10-01 10:20:00'],
            ],
            'kas_keluar' => [
                (object) ['deskripsi' => 'Listrik', 'jumlah' => 200000, 'penerima' => null, 'idbank' => null, 'created_on' => '2026-10-01 11:00:00', 'kategori' => 'Operasional'],
                (object) ['deskripsi' => 'Bayar supplier', 'jumlah' => 400000, 'penerima' => null, 'idbank' => '1', 'created_on' => '2026-10-01 15:10:00', 'kategori' => 'Hutang'],
            ],
        ]);

        $this->assertSame(500000, $result['subtotal']['masuk_tunai']);
        $this->assertSame(700000, $result['subtotal']['masuk_transfer']);
        $this->assertSame(200000, $result['subtotal']['keluar_tunai']);
        $this->assertSame(400000, $result['subtotal']['keluar_transfer']);
        $this->assertSame(600000, $result['net']);
        $this->assertStringContainsString('Operasional', $result['keluar']['tunai'][0]['keterangan']);
    }

    public function testTransferInternalTidakMasukSubtotal(): void
    {
        $result = $this->service()->assemble([
            'ledger' => [
                (object) [
                    'jenis' => 'TRANSFER_INTERNAL', 'arah' => 'KELUAR', 'jumlah' => 1000000,
                    'keterangan' => 'Setor tunai ke bank', 'created_at' => '2026-10-01 16:00:00',
                    'nama_akun' => 'Kas Toko', 'tipe' => 'KAS',
                ],
                (object) [
                    'jenis' => 'TRANSFER_INTERNAL', 'arah' => 'MASUK', 'jumlah' => 1000000,
                    'keterangan' => 'Setor tunai ke bank', 'created_at' => '2026-10-01 16:00:00',
                    'nama_akun' => 'BCA', 'tipe' => 'BANK',
                ],
            ],
        ]);

        $this->assertCount(2, $result['transfer_internal']);
        $this->assertTrue($result['transfer_internal'][0]['transfer_internal']);
        $this->assertSame(0, $result['subtotal']['masuk_transfer']);
        $this->assertSame(0, $result['subtotal']['keluar_tunai']);
        $this->assertSame(0, $result['net']);
    }

    public function testBarisKasKeluarSudahDiLedgerTidakDihitungDuaKali(): void
    {
        // Baris kas_keluar yang sudah dipromosikan ke transaksi_kas_bank hanya
        // dihitung sekali, dari ledger.
        $result = $this->service()->assemble([
            'kas_keluar' => [
                (object) ['idkas_keluar' => 2080, 'deskripsi' => 'ATK', 'jumlah' => 50000, 'penerima' => null, 'idbank' => '1', 'created_on' => '2026-10-01 07:00:00', 'kategori' => 'Operasional'],
            ],
            'ledger' => [
                (object) [
                    'jenis' => 'PENGELUARAN', 'arah' => 'KELUAR', 'jumlah' => 50000,
                    'keterangan' => 'ATK', 'created_at' => '2026-10-01 07:00:00',
                    'nama_akun' => 'BCA', 'tipe' => 'BANK',
                    'sumber_tipe' => 'kas_keluar', 'sumber_id' => 2080,
                ],
            ],
        ]);

        // Baris legacy dibuang, ledger yang dipakai: 50.000, bukan 100.000.
        $this->assertCount(0, $result['keluar']['tunai']);
        $this->assertCount(1, $result['keluar']['transfer']);
        $this->assertSame(50000, $result['subtotal']['keluar_transfer']);
        $this->assertSame(-50000, $result['net']);
    }

    public function testBarisKasKeluarBelumDiLedgerTetapDihitung(): void
    {
        $result = $this->service()->assemble([
            'kas_keluar' => [
                (object) ['idkas_keluar' => 2100, 'deskripsi' => 'Listrik', 'jumlah' => 500000, 'penerima' => null, 'idbank' => null, 'created_on' => '2026-10-01 07:00:00', 'kategori' => 'Operasional'],
            ],
            'ledger' => [
                (object) [
                    'jenis' => 'PENGELUARAN', 'arah' => 'KELUAR', 'jumlah' => 50000,
                    'keterangan' => 'ATK', 'created_at' => '2026-10-01 07:00:00',
                    'nama_akun' => 'BCA', 'tipe' => 'BANK',
                    'sumber_tipe' => 'kas_keluar', 'sumber_id' => 2080,
                ],
            ],
        ]);

        $this->assertSame(500000, $result['subtotal']['keluar_tunai']);
        $this->assertSame(50000, $result['subtotal']['keluar_transfer']);
    }

    public function testNilaiKosongDiabaikan(): void
    {
        $result = $this->service()->assemble([
            'penjualan' => [
                (object) ['kode_invoice' => 'SLL002', 'tanggal' => '2026-10-01 10:00:00', 'bayar_tunai' => null, 'bayar_bank' => null],
            ],
            'service' => [],
            'kas_masuk' => [],
            'kas_keluar' => [],
            'ledger' => [],
        ]);

        $this->assertSame([], $result['masuk']['tunai']);
        $this->assertSame([], $result['masuk']['transfer']);
        $this->assertSame(0, $result['net']);
    }

    public function testBarisTanpaJamDiurutkanTerakhir(): void
    {
        $result = $this->service()->assemble([
            'kas_masuk' => [
                (object) ['deskripsi' => 'A', 'jumlah' => 1000, 'penerima' => '', 'idbank' => null, 'created_on' => null, 'updated_on' => null],
                (object) ['deskripsi' => 'B', 'jumlah' => 2000, 'penerima' => '', 'idbank' => null, 'created_on' => '2026-10-01 09:00:00'],
            ],
        ]);

        $this->assertSame('B', $result['masuk']['tunai'][0]['keterangan']);
        $this->assertSame('A', $result['masuk']['tunai'][1]['keterangan']);
    }
}