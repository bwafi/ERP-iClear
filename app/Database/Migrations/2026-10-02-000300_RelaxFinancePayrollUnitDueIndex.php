<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Longgarkan UNIQUE (unit_id, due_date) pada finance_payroll jadi index biasa.
 *
 * BUG LAMA YANG DIPERBAIKI
 * ------------------------
 * Migration 2026-09-18-000300 membuat index ini dengan
 *
 *     $this->forge->addKey(['unit_id', 'due_date'], false, true, 'idx_payroll_unit_due');
 *
 * Argumen ketiga `true` berarti UNIQUE, padahal docblock migration yang sama
 * justru menulis "Tidak ada UNIQUE(unit, bulan) agar hasil KPI berupa
 * persentase yang informatif, bukan biner 0/100". Indexnya bertentangan
 * dengan niat yang ditulisnya sendiri.
 *
 * Akibatnya register gaji hanya bisa menampung SATU baris per unit per
 * tanggal. Begitu kolom `pegawai_id` ditambahkan (migration 000400) dan
 * register dipakai per karyawan, fitur itu tidak bisa jalan: inserting
 * karyawan kedua di unit yang sama pada tanggal yang sama gagal dengan
 * "Duplicate entry '1-2026-09-30' for key 'idx_payroll_unit_due'", dan
 * karena CI Model::insert() mengembalikan false tanpa melempar error,
 * kegagalannya nyaris tak terlihat.
 *
 * Efeknya ke KPI justru membaik: PayrollTimelinessCalculator menghitung
 * tepat/dinilai, jadi dengan satu baris per karyawan skornya meaningful
 * (mis. 25 dari 27 karyawan tepat waktu = 92,59%), bukan selalu 0 atau 100.
 *
 * CATATAN DOWN
 * ------------
 * Memulihkan UNIQUE akan gagal kalau sudah ada lebih dari satu baris untuk
 * unit + tanggal yang sama. Itu memang kondisi yang wajar setelah fitur ini
 * dipakai, jadi down() memeriksa dulu dan menolak dengan pesan jelas
 * daripada meninggalkan index dalam keadaan setengah jadi.
 */
class RelaxFinancePayrollUnitDueIndex extends Migration
{
    private const INDEX = 'idx_payroll_unit_due';

    public function up()
    {
        if (!$this->adaIndex()) {
            return;
        }

        if (!$this->indexUnik()) {
            return;
        }

        $this->db->query('ALTER TABLE finance_payroll DROP INDEX ' . self::INDEX);
        $this->db->query('ALTER TABLE finance_payroll ADD INDEX ' . self::INDEX . ' (unit_id, due_date)');
    }

    public function down()
    {
        if (!$this->adaIndex() || $this->indexUnik()) {
            return;
        }

        $duplikat = $this->db->query(
            'SELECT unit_id, due_date, COUNT(*) AS n
               FROM finance_payroll
              GROUP BY unit_id, due_date
             HAVING n > 1
              LIMIT 1'
        )->getRow();

        if ($duplikat !== null) {
            throw new \RuntimeException(
                'Tidak bisa memulihkan UNIQUE idx_payroll_unit_due: sudah ada '
                . $duplikat->n . ' baris untuk unit ' . $duplikat->unit_id
                . ' tanggal ' . $duplikat->due_date . '. Index dibiarkan longgar.'
            );
        }

        $this->db->query('ALTER TABLE finance_payroll DROP INDEX ' . self::INDEX);
        $this->db->query('ALTER TABLE finance_payroll ADD UNIQUE INDEX ' . self::INDEX . ' (unit_id, due_date)');
    }

    private function adaIndex(): bool
    {
        $found = $this->db->query(
            "SELECT COUNT(*) AS n FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'finance_payroll'
                AND index_name = '" . self::INDEX . "'"
        )->getRow();

        return $found !== null && (int) $found->n > 0;
    }

    private function indexUnik(): bool
    {
        $row = $this->db->query(
            "SELECT non_unique FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'finance_payroll'
                AND index_name = '" . self::INDEX . "'"
        )->getRow();

        return $row !== null && (int) $row->non_unique === 0;
    }
}