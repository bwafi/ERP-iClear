<?php

use App\Services\Finance\RekonDailyCalculator;
use App\Models\ModelFinanceRekonDaily;

/**
 * Rekonsiliasi Harian — daftar hari + panel hari terpilih.
 *
 * Struktur: satu baris status (kondisi bulan sebagai kalimat, angka yang bisa
 * diklik sebagai filter GET), lalu konsol dua kolom — indeks hari di kiri,
 * panel hari terpilih di kanan. Angka Cash / Transfer / Kas Keluar hanya ada
 * di panel, satu blok penuh per kelompok, bukan tiga sel "a / b / c" yang
 * harus dibaca menyamping.
 *
 * Yang SENGAJA tidak berubah (kontrak produk):
 *   - route & query string filter: unit_id, month, status (GET, ke route yang
 *     sama). `hari` hanya parameter TAMBAHAN untuk memilih hari yang terbuka;
 *   - label & kelas badge status: RekonDailyCalculator::labelStatus() /
 *     labelProses() / badgeProses() (dipakai juga oleh app/Scripts/
 *     recon_http_smoke.php);
 *   - kata "Skor KPI Rekonsiliasi"
 *
 * Halaman ini sekarang satu-satunya permukaan rekonsiliasi. finance/rekon/form
 * dihapus 2026-09-27: input dan verifikasi keduanya inline di panel per hari,
 * jadi tidak ada perpindahan halaman sama sekali.
 *   - stok kartu aplikasi: .card (radius 1.125rem, tanpa border, satu bayangan
 *     0 2px 6px rgba(37,83,185,.1)) dan token --bs-* yang sudah di-resolve
 *     Blue_Theme. Tidak ada token warna, font, atau ikon baru di halaman ini.
 *
 * MARKUP TIDAK memuat .table: template memaksa
 * `[data-bs-theme="dark"] .table … { color:#7c8fac !important }` sehingga
 * setiap sel tabel jadi satu biru-abu di mode gelap. Indeks hari bukan
 * spreadsheet, jadi ia dibangun dari daftar, bukan tabel.
 */

$units = $units ?? [];
$unit_id = (int) ($unit_id ?? 0);
$unit_name = $unit_name ?? '—';
$month = $month ?? date('Y-m');
$monthLabel = date('F Y', strtotime($month . '-01'));
$list = $list ?? [];
$rekon_score = $rekon_score ?? null;
$can_input = $can_input ?? false;
$can_approve = $can_approve ?? false;
$status_proses = $status_proses ?? '';
$akun_names = $akun_names ?? [];
$my_id = (int) ($my_id ?? 0);

// ── Nama bulan & hari dalam bahasa Indonesia ────────────────────────────
$bulanPanjang = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];
$bulanPendek = [
    1 => 'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];
$hariPendek = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

$tsBulan = strtotime($month . '-01');
$bulanNama = $bulanPanjang[(int) date('n', $tsBulan)] ?? date('F', $tsBulan);
$bulanNamaPendek = $bulanPendek[(int) date('n', $tsBulan)] ?? date('M', $tsBulan);
$periodeLabel = $bulanNama . ' ' . date('Y', $tsBulan);

/** Angka Rupiah: pemisah ribuan titik, desimal koma. */
$fmt = static fn($v): string => number_format((int) $v, 0, ',', '.');

/** Tanggal panjang: "Jumat, 25 September 2026". */
$fmtTanggalPanjang = static function (string $tgl) use ($hariPendek, $bulanPanjang): string {
    $ts = strtotime($tgl);
    $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) date('w', $ts)] ?? '';
    $bulan = $bulanPanjang[(int) date('n', $ts)] ?? date('F', $ts);

    return $namaHari . ', ' . date('j', $ts) . ' ' . $bulan . ' ' . date('Y', $ts);
};

/** Waktu singkat untuk jejak audit: "25 Sep 2026 20:05". */
$fmtWaktu = static function ($datetime) use ($bulanPendek): string {
    $ts = strtotime((string) $datetime);

    return $ts === false ? '—' : date('j', $ts) . ' ' . ($bulanPendek[(int) date('n', $ts)] ?? date('M', $ts)) . ' ' . date('Y H:i', $ts);
};

$komponen = [
    'cash_masuk'     => ['nama' => 'Cash Masuk',     'col' => 'erp_cash_masuk',     'act' => 'actual_cash_masuk',     'sel' => 'selisih_cash_masuk'],
    'transfer_masuk' => ['nama' => 'Transfer Masuk', 'col' => 'erp_transfer_masuk', 'act' => 'actual_transfer_masuk', 'sel' => 'selisih_transfer_masuk'],
    'kas_keluar'     => ['nama' => 'Kas Keluar',     'col' => 'erp_kas_keluar',     'act' => 'actual_kas_keluar',     'sel' => 'selisih_kas_keluar'],
];

// ── Keadaan bulan (dihitung dari data yang sudah ada, bukan angka baru) ──
$hitung = ['kosong' => 0, 'draft' => 0, 'revisi' => 0, 'submit' => 0, 'verified' => 0];
$kunciStatus = [
    ModelFinanceRekonDaily::STATUS_VERIFIED     => 'verified',
    ModelFinanceRekonDaily::STATUS_SUBMITTED     => 'submit',
    ModelFinanceRekonDaily::STATUS_NEED_REVISION => 'revisi',
    ModelFinanceRekonDaily::STATUS_DRAFT         => 'draft',
];
$tanggalAda = [];
foreach ($list as $item) {
    $tanggalAda[$item['tanggal']] = true;
    if (! $item['row']) {
        $hitung['kosong']++;
        continue;
    }
    $hitung[$kunciStatus[RekonDailyCalculator::statusProses($item['row'])] ?? 'draft']++;
}

