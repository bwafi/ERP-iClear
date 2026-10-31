<?php

namespace Tests;

use App\Services\Payroll\HoSalaryDistributionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan pembagian gaji HO ke unit:
 * - Non-SPV: pembagi global (pribadi = 1, franchise = 1/unit, trial = 0)
 * - SPV: hanya ke unit wilayahnya yang non-trial.
 */
class HoSalaryDistributionSpvTest extends CIUnitTestCase
{
    private function unit(int $id, string $nama, string $jenis, int $trial = 0): array
    {
        return [
            'idunit'        => $id,
            'NAMA_UNIT'     => $nama,
            'jenis'         => $jenis,
            'is_trial'      => $trial,
            'is_operational'=> 1,
        ];
    }

    private function perUnit(array $hasil): array
    {
        $map = [];
        foreach ($hasil['per_unit'] as $u) {
            $map[$u['idunit']] = $u;
        }

        return $map;
    }

    public function testSpvHanyaDibagiKeWilayahNonTrial(): void
    {
        $units = [
            $this->unit(1, 'Probolinggo', 'pribadi'),
            $this->unit(2, 'Jember', 'pribadi'),
            $this->unit(3, 'Banyuwangi', 'franchise'),
            $this->unit(4, 'Pandaan', 'franchise'),
            $this->unit(5, 'Genteng', 'franchise', 1),
            ['idunit' => 50, 'NAMA_UNIT' => 'Head Office', 'jenis' => 'Kantor', 'is_trial' => 0, 'is_operational' => 0],
        ];

        $employees = [
            // Non-SPV: pool global Rp300.
            ['id' => 100, 'jabatan' => 0, 'perkiraan_gaji' => 300.0, 'gaji_dipotong' => 300.0],
            // SPV Mario: wilayah 2, 3, 5(trial) -> pembagi 2.
            ['id' => 49, 'jabatan' => 40, 'perkiraan_gaji' => 200.0, 'gaji_dipotong' => 200.0],
            // SPV Bima: wilayah 1, 4 -> pembagi 2.
            ['id' => 56, 'jabatan' => 40, 'perkiraan_gaji' => 100.0, 'gaji_dipotong' => 100.0],
        ];

        $hasil = HoSalaryDistributionService::alokasiKeUnit($units, $employees, [
            49 => [2, 3, 5],
            56 => [1, 4],
        ]);

        // Pembagi global: 1 pribadi (unit 1) + franchise 3 + franchise 4 = 3.
        $this->assertSame(3, $hasil['divisor']);
        $this->assertSame(1, $hasil['pribadi_count']);
        $this->assertSame(2, $hasil['franchise_count']);
        $this->assertSame([1, 3, 4], $hasil['counted_unit_ids']);

        $unit = $this->perUnit($hasil);

        // Non-SPV 300/3 = 100 ke unit 1, 3, 4. Unit 2 pribadi tambahan = 0.
        $this->assertEqualsWithDelta(100.0, $unit[1]['non_spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(0.0, $unit[2]['non_spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[3]['non_spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[4]['non_spv_perkiraan'], 0.001);

        // SPV Mario 200/2 = 100 ke unit 2 & 3; unit 5 trial tidak dapat.
        $this->assertEqualsWithDelta(100.0, $unit[2]['spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[3]['spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(0.0, $unit[5]['spv_perkiraan'], 0.001);

        // SPV Bima 100/2 = 50 ke unit 1 & 4.
        $this->assertEqualsWithDelta(50.0, $unit[1]['spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(50.0, $unit[4]['spv_perkiraan'], 0.001);

        // Total per unit.
        $this->assertEqualsWithDelta(150.0, $unit[1]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[2]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(200.0, $unit[3]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(150.0, $unit[4]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(0.0, $unit[5]['total_perkiraan'], 0.001);

        // Seluruh biaya teralokasi (300 + 200 + 100).
        $total = array_sum(array_column($unit, 'total_perkiraan'));
        $this->assertEqualsWithDelta(600.0, $total, 0.001);
    }

    public function testBebanUnitMemakaiAktualUntukYangSudahDibayar(): void
    {
        $units = [
            $this->unit(1, 'Probolinggo', 'pribadi'),
            $this->unit(3, 'Banyuwangi', 'franchise'),
        ];

        $employees = [
            // Sudah dibayar: aktual (termasuk bonus) yang dibebankan.
            ['id' => 41, 'jabatan' => 0, 'perkiraan_gaji' => 300.0, 'gaji_dipotong' => 360.0, 'gaji_dibebankan' => 360.0],
            // Belum dibayar: tetap perkiraan.
            ['id' => 44, 'jabatan' => 0, 'perkiraan_gaji' => 150.0, 'gaji_dipotong' => 0.0, 'gaji_dibebankan' => 150.0],
        ];

        $hasil = HoSalaryDistributionService::alokasiKeUnit($units, $employees);
        $unit = $this->perUnit($hasil);

        // Pembagi 2 (1 pribadi + 1 franchise): (360 + 150) / 2 = 255.
        $this->assertEqualsWithDelta(255.0, $unit[1]['non_spv_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(255.0, $unit[3]['non_spv_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(255.0, $unit[1]['total_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(255.0, $unit[3]['total_dibebankan'], 0.001);
        $this->assertSame('sebagian', $unit[1]['basis']);
        $this->assertSame('sebagian', $unit[3]['basis']);
    }

    public function testTunjanganPenempatanDibebankanPenuhKePusat(): void
    {
        $units = [
            $this->unit(1, 'Probolinggo', 'pribadi'),
            $this->unit(3, 'Banyuwangi', 'franchise'),
        ];
        $employees = [[
            'id' => 41, 'jabatan' => 0,
            'perkiraan_gaji' => 300.0, 'gaji_dipotong' => 0.0,
            'gaji_dibebankan' => 300.0, 'placement' => 100.0,
        ]];

        $hasil = HoSalaryDistributionService::alokasiKeUnit($units, $employees);
        $unit = $this->perUnit($hasil);

        $this->assertSame(1, $hasil['pribadi_unit_id']);
        // Basis (300 - 100) / 2 = 100; pusat + penempatan 100.
        $this->assertEqualsWithDelta(200.0, $unit[1]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[3]['total_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[1]['placement_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(0.0, $unit[3]['placement_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(300.0, array_sum(array_column($unit, 'total_perkiraan')), 0.001);

        // Sudah dibayar (aktual 500 termasuk penempatan 100): pusat 300, cabang 200.
        $employees[0]['gaji_dipotong']    = 500.0;
        $employees[0]['gaji_dibebankan']  = 500.0;
        $unit = $this->perUnit(HoSalaryDistributionService::alokasiKeUnit($units, $employees));

        $this->assertEqualsWithDelta(300.0, $unit[1]['total_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(200.0, $unit[3]['total_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[1]['placement_dibebankan'], 0.001);
        $this->assertEqualsWithDelta(500.0, array_sum(array_column($unit, 'total_dibebankan')), 0.001);
    }

    public function testSpvTanpaWilayahTetapDibagiGlobal(): void
    {
        $units = [
            $this->unit(1, 'Probolinggo', 'pribadi'),
            $this->unit(3, 'Banyuwangi', 'franchise'),
        ];

        $employees = [
            ['id' => 49, 'jabatan' => 40, 'perkiraan_gaji' => 200.0, 'gaji_dipotong' => 200.0],
        ];

        $hasil = HoSalaryDistributionService::alokasiKeUnit($units, $employees);

        $this->assertSame('global', $hasil['employees'][0]['scope']);
        $this->assertSame(2, $hasil['employees'][0]['divisor']);
        $this->assertEqualsWithDelta(200.0, $hasil['total_non_spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(0.0, $hasil['total_spv_perkiraan'], 0.001);

        $unit = $this->perUnit($hasil);
        $this->assertEqualsWithDelta(100.0, $unit[1]['non_spv_perkiraan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $unit[3]['total_perkiraan'], 0.001);
    }
}
