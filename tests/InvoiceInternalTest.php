<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class InvoiceInternalTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['terbilang', 'invoice_internal']);
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return [
            'nama_usaha'        => 'CV. ICLEAR DIGITAL SOLUSI',
            'alamat_usaha'      => 'Jl. Hos Cokroaminoto No.06, Probolinggo',
            'telp_usaha'        => '085183270910',
            'unit_nama'         => 'ICLEAR Banyuwangi',
            'unit_alamat'       => 'Banyuwangi, Jawa Timur',
            'periode'           => '2026-09',
            'invoice_no'        => 'INT/202609/BWI',
            'invoice_date'      => '2026-10-01',
            'royalti'           => 28000000,
            'gaji_cabang_total' => 10000000,
            'gaji_cabang'       => [
                ['nama' => 'Arya', 'nominal' => 6000000, 'komponen' => [
                    ['nama' => 'Gaji Pokok', 'nominal' => 4000000],
                    ['nama' => 'Tunjangan Kinerja', 'nominal' => 1500000],
                    ['nama' => 'Lembur', 'nominal' => 500000],
                ]],
                ['nama' => 'Adji', 'nominal' => 4000000, 'komponen' => [
                    ['nama' => 'Gaji Pokok', 'nominal' => 3000000],
                    ['nama' => 'Penyesuaian payroll', 'nominal' => 1000000],
                ]],
            ],
            'ho_total'          => 7000000,
            'ho_employees'      => [
                [
                    'nama' => 'Huda HO', 'nominal' => 3000000,
                    'komponen' => [
                        ['nama' => 'Gaji Pokok', 'nominal' => 1800000],
                        ['nama' => 'Tunjangan Kinerja', 'nominal' => 1200000],
                    ],
                ],
                [
                    'nama' => 'Fahri HO', 'nominal' => 4000000,
                    'komponen' => [
                        ['nama' => 'Gaji Pokok', 'nominal' => 2500000],
                        ['nama' => 'Penyesuaian payroll', 'nominal' => 1500000],
                    ],
                ],
            ],
            'unit_trial'        => false,
            'finance_nama'      => 'Ira Kurniawati',
            'manager_nama'      => 'Nurul Huda',
            'bank' => [
                'nama'      => 'BCA',
                'norek'     => '039-377-8773',
                'atas_nama' => 'ICLEAR DIGITAL SOLUSI CV',
            ],
            'catatan' => 'Tagihan internal untuk pengelolaan cabang.',
        ];
    }

    public function testHoEmployeesGlobalDibagiDivisor(): void
    {
        $dist = [
            'employees' => [
                [
                    'nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 1500000],
                        ['component_code' => 'TUNJANGAN_KINERJA', 'component_name' => 'Tunjangan Kinerja', 'amount' => 1500000],
                    ],
                ],
                [
                    'nama' => 'Fahri', 'perkiraan_gaji' => 1500000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 900000],
                        ['component_code' => 'TUNJANGAN_KINERJA', 'component_name' => 'Tunjangan Kinerja', 'amount' => 600000],
                    ],
                ],
            ],
        ];

        $this->assertSame([
            [
                'nama' => 'Huda', 'nominal' => 1000000,
                'komponen' => [
                    ['nama' => 'Gaji Pokok', 'nominal' => 500000],
                    ['nama' => 'Tunjangan Kinerja', 'nominal' => 500000],
                ],
            ],
            [
                'nama' => 'Fahri', 'nominal' => 500000,
                'komponen' => [
                    ['nama' => 'Gaji Pokok', 'nominal' => 300000],
                    ['nama' => 'Tunjangan Kinerja', 'nominal' => 200000],
                ],
            ],
        ], invoice_ho_employees($dist, 5, 1500000));
    }

    public function testHoEmployeesSpvHanyaUnitWilayahnya(): void
    {
        $dist = [
            'employees' => [
                [
                    'nama' => 'Ira', 'perkiraan_gaji' => 2000000, 'gaji_dipotong' => 0,
                    'divisor' => 2, 'scope' => 'wilayah', 'wilayah_unit_ids' => [3, 4],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 2000000],
                    ],
                ],
            ],
        ];

        $row = invoice_ho_employees($dist, 3, 1000000)[0];
        $this->assertSame('Ira', $row['nama']);
        $this->assertSame(1000000, $row['nominal']);
        $this->assertSame('Gaji Pokok', $row['komponen'][0]['nama']);
        $this->assertSame(1000000, $row['komponen'][0]['nominal']);
        $this->assertSame([], invoice_ho_employees($dist, 5, 1000000), 'SPV bukan wilayah unit 5');
    }

    public function testHoEmployeesKosongBilaUnitTidakMenanggung(): void
    {
        $dist = [
            'employees' => [
                ['nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0, 'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => []],
            ],
        ];

        $this->assertSame([], invoice_ho_employees($dist, 5, 0), 'unit trial tidak menanggung HO');
    }

    public function testHoEmployeesDisesuaikanKeTotalUnit(): void
    {
        $dist = [
            'employees' => [
                [
                    'nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 3000000],
                    ],
                ],
                [
                    'nama' => 'Fahri', 'perkiraan_gaji' => 1500000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 1500000],
                    ],
                ],
            ],
        ];

        $rows = invoice_ho_employees($dist, 5, 1600000);

        $this->assertSame(1600000, array_sum(array_column($rows, 'nominal')));

        // Nominal tiap karyawan harus sama dengan jumlah komponennya.
        foreach ($rows as $row) {
            $this->assertSame($row['nominal'], array_sum(array_column($row['komponen'], 'nominal')), $row['nama']);
        }

        $akhir = $rows[array_key_last($rows)];
        $this->assertSame('Penyesuaian payroll', end($akhir['komponen'])['nama']);
    }

    public function testHoEmployeesSelisihPembulatanTidakMunculSebagaiBaris(): void
    {
        $dist = [
            'employees' => [
                [
                    'nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 3000000],
                    ],
                ],
                [
                    'nama' => 'Fahri', 'perkiraan_gaji' => 1500000, 'gaji_dipotong' => 0,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 1500000],
                    ],
                ],
            ],
        ];

        $rows = invoice_ho_employees($dist, 5, 1500001);
        $akhir = $rows[array_key_last($rows)];

        $this->assertSame(1500001, array_sum(array_column($rows, 'nominal')));
        $this->assertSame($akhir['nominal'], array_sum(array_column($akhir['komponen'], 'nominal')));
        $this->assertNotSame('Penyesuaian payroll', end($akhir['komponen'])['nama']);
    }

    public function testHoEmployeesMemakaiAktualUntukYangSudahDibayar(): void
    {
        $dist = [
            'employees' => [
                [
                    'nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0,
                    'gaji_dibebankan' => 3000000,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 3000000],
                    ],
                ],
                [
                    // Sudah dibayar plus bonus: beban memakai total bersih aktual.
                    'nama' => 'Syahroni', 'perkiraan_gaji' => 1200000, 'gaji_dipotong' => 2400000,
                    'gaji_dibebankan' => 2400000,
                    'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => [],
                    'components' => [
                        ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 1200000],
                    ],
                ],
            ],
        ];

        $rows = invoice_ho_employees($dist, 3, 1800000);

        $this->assertSame(1000000, $rows[0]['nominal']);
        $this->assertSame(800000, $rows[1]['nominal']);
        $this->assertSame(1800000, array_sum(array_column($rows, 'nominal')));
        $this->assertSame(800000, array_sum(array_column($rows[1]['komponen'], 'nominal')));
        $this->assertSame('Penyesuaian payroll', end($rows[1]['komponen'])['nama']);
    }

    public function testKomponenGajiCabangMenyesuaikanTotalAktual(): void
    {
        // Total aktual (setelah bonus/kasbon) menjadi acuan; selisihnya masuk
        // "Penyesuaian payroll" supaya jumlah komponen = total.
        $komponen = invoice_gaji_komponen([
            'gaji_pokok'           => 3000000,
            'tunjangan_kinerja'    => 500000,
            'tunjangan_absen'      => 0,
            'tunjangan_penempatan' => 250000,
            'insentif'             => 0,
            'lembur'               => 125000,
            'total'                => 4125000,
        ]);

        $this->assertSame(
            ['Gaji Pokok', 'Tunjangan Kinerja', 'Tunjangan Penempatan', 'Lembur', 'Penyesuaian payroll'],
            array_column($komponen, 'nama')
        );
        $this->assertSame(4125000, array_sum(array_column($komponen, 'nominal')));
        $this->assertSame(250000, end($komponen)['nominal']);
    }

    public function testKomponenGajiCabangTetapSamaSaatTanpaSelisih(): void
    {
        $komponen = invoice_gaji_komponen([
            'gaji_pokok' => 1000000,
            'total'      => 1000000,
        ]);

        $this->assertSame([['nama' => 'Gaji Pokok', 'nominal' => 1000000]], $komponen);
    }

    public function testHoEmployeesCabangTanpaTunjanganPenempatan(): void
    {
        $dist = [
            'pribadi_unit_id' => 1,
            'employees'       => [[
                'nama' => 'Syahroni', 'perkiraan_gaji' => 1200000, 'gaji_dipotong' => 0,
                'gaji_dibebankan' => 1200000, 'placement' => 300000,
                'divisor' => 2, 'scope' => 'global', 'wilayah_unit_ids' => [],
                'components' => [
                    ['component_code' => 'GAJI_POKOK', 'component_name' => 'Gaji Pokok', 'amount' => 900000],
                ],
            ]],
        ];

        // Cabang: (1.200.000 - 300.000) / 2 = 450.000 tanpa komponen penempatan.
        $cabang = invoice_ho_employees($dist, 3, 450000);
        $this->assertSame(450000, $cabang[0]['nominal']);
        $this->assertSame(['Gaji Pokok'], array_column($cabang[0]['komponen'], 'nama'));

        // Pusat: 450.000 + penempatan penuh 300.000.
        $pusat = invoice_ho_employees($dist, 1, 750000);
        $this->assertSame(750000, $pusat[0]['nominal']);
        $this->assertSame(['Gaji Pokok', 'Tunjangan Penempatan'], array_column($pusat[0]['komponen'], 'nama'));
        $this->assertSame(750000, array_sum(array_column($pusat[0]['komponen'], 'nominal')));
    }

    public function testViewMenampilkanKomponenDanTotal(): void
    {
        $html = view('cetak/invoice_internal', $this->data());

        foreach ([
            'Tagihan Internal',
            'Invoice',
            'INT/202609/BWI',
            'ICLEAR Banyuwangi',
            'September 2026',
            'Royalti Perusahaan',
            'Gaji Karyawan Cabang',
            'Gaji Head Office',
            'Rincian per karyawan di halaman berikutnya.',
            // Lampiran halaman 2: matriks karyawan x komponen.
            'Lampiran Invoice',
            'Rincian Gaji Karyawan Cabang',
            'Rincian Gaji Head Office',
            'Karyawan',
            'Jumlah',
            'Arya',
            'Adji',
            'Huda HO',
            'Fahri HO',
            'Gaji Pokok',
            'Tunj. Kinerja',
            'Lembur',
            'Penyesuaian',
            'Ira Kurniawati',
            'Nurul Huda',
            '039-377-8773',
            'Rp 45.000.000',
            'Empat Puluh Lima Juta Rupiah',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html, "tidak ditemukan: {$teks}");
        }

        // Nama karyawan cabang hanya di lampiran, tidak diulang di halaman 1.
        $this->assertSame(1, substr_count($html, 'Arya'));
        $this->assertSame(1, substr_count($html, 'Adji'));
    }

    public function testViewUnitTrialTanpaRincianHo(): void
    {
        $data = $this->data();
        $data['unit_nama']   = 'ICLEAR Genteng';
        $data['unit_trial']  = true;
        $data['ho_total']    = 0;
        $data['ho_employees'] = [];

        $html = view('cetak/invoice_internal', $data);

        $this->assertStringContainsString('tidak menanggung gaji HO', $html);
        $this->assertStringNotContainsString('Huda HO', $html);
        // Rincian HO tidak ada, tapi lampiran gaji cabang tetap dicetak.
        $this->assertStringNotContainsString('Rincian Gaji Head Office', $html);
        $this->assertStringContainsString('Rincian Gaji Karyawan Cabang', $html);
    }

    public function testViewTanpaRincianGajiTidakMembuatLampiran(): void
    {
        $data = $this->data();
        $data['gaji_cabang']       = [];
        $data['gaji_cabang_total'] = 0;
        $data['ho_total']          = 0;
        $data['ho_employees']      = [];

        $html = view('cetak/invoice_internal', $data);

        $this->assertStringNotContainsString('Lampiran Invoice', $html);
        $this->assertStringNotContainsString('Rincian Gaji Karyawan Cabang', $html);
        $this->assertStringNotContainsString('Rincian Gaji Head Office', $html);
    }

    public function testViewTidakMemunculkanWarningPhp(): void
    {
        $html = view('cetak/invoice_internal', $this->data());

        foreach (['Undefined array key', 'Undefined variable', 'Warning', 'Notice', 'Fatal error', 'Parse error'] as $pola) {
            $this->assertStringNotContainsString($pola, $html, "terdeteksi: {$pola}");
        }
    }

    public function testPdfTerbentuk(): void
    {
        $pdf = invoice_internal_pdf($this->data());

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}