/**
 * Hari yang paling perlu dikerjakan dibuka otomatis, supaya halaman dimulai
 * dari pekerjaan, bukan dari pilihan kosong. Urutan prioritas:
 * perlu revisi > belum lengkap > draft > belum ada isian > menunggu
 * verifikasi > selesai; dalam prioritas yang sama, hari paling baru dulu.
 * Ini hanya urutan PEMILIHAN TAMPILAN — bukan aturan bisnis baru.
 */
$prioritas = static function (array $item): int {
    if (! $item['row']) {
        return 4;
    }
    $proses = RekonDailyCalculator::statusProses($item['row']);
    if ($proses === ModelFinanceRekonDaily::STATUS_NEED_REVISION) {
        return 1;
    }
    if ($proses === ModelFinanceRekonDaily::STATUS_DRAFT) {
        return ! empty($item['lengkap']) ? 3 : 2;
    }
    if ($proses === ModelFinanceRekonDaily::STATUS_SUBMITTED) {
        return 5;
    }

    return 6;
};

$hariDipilih = '';
if ($list !== []) {
    $hariDiminta = (string) (service('request')->getGet('hari') ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hariDiminta) && isset($tanggalAda[$hariDiminta])) {
        $hariDipilih = $hariDiminta;
    } else {
        $kandidat = $list;
        usort($kandidat, static function (array $a, array $b) use ($prioritas): int {
            $beda = $prioritas($a) <=> $prioritas($b);

            return $beda !== 0 ? $beda : strcmp((string) $b['tanggal'], (string) $a['tanggal']);
        });
        $hariDipilih = (string) $kandidat[0]['tanggal'];
    }
}

/** Link hari: filter yang sedang aktif dipertahankan, `hari` ditambahkan. */
$linkHari = static function (string $tgl) use ($unit_id, $month, $status_proses): string {
    return 'finance/rekonsiliasi?unit_id=' . $unit_id
        . '&month=' . rawurlencode($month)
        . ($status_proses !== '' ? '&status=' . rawurlencode($status_proses) : '')
        . '&hari=' . rawurlencode($tgl);
};

/** Link filter status (klik pada angka di baris status). */
$linkStatus = static function (string $nilai) use ($unit_id, $month): string {
    return 'finance/rekonsiliasi?unit_id=' . $unit_id
        . '&month=' . rawurlencode($month)
        . ($nilai !== '' ? '&status=' . rawurlencode($nilai) : '');
};

?>
<?php /* Token bersama dengan form harian: app/Views/inc/rek_tokens.php */ ?>
<?= view('inc/rek_tokens') ?>
<?= view('inc/rek_input_js') ?>

