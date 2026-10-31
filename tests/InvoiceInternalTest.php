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
            'profit_nett'       => 70000000,
            'tanggungan_asset'  => 0,
            'royalti'           => 28000000,
            'gaji_cabang_total' => 10000000,
            'gaji_cabang'       => [
                ['nama' => 'Arya', 'nominal' => 6000000],
                ['nama' => 'Adji', 'nominal' => 4000000],
            ],
            'ho_total'          => 7000000,
            'ho_employees'      => [
                ['nama' => 'Huda', 'nominal' => 3000000],
                ['nama' => 'Ira', 'nominal' => 4000000],
            ],
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
                ['nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0, 'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => []],
                ['nama' => 'Fahri', 'perkiraan_gaji' => 1500000, 'gaji_dipotong' => 0, 'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => []],
            ],
        ];

        $this->assertSame([
            ['nama' => 'Huda', 'nominal' => 1000000],
            ['nama' => 'Fahri', 'nominal' => 500000],
        ], invoice_ho_employees($dist, 5));
    }

    public function testHoEmployeesSpvHanyaUnitWilayahnya(): void
    {
        $dist = [
            'employees' => [
                ['nama' => 'Ira', 'perkiraan_gaji' => 2000000, 'gaji_dipotong' => 0, 'divisor' => 2, 'scope' => 'wilayah', 'wilayah_unit_ids' => [3, 4]],
            ],
        ];

        $this->assertSame([['nama' => 'Ira', 'nominal' => 1000000]], invoice_ho_employees($dist, 3));
        $this->assertSame([], invoice_ho_employees($dist, 5), 'SPV bukan wilayah unit 5');
    }

    public function testHoEmployeesDisesuaikanKeTotalUnit(): void
    {
        $dist = [
            'employees' => [
                ['nama' => 'Huda', 'perkiraan_gaji' => 3000000, 'gaji_dipotong' => 0, 'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => []],
                ['nama' => 'Fahri', 'perkiraan_gaji' => 1500000, 'gaji_dipotong' => 0, 'divisor' => 3, 'scope' => 'global', 'wilayah_unit_ids' => []],
            ],
        ];

        $rows = invoice_ho_employees($dist, 5, 1500001);

        $this->assertSame(1500001, array_sum(array_column($rows, 'nominal')));
    }

    public function testViewMenampilkanKomponenDanTotal(): void
    {
        $html = view('cetak/invoice_internal', $this->data());

        foreach ([
            'INVOICE INTERNAL',
            'INT/202609/BWI',
            'ICLEAR Banyuwangi',
            'September 2026',
            'Royalti Perusahaan',
            'Gaji Karyawan Cabang',
            'Gaji Head Office',
            'Arya',
            'Huda',
            '039-377-8773',
            'Rp 45.000.000',
            'Empat Puluh Lima Juta Rupiah',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html, "tidak ditemukan: {$teks}");
        }
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
