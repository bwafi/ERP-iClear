<?php

if (! function_exists('invoice_ho_employees')) {
    /**
     * Bagian gaji tiap karyawan HO untuk satu unit, lengkap dengan komponen.
     *
     * Nominal mengikuti pembagi yang sama dengan HoSalaryDistributionService:
     * non-SPV dibagi global, SPV hanya ke unit wilayahnya. Baris terakhir
     * disesuaikan ke total unit supaya penjumlahan rincian sama dengan total.
     *
     * @param array<string,mixed> $distribution hasil calculateDistribution()
     * @return array<int,array{nama:string,nominal:int,komponen:array<int,array{nama:string,nominal:int}>}>
     */
    function invoice_ho_employees(array $distribution, int $unitId, float $targetTotal = 0): array
    {
        // Unit tanpa bagian HO (mis. trial) tidak boleh menampilkan rincian
        // karyawan HO yang seolah-olah dibebankan.
        $target = (int) round($targetTotal);
        if ($target <= 0) {
            return [];
        }

        $prioritas = [
            'GAJI_POKOK'           => 0,
            'TUNJANGAN_KINERJA'    => 1,
            'TUNJANGAN_ABSEN'      => 2,
            'TUNJANGAN_PENEMPATAN' => 3,
            'INSENTIF'             => 4,
        ];

        $rows = [];
        $sum  = 0;
        // Tunjangan penempatan HO dibebankan ke unit pusat (pribadi), bukan
        // ke cabang; cabang tidak menerima komponen ini.
        $isPusat = $unitId > 0 && $unitId === (int) ($distribution['pribadi_unit_id'] ?? 0);

        foreach (($distribution['employees'] ?? []) as $emp) {
            $divisor = (int) ($emp['divisor'] ?? 0);
            if ($divisor <= 0) {
                continue;
            }

            if (($emp['scope'] ?? 'global') === 'wilayah'
                && ! in_array($unitId, array_map('intval', $emp['wilayah_unit_ids'] ?? []), true)) {
                continue;
            }

            $nominal = (float) ($emp['gaji_dibebankan'] ?? 0);
            if ($nominal <= 0) {
                // Fallback untuk data lama/test: aktual bila sudah dibayar,
                // kalau belum pakai perkiraan.
                $nominal = (float) ($emp['gaji_dipotong'] ?? 0);
            }
            if ($nominal <= 0) {
                $nominal = (float) ($emp['perkiraan_gaji'] ?? 0);
            }
            if ($nominal <= 0) {
                continue;
            }

            $placement = max(0.0, (float) ($emp['placement'] ?? 0));
            $nominal   = max(0.0, $nominal - $placement);
            $bagian    = (int) round($nominal / $divisor);
            if ($isPusat) {
                // Pusat menanggung penempatan penuh, bukan dibagi.
                $bagian += (int) round($placement);
            }
            if ($bagian <= 0) {
                continue;
            }

            $komponen = [];
            $tambah = static function (string $kode, string $nama, float $nilai) use (&$komponen, $divisor): void {
                $nama = trim($nama);
                if ($nama === '') {
                    return;
                }
                $bulat = (int) round($nilai / $divisor);
                if ($bulat === 0) {
                    return;
                }
                $kunci = $kode !== '' ? $kode : $nama;
                $komponen[$kunci] ??= ['nama' => $nama, 'nominal' => 0];
                $komponen[$kunci]['nominal'] += $bulat;
            };

            foreach (($emp['components'] ?? []) as $c) {
                $tambah((string) ($c['component_code'] ?? ''), (string) ($c['component_name'] ?? ''), (float) ($c['amount'] ?? 0));
            }
            if ($isPusat && $placement > 0) {
                $komponen['TUNJANGAN_PENEMPATAN'] ??= ['nama' => 'Tunjangan Penempatan', 'nominal' => 0];
                $komponen['TUNJANGAN_PENEMPATAN']['nominal'] += (int) round($placement);
            }
            if ((float) ($emp['insentif'] ?? 0) > 0) {
                $tambah('INSENTIF', 'Insentif', (float) $emp['insentif']);
            }

            uksort($komponen, static function (string $a, string $b) use ($prioritas): int {
                return ($prioritas[$a] ?? 99) <=> ($prioritas[$b] ?? 99) ?: strcmp($a, $b);
            });
            $komponen = array_values($komponen);

            $rows[] = ['nama' => (string) ($emp['nama'] ?? '-'), 'nominal' => $bagian, 'komponen' => $komponen];
            $sum += $bagian;
        }

        // Samakan jumlah karyawan terakhir dengan total unit (pembulatan /
        // payroll aktual), lalu samakan komponennya dengan nominal karyawan.
        if ($rows !== [] && $sum !== $target) {
            $akhir = array_key_last($rows);
            $rows[$akhir]['nominal'] += $target - $sum;
        }

        foreach ($rows as &$row) {
            $selisih = $row['nominal'] - array_sum(array_column($row['komponen'], 'nominal'));
            if ($selisih === 0) {
                continue;
            }

            // Selisih pembulatan (<= Rp 100) ditempel ke komponen terbesar agar
            // invoice tidak menampilkan baris penyesuaian bernilai -1 rupiah.
            if (abs($selisih) <= 100 && $row['komponen'] !== []) {
                $terbesar = 0;
                foreach ($row['komponen'] as $i => $k) {
                    if ($k['nominal'] > $row['komponen'][$terbesar]['nominal']) {
                        $terbesar = $i;
                    }
                }
                $row['komponen'][$terbesar]['nominal'] += $selisih;
                continue;
            }

            $row['komponen'][] = ['nama' => 'Penyesuaian payroll', 'nominal' => $selisih];
        }
        unset($row);

        return $rows;
    }
}

