<?php

namespace Tests\unit;

use App\Services\Service\ServiceExportDetails;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

final class ServiceExportDetailsTest extends TestCase
{
    public function testLongTextAndLegacyWarrantyRemainCompleteAndLiteral(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $address = str_repeat('UAT-TEAM alamat sangat panjang ', 30);
        ServiceExportDetails::append($sheet, [(object) [
            'no_hp' => '081234567890', 'alamat' => $address, 'keterangan' => '=1+2',
            'garansi_opsi_snapshot' => null, 'garansi_hari' => '123',
            'garansi_mulai' => '2026-01-31', 'garansi_akhir' => null,
        ]], 9);
        self::assertSame('081234567890', $sheet->getCell('I2')->getValue());
        self::assertSame($address, $sheet->getCell('J2')->getValue());
        self::assertSame('=1+2', $sheet->getCell('K2')->getValue());
        self::assertSame('s', $sheet->getCell('K2')->getDataType());
        self::assertSame('', $sheet->getCell('L2')->getValue());
        self::assertSame('123', $sheet->getCell('M2')->getValue());
        self::assertSame('2026-01-31', $sheet->getCell('N2')->getValue());
        self::assertSame('', $sheet->getCell('O2')->getValue());
    }
}
