<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddAdminOnlyProductTypeReviewMenu extends Migration
{
    private const MENU_ID = 10151;
    private const ADMIN_JABATAN_ID = 1;
    private const PRODUCT_MENU_ID = 6;
    private const ROUTE = 'produk/review-tipe';

    public function up()
    {
        $admin = $this->db->table('jabatan')
            ->where('ID_JABATAN', self::ADMIN_JABATAN_ID)
            ->get()
            ->getRow();

        if (!$admin || strcasecmp((string) $admin->NAMA_JABATAN, 'Admin root') !== 0) {
            throw new RuntimeException('Administrator jabatan ID 1 tidak terverifikasi; menu review tidak ditambahkan.');
        }

        $productMenu = $this->db->table('menu')
            ->where('idmenu', self::PRODUCT_MENU_ID)
            ->get()
            ->getRow();

        if (!$productMenu || strcasecmp((string) $productMenu->nama_menu, 'Produk') !== 0) {
            throw new RuntimeException('Menu Produk induk tidak ditemukan; menu review tidak ditambahkan.');
        }

        $byId = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();
        $byRoute = $this->db->table('menu')->where('url', self::ROUTE)->get()->getRow();

        if ($byId && ((string) $byId->url !== self::ROUTE || (string) $byId->nama_menu !== 'Review Tipe Item')) {
            throw new RuntimeException('ID menu 10151 sudah dipakai fitur lain; migrasi dibatalkan.');
        }

        if ($byRoute && (int) $byRoute->idmenu !== self::MENU_ID) {
            throw new RuntimeException('Rute Review Tipe Item sudah terdaftar dengan ID lain; migrasi dibatalkan.');
        }

        $this->db->transStart();

        if (!$byId) {
            $this->db->table('menu')->insert([
                'idmenu' => self::MENU_ID,
                'urutan' => 1009,
                'nama_menu' => 'Review Tipe Item',
                'roles' => 'produk/review-tipe',
                'url' => self::ROUTE,
                'show_menu' => 1,
                'sub' => 0,
                'parent' => self::PRODUCT_MENU_ID,
                'utama' => 0,
                'categories' => 0,
                'icon' => '',
                'manualbook' => '',
            ]);
        }

        $roles = json_decode((string) $admin->ROLES_JABATAN, true);
        if (!is_array($roles)) {
            throw new RuntimeException('Daftar izin Administrator tidak valid; migrasi dibatalkan.');
        }

        if (!in_array(self::MENU_ID, array_map('intval', $roles), true)) {
            $roles[] = self::MENU_ID;
            $this->db->table('jabatan')
                ->where('ID_JABATAN', self::ADMIN_JABATAN_ID)
                ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
        }

        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            throw new RuntimeException('Gagal menambahkan menu Review Tipe Item secara transaksional.');
        }
    }

    public function down()
    {
        $admin = $this->db->table('jabatan')
            ->where('ID_JABATAN', self::ADMIN_JABATAN_ID)
            ->get()
            ->getRow();

        if ($admin) {
            $roles = json_decode((string) $admin->ROLES_JABATAN, true);
            if (is_array($roles)) {
                $roles = array_values(array_filter(
                    $roles,
                    static fn ($id): bool => (int) $id !== self::MENU_ID
                ));
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', self::ADMIN_JABATAN_ID)
                    ->update(['ROLES_JABATAN' => json_encode($roles)]);
            }
        }

        $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->where('url', self::ROUTE)
            ->delete();
    }
}
