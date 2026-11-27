<?php

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

/**
 * Simpan QR ke public/qr_service dan kembalikan path file lokalnya.
 *
 * Path lokal dipakai saat render PDF supaya mPDF tidak perlu mengambil gambar
 * lewat HTTP ke server sendiri (yang bisa deadlock di dev server single-thread).
 */
function generateQrToFilePath($text, $filename)
{
    $path = FCPATH . 'qr_service/' . $filename;

    Builder::create()
        ->data($text)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
        ->size(300)
        ->margin(10)
        ->build()
        ->saveToFile($path);

    return $path;
}

function generateQrToFile($text, $filename)
{
    generateQrToFilePath($text, $filename);

    return base_url('qr_service/' . $filename);
}
