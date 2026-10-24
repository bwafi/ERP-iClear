<?php

namespace App\Services\Meta;

/**
 * Kegagalan tetap saat berbicara dengan Meta Marketing API: konfigurasi
 * kurang, autentikasi ditolak, atau respons tidak terduga.
 *
 * PENTING: pesan tidak pernah memuat token. Token hanya dibaca dari .env,
 * dan URL Meta (yang memuat token di query) tidak pernah ditulis ke log
 * atau ke pesan exception.
 */
class MetaAdsException extends \RuntimeException
{
}
