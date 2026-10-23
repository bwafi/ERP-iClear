<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman klaim: sparepart yang sudah dipakai service otomatis muncul
 * di tabel Sparepart Rusak.
 */
final class GaransiClaimDetailPrefillTest extends CIUnitTestCase
{
    public function testSparepartServiceOtomatisMasukTabelRusak(): void
    {
        $service = (object) [
            'idservice' => 1,
            'no_service' => 'SRV-001',
            'nama' => 'Ayu',
            'no_hp' => '0811',
            'alamat' => 'Alamat',
            'tipe_hp' => 'HP',
            'imei' => '123',
            'tanggal_selesai' => '2026-10-01 10:00:00',
            'keluhan' => 'LCD rusak',
            'keterangan' => '-',
        ];

        $html = view('transaksi/table_garansi/claim_detail', [
            'service' => $service,
            'oldkerusakan' => [],
            'service_sparepart' => [
                (object) ['barang_idbarang' => 10, 'nama_barang' => 'LCD Tes', 'jumlah' => 2],
                (object) ['barang_idbarang' => 10, 'nama_barang' => 'LCD Tes', 'jumlah' => 1],
                (object) ['barang_idbarang' => 11, 'nama_barang' => 'Baterai Tes', 'jumlah' => 1],
            ],
            'fungsiMap' => [],
            'sparepart' => [],
            'sparepart_rusak' => [],
            'warranty_end' => null,
            'status_garansi' => 'Aktif',
        ]);

        $this->assertStringContainsString('id="damaged-row-10"', $html);
        $this->assertStringContainsString('name="barang_rusak[0][id]" value="10"', $html);
        $this->assertStringContainsString('name="barang_rusak[0][qty]" value="3"', $html);
        $this->assertStringContainsString('name="barang_rusak[1][id]" value="11"', $html);
        $this->assertStringContainsString('name="barang_rusak[1][qty]" value="1"', $html);
        $this->assertSame(1, substr_count($html, 'id="damaged-row-10"'));
        $this->assertStringContainsString('let damagedIndex = 2;', $html);
    }
}
