<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kurasi akses menu jabatan 44 (Multimedia): HANYA Dashboard Multimedia (10041)
 * & Manajemen Konten (10042) di bawah Digital Marketing (10040).
 * Semua child Digital Marketing lain (Performa Channel, Dashboard DM, Detail
 * Prospek, Performa Ads, Rekap Harian, Laporan DM) dicabut.
 */
class RevokeDmForMultimediaJabatan44 extends Migration
{
    private int $targetJabatan = 44;

    private array $removedMenuIds = [10043, 10044, 10045, 10046, 10047, 10048];

    public function up()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', $this->targetJabatan)
            ->get()
            ->getRow();

        if (!$row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            return;
        }

        // Hapus menu yang tidak diizinkan, lalu pastikan parent+2 child tetap ada.
        $filtered = array_values(array_diff($roles, $this->removedMenuIds));
        $kept = array_values(array_unique(array_merge($filtered, [10040, 10041, 10042])));

        if ($kept !== $roles) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $this->targetJabatan)
                ->update(['ROLES_JABATAN' => json_encode($kept)]);
        }
    }

    public function down()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', $this->targetJabatan)
            ->get()
            ->getRow();

        if (!$row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            return;
        }

        // Kembalikan child DM yang dihapus.
        $restored = array_values(array_unique(array_merge($roles, $this->removedMenuIds)));

        if ($restored !== $roles) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $this->targetJabatan)
                ->update(['ROLES_JABATAN' => json_encode($restored)]);
        }
    }
}