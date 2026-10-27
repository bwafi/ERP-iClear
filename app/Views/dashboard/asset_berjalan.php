<?php
$namaBulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

// Ditentukan controller (resolveUnitScope) agar konsisten dengan otorisasi
// ?unit= di TutupKasir — termasuk Direktur (2) dengan batasan unit per akun.
$bolehPilihUnit = (bool) ($boleh_pilih_unit ?? in_array($id_jabatan, [1, 0, 2, 34, 40], true));

$bulan = (int) ($bulan ?? date('m'));
$tahun = (int) ($tahun ?? date('Y'));
$periodeLabelId = ($namaBulan[$bulan] ?? '-') . ' ' . $tahun;
$labelSebelum   = ($namaBulan[$bulanSebelum] ?? '-') . ' ' . $tahunSebelum;

// Nilai opsional dari controller (dijaga null agar view tetap aman diuji
// maupun dirender tanpa parameter tren).
$pertumbuhanOmset = $pertumbuhan_omset ?? null;

$namaUnitAktif = '';
$jenisUnitAktif = '';
$isTrialUnitAktif = false;
foreach (($list_unit ?? []) as $u) {
    if ((int) ($u['idunit'] ?? 0) === (int) $selected_unit) {
        $namaUnitAktif = (string) ($u['NAMA_UNIT'] ?? '');
        $jenisUnitAktif = (string) ($u['jenis'] ?? '');
        $isTrialUnitAktif = (int) ($u['is_trial'] ?? 0) === 1;
        break;
    }
}

// ---------------------------------------------------------------
// Kalkulasi nilai metrik (formula bisnis — jangan diubah).
// ---------------------------------------------------------------
$tanggunganAsset = 0;
if ($selected_unit == 4) {
    $tanggunganAsset = (($omset_bulan - $pengeluaran - $totalGajiUnit) * 20 / 100);
} elseif (in_array($selected_unit, [1, 2])) {
    $tanggunganAsset = $hariDalamBulan * 355000;
}

$profitNett = $omset_bulan - $pengeluaran - $totalGajiUnit;

// Bagian gaji HO untuk unit yang sedang dibuka (hanya tersedia bila
// controller mengirim rincian per unit).
$hoUnitShare = null;
foreach (($ho_distribution['per_unit'] ?? []) as $u) {
    if ((int) ($u['idunit'] ?? 0) === (int) $selected_unit) {
        $hoUnitShare = $u;
        break;
    }
}

$hakCabang = 0;
$tagihanCenter = 0;
if (in_array($selected_unit, [3, 4, 5])) {
    $hakCabang = 0.60 * $profitNett;
    $tagihanCenter = 0.40 * $profitNett;
}

// Rasio realisasi biaya terkontrol terhadap batas 6% omset. Batas 0 (belum
// ada omset) dianggap 100% kalau sudah ada biaya, supaya statusnya jujur.
$batasBiayaTerkontrol = 0.06 * ($omset_bulan ?? 0);
$biayaTerkontrol = $pengeluaran ?? 0;
$rasioBiayaTerkontrol = $batasBiayaTerkontrol > 0
    ? ($biayaTerkontrol / $batasBiayaTerkontrol) * 100
    : ($biayaTerkontrol > 0 ? 100 : 0);
$statusBiayaTerkontrol = $rasioBiayaTerkontrol > 100
    ? 'Melebihi batas 6%'
    : ($rasioBiayaTerkontrol >= 80 ? 'Hampir mencapai batas' : 'Aman');
$warnaBiayaTerkontrol = $rasioBiayaTerkontrol > 100
    ? 'danger'
    : ($rasioBiayaTerkontrol >= 80 ? 'warning' : 'success');
?>

