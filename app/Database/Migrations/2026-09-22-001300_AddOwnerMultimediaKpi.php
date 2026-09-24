<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Struktur KPI Multimedia (jabatan 44) MENGIKUTI PEDOMAN OWNER:
 *
 *   | KPI               | Bobot |
 *   | Ketepatan Deadline| 25%   |
 *   | Kualitas Output   | 25%   |
 *   | Kesesuaian Brief  | 20%   |
 *   | Produktivitas     | 15%   |
 *   | Support Campaign  | 10%   |
 *   | Improvement       | 5%    |
 *
 * Perubahan data:
 *   - Tabel baru: content_briefs, content_campaigns, improvements.
 *   - Kolom baru : contents.campaign_id, content_qc.sesuai_brief.
 *   - Komponen KPI baru (6) + bobot posisi 44 diganti (total 100).
 *
 * NON-destruktif untuk posisi lain:
 *   - KONTEN_BRAND / KONTEN_PERFORMA / CHANNEL_GROWTH tetap ada di
 *     kpi_components (dipakai posisi 43 & dashboard), HANYA bobot posisi 44
 *     yang dilepas. CHANNEL_GROWTH utk posisi 43 tidak disentuh.
 */
class AddOwnerMultimediaKpi extends Migration
{
    private const NEW_CODES = [
        'KETEPATAN_DEADLINE',
        'KUALITAS_OUTPUT',
        'KESESUAIAN_BRIEF',
        'PRODUKTIVITAS',
        'SUPPORT_CAMPAIGN',
        'IMPROVEMENT',
    ];

    private const NEW_NAMES = [
        'KETEPATAN_DEADLINE' => 'Ketepatan Deadline',
        'KUALITAS_OUTPUT'    => 'Kualitas Output',
        'KESESUAIAN_BRIEF'   => 'Kesesuaian Brief',
        'PRODUKTIVITAS'      => 'Produktivitas',
        'SUPPORT_CAMPAIGN'   => 'Support Campaign',
        'IMPROVEMENT'        => 'Improvement',
    ];

    // Bobot lama posisi 44 (untuk down()).
    private const OLD_WEIGHTS_44 = [
        'KONTEN_JUMLAH'    => 15,
        'KONTEN_DEADLINE'  => 15,
        'KONTEN_KUALITAS'  => 25,
        'KONTEN_BRAND'     => 15,
        'KONTEN_PERFORMA'  => 20,
        'CHANNEL_GROWTH'   => 10,
    ];

    private const NEW_WEIGHTS_44 = [
        'KETEPATAN_DEADLINE' => 25,
        'KUALITAS_OUTPUT'    => 25,
        'KESESUAIAN_BRIEF'   => 20,
        'PRODUKTIVITAS'      => 15,
        'SUPPORT_CAMPAIGN'   => 10,
        'IMPROVEMENT'        => 5,
    ];

