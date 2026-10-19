<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * #12: detail H/P mutasi antar unit harus mengarahkan sisi penghutang ke
 * Kas & Bank → Antar Unit, tanpa memberi tombol bayar ke sisi piutang.
 *
 * @internal
 */
final class HutangPiutangDetailLinkTest extends CIUnitTestCase
{
    public function testDetailHutangMutasiMenampilkanTautanLunasiAntarUnit(): void
    {
        $html = $this->renderDetail('hutang');

        $this->assertStringContainsString('kas_bank/antar-unit?unit_id=1', $html);
        $this->assertStringContainsString('Lunasi di Kas', $html);
    }

    public function testDetailPiutangMutasiTidakMenampilkanTautanLunasi(): void
    {
        $html = $this->renderDetail('piutang');

        $this->assertStringNotContainsString('kas_bank/antar-unit?unit_id=', $html);
        $this->assertStringContainsString('Pelunasan dilakukan unit yang berhutang', $html);
    }

    private function renderDetail(string $jenis): string
    {
        return view('hutangpiutang/detail', [
            'row' => (object) [
                'id' => 10,
                'kode' => 'MUT-1-2',
                'jenis' => $jenis,
                'sumber_tipe' => 'mutasi_unit',
                'sumber_id' => 1,
                'is_projection' => 0,
                'nama_pihak' => 'Unit Lain',
                'tanggal' => '2026-10-19',
                'jatuh_tempo' => '2026-10-22',
                'uraian' => 'Mutasi stok',
                'total' => 1000,
                'total_dibayar' => 0,
                'sisa' => 1000,
                'status' => 'belum_lunas',
                'keterangan' => null,
                'unit_id' => $jenis === 'hutang' ? 1 : 2,
                'lawan_unit_id' => $jenis === 'hutang' ? 2 : 1,
                'cutoff_reason' => null,
                'tanggal_settlement_legacy' => null,
                'legacy_dibatalkan_at' => null,
                'cutoff_closed_by' => null,
                'cutoff_closed_at' => null,
                'sisa_legacy_sebelum' => null,
                'legacy_dibatalkan_by' => null,
                'legacy_alasan_pembatalan' => null,
            ],
            'unit' => (object) ['NAMA_UNIT' => 'Unit 1'],
            'pembayaran' => [],
            'can_input' => true,
            'lawan_kompensasi' => [],
            'bank' => [],
        ]);
    }
}
