<?php

namespace App\Services\Kommo;

use RuntimeException;

/**
 * Kegagalan komunikasi dengan Kommo API/Webhook.
 *
 * Kode dipakai untuk logging & pengambilan keputusan (404 = lead hilang).
 */
class KommoApiException extends RuntimeException
{
    public const TRANSPORT        = 1;   // timeout / koneksi gagal (curl error)
    public const HTTP             = 2;   // respons HTTP error (selain 401/404)
    public const INVALID_RESPONSE = 3;   // body bukan JSON valid / bentuk tidak dikenal
    public const NOT_FOUND        = 404; // resource tidak ditemukan
    public const AUTH             = 401; // token invalid (bahkan setelah refresh)

    public static function transport(string $message): self
    {
        return new self($message, self::TRANSPORT);
    }

    public static function http(int $status, string $message): self
    {
        return new self("Kommo API HTTP {$status}: {$message}", self::HTTP);
    }

    public static function invalidResponse(string $message): self
    {
        return new self('Kommo API respons tidak valid: ' . $message, self::INVALID_RESPONSE);
    }

    public static function notFound(string $message = 'Kommo resource tidak ditemukan'): self
    {
        return new self($message, self::NOT_FOUND);
    }

    public static function auth(string $message = 'Kommo OAuth2 gagal (invalid token)'): self
    {
        return new self($message, self::AUTH);
    }
}