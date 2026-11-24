<?php

namespace App\Commands;

use App\Services\Kpi\ItKpiSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Sinkronkan data KPI Team IT (jabatan 45) ke snapshot bulanan.
 *
 *   php spark it:kpi-sync
 *   php spark it:kpi-sync --month=2026-11
 *   php spark it:kpi-sync --month=2026-11 --restore=failed
 *
 * Sumber: GitHub commits (feat/fix/docs), backups/history.jsonl, dan ledger
 * backups/uptime.jsonl dari `it:uptime-check`.
 * Aman dijalankan berulang (upsert per periode). Cron harian disarankan agar
 * bulan berjalan selalu segar.
 */
class ItKpiSync extends BaseCommand
{
    protected $group       = 'KPI';
    protected $name        = 'it:kpi-sync';
    protected $description = 'Sinkronkan commit GitHub, backup harian, dan uptime ke snapshot KPI Team IT (jabatan 45).';
    protected $usage       = 'it:kpi-sync [--month YYYY-MM] [--restore ok|failed] [--timezone Asia/Jakarta] [--backup-dir backups]';
    protected $options     = [
        '-month'      => 'Periode YYYY-MM (default bulan berjalan zona bisnis).',
        '-restore'    => 'Catat hasil uji restore bulan ini: ok atau failed.',
        '-timezone'   => 'Zona waktu bisnis (default Asia/Jakarta, env KPI_TIMEZONE).',
        '-backup-dir' => 'Folder histori backup (default ROOTPATH/backups, env BACKUP_DIR).',
    ];

    public function run(array $params): int
    {
        $timezone = $this->timezone();
        $now      = new DateTimeImmutable('now', $timezone);

        $month = (string) (CLI::getOption('month') ?: $now->format('Y-m'));
        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m) !== 1) {
            CLI::error('Format --month harus YYYY-MM.');

            return 1;
        }

        $year  = (int) $m[1];
        $mon   = (int) $m[2];
        if ($mon < 1 || $mon > 12 || ! checkdate($mon, 1, $year)) {
            CLI::error('Bulan/tahun tidak valid: ' . $month);

            return 1;
        }

        $backupDir = (string) (
            CLI::getOption('backup-dir')
            ?: env('BACKUP_DIR')
            ?: ((defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR) . 'backups')
        );

        $service = new ItKpiSyncService([
            'github_token'   => (string) env('GITHUB_TOKEN'),
            'github_repos'   => (string) env('GITHUB_REPOS'),
            'github_authors' => (string) env('GITHUB_AUTHORS'),
            'backup_dir'     => $backupDir,
            'timezone'       => $timezone->getName(),
        ]);

        $restore = CLI::getOption('restore');
        if ($restore !== null && $restore !== '') {
            $restore = strtolower(trim((string) $restore));
            if (! in_array($restore, ['ok', 'failed'], true)) {
                CLI::error('--restore harus "ok" atau "failed".');

                return 1;
            }

            try {
                $service->recordRestore($restore, $now);
                CLI::write('Uji restore dicatat: ' . $restore, $restore === 'ok' ? 'green' : 'yellow');
            } catch (Throwable $e) {
                CLI::error('Gagal mencatat uji restore: ' . $e->getMessage());

                return 1;
            }
        }

        CLI::write('Sinkronisasi KPI IT periode ' . $month . ' (' . $timezone->getName() . ')', 'yellow');

        try {
            $result = $service->sync($year, $mon, $now);
        } catch (Throwable $e) {
            CLI::error('Sinkronisasi gagal: ' . $e->getMessage());

            return 1;
        }

        CLI::write('  commit feat : ' . $this->number($result['feat_commits']));
        CLI::write('  commit fix  : ' . $this->number($result['fix_commits']));
        CLI::write('  commit docs : ' . $this->number($result['docs_commits']));
        CLI::write(sprintf(
            '  backup      : %s/%s hari',
            $this->number($result['backup_ok_days']),
            $this->number($result['backup_checked_days'])
        ));
        CLI::write(sprintf(
            '  uptime      : %s/%s hari',
            $this->number($result['uptime_ok_days']),
            $this->number($result['uptime_checked_days'])
        ));

        $restoreOk = $result['restore_ok'] ?? null;
        CLI::write('  uji restore : ' . ($restoreOk === null ? 'belum diuji' : ($restoreOk ? 'ok' : 'GAGAL (skor backup maks 50)')));

        if (trim((string) env('GITHUB_REPOS')) === '') {
            CLI::write('  catatan     : GITHUB_REPOS kosong, commit tidak dihitung.', 'yellow');
        }
        if (($result['detail']['uptime']['status'] ?? '') === 'no-ledger') {
            CLI::write('  catatan     : ledger uptime kosong, uptime N/A (jalankan it:uptime-check).', 'yellow');
        }

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                CLI::error('  ' . $error);
            }

            CLI::write('Snapshot tersimpan sebagian. Perbaiki error lalu jalankan ulang.', 'yellow');

            return 1;
        }

        CLI::write('Snapshot tersimpan: kpi_it_metrics periode ' . $result['period'], 'green');

        return 0;
    }

    /**
     * @param mixed $value
     */
    private function number($value): string
    {
        return $value === null ? 'N/A' : (string) $value;
    }

    private function timezone(): DateTimeZone
    {
        $name = (string) (CLI::getOption('timezone') ?: env('KPI_TIMEZONE') ?: 'Asia/Jakarta');

        try {
            return new DateTimeZone($name);
        } catch (Throwable $e) {
            return new DateTimeZone('Asia/Jakarta');
        }
    }
}
