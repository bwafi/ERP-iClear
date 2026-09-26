<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed sidebar menu fitur Kas & Bank (grup dropdown) + grant akses ke jabatan
 * yang sudah memiliki menu Kas Masuk/Kas Keluar (154).
 *
 * idmenu:
 *   10120 Kas & Bank (parent/kategori)
 *   10121 Dashboard Kas & Bank       -> kas_bank
 *   10122 Master Akun & Saldo Awal   -> kas_bank/akun
 *   10123 Transfer & Pembayaran Antar Unit -> kas_bank/antar_unit
 */
class SeedKasBankMenus extends Migration
{
    private array $menuRows = [
        [
            'idmenu' => 10120,
            'urutan' => 10120,
            'nama_menu' => 'Kas & Bank',
            'roles' => 'kas_bank',
            'url' => null,
            'show_menu' => 1,
            'sub' => 0,
            'parent' => 0,
            'utama' => 1,
            'categories' => 1,
            'icon' => '<iconify-icon icon="solar:wallet-money-bold" width="24" height="24"></iconify-icon>',
            'manualbook' => null,
        ],
        [
            'idmenu' => 10121,
            'urutan' => 10121,
            'nama_menu' => 'Dashboard',
            'roles' => 'kas_bank',
            'url' => 'kas_bank',
            'show_menu' => 1,
            'sub' => 0,
            'parent' => 10120,
            'utama' => 1,
            'categories' => 0,
            'icon' => null,
            'manualbook' => null,
        ],
        [
            'idmenu' => 10122,
            'urutan' => 10122,
            'nama_menu' => 'Master Akun & Saldo Awal',
            'roles' => 'akun_kas_bank',
            'url' => 'kas_bank/akun',
            'show_menu' => 1,
            'sub' => 0,
            'parent' => 10120,
            'utama' => 1,
            'categories' => 0,
            'icon' => null,
            'manualbook' => null,
        ],
        [
            'idmenu' => 10123,
            'urutan' => 10123,
            'nama_menu' => 'Transfer & Pembayaran Antar Unit',
            'roles' => 'antar_unit',
            'url' => 'kas_bank/antar_unit',
            'show_menu' => 1,
            'sub' => 0,
            'parent' => 10120,
            'utama' => 1,
            'categories' => 0,
            'icon' => null,
            'manualbook' => null,
        ],
    ];

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

    public function up()
    {
        foreach ($this->menuRows as $row) {
            $exists = $this->db->table('menu')->where('idmenu', $row['idmenu'])->get()->getRow();
            if ($exists) {
                continue;
            }
            $this->db->table('menu')->insert($row);
        }

        $grantIds = array_column($this->menuRows, 'idmenu');

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

            $merged = array_values(array_unique(array_merge($roles, $grantIds)));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatan)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
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
                continue;
            }

            $filtered = array_values(array_diff($roles, [10120, 10121, 10122, 10123]));

            if ($filtered !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatan)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }

        $this->db->table('menu')->whereIn('idmenu', [10120, 10121, 10122, 10123])->delete();
    }
}