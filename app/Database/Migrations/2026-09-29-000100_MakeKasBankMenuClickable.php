<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jadikan menu parent "Kas & Bank" (10120) bisa diklik.
 *
 * 10120 dibuat sebagai kategori/dropdown dengan url = NULL, sehingga di sidebar
 * tautannya selalu "href="#" (hanya expand/collapse) dan user tidak bisa
 * masuk ke halaman /kas_bank lewat menu utama.
 *
 * Fix ini hanya mengisi kolom url pada 10120 -> 'kas_bank'. Halaman ringkasan
 * (/kas_bank) memang sudah punya submenu sendiri ("Dashboard", 10121) dengan
 * url yang sama, jadi tidak ada perubahan perilaku lain.
 *
 * Idempotent dan non-destruktif terhadap ROLES_JABATAN.
 */
class MakeKasBankMenuClickable extends Migration
{
    private const MENU_ID = 10120;

    private const URL = 'kas_bank';

    public function up()
    {
        $menu = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();

        if (!$menu) {
            // Kategori belum ada: seed asumsi tidak dijalankan, biarkan saja.
            return;
        }

        if ((string) ($menu->url ?? '') === self::URL) {
            return;
        }

        $this->db->table('menu')->where('idmenu', self::MENU_ID)->update(['url' => self::URL]);
    }

    public function down()
    {
        $menu = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();

        if (!$menu) {
            return;
        }

        $this->db->table('menu')->where('idmenu', self::MENU_ID)->update(['url' => null]);
    }
}
