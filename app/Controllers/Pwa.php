<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Manifest PWA mobile. Service worker-nya statis di `public/sw.js` supaya
 * scope-nya otomatis mengikuti folder aplikasi.
 */
class Pwa extends Controller
{
    protected $helpers = ['url'];

    public function manifest()
    {
        $data = [
            'id'               => base_url('absen'),
            'name'             => 'Absen iClear',
            'short_name'       => 'Absen iClear',
            'description'      => 'Absen, pengajuan izin, dan profil pegawai.',
            'start_url'        => base_url('absen'),
            'scope'            => base_url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#eef2f7',
            'theme_color'      => '#0074ba',
            'lang'             => 'id',
            'icons'            => [
                ['src' => base_url('template/assets/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => base_url('template/assets/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => base_url('template/assets/pwa/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Pengajuan Izin', 'url' => base_url('pengajuan_izin')],
            ],
        ];

        return $this->response
            ->setHeader('Content-Type', 'application/manifest+json; charset=utf-8')
            ->setBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
