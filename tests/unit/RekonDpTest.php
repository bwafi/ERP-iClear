<?php

namespace Tests\unit;

use App\Services\Finance\RekonDailyCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * DP (uang muka) service HARUS ikut terhitung di Rekonsiliasi Harian, sama
 * seperti Tutup Kasir. Rekon membaca komponen DP dari
 * `TutupKasirSourceDefinition`, jadi kedua halaman tidak boleh berbeda.
 *
 * Diuji: `erpValues()` per hari (yang jadi dasar selisih saat save) DAN
 * `erpValuesRange()` (yang dipakai panel daftar) memberi angka identik dan
 * sama-sama memuat DP.
 *
 * DDL/DML nyata di group `tests` (prefix dikosongkan).
 *
 * @internal
 */
final class RekonDpTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-01-10'; // DP tunai diterima
    private const D2 = '2026-01-15'; // service A selesai
    private const D3 = '2026-01-20'; // DP transfer diterima

    private const TABEL = ['penjualan', 'kas_keluar', 'service_dp', 'service'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';
    private RekonDailyCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->hapusTabel();
        $this->buatTabel();
        $this->seed();

        $this->calc = new RekonDailyCalculator();
    }

    protected function tearDown(): void
    {
        $this->hapusTabel();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusTabel(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
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
            refund_idkas_keluar INT NULL,
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

        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            idunit INT NULL,
            idbank VARCHAR(20) NULL,
            jumlah BIGINT DEFAULT 0,
            tanggal DATE NULL)');
    }

    private function seed(): void
    {
        // Service A selesai D2, DP tunai 30.000 diterima D1.
        // harus_dibayar 100.000 (gross, sudah termasuk DP), bayar_tunai 40.000
        // -> residual D2 = 60.000 (termasuk DP), transfer riil = 30.000.
        $this->db->table('service')->insert([
            'idservice' => 1, 'unit_idunit' => self::UNIT, 'status_service' => 4,
            'bayar_tunai' => 40000, 'harus_dibayar' => 100000, 'tanggal_selesai' => self::D2,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 1, 'jumlah' => 30000, 'status' => 'aktif',
            'metode' => 'tunai', 'tanggal' => self::D1,
        ]);

        // Service B belum selesai, DP transfer 20.000 diterima D3.
        $this->db->table('service')->insert([
            'idservice' => 2, 'unit_idunit' => self::UNIT, 'status_service' => 1,
        ]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 2, 'jumlah' => 20000, 'status' => 'aktif',
            'metode' => 'transfer', 'tanggal' => self::D3,
        ]);

        $this->db->table('penjualan')->insert([
            'unit_idunit' => self::UNIT, 'kode_invoice' => 'INV-1',
            'bayar_tunai' => 5000, 'bayar_bank' => 7000, 'tanggal' => self::D1,
        ]);

        $this->db->table('kas_keluar')->insert([
            'idunit' => self::UNIT, 'idbank' => null, 'jumlah' => 2000, 'tanggal' => self::D2,
        ]);
    }

    public function testErpValuesMemuatDpSamaSepertiTutupKasir(): void
    {
        // D1: penjualan tunai 5.000 + DP tunai 30.000 diterima hari ini.
        $d1 = $this->calc->erpValues(self::UNIT, self::D1);
        $this->assertSame(35000, $d1['cash_masuk'], 'DP tunai masuk di tanggal penerimaan.');
        $this->assertSame(7000, $d1['transfer_masuk'], 'Transfer penjualan 7.000.');

        // D2: cash service 40.000; transfer = residual 60.000 - DP 30.000.
        $d2 = $this->calc->erpValues(self::UNIT, self::D2);
        $this->assertSame(40000, $d2['cash_masuk']);
        $this->assertSame(30000, $d2['transfer_masuk'], 'DP service selesai tidak dihitung dua kali.');
        $this->assertSame(2000, $d2['kas_keluar']);

        // D3: DP transfer service B diakui walau service belum selesai.
        $d3 = $this->calc->erpValues(self::UNIT, self::D3);
        $this->assertSame(20000, $d3['transfer_masuk'], 'DP transfer masuk di tanggal penerimaan.');
    }

    public function testErpValuesRangeMemuatDpDanSamaDenganPerHari(): void
    {
        $range = $this->calc->erpValuesRange(self::UNIT, self::D1, self::D3);

        foreach ([self::D1, self::D2, self::D3] as $d) {
            $this->assertSame(
                $this->calc->erpValues(self::UNIT, $d),
                $range[$d] ?? [],
                "Rentang harus identik dengan per-hari pada {$d}."
            );
        }

        $this->assertSame(35000, $range[self::D1]['cash_masuk'], 'DP tunai ada di rentang.');
        $this->assertSame(30000, $range[self::D2]['transfer_masuk']);
        $this->assertSame(20000, $range[self::D3]['transfer_masuk'], 'DP transfer ada di rentang.');
    }
}
