<?php

namespace App\Services\Presensi;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Menyimpan foto absen di luar public/ supaya hanya bisa diambil lewat route
 * ber-auth. Path yang disimpan di DB relatif terhadap folder foto (YYYYMM/nama).
 */
class FotoPresensiService
{
    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function simpan(UploadedFile $foto, string $jenis, int $akunId, ?string $dir = null): string
    {
        $config = config('Presensi');
        $dir    = $dir ?? $config->fotoDir;

        if (! $foto->isValid()) {
            throw new PresensiException('File foto tidak valid.');
        }

        $mime = $foto->getMimeType();
        if (! isset(self::MIME[$mime])) {
            throw new PresensiException('Foto harus berformat JPEG atau PNG.');
        }

        if ($foto->getSize() > $config->maxFotoBytes) {
            throw new PresensiException('Ukuran foto maksimal ' . round($config->maxFotoBytes / 1048576) . ' MB.');
        }

        $sub    = date('Ym');
        $folder = rtrim($dir, '/') . '/' . $sub;
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new PresensiException('Folder foto tidak dapat dibuat.', 500);
        }

        $nama = sprintf(
            'absen_%s_%d_%s.%s',
            $jenis,
            $akunId,
            bin2hex(random_bytes(6)),
            self::MIME[$mime]
        );

        if (! $foto->move($folder, $nama)) {
            throw new PresensiException('Foto gagal disimpan.', 500);
        }

        return $sub . '/' . $nama;
    }

    public function hapus(?string $path, ?string $dir = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $file = rtrim($dir ?? config('Presensi')->fotoDir, '/') . '/' . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