<style>
    .metric-figure {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    /* Detail pengeluaran: hero, ringkasan, dan ledger yang lebih rapi. */
    #modalDetailPengeluaran .detail-hero {
        background: var(--bs-warning-bg-subtle);
        border-bottom: 1px solid var(--bs-warning-border-subtle);
        padding: 1rem 1.25rem;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-hero {
        background: var(--bs-secondary-bg-subtle);
        border-bottom-color: var(--bs-secondary-border-subtle);
    }
    #modalDetailPengeluaran .detail-hero-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--bs-warning);
        color: #fff;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-hero-icon {
        background: var(--bs-secondary);
    }
    #modalDetailPengeluaran .detail-summary {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1fr;
        border-bottom: 1px solid var(--bs-border-color);
    }
    #modalDetailPengeluaran .detail-summary > div {
        padding: .85rem 1rem;
        min-width: 0;
    }
    #modalDetailPengeluaran .detail-summary > div + div {
        border-left: 1px solid var(--bs-border-color);
    }
    #modalDetailPengeluaran .modal-body {
        padding-top: 0;
        /* Bar pagination sticky menempel tepat di tepi bawah modal-body; tanpa
           padding bawah, baris tidak menyembul di celah bawah bar. */
        padding-bottom: 0;
    }
    #modalDetailPengeluaran .detail-summary-label,
    #modalDetailPengeluaran .detail-section-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
    }
    #modalDetailPengeluaran .detail-summary-value {
        font-size: clamp(.95rem, 2.2vw, 1.25rem);
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
    }
    #modalDetailPengeluaran .detail-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .32rem .65rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 999px;
        background: var(--bs-body-bg);
        font-size: .78rem;
        color: var(--bs-body-color);
    }
    #modalDetailPengeluaran .detail-chip strong {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }
    /* Chip kategori bisa diklik sebagai filter. Aksen mengikuti warna kartu. */
    #modalDetailPengeluaran button.detail-chip {
        cursor: pointer;
        font-family: inherit;
        transition: border-color .15s ease, background-color .15s ease;
    }
    #modalDetailPengeluaran button.detail-chip:hover {
        border-color: var(--bs-warning);
    }
    #modalDetailPengeluaran[data-warna="secondary"] button.detail-chip:hover {
        border-color: var(--bs-secondary);
    }
    #modalDetailPengeluaran button.detail-chip.is-active {
        border-color: var(--bs-warning);
        background: var(--bs-warning-bg-subtle);
        font-weight: 600;
    }
    #modalDetailPengeluaran[data-warna="secondary"] button.detail-chip.is-active {
        border-color: var(--bs-secondary);
        background: var(--bs-secondary-bg-subtle);
    }
    #modalDetailPengeluaran button.detail-chip:focus-visible {
        outline: 2px solid var(--bs-warning);
        outline-offset: 2px;
    }
    #modalDetailPengeluaran[data-warna="secondary"] button.detail-chip:focus-visible {
        outline-color: var(--bs-secondary);
    }
    #modalDetailPengeluaran .detail-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        background: var(--bs-tertiary-bg);
        border-bottom: 1px solid var(--bs-border-color);
        padding-top: .6rem;
        padding-bottom: .6rem;
    }
    #modalDetailPengeluaran .detail-table tbody td {
        border-color: var(--bs-border-color-translucent);
        vertical-align: middle;
    }

    /* Pagination DataTables: angka & next/prev sebagai kontrol yang jelas,
       aksen mengikuti warna kartu (warning = terkontrol, secondary = kebutuhan).
       Sticky di bawah modal-body supaya tetap terjangkau saat 25 baris di-scroll. */
    #modalDetailPengeluaran .detail-dt-foot {
        position: sticky;
        bottom: 0;
        z-index: 3;
        padding: .9rem .25rem .6rem;
        background: var(--bs-body-bg);
        border-top: 1px solid var(--bs-border-color);
        font-size: .75rem;
        color: var(--bs-secondary-color);
    }
    #modalDetailPengeluaran .detail-dt-foot .dataTables_info {
        padding: 0;
    }
    #modalDetailPengeluaran .detail-dt-foot .dataTables_paginate {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 4px;
    }
    #modalDetailPengeluaran .detail-dt-foot .dataTables_paginate > span {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }
    #modalDetailPengeluaran .detail-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 .6rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 9px;
        background: var(--bs-body-bg);
        color: var(--bs-body-color) !important;
        font-size: .78rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }
    #modalDetailPengeluaran .detail-dt-foot .paginate_button:hover:not(.disabled):not(.current) {
        border-color: var(--bs-warning);
        background: var(--bs-warning-bg-subtle);
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-dt-foot .paginate_button:hover:not(.disabled):not(.current) {
        border-color: var(--bs-secondary);
        background: var(--bs-secondary-bg-subtle);
    }
    #modalDetailPengeluaran .detail-dt-foot .paginate_button.current {
        border-color: var(--bs-warning);
        background: var(--bs-warning);
        color: #000 !important;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-dt-foot .paginate_button.current {
        border-color: var(--bs-secondary);
        background: var(--bs-secondary);
        color: #fff !important;
    }
    #modalDetailPengeluaran .detail-dt-foot .paginate_button.disabled {
        opacity: .45;
        background: transparent;
        color: var(--bs-secondary-color) !important;
        cursor: default;
    }
    #modalDetailPengeluaran .detail-dt-foot .paginate_button:focus-visible {
        outline: 2px solid var(--bs-warning);
        outline-offset: 2px;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-dt-foot .paginate_button:focus-visible {
        outline-color: var(--bs-secondary);
    }
    #modalDetailPengeluaran .detail-dt-foot .ellipsis {
        padding: 0 2px;
        color: var(--bs-secondary-color);
    }
    @media (max-width: 767.98px) {
        /* Di layar sempit, kategori sudah terwakili chip; penerima bisa dibuka di desktop. */
        #modalDetailPengeluaran .detail-table th:nth-child(2),
        #modalDetailPengeluaran .detail-table td:nth-child(2),
        #modalDetailPengeluaran .detail-table th:nth-child(4),
        #modalDetailPengeluaran .detail-table td:nth-child(4) {
            display: none;
        }
        #modalDetailPengeluaran .detail-table th,
        #modalDetailPengeluaran .detail-table td {
            padding-left: .4rem;
            padding-right: .4rem;
        }
        #modalDetailPengeluaran .detail-table {
            table-layout: fixed;
        }
        #modalDetailPengeluaran .detail-table th:first-child {
            width: 96px;
        }
        #modalDetailPengeluaran .detail-table th:last-child {
            width: 116px;
        }
        #modalDetailPengeluaran .detail-table td {
            word-break: break-word;
        }
    }
    @media (max-width: 575.98px) {
        /* Pager di layar sempit: tombol lebih rapat supaya tetap satu-dua baris. */
        #modalDetailPengeluaran .detail-dt-foot .paginate_button {
            min-width: 30px;
            height: 30px;
            padding: 0 .45rem;
            font-size: .72rem;
        }
        #modalDetailPengeluaran .detail-summary {
            grid-template-columns: 1fr 1fr;
        }
        #modalDetailPengeluaran .detail-summary > div:first-child {
            grid-column: 1 / -1;
            border-bottom: 1px solid var(--bs-border-color);
        }
        #modalDetailPengeluaran .detail-summary > div:nth-child(2) {
            border-left: 0;
        }
    }

    /* Lembar posisi: satu baris persamaan + satu strip kewajiban. */
    .posisi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .posisi-cell {
        min-width: 0;
        padding: 1rem 1.25rem;
        border-left: 1px solid var(--bs-border-color);
    }
    .posisi-cell:first-child {
        padding-left: 0;
        border-left: 0;
    }
    .posisi-cell:last-child {
        padding-right: 0;
    }
    .posisi-cell.posisi-hasil {
        padding: 1rem 1.25rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .85rem;
        background: var(--bs-tertiary-bg);
    }
    .posisi-label {
        margin-bottom: .35rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
    }
    .posisi-value-row {
        display: flex;
        align-items: baseline;
        gap: .3rem;
        min-width: 0;
    }
    .posisi-op {
        flex-shrink: 0;
        font-size: 1.2rem;
        font-weight: 700;
        line-height: 1;
        color: var(--bs-secondary-color);
    }
    .posisi-value {
        font-size: clamp(1.1rem, 1.6vw, 1.55rem);
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }
    .posisi-note {
        margin-top: .35rem;
        font-size: .78rem;
        color: var(--bs-secondary-color);
    }
    @media (max-width: 1199.98px) {
        .posisi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .posisi-cell:nth-child(odd) {
            padding-left: 0;
            border-left: 0;
        }
        .posisi-cell:nth-child(even) {
            padding-right: 0;
        }
        .posisi-cell:nth-child(n+3) {
            padding-top: 1rem;
            border-top: 1px solid var(--bs-border-color);
        }
        .posisi-cell.posisi-hasil {
            padding: 1rem;
        }
    }
    @media (max-width: 575.98px) {
        .posisi-grid {
            grid-template-columns: 1fr;
        }
        .posisi-cell {
            padding: 1rem 0 0;
            border-left: 0;
            border-top: 1px solid var(--bs-border-color);
        }
        .posisi-cell:first-child {
            padding-top: 0;
            border-top: 0;
        }
        .posisi-cell.posisi-hasil {
            padding: 1rem;
        }
    }
    .kewajiban-cell {
        padding: 1.25rem;
    }
    @media (min-width: 768px) {
        .kewajiban-cell + .kewajiban-cell {
            border-left: 1px solid var(--bs-border-color);
        }
    }
    @media (max-width: 767.98px) {
        .kewajiban-cell + .kewajiban-cell {
            border-top: 1px solid var(--bs-border-color);
        }
    }
    .ho-table thead th {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        border-bottom: 1px solid var(--bs-border-color);
    }
    .ho-table td {
        vertical-align: top;
    }
    .ho-table tbody tr.ho-row-active > td {
        background: var(--bs-primary-bg-subtle);
    }
    .ho-unit-share {
        margin-top: 1rem;
        padding: 1rem 1.1rem;
        border: 1px solid var(--bs-primary-border-subtle);
        border-radius: .9rem;
        background: var(--bs-primary-bg-subtle);
    }
    .ho-unit-share-label {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-primary-text-emphasis);
    }
    .ho-unit-share-amount {
        margin-top: .15rem;
        font-size: clamp(1.35rem, 2.4vw, 1.8rem);
        font-weight: 700;
        line-height: 1.15;
        color: var(--bs-emphasis-color);
    }
    .ho-unit-share-sub {
        margin-top: .15rem;
        font-size: .8rem;
        color: var(--bs-body-color);
    }
    .ho-unit-share-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
        margin-top: .85rem;
        padding-top: .85rem;
        border-top: 1px solid var(--bs-primary-border-subtle);
    }
    .ho-unit-share-fact span {
        display: block;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-primary-text-emphasis);
    }
    .ho-unit-share-fact strong {
        font-size: .9rem;
        font-weight: 700;
        color: var(--bs-body-color);
    }
    .ho-unit-share-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: .85rem;
    }
    .ho-unit-share-note {
        font-size: .78rem;
        color: var(--bs-body-color);
    }
    @media (max-width: 767.98px) {
        .ho-unit-share-grid {
            grid-template-columns: 1fr;
            gap: .5rem;
        }
        .ho-unit-share-foot .btn {
            width: 100%;
            justify-content: center;
        }
    }

    <?php if (isset($gaji_detail)): ?>
    /* Rincian gaji: register payroll di dalam modal, bukan tabel admin polos. */
    #modalRincianGaji .gaji-hero {
        background: var(--bs-danger-bg-subtle);
        border-bottom: 1px solid var(--bs-danger-border-subtle);
    }
    #modalRincianGaji .gaji-hero-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-danger-border-subtle);
        color: var(--bs-danger-text-emphasis);
    }
    #modalRincianGaji .gaji-hero-meta {
        color: var(--bs-emphasis-color);
    }
    #modalRincianGaji .gaji-summary {
        display: grid;
        grid-template-columns: 1.25fr 1fr 1fr 1fr;
        border-bottom: 1px solid var(--bs-border-color);
    }
    #modalRincianGaji .gaji-summary > div {
        padding: .85rem 1rem;
        min-width: 0;
    }
    #modalRincianGaji .gaji-summary > div + div {
        border-left: 1px solid var(--bs-border-color);
    }
    #modalRincianGaji .gaji-summary-label,
    #modalRincianGaji .gaji-section-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
    }
    #modalRincianGaji .gaji-summary-value {
        font-size: clamp(.95rem, 2.2vw, 1.25rem);
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
    }
    #modalRincianGaji .gaji-summary-value.is-total {
        color: var(--bs-danger-text-emphasis);
    }
    #modalRincianGaji .gaji-table {
        margin-bottom: 0;
    }
    #modalRincianGaji .gaji-table thead th {
        padding: .6rem .5rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
        color: var(--bs-secondary-color);
        border-bottom: 1px solid var(--bs-border-color);
    }
    #modalRincianGaji .gaji-table tbody td {
        padding: .7rem .5rem;
        vertical-align: middle;
        border-color: var(--bs-border-color);
    }
    #modalRincianGaji .gaji-table tbody tr:hover {
        background: var(--bs-tertiary-bg);
    }
    #modalRincianGaji .gaji-person-name {
        font-weight: 600;
        color: var(--bs-body-color);
    }
    #modalRincianGaji .gaji-person-role {
        margin-top: .1rem;
        font-size: .75rem;
        color: var(--bs-secondary-color);
    }
    #modalRincianGaji .gaji-amount {
        font-variant-numeric: tabular-nums;
        letter-spacing: -.01em;
        white-space: nowrap;
    }
    #modalRincianGaji .gaji-table tbody td.gaji-total {
        font-weight: 700;
        color: var(--bs-danger-text-emphasis);
        border-left: 1px solid var(--bs-border-color);
    }
    #modalRincianGaji .gaji-table tfoot td {
        padding: .8rem .5rem;
        background: var(--bs-tertiary-bg);
        border-top: 1px solid var(--bs-border-color);
    }
    #modalRincianGaji .gaji-grand-total {
        font-weight: 700;
        color: var(--bs-danger-text-emphasis);
    }
    #modalRincianGaji .gaji-note {
        font-size: .78rem;
        color: var(--bs-secondary-color);
    }
    #modalRincianGaji .gaji-empty {
        padding: 2.5rem 1rem;
        text-align: center;
    }
    #modalRincianGaji .gaji-empty-icon {
        color: var(--bs-secondary-color);
    }
    @media (max-width: 767.98px) {
        #modalRincianGaji .gaji-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        #modalRincianGaji .gaji-summary > div:nth-child(odd) {
            border-left: 0;
        }
        #modalRincianGaji .gaji-summary > div:nth-child(n+3) {
            border-top: 1px solid var(--bs-border-color);
        }
        #modalRincianGaji .gaji-table thead {
            display: none;
        }
        #modalRincianGaji .gaji-table,
        #modalRincianGaji .gaji-table tbody,
        #modalRincianGaji .gaji-table tfoot,
        #modalRincianGaji .gaji-table tr,
        #modalRincianGaji .gaji-table td {
            display: block;
            width: 100%;
        }
        #modalRincianGaji .gaji-table tbody tr {
            margin-bottom: .65rem;
            padding: .85rem;
            border: 1px solid var(--bs-border-color);
            border-radius: .8rem;
            background: var(--bs-body-bg);
        }
        #modalRincianGaji .gaji-table tbody td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: .28rem 0;
            border: 0;
            text-align: right;
        }
        #modalRincianGaji .gaji-table tbody td::before {
            content: attr(data-label);
            font-size: .78rem;
            font-weight: 600;
            color: var(--bs-secondary-color);
        }
        #modalRincianGaji .gaji-table tbody td:first-child {
            display: block;
            margin-bottom: .35rem;
            padding-bottom: .55rem;
            border-bottom: 1px solid var(--bs-border-color);
            text-align: left;
        }
        #modalRincianGaji .gaji-table tbody td:first-child::before {
            display: none;
        }
        #modalRincianGaji .gaji-table tbody td.gaji-total {
            margin-top: .35rem;
            padding-top: .55rem;
            border-top: 1px solid var(--bs-border-color);
            border-left: 0;
        }
        #modalRincianGaji .gaji-table tfoot tr {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: .85rem;
            border: 1px solid var(--bs-border-color);
            border-radius: .8rem;
            background: var(--bs-tertiary-bg);
        }
        #modalRincianGaji .gaji-table tfoot td {
            padding: 0;
            background: transparent;
            border: 0;
        }
        #modalRincianGaji .gaji-table tfoot td[colspan] {
            text-align: left;
        }
    }
    @media (max-width: 575.98px) {
        #modalRincianGaji .gaji-summary {
            grid-template-columns: 1fr;
        }
        #modalRincianGaji .gaji-summary > div + div {
            border-left: 0;
            border-top: 1px solid var(--bs-border-color);
        }
    }
    <?php endif; ?>
