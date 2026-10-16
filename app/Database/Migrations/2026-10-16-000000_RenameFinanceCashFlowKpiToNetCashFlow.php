<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti label KPI Finance "Cash Flow" menjadi "Net Cash Flow".
 *
 * KEPUTUSAN BISNIS
 * ----------------
 * Halaman /penilaian_kinerja menampilkan nama KPI dari
 * `kpi_components.name` (bukan dari Config\Finance), sehingga label harus
 * ikut diperbarui di database. Komponennya adalah FIN_CASH_FLOW (bobot 15).
 *
 * Yang BERUBAH hanya LABEL. Rumus skor TIDAK disentuh:
 *   Net Cash Flow = Penerimaan - Kas Keluar
 *   NCF%          = Net Cash Flow / Penerimaan x 100
 *   Score         = min((NCF% / target 20%) x 100, 100)
 * Lihat CashFlowCalculator dan Config\Finance::$kpiLabels.
 *
 * Migration lama (2026-10-12-070000_AddFinanceKpiJabatan0) SENGAJA tidak
 * diedit supaya riwayatnya tetap apa adanya; migration ini yang menyesuaikan
 * database yang sudah terlanjur berjalan.
 */
class RenameFinanceCashFlowKpiToNetCashFlow extends Migration
{
    /** Kode komponen KPI Finance yang diganti labelnya. */
    private const KPI_CODE = 'FIN_CASH_FLOW';

    private const LABEL_LAMA = 'Cash Flow';
    private const LABEL_BARU = 'Net Cash Flow';

    public function up()
    {
        $this->renameKpi(self::LABEL_LAMA, self::LABEL_BARU);
    }

    public function down()
    {
        $this->renameKpi(self::LABEL_BARU, self::LABEL_LAMA);
    }

    /**
     * Ganti label hanya selama masih bernilai lama, supaya migration ini aman
     * kalau labelnya sudah pernah diubah manual di luar migration.
     */
    private function renameKpi(string $dari, string $ke): void
    {
        if (! $this->db->fieldExists('name', 'kpi_components')) {
            return;
        }

        $this->db->table('kpi_components')
            ->where('code', self::KPI_CODE)
            ->where('name', $dari)
            ->update(['name' => $ke]);
    }
}
