<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perbaiki menu "Mutasi Stok".
 *
 * Migration 000500 keliru: kolom 'idmenu' tidak di-set sehingga baris menu
 * jatuh ke AUTO_INCREMENT (10126), padahal role yang digrant adalah '26' —
 * akibatnya menu "Konfirmasi Terima Mutasi" tidak pernah muncul di sidebar,
 * dan karena menu 25 ("Mutasi Stok") kini punya child, link langsung ke
 * halaman input mutasi ikut hilang.
 *
 * Di sini:
 *  1. Hapus baris orphan 10126 (bila ada).
 *  2. Pastikan menu 26 "Konfirmasi Terima Mutasi" ada (parent 25).
 *  3. Tambah child baru id 36 "Input Mutasi Stok" (url mutasi_stok,
 *     parent 25) agar halaman input kembali bisa diakses dari sidebar.
 *  4. Enforce akses: hanya admin root (1), direktur (2), manager (34),
 *     admin center (0), spv (40), kepala toko (41), dan admin/kasir cabang
 *     (35, 47) yang boleh melihat menu Mutasi Stok & Konfirmasi Terima.
 */
class FixKonfirmasiTerimaMutasiMenu extends Migration
{
    private const TARGET = [0, 1, 2, 34, 35, 40, 41, 47];

    public function up()
    {
        // 1. Hapus baris orphan dari migration lama.
        $this->db->table('menu')->where('idmenu', 10126)->delete();

        // 2. Pastikan child "Konfirmasi Terima Mutasi" punya id mana 26.
        if (!$this->db->table('menu')->where('idmenu', 26)->get()->getRow()) {
            $this->db->table('menu')->insert([
                'idmenu'     => 26,
                'urutan'     => 2,
                'nama_menu'  => 'Konfirmasi Terima Mutasi',
                'roles'      => 'mutasi_stok',
                'url'        => 'mutasi_stok/masuk',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 25,
                'utama'      => 0,
                'categories' => 0,
                'icon'       => null,
                'manualbook' => null,
            ]);
        }

        // 3. Child "Input Mutasi Stok" (halaman form mutasi).
        if (!$this->db->table('menu')->where('idmenu', 36)->get()->getRow()) {
            $this->db->table('menu')->insert([
                'idmenu'     => 36,
                'urutan'     => 1,
                'nama_menu'  => 'Input Mutasi Stok',
                'roles'      => 'mutasi_stok',
                'url'        => 'mutasi_stok',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 25,
                'utama'      => 0,
                'categories' => 0,
                'icon'       => null,
                'manualbook' => null,
            ]);
        }

        $this->enforceAkses();
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', 26)->delete();
        $this->db->table('menu')->where('idmenu', 36)->delete();

        // Kembalikan role seperti sebelum enforce (25-holder lama + 26).
        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult();
        foreach ($jabatan as $j) {
            $roles = json_decode((string) $j->ROLES_JABATAN, true);
            if (!is_array($roles)) {
                continue;
            }
            $roles = array_values(array_diff($roles, ['25', '26', '36']));
            if (in_array((int) $j->ID_JABATAN, [0, 1, 2, 34, 35, 38, 39, 40, 41, 47], true)) {
                array_push($roles, '25', '26');
            }
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $j->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
        }
    }

    private function enforceAkses(): void
    {
        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult();
        foreach ($jabatan as $j) {
            $roles = json_decode((string) $j->ROLES_JABATAN, true);
            if (!is_array($roles)) {
                continue;
            }
            $ada = in_array((int) $j->ID_JABATAN, self::TARGET, true);
            $rolesBaru = [];
            foreach ($roles as $r) {
                if ($ada) {
                    $rolesBaru[] = $r;
                } elseif (!in_array((string) $r, ['25', '26', '36'], true)) {
                    $rolesBaru[] = $r;
                }
            }
            if ($ada) {
                foreach (['25', '26', '36'] as $m) {
                    if (!in_array($m, $rolesBaru, true)) {
                        $rolesBaru[] = $m;
                    }
                }
            }
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $j->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode(array_values($rolesBaru))]);
        }
    }
}