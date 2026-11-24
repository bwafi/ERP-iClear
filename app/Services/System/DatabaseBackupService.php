<?php

namespace App\Services\System;

use RuntimeException;
use Throwable;

/**
 * Backup database MySQL ke file .sql.gz + retensi + histori.
 *
 * Dipakai oleh `php spark backup:db` (cron production) dan bisa dites tanpa
 * database sungguhan dengan binary dump pengganti.
 *
 * Keputusan penting:
 *   - Password tidak pernah masuk argumen proses; dikirim lewat file
 *     `--defaults-extra-file` 0600 yang dihapus setelah selesai.
 *   - File ditulis ke `.part` lalu diverifikasi (gzip utuh + ada CREATE TABLE)
 *     sebelum di-rename. Dump gagal tidak meninggalkan file setengah jadi.
 *   - Backup terbaru selalu disisakan walau sudah melewati batas retensi,
 *     supaya tidak pernah ada kondisi "nol backup".
 */
class DatabaseBackupService
{
    /** @var array<string, mixed> */
    private array $db;

    private string $binary;

    private string $dir;

    private int $keepDays;

    private string $timezone;

    /**
     * @param array<string, mixed> $db konfigurasi group database (hostname, username, password, database, port)
     */
    public function __construct(array $db, string $binary, string $dir, int $keepDays = 14, string $timezone = 'Asia/Jakarta')
    {
        $this->db       = $db;
        $this->binary   = $binary;
        $this->dir      = rtrim($dir, '/\\');
        $this->keepDays = max(1, $keepDays);
        $this->timezone = $this->resolveTimezone($timezone);
    }

    /**
     * Perintah dump hanya masuk akal untuk koneksi MySQLi.
     */
    public static function supportsDriver(string $driver): bool
    {
        return strcasecmp($driver, 'MySQLi') === 0;
    }

    /**
     * Nama database dipakai sebagai prefix file; jangan biarkan karakter
     * path ikut masuk.
     */
    public static function sanitizeName(string $name): string
    {
        $clean = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_-');

        return $clean === '' ? 'database' : $clean;
    }

    /**
     * Jalankan dump, verifikasi, simpan, lalu terapkan retensi.
     *
     * @return array{path: string, file: string, bytes: int, tables: int, deleted: list<string>}
     *
     * @throws RuntimeException
     */
    public function run(): array
    {
        $this->ensureDir();

        $dbName = (string) ($this->db['database'] ?? '');
        if ($dbName === '') {
            throw new RuntimeException('Nama database kosong pada konfigurasi database default.');
        }

        $base = self::sanitizeName($dbName) . '_' . $this->now()->format('Y-m-d_His');
        $path = $this->uniquePath($base);
        $part = $path . '.part';

        $defaults = $this->writeCredentialsFile();

        try {
            $this->dump($defaults, $part, $dbName);

            $tables = self::countCreateTable($part);
            if ($tables < 1) {
                throw new RuntimeException('Dump tidak valid: tidak ada CREATE TABLE di dalam file.');
            }

            if (! @rename($part, $path)) {
                throw new RuntimeException('Gagal memindahkan file dump ke ' . $path);
            }
        } catch (Throwable $e) {
            @unlink($part);

            $this->appendHistory([
                'at'        => $this->now()->format('c'),
                'status'    => 'failed',
                'database'  => $dbName,
                'file'      => null,
                'bytes'     => null,
                'tables'    => null,
                'keep_days' => $this->keepDays,
                'deleted'   => 0,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            @unlink($defaults);
        }

        $bytes   = (int) @filesize($path);
        $deleted = self::prune($this->dir, $dbName, $this->keepDays);

        $this->appendHistory([
            'at'        => $this->now()->format('c'),
            'status'    => 'ok',
            'database'  => $dbName,
            'file'      => basename($path),
            'bytes'     => $bytes,
            'tables'    => $tables,
            'keep_days' => $this->keepDays,
            'deleted'   => count($deleted),
            'error'     => null,
        ]);

        return [
            'path'    => $path,
            'file'    => basename($path),
            'bytes'   => $bytes,
            'tables'  => $tables,
            'deleted' => $deleted,
        ];
    }

    /**
     * Hapus backup lebih tua dari batas retensi, kecuali yang terbaru.
     *
     * @return list<string> file yang dihapus
     */
    public static function prune(string $dir, string $dbName, int $keepDays, ?int $now = null): array
    {
        $dir     = rtrim($dir, '/\\');
        $now     = $now ?? time();
        $cutoff  = $now - max(1, $keepDays) * 86400;
        $pattern = $dir . DIRECTORY_SEPARATOR . self::sanitizeName($dbName) . '_*.sql.gz';
        $files   = glob($pattern) ?: [];

        usort($files, static function (string $a, string $b): int {
            return (int) @filemtime($b) <=> (int) @filemtime($a);
        });

        $deleted = [];
        // Index 0 = backup terbaru; selalu disisakan.
        foreach (array_slice($files, 1) as $file) {
            if (is_file($file) && (int) @filemtime($file) < $cutoff && @unlink($file)) {
                $deleted[] = $file;
            }
        }

        // Sisa dump yang terputus (mis. server mati saat backup) dibersihkan
        // setelah 1 hari supaya file aktif tidak ikut terhapus.
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.part') ?: [] as $part) {
            if (is_file($part) && (int) @filemtime($part) < $now - 86400) {
                @unlink($part);
            }
        }

        return $deleted;
    }

    /**
     * Hitung jumlah CREATE TABLE di dalam file .gz tanpa membuka seluruh isi
     * ke memori.
     */
    public static function countCreateTable(string $gzPath): int
    {
        $handle = @gzopen($gzPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File dump tidak bisa dibuka: ' . $gzPath);
        }

        $count = 0;
        while (! gzeof($handle)) {
            $chunk = gzread($handle, 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $count += substr_count($chunk, 'CREATE TABLE');
        }
        gzclose($handle);

        return $count;
    }

    private function dump(string $defaultsFile, string $part, string $dbName): void
    {
        $errorFile = $part . '.err';
        $command   = array_merge(
            [$this->binary],
            [
                '--defaults-extra-file=' . $defaultsFile,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--add-drop-table',
                $dbName,
            ]
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $errorFile, 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Tidak bisa menjalankan binary dump: ' . $this->binary);
        }

        fclose($pipes[0]);

        $gz = @gzopen($part, 'wb9');
        if ($gz === false) {
            proc_terminate($process);
            proc_close($process);
            @unlink($errorFile);

            throw new RuntimeException('Tidak bisa menulis file backup: ' . $part);
        }

        while (! feof($pipes[1])) {
            $chunk = fread($pipes[1], 1048576);
            if ($chunk === false || $chunk === '') {
                break;
            }
            gzwrite($gz, $chunk);
        }

        fclose($pipes[1]);
        gzclose($gz);

        $exitCode = proc_close($process);
        $stderr   = is_file($errorFile) ? trim((string) file_get_contents($errorFile)) : '';
        @unlink($errorFile);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Dump gagal (exit ' . $exitCode . '): ' . ($stderr !== '' ? $stderr : 'tanpa pesan error')
            );
        }
    }

