<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Halaman garansi service memakai tema .rsg-*, tetap server-side, dan
 * filter unit mengikuti role.
 */
final class GaransiServiceViewTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testMemakaiTemaRsgDanDataTablesServerSide(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [],
            'can_see_all_units' => true,
        ]);

        $this->assertStringContainsString('class="rsg-page"', $html);
        $this->assertStringContainsString('max-width: none;', $html);
        $this->assertStringContainsString('id="gsTable"', $html);
        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('garansi_service/ajax', $html);
        $this->assertStringContainsString('id="modalRiwayatKlaim"', $html);
        $this->assertStringContainsString('row.tipe_hp', $html);
        $this->assertStringContainsString('id="exportForm"', $html);
        $this->assertStringContainsString('name="sumber" value="garansi"', $html);
        $this->assertStringContainsString('claim_garansi/detail', $html);
        $this->assertStringContainsString('riwayat-klaim', $html);
        $this->assertStringContainsString('Sudah Diklaim', $html);
        $this->assertStringContainsString('rsg-btn--claim:disabled', $html);
        $this->assertStringContainsString('td.dataTables_empty .rsg-empty', $html);
        $this->assertStringContainsString("data-label', 'Pelanggan'", $html);
        $this->assertStringContainsString("data-label', 'Aksi'", $html);
        $this->assertStringContainsString('id="modalRefundGaransi"', $html);
        $this->assertStringContainsString('btn-refund', $html);
        $this->assertStringContainsString('garansi_service/refund', $html);
    }

    public function testRouteRefundTerdaftar(): void
    {
        // Tanpa sesi, filter auth mengalihkan. 404 berarti route belum terdaftar.
        $this->post('garansi_service/refund')->assertRedirect();
        $this->get('garansi_service/refund/detail/1')->assertRedirect();
    }

    public function testRefundFormMenampilkanHppDanHargaJualSparepart(): void
    {
        $html = view('transaksi/table_garansi/refund_form', [
            'service' => (object) [
                'idservice'       => 7,
                'no_service'      => 'SRV-007',
                'nama_pelanggan'  => 'Pelanggan Tes',
                'dp_bayar'        => 100000,
                'bayar'           => 200000,
            ],
            'bank' => [],
            'sparepart' => [
                (object) [
                    'idservice_sparepart'       => 11,
                    'kode_barang'               => 'BRG-1',
                    'nama_barang'               => 'Sparepart Tes',
                    'jumlah'                    => 2,
                    'jumlah_tambahan_garansi'   => 1,
                    'hpp_penjualan'             => 1000,
                    'harga_penjualan'           => 5000,
                    'hpp_penjualan_garansi'     => 800,
                    'harga_penjualan_garansi'   => 4000,
                ],
            ],
            'refunded' => false,
        ]);

        $this->assertStringContainsString('<th>HPP</th>', $html);
        $this->assertStringContainsString('<th>Harga Jual</th>', $html);
        $this->assertStringContainsString('Rp 2.800', $html);
        $this->assertStringContainsString('Rp 14.000', $html);

        // Kondisi tiap baris wajib dipilih, bukan opsional.
        $this->assertStringContainsString('Wajib dipilih untuk setiap baris', $html);
        $this->assertStringContainsString('name="kondisi[11]" value="baik" aria-required="true" required', $html);
        $this->assertStringContainsString('name="kondisi[11]" value="rusak" aria-required="true" required', $html);
    }

    public function testRoleLintasUnitMendapatFilterUnit(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit Tes'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ],
            'can_see_all_units' => true,
        ]);

        $this->assertStringContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('<option value="1">Unit Tes</option>', $html);
        $this->assertStringContainsString('<option value="2">Unit Dua</option>', $html);
        $this->assertStringNotContainsString('style="display:none">Unit</th>', $html);
    }

    public function testRoleUnitTerkunciTidakMendapatSelectUnit(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [],
            'can_see_all_units' => false,
        ]);

        $this->assertStringNotContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('class="rsg-locked"', $html);
        $this->assertStringContainsString('style="display:none">Unit</th>', $html);
    }
}
