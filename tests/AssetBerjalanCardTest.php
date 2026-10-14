<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi kartu "Total Tanggungan Asset / Hak Cabang / Tagihan Center" di
 * /asset_berjalan (view dashboard/asset_berjalan) untuk unit 5 (Genteng).
 *
 * Unit 5 ber-jenis franchise dan mengikuti aturan Banyuwangi (unit 3):
 *   - Total Tanggungan Asset = 0
 *   - Hak Cabang             = 60% laba
 *   - Tagihan Center         = 40% laba
 * Sebelumnya unit 5 tidak ditangani sama sekali sehingga ketiga kartu tampil
 * Rp 0 (fallback `$total ?? 0`).
 *
 * View diuji terisolasi (tanpa template.php) memakai data persis seperti yang
 * dikirim TutupKasir::assetberjalan().
 */
class AssetBerjalanCardTest extends CIUnitTestCase
{
    /**
     * @param int $unit unit terpilih
     */
    private function render(int $unit, float $omset, float $pengeluaran, float $gaji): string
    {
        $vars = [
            'boleh_pilih_unit' => true,
            'id_jabatan'       => 2,
            'selected_unit'    => $unit,
            'list_unit'        => [
                ['idunit' => $unit, 'NAMA_UNIT' => 'ICLEAR Genteng'],
            ],
            'bulan'            => 10,
            'tahun'            => 2026,
            'bulanSebelum'     => 9,
            'tahunSebelum'     => 2026,
            'periodeLabel'     => 'October 2026',
            'isBulanBerjalan'  => false,
            'hariDalamBulan'   => 31,
            'omset_bulan'      => $omset,
            'pengeluaran'      => $pengeluaran,
            'totalGajiUnit'    => $gaji,
        ];

        $r = service('renderer');
        foreach ($vars as $k => $v) {
            $r->setVar($k, $v);
        }

        return (string) $r->include('dashboard/asset_berjalan');
    }

    /** Ambil nominal (string, mis. "42.000.000") dari kartu berjudul $judul. */
    private function kartu(string $html, string $judul): string
    {
        $pola = '#<h6[^>]*>' . preg_quote($judul, '#') . '</h6>.*?Rp\s+([0-9.]+)#s';

        $this->assertMatchesRegularExpression($pola, $html, "Kartu \"{$judul}\" tidak ditemukan");

        preg_match($pola, $html, $m);

        return $m[1];
    }

    public function testUnit5MengikutiAturanFranchiseBanyuwangi(): void
    {
        // laba = 100jt - 20jt - 10jt = 70jt
        $html = $this->render(5, 100000000, 20000000, 10000000);

        $this->assertSame('0', $this->kartu($html, 'Total Tanggungan Asset'), 'Genteng (franchise) tanggungan 0');
        $this->assertSame('42.000.000', $this->kartu($html, 'Hak Cabang'), 'Hak Cabang 60% x 70jt');
        $this->assertSame('28.000.000', $this->kartu($html, 'Tagihan Center'), 'Tagihan Center 40% x 70jt');
    }

    public function testUnit3TetapFranchiseBanyuwangi(): void
    {
        $html = $this->render(3, 100000000, 20000000, 10000000);

        $this->assertSame('0', $this->kartu($html, 'Total Tanggungan Asset'));
        $this->assertSame('42.000.000', $this->kartu($html, 'Hak Cabang'));
        $this->assertSame('28.000.000', $this->kartu($html, 'Tagihan Center'));
    }

    public function testUnit4TetapFranchisePandaan(): void
    {
        $html = $this->render(4, 100000000, 20000000, 10000000);

        $this->assertSame('14.000.000', $this->kartu($html, 'Total Tanggungan Asset'), 'Pandaan tanggungan 20% x 70jt');
        $this->assertSame('42.000.000', $this->kartu($html, 'Hak Cabang'));
        $this->assertSame('28.000.000', $this->kartu($html, 'Tagihan Center'));
    }

    public function testUnit2TetapPribadiJember(): void
    {
        $html = $this->render(2, 100000000, 20000000, 10000000);

        // pribadi: hari x 355.000 = 31 x 355.000
        $this->assertSame('11.005.000', $this->kartu($html, 'Total Tanggungan Asset'));
        $this->assertSame('0', $this->kartu($html, 'Hak Cabang'));
        $this->assertSame('0', $this->kartu($html, 'Tagihan Center'));
    }

    public function testUnit5TidakMemunculkanWarningPhp(): void
    {
        $html = $this->render(5, 100000000, 20000000, 10000000);

        foreach (['Undefined array key', 'Undefined variable', 'Warning', 'Notice', 'Fatal error', 'Parse error'] as $pola) {
            $this->assertStringNotContainsString($pola, $html, "unit 5: terdeteksi \"{$pola}\"");
        }
    }
}
