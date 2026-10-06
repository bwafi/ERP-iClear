<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

/**
 * Pemeriksaan kesehatan modul stok opname, read-only.
 *
 * Dipakai sebagai checklist setelah deploy / migrasi:
 *   - apakah skema v2 benar-benar terpasang (kolom revert, index, view filter);
 *   - apakah tidak ada data yatim atau periode menggantung;
 *   - apakah KPI opname per unit sesuai aturan FINAL + terisi penuh.
 *
 * Tidak mengubah data apa pun, jadi aman dijalankan kapan saja.
 */
class OpnameVerify extends BaseCommand
{
    protected $group       = 'opname';
    protected $name        = 'opname:verify';
    protected $description = 'Verifikasi integritas data & skema stok opname (read-only).';

    public function run(array $params): int
    {
        $db = \Config\Database::connect();

        $cols = array_column($db->query('SHOW COLUMNS FROM stok_opname')->getResultArray(), 'Field');
        $idx  = array_unique(array_column($db->query('SHOW INDEX FROM stok_opname')->getResultArray(), 'Key_name'));
        $view = '';
        foreach ($db->query('SHOW CREATE VIEW stok_barang')->getRowArray() as $k => $x) {
            if (stripos($k, 'create view') !== false) {
                $view = $x;
            }
        }
        $hasRevert = in_array('is_reverted', $cols, true);

        echo "== Skema ==\n";
        echo 'kolom is_reverted : ' . ($hasRevert ? 'ADA' : 'HILANG') . "\n";
        echo 'index             : ' . implode(', ', $idx) . "\n";
        echo 'view stok_barang  : ' . ($view === ''
            ? 'view tidak ditemukan'
            : (strpos($view, 'is_reverted') !== false ? 'filter is_reverted AKTIF' : 'filter is_reverted NONAKTIF (v1)')) . "\n";
        echo 'tabel audit       : ' . ($db->tableExists('stok_opname_audit') ? 'ADA' : 'HILANG') . "\n";

        echo "\n== Data ==\n";
        echo 'audit rows        : ' . $db->query('SELECT COUNT(*) t FROM stok_opname_audit')->getRow()->t . "\n";
        echo 'stok_opname rows  : ' . $db->query('SELECT COUNT(*) t FROM stok_opname')->getRow()->t . "\n";
        echo 'draft rows        : ' . $db->query('SELECT COUNT(*) t FROM stok_opname_draft')->getRow()->t . "\n";
        if ($hasRevert) {
            echo 'reverted rows     : ' . $db->query('SELECT COUNT(*) t FROM stok_opname WHERE is_reverted = 1')->getRow()->t . "\n";
        }

        $p = $db->query("SELECT
                COUNT(*)                                                   AS total,
                SUM(status = 'FINAL')                                      AS final,
                SUM(status = 'DRAFT')                                      AS draft,
                SUM(status = 'FINAL' AND terisi_barang = total_barang
                    AND total_barang > 0)                                 AS final_lengkap
            FROM stok_opname_periode")->getRow();

        echo 'periode total     : ' . $p->total . "\n";
        echo '  FINAL           : ' . $p->final . "\n";
        echo '  DRAFT           : ' . $p->draft . "\n";
        echo '  FINAL + terisi  : ' . $p->final_lengkap . " (dihitung KPI)\n";

        $yatim = $db->query('SELECT COUNT(*) t FROM stok_opname
            WHERE periode_id IS NULL OR periode_id NOT IN (SELECT id FROM stok_opname_periode)')->getRow()->t;
        echo 'baris yatim       : ' . $yatim . "\n";

        echo "\n== KPI opname bulan berjalan (target 4) ==\n";
        $rows = $db->query("SELECT unit_idunit, COUNT(*) c FROM stok_opname_periode
            WHERE status = 'FINAL' AND terisi_barang = total_barang AND total_barang > 0
              AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
            GROUP BY unit_idunit ORDER BY unit_idunit")->getResultArray();

        if ($rows === []) {
            echo "  (tidak ada)\n";
        }
        foreach ($rows as $r) {
            $flag = (int) $r['c'] >= 4 ? 'OK' : 'belum tercapai';
            echo "  unit {$r['unit_idunit']}: {$r['c']} periode ({$flag})\n";
        }

        return 0;
    }
}
