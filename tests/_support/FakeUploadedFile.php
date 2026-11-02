<?php

namespace Tests\Support;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * UploadedFile palsu untuk test service: isValid()/getMimeType() bisa
 * ditentukan, dan move() menyalin file biasa (tanpa is_uploaded_file()).
 */
class FakeUploadedFile extends UploadedFile
{
    private string $mime = 'image/jpeg';
    private bool $valid  = true;
    public bool $dipindah = false;

    public static function buat(string $path, string $mime = 'image/jpeg', bool $valid = true, int $ukuran = 1024): self
    {
        $file        = new self($path, basename($path), $mime, $ukuran, UPLOAD_ERR_OK);
        $file->mime  = $mime;
        $file->valid = $valid;

        return $file;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getMimeType(): string
    {
        return $this->mime;
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $asal           = $this->getPathname();
        $this->dipindah = true;
        $this->setPath($targetPath);

        return copy($asal, rtrim($targetPath, '/') . '/' . $name);
    }
}