    public function up()
    {
        // ── content_briefs (1:1 dgn content): isi brief & persetujuan ──
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'content_id'  => ['type' => 'INT'],
            'isi_brief'   => ['type' => 'TEXT', 'null' => true],
            'requirement' => ['type' => 'TEXT', 'null' => true],
            'approved_by' => ['type' => 'INT', 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('content_id', false, true);
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('content_briefs', true);

        // ── content_campaigns (master campaign marketing) ──
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'auto_increment' => true],
            'nama'               => ['type' => 'VARCHAR', 'constraint' => 191],
            'deskripsi'          => ['type' => 'TEXT', 'null' => true],
            'period_month'       => ['type' => 'TINYINT'],
            'period_year'        => ['type' => 'SMALLINT'],
            'target_jumlah_konten' => ['type' => 'INT', 'null' => true],
            'target_deadline'    => ['type' => 'DATE', 'null' => true],
            'status'             => ['type' => 'ENUM', 'constraint' => ['draft', 'active', 'done'], 'default' => 'draft'],
            'pic'                => ['type' => 'INT', 'null' => true],
            'created_by'         => ['type' => 'INT', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['period_year', 'period_month']);
        $this->forge->addForeignKey('pic', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('content_campaigns', true);

        // ── improvements (ide perbaikan multimedia) ──
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'employee_id'     => ['type' => 'INT'],
            'judul'           => ['type' => 'VARCHAR', 'constraint' => 191],
            'deskripsi'       => ['type' => 'TEXT', 'null' => true],
            'kategori'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'submitted', 'approved', 'implemented', 'rejected'], 'default' => 'draft'],
            'submission_month' => ['type' => 'TINYINT', 'null' => true],
            'submission_year' => ['type' => 'SMALLINT', 'null' => true],
            'approved_at'     => ['type' => 'DATETIME', 'null' => true],
            'implemented_at'  => ['type' => 'DATETIME', 'null' => true],
            'evidence'        => ['type' => 'TEXT', 'null' => true],
            'evaluated_by'    => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey(['submission_year', 'submission_month']);
        $this->forge->addForeignKey('employee_id', 'akun', 'ID_AKUN', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('evaluated_by', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('improvements', true);

        // ── Add campaign_id → contents ──
        $this->forge->addColumn('contents', [
            'campaign_id' => ['type' => 'INT', 'null' => true, 'after' => 'jenis_konten'],
        ]);
        $this->forge->addForeignKey('campaign_id', 'content_campaigns', 'id', 'SET NULL', 'SET NULL', 'fk_contents_campaign');

        // ── Add sesuai_brief → content_qc (verdict kesesuaian brief) ──
        $this->forge->addColumn('content_qc', [
            'sesuai_brief' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true, 'default' => null, 'after' => 'status'],
        ]);

        // ── Komponen KPI baru ──
        $codeToId = [];
        foreach (self::NEW_CODES as $code) {
            $existing = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
            if ($existing) {
                $codeToId[$code] = (int)$existing->id;
                continue;
            }
            $this->db->table('kpi_components')->insert([
                'code'                => $code,
                'name'                => self::NEW_NAMES[$code],
                'type'                => 'automatic',
                'calculation_strategy' => '',
                'is_active'           => 1,
            ]);
            $codeToId[$code] = (int)$this->db->insertID();
        }

        // ── Lepas bobot lama posisi 44 (HANYA posisi 44) ──
        $oldIds = $this->componentIds(array_keys(self::OLD_WEIGHTS_44));
        $this->db->table('kpi_weights')
            ->where('position_id', 44)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', $oldIds)
            ->delete();

        // ── Pasang bobot baru posisi 44 ──
        $this->insertWeights(44, self::NEW_WEIGHTS_44, $codeToId);
    }

    public function down()
    {
        $newIds = $this->componentIds(self::NEW_CODES);

        // Hapus bobot baru posisi 44.
        $this->db->table('kpi_weights')
            ->where('position_id', 44)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', $newIds)
            ->delete();

        // Pulihkan bobot lama posisi 44.
        $oldIds = $this->componentIds(array_keys(self::OLD_WEIGHTS_44));
        $codeToId = [];
        $codes = array_keys(self::OLD_WEIGHTS_44);
        foreach ($oldIds as $i => $id) {
            $codeToId[$codes[$i]] = $id;
        }
        $this->insertWeights(44, self::OLD_WEIGHTS_44, $codeToId);

        // Drop kolom & tabel.
        $this->forge->dropForeignKey('contents', 'fk_contents_campaign');
        $this->forge->dropColumn('contents', 'campaign_id');
        $this->forge->dropColumn('content_qc', 'sesuai_brief');
        $this->forge->dropTable('content_briefs', true);
        $this->forge->dropTable('content_campaigns', true);
        $this->forge->dropTable('improvements', true);

        // Komponen baru tidak dipakai posisi lain → hapus.
        foreach ($newIds as $id) {
            $this->db->table('kpi_components')->where('id', $id)->delete();
        }
    }

    private function componentIds(array $codes): array
    {
        $rows = $this->db->table('kpi_components')->whereIn('code', $codes)->get()->getResult();
        return array_map(static fn($r) => (int)$r->id, $rows);
    }

    private function insertWeights(int $positionId, array $codeWeights, array $codeToId): void
    {
        $rows = [];
        foreach ($codeWeights as $code => $weight) {
            $rows[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => $positionId,
                'weight'           => (float)$weight,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        if (!empty($rows)) {
            $this->db->table('kpi_weights')->insertBatch($rows);
        }
    }
}