</style>

<!-- Lembar posisi unit: header, persamaan, kewajiban -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0 fs-2">
                        <li class="breadcrumb-item">
                            <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item text-primary fw-medium active" aria-current="page">Asset Berjalan</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h4 class="fw-bold mb-0 text-dark">
                        <?= esc($namaUnitAktif !== '' ? $namaUnitAktif : ('Unit ' . $selected_unit)) ?>
                    </h4>
                    <?php if ($jenisUnitAktif !== ''): ?>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2"><?= esc(ucfirst($jenisUnitAktif)) ?></span>
                    <?php endif; ?>
                    <?php if ($isTrialUnitAktif): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3 py-2">Trial</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Ringkasan omset, biaya, dan pembagian hasil unit</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                    class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3"
                    title="<?= esc($labelSebelum) ?>">
                    <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary"></iconify-icon>
                    <span class="d-none d-lg-inline"><?= esc($labelSebelum) ?></span>
                    <span class="d-lg-none">Sebelumnya</span>
                </a>

                <span class="badge bg-primary-subtle text-primary-emphasis fs-3 fw-bold px-3 py-2 rounded-pill">
                    <?= esc($periodeLabelId) ?>
                </span>

                <?php if (!$isBulanBerjalan): ?>
                    <?php $bulanSesudah = (int) date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                    <?php $tahunSesudah = (int) date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                    <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                        class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3"
                        title="<?= esc(($namaBulan[$bulanSesudah] ?? '-') . ' ' . $tahunSesudah) ?>">
                        <span class="d-none d-lg-inline"><?= esc(($namaBulan[$bulanSesudah] ?? '-') . ' ' . $tahunSesudah) ?></span>
                        <span class="d-lg-none">Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone" class="text-primary"></iconify-icon>
                    </a>
                <?php else: ?>
                    <span class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3 text-muted opacity-50 disabled" title="Bulan berikutnya belum tersedia">
                        <span>Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($bolehPilihUnit): ?>
            <form method="GET" class="row g-2 align-items-end mt-3 pt-3 border-top">
                <div class="col-12 col-md-4">
                    <label for="filterUnit" class="form-label fs-2 fw-semibold text-muted mb-1">Unit cabang</label>
                    <select id="filterUnit" name="unit" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                <?= esc($u['NAMA_UNIT']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="filterBulan" class="form-label fs-2 fw-semibold text-muted mb-1">Bulan</label>
                    <select id="filterBulan" name="bulan" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= esc($namaBulan[$i]) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="filterTahun" class="form-label fs-2 fw-semibold text-muted mb-1">Tahun</label>
                    <select id="filterTahun" name="tahun" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = (int) date('Y'); $i >= (int) date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-1">
                        <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        Tampilkan
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex align-items-end justify-content-between mb-2">
    <div>
        <h5 class="fw-bold text-dark mb-0">Posisi unit</h5>
        <small class="text-muted">Omset dikurangi biaya terkontrol dan gaji bulan ini</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="posisi-grid" role="group" aria-label="Rumus laba bersih: omset dikurangi biaya terkontrol dikurangi total gaji">
            <!-- Omset -->
            <div class="posisi-cell">
                <h6 class="posisi-label">Omset Bulan Ini</h6>
                <div class="posisi-value-row">
                    <span class="posisi-value metric-figure text-success">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="posisi-note">
                    vs <?= esc($labelSebelum) ?>
                    <?php if ($pertumbuhanOmset !== null): ?>
                        &middot;
                        <?php if ($pertumbuhanOmset > 0): ?>
                            <span class="text-success fw-semibold"><iconify-icon icon="solar:arrow-up-bold-duotone" class="align-middle"></iconify-icon> +<?= number_format((float) $pertumbuhanOmset, 1, ',', '.') ?>%</span>
                        <?php elseif ($pertumbuhanOmset < 0): ?>
                            <span class="text-danger fw-semibold"><iconify-icon icon="solar:arrow-down-bold-duotone" class="align-middle"></iconify-icon> <?= number_format((float) $pertumbuhanOmset, 1, ',', '.') ?>%</span>
                        <?php else: ?>
                            <span class="text-muted">&plusmn;0%</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Biaya terkontrol (yang dikurangkan dari laba) -->
            <div class="posisi-cell">
                <h6 class="posisi-label">Biaya Terkontrol (Maks 6%)</h6>
                <div class="posisi-value-row">
                    <span class="posisi-op" aria-hidden="true">&minus;</span>
                    <span class="posisi-value metric-figure text-warning-emphasis">Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="progress mt-2" style="height: 6px;" role="progressbar"
                    aria-label="Rasio biaya terkontrol terhadap batas 6%"
                    aria-valuenow="<?= (int) round(min(100, $rasioBiayaTerkontrol)) ?>"
                    aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-<?= $warnaBiayaTerkontrol ?>" style="width: <?= number_format(min(100, $rasioBiayaTerkontrol), 1, '.', '') ?>%"></div>
                </div>
                <div class="posisi-note">
                    <span class="text-<?= $warnaBiayaTerkontrol ?> fw-semibold">
                        <?= number_format($rasioBiayaTerkontrol, 1, ',', '.') ?>% dari batas — <?= $statusBiayaTerkontrol ?>
                    </span>
                </div>
                <button type="button"
                    class="btn btn-sm btn-outline-warning fw-semibold mt-2 d-inline-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalDetailPengeluaran"
                    data-kelompok="terkontrol" data-judul="Biaya Terkontrol (Maks 6%)"
                    data-warna="warning" data-icon="solar:card-send-bold-duotone"
                    aria-label="Detail biaya terkontrol 6%">
                    <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                    Detail
                </button>

                <!-- Biaya di luar 6%: informasi, tidak dikurangkan di rumus laba -->
                <div class="mt-3 pt-3 border-top">
                    <h6 class="posisi-label mb-1">Biaya Kebutuhan (Di Luar 6%)</h6>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span class="fs-2 fw-semibold metric-figure text-secondary-emphasis">Rp <?= number_format($pengeluaran_full ?? 0, 0, ',', '.') ?></span>
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary fw-semibold d-inline-flex align-items-center gap-1"
                            data-bs-toggle="modal" data-bs-target="#modalDetailPengeluaran"
                            data-kelompok="kebutuhan" data-judul="Biaya Kebutuhan (Di Luar 6%)"
                            data-warna="secondary" data-icon="solar:wallet-money-bold-duotone"
                            aria-label="Detail biaya kebutuhan di luar 6%">
                            <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                            Detail
                        </button>
                    </div>
                    <div class="fs-2 text-muted mt-1">Ditampilkan terpisah, tidak dikurangkan di laba bersih.</div>
                </div>
            </div>

            <!-- Total gaji unit -->
            <div class="posisi-cell">
                <h6 class="posisi-label">Total Gaji</h6>
                <div class="posisi-value-row">
                    <span class="posisi-op" aria-hidden="true">&minus;</span>
                    <span class="posisi-value metric-figure text-danger">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></span>
                </div>
                <div class="posisi-note">Beban gaji karyawan cabang</div>
                <?php if (isset($gaji_detail)): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger fw-semibold mt-2 d-inline-flex align-items-center gap-1"
                            data-bs-toggle="modal" data-bs-target="#modalRincianGaji">
                        <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                        Lihat rincian
                    </button>
                <?php endif; ?>
            </div>

            <!-- Hasil -->
            <div class="posisi-cell posisi-hasil">
                <h6 class="posisi-label">Laba Bersih</h6>
                <div class="posisi-value-row">
                    <span class="posisi-op" aria-hidden="true">=</span>
                    <span class="posisi-value metric-figure <?= $profitNett >= 0 ? 'text-success' : 'text-danger' ?>">Rp <?= number_format($profitNett, 0, ',', '.') ?></span>
                </div>
                <div class="posisi-note">Dasar Hak Cabang &amp; Tagihan Center</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-end justify-content-between mb-2">
    <div>
        <h5 class="fw-bold text-dark mb-0">Kewajiban &amp; bagi hasil</h5>
        <small class="text-muted">Dihitung dari laba bersih periode ini</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="row g-0">
        <!-- Total Tanggungan Asset -->
        <div class="col-md-4 kewajiban-cell">
            <div class="d-flex align-items-center gap-2 mb-1">
                <iconify-icon icon="solar:vault-bold-duotone" class="fs-5 text-primary"></iconify-icon>
                <h6 class="text-uppercase fs-2 text-primary fw-bold mb-0">Total Tanggungan Asset</h6>
            </div>
            <div class="fs-4 fw-bold text-primary metric-figure">Rp <?= number_format($tanggunganAsset, 0, ',', '.') ?></div>
            <small class="text-muted">Alokasi tanggungan asset berjalan</small>
        </div>

        <!-- Hak Cabang -->
        <div class="col-md-4 kewajiban-cell">
            <div class="d-flex align-items-center gap-2 mb-1">
                <iconify-icon icon="solar:shop-2-bold-duotone" class="fs-5 text-info-emphasis"></iconify-icon>
                <h6 class="text-uppercase fs-2 text-info-emphasis fw-bold mb-0">Hak Cabang</h6>
            </div>
            <div class="fs-4 fw-bold text-info-emphasis metric-figure">Rp <?= number_format($hakCabang, 0, ',', '.') ?></div>
            <small class="text-muted">Bagian 60% untuk pengelola cabang</small>
        </div>

        <!-- Tagihan Center -->
        <div class="col-md-4 kewajiban-cell">
            <div class="d-flex align-items-center gap-2 mb-1">
                <iconify-icon icon="solar:buildings-3-bold-duotone" class="fs-5 text-primary"></iconify-icon>
                <h6 class="text-uppercase fs-2 text-primary fw-bold mb-0">Tagihan Center</h6>
            </div>
            <div class="fs-4 fw-bold text-primary metric-figure">Rp <?= number_format($tagihanCenter, 0, ',', '.') ?></div>
            <small class="text-muted">Kewajiban penyetoran 40% ke Head Office</small>
        </div>
    </div>
</div>

<?php if (!empty($ho_distribution) && $ho_distribution['divisor'] > 0): ?>
    <!-- Beban Head Office: ringkas, rincian per unit bisa dibuka -->
    <div class="d-flex align-items-end justify-content-between mb-2">
        <div>
            <h5 class="fw-bold text-dark mb-0">Beban Head Office</h5>
            <small class="text-muted">Pembagian gaji HO ke unit, terpisah dari gaji cabang</small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div style="max-width: 62ch;">
                    <p class="fs-2 text-muted mb-0">
                        Pool non-SPV dibagi <strong class="text-dark"><?= (int) $ho_distribution['divisor'] ?> bagian</strong>
                        (<?= (int) $ho_distribution['pribadi_count'] ?> pribadi + <?= (int) $ho_distribution['franchise_count'] ?> franchise non-trial).
                        Gaji SPV hanya dibebankan ke unit wilayahnya yang non-trial.
                    </p>
                </div>
                <div class="text-md-end">
                    <div class="fs-2 text-uppercase fw-semibold text-muted">Total gaji HO &middot; perkiraan</div>
                    <div class="posisi-value metric-figure text-dark">Rp <?= number_format($ho_distribution['total_perkiraan'] ?? 0, 0, ',', '.') ?></div>
                    <div class="fs-2 text-muted mt-1">
                        Dipotong payroll: <span class="fw-semibold text-success metric-figure">Rp <?= number_format($ho_distribution['total_dipotong'] ?? 0, 0, ',', '.') ?></span>
                        &middot; <?= count($ho_distribution['employees'] ?? []) ?> karyawan
                    </div>
                </div>
            </div>

            <?php if (!empty($ho_distribution['per_unit'])): ?>
                <?php if ($hoUnitShare !== null): ?>
                    <div class="ho-unit-share">
                        <div class="ho-unit-share-label">Bagian gaji HO unit ini</div>
                        <div class="ho-unit-share-amount metric-figure" id="hoUnitShareTotal">
                            Rp <?= number_format($hoUnitShare['total_perkiraan'], 0, ',', '.') ?>
                        </div>
                        <div class="ho-unit-share-sub">
                            Perkiraan beban gaji HO untuk <?= esc($namaUnitAktif ?: ('Unit ' . $selected_unit)) ?>
                        </div>
                        <div class="ho-unit-share-grid">
                            <div class="ho-unit-share-fact">
                                <span>Non-SPV</span>
                                <strong class="metric-figure" id="hoUnitShareNonSpv">Rp <?= number_format($hoUnitShare['non_spv_perkiraan'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="ho-unit-share-fact">
                                <span>SPV wilayah</span>
                                <strong class="metric-figure" id="hoUnitShareSpv">Rp <?= number_format($hoUnitShare['spv_perkiraan'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="ho-unit-share-fact">
                                <span>Sudah dipotong payroll</span>
                                <strong class="metric-figure" id="hoUnitShareDipotong">Rp <?= number_format($hoUnitShare['total_dipotong'], 0, ',', '.') ?></strong>
                            </div>
                        </div>
                        <div class="ho-unit-share-foot">
                            <span class="ho-unit-share-note">
                                <iconify-icon icon="solar:info-circle-bold-duotone" class="align-middle"></iconify-icon>
                                Angka ini belum masuk ke Total Gaji dan Laba Bersih unit di halaman ini.
                            </span>
                            <button type="button" class="btn btn-sm btn-light border fw-semibold d-inline-flex align-items-center gap-1"
                                data-bs-toggle="collapse" data-bs-target="#hoRincianUnit" aria-expanded="false" aria-controls="hoRincianUnit">
                                <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                                Lihat rincian per unit
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
                        <div class="fs-2 text-muted">Unit ini tidak termasuk pembagian gaji HO.</div>
                        <button type="button" class="btn btn-sm btn-light border fw-semibold d-inline-flex align-items-center gap-1"
                            data-bs-toggle="collapse" data-bs-target="#hoRincianUnit" aria-expanded="false" aria-controls="hoRincianUnit">
                            <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                            Lihat rincian per unit
                        </button>
                    </div>
                <?php endif; ?>

                <div class="collapse mt-3" id="hoRincianUnit">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 ho-table">
                            <thead>
                                <tr>
                                    <th scope="col">Unit</th>
                                    <th scope="col" class="text-end">Non-SPV</th>
                                    <th scope="col" class="text-end">SPV Wilayah</th>
                                    <th scope="col" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ho_distribution['per_unit'] as $u): ?>
                                    <tr class="<?= (int) $u['idunit'] === (int) $selected_unit ? 'ho-row-active' : '' ?>">
                                        <td>
                                            <div class="fw-semibold text-dark"><?= esc($u['nama']) ?></div>
                                            <div class="fs-2 text-muted d-flex flex-wrap align-items-center gap-1 mt-1">
                                                <?php if ((int) $u['idunit'] === (int) $selected_unit): ?>
                                                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">Unit ini</span>
                                                <?php endif; ?>
                                                <span class="badge bg-<?= $u['jenis'] === 'pribadi' ? 'info-subtle text-info-emphasis' : ($u['jenis'] === 'franchise' ? 'warning-subtle text-warning-emphasis' : 'secondary-subtle text-secondary-emphasis') ?>">
                                                    <?= esc($u['jenis'] ?: '-') ?>
                                                </span>
                                                <?php if (!empty($u['is_trial'])): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Trial</span>
                                                <?php endif; ?>
                                                <?php if (!empty($u['catatan']) && $u['catatan'] !== '-'): ?>
                                                    <span><?= esc($u['catatan']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="metric-figure fw-semibold">Rp <?= number_format($u['non_spv_perkiraan'], 0, ',', '.') ?></div>
                                            <div class="fs-2 text-muted metric-figure">dipotong Rp <?= number_format($u['non_spv_dipotong'], 0, ',', '.') ?></div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="metric-figure fw-semibold">Rp <?= number_format($u['spv_perkiraan'], 0, ',', '.') ?></div>
                                            <div class="fs-2 text-muted metric-figure">dipotong Rp <?= number_format($u['spv_dipotong'], 0, ',', '.') ?></div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="metric-figure fw-bold text-dark">Rp <?= number_format($u['total_perkiraan'], 0, ',', '.') ?></div>
                                            <div class="fs-2 text-muted metric-figure">dipotong Rp <?= number_format($u['total_dipotong'], 0, ',', '.') ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Formula & Context Note -->
<div class="alert alert-light border mb-0 d-flex align-items-start gap-2 py-3 px-3 text-muted fs-2">
    <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-5 text-primary flex-shrink-0"></iconify-icon>
    <span>
        <strong class="text-dark">Total Tanggungan Asset</strong> — unit 4 = 20% laba; unit 1 &amp; 2 = jumlah hari bulan ini &times; Rp 355.000; unit franchise (3 &amp; 5) = Rp 0.
        <strong class="text-dark">Hak Cabang (60%)</strong> dan <strong class="text-dark">Tagihan Center (40%)</strong> dihitung dari laba bersih (Omset &minus; Biaya Terkontrol &minus; Total Gaji) unit 3, 4, &amp; 5.
        <br>
        <strong class="text-dark">Batas 6%</strong> hanya peringatan — transaksi tetap tercatat dan tidak dibatalkan.
    </span>
</div>

<!-- Detail Pengeluaran: data baru diambil saat tombol Detail diklik -->
<div class="modal fade" id="modalDetailPengeluaran" tabindex="-1" aria-labelledby="detailPengeluaranTitle" aria-hidden="true"
    data-url="<?= base_url('asset_berjalan/detail-pengeluaran') ?>"
    data-unit="<?= (int) $selected_unit ?>"
    data-bulan="<?= (int) $bulan ?>"
    data-tahun="<?= (int) $tahun ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header detail-hero align-items-start gap-3">
                <div class="detail-hero-icon">
                    <iconify-icon id="detailPengeluaranIcon" icon="solar:card-send-bold-duotone" width="24" height="24"></iconify-icon>
                </div>
                <div class="flex-grow-1">
                    <h5 class="modal-title mb-1" id="detailPengeluaranTitle">Detail Pengeluaran</h5>
                    <small class="text-muted">
                        <?= esc($namaUnitAktif ?: ('Unit ' . $selected_unit)) ?> &middot; <?= esc($periodeLabelId) ?>
                    </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="detail-summary" id="detailPengeluaranSummary">
                <div>
                    <div class="detail-summary-label">Total pengeluaran</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranTotal">Rp 0</div>
                </div>
                <div>
                    <div class="detail-summary-label">Transaksi</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranCount">0</div>
                </div>
                <div>
                    <div class="detail-summary-label">Kategori</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranKategoriCount">0</div>
                </div>
            </div>

            <div class="modal-body">
                <div id="detailPengeluaranError" class="alert alert-danger d-none mb-0 mt-3" role="alert">
                    <span id="detailPengeluaranErrorText">Gagal memuat detail.</span>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="detailPengeluaranRetry">Coba lagi</button>
                </div>
                <div id="detailPengeluaranTableWrap" class="d-none">
                    <div class="detail-section-label mb-2 mt-3">Rincian kategori</div>
                    <div id="detailPengeluaranKategori" class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter kategori"></div>
                    <input type="search" id="detailPengeluaranSearch" class="form-control form-control-sm mb-2"
                        placeholder="Cari deskripsi, penerima, atau kategori…" aria-label="Cari transaksi">
                    <div>
                        <table class="table table-hover align-middle mb-0 detail-table" id="detailPengeluaranTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Penerima</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody id="detailPengeluaranRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <span class="text-muted fs-2">Sumber: Kas Keluar</span>
                <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalDetailPengeluaran');
    if (!modal) return;

    const url = modal.dataset.url;
    const el = (id) => document.getElementById(id);
    const errorBox = el('detailPengeluaranError');
    const errorText = el('detailPengeluaranErrorText');
    const wrap = el('detailPengeluaranTableWrap');
    const searchInput = el('detailPengeluaranSearch');
    const kategoriWrap = el('detailPengeluaranKategori');
    const totalEl = el('detailPengeluaranTotal');
    const countEl = el('detailPengeluaranCount');
    const kategoriCountEl = el('detailPengeluaranKategoriCount');
    const rupiah = new Intl.NumberFormat('id-ID');

    let table = null;
    let aktif = null;
    let kategoriAktif = null;

    const tanggal = (v) => {
        const s = String(v || '').slice(0, 10).split('-');
        return s.length === 3 ? s[2] + '/' + s[1] + '/' + s[0] : (v || '-');
    };
    const escHtml = (v) => String(v == null ? '' : v)
        .replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    const tampilError = (teks) => {
        errorBox.classList.toggle('d-none', !teks);
        if (teks) errorText.textContent = teks;
    };

    const resetRingkasan = (teks) => {
        totalEl.textContent = teks;
        countEl.textContent = teks;
        kategoriCountEl.textContent = teks;
        kategoriWrap.textContent = '';
    };

    // Chip kategori: bisa diklik untuk memfilter tabel (single pilih).
    // id null = "Semua" (reset); tanpa id = kategori tak terfilter (mis. tanpa kategori).
    const buatChip = (label, { id = null, aktif = false, jumlah = null, dapatDiklik = true } = {}) => {
        const chip = document.createElement(dapatDiklik ? 'button' : 'span');
        if (dapatDiklik) {
            chip.type = 'button';
            chip.dataset.id = id == null ? '' : String(id);
            chip.setAttribute('aria-pressed', aktif ? 'true' : 'false');
        }
        chip.className = 'detail-chip' + (aktif ? ' is-active' : '');
        const teks = document.createElement('span');
        teks.textContent = label;
        chip.appendChild(teks);
        if (jumlah != null) {
            const value = document.createElement('strong');
            value.textContent = 'Rp ' + rupiah.format(jumlah);
            chip.appendChild(value);
        }
        return chip;
    };

    // Ringkasan dihitung server dari seluruh hasil filter, bukan dari baris
    // halaman ini saja — jadi angkanya tidak berubah saat pindah halaman.
    const isiRingkasan = (json) => {
        totalEl.textContent = 'Rp ' + rupiah.format(json.total || 0);
        countEl.textContent = json.recordsFiltered || 0;

        const kategori = json.kategori || [];
        kategoriCountEl.textContent = kategori.length;
        kategoriWrap.textContent = '';
        // Chip tetap lengkap (semua kategori kelompok) walau tabel difilter,
        // supaya user bisa ganti kategori tanpa reset dulu.
        kategoriWrap.appendChild(buatChip('Semua', { aktif: kategoriAktif === null }));
        kategori.forEach((k) => {
            const id = parseInt(k.id, 10) || 0;
            kategoriWrap.appendChild(buatChip(k.nama, {
                id,
                aktif: id > 0 && kategoriAktif === id,
                jumlah: k.jumlah || 0,
                dapatDiklik: id > 0,
            }));
        });
    };

    // DataTables server-side: modal hanya memuat satu halaman baris, bukan
    // seluruh pengeluaran, sehingga tetap ringan walau transaksinya banyak.
    const buatTabel = () => {
        table = $('#detailPengeluaranTable').DataTable({
            serverSide: true,
            processing: true,
            // Modal masih tersembunyi saat init; biarkan CSS .detail-table yang
            // mengatur lebar, bukan autoWidth yang menghitung dari container 0.
            autoWidth: false,
            pageLength: 25,
            lengthChange: false,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [2] }],
            dom: 'rt<"detail-dt-foot d-flex flex-wrap justify-content-between align-items-center gap-2"ip>',
            ajax: {
                url: url,
                data: (d) => {
                    d.unit = modal.dataset.unit;
                    d.bulan = modal.dataset.bulan;
                    d.tahun = modal.dataset.tahun;
                    d.kelompok = aktif;
                    d.kategori = kategoriAktif == null ? '' : kategoriAktif;
                },
                dataSrc: (json) => {
                    tampilError('');
                    isiRingkasan(json);
                    return json.data;
                },
                error: (xhr) => {
                    resetRingkasan('—');
                    tampilError(xhr && xhr.status === 403
                        ? 'Anda tidak berhak melihat unit ini.'
                        : (xhr && xhr.status === 401
                            ? 'Sesi berakhir. Muat ulang halaman.'
                            : 'Gagal memuat detail. Periksa koneksi lalu coba lagi.'));
                }
            },
            columns: [
                { data: 'tanggal', render: (d) => tanggal(d), className: 'text-nowrap text-secondary' },
                {
                    data: 'nama_kategori',
                    render: (d) => '<span class="badge rounded-pill bg-light text-dark border fw-medium">'
                        + escHtml(d || 'Tanpa kategori') + '</span>'
                },
                { data: 'deskripsi', render: (d) => escHtml(d || '-') },
                { data: 'penerima', render: (d) => escHtml(d || '-'), className: 'text-muted' },
                { data: 'jumlah', render: (d) => 'Rp ' + rupiah.format(d || 0), className: 'text-end fw-semibold metric-figure text-nowrap' }
            ],
            language: {
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ transaksi',
                infoEmpty: 'Tidak ada transaksi',
                infoFiltered: '',
                zeroRecords: 'Tidak ada pengeluaran.',
                emptyTable: 'Tidak ada pengeluaran.',
                processing: 'Memuat…',
                paginate: { previous: '‹ Sebelumnya', next: 'Berikutnya ›' }
            }
        });
        return table;
    };

    modal.addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        if (!btn || !btn.dataset.kelompok) return;

        el('detailPengeluaranTitle').textContent = btn.dataset.judul || 'Detail Pengeluaran';
        modal.dataset.warna = btn.dataset.warna || 'warning';
        el('detailPengeluaranIcon').setAttribute('icon', btn.dataset.icon || 'solar:list-bold-duotone');

        wrap.classList.remove('d-none');
        tampilError('');
        aktif = btn.dataset.kelompok;
        // Ganti kartu = mulai dari filter bersih (semua kategori).
        kategoriAktif = null;
        if (searchInput) searchInput.value = '';

        if (!table) {
            resetRingkasan('…');
            buatTabel();
        } else {
            resetRingkasan('…');
            table.search('').ajax.reload();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (table) table.search(searchInput.value).draw();
        });
    }

    // Klik chip = saring tabel ke kategori itu; klik chip aktif lagi atau
    // "Semua" = tampil semua. Server-side: reload dari halaman 1.
    kategoriWrap.addEventListener('click', (event) => {
        const btn = event.target.closest('button.detail-chip');
        if (!btn || !kategoriWrap.contains(btn)) return;

        const id = btn.dataset.id ? parseInt(btn.dataset.id, 10) : null;
        const baru = (id && kategoriAktif === id) ? null : id;
        if (baru === kategoriAktif) return;

        kategoriAktif = baru;
        if (table) table.ajax.reload(null, true);
    });

    el('detailPengeluaranRetry').addEventListener('click', () => {
        tampilError('');
        if (table) table.ajax.reload();
    });
})();
</script>