<style>
    /* ── Baris status: kondisi bulan sebagai satu kalimat ─────── */
    .rk-band .card-body {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1.5rem;
    }

    .rk-band-scope {
        font-size: .875rem;
        color: var(--rk-ink-2);
    }

    .rk-band-scope b {
        color: var(--rk-ink);
        font-weight: 600;
    }

    .rk-counts {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .375rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .rk-counts li+li:before {
        content: "";
        display: inline-block;
        width: 1px;
        height: 1em;
        margin-right: .75rem;
        background: var(--rk-line);
        vertical-align: -.125em;
    }

    .rk-count {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        padding: .25rem .5rem;
        margin: 0;
        border-radius: .5rem;
        font-size: .8125rem;
        line-height: 1.3;
        color: var(--rk-ink-2);
        text-decoration: none;
        transition: background-color .18s ease, color .18s ease;
    }

    .rk-count b {
        color: var(--rk-ink);
        font-weight: 600;
    }

    a.rk-count:hover {
        background: var(--rk-raise);
        color: var(--rk-ink);
    }

    a.rk-count:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 1px;
    }

    .rk-count-static {
        color: var(--rk-ink-2);
        padding-left: 0;
    }

    .rk-count-reset {
        padding: .25rem .625rem;
        border: 1px solid var(--rk-line);
        background: var(--rk-raise);
    }

    .rk-dot {
        flex: 0 0 auto;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--rk-ink-3);
    }

    .rk-dot-draft {
        background: var(--bs-warning);
    }

    .rk-dot-revisi {
        background: var(--bs-danger);
    }

    .rk-dot-submit {
        background: var(--bs-info);
    }

    .rk-dot-verified {
        background: var(--bs-success);
    }

    /* Skor KPI: angka + meter tipis, bukan kartu metrik sendiri. */
    .rk-score {
        margin-left: auto;
        min-width: 15rem;
    }

    .rk-score-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .75rem;
        font-size: .8125rem;
        color: var(--rk-ink-2);
    }

    .rk-score-top b {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--rk-ink);
    }

    .rk-meter {
        height: 4px;
        margin: .375rem 0 .25rem;
        border-radius: 2px;
        background: var(--rk-raise);
        overflow: hidden;
    }

    .rk-meter i {
        display: block;
        height: 100%;
        background: var(--bs-primary);
    }

    .rk-score-sub {
        font-size: .75rem;
        color: var(--rk-ink-3);
    }

    /* ── Konsol: indeks hari | panel hari ─────────────────────── */
    .rk-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .75rem 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--rk-line);
    }

    .rk-filters .rk-field {
        display: grid;
        gap: .25rem;
    }

    .rk-filters .rk-field>label {
        margin: 0;
        font-size: .75rem;
        font-weight: 600;
        color: var(--rk-ink-2);
    }

    .rk-console {
        display: grid;
        grid-template-columns: minmax(0, 42fr) minmax(0, 58fr);
        align-items: start;
    }

    .rk-index {
        min-width: 0;
        border-right: 1px solid var(--rk-line);
    }

    .rk-index-head {
        position: sticky;
        top: 4.375rem;
        z-index: 1;
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .5rem;
        padding: .75rem 1.25rem;
        font-size: .75rem;
        color: var(--rk-ink-3);
        background: var(--bs-card-bg, var(--bs-body-bg));
        border-bottom: 1px solid var(--rk-line);
    }

    .rk-days {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .rk-day {
        display: grid;
        grid-template-columns: 4.25rem minmax(0, 1fr) auto;
        align-items: center;
        gap: .5rem .75rem;
        min-height: 3.25rem;
        padding: .5rem 1.25rem;
        color: inherit;
        text-decoration: none;
        border-bottom: 1px solid var(--rk-line);
        transition: background-color .18s ease;
    }

    .rk-day:hover {
        background: var(--rk-raise);
    }

    .rk-day:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: -2px;
    }

    .rk-day.is-active {
        background: var(--bs-primary-bg-subtle);
        box-shadow: inset 3px 0 0 var(--bs-primary);
    }

    .rk-day-d {
        display: block;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.1;
        color: var(--rk-ink);
    }

    .rk-day-m {
        display: block;
        font-size: .75rem;
        color: var(--rk-ink-3);
    }

    .rk-day.is-active .rk-day-d {
        color: var(--bs-primary);
    }

    .rk-day-state {
        display: block;
        font-size: .8125rem;
        line-height: 1.3;
        color: var(--rk-ink-2);
    }

    .rk-day-flag {
        display: block;
        margin-top: .125rem;
        font-size: .75rem;
        line-height: 1.3;
        font-weight: 500;
        color: var(--rk-bad-ink);
    }

    .rk-day-flag.is-netral {
        color: var(--rk-ink-3);
    }

    .rk-day-empty {
        display: block;
        font-size: .75rem;
        line-height: 1.3;
        color: var(--rk-ink-3);
    }

    .rk-day .badge {
        font-weight: 500;
    }

    /* ── Panel hari ───────────────────────────────────────────── */
    /* Topbar template sticky setinggi 4.375rem; panel tidak boleh menyelip
       ke bawahnya, makanya offset sama dan max-height-nya disisakan
       ruang untuk topbar + jeda bawah. */
    .rk-aside {
        position: sticky;
        top: 4.375rem;
        min-width: 0;
        max-height: calc(100vh - 6rem);
        padding: 1.25rem;
        overflow-y: auto;
    }

    .rk-panel[hidden] {
        display: none !important;
    }

    .rk-panel-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: .5rem 1rem;
    }

    .rk-panel-date {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.25;
        color: var(--rk-ink);
    }

    .rk-panel-sub {
        margin: .125rem 0 0;
        font-size: .8125rem;
        color: var(--rk-ink-2);
    }

    /* Aksi panel: simpan, dan verifikasi manager yang sekarang inline di sini.
       .rk-panel-save belum punya definisi sejak markup-nya ditambahkan, jadi
       tombolnya selama ini tidak mendapat spacing sama sekali. */
    .rk-panel-save {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .625rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--rk-line);
    }

    .rk-panel-save-note {
        font-size: .8125rem;
        color: var(--rk-ink-2);
    }

    .rk-appr-form {
        display: grid;
        gap: .375rem;
        width: 100%;
    }

    .rk-appr-label {
        font-size: .8125rem;
        font-weight: 600;
        color: var(--rk-ink);
    }

    .rk-appr-acts {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    .rk-panel-where {
        margin: .75rem 0 0;
        padding: .5rem .75rem;
        font-size: .8125rem;
        line-height: 1.45;
        color: var(--rk-ink-2);
        background: var(--rk-raise);
        border-radius: .5rem;
    }

    .rk-panel-where b {
        color: var(--rk-ink);
        font-weight: 600;
    }

    .rk-comps {
        display: grid;
        gap: .625rem;
        margin: 1rem 0 0;
    }

    .rk-comp {
        padding: .75rem .875rem;
        border: 1px solid var(--rk-line);
        border-radius: .625rem;
    }

    .rk-comp-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .rk-comp-head h3 {
        margin: 0;
        font-size: .9375rem;
        font-weight: 600;
        color: var(--rk-ink);
    }

    .rk-comp-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
        margin-top: .5rem;
    }

    .rk-cell-k {
        display: block;
        font-size: .75rem;
        color: var(--rk-ink-3);
    }

    .rk-cell-v {
        display: block;
        margin-top: .125rem;
        font-size: 1rem;
        line-height: 1.2;
        color: var(--rk-ink);
        word-break: break-all;
    }

    .rk-cell-v.is-actual {
        font-weight: 600;
    }

    .rk-cell-v.is-nihil {
        color: var(--rk-ink-3);
        font-weight: 400;
    }

    .rk-cell-v.is-selisih-nol {
        color: var(--rk-good-ink);
    }

    .rk-cell-v.is-selisih {
        color: var(--rk-bad-ink);
        font-weight: 600;
    }

    .rk-notes {
        display: grid;
        gap: .625rem;
        margin: 1rem 0 0;
    }

    .rk-note {
        padding: .625rem .75rem;
        font-size: .8125rem;
        line-height: 1.5;
        border-radius: .5rem;
        background: var(--rk-raise);
        color: var(--rk-ink-2);
    }

    .rk-note-k {
        display: block;
        margin-bottom: .125rem;
        font-size: .75rem;
        font-weight: 600;
        color: var(--rk-ink);
    }

    .rk-note-revisi {
        background: var(--bs-danger-bg-subtle);
    }

    .rk-trail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
        gap: .5rem .75rem;
        margin: 1rem 0 0;
        padding: 0;
    }

    .rk-trail div {
        min-width: 0;
    }

    .rk-trail dt {
        font-size: .75rem;
        font-weight: 400;
        color: var(--rk-ink-3);
    }

    .rk-trail dd {
        margin: 0;
        font-size: .8125rem;
        color: var(--rk-ink);
        overflow-wrap: anywhere;
    }

    .rk-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .625rem;
        margin: 1.25rem 0 0;
        padding-top: 1rem;
        border-top: 1px solid var(--rk-line);
    }

    /* Target sentuh: .btn-sm template hanya ~29px, di bawah lantai 32px
       yang dipakai halaman finance lain, jadi aksi utama diberi tinggi
       minimum dan di layar sentuh dibuat sedikit lebih lega. */
    .rk-actions .btn {
        min-height: 2rem;
    }

    .rk-legend {
        margin: 0;
        padding: .75rem 1.25rem;
        font-size: .75rem;
        line-height: 1.5;
        color: var(--rk-ink-3);
        border-top: 1px solid var(--rk-line);
    }

    /* ── Kosong ───────────────────────────────────────────────── */
    .rk-empty {
        padding: 2.5rem 1.25rem;
        text-align: center;
    }

    .rk-empty h2 {
        margin: 0 0 .375rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--rk-ink);
    }

    .rk-empty p {
        margin: 0;
        font-size: .8125rem;
        color: var(--rk-ink-2);
    }

    @media (max-width: 1399.98px) {
        .rk-console {
            grid-template-columns: minmax(0, 40fr) minmax(0, 60fr);
        }
    }

    /* Di bawah tablet: satu kolom, panel pindah tepat di bawah baris yang
       dipilih (dikelola JS; tanpa JS panel tetap tampil di bawah daftar). */
    @media (max-width: 991.98px) {
        .rk-console {
            grid-template-columns: minmax(0, 1fr);
        }

        .rk-index {
            border-right: 0;
        }

        /* Di bawah 992px topbar membungkus sampai ~140px, jadi kepala
           indeks tidak lagi Worthy sticky: satu kolom, daftar panjang,
           dan ruang vertikalnya lebih mahal daripada label berulang. */
        .rk-index-head {
            position: static;
        }

        .rk-aside {
            position: static;
            max-height: none;
            padding: 0;
            overflow: visible;
        }

        .rk-aside.rk-aside-moved {
            display: none;
        }

        .rk-day {
            grid-template-columns: 3.5rem minmax(0, 1fr);
            row-gap: .375rem;
        }

        .rk-day .badge {
            grid-column: 2;
            justify-self: start;
        }

        .rk-panel {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--rk-line);
        }
    }

    @media (max-width: 575.98px) {
        .rk-band .card-body {
            gap: .625rem .875rem;
        }

        .rk-counts {
            gap: .25rem;
        }

        .rk-counts li+li:before {
            margin-right: .5rem;
        }

        .rk-score {
            margin-left: 0;
            width: 100%;
            min-width: 0;
        }

        .rk-day {
            padding: .5rem 1rem;
        }

        .rk-comp-grid {
            gap: .5rem;
        }

        .rk-cell-v {
            font-size: .9375rem;
        }

        .rk-actions .btn {
            min-height: 2.75rem;
        }
    }
