<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi tampilan daftar Hutang/Piutang: ringkasan, filter status, tabel
 * yang lebih ringkas, dan empty state. Tidak menyentuh database.
 *
 * @internal
 */
final class HutangPiutangListRedesignTest extends CIUnitTestCase
{
    public function testDaftarPiutangMenampilkanRingkasanDanBaris(): void
    {
        $rows = [
            $this->row([
                'id' => 1,
                'kode' => 'PLG-20261019-0001',
                'sumber_tipe' => 'piutang_pelanggan',
                'nama_pihak' => 'Toko Sinar Jaya',
                'total' => 1000000,
                'total_dibayar' => 250000,
                'sisa' => 750000,
                'status' => 'sebagian',
                'jatuh_tempo' => date('Y-m-d', strtotime('-3 days')),
            ]),
            $this->row([
                'id' => 2,
                'kode' => 'PLG-20261019-0002',
                'sumber_tipe' => 'kasbon',
                'nama_pihak' => 'Budi',
                'total' => 500000,
                'total_dibayar' => 500000,
                'sisa' => 0,
                'status' => 'lunas',
                'jatuh_tempo' => date('Y-m-d', strtotime('+10 days')),
            ]),
        ];

        $html = $this->render($rows, 'piutang');

        $this->assertStringContainsString('Daftar Piutang', $html);
        $this->assertStringContainsString('hp-board', $html);
        $this->assertStringContainsString('Sisa piutang', $html);
        $this->assertStringContainsString('Rp 750.000', $html);
        $this->assertStringContainsString('Rp 1.000.000', $html);
        $this->assertStringContainsString('Terlambat 3 hari', $html);
        $this->assertStringContainsString('PLG-20261019-0001', $html);
        $this->assertStringContainsString('Mutasi Stok Antar Unit', $html);
        $this->assertStringContainsString('1 sebagian', $html);
        $this->assertStringContainsString('1 lunas', $html);
    }

    public function testFilterStatusAktifTetapTerpilih(): void
    {
        $html = $this->render([], 'piutang', ['status' => 'sebagian']);

        $this->assertStringContainsString('value="sebagian" checked', $html);
        $this->assertStringContainsString('Tidak ada hasil', $html);
    }

    public function testDaftarHutangKosongMenampilkanEmptyStateDanCatatanSupplier(): void
    {
        $html = $this->render([], 'hutang');

        $this->assertStringContainsString('Daftar Hutang', $html);
        $this->assertStringContainsString('hp-empty', $html);
        $this->assertStringContainsString('Belum ada dokumen', $html);
        $this->assertStringContainsString('Daftar Tagihan Hutang', $html);
    }

    public function testBarisMutasiAntarUnitMenyediakanAksiLunasi(): void
    {
        $rows = [
            $this->row([
                'id' => 7,
                'kode' => 'HUT-20261019-0007',
                'sumber_tipe' => 'mutasi_unit',
                'jenis' => 'hutang',
                'nama_pihak' => 'Unit B',
                'total' => 500000,
                'total_dibayar' => 0,
                'sisa' => 500000,
                'status' => 'belum_lunas',
                'scope' => 'active',
            ]),
        ];

        $html = $this->render($rows, 'hutang', [], true);

        $this->assertStringContainsString('hutangpiutang/detail/7?bayar=1', $html);
        $this->assertStringContainsString('>Lunasi</a>', $html);
    }

    private function render(array $rows, string $jenis, array $filters = [], bool $canInput = false): string
    {
        $filters = array_merge([
            'jenis' => $jenis,
            'status' => '',
            'sumber_tipe' => '',
            'unit_id' => null,
            'bulan' => '',
            'q' => '',
            'scope' => '',
        ], $filters);

        return view('hutangpiutang/index', [
            'jenis' => $jenis,
            'rows' => $rows,
            'filters' => $filters,
            'units' => [],
            'unit_id' => $filters['unit_id'],
            'can_input' => $canInput,
            'input_types' => [],
            'pelanggan' => [],
            'pegawai' => [],
            'suplier' => [],
            'teknisi' => [],
            'bank' => [],
        ]);
    }

    private function row(array $override = []): array
    {
        return array_merge([
            'id' => 1,
            'kode' => 'PLG-20261019-0001',
            'jenis' => 'piutang',
            'sumber_tipe' => 'piutang_pelanggan',
            'is_projection' => 0,
            'pihak_tipe' => 'pelanggan',
            'pihak_id' => 1,
            'nama_pihak' => 'Toko Sinar Jaya',
            'tanggal' => date('Y-m-d', strtotime('-20 days')),
            'jatuh_tempo' => date('Y-m-d', strtotime('+7 days')),
            'uraian' => 'Tagihan contoh',
            'total' => 1000000,
            'total_dibayar' => 0,
            'sisa' => 1000000,
            'status' => 'belum_lunas',
            'scope' => 'active',
            'cutoff_reason' => null,
            'tanggal_settlement_legacy' => null,
            'NAMA_UNIT' => 'Unit Probolinggo',
        ], $override);
    }
}
