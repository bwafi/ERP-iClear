<?php

namespace Tests;

use App\Services\Finance\HutangPiutangService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Projection hutang supplier harus memakai nilai transaksi, bukan nominal
 * yang sudah dibayar. Regresi HP-01/PI-05.
 */
final class HutangPiutangProjectionTotalTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    private const TABEL = ['suplier', 'pembelian', 'hutang_piutang'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->buatSkema();
        $this->db->table('suplier')->insert(['id_suplier' => 1, 'nama_suplier' => 'Supplier']);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->close();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier TEXT NULL)');
        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY AUTO_INCREMENT,
                no_nota_supplier TEXT NULL, tanggal_masuk DATETIME NULL, unit_idunit INT NULL,
                suplier_id_suplier INT NULL, sisa INT NULL, status TEXT NULL,
                total_transaksi INT NULL, total_bayar INT NULL, bayar INT NULL,
                jatuh_tempo TEXT NULL)');
        $q('CREATE TABLE hutang_piutang (id INT PRIMARY KEY AUTO_INCREMENT,
                kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                nama_pihak TEXT NULL, tanggal TEXT NULL, jatuh_tempo TEXT NULL,
                uraian TEXT NULL, total INT NULL, total_dibayar INT NULL, sisa INT NULL,
                status TEXT NULL, unit_id INT NULL, input_by INT NULL, deleted INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
    }

    private function buatPembelian(int $totalTransaksi, int $dibayar, int $sisa): int
    {
        $this->db->table('pembelian')->insert([
            'no_nota_supplier' => 'PCR1-1',
            'tanggal_masuk' => '2026-11-10 10:00:00',
            'unit_idunit' => 1,
            'suplier_id_suplier' => 1,
            'sisa' => $sisa,
            'status' => $sisa > 0 ? 'Belum Lunas' : 'Lunas',
            'total_transaksi' => $totalTransaksi,
            'total_bayar' => $dibayar,
            'bayar' => $dibayar,
            'jatuh_tempo' => '2026-11-20',
        ]);

        return (int) $this->db->insertID();
    }

    private function projection(int $sumberId): object
    {
        return $this->db->table('hutang_piutang')
            ->where('sumber_tipe', HutangPiutangService::SUMBER_PEMBELIAN)
            ->where('sumber_id', $sumberId)
            ->get()->getRow();
    }

    public function testPembelianSebagianMemakaiTotalTransaksi(): void
    {
        $id = $this->buatPembelian(1000000, 400000, 600000);

        (new HutangPiutangService())->syncFromPembelian($id);

        $row = $this->projection($id);
        $this->assertSame(1000000, (int) $row->total, 'total harus nilai transaksi');
        $this->assertSame(400000, (int) $row->total_dibayar, 'dibayar harus nominal yang sudah dibayar');
        $this->assertSame(600000, (int) $row->sisa);
        $this->assertSame(HutangPiutangService::STATUS_SEBAGIAN, $row->status);
    }

    public function testPembelianLunasMemakaiTotalTransaksi(): void
    {
        $id = $this->buatPembelian(500000, 500000, 0);

        (new HutangPiutangService())->syncFromPembelian($id);

        $row = $this->projection($id);
        $this->assertSame(500000, (int) $row->total);
        $this->assertSame(500000, (int) $row->total_dibayar);
        $this->assertSame(0, (int) $row->sisa);
        $this->assertSame(HutangPiutangService::STATUS_LUNAS, $row->status);
    }

    public function testRefreshProjectionsMemperbaikiTotalLama(): void
    {
        $id = $this->buatPembelian(1000000, 400000, 600000);

        $this->db->table('hutang_piutang')->insert([
            'kode' => 'HUT-PB' . $id,
            'jenis' => 'hutang',
            'sumber_tipe' => HutangPiutangService::SUMBER_PEMBELIAN,
            'sumber_id' => $id,
            'is_projection' => 1,
            'pihak_tipe' => 'suplier',
            'pihak_id' => 1,
            'nama_pihak' => 'Supplier',
            'tanggal' => '2026-11-10 10:00:00',
            'jatuh_tempo' => '2026-11-20',
            'uraian' => 'PCR1-1',
            'total' => 400000,
            'total_dibayar' => 400000,
            'sisa' => 600000,
            'status' => HutangPiutangService::STATUS_SEBAGIAN,
            'unit_id' => 1,
            'deleted' => 0,
        ]);

        (new HutangPiutangService())->refreshProjections();

        $row = $this->projection($id);
        $this->assertSame(1000000, (int) $row->total, 'baris projection lama harus dikoreksi');
        $this->assertSame(400000, (int) $row->total_dibayar);
        $this->assertSame(600000, (int) $row->sisa);
    }
}
