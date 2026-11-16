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

    private function render(array $rows, array $rincian = []): string
    {
        helper('url');

        return (string) view('hutang/daftar_tagihan', [
            'hutang' => $rows,
            'rincian' => $rincian,
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

    public function testRingkasanBarangAdaDiBarisDanRincianLengkapDiModal(): void
    {
        $html = $this->render($this->rows(), [
            11 => [
                (object) [
                    'pembelian_idpembelian' => 11,
                    'nama_barang' => 'LCD iPhone 11',
                    'kode_barang' => 'P01',
                    'jumlah' => 2,
                    'satuan_beli' => 'Pcs',
                    'hrg_beli' => 100000,
                    'diskon' => 0,
                    'total_harga' => 200000,
                ],
                (object) [
                    'pembelian_idpembelian' => 11,
                    'nama_barang' => 'Baterai iPhone 11',
                    'kode_barang' => 'P02',
                    'jumlah' => 3,
                    'satuan_beli' => 'Pcs',
                    'hrg_beli' => 50000,
                    'diskon' => 5000,
                    'total_harga' => 0, // legacy: subtotal dihitung ulang
                ],
                (object) [
                    'pembelian_idpembelian' => 11,
                    'nama_barang' => 'Konektor Charger',
                    'kode_barang' => 'P03',
                    'jumlah' => 1,
                    'satuan_beli' => '',
                    'hrg_beli' => 75000,
                    'diskon' => 0,
                    'total_harga' => 75000,
                ],
            ],
        ]);

        // Ringkasan baris: jumlah + dua nama pertama, sisanya diringkas.
        $this->assertStringContainsString('3 barang · LCD iPhone 11, Baterai iPhone 11 +1', $html);
        $this->assertStringContainsString('title="LCD iPhone 11, Baterai iPhone 11, Konektor Charger"', $html);

        // Sumber rincian untuk modal: satu template per nota, hanya nota berisi detail.
        $this->assertStringContainsString('id="dt-items-store"', $html);
        $this->assertStringContainsString('data-items-for="11"', $html);
        $this->assertStringNotContainsString('data-items-for="12"', $html);

        // Rincian item: nama, kode, qty × satuan × harga, diskon, subtotal.
        $this->assertStringContainsString('LCD iPhone 11', $html);
        $this->assertStringContainsString('P01', $html);
        $this->assertStringContainsString('2 Pcs', $html);
        $this->assertStringContainsString('× Rp 100.000', $html);
        $this->assertStringContainsString('Rp 200.000', $html);
        $this->assertStringContainsString('Diskon Rp 5.000', $html);
        $this->assertStringContainsString('Rp 145.000', $html);
        $this->assertStringContainsString('Konektor Charger', $html);

        // Kontainer modal dan fallback nota tanpa detail.
        $this->assertStringContainsString('id="dt-pay-items"', $html);
        $this->assertStringContainsString('id="dt-pay-items-count"', $html);
        $this->assertStringContainsString('Rincian barang tidak tersedia untuk nota ini.', $html);
        $this->assertStringContainsString('Rincian barang tidak tersedia', $html);
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
