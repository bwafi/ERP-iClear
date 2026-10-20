<?php

namespace Tests;

use App\Services\Finance\MutasiTimelinessCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class MutasiTimelinessCalculatorTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_hutang_piutang');
        $this->db->query('DROP TABLE IF EXISTS db_pembayaran_hutang_piutang');
        $this->db->query('CREATE TABLE db_hutang_piutang (
            id INT PRIMARY KEY AUTO_INCREMENT,
            kode VARCHAR(50) NULL,
            jenis VARCHAR(20) NULL,
            sumber_tipe VARCHAR(30) NULL,
            tanggal DATE NULL,
            jatuh_tempo DATE NULL,
            total DOUBLE NULL,
            total_dibayar DOUBLE NULL,
            sisa DOUBLE NULL,
            status VARCHAR(30) NULL,
            unit_id INT NULL,
            deleted TINYINT DEFAULT 0,
            scope VARCHAR(20) DEFAULT \'active\'
        )');
        $this->db->query('CREATE TABLE db_pembayaran_hutang_piutang (
            id INT PRIMARY KEY AUTO_INCREMENT,
            hutang_piutang_id INT NULL,
            tanggal_bayar DATE NULL,
            jumlah_bayar DOUBLE NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_hutang_piutang');
        $this->db->query('DROP TABLE IF EXISTS db_pembayaran_hutang_piutang');

        parent::tearDown();
    }

    public function testHitungKetepatanMutasiAntarUnit(): void
    {
        $rows = [
            [1, 'H-1', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 100, 0, 'lunas', 1, 0, 'active'],
            [2, 'H-2', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 0, 'active'],
            [3, 'H-3', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-31', 100, 0, 100, 'belum_lunas', 1, 0, 'active'],
            [4, 'H-4', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 2, 0, 'active'],
            [5, 'P-1', 'piutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 0, 'active'],
            [6, 'H-5', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 0, 'legacy'],
            [7, 'H-6', 'hutang', 'mutasi_unit', '2026-10-05', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 0, 'active'],
            [8, 'H-7', 'hutang', 'pembelian', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 0, 'active'],
            [9, 'H-8', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 100, 'belum_lunas', 1, 1, 'active'],
            [10, 'H-9', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 0, 0, 'lunas', 1, 0, 'active'],
        ];

        foreach ($rows as $r) {
            $this->db->table('hutang_piutang')->insert([
                'id' => $r[0],
                'kode' => $r[1],
                'jenis' => $r[2],
                'sumber_tipe' => $r[3],
                'tanggal' => $r[4],
                'jatuh_tempo' => $r[5],
                'total' => $r[6],
                'total_dibayar' => $r[7],
                'sisa' => $r[8],
                'status' => $r[9],
                'unit_id' => $r[10],
                'deleted' => $r[11],
                'scope' => $r[12],
            ]);
        }

        $this->db->table('pembayaran_hutang_piutang')->insert([
            'hutang_piutang_id' => 1,
            'tanggal_bayar' => '2026-10-09',
            'jumlah_bayar' => 100,
        ]);

        $result = (new MutasiTimelinessCalculator())->calculate(1, 10, 2026);

        $this->assertSame(50.0, $result['score']);
        $this->assertSame('ok', $result['status']);
        $this->assertSame(1, $result['detail']['tepat']);
        $this->assertSame(1, $result['detail']['terlambat']);
        $this->assertSame(1, $result['detail']['open']);
        $this->assertSame(1, $result['detail']['tanpa_bukti']);
        $this->assertSame(2, $result['detail']['dinilai']);
    }

    public function testTanpaMutasiJatuhTempoMenghasilkanNull(): void
    {
        $result = (new MutasiTimelinessCalculator())->calculate(1, 10, 2026);

        $this->assertNull($result['score']);
        $this->assertSame('data_kosong', $result['status']);
        $this->assertSame(0, $result['detail']['dinilai']);
    }
}
