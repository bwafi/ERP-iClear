<?php

namespace App\Commands;

use App\Services\System\DatabaseBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Backup database default ke file .sql.gz, terverifikasi, dengan retensi.
 *
 *   php spark backup:db
 *   php spark backup:db --keep-days 30 --dir /home/user/backups
 *   php spark backup:db --bin mariadb-dump
 *
 * Cocok dipanggil dari cron cPanel:
 *   15 2 * * * cd /path/ke/erp && /usr/local/bin/php spark backup:db >> writable/logs/backup-cron.log 2>&1
 *
 * Perintah ini TIDAK mengubah data dan tidak menyentuh schema. Password
 * database dikirim lewat file sementara 0600, bukan argumen proses.
 */
class BackupDatabase extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'backup:db';
    protected $description = 'Backup database default (MySQLi) ke backups/*.sql.gz: verifikasi, retensi, histori JSONL.';
    protected $usage       = 'backup:db [--keep-days 14] [--dir backups] [--bin mysqldump] [--timezone Asia/Jakarta]';
    protected $options     = [
        '-keep-days' => 'Retensi backup dalam hari (default 14, atau env BACKUP_KEEP_DAYS).',
        '-dir'       => 'Folder tujuan (default ROOTPATH/backups, atau env BACKUP_DIR).',
        '-bin'       => 'Binary dump: mysqldump atau mariadb-dump (default deteksi otomatis).',
        '-timezone'  => 'Zona waktu nama file dan histori (default Asia/Jakarta, atau env BACKUP_TIMEZONE).',
    ];

    public function run(array $params)
    {
        $config = config(Database::class);
        $db     = $config->default;
        $driver = (string) ($db['DBDriver'] ?? '');

        if (! DatabaseBackupService::supportsDriver($driver)) {
            CLI::error(
                'backup:db hanya mendukung MySQLi. Driver sekarang: '
                . ($driver !== '' ? $driver : '(kosong)')
            );

            return 1;
        }

        $keepDays = $this->positiveInt(
            CLI::getOption('keep-days'),
            'BACKUP_KEEP_DAYS',
            14
        );

        $dir = (string) (
            CLI::getOption('dir')
            ?: env('BACKUP_DIR')
            ?: ((defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR) . 'backups')
        );

        $requested = CLI::getOption('bin');
        $timezone  = (string) (CLI::getOption('timezone') ?: env('BACKUP_TIMEZONE') ?: 'Asia/Jakarta');

        try {
            $binary  = $this->detectBinary($requested !== null ? (string) $requested : null);
            $service = new DatabaseBackupService($db, $binary, $dir, $keepDays, $timezone);
            $result  = $service->run();
        } catch (Throwable $e) {
            CLI::error('Backup gagal: ' . $e->getMessage());

            return 1;
        }

        CLI::write('Backup OK: ' . $result['path'], 'green');
        CLI::write(sprintf(
            '  ukuran: %s, tabel: %d, retensi: %d hari, dihapus: %d file',
            $this->humanBytes($result['bytes']),
            $result['tables'],
            $keepDays,
            count($result['deleted'])
        ));
        CLI::write('  histori: ' . rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'history.jsonl');
        CLI::write('  zona waktu: ' . $timezone);

        return 0;
    }

    /**
     * @param mixed $option
     */
    private function positiveInt($option, string $envKey, int $default): int
    {
        $raw = $option !== null ? $option : env($envKey);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return $default;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : $default;
    }

    private function detectBinary(?string $requested): string
    {
        $candidates = ($requested !== null && trim($requested) !== '')
            ? [trim($requested)]
            : ['mysqldump', 'mariadb-dump'];

        foreach ($candidates as $candidate) {
            if ($this->binaryWorks($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Binary dump tidak ditemukan atau tidak bisa dijalankan. '
            . 'Pasang mysql-client/mariadb-client atau sebutkan path dengan --bin.'
        );
    }

    private function binaryWorks(string $binary): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open([$binary, '--version'], $descriptors, $pipes);
        if (! is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return $bytes . ' B';
    }
}
