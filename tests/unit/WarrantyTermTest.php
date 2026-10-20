<?php

namespace Tests\unit;

use App\Services\Service\WarrantyTerm;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WarrantyTermTest extends TestCase
{
    public function testAllSevenChoicesAreAvailableAndLabeled(): void
    {
        self::assertSame([
            'non_garansi' => 'Non Garansi',
            'hari_7' => 'Garansi 7 Hari',
            'bulan_1' => 'Garansi 1 Bulan',
            'bulan_6' => 'Garansi 6 Bulan',
            'tahun_1' => 'Garansi 1 Tahun',
            'tahun_2' => 'Garansi 2 Tahun',
            'selamanya' => 'Garansi Selamanya',
        ], WarrantyTerm::OPTIONS);
        self::assertSame('Belum Diatur', WarrantyTerm::label(WarrantyTerm::UNSET));
        self::assertSame('Non Garansi', WarrantyTerm::label('non_garansi'));
        self::assertSame('Nilai lama: 45 hari (belum dikonversi)', WarrantyTerm::label(null, 45));
    }

    public function testMonthEndAndLeapYearClampToLastDayOfTargetMonth(): void
    {
        self::assertSame('2026-02-28', WarrantyTerm::endDate('2026-01-31', 'bulan_1'));
        self::assertSame('2024-02-29', WarrantyTerm::endDate('2024-01-31', 'bulan_1'));
        self::assertSame('2025-02-28', WarrantyTerm::endDate('2024-02-29', 'tahun_1'));
        self::assertSame('2028-02-29', WarrantyTerm::endDate('2027-08-31', 'bulan_6'));
    }

    public function testNonWarrantyAndLifetimeHaveNoExpiry(): void
    {
        self::assertNull(WarrantyTerm::endDate('2026-01-01', 'non_garansi'));
        self::assertNull(WarrantyTerm::endDate('2026-01-01', 'selamanya'));
    }

    public function testDescriptionSeparatesUnsetNonWarrantyLifetimeAndExpired(): void
    {
        $today = new DateTimeImmutable('2026-03-01');
        $base = ['status_service' => 4, 'garansi_mulai' => '2026-01-31', 'tanggal_selesai' => '2026-01-31'];
        self::assertSame('Belum Diatur', WarrantyTerm::describe((object) ($base + ['garansi_opsi_snapshot' => WarrantyTerm::UNSET]), $today)['status']);
        self::assertSame('Non Garansi', WarrantyTerm::describe((object) ($base + ['garansi_opsi_snapshot' => 'non_garansi']), $today)['status']);
        self::assertSame('Aktif - Selamanya', WarrantyTerm::describe((object) ($base + ['garansi_opsi_snapshot' => 'selamanya']), $today)['status']);
        self::assertSame('Kedaluwarsa', WarrantyTerm::describe((object) ($base + ['garansi_opsi_snapshot' => 'bulan_1']), $today)['status']);
        self::assertSame('Belum Diambil', WarrantyTerm::describe((object) array_replace($base, ['status_service' => 2, 'garansi_opsi_snapshot' => 'selamanya']), $today)['status']);
        self::assertSame('Belum Dimulai', WarrantyTerm::describe((object) array_replace($base, ['garansi_mulai' => '2026-03-02', 'garansi_opsi_snapshot' => 'selamanya']), $today)['status']);
    }
}
