<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Masukkan Unit 5 (ICLEAR Genteng) ke area SPV Mario Reza Pratama.
 *
 * KEADAAN SEBELUM
 * ---------------
 * spv_units hanya memetakan dua area:
 *   49 (Mario R) -> [2 Jember, 3 Banyuwangi]
 *   56 (Bima)   -> [1 Probolinggo, 4 Pandaan]
 * Unit 5 ICLEAR Genteng (unit cabang, terdaftar 2026-09-16) belum punya SPV,
 * sehingga eksplisit TIDAK masuk area siapa pun: KPI SPV Mario/Bima hanya
 * encompass unit 2-4, dan modul lain (Finance, Konten, Customer Satisfaction,
 * SupervisorKpiService) semuanya membaca tabel yang sama ini.
 *
 * PERUBAHAN
 * ---------
 * Menambah satu baris (49, 5). Area Bima tidak berubah.
 *
 * DAMBAK YANG DISENGJAJA
 * ---------------------
 * Unit 5 belum punya target di kpi_targets (belum ada OMSET_CABANG /
 * TARGET_CABANG unit 5), jadi:
 *   - SupervisorKpiService::targetCabang() melewati cabang tanpa target
 *     ("cabang tanpa target tidak ikut hitungan") sehingga rasio cabang
 *     tercapai Mario tetap dihitung atas unit 2 & 3, tidak turun karena ada
 *     cabang baru;
 *   - SupervisorKpiService::omzetWilayah() menambah omset riil unit 5 ke
 *     pembilang actual tanpa menambah target. Ini sesuai definisi komponen:
 *     yang diukur adalah omset area, dan achievement tetap di-cap 100.
 * Bila nanti unit 5 punya target sendiri, angka-angka ini otomatis memakai
 * target itu tanpa perubahan kode.
 *
 * IDEMPOTENT: spv_units punya UNIQUE (spv_id, unit_id), jadi dijalankan
 * berulang hanya menghasilkan no-op. Migration ini juga batal bila unit 5
 * atau akun SPV Mario tidak ada di master, supaya tidak menyisakan baris yatim.
 */
class MasukkanUnitGentengKeAreaSpvMario extends Migration
{
    private const SPV_MARIO = 49;
    private const UNIT_GENTENG = 5;

    public function up()
    {
        foreach (['spv_units', 'unit', 'akun'] as $table) {
            if (! $this->db->tableExists($table)) {
                return;
            }
        }

        $spv = $this->db->table('akun')
            ->select('ID_AKUN')
            ->where('ID_AKUN', self::SPV_MARIO)
            ->where('ID_JABATAN', 40)
            ->where('STATUS_PEGAWAI', 1)
            ->get()
            ->getRow();

        if (! $spv) {
            log_message('warning', sprintf(
                '[Migration MasukkanUnitGentengKeAreaSpvMario] Akun SPV %d tidak ada/aktif Jabatan 40 — area SPV tidak diubah.',
                self::SPV_MARIO
            ));
            return;
        }

        $unit = $this->db->table('unit')
            ->select('idunit')
            ->where('idunit', self::UNIT_GENTENG)
            ->get()
            ->getRow();

        if (! $unit) {
            log_message('warning', sprintf(
                '[Migration MasukkanUnitGentengKeAreaSpvMario] Unit %d belum ada di master unit — area SPV tidak diubah.',
                self::UNIT_GENTENG
            ));
            return;
        }

        $sudahAda = $this->db->table('spv_units')
            ->where('spv_id', self::SPV_MARIO)
            ->where('unit_id', self::UNIT_GENTENG)
            ->countAllResults() > 0;

        if ($sudahAda) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('spv_units')->insert([
            'spv_id'     => self::SPV_MARIO,
            'unit_id'    => self::UNIT_GENTENG,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        log_message('info', sprintf(
            '[Migration MasukkanUnitGentengKeAreaSpvMario] Unit %d (ICLEAR Genteng) ditambahkan ke area SPV %d.',
            self::UNIT_GENTENG,
            self::SPV_MARIO
        ));
    }

    /**
     * Hanya mencabut baris yang ditambahkan migration ini; area SPV Mario
     * lain (unit 2 & 3) tidak disentuh.
     */
    public function down()
    {
        if (! $this->db->tableExists('spv_units')) {
            return;
        }

        $this->db->table('spv_units')
            ->where('spv_id', self::SPV_MARIO)
            ->where('unit_id', self::UNIT_GENTENG)
            ->delete();
    }
}