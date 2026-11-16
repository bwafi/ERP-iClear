<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Migrasi manual book (issue #213) harus menambah PDF baru tanpa menghapus
 * nama manual lama, dan aman dijalankan ulang.
 */
class AddPanduanManualbookMigrationTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->query('CREATE TABLE menu (
            idmenu INTEGER PRIMARY KEY,
            url TEXT NULL,
            manualbook TEXT NULL
        )');

        $this->db->table('menu')->insertBatch([
            ['idmenu' => 1, 'url' => 'pembelian', 'manualbook' => 'Pembelian Barang.pdf'],
            ['idmenu' => 2, 'url' => 'kartu_stok', 'manualbook' => 'Stok Produk.pdf'],
            ['idmenu' => 3, 'url' => 'tarif_jasa', 'manualbook' => null],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): \App\Database\Migrations\AddPanduanPembelianHppManualbook
    {
        require_once APPPATH . 'Database/Migrations/2026-11-16-000200_AddPanduanPembelianHppManualbook.php';

        return new \App\Database\Migrations\AddPanduanPembelianHppManualbook();
    }

    private function manualbook(string $url): ?string
    {
        $row = $this->db->table('menu')->where('url', $url)->get()->getRow();

        return $row->manualbook ?? null;
    }

    public function testUpMenambahPanduanTanpaMenghapusManualLama(): void
    {
        $this->migrasi()->up();

        $this->assertSame('Panduan Pembelian.pdf|Pembelian Barang.pdf', $this->manualbook('pembelian'));
        $this->assertSame(
            'Panduan HPP dan Harga Jual per Unit.pdf|Stok Produk.pdf',
            $this->manualbook('kartu_stok')
        );
        $this->assertSame('Panduan HPP dan Harga Jual per Unit.pdf', $this->manualbook('tarif_jasa'));
    }

    public function testUpDijalankanUlangTidakDuplikat(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->up();

        $this->assertSame('Panduan Pembelian.pdf|Pembelian Barang.pdf', $this->manualbook('pembelian'));
        $this->assertSame('Panduan HPP dan Harga Jual per Unit.pdf', $this->manualbook('tarif_jasa'));
    }

    public function testDownMengembalikanNilaiLama(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->down();

        $this->assertSame('Pembelian Barang.pdf', $this->manualbook('pembelian'));
        $this->assertSame('Stok Produk.pdf', $this->manualbook('kartu_stok'));
        $this->assertNull($this->manualbook('tarif_jasa'));
    }
}
