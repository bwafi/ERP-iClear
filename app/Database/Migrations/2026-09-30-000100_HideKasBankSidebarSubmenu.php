<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sembunyikan submenu "Kas & Bank" dari sidebar.
 *
 * Modul Kas & Bank sudah menyediakan navigasi sendiri di dalam halaman:
 * segmented control pada app/Views/kas_bank/_nav.php (Ringkasan, Rekening &
 * Saldo, Pindah Saldo, Bayar Antar Unit). Empat menu anak yang masih
 * terdaftar di tabel `menu` (10121 Dashboard, 10122 Master Akun & Saldo Awal,
 * 10123 Transfer & Pembayaran Antar Unit, 10124 Transfer Internal)
 * menduplikasi tab tersebut dan memaksa parent 10120 dirender sebagai
 * expander.
 *
 * Panah pada app/Views/inc/left_vertical.php:82-97 muncul hanya bila
 * sizeof($mymenu['menu']) > 0, sedangkan chevron-nya digambar oleh
 * .has-arrow::after di public/template/assets/css/styles.css:17056.
 *
 * Solusinya cukup set show_menu = 0 pada anak-anak 10120 supaya
 * Core::get_menu_show() (app/Models/Core.php:246-248) tidak mengambilnya lagi
 * dan 10120 jatuh ke branch "Menu Tanpa Sub-menu" (left_vertical.php:65-79) —
 * link biasa ke /kas_bank tanpa panah.
 *
 * Baris menu dan ROLES_JABATAN tidak dihapus, jadi hak akses tetap utuh dan
 * down() cukup mengembalikan show_menu. Idempotent.
 */
class HideKasBankSidebarSubmenu extends Migration
{
    private const PARENT_ID = 10120;

    public function up()
    {
        $this->db->table('menu')
            ->where('parent', self::PARENT_ID)
            ->where('show_menu', 1)
            ->update(['show_menu' => 0]);
    }

    public function down()
    {
        $this->db->table('menu')
            ->where('parent', self::PARENT_ID)
            ->where('show_menu', 0)
            ->update(['show_menu' => 1]);
    }
}
