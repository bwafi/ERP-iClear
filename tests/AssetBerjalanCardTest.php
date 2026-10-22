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
    private function render(int $unit, float $omset, float $pengeluaran, float $gaji, ?array $gajiDetail = null): string
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
            'gaji_detail'      => $gajiDetail,
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

    /** Baris rincian gaji contoh; total = 3.000.000 seperti $totalGajiUnit. */
    private function detailContoh(): array
    {
        return [
            [
                'nama' => 'Budi Santoso', 'jabatan' => 'Teknisi',
                'gaji_pokok' => 1500000.0, 'tunjangan_kinerja' => 1000000.0,
                'tunjangan_absen' => 0.0, 'tunjangan_penempatan' => 350000.0,
                'insentif' => 150000.0, 'total' => 3000000.0,
            ],
        ];
    }

    public function testRincianGajiMunculBilaDataDikirim(): void
    {
        $html = $this->render(1, 100000000, 20000000, 3000000, $this->detailContoh());

        $this->assertStringContainsString('Lihat rincian', $html, 'tombol rincian tidak dirender');
        $this->assertStringContainsString('id="modalRincianGaji"', $html, 'modal rincian tidak dirender');
        $this->assertStringContainsString('Budi Santoso', $html, 'nama pegawai tidak muncul');
        $this->assertStringContainsString('Teknisi', $html, 'jabatan tidak muncul');

        // Setiap komponen muncul dengan nominalnya sendiri.
        foreach (['1.500.000', '1.000.000', '350.000', '150.000'] as $nominal) {
            $this->assertStringContainsString($nominal, $html, "komponen {$nominal} tidak muncul");
        }
    }

    public function testRincianGajiTidakDirenderTanpaData(): void
    {
        $html = $this->render(1, 100000000, 20000000, 3000000);

        $this->assertStringNotContainsString('Lihat rincian', $html, 'tombol rincian bocor ke yang tak berhak');
        $this->assertStringNotContainsString('modalRincianGaji', $html, 'modal bocor ke yang tak berhak');
        $this->assertStringNotContainsString('Budi Santoso', $html);
    }

    public function testTotalRincianSamaDenganAngkaKartu(): void
    {
        $html = $this->render(1, 100000000, 20000000, 3000000, $this->detailContoh());

        $pola = '#<td[^>]*rincian-gaji-total[^>]*>\s*Rp\s+([0-9.]+)#';
        $this->assertMatchesRegularExpression($pola, $html, 'baris total rincian tidak ada');
        preg_match($pola, $html, $m);

        $this->assertSame($this->kartu($html, 'Total Gaji'), $m[1], 'total rincian harus sama dengan kartu');
    }

    public function testHanyaAdminRootFinanceManagerYangBolehLihatRincian(): void
    {
        foreach ([0, 1, 34] as $jabatan) {
            $this->assertTrue(
                \App\Controllers\TutupKasir::bolehLihatRincianGaji($jabatan),
                "jabatan {$jabatan} seharusnya boleh melihat rincian gaji"
            );
        }

        foreach ([2, 35, 36, 40, 41, 43, 44, 46] as $jabatan) {
            $this->assertFalse(
                \App\Controllers\TutupKasir::bolehLihatRincianGaji($jabatan),
                "jabatan {$jabatan} tidak boleh melihat rincian gaji"
            );
        }
    }

    /** Panel gaji HO (agregat HO) hanya untuk role lintas cabang. */
    public function testHanyaRoleLintasUnitYangBolehLihatPanelHo(): void
    {
        foreach ([0, 1, 2, 34, 40] as $jabatan) {
            $this->assertTrue(
                \App\Controllers\TutupKasir::bolehLihatPanelHo($jabatan),
                "jabatan {$jabatan} seharusnya boleh melihat panel gaji HO"
            );
        }

        foreach ([35, 36, 37, 41, 42, 43, 44, 45, 46, 48] as $jabatan) {
            $this->assertFalse(
                \App\Controllers\TutupKasir::bolehLihatPanelHo($jabatan),
                "jabatan {$jabatan} tidak boleh melihat panel gaji HO"
            );
        }
    }
}
