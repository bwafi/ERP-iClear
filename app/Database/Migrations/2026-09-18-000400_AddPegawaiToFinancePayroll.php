<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kolom pegawai pada register payroll Finance.
 *
 * Karyawan direferensikan ke tabel `akun` (ID_AKUN), konsisten dengan modul
 * payroll yang sudah ada (payroll_pegawai / kas_keluar.penerima).
 */
class AddPegawaiToFinancePayroll extends Migration
{
    public function up()
    {
        $this->forge->addColumn('finance_payroll', [
            'pegawai_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
        ]);

        $this->db->query('ALTER TABLE finance_payroll ADD INDEX idx_payroll_pegawai (pegawai_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE finance_payroll DROP INDEX idx_payroll_pegawai');

        $this->forge->dropColumn('finance_payroll', 'pegawai_id');
    }
}
