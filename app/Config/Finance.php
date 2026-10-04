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
     * KPI yang bisa diisi manual oleh Finance.
     *
     * 'rekonsiliasi' sengaja DISISIPKAN sebagai FALLBACK: modul sudah punya
     * calculator auto, dan FinanceKpiCalculationService mencoba auto lebih
     * dahulu. Jika auto tidak tersedia / error / tidak dapat dihitung, skor
     * manual dari finance_kpi_records tetap dipakai. Jangan hapus item ini
     * sebelum calculator auto terbukti stabil di produksi.
     */
    public array $manualKpiCodes = [
        'kesehatan_uang',
        'rekonsiliasi',
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
     * Tanggal DASAR / statement cut-off Kas & Bank (YYYY-MM-DD).
     *
     * Ini adalah tanggal SALDOKORAN yang disepakati, bukan tanggal mulai
     * operasional. Saldo kas per unit pada tanggal ini (dari tutup_kasir
     * .akhir_cash) dan saldo statement tiap rekening fisik (dari
     * saldo_awal_kas_bank) adalah BASELINE — keduanya baseline, bukan
     * transaksi ledger.
     *
     * PENTING: tanggal ini BUKAN batas bawah ledger. Transaksi ledger
     * transaksi_kas_bank hanya dihitung mulai $periodeMulaiDate. Jangan
     * memakai cutoffDate untuk filter "tanggal >= ..." — itu batas salah.
     *
     * CUT-OFF 2026-10-05. Saldo yang diinput di tanggal ini adalah SALDO
     * RIIL yang diverifikasi Finance pada akhir 5 Okt — bukan hasil SUM
     * transaksi legacy. Transaksi 1–5 Okt TIDAK diposting sebagai transaksi
     * Finance baru; semuanya sudah terserap di saldo riil tersebut.
     */
    public string $cutoffDate = '2026-10-05';

    /**
     * Hari pertama periode operasional baru (YYYY-MM-DD).
     *
     * Inilah batas bawah yang dipakai SEMUA filter ledger
     * (`tanggal >= periodeMulaiDate`). Transaksi sebelum tanggal ini
     * adalah legacy dan TIDAK boleh ikut menghitung saldo aktif; transaksi
     * tepat pada tanggal cut-off (5 Okt) juga legacy karena tanggal itu
     * hanya baseline statement.
     *
     * Cut-off 5 Okt → periode ledger mulai 6 Okt. KPI Keuangan/Cash Flow/
     * Hutang-Piutang yang berbasis periodeMulaiDate otomatis mulai 6 Okt.
     *
     * Harus = cutoffDate + 1 hari. Nilai ini tidak di-hardcode di tempat lain:
     * semua pemakai filtering memanggil FinanceScopeService::periodeMulaiDate().
     */
    public string $periodeMulaiDate = '2026-10-06';

    /**
     * ID_JABATAN yang boleh mengisi (input) Dashboard Finance.
     */
    public array $financeInputRoles = [0, 1, 2, 34];

    /**
     * Dashboard Finance hanya untuk jabatan yang boleh mengisi (0, 1, 2, 34).
     * Dibiarkan kosong: kontrol akses penuh lewat financeInputRoles.
     */
    public array $financeViewRoles = [];

    /**
     * Jabatan yang boleh melakukan approval rekonsiliasi
     * (SUBMITTED -> VERIFIED / NEED_REVISION).
     *
     * Hanya MANAGER (34) dan ADMIN ROOT (1) yang boleh memeriksa (verify).
     * Administrator Finance (0) dan Direktur (2) tetap boleh MENGISI form
     * (financeInputRoles), tetapi tidak berwenang memverifikasi.
     *
     * Aturan YANG WAJIB berlaku apa pun konfigurasi:
     * whoever yang mengirim (submitted_by / input_by) TIDAK BOLEH memverifikasi
     * miliknya sendiri (separation of duties).
     */
    public array $financeApproveRoles = [1, 34];

    /**
     * ID_JABATAN yang boleh MENARIK DANA dari rekening Finance/HO (mis. IRA).
     *
     * Rekening Finance/HO (akun_kas_bank.is_finance_ho = 1) bukan milik unit
     * mana pun dan tidak butuh alokasi_saldo_kas_bank. Akses-nya BERARAH:
     *
     *   - sebagai TUJUAN  : unit mana pun yang memang boleh bertransaksi
     *                      (mis. "Unit 1 -> IRA" dan "Unit 2 -> IRA" sama-sama
     *                      valid). Tidak butuh izin-role apa pun.
     *   - sebagai SUMBER  : HANYA role di bawah ini.
     *
     * 0 = Finance, 1 = Admin root.
     *
     * PENTING: "boleh transfer KE IRA" TIDAK berarti user tersebut boleh
     * memakai IRA sebagai rekening sumber. Jangan gunakan resolveAllowedUnits()
     * sebagai pengganti permission rekening Finance/HO — itu user scope,
     * bukan permission penarikan dana.
     */
    public array $financeHoSourceRoles = [0, 1];

    // =====================================================================
    // PETA REKENING RESMI (ground truth bisnis, sudah dikonfirmasi Finance)
    // =====================================================================
    //
    // Kunci = akun_kas_bank.idakun_kas_bank. Nilai = daftar unit_id yang
    // MEMILIKI HAK memakai rekening fisik tersebut.
    //
    // PENTING —bedakan tiga hal yang sering tercampur:
    //   1. HAK memakai rekening  -> peta di bawah ini + alokasi_saldo_kas_bank
    //   2. SALDO REAL rekening   -> saldo_awal_kas_bank status VERIFIED
    //   3. ALOKASI saldo unit   -> alokasi_saldo_kas_bank.nominal
    //
    // Ketiganya TIDAK boleh disimpulkan satu dari yang lain:
    //   - unit ada di peta ini        => punya hak memakai
    //   - nominal alokasi = 0        => punya HAK, tapi belum ada saldo
    //                                  dialokasikan. BUKAN "tidak punya hak".
    //   - nominal alokasi = 0        => TIDAK berarti saldo fisik rekening 0.
    //
    // Peta ini adalah definisi POLICY untuk migration dan untuk validasi
    // entitlement. Runtime scope tetap membaca entitlement dari
    // alokasi_saldo_kas_bank (sumber data), memakai peta ini sebagai rujukan
    // silang — supayayi Anda tidak mengunci seluruh scope ke array hardcoded
    // sementara tabel alokasi belum terisi.
    //
    // Sejarah transaksi TIDAK boleh dipakai menurunkan peta ini. Transaksi
    // hanya dipakai menemukan anomali historis untuk dilaporkan.
    // KUNCI = bank_idbank, BUKAN idakun_kas_bank.
    //
    // KENAPA bukan idakun_kas_bank. Kolom itu berasal dari AUTO_INCREMENT:
    // migration 2026-09-21-000200 membuat rekening bank tanpa id eksplisit,
    // jadi nomor urutnya TIDAK deterministik antar-linse data. Dump lama
    // punya CV=16/SABRINA=15; dump produksi yang lain bisa CV=12/SABRINA=11
    // hanya karena AUTO_INCREMENT-nya berbeda. Kunci policy di idakun_kas_bank
    // karena itu pecah begitu dump diganti.
    //
    // Yang stabil itu bank_idbank: FK ke master `bank`, nilainya sudah
    // disahkan Finance (nomor rekening + pemilik). Policy di-key di situ,
    // lalu EntitlementPolicyService me-resolve ke idakun_kas_bank saat runtime.
    //
    // Konsekuensi: config ini TIDAK LAGI bisa dipakai tanpa DB. Selalu lewat
    // EntitlementPolicyService, yang me-resolve dan cache per request.
    public array $rekeningResmiByBank = [
        '2' => [1, 2], // CV       — shared 2 unit
        '5' => [3],    // ALFARIZKI — unit 3 saja
        '1' => [4],    // SABRINA  — unit 4 saja
        '3' => [],     // FINANCE  — BUKAN rekening operasional unit
        // GENTENG (unit 5) BELUM ADA di sini: nomor rekening 1802016123
        // belum diverifikasi Finance. Jangan menambahkan sebelum konfirmasi.
    ];

    /**
     * Rekening yang tunduk pada mekanisme SALDO REAL (statement VERIFIED).
     *
     * Rekening Finance/HO dikecualikan: itu rekening kas Direksi, bukan
     * rekening operasional unit, dan tidak punya statement cutoff.
     */
    public array $rekeningWajibStatementByBank = ['2', '5', '1'];

    /**
     * Bentuk master rekening resmi, di-key bank_idbank.
     *
     * Dipakai migration sebagai target assertions. Nilai =
     * ['unit_id' => .., 'is_shared' => ..].
     *
     * `unit_id` null = rekening lintas unit; `is_shared` 1 wajib mengikutinya.
     */
    public array $rekeningResmiMasterByBank = [
        '2' => ['unit_id' => null, 'is_shared' => 1], // CV
        '5' => ['unit_id' => 3,    'is_shared' => 0], // ALFARIZKI
        '1' => ['unit_id' => 4,    'is_shared' => 0], // SABRINA
        '3' => ['unit_id' => null, 'is_shared' => 1], // FINANCE
    ];

    /**
     * Akun Finance/HO — rekening kas Direksi, BUKAN rekening operasional unit.
     *
     * Tidak boleh punya alokasi unit, dan tidak boleh ikut mekanisme saldo
     * real. Aksesnya berasal dari ROLE ($financeHoSourceRoles), bukan dari
     * unitId — user unit 50 (Head Office) TIDAK otomatis mendapat akses
     * hanya karena is_finance_ho = 1.
     */
    public array $financeHoBankIds = ['3'];
}
