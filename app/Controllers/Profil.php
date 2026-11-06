<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Profil pegawai untuk app mobile (tab ketiga navbar).
 *
 * Read-only: identitas diambil dari sesi login. Edit profil belum termasuk.
 */
class Profil extends Controller
{
    protected $helpers = ['url'];

    public function index()
    {
        return view('profil/mobile', [
            'nama'    => (string) (session('NAMA') ?: 'Pegawai'),
            'jabatan' => (string) (session('NAMA_JABATAN') ?: ''),
            'unit'    => (string) (session('NAMA_UNIT') ?: ''),
            'noid'    => (string) (session('NOID') ?: ''),
            'email'   => (string) (session('EMAIL') ?: ''),
        ]);
    }
}
