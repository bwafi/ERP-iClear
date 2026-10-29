<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Isi membership insentif KEPALA_TOKO untuk unit 5 (ICLEAR Genteng).
 *
 * Unit 5 dibuat setelah `IncentiveGroupSeeder` dijalankan, jadi tidak punya
 * baris `incentive_members` sama sekali; akibatnya computeIncentive() selalu
 * mengembalikan 0 walau rate 1% sudah benar. Baris lama pegawai yang pindah
 * unit (mis. Dimas 67 masih menempel di unit 3) juga ikut diselaraskan.
 *
 * Hanya pegawai aktif yang TERIMA_GAJI = 1 yang menjadi anggota, karena
 * insentif dibayar lewat gaji. IDEMPOTEN: aman dijalankan berulang.
 */
class BackfillGentengIncentiveMembers extends Migration
{
    /** Unit 5 = ICLEAR Genteng. */
    private const UNIT_ID = 5;

    /** Awal periode payroll yang masih berjalan saat migrasi ini dibuat. */
    private const EFFECTIVE_FROM = '2026-10-01';

    /** Kepala Toko (41), Teknisi (36), Admin (35). */
    private const JABATAN_STORE = [35, 36, 41];

    public function up()
    {
        $group = $this->db->table('incentive_groups')
            ->where('code', 'KEPALA_TOKO')
            ->get()
            ->getRow();

        if (!$group) {
            return;
        }

        $staff = $this->db->table('akun')
            ->select('ID_AKUN, TERIMA_GAJI')
            ->where('ID_UNIT', self::UNIT_ID)
            ->whereIn('ID_JABATAN', self::JABATAN_STORE)
            ->where('STATUS_PEGAWAI', 1)
            ->where('deleted', null)
            ->get()
            ->getResultArray();

        $now = date('Y-m-d H:i:s');

        foreach ($staff as $pegawai) {
            $penerimaGaji = (int) $pegawai['TERIMA_GAJI'] === 1;

            $member = $this->db->table('incentive_members')
                ->where('employee_id', $pegawai['ID_AKUN'])
                ->where('incentive_group_id', $group->id)
                ->get()
                ->getRow();

            if ($member) {
                $this->db->table('incentive_members')
                    ->where('id', $member->id)
                    ->update([
                        'unit_id'    => self::UNIT_ID,
                        'is_active'  => $penerimaGaji ? 1 : 0,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            if (!$penerimaGaji) {
                continue;
            }

            $this->db->table('incentive_members')->insert([
                'incentive_group_id' => $group->id,
                'employee_id'        => $pegawai['ID_AKUN'],
                'unit_id'            => self::UNIT_ID,
                'effective_from'     => self::EFFECTIVE_FROM,
                'effective_to'       => null,
                'is_active'          => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

    /**
     * Tidak di-rollback: menghapus membership Genteng akan mengembalikan
     * insentifnya ke 0, dan baris lama yang dipindah tidak bisa dipulihkan
     * dengan aman.
     */
    public function down()
    {
    }
}
