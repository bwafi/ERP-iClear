<?php

namespace Tests\unit;

use App\Services\Finance\TutupKasirSourceDefinition;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Integrasi DP (uang muka) service ke angka Tutup Kasir.
 *
 * Kontrak yang diuji (komposisi canonical yang dipanggil `TutupKasirClosing`):
 *  - `kasMasukDp()`     = cashMasuk()      + DP tunai diterima di tanggal itu
 *  - `transferMasukDp()` = transferMasuk() + DP transfer diterima
 *                                           - DP service yang SELESAI di tanggal itu
 *
 * DP diakui di TANGGAL PENERIMAAN (`service_dp.tanggal`) menurut `metode`,
 * jadi tidak terhitung dua kali saat service diambil. Hanya DP ber-status
 * 'aktif' yang dihitung, dan hanya service milik unit yang bersangkutan.
 *
 * DDL/DML nyata di group `tests` (prefix dikosongkan).
 *
 * @internal
 */
final class TutupKasirDpTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-01-10'; // tanggal penerimaan DP
    private const D2 = '2026-01-15'; // tanggal service A selesai/diambil
    private const D3 = '2026-01-20'; // tanggal penerimaan DP transfer service B

    private const TABEL = ['penjualan', 'service_dp', 'service'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';
    private TutupKasirSourceDefinition $src;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->hapusTabel();
        $this->buatTabel();
        $this->seed();

        $this->src = new TutupKasirSourceDefinition($this->db);
    }

    protected function tearDown(): void
    {
        $this->hapusTabel();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusTabel(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }
    }

    private function buatTabel(): void
    {
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL,
            status_service INT DEFAULT 1,
            bayar_tunai BIGINT DEFAULT 0,
            harus_dibayar BIGINT DEFAULT 0,
            tanggal_selesai DATE NULL)');

        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL,
            jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            metode VARCHAR(15) NULL,
            tanggal DATE NULL)');

        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL,
            kode_invoice VARCHAR(64) NULL,
            bayar_tunai BIGINT DEFAULT 0,
            bayar_bank BIGINT DEFAULT 0,
            tanggal DATE NULL)');
    }

    private function seed(): void
    {
        // Service A: selesai di D2, DP tunai 30.000 diterima D1.
        // harus_dibayar 100.000 (GROSS, sudah termasuk DP), bayar_tunai 40.000
        // -> sisa transfer di D2 = 60.000 (termasuk DP), transfer riil = 30.000.
        $this->db->table('service')->insert([
            'idservice'       => 1,
            'unit_idunit'     => self::UNIT,
            'status_service'  => 4,
            'bayar_tunai'     => 40000,
            'harus_dibayar'   => 100000,
            'tanggal_selesai' => self::D2,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 1,
            'jumlah'            => 30000,
            'status'            => 'aktif',
            'metode'            => 'tunai',
            'tanggal'           => self::D1,
        ]);

        // Service B: belum selesai, DP transfer 20.000 diterima D3.
        $this->db->table('service')->insert([
            'idservice'      => 2,
            'unit_idunit'    => self::UNIT,
            'status_service' => 1,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 2,
            'jumlah'            => 20000,
            'status'            => 'aktif',
            'metode'            => 'transfer',
            'tanggal'           => self::D3,
        ]);

        // Service C: DP BERSTATUS BATAL -> tidak boleh dihitung.
        $this->db->table('service')->insert([
            'idservice'      => 3,
            'unit_idunit'    => self::UNIT,
            'status_service' => 1,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 3,
            'jumlah'            => 50000,
            'status'            => 'batal',
            'metode'            => 'tunai',
            'tanggal'           => self::D1,
        ]);

        // Service D: unit LAIN -> tidak boleh dihitung oleh unit 1.
        $this->db->table('service')->insert([
            'idservice'      => 4,
            'unit_idunit'    => 2,
            'status_service' => 1,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 4,
            'jumlah'            => 9999,
            'status'            => 'aktif',
            'metode'            => 'tunai',
            'tanggal'           => self::D1,
        ]);

        // Penjualan unit 1 di D1 (bukan service): tunai 5.000, bank 7.000.
        $this->db->table('penjualan')->insert([
            'unit_idunit'  => self::UNIT,
            'kode_invoice' => 'INV-1',
            'bayar_tunai'  => 5000,
            'bayar_bank'   => 7000,
            'tanggal'      => self::D1,
        ]);
    }

    public function testDpMasukDipisahPerMetodeDanHanyaStatusAktif(): void
    {
        $this->assertSame(30000, $this->src->dpMasukTunai(self::UNIT, self::D1), 'DP tunai diterima D1.');
        $this->assertSame(0, $this->src->dpMasukTunai(self::UNIT, self::D3), 'Tidak ada DP tunai di D3.');
        $this->assertSame(0, $this->src->dpMasukTransfer(self::UNIT, self::D1), 'Tidak ada DP transfer di D1.');
        $this->assertSame(20000, $this->src->dpMasukTransfer(self::UNIT, self::D3), 'DP transfer diterima D3.');

        $this->assertSame(9999, $this->src->dpMasukTunai(2, self::D1), 'DP unit lain tidak bocor ke unit 1, tapi tetap ada di unitnya.');
    }

    public function testDpServiceSelesaiMenjumlahDpServiceYangDiambil(): void
    {
        $this->assertSame(30000, $this->src->dpServiceSelesai(self::UNIT, self::D2), 'DP service A (selesai D2).');
        $this->assertSame(0, $this->src->dpServiceSelesai(self::UNIT, self::D1), 'Tidak ada service selesai di D1.');
    }

    public function testKasMasukDpMenambahDpTunaiDiTanggalPenerimaan(): void
    {
        // D1: penjualan tunai 5.000 + DP tunai 30.000.
        $this->assertSame(5000, $this->src->cashMasuk(self::UNIT, self::D1));
        $this->assertSame(35000, $this->src->kasMasukDp(self::UNIT, self::D1));

        // D2: tidak ada DP tunai, hanya cash service 40.000.
        $this->assertSame(40000, $this->src->kasMasukDp(self::UNIT, self::D2));
    }

    public function testTransferMasukDpTidakMenghitungDpDuaKali(): void
    {
        // D1: transfer penjualan 7.000, tanpa DP transfer.
        $this->assertSame(7000, $this->src->transferMasuk(self::UNIT, self::D1));
        $this->assertSame(7000, $this->src->transferMasukDp(self::UNIT, self::D1));

        // D2: residual service A = 100.000 - 40.000 = 60.000 (termasuk DP),
        // dikurangi DP 30.000 -> transfer riil 30.000.
        $this->assertSame(60000, $this->src->transferMasuk(self::UNIT, self::D2));
        $this->assertSame(30000, $this->src->transferMasukDp(self::UNIT, self::D2));

        // D3: DP transfer service B 20.000, belum ada service selesai.
        $this->assertSame(20000, $this->src->transferMasukDp(self::UNIT, self::D3));
    }

    public function testTotalDpDanResidualTidakDobelSepanjangHari(): void
    {
        // Total kas+transfer versi DP di D1+D2 = penjualan (12.000) + service A
        // GROSS (100.000). DP 30.000 hanya muncul sekali (di D1) dan dikeluarkan
        // dari residual di D2.
        $total = $this->src->kasMasukDp(self::UNIT, self::D1)
            + $this->src->transferMasukDp(self::UNIT, self::D1)
            + $this->src->kasMasukDp(self::UNIT, self::D2)
            + $this->src->transferMasukDp(self::UNIT, self::D2);

        $this->assertSame(112000, $total);
    }
}
