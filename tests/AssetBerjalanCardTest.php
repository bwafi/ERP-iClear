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
    private function render(int $unit, float $omset, float $pengeluaran, float $gaji, ?array $gajiDetail = null, ?array $hoDistribution = null, ?array $hoUnitShare = null, float $biayaLain = 0.0): string
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
            'biaya_lain'       => $biayaLain,
            'totalGajiUnit'    => $gaji,
            'gaji_detail'      => $gajiDetail,
            'ho_distribution'  => $hoDistribution,
            'ho_unit_share'    => $hoUnitShare,
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

    /**
     * Ikon sel Total Tanggungan Asset harus nama yang benar-benar ada di set
     * Iconify Solar. `solar:vault-bold-duotone` tidak ada, sehingga sel tampil
     * tanpa ikon.
     */
    public function testIkonTotalTanggunganAssetMemakaiNamaSolarYangValid(): void
    {
        $html = $this->render(5, 100000000, 20000000, 10000000);

        $this->assertMatchesRegularExpression(
            '#<iconify-icon icon="solar:safe-2-bold-duotone"[^>]*>\s*</iconify-icon>\s*<h6[^>]*>Total Tanggungan Asset</h6>#s',
            $html
        );
        $this->assertStringNotContainsString('solar:vault-bold-duotone', $html);
    }

    public function testUnit4TetapFranchisePandaan(): void
    {
        $html = $this->render(4, 100000000, 20000000, 10000000);

        $this->assertSame('14.000.000', $this->kartu($html, 'Total Tanggungan Asset'), 'Pandaan tanggungan 20% x 70jt');
        // Bagi hasil dari sisa setelah tanggungan: 70jt - 14jt = 56jt.
        $this->assertSame('33.600.000', $this->kartu($html, 'Hak Cabang'), '60% x 56jt');
        $this->assertSame('22.400.000', $this->kartu($html, 'Tagihan Center'), '40% x 56jt');
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

    /** Ringkasan modal menjumlah komponen, bukan menampilkan angka baru. */
    public function testRingkasanRincianGajiMenjumlahKomponen(): void
    {
        $html = $this->render(1, 100000000, 20000000, 3000000, $this->detailContoh());

        foreach ([
            'gajiSummaryTotal'     => '3.000.000',
            'gajiSummaryPokok'     => '1.500.000',
            'gajiSummaryTunjangan' => '1.350.000',
            'gajiSummaryTambahan'  => '150.000',
        ] as $id => $nominal) {
            $this->assertMatchesRegularExpression(
                '#id="' . $id . '"[^>]*>\s*Rp\s+' . preg_quote($nominal, '#') . '#',
                $html,
                "ringkasan {$id} tidak sesuai"
            );
        }
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

    /** Lembar posisi: 5 pengurang + satu bar hasil, bukan tumpukan kartu metrik. */
    public function testLembarPosisiMenampilkanPersamaanDanLabaBersih(): void
    {
        $html = $this->render(5, 100000000, 20000000, 10000000, null, $this->hoDistribution(true));

        $this->assertSame(6, substr_count($html, 'class="posisi-cell'), 'persamaan harus 6 sel');
        $this->assertStringContainsString('Laba Bersih', $html);
        $this->assertStringContainsString('Gaji HO', $html);
        $this->assertStringContainsString('Rp 70.000.000', $html, 'tanpa bagian HO: laba = omset - biaya - gaji');
    }

    /** Biaya iklan/SDM/lain-lain ikut mengurangi laba bersih dan turunannya. */
    public function testLabaBersihDikurangiBiayaIklanSdmLainLain(): void
    {
        $html = $this->render(5, 100000000, 20000000, 10000000, null, null, null, 5000000);

        $this->assertSame('5.000.000', $this->kartu($html, 'Biaya Iklan, SDM &amp; Lain-lain'));
        // laba = 100jt - 20jt - 10jt - 5jt = 65jt
        $this->assertSame('65.000.000', $this->kartu($html, 'Laba Bersih'));
        $this->assertSame('39.000.000', $this->kartu($html, 'Hak Cabang'), '60% x 65jt');
    }

    /** @return array<string,mixed> bagian HO untuk satu unit */
    private function shareUnit(int $idunit, float $perkiraan, float $dipotong = 0.0): array
    {
        return [
            'idunit'            => $idunit,
            'nama'              => 'ICLEAR Genteng',
            'jenis'             => 'franchise',
            'is_trial'          => 0,
            'non_spv_perkiraan' => $perkiraan,
            'spv_perkiraan'     => 0.0,
            'non_spv_dipotong'  => $dipotong,
            'spv_dipotong'      => 0.0,
            'total_perkiraan'   => $perkiraan,
            'total_dipotong'    => $dipotong,
            'catatan'           => '-',
        ];
    }

    /** Gaji HO (perkiraan) mengurangi laba bersih; Hak Cabang & Tagihan Center ikut. */
    public function testLabaBersihDikurangiGajiHoDanTurunanIkut(): void
    {
        $dist = $this->hoDistribution(true);
        $dist['per_unit'][] = $this->shareUnit(5, 5000000.0);

        $html = $this->render(5, 100000000, 20000000, 10000000, null, $dist);

        // laba = 100jt - 20jt - 10jt - 5jt = 65jt
        $this->assertSame('65.000.000', $this->kartu($html, 'Laba Bersih'));
        $this->assertSame('5.000.000', $this->kartu($html, 'Gaji HO'));
        $this->assertSame('39.000.000', $this->kartu($html, 'Hak Cabang'), '60% x 65jt');
        $this->assertSame('26.000.000', $this->kartu($html, 'Tagihan Center'), '40% x 65jt');
    }

    /** Nominal payroll yang sudah dipotong menggantikan perkiraan, bukan ditambahkan. */
    public function testNominalPayrollMenggantikanPerkiraan(): void
    {
        $dist = $this->hoDistribution(true);
        $dist['per_unit'][] = $this->shareUnit(5, 5000000.0, 3000000.0);

        $html = $this->render(5, 100000000, 20000000, 10000000, null, $dist);

        // 100jt - 20jt - 10jt - 3jt (payroll), bukan 5jt (perkiraan) atau 8jt.
        $this->assertSame('3.000.000', $this->kartu($html, 'Gaji HO'));
        $this->assertSame('67.000.000', $this->kartu($html, 'Laba Bersih'));
        $this->assertStringContainsString('data-basis="payroll"', $html);
        $this->assertStringContainsString('Sudah dipotong payroll unit ini', $html);
    }

    /** Belum ada potongan payroll: pakai perkiraan dan sebut itu perkiraan. */
    public function testTanpaPotonganPayrollMemakaiPerkiraan(): void
    {
        $dist = $this->hoDistribution(true);
        $dist['per_unit'][] = $this->shareUnit(5, 5000000.0);

        $html = $this->render(5, 100000000, 20000000, 10000000, null, $dist);

        $this->assertSame('5.000.000', $this->kartu($html, 'Gaji HO'));
        $this->assertStringContainsString('data-basis="perkiraan"', $html);
        $this->assertStringContainsString('belum dipotong payroll', $html);
    }

    /** Tanggungan unit 4 juga memakai laba setelah gaji HO. */
    public function testTanggunganUnit4IkutSetelahGajiHo(): void
    {
        $dist = $this->hoDistribution(true);
        $dist['per_unit'][] = $this->shareUnit(4, 5000000.0);

        $html = $this->render(4, 100000000, 20000000, 10000000, null, $dist);

        // laba = 100jt - 20jt - 10jt - 5jt = 65jt; tanggungan 20% = 13jt.
        $this->assertSame('13.000.000', $this->kartu($html, 'Total Tanggungan Asset'), '20% x 65jt');
        $this->assertSame('31.200.000', $this->kartu($html, 'Hak Cabang'), '60% x (65jt - 13jt)');
        $this->assertSame('20.800.000', $this->kartu($html, 'Tagihan Center'), '40% x (65jt - 13jt)');
    }

    /** Staf cabang tidak menerima panel HO, tapi laba bersihnya tetap sama. */
    public function testTanpaPanelHoBagianUnitTetapMengurangiLaba(): void
    {
        $html = $this->render(5, 100000000, 20000000, 10000000, null, null, $this->shareUnit(5, 5000000.0));

        $this->assertSame('65.000.000', $this->kartu($html, 'Laba Bersih'));
        $this->assertSame('39.000.000', $this->kartu($html, 'Hak Cabang'));
        $this->assertStringNotContainsString('Beban Head Office', $html, 'panel HO tidak boleh bocor');
    }

    /** @return array<string,mixed> */
    private function hoDistribution(bool $denganPerUnit): array
    {
        $dist = [
            'divisor'              => 3,
            'pribadi_count'        => 1,
            'franchise_count'      => 2,
            'total_perkiraan'      => 6000.0,
            'total_dipotong'       => 6000.0,
            'per_employee_share'   => 1000.0,
            'per_employee_actual'  => 1000.0,
            'employees'            => [[]],
        ];

        if ($denganPerUnit) {
            $dist['per_unit'] = [[
                'idunit'            => 1,
                'nama'              => 'ICLEAR Probolinggo',
                'jenis'             => 'pribadi',
                'is_trial'          => 0,
                'non_spv_perkiraan' => 1000.0,
                'spv_perkiraan'     => 500.0,
                'non_spv_dipotong'  => 1000.0,
                'spv_dipotong'      => 500.0,
                'total_perkiraan'   => 1500.0,
                'total_dipotong'    => 1500.0,
                'catatan'           => '-',
            ]];
        }

        return $dist;
    }

    public function testTabelRincianHoDirenderBilaDataPerUnitAda(): void
    {
        $html = $this->render(1, 0, 0, 0, null, $this->hoDistribution(true));

        $this->assertStringContainsString('Beban Head Office', $html);
        $this->assertStringContainsString('Lihat rincian per unit', $html);
        $this->assertStringContainsString('SPV Wilayah', $html);
        $this->assertStringContainsString('ICLEAR Probolinggo', $html);
        $this->assertStringContainsString('1.500', $html, 'total per unit harus tampil');
        $this->assertStringContainsString('class="collapse mt-3" id="hoRincianUnit"', $html, 'rincian harus terlipat secara default');
    }

    public function testTabelRincianHoTidakDirenderTanpaDataPerUnit(): void
    {
        $html = $this->render(1, 0, 0, 0, null, $this->hoDistribution(false));

        $this->assertStringNotContainsString('hoRincianUnit', $html);
        $this->assertStringContainsString('Beban Head Office', $html, 'ringkasan tetap tampil');
    }

    /** Bagian unit ini harus menjawab nominal unit terpilih, bukan cuma total global. */
    public function testBagianUnitIniMenampilkanNominalUnitTerpilih(): void
    {
        $html = $this->render(1, 0, 0, 0, null, $this->hoDistribution(true));

        $this->assertStringContainsString('Bagian gaji HO unit ini', $html);
        foreach ([
            'hoUnitShareTotal'    => '1.500',
            'hoUnitShareNonSpv'   => '1.000',
            'hoUnitShareSpv'      => '500',
            'hoUnitShareDipotong' => '1.500',
        ] as $id => $nominal) {
            $this->assertMatchesRegularExpression(
                '#id="' . $id . '"[^>]*>\s*Rp\s+' . preg_quote($nominal, '#') . '#',
                $html,
                "{$id} tidak sesuai"
            );
        }
        $this->assertStringContainsString('yang mengurangi Laba Bersih', $html);
        $this->assertStringContainsString('ho-row-active', $html);
        $this->assertStringContainsString('Unit ini', $html);
    }
}
