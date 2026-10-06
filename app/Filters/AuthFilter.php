<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    //
    public function before(RequestInterface $request, $arguments = null)
    {
        // return redirect()->to(base_url('/maintenance/page'));

        // Syaratnya HANYA "sudah login". Sebelumnya memakai
        //   !logged_in && ID_UNIT == null
        // yang berarti sesi yang punya ID_UNIT tapi belum terautentikasi tetap
        // lolos. ID_UNIT memang selalu di-set bersamaan dengan logged_in, jadi
        // cukup cek logged_in. Alur login "pilih gedung" (hanya menyimpan
        // ID_GEDUNG) tetap lolos karena tetap menyertakan logged_in.
        if (session()->get('logged_in') !== true) {
            return redirect()->to(base_url('/Login'))->with('error', "Invalid Credential Login");
        }
            // $ID_AKUN = session()->get("ID_AKUN");
            // $data_user = db_connect()->table("akun")->where("ID_AKUN", "$ID_AKUN")->get()->getRow();
            // $data_jabatan = db_connect()->table("jabatan")->where("ID_JABATAN", $data_user->ID_JABATAN)->get()->getRow();
            // $role = array("1");
            // $role_user = json_decode($data_user->ROLES);
            // $role_jabatan = json_decode($data_jabatan->ROLES_JABATAN);
            // if (is_array($role_jabatan)) {
            //     if (@count($role_jabatan) > 0) {
            //         $role = @array_merge($role, @$role_jabatan);
            //     }
            // }
            // if (is_array($role_user)) {
            //     if (@count($role_user) > 0) {
            //         $role = @array_merge($role, @$role_user);
            //     }
            // }
            // // echo json_encode($role);
            // // die();
            // $menu = db_connect()->table("menu")->whereIn("idmenu", $role)->get()->getResult();
            // $isaccess = false;
            // foreach ($menu as $value) {
            //     if ($value->roles == $request->uri->getSegment(1) || $request->uri->getSegment(1) == "") {
            //         $isaccess = true;
            //     }
            // }
            // if (!$isaccess) {
            //     return redirect()->to(base_url('/notFound'))->with('error', "Invalid Credential");
            // }
    }

    //--------------------------------------------------------------------

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Ambil instance database dan tutup koneksi
        $db = \Config\Database::connect();
        $db->close(); // Menutup koneksi database
    }
}
