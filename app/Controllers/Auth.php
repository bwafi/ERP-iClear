<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;

class Auth extends BaseController

{

    protected $ModelAuth;

    public function __construct()
    {
        $this->ModelAuth = new ModelAuth();
        $this->session = \Config\Services::session();
        $this->session->start();
    }

    public function login()
    {
        return view('auth/login');
    }

    function proses_login()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        // echo $username . " " . $password;

        // Untuk mengecek reCAPTCHA cloudflare
        // $turnstile_response = $this->request->getPost('cf-turnstile-response');
        // $secret_key = "0x4AAAAAAAeMYbxo_nLU2sdvndBSmVfGaJE";
        // $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        // $data = array(
        //     'secret' => $secret_key,
        //     'response' => $turnstile_response
        // );

        // $options = array(
        //     'http' => array(
        //         'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        //         'method' => 'POST',
        //         'content' => http_build_query($data),
        //     ),
        // );

        // $context = stream_context_create($options);
        // $result = file_get_contents($url, false, $context);
        // $resultJson = json_decode($result);

        // if ($resultJson->success) {
        $cekUsername = $this->ModelAuth->cekUsername($username);
        // die(json_encode($cekUsername->getRowArray()));
        if ($cekUsername->getNumRows() > 0) {
            $akun = $cekUsername->getRowArray();
            $pw_valid = $akun['PASSWORD'];
            if (password_verify($password, $pw_valid)) {
                // echo "Password benar";

                $nama_jabatan = $this->ModelAuth->get_nama_jabatan($akun['ID_JABATAN']);
                $log_akses = [
                    'agent' => $this->request->getUserAgent(),
                    'ip' => $this->request->getIPAddress(),
                    'user_id' => $akun['ID_AKUN'],
                    'username' => $akun['NAMA_AKUN'],
                    'login_time' => date('Y-m-d H:i:s'),
                ];

                // $insert_log = db_connect('db_logs')->table('log_akses')->insert($log_akses);
                // $idlogin = db_connect('db_logs')->insertID();

                $newSession = [
                    'ID_AKUN' => $akun['ID_AKUN'],
                    'NAMA' => $akun['NAMA_AKUN'],
                    'NOID' => $akun['NOID'],
                    'KTP' => $akun['KTP'],
                    'EMAIL' => $akun['EMAIL'],
                    'ID_JABATAN' => $nama_jabatan['ID_JABATAN'],
                    'NAMA_JABATAN' => $nama_jabatan['NAMA_JABATAN']
                ];
                session()->set($newSession);
                $this->session->set($newSession);
                $ID_UNIT = json_decode($akun['ID_UNIT']);

                if (is_array($ID_UNIT)) {
                    $ID_UNIT = $ID_UNIT[0];
                } else {
                    $ID_UNIT = $akun['ID_UNIT'];
                }
                $nama_gedung = $this->ModelAuth->get_unit($ID_UNIT);
                $SessionGedung = [
                    'ID_UNIT' => $nama_gedung['idunit'],
                    'NAMA_UNIT' => $nama_gedung['NAMA_UNIT'],
                    'logged_in' => true,
                    'is_select_gedung' => false
                ];
                session()->set($SessionGedung);
                $this->session->set($SessionGedung);
                session()->setFlashdata('success', 'Selamat Anda Berhasil Login');
                // echo json_encode($SessionGedung);
                return redirect()->to(base_url());
                // }
            } else {
                echo "Password salah";
                session()->setFlashdata('pesan_password', 'Password salah');
                return redirect()->to(base_url('login'));
            }
        } else {
            echo "Username tidak ditemukan";
            session()->setFlashdata('pesan_username', 'Username tidak ditemukan');
            return redirect()->to(base_url('login'));
        }
        // } 
        // else {
        //     session()->setFlashdata('pesan_username', 'Verifikasi Gagal');
        //     return redirect()->to(base_url('login'));
        // }
    }


    function setGedung()
    {
        $ID_GEDUNG = $this->request->getPost('ID_GEDUNG');
        $gedung = $this->ModelAuth->get_nama_gedung($ID_GEDUNG);
        $newSession = [
            'ID_GEDUNG' => $gedung['ID_GEDUNG'],
            'NAMA_GEDUNG' => $gedung['NAMA_GEDUNG'],
            'logged_in' => true,
            'is_select_gedung' => true
        ];
        session()->set($newSession);
        session()->setFlashdata('success', 'Selamat Anda Berhasil Login');
        return redirect()->to(base_url());
    }

    public function getRoles()
    {
        $ID_AKUN = session()->get('ID_AKUN');


        if (!$ID_AKUN) {
            return redirect()->to(base_url('login'));
        }
        $data_user = db_connect()->table("akun")->where("ID_AKUN", $ID_AKUN)->get()->getRow();
        if (!$data_user || empty($data_user->ROLES)) {
            return redirect()->to(base_url('login'));
        }

        $role = json_decode($data_user->ROLES);

        if (empty($role) || !is_array($role)) {
            return redirect()->to(base_url('login'));
        }

        $menu = db_connect()->table("menu")->whereIn("idmenu", $role)->get()->getResult();

        return json_encode($menu);
    }


    function proses_darkmode()
    {
        if (session()->get('darkmode')) {
            session()->remove('darkmode');
        } else {
            session()->set('darkmode', true);
        }
        return redirect()->back();
    }

    public function changePassword()
    {
    $userId = session('ID_AKUN');
    $newPassword = $this->request->getPost('new_password');
    $confirmPassword = $this->request->getPost('confirm_password');

    if (empty($newPassword) || empty($confirmPassword)) {
        return redirect()->back()->with('error', 'Password tidak boleh kosong.');
    }

    if ($newPassword !== $confirmPassword) {
        return redirect()->back()->with('error', 'Konfirmasi password tidak cocok.');
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $userModel = new \App\Models\ModelAuth();
    $userModel->update($userId, ['PASSWORD' => $hashedPassword]);

    return redirect()->back()->with('success', 'Password berhasil diubah.');
    }

    function proses_logout()
    {
        $idlogin = session()->get('ID_LOGIN');
        $log_akses = [
            'logout_time' => date('Y-m-d H:i:s'),
        ];
        // $update_log = db_connect('db_logs')->table('log_akses')->where('id', $idlogin)->update($log_akses);
        session()->destroy();
        return redirect()->to(base_url() . 'login');
    }
}