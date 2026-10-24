<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman klaim: sparepart service otomatis muncul di tabel Sparepart Rusak
 * dan user memilih sendiri part mana yang diklaim.
 */
final class GaransiClaimDetailPrefillTest extends CIUnitTestCase
{
    private function render(array $serviceSparepart, array $sparepart = []): string
    {
        $service = (object) [
            'idservice' => 1,
            'no_service' => 'SRV-001',
            'nama' => 'Ayu',
            'no_hp' => '0811',
            'alamat' => 'Alamat',
            'tipe_hp' => 'HP',
            'imei' => '123',
            'unit_idunit' => 1,
            'tanggal_selesai' => '2026-10-01 10:00:00',
            'keluhan' => 'LCD rusak',
            'keterangan' => '-',
        ];

        return view('transaksi/table_garansi/claim_detail', [
            'service' => $service,
            'oldkerusakan' => [],
            'service_sparepart' => $serviceSparepart,
            'fungsiMap' => [],
            'sparepart' => $sparepart,
            'warranty_end' => null,
            'status_garansi' => 'Aktif',
        ]);
    }

    public function testSparepartServiceTampilDenganPilihanKlaim(): void
    {
        $html = $this->render([
            (object) ['barang_idbarang' => 10, 'nama_barang' => 'LCD Tes', 'jumlah' => 2],
            (object) ['barang_idbarang' => 10, 'nama_barang' => 'LCD Tes', 'jumlah' => 1],
            (object) ['barang_idbarang' => 11, 'nama_barang' => 'Baterai Tes', 'jumlah' => 1],
        ]);

        $this->assertStringContainsString('id="damaged-row-10"', $html);
        $this->assertStringContainsString('name="barang_rusak[0][id]" value="10"', $html);
        $this->assertStringContainsString('name="barang_rusak[0][qty]" value="3"', $html);
        $this->assertStringContainsString('name="barang_rusak[1][id]" value="11"', $html);
        $this->assertStringContainsString('name="barang_rusak[1][qty]" value="1"', $html);
        $this->assertSame(1, substr_count($html, 'id="damaged-row-10"'));
        $this->assertStringNotContainsString('damaged-qty', $html);
        $this->assertStringNotContainsString('Tambah Sparepart Rusak', $html);
        $this->assertStringNotContainsString('damagedPartModal', $html);
        $this->assertLessThan(
            strpos($html, 'Sparepart Pengganti'),
            strpos($html, 'Sparepart Rusak')
        );
    }

    public function testServiceTanpaSparepartMenampilkanPesanKosong(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('Tidak ada sparepart di service ini.', $html);
    }

    public function testModalPenggantiMemakaiDesainPemilihSparepart(): void
    {
        $html = $this->render([], [
            (object) [
                'idbarang' => 10,
                'nama_barang' => 'LCD Tes',
                'kode_barang' => 'SP-10',
                'stok_akhir' => 5,
            ],
        ]);

        $this->assertStringContainsString('id="replacementPartModal"', $html);
        $this->assertStringContainsString('id="searchReplacementInput"', $html);
        $this->assertStringContainsString('id="replacementPartList"', $html);
        $this->assertStringContainsString('id="replacement-selected-count"', $html);
        $this->assertStringContainsString('class="service-sp-item__input replacement-check"', $html);
        $this->assertStringContainsString('class="service-sp-stock"', $html);
        $this->assertStringContainsString('Gratis', $html);
        $this->assertStringNotContainsString('id="replacementPartTable"', $html);
    }
}
