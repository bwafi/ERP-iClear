<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan GAJI_POKOK untuk jabatan Finance (0) dan Manager (34).
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * Kedua jabatan itu punya salary_structures, tapi hanya berisi
 * TUNJANGAN_KINERJA. GAJI_POKOK-nya tidak pernah diisi, sehingga
 * PayrollGenerator menghitung total Rp 0 untuk setiap karyawan dengan
 * jabatan tersebut: tidak ada komponen tetap, dan tunjangan kinerja pun
 * bernilai 0 karena skor KPI-nya 0.
 *
 * KBEPUTUSAN BISNIS (sudah disetujui user)
 * --------------------------------------
 * Gaji pokok Finance dan Manager disamakan dengan jabatan lain, yaitu
 * Rp 1.500.000. Batas tunjangan kinerja yang sudah ada di master tidak
 * diubah dan sudah sesuai: Manager Rp 2.250.000 (baris id 38) dan
 * Finance Rp 1.250.000 (baris id 37).
 *
 * effective_from memakai 2024-01-01, sama seperti GAJI_POKOK jabatan lain,
 * supaya gaji pokok juga berlaku untuk payroll bulan-bulan sebelumnya,
 * bukan hanya mulai bulan migration ini dijalankan.
 *
 * Yang SENGAJA tidak dilakukan:
 *   - tidak mengubah base_value TUNJANGAN_KINERJA yang sudah ada
 *   - tidak menambah TUNJANGAN_ABSEN untuk kedua jabatan ini
 *   - tidak menyentuh payroll yang sudah pernah dibuat
 */
class AddGajiPokokFinanceManager extends Migration
{
    /** Component code untuk gaji pokok. */
    private const KOMPONEN_GAJI_POKOK = 'GAJI_POKOK';

    /** Gaji pokok, sama dengan jabatan lain. */
    private const GAJI_POKOK = 1500000.00;

    /** Mulai berlaku, mengikuti GAJI_POKOK jabatan lain. */
    private const MULAI = '2024-01-01';

    /**
     * Jabatan yang perlu GAJI_POKOK.
     *
     * Kunci = ID jabatan, nilai = nama jabatan untuk pesan log.
     */
    private const JABATAN = [
        0  => 'Finance',
        34 => 'Manager',
    ];

    public function up()
    {
        $componentId = $this->componentGajiPokok();

        if ($componentId === null) {
            log_message(
                'error',
                'AddGajiPokokFinanceManager: komponen GAJI_POKOK tidak ada, '
                . 'gaji pokok Finance/Manager tidak ditambahkan.'
            );

            return;
        }

        foreach (self::JABATAN as $positionId => $namaJabatan) {
            if ($this->sudahPunyaGajiPokok($positionId, $componentId)) {
                continue;
            }

            $this->db->table('salary_structures')->insert([
                'position_id'       => $positionId,
                'salary_component_id' => $componentId,
                'unit_id'           => null,
                'context'           => 'default',
                'base_value'        => self::GAJI_POKOK,
                'calculation_type'  => 'fixed',
                'effective_from'    => self::MULAI,
                'effective_to'      => null,
                'created_by'        => null,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            log_message(
                'info',
                'AddGajiPokokFinanceManager: gaji pokok ' . $namaJabatan
                . ' ditambahkan Rp ' . number_format(self::GAJI_POKOK, 0, ',', '.')
                . ' mulai ' . self::MULAI
            );
        }
    }

    public function down()
    {
        $componentId = $this->componentGajiPokok();

        if ($componentId === null) {
            return;
        }

        // Hanya baris yang persis sama dengan yang dibuat up(): dicocokkan
        // juga pada base_value, unit_id, context, dan effective_from. Kalau
        // ada yang diubah Finance lewat master salary setelah migration ini
        // dijalankan, barisnya tidak ikut terhapus.
        $this->db->table('salary_structures')
            ->whereIn('position_id', array_keys(self::JABATAN))
            ->where('salary_component_id', $componentId)
            ->where('base_value', self::GAJI_POKOK)
            ->where('unit_id', null)
            ->where('context', 'default')
            ->where('effective_from', self::MULAI)
            ->delete();
    }

    /** ID komponen GAJI_POKOK, atau null kalau master-nya belum lengkap. */
    private function componentGajiPokok(): ?int
    {
        if (! $this->db->fieldExists('code', 'salary_components')) {
            return null;
        }

        $row = $this->db->table('salary_components')
            ->select('id')
            ->where('code', self::KOMPONEN_GAJI_POKOK)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** Sudah ada gaji pokok yang masih berlaku untuk jabatan ini? */
    private function sudahPunyaGajiPokok(int $positionId, int $componentId): bool
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