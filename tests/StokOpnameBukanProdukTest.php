<?php

namespace Tests;

use App\Services\StokOpnameService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Barang jasa (nama berawalan PUP/JASA/MESIN) bukan produk sehingga tidak
 * boleh masuk daftar opname.
 *
 * Menguji lewat service (bukan controller) supaya fokus pada aturan seed dan
 * pembersihan draft: seed baru harus melewatkan jasa, draft lama yang dibuka
 * sebelum aturan ini pun harus bersih.
 *
 * Catatan prefix: StokOpnameService memakai sebagian raw SQL tanpa prefix
 * (asumsi produksi: DBPrefix = ''). Tes ini menyetel prefix koneksi ke '' agar
 * menyerupai produksi, lalu mengembalikannya di tearDown.
 */
class StokOpnameBukanProdukTest extends CIUnitTestCase
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

        foreach ([
            'barang', 'stok_barang', 'stok_awal', 'hpp_barang',
            'stok_opname_periode', 'stok_opname_draft', 'stok_opname_audit',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE barang (idbarang INTEGER PRIMARY KEY, nama_barang TEXT NULL, kode_barang TEXT NULL, deleted INTEGER DEFAULT 0)');

        // Di produksi `stok_barang` adalah VIEW; di test cukup tabel datar dengan
        // kolom yang dibaca StokOpnameService::createPeriode().
        $q('CREATE TABLE stok_barang (
                idbarang INTEGER, id_unit INTEGER, kode_barang TEXT NULL,
                nama_barang TEXT NULL, stok_akhir REAL NULL)');

        $q('CREATE TABLE stok_awal (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, barang_idbarang INTEGER NULL,
                unit_idunit INTEGER NULL, jumlah REAL NULL, satuan_terkecil TEXT NULL)');
        $q('CREATE TABLE hpp_barang (idbarang INTEGER PRIMARY KEY, hpp REAL NULL)');

        $q('CREATE TABLE stok_opname_periode (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INTEGER NOT NULL, tanggal TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'DRAFT\',
                jumlah_komp REAL NOT NULL DEFAULT 0, jumlah_real REAL NULL,
                jumlah_selisih REAL NULL, total_barang INTEGER NOT NULL DEFAULT 0,
                terisi_barang INTEGER NOT NULL DEFAULT 0, mulai_by INTEGER NULL,
                finalisasi_by INTEGER NULL, tanggal_finalisasi TEXT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');

        $q('CREATE TABLE stok_opname_draft (
                idstok_opname INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, hpp REAL NULL, jumlah_real REAL NULL,
                jumlah_komp REAL NULL, jumlah_selisih REAL NULL,
                satuan_terkecil TEXT NULL, barang_idbarang INTEGER NULL,
                unit_idunit INTEGER NULL, periode_id INTEGER NULL)');

        $q('CREATE TABLE stok_opname_audit (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, periode_id INTEGER NULL,
                unit_idunit INTEGER NULL, tanggal TEXT NULL, aksi TEXT NULL,
                actor_id INTEGER NULL, jumlah_barang INTEGER DEFAULT 0,
                jumlah_terisi INTEGER DEFAULT 0, catatan TEXT NULL, created_at TEXT NULL)');
    }

    /**
     * Barang 101,105,106 adalah produk (106 hanya KEBETULAN memuat kata
     * "Mesin" di tengah, bukan awalan). 102/103/104 adalah jasa.
     */
    private function sebarData(): void
    {
        $barang = [
            [101, 'HP Samsung A', 'HP-101', 5],
            [102, 'PUP Ganti LCD', 'PUP-102', 2],
            [103, 'JASA Service Panggilan', 'JASA-103', 1],
            [104, 'MESIN Press LCD', 'MESIN-104', 3],
            [105, 'LCD iPhone 11', 'LCD-105', 4],
            [106, 'Kuras Mesin HP', 'SVC-106', 2],
        ];

        foreach ($barang as [$id, $nama, $kode, $stok]) {
            $this->db->query('INSERT INTO barang (idbarang, nama_barang, kode_barang, deleted) VALUES (?, ?, ?, 0)', [$id, $nama, $kode]);
            $this->db->query(
                'INSERT INTO stok_barang (idbarang, id_unit, kode_barang, nama_barang, stok_akhir) VALUES (?, 1, ?, ?, ?)',
                [$id, $kode, $nama, $stok]
            );
            $this->db->query('INSERT INTO stok_awal (barang_idbarang, unit_idunit, jumlah, satuan_terkecil) VALUES (?, 1, ?, ?)', [$id, $stok, 'pcs']);
            $this->db->query('INSERT INTO hpp_barang (idbarang, hpp) VALUES (?, 1000)', [$id]);
        }
    }

    public function testMulaiOpnameMengecualikanJasaPupMesin(): void
    {
        $svc = new StokOpnameService($this->db);
        $r = $svc->createPeriode(1, '2026-10-10', 43);

        $this->assertTrue($r['success'], implode(' ', $r['errors']));
        $this->assertSame(3, $r['jumlah'], 'Hanya produk (101,105,106) yang boleh diopname');

        $rows = $this->db->table('stok_opname_draft')->orderBy('barang_idbarang', 'ASC')->get()->getResultArray();
        $ids = array_map(static fn ($r) => (int) $r['barang_idbarang'], $rows);

        $this->assertSame([101, 105, 106], $ids, 'Barang jasa PUP/JASA/MESIN tidak boleh ikut ter-seed');
        $this->assertSame(3, (int) $r['periode']->total_barang);
    }

    public function testBuangBukanProdukMembersihkanDraftLama(): void
    {
        // Draft lama (mis. dibuat sebelum aturan ini) yang masih memuat jasa.
        $this->db->query(
            "INSERT INTO stok_opname_periode (id, unit_idunit, tanggal, status, created_at, updated_at)
             VALUES (50, 1, '2026-10-01', 'DRAFT', NOW(), NOW())"
        );
        foreach ([101, 102, 103, 106] as $id) {
            $this->db->query(
                'INSERT INTO stok_opname_draft (tanggal, jumlah_komp, barang_idbarang, unit_idunit, periode_id)
                 VALUES (\'2026-10-01\', 5, ?, 1, 50)',
                [$id]
            );
        }

        $svc = new StokOpnameService($this->db);
        $dibuang = $svc->buangBukanProdukDariDraft(1);

        $this->assertSame(2, $dibuang, 'Hanya baris jasa (102 PUP, 103 JASA) yang dibuang');

        $sisa = $this->db->table('stok_opname_draft')->orderBy('barang_idbarang', 'ASC')->get()->getResultArray();
        $ids = array_map(static fn ($r) => (int) $r['barang_idbarang'], $sisa);
        $this->assertSame([101, 106], $ids, '"Kuras Mesin" (bukan awalan MESIN) harus tetap ada');

        $periode = $this->db->table('stok_opname_periode')->where('id', 50)->get()->getRowArray();
        $this->assertSame(2, (int) $periode['total_barang'], 'Ringkasan periode harus dihitung ulang setelah pembersihan');
    }

    public function testBuangBukanProdukTidakMenyentuhPeriodeFinal(): void
    {
        $this->db->query(
            "INSERT INTO stok_opname_periode (id, unit_idunit, tanggal, status, created_at, updated_at)
             VALUES (60, 1, '2026-09-30', 'FINAL', NOW(), NOW())"
        );
        $this->db->query(
            'INSERT INTO stok_opname_draft (tanggal, jumlah_komp, barang_idbarang, unit_idunit, periode_id)
             VALUES (\'2026-09-30\', 2, 102, 1, 60)'
        );

        $svc = new StokOpnameService($this->db);
        $this->assertSame(0, $svc->buangBukanProdukDariDraft(1), 'FINAL tidak disentuh');

        $this->assertSame(
            1,
            $this->db->table('stok_opname_draft')->where('periode_id', 60)->countAllResults(),
            'Baris jasa pada periode FINAL dibiarkan sebagai catatan historis'
        );
    }
}
