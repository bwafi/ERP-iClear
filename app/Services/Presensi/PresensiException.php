<?php

namespace App\Services\Presensi;

class PresensiException extends \RuntimeException
{
    public int $status;
    public string $kode;

    public function __construct(string $message, int $status = 422, string $kode = '')
    {
        parent::__construct($message);
        $this->status = $status;
        $this->kode   = $kode;
    }
}
