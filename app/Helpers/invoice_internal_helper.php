<?php

if (! function_exists('invoice_ho_employees')) {
    /**
     * Bagian gaji tiap karyawan HO untuk satu unit.
     *
     * Nominal mengikuti pembagi yang sama dengan HoSalaryDistributionService:
     * non-SPV dibagi global, SPV hanya ke unit wilayahnya. Baris terakhir
     * disesuaikan ke total unit supaya penjumlahan rincian sama dengan total.
     *
     * @param array<string,mixed> $distribution hasil calculateDistribution()
     * @return array<int,array{nama:string,nominal:int}>
     */
    function invoice_ho_employees(array $distribution, int $unitId, float $targetTotal = 0): array
    {
        $rows = [];
        $sum  = 0;

        foreach (($distribution['employees'] ?? []) as $emp) {
            $divisor = (int) ($emp['divisor'] ?? 0);
            if ($divisor <= 0) {
                continue;
            }

            if (($emp['scope'] ?? 'global') === 'wilayah'
                && ! in_array($unitId, array_map('intval', $emp['wilayah_unit_ids'] ?? []), true)) {
                continue;
            }

            $nominal = (float) ($emp['gaji_dipotong'] ?? 0);
            if ($nominal <= 0) {
                $nominal = (float) ($emp['perkiraan_gaji'] ?? 0);
            }
            if ($nominal <= 0) {
                continue;
            }

            $bagian = (int) round($nominal / $divisor);
            if ($bagian <= 0) {
                continue;
            }

            $rows[] = ['nama' => (string) ($emp['nama'] ?? '-'), 'nominal' => $bagian];
            $sum += $bagian;
        }

        $target = (int) round($targetTotal);
        if ($rows !== [] && $target > 0 && $sum !== $target) {
            $akhir = array_key_last($rows);
            $rows[$akhir]['nominal'] += $target - $sum;
        }

        return $rows;
    }
}

if (! function_exists('invoice_internal_pdf')) {
    /**
     * Render invoice internal menjadi byte PDF.
     *
     * 'S' dipakai supaya hasilnya bisa dikembalikan lewat Response dan diuji;
     * 'I' akan langsung mengirim output dan mematikan request.
     */
    function invoice_internal_pdf(array $data): string
    {
        $html = view('cetak/invoice_internal', $data);

        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 14,
            'margin_right'  => 14,
            'margin_top'    => 12,
            'margin_bottom' => 12,
        ]);
        $mpdf->SetTitle('Invoice Internal');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
