<?php

namespace App\Services\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ServiceExportDetails
{
    public static function append(Worksheet $sheet, array $rows, int $firstColumn): void
    {
        $fields = [
            'no_hp' => 'No. HP',
            'alamat' => 'Alamat Lengkap',
            'keterangan' => 'Keterangan Lengkap',
            'garansi_opsi_snapshot' => 'Pilihan Garansi Tercatat',
            'garansi_hari' => 'Nilai Garansi Lama (Hari)',
            'garansi_mulai' => 'Mulai Garansi Tercatat',
            'garansi_akhir' => 'Akhir Garansi Tercatat',
        ];
        foreach (array_keys($fields) as $offset => $field) {
            $column = Coordinate::stringFromColumnIndex($firstColumn + $offset);
            $sheet->setCellValue($column . '1', $fields[$field]);
            foreach ($rows as $index => $row) {
                $sheet->setCellValueExplicit($column . ($index + 2), (string) ($row->$field ?? ''), DataType::TYPE_STRING);
            }
            $sheet->getColumnDimension($column)->setWidth(28);
            $sheet->getStyle($column . '1')->getFont()->setBold(true);
            $sheet->getStyle($column . '1:' . $column . max(1, count($rows) + 1))->getAlignment()->setWrapText(true);
        }
    }
}
