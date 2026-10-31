<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Bukti cetak tutup kasir harus memuat seluruh record closing, bukan hanya
 * sebagian angka. Angka uang diambil dari baris `tutup_kasir`, jadi view
 * diuji langsung dengan data record.
 */
final class TutupKasirCetakViewTest extends CIUnitTestCase
{
    /** @return array<string,mixed> */
    private function data(array $override = []): array
    {
        $tutup = (object) array_merge([
            'idtutupkasir'        => 623,
            'tanggal'             => '2026-10-30',
            'awal_cash'           => 463200,
            'awal_transfer'       => 12448270,
            'akhir_cash'          => 2557200,
            'akhir_transfer'      => 13468270,
            'pendapatan_cash'     => 2520000,
            'pendapatan_transfer' => 1020000,
            'pengeluaran_cash'    => 426000,
            'pengeluaran_transfer' => 0,
            'cash_laci'           => 2557200,
            'status'              => 'selesai',
            'unit'                => 1,
            'akun_ID_AKUN'        => 65,
            'created_at'          => '2026-10-30 14:06:05',
        ], $override);

        return [
            'tutup'         => $tutup,
            'unit_row'      => [
                'NAMA_UNIT'      => 'ICLEAR Probolinggo',
                'JALAN_UNIT'     => 'Jl. Hos Cokroaminoto No.06',
                'KABUPATEN_UNIT' => 'Probolinggo',
                'NOTELP'         => '085183270910',
            ],
            'penutup'       => ['NAMA_AKUN' => 'Priyanka Aprilia Dewi', 'NAMA_JABATAN' => 'Kepala Toko'],
            'spv_nama'      => 'Bima Bayu Baskoro',
            'qty_hp'        => 0,
            'hp_total'      => 0,
            'qty_service'   => 8,
            'service_total' => 3590000,
            'qty_acc'       => 0,
            'acc_total'     => 0,
            'op_cash'       => 0,
            'op_tf'         => 0,
            'ps_cash'       => 0,
            'ps_tf'         => 0,
        ];
    }

    public function testCetakMemuatSemuaInfoClosing(): void
    {
        $html = view('cetak/cetak_tutupkasir', $this->data());

        foreach ([
            'Tutup Kasir',
            'No. 623',
            '30 Oktober 2026',
            '21:06 WIB',
            'Status: Selesai',
            'ICLEAR Probolinggo',
            'Jl. Hos Cokroaminoto No.06',
            'Priyanka Aprilia Dewi',
            'Kepala Toko',
            'Bima Bayu Baskoro',
            'SPV',
            'Saldo awal',
            '463.200',
            '12.448.270',
            '12.911.470',
            'Pendapatan',
            '2.520.000',
            '1.020.000',
            '3.540.000',
            '(426.000)',
            'Saldo akhir',
            '2.557.200',
            '13.468.270',
            '16.025.470',
            'Rekap transaksi',
            'Servis',
            '3.590.000',
            'Rincian pengeluaran',
            'Lain-lain',
            'Kas menurut sistem',
            'Kas hasil hitung fisik',
            'Cocok',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
    }

    public function testSpvKosongDibiarkanUntukTandaTanganManual(): void
    {
        $data = $this->data();
        $data['spv_nama'] = '';

        $html = view('cetak/cetak_tutupkasir', $data);

        $this->assertStringContainsString('SPV', $html);
        $this->assertStringContainsString('____________', $html);
    }

    public function testMutasiNetoDanSelisihDitampilkanSaatTidakNol(): void
    {
        // akhir_cash = 1.000.000 + 500.000 - 200.000 - setor + tarik
        // 1.000.000 = 1.300.000 - setor + tarik -> neto setor 300.000.
        $html = view('cetak/cetak_tutupkasir', $this->data([
            'awal_cash'        => 1000000,
            'awal_transfer'    => 0,
            'pendapatan_cash'  => 500000,
            'pendapatan_transfer' => 0,
            'pengeluaran_cash' => 200000,
            'pengeluaran_transfer' => 0,
            'akhir_cash'       => 1000000,
            'akhir_transfer'   => 0,
            'cash_laci'        => 900000,
        ]));

        $this->assertStringContainsString('Setor ke bank (neto)', $html);
        $this->assertStringContainsString('(300.000)', $html);
        $this->assertStringContainsString('Kurang', $html);
        $this->assertStringContainsString('(100.000)', $html);
    }
}
