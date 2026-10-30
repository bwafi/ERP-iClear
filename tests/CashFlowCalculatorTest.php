<?php

namespace Tests;

use App\Services\Finance\CashFlowCalculator;
use App\Services\Finance\FinanceScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * KPI Net Cash Flow — sisi DATABASE.
 *
 * Regresi: periode memakai BULAN PENUH (tanggal 1 s/d akhir bulan), tanpa
 * batas cut-off periodeMulaiDate, supaya Uang Masuk sama dengan Kesehatan
 * Uang dan Tutup Kasir.
 *
 * @internal
 */
final class CashFlowCalculatorTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->schema();
    }

    protected function tearDown(): void
    {
        foreach (['db_kas_keluar', 'db_service', 'db_penjualan'] as $t) {
            $this->db->query('DROP TABLE IF EXISTS ' . $t);
        }

        parent::tearDown();
    }

    private function schema(): void
    {
        foreach (['db_kas_keluar', 'db_service', 'db_penjualan'] as $t) {
            $this->db->query('DROP TABLE IF EXISTS ' . $t);
        }

        $this->db->query('CREATE TABLE db_penjualan (
            idpenjualan INTEGER PRIMARY KEY, unit_idunit INT NULL, tanggal TEXT NULL,
            bayar_tunai REAL NULL, bayar_bank REAL NULL, kode_invoice TEXT NULL)');
        $this->db->query('CREATE TABLE db_service (
            idservice INTEGER PRIMARY KEY, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
            status_service INT NULL, bayar_tunai REAL NULL, harus_dibayar REAL NULL)');
        $this->db->query('CREATE TABLE db_kas_keluar (
            idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
            jumlah REAL NULL, idunit INT NULL, idbank TEXT NULL)');
    }

    private function penjualan(int $id, string $tanggal, int $tunai, int $bank, string $invoice): void
    {
        $this->db->query(
            'INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
             VALUES (?, 1, ?, ?, ?, ?)',
            [$id, $tanggal, $tunai, $bank, $invoice]
        );
    }

    private function kasKeluar(string $tanggal, int $jumlah): void
    {
        $this->db->query(
            'INSERT INTO db_kas_keluar (tanggal, jumlah, idunit, idbank) VALUES (?, ?, 1, NULL)',
            [$tanggal, $jumlah]
        );
    }

    public function testBulanCutoffTetapMenghitungTanggalSatu(): void
    {
        $mulai = FinanceScopeService::periodeMulaiDate();
        $bulan = (int) date('n', strtotime($mulai));
        $tahun = (int) date('Y', strtotime($mulai));
        $tglAwal = date('Y-m-01', strtotime($mulai));

        // Tanggal 1 ada sebelum periodeMulaiDate (cut-off tanggal 6).
        $this->penjualan(1, $tglAwal, 1000000, 0, 'SLL-1');

        $hasil = (new CashFlowCalculator())->calculate(1, $bulan, $tahun);

        $this->assertSame(1000000.0, $hasil['detail']['penjualan']);
        $this->assertSame('ok', $hasil['status']);
    }

    public function testBulanSebelumCutoffTetapDihitung(): void
    {
        $tgl = date('Y-m-d', strtotime(FinanceScopeService::periodeMulaiDate() . ' -3 months'));
        $bulan = (int) date('n', strtotime($tgl));
        $tahun = (int) date('Y', strtotime($tgl));

        $this->penjualan(1, $tgl, 5000000, 2000000, 'SLL-1');
        $this->kasKeluar($tgl, 1000000);

        $hasil = (new CashFlowCalculator())->calculate(1, $bulan, $tahun);

        $this->assertSame(7000000.0, $hasil['detail']['penjualan']);
        $this->assertSame(1000000.0, $hasil['detail']['kas_keluar']);
        $this->assertSame(6000000.0, $hasil['detail']['net']);
        $this->assertSame(85.71, $hasil['detail']['persen']);
        $this->assertSame(100.0, $hasil['score']);
    }
}
