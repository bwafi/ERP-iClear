<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Refactor skema KONTROL_ASET KPI:
 *
 *   aset_kpi            → Asset MASTER (baseline): unit, nama, kode, quantity,
 *                         is_active (aktif/nonaktif), keterangan.
 *                         Quantity master TIDAK pernah diubah hasil audit.
 *
 *   audit_aset_periode  → sesi audit per (unit, bulan, tahun), status DRAFT/FINAL.
 *                         Satu unit hanya satu periode final per bulan.
 *
 *   audit_aset_item     → hasil audit per aset dalam periode:
 *                         quantity_ditemukan (sumber Existence),
 *                         perawatan TERAWAT/TIDAK_TERAWAT (sumber Maintenance),
 *                         kondisi (INFORMASI saja, tidak dipakai KPI), keterangan.
 *
 *   Menu 10028 diubah menjadi "Asset Master" (Admin Center 0, Root 1,
 *   Direktur 2, Manager 34). Menu baru 10029 "Kontrol Aset" (SPV 40).
 */
class RefactorAsetKpiToMasterControl extends Migration
{
    public function up()
    {
        // Unit lama (implementasi pertama) — tabel masih kosong, di-drop.
        $this->db->query('DROP TABLE IF EXISTS audit_aset_kpi');
        $this->db->query('DROP TABLE IF EXISTS aset_kpi');

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS aset_kpi (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                unit INT NOT NULL,
                asset VARCHAR(200) NOT NULL,
                kode_aset VARCHAR(20) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                is_active TINYINT NOT NULL DEFAULT 1,
                keterangan TEXT NULL,
                created_by INT NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_kode_aset (kode_aset),
                KEY idx_unit (unit)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS audit_aset_periode (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                unit INT NOT NULL,
                bulan TINYINT NOT NULL,
                tahun SMALLINT NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'DRAFT',
                tanggal_audit DATE NULL,
                auditor_id INT NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_unit_periode (unit, bulan, tahun)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS audit_aset_item (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                periode_id INT UNSIGNED NOT NULL,
                aset_kpi_id INT UNSIGNED NOT NULL,
                quantity_ditemukan INT NULL,
                kondisi VARCHAR(50) NULL,
                perawatan VARCHAR(20) NULL,
                keterangan TEXT NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_periode_aset (periode_id, aset_kpi_id),
                KEY idx_aset (aset_kpi_id),
                CONSTRAINT fk_ap FOREIGN KEY (periode_id) REFERENCES audit_aset_periode (id) ON DELETE CASCADE,
                CONSTRAINT fk_ak FOREIGN KEY (aset_kpi_id) REFERENCES aset_kpi (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        // ── Menu: ganti 10028 → Asset Master (di bawah induk BARU 10030 "MASTER ASET") ──
        $rows = $this->db->table('menu')->where('idmenu', 10028)->countAllResults();
        if ($rows > 0) {
            $this->db->table('menu')->where('idmenu', 10028)->update([
                'nama_menu' => 'Asset Master',
                'roles'     => 'asset_master',
                'url'       => 'penilaian/kpi/aset_master',
                'urutan'    => 1,
                'parent'    => 10030,
            ]);
        } else {
            $this->db->table('menu')->insert([
                'idmenu'     => 10028,
                'urutan'     => 1,
                'nama_menu'  => 'Asset Master',
                'roles'      => 'asset_master',
                'url'        => 'penilaian/kpi/aset_master',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10030,
                'utama'      => 1,
                'categories' => 0,
                'icon'       => null,
            ]);
        }

        // Menu induk baru: MASTER ASET (10030) — parent 0 (categories=1).
        if ($this->db->table('menu')->where('idmenu', 10030)->countAllResults() === 0) {
            $this->db->table('menu')->insert([
                'idmenu'     => 10030,
                'urutan'     => 106,
                'nama_menu'  => 'Master Aset',
                'roles'      => 'master_aset',
                'url'        => '',
                'show_menu'  => 1,
                'sub'        => 1,
                'parent'     => 0,
                'utama'      => 1,
                'categories' => 1,
                'icon'       => '<iconify-icon icon="solar:database-bold" width="24" height="24"></iconify-icon>',
            ]);
        }

        // Menu: Kontrol Aset (10029) — parent 10030 (MASTER ASET).
        if ($this->db->table('menu')->where('idmenu', 10029)->countAllResults() === 0) {
            $this->db->table('menu')->insert([
                'idmenu'     => 10029,
                'urutan'     => 2,
                'nama_menu'  => 'Kontrol Aset',
                'roles'      => 'kontrol_aset',
                'url'        => 'penilaian/kpi/kontrol_aset',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10030,
                'utama'      => 1,
                'categories' => 0,
                'icon'       => null,
            ]);
        } else {
            $this->db->table('menu')->where('idmenu', 10029)->update([
                'parent' => 10030,
                'urutan' => 2,
            ]);
        }

        // ── Hak akses ───────────────────────────────────────────────────────
        // Menu induk MASTER ASET (10030): 0,1,2,34,40
        $this->addRoles([0, 1, 2, 34, 40], 10030);
        // Asset Master (10028): 0,1,2,34 (Admin/Root/Direktur/Manager)
        $this->addRoles([0, 1, 2, 34], 10028);
        $this->removeRoles([40], 10028);       // SPV tidak dapat Asset Master
        // Kontrol Aset (10029): 0,1,2,40 (Admin/Root/Direktur/SPV)
        $this->addRoles([0, 1, 2, 40], 10029);
    }

    /**
     * Tambah id menu ke ROLES_JABATAN jabatan tertentu.
     *
     * @param int[] $jabatans
     */
    private function addRoles(array $jabatans, int $menuId)
    {
        foreach ($jabatans as $jabatanId) {
            $this->mutateRoles($jabatanId, function (array $roles) use ($menuId) {
                if (!in_array($menuId, $roles, true)) {
                    $roles[] = $menuId;
                }
                return $roles;
            });
        }
    }

    /**
     * Hapus id menu dari ROLES_JABATAN jabatan tertentu.
     *
     * @param int[] $jabatans
     */
    private function removeRoles(array $jabatans, int $menuId)
    {
        foreach ($jabatans as $jabatanId) {
            $this->mutateRoles($jabatanId, function (array $roles) use ($menuId) {
                return array_values(array_diff($roles, [$menuId]));
            });
        }
    }

    /**
     * Baca-ubah-tulis ROLES_JABATAN satu jabatan.
     */
    private function mutateRoles(int $jabatanId, callable $fn)
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
        if (!$row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            $roles = [];
        }

        $newRoles = array_values(array_unique($fn($roles)));
        if (json_encode($newRoles) !== json_encode($roles)) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($newRoles)]);
        }
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS audit_aset_item');
        $this->db->query('DROP TABLE IF EXISTS audit_aset_periode');
        $this->db->query('DROP TABLE IF EXISTS aset_kpi');
    }
}

