<?php

namespace App\Commands;

use App\Services\Payroll\AttendanceOvertimeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Dry-run lembur dari roster shift FULL. Tidak menulis apa pun.
 *
 * Contoh:
 *   php spark presensi:lembur --bulan=2026-11
 *   php spark presensi:lembur --bulan=2026-11 --pegawai=67 --csv
 */
class PresensiLembur extends BaseCommand
{
    protected $group       = 'presensi';
    protected $name        = 'presensi:lembur';
    protected $description = 'Tampilkan lembur dari roster shift FULL untuk Teknisi dan Kepala Toko (dry-run).';

    public function run(array $params): int
    {
        $bulan = (string) ($this->opsi($params, 'bulan') ?: date('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            CLI::error('Format --bulan harus YYYY-MM.');

            return 1;
        }

        $service = new AttendanceOvertimeService();

        if ($service->sumberPeriode($bulan) !== 'presensi') {
            CLI::write('Periode ' . $bulan . ' memakai kas_keluar (sebelum cut-off), bukan presensi.', 'yellow');

            return 0;
        }

        $pegawai = (int) $this->opsi($params, 'pegawai');
        $baris   = $service->laporan($bulan, $pegawai > 0 ? $pegawai : null);

        if ((bool) $this->opsi($params, 'csv')) {
            CLI::write('pegawai;nama;jabatan;hari;tanggal;nominal');
            foreach ($baris as $row) {
                CLI::write(implode(';', [
                    $row['pegawai_id'], $row['nama'], $row['jabatan_id'],
                    $row['hari'], implode('|', $row['tanggal']), $row['nominal'],
                ]));
            }
        } else {
            foreach ($baris as $row) {
                CLI::write(sprintf(
                    '%d %s (jabatan %d): %d hari, Rp %s — %s',
                    $row['pegawai_id'],
                    $row['nama'],
                    $row['jabatan_id'],
                    $row['hari'],
                    number_format($row['nominal'], 0, ',', '.'),
                    implode(', ', $row['tanggal'])
                ));
            }
        }

        $totalHari    = array_sum(array_column($baris, 'hari'));
        $totalNominal = array_sum(array_column($baris, 'nominal'));
        CLI::write(sprintf(
            'Total: %d hari, Rp %s. DRY-RUN: tidak ada data yang ditulis.',
            $totalHari,
            number_format($totalNominal, 0, ',', '.')
        ), 'green');

        return 0;
    }

    /**
     * Baca opsi baik dalam bentuk `--nama=nilai` maupun `--nama nilai`.
     *
     * CI 4.4 hanya memecah bentuk spasi, sehingga `--bulan=2026-11` masuk ke
     * `$params` sebagai key `bulan=2026-11`.
     *
     * @return mixed
     */
    private function opsi(array $params, string $nama)
    {
        $nilai = CLI::getOption($nama);
        if ($nilai !== null) {
            return $nilai;
        }

        foreach ($params as $key => $value) {
            if (is_string($key) && strpos($key, $nama . '=') === 0) {
                return substr($key, strlen($nama) + 1);
            }
        }

        return null;
    }
}
