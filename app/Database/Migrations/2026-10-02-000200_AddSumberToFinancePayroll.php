<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tandai asal baris payroll gaji: 'manual' atau 'auto'.
 *
 * KEPUTUSAN BISNIS (sudah disetujui user)
 * --------------------------------------
 * Sejak payroll bisa disusun otomatis dari salary_structures, tabel
 * finance_payroll jadi punya dua jenis baris:
 *
 *   - manual -> diketik sendiri lewat form "Input Payroll Gaji" seperti
 *               sebelumnya (Finance mengisi nominal sendiri)
 *   - auto   -> dibuat oleh PayrollGenerator dari master salary_structures,
 *               lengkap dengan rincian komponennya di `notes`
 *
 * Tanpa penanda ini Finance tidak bisa tahu angka mana yang sudah dihitung
 * sistem dan mana yang ia ubah sendiri, sehingga tidak aman untuk diisi ulang.
 *
 * Kolom ini sengaja dibuat nullable-default 'manual' supaya baris lama
 * (termasuk yang sudah pernah ada) otomatis dianggap manual tanpa perlu
 * backfill terpisah.
 */
class AddSumberToFinancePayroll extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('sumber', 'finance_payroll')) {
            return;
        }

        $this->forge->addColumn('finance_payroll', [
            'sumber' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => 'manual',
                'after'      => 'status',
            ],
        ]);

        $this->db->query('ALTER TABLE finance_payroll ADD INDEX idx_payroll_pegawai_due (pegawai_id, due_date)');
    }

    public function down()
    {
        if (!$this->db->fieldExists('sumber', 'finance_payroll')) {
            return;
        }

        $this->forge->dropKey('finance_payroll', 'idx_payroll_pegawai_due');
        $this->forge->dropColumn('finance_payroll', 'sumber');
    }
}