if (! function_exists('invoice_gaji_komponen')) {
    /**
     * Pecah satu baris gaji jadi komponen untuk lampiran invoice.
     *
     * Baris payroll yang sudah dibayar memakai total aktual (termasuk
     * bonus/potongan), sehingga selisih terhadap komponen masuk
     * "Penyesuaian payroll".
     *
     * @param array<string,mixed> $gaji baris `$gajiDetail`/PayrollGenerator
     *
     * @return array<int,array{nama:string,nominal:int}>
     */
    function invoice_gaji_komponen(array $gaji): array
    {
        $nominal  = (int) round((float) ($gaji['total'] ?? 0));
        $komponen = [];
        foreach ([
            'Gaji Pokok'           => 'gaji_pokok',
            'Tunjangan Kinerja'    => 'tunjangan_kinerja',
            'Tunjangan Absensi'    => 'tunjangan_absen',
            'Tunjangan Penempatan' => 'tunjangan_penempatan',
            'Insentif'             => 'insentif',
            'Lembur'               => 'lembur',
        ] as $nama => $kunci) {
            $nilai = (int) round((float) ($gaji[$kunci] ?? 0));
            if ($nilai !== 0) {
                $komponen[] = ['nama' => $nama, 'nominal' => $nilai];
            }
        }

        $selisih = $nominal - array_sum(array_column($komponen, 'nominal'));
        if ($selisih !== 0) {
            $komponen[] = ['nama' => 'Penyesuaian payroll', 'nominal' => $selisih];
        }

        return $komponen;
    }
}

if (! function_exists('invoice_internal_signers')) {
    /**
     * Nama penanda tangan invoice: akun Finance (jabatan 0) dan Manager (34).
     *
     * @return array{finance:string,manager:string}
     */
    function invoice_internal_signers(): array
    {
        $pick = static function (int $jabatan): string {
            $row = db_connect()->table('akun')
                ->select('NAMA_AKUN')
                ->where('ID_JABATAN', $jabatan)
                ->where('STATUS_PEGAWAI', 1)
                ->orderBy('ID_AKUN', 'ASC')
                ->get()
                ->getRow();

            return $row ? trim((string) $row->NAMA_AKUN) : '';
        };

        return ['finance' => $pick(0), 'manager' => $pick(34)];
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
