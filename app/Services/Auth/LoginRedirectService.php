<?php

namespace App\Services\Auth;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Menyimpan halaman tujuan sebelum login dan memakainya sekali setelah login.
 * Nilai session hanya berisi path internal, bukan URL penuh.
 */
class LoginRedirectService
{
    private const SESSION_KEY = 'redirect_after_login';

    public function simpan(RequestInterface $request): void
    {
        if (strtoupper($request->getMethod()) !== 'GET') {
            return;
        }

        $path = $request->getUri()->getPath();
        $indexPage = trim((string) config('App')->indexPage, '/');
        if ($indexPage !== '' && strpos($path, '/' . $indexPage . '/') === 0) {
            $path = substr($path, strlen($indexPage) + 1);
        }
        if ($path === '' || $path === '/' || $path === '/login') {
            return;
        }

        $query = $request->getUri()->getQuery();
        session()->set(self::SESSION_KEY, $path . ($query !== '' ? '?' . $query : ''));
    }

    /**
     * Mengambil sekaligus menghapus tujuan. Nilai yang tidak valid diabaikan.
     */
    public function ambil(): string
    {
        $tujuan = (string) session()->get(self::SESSION_KEY);
        session()->remove(self::SESSION_KEY);

        if (! $this->valid($tujuan)) {
            return base_url();
        }

        return base_url($tujuan);
    }

    private function valid(string $tujuan): bool
    {
        if ($tujuan === '' || $tujuan[0] !== '/') {
            return false;
        }

        // Tolak protocol-relative, backslash, dan header injection.
        if (strpos($tujuan, '//') === 0
            || strpos($tujuan, '\\') !== false
            || strpos($tujuan, "\r") !== false
            || strpos($tujuan, "\n") !== false
        ) {
            return false;
        }

        return $tujuan !== '/login'
            && strpos($tujuan, '/login?') !== 0
            && strpos($tujuan, '/login/') !== 0;
    }
}