    private function writeCredentialsFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp_db_');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat file kredensial sementara.');
        }

        $content = "[client]\n"
            . 'host=' . $this->quote((string) ($this->db['hostname'] ?? 'localhost')) . "\n"
            . 'port=' . (int) ($this->db['port'] ?? 3306) . "\n"
            . 'user=' . $this->quote((string) ($this->db['username'] ?? '')) . "\n"
            . 'password=' . $this->quote((string) ($this->db['password'] ?? '')) . "\n";

        if (file_put_contents($path, $content) === false) {
            @unlink($path);

            throw new RuntimeException('Tidak bisa menulis file kredensial sementara.');
        }
        @chmod($path, 0600);

        return $path;
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '', ''],
            $value
        ) . '"';
    }

    private function uniquePath(string $base): string
    {
        $path  = $this->dir . DIRECTORY_SEPARATOR . $base . '.sql.gz';
        $index = 1;

        while (file_exists($path) || file_exists($path . '.part')) {
            $path = $this->dir . DIRECTORY_SEPARATOR . $base . '_' . $index . '.sql.gz';
            $index++;
        }

        return $path;
    }

    private function ensureDir(): void
    {
        if (! is_dir($this->dir) && ! @mkdir($this->dir, 0755, true) && ! is_dir($this->dir)) {
            throw new RuntimeException('Folder backup tidak bisa dibuat: ' . $this->dir);
        }
    }

    private function resolveTimezone(string $timezone): string
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            return 'Asia/Jakarta';
        }

        return $timezone;
    }

    /**
     * Waktu bisnis (default WIB), bukan timezone aplikasi yang UTC.
     *
     * Nama file dan histori harus memakai tanggal toko; kalau memakai UTC,
     * backup pukul 02:15 WIB akan tercatat sebagai tanggal kemarin dan
     * perhitungan KPI harian jadi salah hari.
     */
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }

    /**
     * Histori JSONL dipakai untuk menghitung skor KPI Backup harian.
     *
     * @param array<string, mixed> $entry
     */
    private function appendHistory(array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return;
        }

        @file_put_contents(
            $this->dir . DIRECTORY_SEPARATOR . 'history.jsonl',
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
