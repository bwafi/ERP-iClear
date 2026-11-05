<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Session;

/**
 * Hapus file session di folder `Config\Session::$savePath`.
 *
 * Contoh:
 *   php spark session:flush            # logout semua akun
 *   php spark session:flush --expired  # hanya session yang idle melewati expiration
 */
class SessionFlush extends BaseCommand
{
    protected $group       = 'session';
    protected $name        = 'session:flush';
    protected $description = 'Hapus file session (semua, atau hanya yang kedaluwarsa).';
    protected $usage       = 'session:flush [--expired]';
    protected $options     = [
        '--expired' => 'Hanya hapus session yang idle melewati Config\\Session::$expiration.',
    ];

    public function run(array $params): int
    {
        $config = config(Session::class);
        $path   = rtrim($config->savePath, '/\\') . DIRECTORY_SEPARATOR;

        if (! is_dir($path)) {
            CLI::error('Folder session tidak ditemukan: ' . $path);

            return 1;
        }

        $kedaluwarsa = CLI::getOption('expired') !== null;
        [$terhapus, $gagal] = self::bersihkan(
            $path,
            $config->cookieName,
            $kedaluwarsa,
            (int) $config->expiration
        );

        CLI::write(sprintf(
            '%s: %d session dihapus%s.',
            $kedaluwarsa ? 'Session kedaluwarsa' : 'Semua session',
            $terhapus,
            $gagal > 0 ? ', ' . $gagal . ' gagal' : ''
        ), $gagal > 0 ? 'yellow' : 'green');

        return $gagal > 0 ? 1 : 0;
    }

    /**
     * Hanya file berprefix cookie session yang disentuh; file lain dibiarkan.
     *
     * @return array{0: int, 1: int} jumlah terhapus, jumlah gagal
     */
    public static function bersihkan(string $path, string $prefix, bool $kedaluwarsa, int $expiration): array
    {
        $terhapus = 0;
        $gagal    = 0;
        $batas    = time() - $expiration;
        $path     = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;

        // expiration 0 = sesi berakhir saat browser ditutup; tidak ada batas waktu server.
        if ($kedaluwarsa && $expiration <= 0) {
            return [$terhapus, $gagal];
        }

        foreach (glob($path . $prefix . '*') ?: [] as $file) {
            if (! is_file($file) || ($kedaluwarsa && filemtime($file) > $batas)) {
                continue;
            }

            if (@unlink($file)) {
                $terhapus++;
            } else {
                $gagal++;
            }
        }

        return [$terhapus, $gagal];
    }
}
