<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengecualian rate insentif per karyawan per bulan.
 *
 * Dipakai untuk kasus satu periode seperti masa training: karyawan yang
 * masih training tidak mendapat insentif, sementara pendampingnya mendapat
 * rate lebih tinggi. Baris di sini hanya berlaku pada `period` (YYYY-MM)
 * yang dicatat, sehingga bulan berikutnya otomatis kembali ke rate normal
 * tanpa perlu edit manual.
 *
 * `rate` adalah persen dari omset (1.50 = 1,5%); `rate` 0 berarti tidak
 * mendapat insentif bulan itu. Syarat minimum_achievement tetap berlaku.
 *
 * Migrasi ini juga mengisi pengecualian Oktober 2026 untuk unit 5:
 * Dimas 67 = 1,5% dan Nanda (Danis) 71 = 0%. IDEMPOTEN.
 */
class CreateIncentiveOverrides extends Migration
{
    /** Unit 5 = ICLEAR Genteng. */
    private const UNIT_ID = 5;

    /** Periode payroll yang sedang berjalan saat migrasi ini dibuat. */
    private const PERIOD = '2026-10';

    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'employee_id' => ['type' => 'INT'],
            'unit_id' => ['type' => 'INT'],
            'period' => ['type' => 'CHAR', 'constraint' => 7],
            'rate' => ['type' => 'DECIMAL', 'constraint' => [5, 2]],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'unit_id', 'period'], false, true);
        $this->forge->addForeignKey('employee_id', 'akun', 'ID_AKUN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->createTable('incentive_overrides');

        $now = date('Y-m-d H:i:s');

        $rows = [
            ['employee_id' => 67, 'rate' => 1.50, 'reason' => 'Oktober 2026: pendamping teknisi training'],
            ['employee_id' => 71, 'rate' => 0.00, 'reason' => 'Oktober 2026: masih masa training'],
        ];

        foreach ($rows as $row) {
            $pegawai = $this->db->table('akun')
                ->where('ID_AKUN', $row['employee_id'])
                ->where('ID_UNIT', self::UNIT_ID)
                ->countAllResults();

            if ($pegawai === 0) {
                continue;
            }

            $sudahAda = $this->db->table('incentive_overrides')
                ->where('employee_id', $row['employee_id'])
                ->where('unit_id', self::UNIT_ID)
                ->where('period', self::PERIOD)
                ->countAllResults();

            if ($sudahAda > 0) {
                continue;
            }

            $this->db->table('incentive_overrides')->insert([
                'employee_id' => $row['employee_id'],
                'unit_id' => self::UNIT_ID,
                'period' => self::PERIOD,
                'rate' => $row['rate'],
                'reason' => $row['reason'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('incentive_overrides');
    }
}
