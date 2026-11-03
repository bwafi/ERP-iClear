<?php

namespace App\Commands;

use App\Services\Kpi\PresensiAttendanceSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backfill / perbaikan sinkronisasi absen mobile ke KPI KEHADIRAN.
 *
 * Contoh:
 *   php spark presensi:sync-kpi --bulan=2026-11
 *   php spark presensi:sync-kpi --bulan=2026-11 --pegawai=10
 */
class PresensiSyncKpi extends BaseCommand
{
    protected $group       = 'presensi';
    protected $name        = 'presensi:sync-kpi';
    protected $description = 'Sinkronkan punch absen mobile ke nilai KEHADIRAN KPI.';

    public function run(array $params): int
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('presensi_roster')) {
            CLI::error('Tabel presensi_roster belum ada. Jalankan migrasi dulu.');

            return 1;
        }

        $bulan = (string) (CLI::getOption('bulan') ?: date('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            CLI::error('Format --bulan harus YYYY-MM.');

            return 1;
        }

        $start = $bulan . '-01';
        $end   = date('Y-m-t', strtotime($start));

        $pegawai = (int) CLI::getOption('pegawai');
        if ($pegawai > 0) {
            $ids = [$pegawai];
        } else {
            $rows = $db->query(
                'SELECT akun_idakun AS id FROM presensi_roster WHERE tanggal >= ? AND tanggal <= ?
                 UNION
                 SELECT DISTINCT akun_idakun AS id FROM presensi WHERE waktu_masuk >= ? AND waktu_masuk <= ?',
                [$start, $end, $start . ' 00:00:00', $end . ' 23:59:59']
            )->getResultArray();
            $ids = array_map(static fn ($row) => (int) $row['id'], $rows);
        }

        CLI::write('Sinkronisasi KPI absen periode ' . $bulan . ' (' . count($ids) . ' pegawai)', 'yellow');

        $svc   = new PresensiAttendanceSyncService();
        $total = ['synced' => 0, 'off' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($ids as $id) {
            $hasil = $svc->syncMonth($id, $bulan);
            foreach ($hasil as $key => $value) {
                $total[$key] += $value;
            }

            if ($hasil['synced'] > 0 || $hasil['off'] > 0 || $hasil['error'] > 0) {
                CLI::write(sprintf(
                    '  pegawai %d: hadir=%d off=%d dilewati=%d gagal=%d',
                    $id,
                    $hasil['synced'],
                    $hasil['off'],
                    $hasil['skipped'],
                    $hasil['error']
                ));
            }
        }

        CLI::write(sprintf(
            'Selesai: hadir=%d off=%d dilewati=%d gagal=%d',
            $total['synced'],
            $total['off'],
            $total['skipped'],
            $total['error']
        ), $total['error'] > 0 ? 'red' : 'green');

        return $total['error'] > 0 ? 1 : 0;
    }
}
