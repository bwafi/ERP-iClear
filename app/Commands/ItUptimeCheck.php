<?php

namespace App\Commands;

use App\Services\Kpi\ItUptimeCheckService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Satu pengecekan uptime ERP untuk KPI Team IT (jabatan 45).
 *
 *   php spark it:uptime-check
 *   php spark it:uptime-check --url=https://erp.icleargroup.id/login
 *
 * Dijadwalkan cron 3 kali sehari (02:00, 08:00, 22:00 WIB). Setiap jalan
 * menambah satu baris ke `backups/uptime.jsonl`; `it:kpi-sync` yang mengubah
 * baris-baris itu menjadi skor harian.
 *
 * Cek gagal BUKAN kegagalan perintah: exit code tetap 0 supaya cron tidak
 * mengirim error tiap kali situs down. Exit 1 hanya untuk konfigurasi salah.
 */
class ItUptimeCheck extends BaseCommand
{
    protected $group       = 'KPI';
    protected $name        = 'it:uptime-check';
    protected $description = 'Cek uptime halaman login ERP dan catat buktinya untuk KPI Team IT.';
    protected $usage       = 'it:uptime-check [--url URL] [--marker TEXT] [--timeout 10] [--backup-dir backups] [--timezone Asia/Jakarta]';
    protected $options     = [
        '-url'        => 'URL yang dicek (default env KPI_UPTIME_URL atau https://erp.icleargroup.id/login).',
        '-marker'     => 'Teks yang wajib ada di halaman (default env KPI_UPTIME_MARKER, kosong = cukup HTTP 200).',
        '-timeout'    => 'Batas waktu detik (default env KPI_UPTIME_TIMEOUT atau 10).',
        '-backup-dir' => 'Folder ledger uptime (default ROOTPATH/backups, env BACKUP_DIR).',
        '-timezone'   => 'Zona waktu bisnis (default Asia/Jakarta, env KPI_TIMEZONE).',
    ];

    public function run(array $params): int
    {
        $url = trim((string) (CLI::getOption('url') ?: env('KPI_UPTIME_URL') ?: ItUptimeCheckService::DEFAULT_URL));
        if ($url === '') {
            CLI::error('URL cek uptime kosong.');

            return 1;
        }

        $rawTimeout = CLI::getOption('timeout') ?: env('KPI_UPTIME_TIMEOUT') ?: '10';
        if (! is_numeric($rawTimeout) || (int) $rawTimeout < 1) {
            CLI::error('--timeout harus angka detik lebih dari 0.');

            return 1;
        }

        $ledgerDir = (string) (
            CLI::getOption('backup-dir')
            ?: env('BACKUP_DIR')
            ?: ((defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR) . 'backups')
        );

        $service = new ItUptimeCheckService([
            'url'        => $url,
            'marker'     => (string) (CLI::getOption('marker') ?: env('KPI_UPTIME_MARKER') ?: ''),
            'timeout'    => (int) $rawTimeout,
            'timezone'   => (string) (CLI::getOption('timezone') ?: env('KPI_TIMEZONE') ?: 'Asia/Jakarta'),
            'ledger_dir' => $ledgerDir,
        ]);

        CLI::write('Cek uptime: ' . $url, 'yellow');

        try {
            $result = $service->run();
        } catch (Throwable $e) {
            CLI::error('Cek uptime gagal dijalankan: ' . $e->getMessage());

            return 1;
        }

        if ($result['ok']) {
            CLI::write(sprintf(
                '  hasil  : OK (HTTP %d, %d ms)',
                $result['http'],
                $result['ms']
            ), 'green');
        } else {
            $reason = $result['error'] !== null && $result['error'] !== ''
                ? $result['error']
                : sprintf('HTTP %d', $result['http']);

            CLI::write('  hasil  : GAGAL (' . $reason . ')', 'red');
        }

        CLI::write('  ledger : ' . $service->path());

        if (! is_file($service->path())) {
            CLI::write('  catatan: ledger tidak bisa ditulis, periksa permission folder.', 'yellow');
        }

        return 0;
    }
}
