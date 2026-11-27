<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi cetak invoice service: aset gambar harus memakai path file lokal.
 *
 * Kalau view memakai base_url(), mPDF akan fetch HTTP ke server sendiri. Di dev
 * server `php spark serve` (single-thread) request itu tidak pernah dilayani,
 * sehingga cetak invoice menggantung dan seluruh aplikasi ikut macet.
 */
final class ServiceInvoicePrintAssetsTest extends CIUnitTestCase
{
    private function service(): object
    {
        return (object) [
            'no_service'           => 'SVC-001',
            'created_at'           => '2026-11-27 10:00:00',
            'tipe_hp'              => 'iPhone 11',
            'imei'                 => '1234567890',
            'email_icloud'         => 'user@example.com',
            'passcode'             => '1234',
            'password_icloud'      => 'secret',
            'keterangan'           => 'LCD pecah',
            'tanggal_claim_garansi' => null,
            'garansi_hari'         => 7,
            'bayar_tunai'          => 0,
            'dp'                   => 0,
            'harus_dibayar'        => 0,
            'total_diskon'         => 0,
            'bayar'                => 0,
        ];
    }

    private function human(): object
    {
        return (object) [
            'nama_pelanggan'  => 'Pelanggan Uji',
            'no_hp'           => '08123456789',
            'nama_service_by' => 'Teknisi Uji',
        ];
    }

    private function unit(): object
    {
        return (object) [
            'NAMA_UNIT'       => 'ICLEAR Uji',
            'JALAN_UNIT'      => 'Jl. Uji No. 1',
            'KABUPATEN_UNIT'  => 'Uji',
            'KELURAHAN_UNIT'  => '@iclear.uji',
            'NOTELP_KOMPLAIN' => '0800',
        ];
    }

    private function data(): array
    {
        return [
            'service'     => $this->service(),
            'human'       => $this->human(),
            'dataunit'    => $this->unit(),
            'kerusakan'   => [(object) ['nama_fungsi' => 'Signal', 'keterangan' => 'Mati']],
            'sparepart'   => [],
            'logoPath'    => FCPATH . 'template/assets/images/logo.png',
            'hpImagePath' => FCPATH . 'template/assets/images/hp.png',
            'qrImagePath' => FCPATH . 'template/assets/images/logo_iclear.png',
            'qrImageUrl'  => base_url('qr_service/uji.png'),
        ];
    }

    public function testInvoiceA4MemakaiPathLokalUntukSemuaAsetCetak(): void
    {
        helper(['url', 'qr']);

        $html = view('cetak/invoice_service', $this->data());

        $this->assertStringContainsString('src="' . FCPATH . 'template/assets/images/logo.png"', $html);
        $this->assertStringContainsString('src="' . FCPATH . 'template/assets/images/hp.png"', $html);
        $this->assertStringContainsString('src="' . FCPATH . 'template/assets/images/logo_iclear.png"', $html);

        $this->assertStringNotContainsString(base_url('template/assets/images/logo.png'), $html);
        $this->assertStringNotContainsString(base_url('template/assets/images/hp.png'), $html);
        $this->assertStringNotContainsString(base_url('qr_service/uji.png'), $html);
    }

    public function testInvoiceThermalMemakaiPathLokalUntukLogoDanQr(): void
    {
        helper(['url', 'qr']);

        $html = view('cetak/invoice_service_thermal', $this->data());

        $this->assertStringContainsString('src="' . FCPATH . 'template/assets/images/logo.png"', $html);
        $this->assertStringContainsString('src="' . FCPATH . 'template/assets/images/logo_iclear.png"', $html);

        $this->assertStringNotContainsString(base_url('template/assets/images/logo.png'), $html);
        $this->assertStringNotContainsString(base_url('qr_service/uji.png'), $html);
    }

    public function testGenerateQrToFilePathMengembalikanFileLokal(): void
    {
        helper('qr');

        $name = 'qr_unit_test_' . uniqid() . '.png';
        $path = generateQrToFilePath(base_url('status_service/1'), $name);

        try {
            $this->assertSame(FCPATH . 'qr_service/' . $name, $path);
            $this->assertStringNotContainsString('://', $path);
            $this->assertFileExists($path);
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
