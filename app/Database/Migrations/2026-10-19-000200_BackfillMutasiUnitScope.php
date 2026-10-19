<?php

namespace App\Database\Migrations;

use App\Services\Finance\FinanceScopeService;
use CodeIgniter\Database\Migration;

/**
 * Backfill scope H/P mutasi antar unit yang dibuat sebelum aturan cut-off
 * diterapkan di jalur pembuatan.
 *
 * Baris `sumber_tipe='mutasi_unit'` bertanggal sebelum periode operasional
 * baru seharusnya `scope='legacy'`, bukan `active`. Idempotent: baris yang
 * sudah legacy (atau sudah ditutup) tidak diubah.
 */
class BackfillMutasiUnitScope extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('hutang_piutang')) {
            return;
        }

        $periode  = FinanceScopeService::periodeMulaiDate();
        $cutoffAt = FinanceScopeService::cutoffDate() . ' 23:59:59';
        $table    = $this->db->prefixTable('hutang_piutang');

        $this->db->query("
            UPDATE `{$table}`
            SET scope = \"legacy\",
                cutoff_closed_at = COALESCE(cutoff_closed_at, :cutoff_at:),
                cutoff_closed_by = 0,
                cutoff_reason = \"LEGACY_CUTOFF\"
            WHERE deleted = 0
              AND scope = \"active\"
              AND sumber_tipe = \"mutasi_unit\"
              AND tanggal < :periode:
        ", ['cutoff_at' => $cutoffAt, 'periode' => $periode]);
    }

    public function down()
    {
        // Sengaja kosong: membalik scope ke active akan menghitung ulang
        // utang legacy sebagai utang aktif. Perbaikan data tidak auto-revert.
    }
}
