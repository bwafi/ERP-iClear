<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * IncentiveGroupSeeder
 *
 * Men-Seed BUSINESS RULE INCEntif yang sudah dikonfirmasi:
 *   KEPALA_TOKO        : rate 3% omset toko, dibagi jumlah anggota aktif group per unit
 *   DIGITAL_DIVISION   : rate 1% omset toko, dibagi jumlah anggota aktif group per unit
 *
 * Membership diambil dari struktur organisasi SAAT INI (baseline existing):
 *   KEPALA_TOKO   : Kepala Toko (41) + Teknisi (36) + Admin (35)
 *   DIGITAL_DIVISION : Pengiklan (43) + IT (45) + Multimedia (44) + CS (42)
 *
 * Divisor TIDAK di-hardcode — dihitung dari baris aktif incentive_members per unit & periode.
 */
class IncentiveGroupSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        // ===== GROUPS =====
        $db->table('incentive_groups')->where('code', 'KEPALA_TOKO')->delete();
        $db->table('incentive_groups')->where('code', 'DIGITAL_DIVISION')->delete();
        $db->table('incentive_members')->truncate();

        $db->table('incentive_groups')->insertBatch([
            [
                'code'           => 'KEPALA_TOKO',
                'name'           => 'Kelompok Kepala Toko (Store Team)',
                'description'    => '3% omset toko dibagi rata ke anggota toko (Kepala Toko, Teknisi, Admin)',
                'is_active'      => 1,
                'effective_from' => '2024-01-01',
                'effective_to'   => null,
            ],
            [
                'code'           => 'DIGITAL_DIVISION',
                'name'           => 'Kelompok Digital Division',
                'description'    => '1% omset toko dibagi rata (Pengiklan, IT, Multimedia, CS)',
                'is_active'      => 1,
                'effective_from' => '2024-01-01',
                'effective_to'   => null,
            ],
        ]);

        $groups = $db->table('incentive_groups')->select('id, code')->get()->getResultArray();
        $groupMap = array_column($groups, 'id', 'code');

        // ===== RULES (rate) — TIDAK duplikasi: rate hanya di incentive_rules =====
        $db->table('incentive_rules')->whereIn('incentive_group_id', array_values($groupMap))->delete();

        $db->table('incentive_rules')->insertBatch([
            [
                'incentive_group_id'  => $groupMap['KEPALA_TOKO'],
                'kpi_component_id'    => 1, // OMSET_TOKO (asal)
                'incentive_name'      => 'Kepala Toko 3% Pool',
                'calculation_type'    => 'percentage',
                'base_value'          => 3.00,
                'minimum_achievement' => 100.00,
                'division_method'     => 'member_count',
                'effective_from'      => '2024-01-01',
                'effective_to'        => null,
            ],
            [
                'incentive_group_id'  => $groupMap['DIGITAL_DIVISION'],
                'kpi_component_id'    => 1, // OMSET_TOKO (asal)
                'incentive_name'      => 'Digital Division 1% Pool',
                'calculation_type'    => 'percentage',
                'base_value'          => 1.00,
                'minimum_achievement' => 100.00,
                'division_method'     => 'member_count',
                'effective_from'      => '2024-01-01',
                'effective_to'        => null,
            ],
        ]);

        // ===== MEMBERS (per unit, dari struktur organisasi existing) =====
        // NOTE: membership 100% dari tabel `akun` (employee nyata) — TIDAK ada data fiktif.
        // Mapping jabatan-id → group adalah SEED-TIME CONFIGURATION:
        // jalankan seeder sekali, setelah itu membership dikelola via tabel `incentive_members`.
        // Penting: jika struktur organisasi berubah, UPDATE incentive_members,
        // bukan menambah hardcode baru di seeder.
        $employees = $db->table('akun')
            ->select('ID_AKUN, ID_JABATAN, ID_UNIT')
            ->where('STATUS_PEGAWAI', 1)
            ->get()
            ->getResultArray();

        $members = [];
        foreach ($employees as $emp) {
            $groupCode = null;

            switch ((int) $emp['ID_JABATAN']) {
                case 36: // Teknisi
                case 41: // Kepala Toko
                case 35: // Admin
                    $groupCode = 'KEPALA_TOKO';
                    break;
                case 42: // Customer Service
                case 43: // Pengiklan
                case 44: // Multimedia
                case 45: // IT
                    $groupCode = 'DIGITAL_DIVISION';
                    break;
            }

            if ($groupCode === null) {
                continue;
            }

            $members[] = [
                'incentive_group_id' => $groupMap[$groupCode],
                'employee_id'        => (int) $emp['ID_AKUN'],
                'unit_id'            => (int) $emp['ID_UNIT'],
                'effective_from'     => '2024-01-01',
                'effective_to'       => null,
                'is_active'          => 1,
            ];
        }

        if (!empty($members)) {
            $db->table('incentive_members')->insertBatch($members);
        }

        // ===== VALIDASI: hitung member per group per unit =====
        $summary = $db->query("
            SELECT g.code AS group_code, m.unit_id, COUNT(*) AS member_count
            FROM incentive_members m
            JOIN incentive_groups g ON g.id = m.incentive_group_id
            WHERE m.is_active = 1
            GROUP BY g.code, m.unit_id
            ORDER BY g.code, m.unit_id
        ")->getResultArray();

        echo "Incentive groups seeded:\n";
        foreach ($summary as $row) {
            echo "  {$row['group_code']} | unit {$row['unit_id']} | {$row['member_count']} member(s)\n";
        }
    }
}