</style>

<div class="rk-scope">

    <div class="card shadow-none rk-masthead mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 p-4">
            <div>
                <h1>Rekonsiliasi Harian</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard/finance') ?>">Dashboard Finance</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Rekonsiliasi</li>
                    </ol>
                </nav>
            </div>
            <div class="rk-scope-readout">
                <span><b>Unit</b> <?= esc($unit_name) ?></span>
                <span><b>Periode</b> <?= esc($periodeLabel) ?></span>
            </div>
        </div>
    </div>

    <?php if (session()->getFlashdata('sukses')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata('sukses')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('gagal')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata('gagal')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card rk-band mb-3">
        <div class="card-body">
            <p class="rk-band-scope mb-0">
                <b><?= esc($bulanNama) ?> <?= esc(date('Y', $tsBulan)) ?></b> · <?= esc($unit_name) ?>
            </p>

            <?php if ($list !== []): ?>
                <?php if ($status_proses === ''): ?>
                    <ul class="rk-counts">
                        <li>
                            <span class="rk-count rk-count-static">
                                <span class="rk-dot" aria-hidden="true"></span>
                                Belum diisi <b class="rk-num"><?= $hitung['kosong'] ?></b>
                            </span>
                        </li>
                        <li>
                            <a class="rk-count" href="<?= base_url($linkStatus(ModelFinanceRekonDaily::STATUS_DRAFT)) ?>">
                                <span class="rk-dot rk-dot-draft" aria-hidden="true"></span>
                                Draft <b class="rk-num"><?= $hitung['draft'] ?></b>
                            </a>
                        </li>
                        <li>
                            <a class="rk-count" href="<?= base_url($linkStatus(ModelFinanceRekonDaily::STATUS_NEED_REVISION)) ?>">
                                <span class="rk-dot rk-dot-revisi" aria-hidden="true"></span>
                                Perlu revisi <b class="rk-num"><?= $hitung['revisi'] ?></b>
                            </a>
                        </li>
                        <li>
                            <a class="rk-count" href="<?= base_url($linkStatus(ModelFinanceRekonDaily::STATUS_SUBMITTED)) ?>">
                                <span class="rk-dot rk-dot-submit" aria-hidden="true"></span>
                                Menunggu verify <b class="rk-num"><?= $hitung['submit'] ?></b>
                            </a>
                        </li>
                        <li>
                            <a class="rk-count" href="<?= base_url($linkStatus(ModelFinanceRekonDaily::STATUS_VERIFIED)) ?>">
                                <span class="rk-dot rk-dot-verified" aria-hidden="true"></span>
                                Selesai <b class="rk-num"><?= $hitung['verified'] ?></b>
                            </a>
                        </li>
                    </ul>
                <?php else: ?>
                    <p class="rk-band-scope mb-0">
                        Menampilkan <b><?= esc(RekonDailyCalculator::labelProses($status_proses)) ?></b>
                        · <b class="rk-num"><?= count($list) ?></b> hari<?php
                                                                        // $list sudah difilter status, jadi angka "dari … hari"
                                                                        // diambil dari detail KPI yang dihitung untuk
                                                                        // seluruh bulan — bukan dari daftar yang difilter.
                                                                        if (! empty($rekon_score['detail']['hari_dilalui'])): ?>
                        dari <b class="rk-num"><?= (int) $rekon_score['detail']['hari_dilalui'] ?></b> hari
                    <?php endif; ?>
                    &nbsp;·&nbsp;
                    <a class="rk-count rk-count-reset" href="<?= base_url($linkStatus('')) ?>">Tampilkan semua</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="rk-score">
                <div class="rk-score-top">
                    <span>Skor KPI Rekonsiliasi</span>
                    <?php if (isset($rekon_score['score']) && $rekon_score['score'] !== null): ?>
                        <b class="rk-num"><?= number_format((float) $rekon_score['score'], 2, ',', '.') ?>%</b>
                    <?php else: ?>
                        <b>Belum tersedia</b>
                    <?php endif; ?>
                </div>
                <div class="rk-meter" role="img"
                    aria-label="Skor KPI Rekonsiliasi <?= isset($rekon_score['score']) && $rekon_score['score'] !== null ? number_format((float) $rekon_score['score'], 0) . ' persen' : 'belum tersedia' ?>">
                    <i style="width: <?= isset($rekon_score['score']) && $rekon_score['score'] !== null
                                            ? max(0, min(100, (float) $rekon_score['score'])) : 0 ?>%"></i>
                </div>
                <div class="rk-score-sub">
                    <?php if (! empty($rekon_score['detail']['hari_dilalui'])): ?>
                        <span class="rk-num"><?= (int) $rekon_score['detail']['hari_lengkap_verified'] ?></span>
                        / <span class="rk-num"><?= (int) $rekon_score['detail']['hari_dilalui'] ?></span>
                        hari lengkap &amp; verified
                    <?php else: ?>
                        Semua hari kalender, dipotong di hari ini
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card rk-deck">
        <div class="card-body">
            <form action="<?= base_url('finance/rekonsiliasi') ?>" method="get" class="rk-filters">
                <div class="rk-field">
                    <label for="rk-unit">Unit</label>
                    <select name="unit_id" id="rk-unit" class="form-select form-select-sm" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int) ($u->idunit ?? 0) ?>" <?= (int) ($u->idunit ?? 0) === $unit_id ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rk-field">
                    <label for="rk-month">Bulan</label>
                    <input type="month" name="month" id="rk-month" class="form-control form-control-sm"
                        value="<?= esc($month) ?>">
                </div>
                <div class="rk-field">
                    <label for="rk-status">Status Proses</label>
                    <select name="status" id="rk-status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach (
                            [
                                ModelFinanceRekonDaily::STATUS_DRAFT        => 'Draft',
                                ModelFinanceRekonDaily::STATUS_SUBMITTED    => 'Submitted',
                                ModelFinanceRekonDaily::STATUS_VERIFIED     => 'Verified',
                                ModelFinanceRekonDaily::STATUS_NEED_REVISION => 'Perlu Revisi',
                            ] as $val => $label
                        ): ?>
                            <option value="<?= esc($val) ?>" <?= $status_proses === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rk-field">
                    <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                </div>
            </form>

            <?php if ($list === []): ?>
                <div class="rk-empty">
                    <h2>Belum ada data rekonsiliasi untuk periode ini</h2>
                    <p class="mb-0">Pilih unit dan bulan lain, atau tunggu sampai ada transaksi pada periode tersebut.</p>
                </div>
            <?php else: ?>
                <div class="rk-console">
                    <div class="rk-index">
                        <div class="rk-index-head">
                            <span><?= esc($bulanNamaPendek) ?> <?= esc(date('Y', $tsBulan)) ?></span>
                            <span class="rk-num"><?= count($list) ?> hari</span>
                        </div>
                        <ol class="rk-days">
                            <?php foreach ($list as $item):
                                $row = $item['row'];
                                $tgl = (string) $item['tanggal'];
                                $ts = strtotime($tgl);
                                $sHarian = RekonDailyCalculator::statusHarian($row);
                                $sProses = $row ? RekonDailyCalculator::statusProses($row) : '';
                                $aktif = $tgl === $hariDipilih;
                                // Minggu TIDAK diberi perlakuan khusus: rekonsiliasi
                                // wajib diisi tiap hari kalender, jadi sebuah Minggu
                                // tampil dan dinilai persis seperti hari lain.
                            ?>
                                <li>
                                    <a class="rk-day<?= $aktif ? ' is-active' : '' ?>" data-day="<?= esc($tgl) ?>"
                                        href="<?= base_url($linkHari($tgl)) ?>" <?= $aktif ? ' aria-current="true"' : '' ?>>
                                        <span>
                                            <span class="rk-day-d rk-num"><?= esc(date('j', $ts)) ?></span>
                                            <span class="rk-day-m"><?= esc(($bulanPendek[(int) date('n', $ts)] ?? date('M', $ts)) . ' · ' . $hariPendek[(int) date('w', $ts)]) ?></span>
                                        </span>
                                        <span>
                                            <span class="rk-day-state"><?= esc(RekonDailyCalculator::labelStatus($sHarian)) ?></span>
                                            <?php if ($row && $row->catatan_revisi): ?>
                                                <span class="rk-day-flag">Catatan revisi</span>
                                            <?php elseif (! $row): ?>
                                                <span class="rk-day-empty">Belum ada isian</span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($sProses !== ''): ?>
                                            <span class="badge <?= RekonDailyCalculator::badgeProses($sProses) ?>">
                                                <?= esc(RekonDailyCalculator::labelProses($sProses)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <div class="rk-aside" role="region" aria-label="Detail hari terpilih">
                        <?php foreach ($list as $item):
                            $row = $item['row'];
                            $tgl = (string) $item['tanggal'];
                            $aktif = $tgl === $hariDipilih;
                            $sHarian = RekonDailyCalculator::statusHarian($row);
                            $sProses = $row ? RekonDailyCalculator::statusProses($row) : '';
                            $terkunci = RekonDailyCalculator::isLocked($row);
                            // Angka terkunci bukan hanya saat VERIFIED: hari yang
                            // sudah dikirim jadi pernyataan dan hanya boleh
                            // disentuh oleh yang mengirimnya.
                            $bolehUbah = $item['boleh_ubah'] ?? RekonDailyCalculator::bolehUbahAngka($row, (int) ($my_id ?? 0));
                            $kunciAlasan = $item['kunci_alasan'] ?? RekonDailyCalculator::kunciAlasan($row, (int) ($my_id ?? 0));

                            // Awalan id untuk form inline tanggal ini. Daftar memuat
                            // satu panel per hari kalender, jadi tanpa prefix tiga
                            // input tiap hari akan saling menimpa id yang sama.
                            $prefix = 'p' . str_replace('-', '', $tgl) . '-';

                            // Kalimat "di tangan siapa" — hanya petunjuk tampilan.
                            // Otorisasi approve tetap di server (DashboardFinance::canApproveRekon).
                            $menungguAnda = $sProses === ModelFinanceRekonDaily::STATUS_SUBMITTED
                                && $can_approve
                                && $my_id > 0
                                && (int) $row->submitted_by !== $my_id
                                && (int) $row->input_by !== $my_id;

                            $kurang = [];
                            foreach ($komponen as $suffix => $meta) {
                                if ($row && $row->{$meta['act']} === null) {
                                    $kurang[] = $meta['nama'];
                                }
                            }
                        ?>
                            <article class="rk-panel" data-day="<?= esc($tgl) ?>" <?= $aktif ? '' : ' hidden' ?>
                                aria-label="<?= esc($fmtTanggalPanjang($tgl)) ?>">
                                <header class="rk-panel-head">
                                    <div>
                                        <h2 class="rk-panel-date"><?= esc($fmtTanggalPanjang($tgl)) ?></h2>
                                        <p class="rk-panel-sub">
                                            <?= esc($unit_name) ?>
                                            <?php if ($sProses !== ''): ?>
                                                · <span class="badge <?= RekonDailyCalculator::badgeProses($sProses) ?>"><?= esc(RekonDailyCalculator::labelProses($sProses)) ?></span>
                                                <span class="badge <?= RekonDailyCalculator::badgeStatus($sHarian) ?>"><?= esc(RekonDailyCalculator::labelStatus($sHarian)) ?></span>
                                            <?php else: ?>
                                                · <span class="badge <?= RekonDailyCalculator::badgeStatus($sHarian) ?>"><?= esc(RekonDailyCalculator::labelStatus($sHarian)) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </header>

                                <?php if (! $row): ?>
                                    <p class="rk-panel-where mb-0">
                                        <b>Belum ada isian</b> untuk tanggal ini. Angka ERP ikut tersimpan
                                        saat draft disimpan, jadi hari ini belum punya angka untuk
                                        dicocokkan — buka formnya untuk mengisinya.
                                    </p>
                                <?php elseif ($sProses === ModelFinanceRekonDaily::STATUS_NEED_REVISION): ?>
                                    <p class="rk-panel-where mb-0">
                                        <b>Dikembalikan untuk revisi.</b> Perbaiki angka yang ditandai,
                                        lalu simpan lagi — begitu lengkap otomatis terkirim ke
                                        Manager / Admin Root.
                                    </p>
                                <?php elseif ($terkunci): ?>
                                    <p class="rk-panel-where mb-0">
                                        <b>Terkunci.</b> Diverifikasi oleh
                                        <?= esc($akun_names[(int) $row->verified_by] ?? '—') ?>
                                        pada <?= esc($fmtWaktu($row->verified_at)) ?> dan tidak dapat diubah.
                                    </p>
                                <?php elseif ($sProses === ModelFinanceRekonDaily::STATUS_SUBMITTED): ?>
                                    <p class="rk-panel-where mb-0">
                                        <?php if ($menungguAnda): ?>
                                            <b>Menunggu verifikasi Anda.</b> Data sudah lengkap dan
                                            dikirim — pakai tombol Verify di panel ini.
                                        <?php else: ?>
                                            Menunggu verifikasi Manager / Admin Root.
                                        <?php endif; ?>
                                    </p>
                                <?php elseif ($kurang !== []): ?>
                                    <p class="rk-panel-where mb-0">
                                        <b>Belum lengkap.</b> Belum diisi: <?= esc(implode(', ', $kurang)) ?>.
                                    </p>
                                <?php endif; ?>

                                <?php if ($can_input): ?>
                                    <?php
                                    // Panel yang bisa diisi. Markup & logikanya sama
                                    // dengan halaman form (inc/rek_input_fields + JS),
                                    // jadi angka selisih yang tampil di sini sama
                                    // dengan yang akan tersimpan.
                                    //
                                    // Angka ERP yang dipakai adalah angka TERKINI
                                    // ($item['erp'], satu query rentang per bulan),
                                    // bukan snapshot di record — sama seperti form.
                                    $erpHari = $item['erp'] ?? [
                                        'cash_masuk' => 0,
                                        'transfer_masuk' => 0,
                                        'kas_keluar' => 0,
                                    ];
                                    ?>
                                    <form action="<?= base_url('finance/rekon/save') ?>" method="post"
                                        class="rk-panel-form" data-rk-input data-rk-prefix="<?= esc($prefix) ?>">
                                        <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
                                        <input type="hidden" name="tanggal" value="<?= esc($tgl) ?>">

                                        <?= view('inc/rek_input_fields', [
                                            'ex'            => $row,
                                            'erp'           => $erpHari,
                                            'bolehUbah'     => $bolehUbah,
                                            'kunciAlasan'   => $kunciAlasan,
                                            'my_id'         => (int) ($my_id ?? 0),
                                            'catatanRevisi' => $row ? $row->catatan_revisi : null,
                                            'idPrefix'      => $prefix,
                                            'showLockNote'  => true,
                                        ]) ?>

                                        <?php if ($bolehUbah): ?>
                                            <div class="rk-panel-save">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Simpan &amp; Kirim
                                                </button>
                                                <span class="rk-panel-save-note">
                                                    Sekali klik: lengkap langsung terkirim, belum lengkap jadi draft.
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <p class="rk-panel-where mb-0">
                                                <b>Tidak bisa diubah.</b> <?= esc((string) $kunciAlasan) ?>
                                            </p>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <div class="rk-comps">
                                        <?php foreach ($komponen as $suffix => $meta):
                                            $erp = $row ? $row->{$meta['col']} : null;
                                            $aktual = $row ? $row->{$meta['act']} : null;
                                            $selisih = $row ? $row->{$meta['sel']} : null;
                                            $stKomponen = RekonDailyCalculator::statusKomponen($row, $suffix);
                                        ?>
                                            <section class="rk-comp">
                                                <div class="rk-comp-head">
                                                    <h3><?= esc($meta['nama']) ?></h3>
                                                    <span class="badge <?= RekonDailyCalculator::badgeKomponen($stKomponen) ?>">
                                                        <?= esc(RekonDailyCalculator::labelKomponen($stKomponen)) ?>
                                                    </span>
                                                </div>
                                                <div class="rk-comp-grid">
                                                    <div>
                                                        <span class="rk-cell-k">ERP</span>
                                                        <span class="rk-cell-v rk-num<?= $erp === null ? ' is-nihil' : '' ?>"><?= $erp === null ? '—' : $fmt($erp) ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="rk-cell-k">Aktual</span>
                                                        <span class="rk-cell-v rk-num is-actual<?= $aktual === null ? ' is-nihil' : '' ?>"><?= $aktual === null ? '—' : $fmt($aktual) ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="rk-cell-k">Selisih</span>
                                                        <?php if ($selisih === null): ?>
                                                            <span class="rk-cell-v is-nihil">—</span>
                                                        <?php else: ?>
                                                            <span class="rk-cell-v rk-num <?= (int) $selisih === 0 ? ' is-selisih-nol' : 'is-selisih' ?>">
                                                                <?= (int) $selisih > 0 ? '+' : ((int) $selisih < 0 ? '−' : '') . $fmt(abs((int) $selisih)) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (! $can_input && $row && ($row->catatan || $row->catatan_revisi)): ?>
                                    <div class="rk-notes">
                                        <?php if ($row->catatan): ?>
                                            <div class="rk-note">
                                                <span class="rk-note-k">Catatan</span>
                                                <?= esc((string) $row->catatan) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row->catatan_revisi): ?>
                                            <div class="rk-note rk-note-revisi">
                                                <span class="rk-note-k">Catatan manager (perlu revisi)</span>
                                                <?= esc((string) $row->catatan_revisi) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($row && ($row->input_by || $row->submitted_by || $row->verified_by)): ?>
                                    <dl class="rk-trail">
                                        <?php if ($row->input_by): ?>
                                            <div>
                                                <dt>Input</dt>
                                                <dd><?= esc($akun_names[(int) $row->input_by] ?? '—') ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row->submitted_by): ?>
                                            <div>
                                                <dt>Submit</dt>
                                                <dd><?= esc($akun_names[(int) $row->submitted_by] ?? '—') ?><?= $row->submitted_at ? ' · ' . esc($fmtWaktu($row->submitted_at)) : '' ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row->verified_by): ?>
                                            <div>
                                                <dt>Verify</dt>
                                                <dd><?= esc($akun_names[(int) $row->verified_by] ?? '—') ?><?= $row->verified_at ? ' · ' . esc($fmtWaktu($row->verified_at)) : '' ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                <?php endif; ?>

                                <div class="rk-actions">
                                    <?php
                                    // Tidak ada lagi pindah halaman untuk input maupun
                                    // verifikasi (halaman finance/rekon/form dihapus
                                    // 2026-09-27). Yang tersisa di baris ini:
                                    //   - form verifikasi, kalau pemohon berhak dan
                                    //     hari ini Submitted
                                    //   - penanda read-only
                                    //   - atau panel input yang sudah ada di atas
                                    $bolehVerifikasi = $sProses === ModelFinanceRekonDaily::STATUS_SUBMITTED
                                        && ! empty($item['can_approve']);
                                    ?>
                                    <?php if ($bolehVerifikasi): ?>
                                        <form action="<?= base_url('finance/rekon/approve') ?>" method="post"
                                            class="rk-appr-form"
                                            data-rk-appr="<?= esc($prefix) ?>"
                                            aria-label="Verifikasi manager untuk <?= esc($fmtTanggalPanjang($tgl)) ?>">
                                            <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
                                            <input type="hidden" name="tanggal" value="<?= esc($tgl) ?>">

                                            <label class="rk-appr-label" for="<?= esc($prefix) ?>catatan_revisi">
                                                Catatan revisi
                                            </label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="<?= esc($prefix) ?>catatan_revisi" name="catatan_revisi"
                                                value="<?= esc((string) ($row ? $row->catatan_revisi : '')) ?>"
                                                placeholder="Wajib diisi bila memilih butuh Revisi."
                                                aria-describedby="<?= esc($prefix) ?>revisi-help">
                                            <p class="form-text mb-2" id="<?= esc($prefix) ?>revisi-help">
                                                Verifikasi mengunci angka selamanya. Butuh Revisi
                                                mengembalikannya ke pengirim, jadi isi alasannya.
                                            </p>
                                            <div class="rk-appr-acts">
                                                <button type="submit" name="action" value="verify" class="btn btn-success btn-sm">
                                                    <i class="ti ti-check me-1" aria-hidden="true"></i>Verify
                                                </button>
                                                <button type="submit" name="action" value="need_revision" class="btn btn-warning btn-sm">
                                                    <i class="ti ti-arrow-back-up me-1" aria-hidden="true"></i>Butuh Revisi
                                                </button>
                                            </div>
                                        </form>
                                    <?php elseif (! $can_input): ?>
                                        <span class="rk-day-empty">Hanya-baca</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="rk-legend">
                    Selisih = Aktual − ERP; nilai negatif berarti aktual lebih kecil dari ERP.
                    Status proses: Draft → Submitted → Verified, atau Perlu Revisi bila manager mengembalikannya.
                    Indeks hari memuat semua hari kalender, Minggu dan hari libur termasuk, dan
                    semuanya dihitung untuk KPI — jadi isian yang tertunda bisa disusulkan kapan saja.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Panel hari pindah ke dalam daftar pada layar kecil (aksibel) dan
        // kembali ke kolom kanan pada layar lebar. Tanpa JS semua tautan
        // tetap bekerja sebagai GET biasa, jadi halaman tetap utuh.
        var root = document.querySelector('.rk-scope');
        if (!root) {
            return;
        }

        var aside = root.querySelector('.rk-aside');
        var tautan = Array.prototype.slice.call(root.querySelectorAll('.rk-day'));
        var lebar = window.matchMedia('(min-width: 992px)');
        var UBLahir = root.querySelector('.rk-day.is-active');

        function panelHari(hari) {
            return root.querySelector('.rk-panel[data-day="' + hari + '"]');
        }

        function tempatkan(panel, tautanHari) {
            if (lebar.matches) {
                if (panel.parentNode !== aside) {
                    aside.appendChild(panel);
                }
                aside.classList.remove('rk-aside-moved');
            } else {
                if (panel.previousElementSibling !== tautanHari || panel.parentNode !== tautanHari.parentNode) {
                    tautanHari.parentNode.insertBefore(panel, tautanHari.nextSibling);
                }
                aside.classList.add('rk-aside-moved');
            }
        }

        function pilih(hari, perbaruiUrl) {
            tautan.forEach(function(t) {
                var aktif = t.dataset.day === hari;
                var panel = panelHari(t.dataset.day);
                t.classList.toggle('is-active', aktif);
                if (aktif) {
                    t.setAttribute('aria-current', 'true');
                } else {
                    t.removeAttribute('aria-current');
                }
                if (panel) {
                    panel.hidden = !aktif;
                    if (aktif) {
                        tempatkan(panel, t);
                    }
                }
            });
            UBLahir = root.querySelector('.rk-day.is-active');

            if (perbaruiUrl && window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('hari', hari);
                window.history.replaceState(null, '', url.toString());
            }
        }

        tautan.forEach(function(t) {
            t.addEventListener('click', function(e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
                    return;
                }
                e.preventDefault();
                pilih(t.dataset.day, true);
            });
        });

        function susunUlang() {
            if (!UBLahir) {
                return;
            }
            pilih(UBLahir.dataset.day, false);
        }

        if (typeof lebar.addEventListener === 'function') {
            lebar.addEventListener('change', susunUlang);
        } else if (typeof lebar.addListener === 'function') {
            lebar.addListener(susunUlang);
        }

        if (UBLahir) {
            susunUlang();
        }
    });

    // Need Revision tanpa alasan akan ditolak server dengan pesan yang muncul
    // jauh dari tombolnya. Tangkap di sini, di tempatnya, per panel.
    //
    // Logikanya sama dengan yang dulu ada di halaman form harian, tapi
    // diulang untuk SETIAP panel karena sekarang verifikasi inline: satu
    // halaman punya banyak form approve sekaligus.
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[data-rk-appr]').forEach(function(form) {
            var revisi = form.querySelector('[name="catatan_revisi"]');
            if (!revisi) {
                return;
            }

            form.addEventListener('submit', function(e) {
                var aksi = e.submitter && e.submitter.value;
                if (aksi !== 'need_revision') {
                    return;
                }
                if (revisi.value.trim() !== '') {
                    return;
                }

                e.preventDefault();
                revisi.setCustomValidity('Isi alasan revisi dulu, lalu kirim ulang.');
                revisi.reportValidity();
                revisi.focus();
            });
            revisi.addEventListener('input', function() {
                revisi.setCustomValidity('');
            });
        });
    });
</script>
