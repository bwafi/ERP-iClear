<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class CreateTechnicianCashierServiceQueues extends Migration
{
    private const TECH_MENU = 10153;
    private const CASH_MENU = 10154;

    public function up()
    {
        $techSource = $this->db->table('menu')->where('url', 'service')->get()->getRow();
        $cashSource = $this->db->table('menu')->where('url', 'proses_service')->get()->getRow();
        if (! $techSource || ! $cashSource) {
            throw new RuntimeException('Menu izin Service atau Proses Service tidak ditemukan; migrasi antrean dibatalkan.');
        }

        $this->assertMenuIdAvailable(self::TECH_MENU, 'pekerjaan-teknisi');
        $this->assertMenuIdAvailable(self::CASH_MENU, 'antrean-kasir');

        if (! $this->db->tableExists('service_workflow_menu_state')) {
            $this->forge->addField([
                'menu_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'previous_show_menu' => ['type' => 'TINYINT', 'constraint' => 1],
            ]);
            $this->forge->addKey('menu_id', true);
            $this->forge->createTable('service_workflow_menu_state', true);
        }
        if ($this->db->table('service_workflow_menu_state')->countAllResults() === 0) {
            $this->db->table('service_workflow_menu_state')->insert([
                'menu_id' => (int) $techSource->idmenu,
                'previous_show_menu' => (int) ($techSource->show_menu ?? 1),
            ]);
        }

        if (! $this->db->tableExists('service_workflow')) {
            $this->forge->addField([
                'service_idservice' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'queue_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
                'technician_idakun' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'cashier_idakun' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'recommendation' => ['type' => 'TEXT', 'null' => true],
                'submitted_at' => ['type' => 'DATETIME', 'null' => true],
                'processing_at' => ['type' => 'DATETIME', 'null' => true],
                'returned_at' => ['type' => 'DATETIME', 'null' => true],
                'return_reason' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('service_idservice', true);
            $this->forge->addKey(['queue_status', 'cashier_idakun'], false, false, 'idx_sw_queue_cashier');
            $this->forge->addKey(['queue_status', 'technician_idakun'], false, false, 'idx_sw_queue_technician');
            $this->forge->createTable('service_workflow', true);
        }

        if (! $this->db->tableExists('service_workflow_event')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'service_idservice' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'event_type' => ['type' => 'VARCHAR', 'constraint' => 40],
                'actor_idakun' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'occurred_at' => ['type' => 'DATETIME'],
                'detail' => ['type' => 'TEXT', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['service_idservice', 'occurred_at'], false, false, 'idx_swe_service_time');
            $this->forge->createTable('service_workflow_event', true);
        }

        $this->upsertMenu(self::TECH_MENU, 'Pekerjaan Teknisi', 'pekerjaan-teknisi', $techSource, '<iconify-icon icon="solar:smartphone-2-bold" width="24" height="24"></iconify-icon>');
        $this->upsertMenu(self::CASH_MENU, 'Antrean Kasir', 'antrean-kasir', $cashSource, '<iconify-icon icon="solar:cash-out-bold" width="24" height="24"></iconify-icon>');
        $this->mirrorGrants((int) $techSource->idmenu, self::TECH_MENU);
        $this->mirrorGrants((int) $cashSource->idmenu, self::CASH_MENU);
        $this->db->table('menu')->where('idmenu', (int) $techSource->idmenu)->update(['show_menu' => 0]);
    }

    public function down()
    {
        foreach (['service_workflow_event', 'service_workflow'] as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException('Rollback ditolak: histori alur service sudah digunakan.');
            }
        }
        $this->removeGrants(self::TECH_MENU);
        $this->removeGrants(self::CASH_MENU);
        $this->db->table('menu')->whereIn('idmenu', [self::TECH_MENU, self::CASH_MENU])->delete();
        if ($this->db->tableExists('service_workflow_menu_state')) {
            foreach ($this->db->table('service_workflow_menu_state')->get()->getResult() as $state) {
                $this->db->table('menu')->where('idmenu', (int) $state->menu_id)->update(['show_menu' => (int) $state->previous_show_menu]);
            }
            $this->forge->dropTable('service_workflow_menu_state', true);
        }
        $this->forge->dropTable('service_workflow_event', true);
        $this->forge->dropTable('service_workflow', true);
    }

    private function assertMenuIdAvailable(int $id, string $route): void
    {
        $row = $this->db->table('menu')->where('idmenu', $id)->get()->getRow();
        if ($row && (string) $row->url !== $route) {
            throw new RuntimeException('ID menu ' . $id . ' sudah dipakai fitur lain.');
        }
    }

    private function upsertMenu(int $id, string $name, string $route, object $source, string $icon): void
    {
        $row = $this->db->table('menu')->where('idmenu', $id)->get()->getRow();
        $data = [
            'urutan' => (int) $source->urutan + ($id === self::TECH_MENU ? 1 : 2),
            'nama_menu' => $name,
            'roles' => $route,
            'url' => $route,
            'show_menu' => 1,
            'sub' => 0,
            'parent' => (int) $source->parent,
            'utama' => 0,
            'categories' => 0,
            'icon' => $icon,
            'manualbook' => '',
        ];
        if ($row) {
            $this->db->table('menu')->where('idmenu', $id)->update($data);
            return;
        }
        $this->db->table('menu')->insert(['idmenu' => $id] + $data);
    }

    private function mirrorGrants(int $sourceMenuId, int $targetMenuId): void
    {
        foreach ($this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult() as $row) {
            $roles = $this->decode($row->ROLES_JABATAN ?? null);
            if (in_array($sourceMenuId, array_map('intval', $roles), true)) {
                $this->db->table('jabatan')->where('ID_JABATAN', $row->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode(array_values(array_unique(array_merge(array_map('intval', $roles), [$targetMenuId]))))]);
            }
        }
        foreach ($this->db->table('akun')->select('ID_AKUN, ROLES')->get()->getResult() as $row) {
            $roles = $this->decode($row->ROLES ?? null);
            if (in_array($sourceMenuId, array_map('intval', $roles), true)) {
                $updatedRoles = array_values(array_unique(array_merge(array_map('intval', $roles), [$targetMenuId])));
                $this->db->table('akun')->where('ID_AKUN', $row->ID_AKUN)
                    ->update(['ROLES' => json_encode($updatedRoles)]);
            }
        }
    }

    private function removeGrants(int $menuId): void
    {
        foreach ([['jabatan', 'ID_JABATAN', 'ROLES_JABATAN'], ['akun', 'ID_AKUN', 'ROLES']] as [$table, $key, $column]) {
            foreach ($this->db->table($table)->select($key . ', ' . $column)->get()->getResult() as $row) {
                $roles = $this->decode($row->{$column} ?? null);
                $filtered = array_values(array_filter($roles, static fn ($role): bool => (int) $role !== $menuId));
                if ($filtered !== $roles) {
                    $this->db->table($table)->where($key, $row->{$key})->update([$column => json_encode($filtered)]);
                }
            }
        }
    }

    private function decode(?string $encoded): array
    {
        $roles = json_decode((string) $encoded, true);
        return is_array($roles) ? $roles : [];
    }
}