<?php if (isset($gaji_detail)): ?>
<?php
$gajiRows = $gaji_detail ?? [];
$gajiPokokTotal = 0.0;
$gajiTunjanganTotal = 0.0;
$gajiTambahanTotal = 0.0;
foreach ($gajiRows as $gajiRow) {
    $gajiPokokTotal += (float) ($gajiRow['gaji_pokok'] ?? 0);
    $gajiTunjanganTotal += (float) ($gajiRow['tunjangan_kinerja'] ?? 0)
        + (float) ($gajiRow['tunjangan_absen'] ?? 0)
        + (float) ($gajiRow['tunjangan_penempatan'] ?? 0);
    $gajiTambahanTotal += (float) ($gajiRow['insentif'] ?? 0)
        + (float) ($gajiRow['lembur'] ?? 0);
}
?>
<!-- Rincian Total Gaji: register payroll unit, terpisah dari distribusi gaji HO. -->
<div class="modal fade" id="modalRincianGaji" tabindex="-1" aria-labelledby="gajiRincianTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header gaji-hero align-items-start gap-3">
                <div class="gaji-hero-icon">
                    <iconify-icon icon="solar:wallet-money-bold-duotone" width="24" height="24"></iconify-icon>
                </div>
                <div class="flex-grow-1">
                    <h5 class="modal-title mb-1" id="gajiRincianTitle">Rincian Total Gaji</h5>
                    <small class="gaji-hero-meta">
                        <?= esc($namaUnitAktif ?: ('Unit ' . $selected_unit)) ?> &middot; <?= esc($periodeLabelId) ?>
                    </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <?php if ($gajiRows !== []): ?>
                <div class="gaji-summary">
                    <div>
                        <div class="gaji-summary-label">Total gaji unit</div>
                        <div class="gaji-summary-value metric-figure is-total" id="gajiSummaryTotal">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></div>
                    </div>
                    <div>
                        <div class="gaji-summary-label">Gaji pokok</div>
                        <div class="gaji-summary-value metric-figure" id="gajiSummaryPokok">Rp <?= number_format($gajiPokokTotal, 0, ',', '.') ?></div>
                    </div>
                    <div>
                        <div class="gaji-summary-label">Tunjangan</div>
                        <div class="gaji-summary-value metric-figure" id="gajiSummaryTunjangan">Rp <?= number_format($gajiTunjanganTotal, 0, ',', '.') ?></div>
                    </div>
                    <div>
                        <div class="gaji-summary-label">Insentif &amp; lembur</div>
                        <div class="gaji-summary-value metric-figure" id="gajiSummaryTambahan">Rp <?= number_format($gajiTambahanTotal, 0, ',', '.') ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="modal-body pt-0">
                <?php if ($gajiRows === []): ?>
                    <div class="gaji-empty">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" width="32" height="32" class="gaji-empty-icon"></iconify-icon>
                        <div class="fw-semibold text-body mt-2">Belum ada rincian gaji</div>
                        <div class="gaji-note">Tidak ada data gaji unit pada periode ini.</div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-1 px-1 pt-3 pb-1">
                        <span class="gaji-section-label">Rincian per karyawan</span>
                        <span class="gaji-note"><?= count($gajiRows) ?> karyawan &middot; belum termasuk distribusi gaji HO</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle gaji-table">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th class="text-end">Gaji pokok</th>
                                    <th class="text-end">T. kinerja</th>
                                    <th class="text-end">T. absen</th>
                                    <th class="text-end">T. penempatan</th>
                                    <th class="text-end">Insentif</th>
                                    <th class="text-end">Lembur</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gajiRows as $d): ?>
                                    <tr>
                                        <td data-label="Karyawan">
                                            <div class="gaji-person-name"><?= esc($d['nama']) ?></div>
                                            <div class="gaji-person-role"><?= esc($d['jabatan'] ?? '') ?></div>
                                        </td>
                                        <td data-label="Gaji pokok" class="text-end gaji-amount">Rp <?= number_format($d['gaji_pokok'], 0, ',', '.') ?></td>
                                        <td data-label="T. kinerja" class="text-end gaji-amount">Rp <?= number_format($d['tunjangan_kinerja'], 0, ',', '.') ?></td>
                                        <td data-label="T. absen" class="text-end gaji-amount">Rp <?= number_format($d['tunjangan_absen'], 0, ',', '.') ?></td>
                                        <td data-label="T. penempatan" class="text-end gaji-amount">Rp <?= number_format($d['tunjangan_penempatan'], 0, ',', '.') ?></td>
                                        <td data-label="Insentif" class="text-end gaji-amount">Rp <?= number_format($d['insentif'], 0, ',', '.') ?></td>
                                        <td data-label="Lembur" class="text-end gaji-amount">Rp <?= number_format($d['lembur'] ?? 0, 0, ',', '.') ?></td>
                                        <td data-label="Total" class="text-end gaji-amount gaji-total">Rp <?= number_format($d['total'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="text-end">Total gaji unit</td>
                                    <td class="text-end gaji-amount gaji-grand-total rincian-gaji-total">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
