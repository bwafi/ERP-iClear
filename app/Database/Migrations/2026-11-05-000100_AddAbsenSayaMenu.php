<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah menu sidebar "Absen Saya" (10162) di parent DASHBOARD.
 *
 * `/absen` adalah halaman mobile-first tanpa baris menu. Menu ini menjadi
 * jalan masuk desktop untuk semua pegawai; grup **Absensi** tetap khusus
 * evaluator (approval, riwayat admin, jadwal masuk).
 *
 * Idempoten: cek keberadaan sebelum insert/merge — aman dijalankan ulang.
 */
class AddAbsenSayaMenu extends Migration
{
    private const MENU_ID = 10162;
    private const DASHBOARD_ID = 1;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 0,
        'nama_menu'  => 'Absen Saya',
        'roles'      => 'absen_saya',
        'url'        => 'absen',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => self::DASHBOARD_ID,
        'utama'      => 1,
        'categories' => 0,
        'icon'       => null,
    ];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $menu = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();
        if ($menu && $menu->url !== 'absen') {
            // Jangan menimpa menu lain bila id ini sudah dipakai di database berbeda.
            log_message('error', 'AddAbsenSayaMenu: idmenu {id} sudah dipakai menu "{nama}".', [
                'id'   => self::MENU_ID,
                'nama' => $menu->nama_menu,
            ]);

            return;
        }

        if (! $menu) {
            $this->db->table('menu')->insert($this->menuDef);
        }

        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach ($this->db->table('jabatan')->get()->getResult() as $row) {
            $this->grant((int) $row->ID_JABATAN);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if ($this->db->tableExists('menu')) {
            $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();
        }

        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach ($this->db->table('jabatan')->get()->getResult() as $row) {
            $roles = $this->roles((int) $row->ID_JABATAN);
            $sisa  = array_values(array_filter(
                $roles,
                static fn ($id) => (int) $id !== self::MENU_ID
            ));

            if ($sisa !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $row->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode($sisa)]);
            }
        }
    }

    private function grant(int $jabatan): void
    {
        $roles = $this->roles($jabatan);
        if (in_array(self::MENU_ID, array_map('intval', $roles), true)) {
            return;
        }

        $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->update([
            'ROLES_JABATAN' => json_encode(array_merge($roles, [self::MENU_ID])),
        ]);
    }

    private function roles(int $jabatan): array
    {
        $row   = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();
        $roles = json_decode((string) ($row->ROLES_JABATAN ?? ''), true);

        return is_array($roles) ? $roles : [];
    }
}
