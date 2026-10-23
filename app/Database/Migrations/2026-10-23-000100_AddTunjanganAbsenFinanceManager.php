<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan TUNJANGAN_ABSEN untuk jabatan Finance (0) dan Manager (34).
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * Kedua jabatan sudah punya skor absen (skor_total2 dari
 * AttendanceAggregationService), tetapi salary_structures-nya tidak punya
 * baris TUNJANGAN_ABSEN. Akibatnya SalaryCalculationService tidak pernah
 * membuat komponen itu dan tunjangan absen tampil Rp 0.
 *
 * Nilai dan rumusnya sama dengan jabatan lain: percent_of_kpi dengan
 * base_value Rp 250.000, jadi tunjangan = skor_total2 / 100 x 250.000.
 *
 * effective_from memakai 2024-01-01 seperti jabatan lain, supaya payroll
 * bulan-bulan sebelumnya juga ikut bila dihitung ulang.
 *
 * Yang SENGAJA tidak dilakukan:
 *   - tidak menambah kpi_weights grup 'absen' — perhitungan absen memakai
 *     bobot 40/20/20/20 di AttendanceAggregationService
 *   - tidak menyentuh payroll yang sudah pernah dibuat
 */
class AddTunjanganAbsenFinanceManager extends Migration
{
    /** Component code untuk tunjangan absen. */
    private const KOMPONEN_ABSEN = 'TUNJANGAN_ABSEN';

    /** Batas tunjangan absen, sama dengan jabatan lain. */
    private const TUNJANGAN_ABSEN = 250000.00;

    /** Mulai berlaku, mengikuti TUNJANGAN_ABSEN jabatan lain. */
    private const MULAI = '2024-01-01';

    /** Jabatan yang perlu TUNJANGAN_ABSEN. */
    private const JABATAN = [
        0  => 'Finance',
        34 => 'Manager',
    ];

    public function up()
    {
        $componentId = $this->componentAbsen();

        if ($componentId === null) {
            log_message(
                'error',
                'AddTunjanganAbsenFinanceManager: komponen TUNJANGAN_ABSEN '
                . 'tidak ada, tunjangan absen Finance/Manager tidak ditambahkan.'
            );

            return;
        }

        foreach (self::JABATAN as $positionId => $namaJabatan) {
            if ($this->sudahPunyaTunjanganAbsen($positionId, $componentId)) {
                continue;
            }

            $this->db->table('salary_structures')->insert([
                'position_id'         => $positionId,
                'salary_component_id' => $componentId,
                'unit_id'             => null,
                'context'             => 'default',
                'base_value'          => self::TUNJANGAN_ABSEN,
                'calculation_type'    => 'percent_of_kpi',
                'multiplier'          => 1.00,
                'effective_from'      => self::MULAI,
                'effective_to'        => null,
                'created_by'          => null,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            log_message(
                'info',
                'AddTunjanganAbsenFinanceManager: tunjangan absen '
                . $namaJabatan . ' ditambahkan Rp '
                . number_format(self::TUNJANGAN_ABSEN, 0, ',', '.')
                . ' mulai ' . self::MULAI
            );
        }
    }

    public function down()
    {
        $componentId = $this->componentAbsen();

        if ($componentId === null) {
            return;
        }

        // Hanya hapus baris yang persis dibuat up(): kalau Finance mengubah
        // nominal/effective_from lewat master salary, barisnya tidak ikut
        // terhapus.
        $this->db->table('salary_structures')
            ->whereIn('position_id', array_keys(self::JABATAN))
            ->where('salary_component_id', $componentId)
            ->where('base_value', self::TUNJANGAN_ABSEN)
            ->where('unit_id', null)
            ->where('context', 'default')
            ->where('effective_from', self::MULAI)
            ->delete();
    }

    /** ID komponen TUNJANGAN_ABSEN, atau null kalau master-nya belum lengkap. */
    private function componentAbsen(): ?int
    {
        if (! $this->db->fieldExists('code', 'salary_components')) {
            return null;
        }

        $row = $this->db->table('salary_components')
            ->select('id')
            ->where('code', self::KOMPONEN_ABSEN)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** Sudah ada tunjangan absen yang masih berlaku untuk jabatan ini? */
    private function sudahPunyaTunjanganAbsen(int $positionId, int $componentId): bool
    {
        return $this->db->table('salary_structures')
            ->where('position_id', $positionId)
            ->where('salary_component_id', $componentId)
            ->groupStart()
                ->where('effective_to IS NULL')
                ->orWhere('effective_to >=', date('Y-m-d'))
            ->groupEnd()
            ->countAllResults() > 0;
    }
}
