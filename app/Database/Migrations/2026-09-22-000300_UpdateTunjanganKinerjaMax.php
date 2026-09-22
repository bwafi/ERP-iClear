<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perbaikan maksimum TUNJANGAN_KINERJA di salary_structures.
 *
 * Nilai acuan (per jabatan DIVISI & pendukung):
 *   - SPV (40):                 Rp1.500.000
 *   - Kepala Divisi / Digital Marketing (43): Rp1.500.000
 *   - Multimedia (44):          Rp750.000
 *   - IT / System (45):         Rp750.000
 *   - Admin Center (0):         Rp1.250.000   (baru, belum ada row)
 *   - Manager (34):             Rp2.250.000   (baru, belum ada row)
 *
 * NON-DESTRUKTIF & IDEMPOTEN:
 *   - UPDATE hanya baris yang sudah ada (scoped position_id + TUNJANGAN_KINERJA).
 *   - INSERT hanya bila baris belum ada (0 & 34), dijamin unik oleh
 *     UNIQUE(position_id, salary_component_id, unit_id, context, effective_from).
 *   - Tabel lain tidak disentuh.
 *
 * Catatan: KPIConfigurationSeeder adalah source-of-truth untuk instalasi baru;
 * jangan re-run seeder untuk meng-update data existing — pakai migration ini.
 */
class UpdateTunjanganKinerjaMax extends Migration
{
    /** salary_components.id untuk TUNJANGAN_KINERJA */
    private const COMP_TK = 2;

    /** position_id => base_value utk baris yang SUDAH ada (UPDATE). */
    private const UPDATE_MAP = [
        40 => 1500000.00, // SPV
        43 => 1500000.00, // Kepala Divisi / Digital Marketing
        44 => 750000.00,  // Multimedia
        45 => 750000.00,  // IT & System
    ];

    /** position_id => base_value utk posisi yang BELUM punya row TUNJANGAN_KINERJA (INSERT). */
    private const INSERT_MAP = [
        0  => 1250000.00, // Admin Center
        34 => 2250000.00, // Manager
    ];

    /** tanggal efektif baris baru (konsisten dgn tanggal migration). */
    private const EFFECTIVE_FROM = '2026-09-22';

    public function up()
    {
        $PC = 'percent_of_kpi';

        foreach (self::UPDATE_MAP as $positionId => $value) {
            $this->db->table('salary_structures')
                ->where('position_id', $positionId)
                ->where('salary_component_id', self::COMP_TK)
                ->update(['base_value' => $value]);
        }

        foreach (self::INSERT_MAP as $positionId => $value) {
            $exists = $this->db->table('salary_structures')
                ->where('position_id', $positionId)
                ->where('salary_component_id', self::COMP_TK)
                ->where('unit_id', null)
                ->where('context', 'default')
                ->countAllResults();

            if ($exists > 0) {
                continue; // sudah ter-insert (idempotent)
            }

            $this->db->table('salary_structures')->insert([
                'position_id'         => $positionId,
                'salary_component_id' => self::COMP_TK,
                'unit_id'             => null,
                'context'             => 'default',
                'base_value'          => $value,
                'calculation_type'    => $PC,
                'effective_from'      => self::EFFECTIVE_FROM,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        foreach ([40 => 1250000.00, 43 => 1000000.00, 44 => 250000.00, 45 => 250000.00] as $positionId => $value) {
            $this->db->table('salary_structures')
                ->where('position_id', $positionId)
                ->where('salary_component_id', self::COMP_TK)
                ->update(['base_value' => $value]);
        }

        foreach (self::INSERT_MAP as $positionId => $_) {
            $this->db->table('salary_structures')
                ->where('position_id', $positionId)
                ->where('salary_component_id', self::COMP_TK)
                ->where('unit_id', null)
                ->where('context', 'default')
                ->delete();
        }
    }
}