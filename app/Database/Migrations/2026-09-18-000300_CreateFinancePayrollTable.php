<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel KPI Payroll Finance — register pembayaran gaji per unit.
 *
 * Desain Fase 4 (menggantikan GAP kas_keluar kategori 10 yang tidak punya
 * jadwal vs tanggal bayar):
 *  - Satu baris = satu pembayaran gaji (boleh beberapa dalam sebulan,
 *    misal gaji pokok/tunjangan/lembur dibayar terpisah).
 *  - due_date  : jatuh tempo pembayaran (jadwal gajian).
 *  - paid_date : tanggal aktual dibayar (NULL = belum bayar).
 *  - status    : 'rencana' (belum ada tanggal bayar) / 'dibayar'.
 *
 * KPI dievaluasi dari baris yang due_date-nya jatuh dalam bulan dievaluasi
 * (menyamakan pola Hutang: jatuh_tempo). Tidak ada UNIQUE(unit, bulan) agar
 * hasil KPI berupa persentase yang informatif, bukan biner 0/100.
 */
class CreateFinancePayrollTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INTEGER',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'unit_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => false,
            ],
            'due_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'paid_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'rencana',
            ],
            'total' => [
                'type'       => 'BIGINT',
                'null'       => false,
                'default'    => 0,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['unit_id', 'due_date'], false, true, 'idx_payroll_unit_due');
        $this->forge->createTable('finance_payroll', true);
    }

    public function down()
    {
        $this->forge->dropTable('finance_payroll', true);
    }
}