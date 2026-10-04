<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Posting ke ledger `transaksi_kas_bank` gagal WAJIB — bukan skip idempoten.
 *
 * MASALAH YANG DISOLUSIKAN
 * -----------------------
 * `ModeKasBank::posting*()` memakai `status` dengan dua arti yang tadinya
 * bercampur:
 *
 *   - 'skipped' + reason 'sudah terposting'  -> no-op idempoten, LEDGER SUDAH BENAR
 *   - 'skipped' + reason dari resolver      -> LEDGER KOSONG, transaksi gagal
 *
 * Caller lama cukup memanggil `posting*()` tanpa memeriksa return value, sehingga
 * kasus kedua tampil sebagai "Data kas keluar berhasil disimpan." padahal
 * `transaksi_kas_bank` tidak berisi apa pun. Itu kondisi silent success.
 *
 * KONTRAK SETELAH PERUBAHAN INI
 * -----------------------------
 *   inserted -> ledger ditulis, transaksi sukses
 *   skipped  -> hanya 'sudah terposting'; LEDGER SUDAH BENAR, aman dianggap sukses
 *   failed   -> ledger TIDAK ditulis; transaksi WAJIB dianggap gagal
 *
 * Hanya `failed` yang melempar lewat kelas ini, dan pesannya tetap memuat
 * reason asli dari resolver (akun tidak ditemukan / bank tidak terdaftar /
 * unit tidak punya entitlement / tidak boleh sebagai source-destination)
 * supaya admin tahu harus memperbaiki apa.
 *
 * Exception TIDAK menelan informasi: `getResult()` mengembalikan array status
 * lengkap dan `getAlasan()` mengembalikan reason resolver apa adanya.
 */
class PostingLedgerException extends RuntimeException
{
    /** @var array<string,mixed> */
    private array $result;

    /**
     * @param array<string,mixed> $result Array return dari ModeKasBank::posting*()
     */
    public function __construct(array $result, string $context = '')
    {
        $this->result = $result;

        parent::__construct(self::susunPesan($result, $context));
    }

    /**
     * Lempar hanya kalau posting benar-benar gagal. Pemakaian di controller:
     *
     *   $post = $this->KasBankLib->postingKasKeluar((int) $id);
     *   PostingLedgerException::wajibBerhasil($post, 'kas keluar');
     *
     * Idempotent skip TIDAK melempar, jadi re-posting yang sah tetap aman.
     *
     * @param array<string,mixed> $result
     *
     * @throws self
     */
    public static function wajibBerhasil(array $result, string $context = ''): void
    {
        if (self::statusGagal($result)) {
            throw new self($result, $context);
        }
    }

    /**
     * @param array<string,mixed> $result
     */
    public static function statusGagal(array $result): bool
    {
        return ($result['status'] ?? '') === 'failed';
    }

    /**
     * @param array<string,mixed> $result
     */
    public static function susunPesan(array $result, string $context = ''): string
    {
        $alasan = trim((string) ($result['reason'] ?? ''));
        if ($alasan === '') {
            $alasan = 'akun kas/bank tidak terkonfigurasi';
        }

        $label = $context === '' ? 'Transaksi' : ucfirst($context);

        return $label . ' gagal disimpan karena tidak bisa masuk ledger kas/bank: ' . $alasan
            . ' Transaksi sudah dibatalkan agar saldo tidak meleset. Perbaiki konfigurasi rekeningnya, lalu ulangi.';
    }

    /**
     * @return array<string,mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Reason asli dari resolver, tanpa dibungkus kalimat apa pun.
     */
    public function getAlasan(): string
    {
        return trim((string) ($this->result['reason'] ?? ''));
    }
}
