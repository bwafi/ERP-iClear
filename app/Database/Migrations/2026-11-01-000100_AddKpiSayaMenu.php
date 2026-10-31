<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah menu sidebar "KPI Saya" (10105) untuk pegawai cabang.
 *
 * Halaman /kpi_saya hanya menampilkan skor KPI milik pegawai yang login.
 * Menu diberikan ke jabatan yang punya minimal satu pegawai aktif di unit
 * selain Head Office (unit 50). Guard unit tetap dilakukan di controller;
 * migration ini hanya mengatur tampilnya menu.
 *
 * Idempotent: cek keberadaan sebelum insert/merge — aman dijalankan ulang.
 */
class AddKpiSayaMenu extends Migration
{
    private const MENU_ID = 10105;
    private const HO_UNIT_ID = 50;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 125,
        'nama_menu'  => 'KPI Saya',
        'roles'      => 'kpi_saya',
        'url'        => 'kpi_saya',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0,
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:chart-bold" width="22" height="22"></iconify-icon>',
    ];

    public function up()
    {
        $exists = $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('menu')->insert($this->menuDef);
        }

        $jabatanIds = $this->db->table('akun')
            ->select('ID_JABATAN')
            ->distinct()
            ->where('STATUS_PEGAWAI', 1)
            ->where('ID_UNIT IS NOT NULL', null, false)
            ->where('ID_UNIT !=', self::HO_UNIT_ID)
            ->get()
            ->getResultArray();

        foreach ($jabatanIds as $row) {
            $this->grant((int) $row['ID_JABATAN']);
        }
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();

        foreach ($this->db->table('jabatan')->get()->getResult() as $row) {
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                continue;
            }

            $bersih = array_values(array_filter(
                $roles,
                static fn ($id) => (int) $id !== self::MENU_ID
            ));

            if ($bersih !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $row->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode($bersih)]);
            }
        }
    }

    private function grant(int $jabatanId): void
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', $jabatanId)
            ->get()
            ->getRow();

        if (! $row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (! is_array($roles)) {
            $roles = [];
        }

        $merged = array_values(array_unique(array_merge($roles, [self::MENU_ID])));

        if ($merged !== $roles) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($merged)]);
        }
    }
}
