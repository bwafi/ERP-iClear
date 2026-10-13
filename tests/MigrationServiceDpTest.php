<?php

/**
 * Audit struktur & perilaku migration ledger DP (`service_dp`).
 *
 * Menjalankan migration secara manual di database uji (`erp_test`) dengan
 * prefix kosong, mengikuti pola MigrationIdempotencyTest:
 *  - kolom & index yang dijanjikan benar-benar ada (termasuk primary key dan
 *    index UNIQUE `kode_dp`);
 *  - keunikan `kode_dp` ditegakkan di level DB (backstop idempotensi)
 *    — kontroller mengandalkan ini lewat kode_dp stabil 'DP' + no_service;
 *  - `up()` aman dieksekusi berulang;
 *  - `down()` hanya menghapus tabel milik migration ini (tabel lain tetap ada).
 */

namespace Tests;

use App\Database\Migrations\CreateServiceDpLedger;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class MigrationServiceDpTest extends CIUnitTestCase
{
    private const FILE = '2026-10-12-080000_CreateServiceDpLedger';

    private $prefixAsli;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach ($this->tabelMilikTes() as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        // Cache daftar tabel CI4 per koneksi bisa basi setelah DDL di test
        // sebelumnya; bersihkan agar guard `tableExists` di migration deterministik.
        $this->db->resetDataCache();

        if (! class_exists(CreateServiceDpLedger::class, false)) {
            require_once APPPATH . 'Database/Migrations/' . self::FILE . '.php';
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tabelMilikTes() as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function tabelMilikTes(): array
    {
        return ['service_dp', 'service'];
    }

    private function migrasi(): CreateServiceDpLedger
    {
        return new CreateServiceDpLedger();
    }

    public function testMigrasiMembuatTabelServiceDpDenganKolomDanIndex(): void
    {
        $this->migrasi()->up();

        $this->assertTrue($this->db->tableExists('service_dp', false), 'Tabel service_dp harus dibuat setelah up().');

        $kolom = [];
        foreach ($this->db->query('SHOW COLUMNS FROM service_dp')->getResultArray() as $baris) {
            $kolom[$baris['Field']] = $baris;
        }

        foreach ([
            'id', 'service_idservice', 'kode_dp', 'tanggal', 'jumlah', 'metode',
            'bank_idbank', 'referensi', 'status', 'keterangan', 'unit_id',
            'input_by', 'created_at', 'updated_at',
        ] as $nama) {
            $this->assertArrayHasKey($nama, $kolom, "Kolom `{$nama}` harus ada.");
        }

        $this->assertStringContainsString('PRI', $kolom['id']['Key']);
        $this->assertStringContainsString('auto_increment', $kolom['id']['Extra']);

        $index = [];
        foreach ($this->db->query('SHOW INDEX FROM service_dp')->getResultArray() as $baris) {
            $index[$baris['Key_name']][(int) $baris['Seq_in_index']] = [
                'kolom' => $baris['Column_name'],
                'unik'  => (int) $baris['Non_unique'] === 0,
            ];
        }

        $this->assertSame(['id', true], [$index['PRIMARY'][1]['kolom'], $index['PRIMARY'][1]['unik']], 'Primary key pada id.');
        $this->assertSame(['kode_dp', true], [$index['uniq_sdp_kode'][1]['kolom'], $index['uniq_sdp_kode'][1]['unik']], 'Unique key uniq_sdp_kode pada kode_dp.');
        $this->assertSame(['service_idservice', false], [$index['idx_sdp_service'][1]['kolom'], $index['idx_sdp_service'][1]['unik']]);
        $this->assertSame(['service_idservice', 'status'], [
            $index['idx_sdp_service_status'][1]['kolom'],
            $index['idx_sdp_service_status'][2]['kolom'],
        ]);
        $this->assertSame(['unit_id'], [$index['idx_sdp_unit'][1]['kolom']]);
        $this->assertSame(['tanggal'], [$index['idx_sdp_tanggal'][1]['kolom']]);
    }

    public function testUniqueKodeDpDitindaklanjutiDiLevelDb(): void
    {
        $this->migrasi()->up();

        $ditolak = false;
        try {
            $this->db->table('service_dp')->insert([
                'service_idservice' => 1,
                'kode_dp'           => 'DP-DUPLIKAT',
                'jumlah'            => 100000,
            ]);
            $this->db->table('service_dp')->insert([
                'service_idservice' => 2,
                'kode_dp'           => 'DP-DUPLIKAT',
                'jumlah'            => 200000,
            ]);
        } catch (\Throwable $e) {
            $ditolak = true;
        }

        $this->assertTrue($ditolak, 'Insert dengan kode_dp duplikat harus ditolak oleh unique constraint.');
        $this->assertSame(1, (int) $this->db->table('service_dp')->countAllResults());
    }

    public function testEksekusiUpBerulangAman(): void
    {
        $this->migrasi()->up();
        $this->migrasi()->up();

        $nenek = $this->db->query("SHOW TABLES LIKE 'service_dp'")->getResultArray();
        $this->assertTrue($this->db->tableExists('service_dp', false), 'Tabel service_dp tetap ada.');
        $this->assertCount(1, $nenek, 'Tabel service_dp hanya boleh ada satu.');
    }

    public function testDownHanyaMenghapusTabelMilikMigration(): void
    {
        $this->db->query('CREATE TABLE service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');
        $this->db->table('service')->insert(['no_service' => 'TETAP-ADA']);

        $this->migrasi()->up();
        $this->assertTrue($this->db->tableExists('service_dp', false));

        $this->migrasi()->down();

        $this->assertFalse($this->db->tableExists('service_dp', false), 'Down() harus menghapus service_dp.');
        $this->assertTrue($this->db->tableExists('service', false), 'Down() tidak boleh menghapus tabel lain.');
        $this->assertSame(1, (int) $this->db->table('service')->countAllResults(), 'Data tabel lain tidak boleh ikut hilang.');
    }
}