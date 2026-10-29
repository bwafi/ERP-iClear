<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penyesuaian gaji manual per karyawan per bulan: potongan (mis. SP) dan
 * bonus (mis. bonus kinerja).
 *
 * Nominal bebas, ditentukan manager/owner, dan langsung berlaku pada gaji
 * bulan itu. Satu baris per karyawan per periode per jenis, dengan
 * `keterangan` sebagai alasan. Baris bulan lain tidak terpengaruh.
 *
 * `finance_payroll.potongan_lain` dan `bonus_lain` menyimpan nominal yang
 * benar-benar dipakai saat payroll ditandai dibayar, untuk jejak audit.
 */
class CreatePayrollAdjustment extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'employee_id' => ['type' => 'INT'],
            'unit_id' => ['type' => 'INT'],
            'period' => ['type' => 'CHAR', 'constraint' => 7],
            'jenis' => ['type' => 'VARCHAR', 'constraint' => 20],
            'jumlah' => ['type' => 'DECIMAL', 'constraint' => [15, 2], 'default' => 0],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'period', 'jenis'], false, true);
        $this->forge->addForeignKey('employee_id', 'akun', 'ID_AKUN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->createTable('payroll_adjustment');

        if (!$this->db->fieldExists('potongan_lain', 'finance_payroll')) {
            $this->forge->addColumn('finance_payroll', [
                'potongan_lain' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'null' => true, 'default' => 0,
                ],
            ]);
        }

        if (!$this->db->fieldExists('bonus_lain', 'finance_payroll')) {
            $this->forge->addColumn('finance_payroll', [
                'bonus_lain' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'null' => true, 'default' => 0,
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (['potongan_lain', 'bonus_lain'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'finance_payroll')) {
                $this->forge->dropColumn('finance_payroll', $kolom);
            }
        }

        $this->forge->dropTable('payroll_adjustment');
    }
}
