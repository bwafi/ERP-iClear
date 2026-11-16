<?php

namespace Tests;

use App\Controllers\Pembelian;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Jatuh tempo pembelian wajib diisi manual oleh penginput.
 *
 * Form tidak lagi memakai `date('Y-m-d')` sebagai default supaya penginput
 * tidak diam-diam menyimpan jatuh tempo hari ini. Server tetap memvalidasi
 * karena `required` di HTML bisa dilewati lewat POST langsung.
 */
final class PembelianJatuhTempoTest extends CIUnitTestCase
{
    public function testTanggalValidDiterima(): void
    {
        foreach (['2026-11-16', '2026-02-28', '2024-02-29', '2026-01-01'] as $tanggal) {
            $this->assertTrue(
                Pembelian::tanggalJatuhTempoValid($tanggal),
                $tanggal . ' seharusnya valid'
            );
        }
    }

    public function testKosongDitolak(): void
    {
        $this->assertFalse(Pembelian::tanggalJatuhTempoValid(''));
        $this->assertFalse(Pembelian::tanggalJatuhTempoValid('   '));
    }

    public function testTanggalTidakAdaDitolak(): void
    {
        foreach (['2026-02-30', '2025-02-29', '2026-13-01', '2026-00-10', '0000-00-00'] as $tanggal) {
            $this->assertFalse(
                Pembelian::tanggalJatuhTempoValid($tanggal),
                $tanggal . ' seharusnya ditolak'
            );
        }
    }

    public function testFormatSelainYmdDitolak(): void
    {
        foreach (['16-11-2026', '2026/11/16', '2026-11-16abc', 'besok', '16 Nov 2026'] as $tanggal) {
            $this->assertFalse(
                Pembelian::tanggalJatuhTempoValid($tanggal),
                $tanggal . ' seharusnya ditolak'
            );
        }
    }
}
