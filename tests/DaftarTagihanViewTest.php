<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Smoke test view Daftar Tagihan Hutang.
 *
 * Menguji perhitungan ringkasan/urgensi jatuh tempo dan kontrak form yang
 * dibutuhkan controller (`export_daftar_hutang` & `update_cicilan_hutang`)
 * tetap ada setelah redesign. Tidak menyentuh database.
 */
class DaftarTagihanViewTest extends CIUnitTestCase
{
    private function rows(): array
    {
        $hari = static fn(int $offset): string => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));

        return [
            (object) [
                'idpembelian' => 11,
                'no_nota_supplier' => 'PCR1001',
                'NAMA_UNIT' => 'ICLEAR Jember',
                'tanggal_masuk' => $hari(-10),
                'jatuh_tempo' => $hari(-5),
                'sisa' => 500000,
                'total_bayar' => 500000,
                'total_transaksi' => 1000000,
                'status' => 'Belum Lunas',
            ],
            (object) [
                'idpembelian' => 12,
                'no_nota_supplier' => 'PCR1002',
                'NAMA_UNIT' => 'ICLEAR Genteng',
                'tanggal_masuk' => $hari(-2),
                'jatuh_tempo' => $hari(2),
                'sisa' => 1000000,
                'total_bayar' => 0,
                'total_transaksi' => 1000000,
                'status' => 'Belum Lunas',
            ],
            (object) [
                'idpembelian' => 13,
                'no_nota_supplier' => 'PCR1003',
                'NAMA_UNIT' => 'ICLEAR Jember',
                'tanggal_masuk' => $hari(-1),
                'jatuh_tempo' => $hari(30),
                'sisa' => 250000,
                'total_bayar' => 0,
                'total_transaksi' => 250000,
                'status' => 'Belum Lunas',
            ],
        ];
    }

    private function render(array $rows): string
    {
        helper('url');

        return (string) view('hutang/daftar_tagihan', [
            'hutang' => $rows,
            'bank'   => [
                (object) [
                    'idbank'     => 7,
                    'nama_bank'  => 'BCA',
                    'atas_nama'  => 'SABRINA RATU SALSABILLA',
                    'norek'      => '1234567890',
                ],
            ],
        ]);
    }

    public function testRingkasanDanStatusJatuhTempoDihitungDariData(): void
    {
        $html = $this->render($this->rows());

        $this->assertStringContainsString('Tagihan Supplier', $html);

        // 3 tagihan: satu terlambat, satu ≤7 hari, satu aman.
        $this->assertStringContainsString('Tagihan terbuka', $html);
        $this->assertStringContainsString('Rp 1.750.000', $html);
        $this->assertStringContainsString('Terlambat', $html);
        $this->assertStringContainsString('≤7 hari', $html);
        $this->assertStringContainsString('Aman', $html);
        $this->assertStringContainsString('Terlambat 5 hari', $html);
        $this->assertStringContainsString('2 hari lagi', $html);
    }

    public function testKontrakExportDanFormBayarTetapAda(): void
    {
        $html = $this->render($this->rows());

        // Filter/export.
        $this->assertStringContainsString('action="' . base_url('export_daftar_hutang') . '"', $html);
        $this->assertStringContainsString('name="tanggal_awal"', $html);
        $this->assertStringContainsString('name="tanggal_akhir"', $html);
        $this->assertStringContainsString('name="nama_unit"', $html);

        // Form bayar: field yang dibaca PembayaranHutang::insert_cicilan.
        $this->assertStringContainsString('action="' . base_url('update_cicilan_hutang') . '"', $html);
        $this->assertStringContainsString('name="idpembelian"', $html);
        $this->assertStringContainsString('name="bayar_tunai"', $html);
        $this->assertStringContainsString('name="bayar_bank"', $html);
        $this->assertStringContainsString('name="bank_idbank"', $html);
        $this->assertStringContainsString('name="sisa"', $html);
        $this->assertStringContainsString('data-sisa="500000"', $html);
        $this->assertStringContainsString('data-idpembelian="12"', $html);
        $this->assertStringContainsString('BCA', $html);
        // Label rekening = nama bank + pemilik; norek tidak ditampilkan.
        $this->assertStringContainsString('BCA · SABRINA RATU SALSABILLA', $html);
        $this->assertStringNotContainsString('1234567890', $html);

        // Hanya satu metode pembayaran yang tampil pada satu waktu.
        $this->assertStringContainsString('data-dt-method="tunai"', $html);
        $this->assertStringContainsString('data-dt-method="bank"', $html);
        $this->assertStringContainsString('data-dt-panel="tunai"', $html);
        $this->assertMatchesRegularExpression('/data-dt-panel="bank"[^>]*hidden/', $html);

        // Tidak memakai id DataTables global (hindari init ganda).
        $this->assertStringNotContainsString('id="zero_config"', $html);
    }

    public function testHeaderMemakaiPermukaanKartuBukanTransparan(): void
    {
        $html = $this->render($this->rows());

        // DESIGN.md: page head adalah kartu putih ber-hairline, bukan teks
        // transparan yang menyatu dengan body background.
        $this->assertMatchesRegularExpression(
            '/\.dt-head\s*\{[^}]*background:\s*var\(--dt-surface\)/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/\.dt-head\s*\{[^}]*border:\s*1px solid var\(--dt-rule\)/s',
            $html
        );
        // Header tabel juga putih, bukan pita abu-abu di atas lembar.
        $this->assertMatchesRegularExpression(
            '/\.dt-table th\s*\{[^}]*background:\s*var\(--dt-surface\)/s',
            $html
        );
    }

    public function testTanpaTagihanMenampilkanEmptyStateTanpaTabel(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('Tidak ada tagihan terbuka', $html);
        $this->assertStringNotContainsString('id="tagihan-table"', $html);
        $this->assertStringContainsString('Rp 0', $html);
    }

    public function testTanpaRekeningBankPembayaranBankDinonaktifkan(): void
    {
        helper('url');

        $html = (string) view('hutang/daftar_tagihan', [
            'hutang' => $this->rows(),
            'bank'   => [],
        ]);

        $this->assertStringContainsString('Belum ada rekening', $html);
        $this->assertStringContainsString('Pembayaran bank nonaktif', $html);
        $this->assertMatchesRegularExpression('/id="dt-pay-bank"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/data-dt-method="bank"[^>]*disabled/', $html);
    }
}
