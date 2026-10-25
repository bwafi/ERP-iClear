<?php

namespace Tests;

use App\Models\ModelReturBarangRusak;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Alur retur barang rusak klaim: simpan, batal, ubah status, dan scope unit.
 */
final class ReturBarangRusakTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_retur_klaim_item',
        'db_retur_klaim',
        'db_service',
        'db_service_sparepart',
        'db_barang_rusak_claim',
        'db_service_kerusakan',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_akun',
        'db_jabatan',
        'db_barang',
        'db_hpp_barang',
        'db_stok_awal',
        'db_proses_service',
        'db_penjualan',
        'db_detail_penjualan',
        'db_suplier',
        'db_menu',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->fresh();
        $this->buatTabel();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $db = $this->fresh();
        foreach (self::TABEL as $tabel) {
            $db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        parent::tearDown();
    }

    public function testSimpanDitolakMenyimpanLossDiBulanRetur(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [[
                'selected' => 1,
                'id' => 1,
                'hpp_manual' => '4500',
                'hasil' => 'ditolak',
                'keterangan' => 'Supplier menolak',
            ]],
        ])->assertRedirect();

        $db = $this->fresh();
        $header = $db->table('retur_klaim')->get()->getRow();
        $item = $db->table('retur_klaim_item')->get()->getRow();
        $sparepart = $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();

        $this->assertNotNull($header);
        $this->assertStringStartsWith('RTB120261025', $header->no_retur);
        $this->assertSame(4500, (int) $item->hpp_manual);
        $this->assertSame(9000, (int) $item->nilai);
        $this->assertSame('ditolak', $sparepart->status_retur);
        $this->assertSame(4500, (int) $sparepart->hpp_omset_garansi);
        $this->assertSame('2026-10-25', $sparepart->tanggal_hpp_klaim);
        $this->assertSame(4000, (int) $sparepart->hpp_penjualan_garansi);
    }

    public function testSimpanTidakMeneruskanFilterKlaim(): void
    {
        $result = $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'kembali' => 'tanggal_awal=2020-01-01&tanggal_akhir=2020-01-31',
            'items' => [['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak']],
        ]);

        $result->assertRedirectTo(base_url('retur_barang_rusak'));
    }

    public function testSimpanBanyakItemSatuUnit(): void
    {
        $this->db->table('service_sparepart')->insert([
            'idservice_sparepart' => 2,
            'service_idservice' => 1,
            'barang_idbarang' => 11,
            'unit_idunit' => 1,
            'jumlah_tambahan_garansi' => 1,
            'hpp_penjualan_garansi' => 1000,
            'status_retur' => 'bisa',
            'hpp_omset_garansi' => 0,
        ]);

        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [
                ['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'potong_tagihan'],
                ['selected' => 1, 'id' => 2, 'hpp_manual' => '1000', 'hasil' => 'uang_kembali'],
            ],
        ])->assertRedirect();

        $db = $this->fresh();
        $this->assertSame(2, $db->table('retur_klaim_item')->countAllResults());
        $rows = $db->table('service_sparepart')->whereIn('idservice_sparepart', [1, 2])->get()->getResult();
        foreach ($rows as $row) {
            $this->assertSame('diterima', $row->status_retur);
            $this->assertSame(0, (int) $row->hpp_omset_garansi);
            $this->assertNull($row->tanggal_hpp_klaim);
        }
    }

    public function testSimpanBarangPenggantiMenyimpanNilaiTanpaMutasiStok(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [[
                'selected' => 1,
                'id' => 1,
                'hpp_manual' => '4500',
                'hasil' => 'barang_pengganti',
                'barang_pengganti_id' => 11,
                'jumlah_pengganti' => 2,
            ]],
        ])->assertRedirect();

        $db = $this->fresh();
        $item = $db->table('retur_klaim_item')->get()->getRow();
        $sparepart = $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $stok = $db->table('stok_barang')->where('idbarang', 11)->get()->getRow();

        $this->assertSame(9000, (int) $item->nilai);
        $this->assertSame(11, (int) $item->barang_pengganti_idbarang);
        $this->assertSame(2.0, (float) $item->jumlah_pengganti);
        $this->assertSame('diterima', $sparepart->status_retur);
        $this->assertSame(5, (int) $stok->stok_akhir);
    }

    public function testSimpanLintasUnitDitolak(): void
    {
        $this->db->table('service')->insert([
            'idservice' => 2,
            'no_service' => 'SRV-2',
            'status_service' => 4,
            'unit_idunit' => 2,
            'tanggal_claim_garansi' => '2026-10-20 10:00:00',
        ]);
        $this->db->table('service_sparepart')->insert([
            'idservice_sparepart' => 3,
            'service_idservice' => 2,
            'barang_idbarang' => 10,
            'unit_idunit' => 2,
            'jumlah_tambahan_garansi' => 1,
            'hpp_penjualan_garansi' => 4000,
            'status_retur' => 'bisa',
            'hpp_omset_garansi' => 0,
        ]);

        $result = $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [
                ['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak'],
                ['selected' => 1, 'id' => 3, 'hpp_manual' => '4000', 'hasil' => 'ditolak'],
            ],
        ]);

        $result->assertRedirect();
        $db = $this->fresh();
        $this->assertSame(0, $db->table('retur_klaim')->countAllResults());
        $this->assertSame('bisa', $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow()->status_retur);
        $this->assertSame('bisa', $db->table('service_sparepart')->where('idservice_sparepart', 3)->get()->getRow()->status_retur);
    }

    public function testBatalReturMengembalikanStatus(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak']],
        ])->assertRedirect();

        $db = $this->fresh();
        $headerId = (int) $db->table('retur_klaim')->get()->getRow()->idretur_klaim;

        $this->sesi();
        $this->post('retur_barang_rusak/batal/' . $headerId, [
            'csrf_test_name' => service('security')->getHash(),
        ])->assertRedirect();

        $db = $this->fresh();
        $this->assertSame(0, $db->table('retur_klaim')->countAllResults());
        $this->assertSame(0, $db->table('retur_klaim_item')->countAllResults());
        $sparepart = $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame('bisa', $sparepart->status_retur);
        $this->assertSame(0, (int) $sparepart->hpp_omset_garansi);
        $this->assertNull($sparepart->tanggal_hpp_klaim);
    }

    public function testUbahStatusMengubahLoss(): void
    {
        $this->sesi();
        $this->post('retur_barang_rusak/ubah-status/1', [
            'status' => 'tidak_bisa',
            'csrf_test_name' => service('security')->getHash(),
        ])->assertRedirect();

        $db = $this->fresh();
        $sparepart = $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame('tidak_bisa', $sparepart->status_retur);
        $this->assertSame(4000, (int) $sparepart->hpp_omset_garansi);
        $this->assertSame('2026-10-20', $sparepart->tanggal_hpp_klaim);

        $this->post('retur_barang_rusak/ubah-status/1', [
            'status' => 'bisa',
            'csrf_test_name' => service('security')->getHash(),
        ])->assertRedirect();

        $db = $this->fresh();
        $sparepart = $db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame('bisa', $sparepart->status_retur);
        $this->assertSame(0, (int) $sparepart->hpp_omset_garansi);
        $this->assertNull($sparepart->tanggal_hpp_klaim);
    }

    public function testUbahStatusMempertahankanFilter(): void
    {
        $this->sesi();
        $result = $this->post('retur_barang_rusak/ubah-status/1', [
            'status' => 'tidak_bisa',
            'kembali' => 'unit=1&tanggal_awal=2026-10-01&tanggal_akhir=2026-10-31',
            'csrf_test_name' => service('security')->getHash(),
        ]);

        $result->assertRedirectTo(base_url('retur_barang_rusak?unit=1&tanggal_awal=2026-10-01&tanggal_akhir=2026-10-31'));
    }

    public function testBarisSudahReturTidakMunculDiDaftar(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak']],
        ])->assertRedirect();

        $model = new ModelReturBarangRusak();
        $this->assertCount(0, $model->getKlaimBelumRetur(1, '2026-10-01', '2026-10-31'));
    }

    public function testEditSparepartLainTidakBisaHapusBarisKlaimYangSudahRetur(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak']],
        ])->assertRedirect();

        $this->sesi();
        $result = $this->post('update_service/saveSparepart', [
            'idservice_s' => 1,
            'garansi' => 30,
            'produk' => [[
                'id' => 11,
                'jumlah' => 1,
                'harga' => '50000',
                'diskon' => '0',
                'total' => '50000',
            ]],
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('gagal');

        $db = $this->fresh();
        $this->assertSame(1, $db->table('service_sparepart')->where('idservice_sparepart', 1)->countAllResults());
        $this->assertSame(1, $db->table('retur_klaim_item')->countAllResults());
    }

    public function testEditSparepartKlaimTidakBisaHapusBarisYangSudahRetur(): void
    {
        $this->postRetur([
            'suplier_id' => 1,
            'tanggal' => '2026-10-25',
            'items' => [['selected' => 1, 'id' => 1, 'hpp_manual' => '4500', 'hasil' => 'ditolak']],
        ])->assertRedirect();

        $this->sesi();
        $result = $this->post('update_service_garansi/saveSparepart', [
            'idservice_s' => 1,
            'biaya_garansi' => 0,
            'produk' => [[
                'id' => 11,
                'qty_tambahan' => 1,
                'harga' => '50000',
                'diskon_penjualan_garansi' => '0',
            ]],
            'csrf_test_name' => service('security')->getHash(),
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('gagal');

        $db = $this->fresh();
        $this->assertSame(1, $db->table('service_sparepart')->where('idservice_sparepart', 1)->countAllResults());
        $this->assertSame(1, $db->table('retur_klaim_item')->countAllResults());
    }

    public function testIndexMenampilkanHalaman(): void
    {
        $this->sesi();
        $result = $this->get('retur_barang_rusak?tanggal_awal=2026-10-01&tanggal_akhir=2026-10-31');

        $result->assertOK();
        $result->assertSee('Retur Barang Rusak');
        $result->assertSee('SRV-1');
    }

    public function testViewMenampilkanKlaimDanRiwayat(): void
    {
        $klaim = (object) [
            'idservice_sparepart' => 1,
            'no_service' => 'SRV-1',
            'tanggal_claim_garansi' => '2026-10-20 10:00:00',
            'NAMA_UNIT' => 'Unit 1',
            'nama_barang' => 'LCD Tes',
            'nama_barang_rusak' => 'LCD Rusak',
            'jumlah_tambahan_garansi' => 2,
            'hpp_penjualan_garansi' => 4000,
            'status_retur' => 'bisa',
        ];
        $header = (object) [
            'idretur_klaim' => 1,
            'no_retur' => 'RTB120261025001',
            'tanggal' => '2026-10-25',
            'nama_suplier' => 'Supplier Tes',
            'NAMA_UNIT' => 'Unit 1',
            'jumlah_item' => 1,
            'total_nilai' => 9000,
        ];
        $item = (object) [
            'nama_barang' => 'LCD Tes',
            'jumlah' => 2,
            'hpp_manual' => 4500,
            'hasil' => 'ditolak',
            'nama_barang_pengganti' => null,
            'jumlah_pengganti' => null,
            'nilai' => 9000,
            'keterangan' => 'Ditolak supplier',
        ];

        $html = view('retur/retur_barang_rusak', [
            'klaim' => [$klaim],
            'riwayat' => [$header],
            'items_by_retur' => [1 => [$item]],
            'suppliers' => [(object) ['id_suplier' => 1, 'nama_suplier' => 'Supplier Tes']],
            'barang' => [(object) ['idbarang' => 10, 'kode_barang' => 'SP-10', 'nama_barang' => 'LCD Tes']],
            'units' => [],
            'can_see_all_units' => false,
            'selected_unit' => 1,
            'tanggal_awal' => '2026-10-01',
            'tanggal_akhir' => '2026-10-31',
        ]);

        $this->assertStringContainsString('Retur Barang Rusak', $html);
        $this->assertStringContainsString('SRV-1', $html);
        $this->assertStringContainsString('RTB120261025001', $html);
        $this->assertStringContainsString('name="items[0][hpp_manual]"', $html);
        $this->assertStringContainsString('name="items[0][barang_pengganti_id]"', $html);
        $this->assertStringContainsString('name="suplier_id"', $html);
        $this->assertStringContainsString('rt-table--klaim', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('Ditolak supplier', $html);
    }

    private function postRetur(array $payload)
    {
        $this->sesi();

        return $this->post('retur_barang_rusak/simpan', $payload + [
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN' => 43,
            'ID_UNIT' => 1,
            'ID_JABATAN' => 2,
        ]);
    }

    private function fresh()
    {
        return Database::connect('tests', false);
    }

    private function seed(): void
    {
        $this->db->table('akun')->insert([
            'ID_AKUN' => 43,
            'ID_JABATAN' => 2,
            'ID_UNIT' => 1,
            'NAMA_AKUN' => 'Akun Tes',
            'ROLES' => null,
        ]);
        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Admin Tes', 'ROLES_JABATAN' => null],
            ['ID_JABATAN' => 2, 'NAMA_JABATAN' => 'Teknisi Tes', 'ROLES_JABATAN' => null],
        ]);
        $this->db->table('pelanggan')->insert(['id_pelanggan' => 1, 'nama' => 'Pelanggan Tes']);
        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1', 'LOGO' => null],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit 2', 'LOGO' => null],
        ]);
        $this->db->table('menu')->insert([
            'idmenu' => 10160,
            'nama_menu' => 'Retur Barang Rusak',
            'url' => 'retur_barang_rusak',
            'parent' => 0,
            'sub' => 0,
            'utama' => 1,
            'categories' => 1,
            'show_menu' => 1,
            'urutan' => 26,
            'roles' => 'retur_barang_rusak',
            'icon' => null,
            'manualbook' => null,
        ]);
        $this->db->table('suplier')->insert([
            'id_suplier' => 1,
            'nama_suplier' => 'Supplier Tes',
            'deleted' => '0',
            'unit_idunit' => 1,
        ]);
        $this->db->table('barang')->insertBatch([
            ['idbarang' => 10, 'kode_barang' => 'SP-10', 'nama_barang' => 'LCD Tes', 'harga' => 100000, 'deleted' => '0'],
            ['idbarang' => 11, 'kode_barang' => 'SP-11', 'nama_barang' => 'Baterai Tes', 'harga' => 50000, 'deleted' => '0'],
        ]);
        $this->db->table('hpp_barang')->insertBatch([
            ['idbarang' => 10, 'hpp' => 4000],
            ['idbarang' => 11, 'hpp' => 1000],
        ]);
        $this->db->table('stok_awal')->insertBatch([
            ['barang_idbarang' => 10, 'satuan_terkecil' => 'pcs'],
            ['barang_idbarang' => 11, 'satuan_terkecil' => 'pcs'],
        ]);
        $this->db->table('stok_barang')->insertBatch([
            ['idbarang' => 10, 'id_unit' => 1, 'stok_akhir' => 5, 'stok_minimum' => 0],
            ['idbarang' => 11, 'id_unit' => 1, 'stok_akhir' => 5, 'stok_minimum' => 0],
        ]);
        $this->db->table('service')->insert([
            'idservice' => 1,
            'no_service' => 'SRV-1',
            'status_service' => 4,
            'garansi_hari' => 30,
            'pelanggan_id_pelanggan' => 1,
            'unit_idunit' => 1,
            'tanggal_selesai' => '2026-10-15 10:00:00',
            'tanggal_claim_garansi' => '2026-10-20 10:00:00',
            'created_at' => '2026-10-15 10:00:00',
        ]);
        $this->db->table('barang_rusak_claim')->insert([
            'idbarang_rusak_claim' => 1,
            'service_idservice' => 1,
            'barang_idbarang' => 10,
            'jumlah' => 1,
            'unit_idunit' => 1,
        ]);
        $this->db->table('service_sparepart')->insert([
            'idservice_sparepart' => 1,
            'service_idservice' => 1,
            'barang_idbarang' => 10,
            'unit_idunit' => 1,
            'jumlah' => 0,
            'jumlah_tambahan_garansi' => 2,
            'hpp_penjualan_garansi' => 4000,
            'status_retur' => 'bisa',
            'hpp_omset_garansi' => 0,
            'tanggal_hpp_klaim' => null,
        ]);
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $tabel) {
            $q("DROP TABLE IF EXISTS {$tabel}");
        }

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            no_hp VARCHAR(32) NULL,
            alamat TEXT NULL,
            keluhan TEXT NULL,
            keterangan TEXT NULL,
            tipe_hp VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            status_service INT NOT NULL DEFAULT 0,
            status_proses INT NULL,
            garansi_hari INT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            tanggal_selesai DATETIME NULL,
            tanggal_bisa_diambil DATE NULL,
            tanggal_claim_garansi DATETIME NULL,
            total_service_garansi INT NULL DEFAULT 0,
            total_diskon_garansi INT NULL DEFAULT 0,
            biaya_tambahan_garansi INT NULL DEFAULT 0,
            harus_dibayar_garansi INT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            unit_idunit INT NULL,
            jumlah INT NULL DEFAULT 0,
            harga_penjualan INT NULL DEFAULT 0,
            harga_penjualan_garansi INT NULL DEFAULT 0,
            sub_total INT NULL DEFAULT 0,
            sub_total_garansi INT NULL DEFAULT 0,
            hpp_penjualan INT NULL DEFAULT 0,
            satuan_jual VARCHAR(32) NULL,
            diskon_penjualan INT NULL DEFAULT 0,
            diskon_penjualan_garansi INT NULL DEFAULT 0,
            jumlah_tambahan_garansi INT NULL DEFAULT 0,
            hpp_penjualan_garansi INT NULL DEFAULT 0,
            status_retur VARCHAR(20) NULL DEFAULT NULL,
            hpp_omset_garansi INT NOT NULL DEFAULT 0,
            tanggal_hpp_klaim DATE NULL DEFAULT NULL,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_retur_klaim (
            idretur_klaim INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_retur VARCHAR(45) NOT NULL,
            tanggal DATE NOT NULL,
            suplier_id_suplier INT NOT NULL,
            unit_idunit INT NOT NULL,
            input_by INT NULL,
            catatan TEXT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idretur_klaim),
            UNIQUE KEY uq_retur_klaim_no (no_retur)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_retur_klaim_item (
            idretur_klaim_item INT UNSIGNED NOT NULL AUTO_INCREMENT,
            retur_klaim_idretur_klaim INT NOT NULL,
            service_sparepart_idservice_sparepart INT NOT NULL,
            barang_rusak_claim_idbarang_rusak_claim INT NULL,
            barang_idbarang INT NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL,
            hpp_manual INT NOT NULL,
            hasil VARCHAR(30) NOT NULL,
            barang_pengganti_idbarang INT NULL,
            jumlah_pengganti DECIMAL(15,2) NULL,
            nilai INT NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idretur_klaim_item),
            UNIQUE KEY uq_retur_klaim_item_sparepart (service_sparepart_idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_barang_rusak_claim (
            idbarang_rusak_claim INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            unit_idunit INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idbarang_rusak_claim)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_kerusakan (
            idservice_kerusakan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            fungsi_idfungsi INT NULL,
            keterangan TEXT NULL,
            PRIMARY KEY (idservice_kerusakan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            LOGO VARCHAR(255) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_barang (
            idbarang INT NULL,
            id_unit INT NULL,
            stok_akhir INT NULL DEFAULT 0,
            stok_minimum INT NULL DEFAULT 0
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_akun (
            ID_AKUN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ID_JABATAN INT NULL,
            ID_UNIT INT NULL DEFAULT 0,
            NAMA_AKUN VARCHAR(100) NULL,
            ROLES TEXT NULL,
            PRIMARY KEY (ID_AKUN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jabatan (
            ID_JABATAN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_JABATAN VARCHAR(150) NULL,
            ROLES_JABATAN TEXT NULL,
            PRIMARY KEY (ID_JABATAN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_barang (
            idbarang INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_barang VARCHAR(64) NULL,
            nama_barang VARCHAR(150) NULL,
            harga INT NULL DEFAULT 0,
            idkategori INT NULL,
            deleted VARCHAR(5) NULL DEFAULT "0",
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_hpp_barang (
            idbarang INT UNSIGNED NOT NULL,
            hpp INT NULL DEFAULT 0,
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_awal (
            idstok_awal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            barang_idbarang INT NULL,
            satuan_terkecil VARCHAR(32) NULL,
            PRIMARY KEY (idstok_awal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_proses_service (
            idproses_service INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            PRIMARY KEY (idproses_service)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_penjualan (
            idpenjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_invoice VARCHAR(64) NULL,
            tanggal DATETIME NULL,
            total_penjualan INT NULL DEFAULT 0,
            bayar INT NULL DEFAULT 0,
            keterangan VARCHAR(64) NULL,
            service_idservice INT NULL,
            unit_idunit INT NULL,
            PRIMARY KEY (idpenjualan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_detail_penjualan (
            iddetail_penjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            penjualan_idpenjualan INT NULL,
            barang_idbarang INT NULL,
            jumlah INT NULL DEFAULT 0,
            harga_penjualan INT NULL DEFAULT 0,
            diskon_penjualan INT NULL DEFAULT 0,
            sub_total INT NULL DEFAULT 0,
            hpp_penjualan INT NULL DEFAULT 0,
            PRIMARY KEY (iddetail_penjualan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_suplier (
            id_suplier INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_suplier VARCHAR(255) NULL,
            alamat VARCHAR(255) NULL,
            no_hp VARCHAR(255) NULL,
            deleted VARCHAR(255) NULL,
            unit_idunit INT NULL,
            PRIMARY KEY (id_suplier)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_menu (
            idmenu INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_menu VARCHAR(150) NULL,
            url VARCHAR(150) NULL,
            parent INT NULL DEFAULT 0,
            sub INT NULL DEFAULT 0,
            utama INT NULL DEFAULT 1,
            categories INT NULL DEFAULT 0,
            show_menu INT NULL DEFAULT 1,
            urutan INT NULL DEFAULT 0,
            roles VARCHAR(150) NULL,
            icon TEXT NULL,
            manualbook VARCHAR(150) NULL,
            PRIMARY KEY (idmenu)
        ) ENGINE=InnoDB');
    }
}
