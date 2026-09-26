<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi Dashboard Finance + KPI Finance (Fase 1).
 *
 * - Bobot KPI mengikuti keputusan bisnis (Fase 1): total harus 100.
 * - Kode disimpan di finance_kpi_records.kpi_code.
 */
class Finance extends BaseConfig
{
    /**
     * Bobot tiap KPI (dalam %).
     */
    public array $kpiWeights = [
        'kesehatan_uang' => 30, // Manual
        'akurasi'        => 20, // Auto + Manual (omzet ERP vs Sheet)
        'cash_flow'      => 15, // Auto
        'hutang_piutang' => 10, // Auto (Fase 2)
        'rekonsiliasi'   => 10, // Manual
        'payroll'        => 5,  // Auto (Fase 4)
        'compliance'     => 5,  // Manual
        'improvement'    => 5,  // Manual
    ];

    /**
     * Label tampilan tiap KPI.
     */
    public array $kpiLabels = [
        'kesehatan_uang' => 'Kesehatan Uang per Cabang',
        'akurasi'        => 'Akurasi Laporan Keuangan',
        'cash_flow'      => 'Cash Flow',
        'hutang_piutang' => 'Hutang & Piutang',
        'rekonsiliasi'   => 'Rekonsiliasi',
        'payroll'        => 'Payroll',
        'compliance'     => 'Compliance',
        'improvement'    => 'Improvement Finance',
    ];

    /**
     * KPI yang diisi manual oleh Finance.
     */
    public array $manualKpiCodes = [
        'kesehatan_uang',
        'compliance',
        'improvement',
    ];

    /**
     * Target/batas Cash Flow (%). Score = (CF% / target) x 100, maks 100.
     */
    public float $cashFlowTargetPercent = 20.0;

    /**
     * Toleransi selisih Omzet ERP vs Sheet agar dianggap "Sesuai" (dalam rupiah).
     * Default 0 = harus sama persis. Keputusan bisnis bisa mengubah konstanta ini.
     */
    public int $omzetTolerance = 0;

    /**
     * Tanggal Financial Cut-off / release engine Finance baru (YYYY-MM-DD).
     *
     * Prinsip: seluruh hutang/piutang/mutasi/kas dengan tanggal transaksi
     * SEBELUM tanggal ini diperlakukan sebagai data legacy/histori (tidak
     * dihitung sebagai saldo aktif). Transaksi pada/≥ tanggal ini diproses
     * normal oleh engine Finance baru. Satu-satunya sumber tanggal cut-off
     * (diubah cukup di sini).
     */
    public string $cutoffDate = '2026-10-01';

    /**
     * ID_JABATAN yang boleh mengisi (input) Dashboard Finance.
     */
    public array $financeInputRoles = [0, 1, 2, 34];

    /**
     * Dashboard Finance hanya untuk jabatan yang boleh mengisi (0, 1, 2, 34).
     * Dibiarkan kosong: kontrol akses penuh lewat financeInputRoles.
     */
    public array $financeViewRoles = [];
}