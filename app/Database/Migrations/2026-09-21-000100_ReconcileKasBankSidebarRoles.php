<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rekonsiliasi sidebar Kas & Bank.
 *
 * Draf awal migration KonsepRekeningFisikKasBank (000400) sempat menginsert
 * idmenu 10125 "Transfer Internal", sementara file final memakai 10124.
 * Akibatnya di DB: 10125 ada, 10124 tidak, dan id anak 10121..10123 sempat
 * terhapus dari ROLES_JABATAN (oleh down() 000400) sehingga sidebar cuma
 * menampilkan kategori "Kas & Bank" tanpa submenu.
 *
 * Perbaikan (idempotent, tidak destruktif terhadap roles):
 * 1. Kanonikan item menu transfer ke idmenu 10124 (rename dari 10125 bila ada).
 * 2. Pastikan 10121..10124 terdaftar pada jabatan yang sama dengan seed
 *    SeedKasBankMenus (000200): 0, 1, 2, 34, 35, 40, 41, 47.
 */
class ReconcileKasBankSidebarRoles extends Migration
{
    private array $targetJabatans = [
        0,   // ADMIN CENTER
        1,   // Admin root
        2,   // Direktur
        34,  // Manager
        35,  // ADMIN / KASIR
        40,  // SPV
        41,  // Kepala Toko
        47,  // ADMIN / KASIR
    ];

    private array $grantIds = ['10121', '10122', '10123', '10124'];

    public function up()
    {
        // 1. Kanonikan idmenu transfer: 10125 -> 10124 bila ada.
        $tua = $this->db->table('menu')->where('idmenu', 10125)->get()->getRow();
        if ($tua) {
            $tujuan = $this->db->table('menu')->where('idmenu', 10124)->get()->getRow();
            $idAkhir = $tujuan ? 10124 : $tua->idmenu;
            if ((int) $tua->idmenu !== $idAkhir) {
                $this->db->table('menu')->where('idmenu', 10125)->update(['idmenu' => 10124]);
            }
            if ($tujuan) {
                // 10125 dan 10124 duplikat: buang yang lama (10125).
                $this->db->table('menu')->where('idmenu', 10125)->delete();
            }
        }

        // 2. Pastikan menu transfer ada (fallback bila belum pernah dibuat).
        $menu = $this->db->table('menu')->where('idmenu', 10124)->get()->getRow();
        if (!$menu) {
            $this->db->table('menu')->insert([
                'urutan'     => 10124,
                'nama_menu'  => 'Transfer Internal',
                'roles'      => 'transfer_internal',
                'url'        => 'kas_bank/transfer',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10120,
                'utama'      => 0,
                'categories' => 0,
                'icon'       => null,
                'manualbook' => null,
            ]);
        }

        // 3. Grant id anak 10121..10124 ke jabatan target (tanpa mengubah yang lain).
        foreach ($this->targetJabatans as $jabatan) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatan)
                ->get()
                ->getRow();
            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, $this->grantIds)));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatan)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        // Kembalikan id menu ke 10125 bila rekonsiliasi mengubahnya, tanpa
        // menyentuh ROLES_JABATAN (grant 10121..10123 milik seed 000200).
        $baru = $this->db->table('menu')->where('idmenu', 10124)->get()->getRow();
        $tua  = $this->db->table('menu')->where('idmenu', 10125)->get()->getRow();
        if ($baru && !$tua) {
            $this->db->table('menu')->where('idmenu', 10124)->update(['idmenu' => 10125]);
        }
    }
}