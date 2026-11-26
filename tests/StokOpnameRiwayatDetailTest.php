<?php

namespace Tests;

use App\Services\StokOpnameService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Rincian selisih per periode untuk modal Detail di riwayat /stok_opname.
 *
 * Aturan yang dijaga:
 *   - FINAL membaca stok_opname dan hanya revisi aktif (is_reverted = 0).
 *   - DRAFT membaca stok_opname_draft.
 *   - BATAL tidak punya hasil berlaku.
 *   - Baris dengan selisih 0 tidak ikut, walau total periode 0.
 *
 * Catatan prefix: StokOpnameService memakai raw SQL tanpa prefix pada sebagian
 * method. Tes ini menyetel prefix koneksi ke '' agar menyerupai produksi.
 */
class StokOpnameRiwayatDetailTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['barang', 'stok_opname', 'stok_opname_draft'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE barang (
                idbarang INTEGER PRIMARY KEY, kode_barang TEXT NULL,
                nama_barang TEXT NULL, jenis_hp TEXT NULL, warna TEXT NULL)');

        $q('CREATE TABLE stok_opname (
                idstok_opname INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, jumlah_real REAL NULL, jumlah_komp REAL NULL,
                jumlah_selisih REAL NULL, barang_idbarang INTEGER NULL,
                unit_idunit INTEGER NULL, periode_id INTEGER NULL,
                is_reverted INTEGER NOT NULL DEFAULT 0)');

        $q('CREATE TABLE stok_opname_draft (
                idstok_opname INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, jumlah_real REAL NULL, jumlah_komp REAL NULL,
                jumlah_selisih REAL NULL, barang_idbarang INTEGER NULL,
                unit_idunit INTEGER NULL, periode_id INTEGER NULL)');
    }

    private function sebarData(): void
    {
        foreach ([
            [1, 'BRG-1', 'Barang Plus'],
            [2, 'BRG-2', 'Barang Minus'],
            [3, 'BRG-3', 'Barang Presisi'],
            [4, 'BRG-4', 'Barang Revisi Lama'],
        ] as [$id, $kode, $nama]) {
            $this->db->query(
                'INSERT INTO barang (idbarang, kode_barang, nama_barang, jenis_hp, warna) VALUES (?, ?, ?, ?, ?)',
                [$id, $kode, $nama, 'Tipe ' . $id, 'Hitam']
            );
        }
    }

    private function barisFinal(int $periode, int $barang, float $real, float $komp, float $selisih, int $reverted = 0): void
    {
        $this->db->query(
            'INSERT INTO stok_opname (tanggal, jumlah_real, jumlah_komp, jumlah_selisih, barang_idbarang, unit_idunit, periode_id, is_reverted)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
            ['2026-07-14', $real, $komp, $selisih, $barang, $periode, $reverted]
        );
    }

    private function barisDraft(int $periode, int $barang, float $real, float $komp, float $selisih): void
    {
        $this->db->query(
            'INSERT INTO stok_opname_draft (tanggal, jumlah_real, jumlah_komp, jumlah_selisih, barang_idbarang, unit_idunit, periode_id)
             VALUES (?, ?, ?, ?, ?, 1, ?)',
            ['2026-07-14', $real, $komp, $selisih, $barang, $periode]
        );
    }

    public function testFinalMengembalikanBarisBerselisihWalauTotalNol(): void
    {
        // +1 dan -1 saling menutup: total periode 0, tetapi dua baris berselisih.
        $this->barisFinal(9, 1, 6, 5, 1);
        $this->barisFinal(9, 2, 4, 5, -1);
        $this->barisFinal(9, 3, 5, 5, 0);

        $items = (new StokOpnameService($this->db))->selisihItems(9, 'FINAL');

        $this->assertCount(2, $items, 'Baris selisih 0 tidak ikut, dua baris berselisih tetap tampil');
        $this->assertSame(['BRG-1', 'BRG-2'], array_column($items, 'kode'));
        $this->assertSame(1.0, $items[0]['selisih']);
        $this->assertSame(-1.0, $items[1]['selisih']);
        $this->assertSame('Tipe 1', $items[0]['jenis']);
        $this->assertSame('Hitam', $items[0]['warna']);
    }

    public function testFinalMengabaikanBarisYangSudahDirevert(): void
    {
        $this->barisFinal(9, 1, 6, 5, 1);
        $this->barisFinal(9, 4, 1, 100, -99, 1); // revisi lama setelah reopen

        $items = (new StokOpnameService($this->db))->selisihItems(9, 'FINAL');

        $this->assertCount(1, $items);
        $this->assertSame('BRG-1', $items[0]['kode']);
    }

    public function testDraftMembacaTabelDraft(): void
    {
        $this->barisDraft(20, 2, 3, 5, -2);

        $items = (new StokOpnameService($this->db))->selisihItems(20, 'DRAFT');

        $this->assertCount(1, $items);
        $this->assertSame('BRG-2', $items[0]['kode']);
        $this->assertSame(-2.0, $items[0]['selisih']);
    }

    public function testBatalDanIdTidakValidTidakPunyaDetail(): void
    {
        $this->barisDraft(30, 2, 3, 5, -2);
        $svc = new StokOpnameService($this->db);

        $this->assertSame([], $svc->selisihItems(30, 'BATAL'));
        $this->assertSame([], $svc->selisihItems(0, 'FINAL'));
        $this->assertSame([], $svc->selisihItems(30, 'STATUS_ASING'));
    }
}
