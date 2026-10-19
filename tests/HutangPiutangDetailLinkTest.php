<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pelunasan antar unit dicatat langsung dari halaman detail H/P.
 *
 * Form tetap POST ke KasBank::saveAntarUnit() supaya ledger, guard saldo, dan
 * sisa H/P dihitung sekali. View hanya menyediakan rekening + token; halaman
 * Kas & Bank tidak lagi menjadi jalur wajib.
 *
 * @internal
 */
final class HutangPiutangDetailLinkTest extends CIUnitTestCase
{
    public function testDetailHutangMutasiMenampilkanFormPelunasanLangsung(): void
    {
        $html = $this->renderDetail('hutang', $this->antarUnitData());

        $this->assertStringContainsString('kas_bank/antar-unit/save', $html);
        $this->assertStringContainsString('name="akun_pengirim_id"', $html);
        $this->assertStringContainsString('name="akun_penerima_id"', $html);
        $this->assertStringContainsString('name="hutang_piutang_id" value="901"', $html);
        $this->assertStringContainsString('name="kembali_ke" value="hutangpiutang/detail/10"', $html);
        $this->assertStringContainsString('Catat Pelunasan Antar Unit', $html);
        $this->assertStringNotContainsString('Lunasi di Kas', $html);
    }

    public function testDetailPiutangMutasiJugaBisaCatatPelunasan(): void
    {
        $html = $this->renderDetail('piutang', $this->antarUnitData());

        $this->assertStringContainsString('kas_bank/antar-unit/save', $html);
        // Yang dibayar tetap baris hutang pasangannya, bukan baris piutang.
        $this->assertStringContainsString('name="hutang_piutang_id" value="901"', $html);
    }

    public function testDetailTanpaDataAntarUnitTidakMenampilkanForm(): void
    {
        $html = $this->renderDetail('hutang');

        $this->assertStringNotContainsString('kas_bank/antar-unit/save', $html);
        $this->assertStringContainsString('belum bisa dicatat dari halaman ini', $html);
    }

    private function antarUnitData(): array
    {
        return [
            'hutang' => (object) ['id' => 901, 'kode' => 'H-901', 'unit_id' => 1, 'sisa' => 1000],
            'piutang' => (object) ['id' => 902, 'kode' => 'P-902', 'unit_id' => 2, 'sisa' => 1000],
            'akun_pengirim' => [
                (object) ['idakun_kas_bank' => 11, 'nama_akun' => 'Kas Unit A', 'tipe' => 'KAS', 'unit_id' => 1],
            ],
            'akun_penerima' => [
                (object) ['idakun_kas_bank' => 12, 'nama_akun' => 'Kas Unit B', 'tipe' => 'KAS', 'unit_id' => 2],
            ],
            'unit_map' => [1 => 'Unit A', 2 => 'Unit B'],
            'batas_jumlah' => 1000,
            'submit_token' => 'tok-antar-unit',
            'kembali_ke' => 'hutangpiutang/detail/10',
            'periode_mulai' => '2026-10-01',
        ];
    }

    private function renderDetail(string $jenis, ?array $antarUnit = null): string
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
            'antar_unit' => $antarUnit,
        ]);
    }
}
