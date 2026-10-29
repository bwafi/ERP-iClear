<?= $this->include('jurnal/_payroll_theme') ?>
<?php
/**
 * Payroll — Lembar Proses Payroll.
 *
 * Satu periode dibaca sebagai proses yang berjalan: papan fase di atas,
 * ledger sebagai tempat kerja. Route, field, role gating, dan logika bisnis
 * tidak berubah; yang berubah hanya susunan, hierarki, dan cara mencari.
 */
$todayTs        = strtotime(date('Y-m-d'));
$payrollSummary = $payrollSummary ?? [];
$payrollItems   = $payrollItems ?? [];
$payrollRun     = $payrollRun ?? [
    'auto'   => ['count' => 0, 'total' => 0],
    'manual' => ['count' => 0, 'total' => 0],
    'paid'   => ['count' => 0, 'total' => 0],
    'bonus'  => ['count' => 0, 'total' => 0],
];
$payrollTotals  = $payrollTotals ?? [
    'count' => 0, 'total' => 0, 'paid_count' => 0, 'paid_total' => 0,
    'unpaid_count' => 0, 'unpaid_total' => 0, 'overdue_count' => 0,
    'overdue_total' => 0, 'employees' => 0,
];
$can_input      = $can_input ?? false;
$bulan          = $bulan ?? date('Y-m');
$show_all       = $show_all ?? ($bulan === '');
$kas_keluar     = $kas_keluar ?? [];
$unit           = $unit ?? [];
$akun           = $akun ?? [];
$bank           = $bank ?? [];
$kategori_kas   = $kategori_kas ?? [];
$bonusCount     = $bonusCount ?? count($kas_keluar);
$bonusTotal     = $bonusTotal ?? 0;
$payrollLocks   = $payrollLocks ?? [];
$locks          = $payrollLocks;

$namaBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];
$bulanLabel = 'Semua bulan';
if (! $show_all && $bulan !== '' && preg_match('/^(\d{4})-(\d{2})$/', $bulan, $m)) {
    $bulanLabel = ($namaBulan[$m[2]] ?? $m[2]) . ' ' . $m[1];
}

$fmtRupiah = static function ($value): string {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
};
$fmtTanggal = static function ($value): string {
    $ts = strtotime((string) $value);
    return !empty($value) && $ts !== false ? date('d/m/Y', $ts) : '—';
};
?>
<div class="payroll-page">
    <section class="pay-sheet" aria-labelledby="payroll-title">
        <header class="pay-head">
            <div class="pay-head-main">
                <nav class="pay-crumb" aria-label="breadcrumb">
                    <a href="<?= base_url('/') ?>">Jurnal</a>
                    <span aria-hidden="true">/</span>
                    <span>Payroll</span>
                </nav>
                <div class="pay-title-row">
                    <h1 class="pay-title" id="payroll-title">Payroll &amp; Penggajian</h1>
                    <span class="pay-period-badge">
                        <iconify-icon icon="solar:calendar-linear" width="13" height="13" aria-hidden="true"></iconify-icon>
                        <?= esc($bulanLabel) ?>
                    </span>
                </div>
                <p class="pay-sub">Kelola jadwal gaji, realisasi pembayaran, dan bonus/lembur karyawan dalam satu periode.</p>
            </div>

            <div class="pay-head-side">
                <form class="pay-period" method="get" action="<?= base_url('payroll') ?>">
                    <label for="payroll-bulan">Periode</label>
                    <input id="payroll-bulan" type="month" class="form-control form-control-sm" name="bulan" value="<?= esc($show_all ? '' : $bulan) ?>" placeholder="Semua bulan">
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
                    <a href="<?= base_url('payroll?bulan=') ?>" class="btn btn-outline-secondary btn-sm <?= $show_all ? 'active' : '' ?>" <?= $show_all ? 'aria-current="page"' : '' ?>>Semua</a>
                </form>

                <?php if ($can_input): ?>
                    <div class="pay-actions">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generate-payroll-modal">
                            <iconify-icon icon="solar:magic-stick-2-bold-duotone" width="15" height="15" aria-hidden="true"></iconify-icon>
                            Susun Otomatis
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#input-payroll-finance-modal">
                            <iconify-icon icon="solar:wallet-money-line-duotone" width="15" height="15" aria-hidden="true"></iconify-icon>
                            Input Payroll Gaji
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($show_all): ?>
            <div class="pay-run-note">
                <iconify-icon icon="solar:info-circle-linear" width="17" height="17" aria-hidden="true"></iconify-icon>
                <div><strong>Menampilkan semua bulan.</strong> Pilih periode untuk melihat status proses dan KPI unit.</div>
            </div>
        <?php else: ?>
            <div class="pay-run">
                <div class="pay-run-head">
                    <span><strong>Status proses</strong> <?= esc($bulanLabel) ?></span>
                    <?php if ((int) ($payrollTotals['overdue_count'] ?? 0) > 0): ?>
                        <span class="pay-run-alert">
                            <iconify-icon icon="solar:danger-triangle-linear" width="14" height="14" aria-hidden="true"></iconify-icon>
                            <?= (int) $payrollTotals['overdue_count'] ?> baris lewat jatuh tempo · <?= $fmtRupiah($payrollTotals['overdue_total']) ?>
                        </span>
                    <?php else: ?>
                        <span class="pay-run-ok">
                            <iconify-icon icon="solar:check-circle-linear" width="14" height="14" aria-hidden="true"></iconify-icon>
                            Tidak ada baris lewat jatuh tempo
                        </span>
                    <?php endif; ?>
                </div>

                <div class="pay-phases">
                    <div class="pay-phase pay-phase--auto">
                        <div class="pay-phase-label"><span class="pay-dot" aria-hidden="true"></span>Susun</div>
                        <div class="pay-phase-value"><?= (int) $payrollRun['auto']['count'] ?> <small>baris</small></div>
                        <div class="pay-phase-money"><?= $fmtRupiah($payrollRun['auto']['total']) ?></div>
                        <div class="pay-phase-note">Otomatis, belum dikoreksi</div>
                    </div>
                    <div class="pay-phase pay-phase--manual">
                        <div class="pay-phase-label"><span class="pay-dot" aria-hidden="true"></span>Koreksi</div>
                        <div class="pay-phase-value"><?= (int) $payrollRun['manual']['count'] ?> <small>baris</small></div>
                        <div class="pay-phase-money"><?= $fmtRupiah($payrollRun['manual']['total']) ?></div>
                        <div class="pay-phase-note">Manual atau sudah dikoreksi</div>
                    </div>
                    <div class="pay-phase pay-phase--paid">
                        <div class="pay-phase-label"><span class="pay-dot" aria-hidden="true"></span>Bayar</div>
                        <div class="pay-phase-value"><?= (int) $payrollRun['paid']['count'] ?> <small>baris</small></div>
                        <div class="pay-phase-money"><?= $fmtRupiah($payrollRun['paid']['total']) ?></div>
                        <div class="pay-phase-note">Sudah dibayar</div>
                    </div>
                    <div class="pay-phase pay-phase--bonus">
                        <div class="pay-phase-label"><span class="pay-dot" aria-hidden="true"></span>Bonus / Lembur</div>
                        <div class="pay-phase-value"><?= (int) $payrollRun['bonus']['count'] ?> <small>transaksi</small></div>
                        <div class="pay-phase-money"><?= $fmtRupiah($payrollRun['bonus']['total']) ?></div>
                        <div class="pay-phase-note">Kas keluar periode ini</div>
                    </div>
                </div>
            </div>

            <div class="pay-units">
                <div class="pay-units-head">
                    <span>KPI unit</span>
                    <span><?= count($payrollSummary) ?> unit dengan payroll</span>
                </div>
                <?php if (!empty($payrollSummary)): ?>
                    <div class="pay-units-grid">
                        <?php foreach ($payrollSummary as $ps): ?>
                            <?php
                            $score = (float) ($ps['score'] ?? 0);
                            $isNoData = ($ps['status'] ?? 'no_data') === 'no_data';
                            $tepat = (int) ($ps['detail']['tepat'] ?? 0);
                            $telat = (int) ($ps['detail']['terlambat'] ?? 0);
                            $menunggu = (int) ($ps['detail']['open'] ?? 0);
                            ?>
                            <div class="pay-unit">
                                <div class="pay-unit-name" title="<?= esc($ps['nama_unit'] ?? '-') ?>">
                                    <iconify-icon icon="solar:buildings-2-linear" width="13" height="13" aria-hidden="true"></iconify-icon>
                                    <?= esc($ps['nama_unit'] ?? '-') ?>
                                </div>
                                <div class="pay-unit-row">
                                    <span class="pay-unit-score">
                                        <?= $isNoData ? '—' : number_format($score, 0, ',', '.') ?><?php if (! $isNoData): ?><small>/100</small><?php endif; ?>
                                    </span>
                                    <span class="pay-unit-total"><?= $fmtRupiah($ps['detail']['total_payroll'] ?? 0) ?></span>
                                </div>
                                <?php if (! $isNoData): ?>
                                    <div class="pay-meter" role="img" aria-label="Skor KPI <?= esc($ps['nama_unit'] ?? '') ?> <?= number_format($score, 0, ',', '.') ?> dari 100">
                                        <span style="--pay-score: <?= max(0, min(100, $score)) ?>%"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="pay-unit-status">
                                    <?= $isNoData ? 'Belum dinilai' : 'Tepat ' . $tepat . ' · Terlambat ' . $telat . ' · Menunggu ' . $menunggu ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="pay-units-empty">Belum ada payroll untuk periode ini, jadi skor KPI unit belum bisa dihitung.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="pay-panel" aria-labelledby="payroll-ledger-title">
        <div class="pay-panel-head">
            <div>
                <h2 class="pay-panel-title" id="payroll-ledger-title">Jadwal &amp; realisasi gaji</h2>
                <p class="pay-panel-sub">Baris rencana yang sudah dikoreksi siap dibayar; baris dibayar tidak ikut dihitung ulang.</p>
            </div>
            <div class="pay-panel-meta">
                <span><?= (int) $payrollTotals['count'] ?> baris · <?= (int) $payrollTotals['employees'] ?> karyawan</span>
                <b><?= $fmtRupiah($payrollTotals['total']) ?></b>
            </div>
        </div>

        <?php if (!empty($payrollItems)): ?>
        <div class="pay-toolbar">
            <label class="pay-search" for="payroll-search">
                <iconify-icon icon="solar:card-search-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                <input id="payroll-search" type="search" placeholder="Cari karyawan, unit, atau catatan..." autocomplete="off">
            </label>
            <div class="pay-chips" role="group" aria-label="Filter status payroll">
                <button type="button" class="pay-chip is-active" data-payroll-status="all" aria-pressed="true">Semua <span><?= (int) $payrollTotals['count'] ?></span></button>
                <button type="button" class="pay-chip" data-payroll-status="rencana" aria-pressed="false">Rencana <span><?= (int) $payrollTotals['unpaid_count'] ?></span></button>
                <button type="button" class="pay-chip" data-payroll-status="dibayar" aria-pressed="false">Dibayar <span><?= (int) $payrollTotals['paid_count'] ?></span></button>
                <button type="button" class="pay-chip" data-payroll-status="telat" aria-pressed="false">Telat <span><?= (int) $payrollTotals['overdue_count'] ?></span></button>
            </div>
            <div class="pay-result" id="payroll-result" aria-live="polite"><?= count($payrollItems) ?> baris</div>
        </div>
        <?php endif; ?>

        <?php if (empty($payrollItems)): ?>
            <div class="pay-empty">
                <iconify-icon icon="solar:document-add-linear" width="34" height="34" aria-hidden="true"></iconify-icon>
                <h3>Belum ada payroll untuk periode ini</h3>
                <p>Susun payroll otomatis dari struktur gaji, atau input satu karyawan secara manual.</p>
                <?php if ($can_input): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generate-payroll-modal">Susun Otomatis</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pay-table-wrap">
                <table class="pay-table" id="payroll-table">
                    <thead>
                        <tr>
                            <th scope="col">Karyawan</th>
                            <th scope="col">Jatuh tempo</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="pay-th-right">Total</th>
                            <th scope="col">Catatan</th>
                            <th scope="col" class="pay-th-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payrollItems as $row): ?>
                            <?php
                            $isPaid = ($row->status ?? '') === 'dibayar';
                            $dueTs = strtotime((string) ($row->due_date ?? ''));
                            $paidTs = !empty($row->paid_date) ? strtotime((string) $row->paid_date) : null;
                            $isLate = ! $isPaid && $dueTs !== false && $dueTs < $todayTs;
                            if ($isPaid) {
                                if ($paidTs !== null && $dueTs !== false) {
                                    $onTime = $paidTs <= $dueTs;
                                    $timingClass = $onTime ? 'is-ok' : 'is-late';
                                    $timingLabel = $onTime ? 'Tepat waktu' : 'Terlambat';
                                } else {
                                    $timingClass = '';
                                    $timingLabel = 'Tanggal bayar belum tercatat';
                                }
                            } elseif ($dueTs === false) {
                                $timingClass = '';
                                $timingLabel = 'Jatuh tempo belum tercatat';
                            } elseif ($isLate) {
                                $timingClass = 'is-late';
                                $timingLabel = 'Lewat jatuh tempo';
                            } else {
                                $timingClass = '';
                                $timingLabel = 'Belum jatuh tempo';
                            }
                            ?>
                            <tr data-status="<?= $isPaid ? 'dibayar' : 'rencana' ?>" data-late="<?= $isLate ? '1' : '0' ?>">
                                <td class="pay-cell-person" data-label="Karyawan">
                                    <div class="pay-person">
                                        <span class="pay-avatar" aria-hidden="true"><?= esc(strtoupper(substr((string) ($row->NAMA_AKUN ?? 'K'), 0, 1))) ?></span>
                                        <span class="pay-person-copy">
                                            <span class="pay-person-name"><?= esc($row->NAMA_AKUN ?? '-') ?></span>
                                            <span class="pay-person-unit"><?= esc($row->NAMA_UNIT ?? '-') ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Jatuh tempo">
                                    <span class="pay-date"><?= $fmtTanggal($row->due_date ?? '') ?></span>
                                    <?php if ($isPaid): ?>
                                        <span class="pay-date-sub"><?= $paidTs !== null ? 'Dibayar ' . $fmtTanggal($row->paid_date) : 'Tanggal bayar belum tercatat' ?></span>
                                    <?php else: ?>
                                        <span class="pay-date-sub">Belum dibayar</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="pay-badge <?= $isPaid ? 'pay-badge--paid' : 'pay-badge--plan' ?>"><?= $isPaid ? 'Dibayar' : 'Rencana' ?></span>
                                    <span class="pay-timing <?= $timingClass ?>"><?= $timingLabel ?></span>
                                </td>
                                <td class="pay-amount" data-label="Total">
                                    <?= $fmtRupiah($row->total ?? 0) ?>
                                    <?php $adj = $adjustment_map[(int) ($row->pegawai_id ?? 0)] ?? []; ?>
                                    <?php $pot = $adj['potongan'] ?? null; ?>
                                    <?php $bonus = $adj['bonus'] ?? null; ?>
                                    <?php if ($bonus && (float) $bonus->jumlah > 0): ?>
                                        <span class="pay-date-sub text-success">+ <?= $fmtRupiah($bonus->jumlah) ?><?= $bonus->keterangan ? ' · ' . esc($bonus->keterangan) : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($pot && (float) $pot->jumlah > 0): ?>
                                        <span class="pay-date-sub">− <?= $fmtRupiah($pot->jumlah) ?><?= $pot->keterangan ? ' · ' . esc($pot->keterangan) : '' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="pay-cell-note" data-label="Catatan">
                                    <?php if (($row->sumber ?? 'manual') === 'auto'): ?>
                                        <span class="pay-src">Auto</span>
                                    <?php else: ?>
                                        <span class="pay-src pay-src--manual">Manual</span>
                                    <?php endif; ?>
                                    <span class="pay-note" title="<?= esc($row->notes ?? '') ?>"><?= esc($row->notes ?? '—') ?></span>
                                </td>
                                <td class="pay-cell-actions" data-label="Aksi">
                                    <?php if (! $isPaid && $can_input): ?>
                                        <div class="pay-row-actions">
                                            <button type="button"
                                                class="pay-icon-btn pay-icon-btn--edit edit-register-button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#edit-register-modal"
                                                data-id="<?= esc($row->id) ?>"
                                                data-karyawan="<?= esc($row->NAMA_AKUN ?? '-') ?>"
                                                data-total="<?= esc($row->total ?? 0) ?>"
                                                data-due="<?= esc($row->due_date ?? '') ?>"
                                                data-notes="<?= esc($row->notes ?? '') ?>"
                                                data-potongan="<?= esc($pot->jumlah ?? 0) ?>"
                                                data-potongan-keterangan="<?= esc($pot->keterangan ?? '') ?>"
                                                data-bonus="<?= esc($bonus->jumlah ?? 0) ?>"
                                                data-bonus-keterangan="<?= esc($bonus->keterangan ?? '') ?>"
                                                aria-label="Koreksi payroll <?= esc($row->NAMA_AKUN ?? '') ?>"
                                                title="Koreksi payroll">
                                                <i class="ti ti-pencil" aria-hidden="true"></i>
                                            </button>
                                            <button type="button"
                                                class="pay-icon-btn pay-icon-btn--pay bayar-button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#bayar-payroll-modal"
                                                data-id="<?= esc($row->id) ?>"
                                                data-karyawan="<?= esc($row->NAMA_AKUN ?? '-') ?>"
                                                aria-label="Tandai sudah dibayar <?= esc($row->NAMA_AKUN ?? '') ?>"
                                                title="Tandai sudah dibayar">
                                                <i class="ti ti-check" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="pay-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pay-empty" id="payroll-empty" hidden>
                <iconify-icon icon="solar:card-search-linear" width="30" height="30" aria-hidden="true"></iconify-icon>
                <h3>Tidak ada baris yang cocok</h3>
                <p>Ubah kata kunci atau pilih filter “Semua”.</p>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="payroll-reset">Reset filter</button>
            </div>
        <?php endif; ?>
    </section>

    <section class="pay-panel pay-panel--bonus" aria-labelledby="bonus-title">
        <div class="pay-panel-head">
            <div>
                <h2 class="pay-panel-title" id="bonus-title">Bonus / lembur</h2>
                <p class="pay-panel-sub">Kas keluar kategori bonus/lembur pada periode yang sama.</p>
            </div>
            <div class="pay-panel-meta">
                <span><?= (int) $bonusCount ?> transaksi</span>
                <b><?= $fmtRupiah($bonusTotal) ?></b>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#input-kas-modal">
                <iconify-icon icon="solar:wallet-money-line-duotone" width="15" height="15" aria-hidden="true"></iconify-icon>
                Input Bonus / Lembur
            </button>
        </div>

        <?php if (!empty($kas_keluar)): ?>
        <div class="pay-toolbar">
            <label class="pay-search" for="bonus-search">
                <iconify-icon icon="solar:card-search-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                <input id="bonus-search" type="search" placeholder="Cari deskripsi, penerima, atau unit..." autocomplete="off">
            </label>
            <div class="pay-result" id="bonus-result" aria-live="polite"><?= count($kas_keluar) ?> transaksi</div>
        </div>
        <?php endif; ?>

        <?php if (empty($kas_keluar)): ?>
            <div class="pay-empty">
                <iconify-icon icon="solar:wallet-money-linear" width="34" height="34" aria-hidden="true"></iconify-icon>
                <h3>Belum ada bonus/lembur</h3>
                <p>Catat bonus atau lembur karyawan untuk periode ini agar ikut terbaca di lembar proses.</p>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#input-kas-modal">Input Bonus / Lembur</button>
            </div>
        <?php else: ?>
            <div class="pay-table-wrap">
                <table class="pay-table pay-table--bonus" id="bonus-table">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Keterangan</th>
                            <th scope="col">Penerima</th>
                            <th scope="col" class="pay-th-right">Jumlah</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="pay-th-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kas_keluar as $row): ?>
                            <?php $isLocked = !empty($locks[(string) $row->idkas_keluar]); ?>
                            <tr>
                                <td data-label="Tanggal"><span class="pay-date"><?= $fmtTanggal($row->tanggal ?? '') ?></span></td>
                                <td class="pay-cell-note" data-label="Keterangan">
                                    <span class="pay-src"><?= esc($row->kategori ?? 'Bonus') ?></span>
                                    <span class="pay-note" title="<?= esc($row->deskripsi ?? '') ?>"><?= esc($row->deskripsi ?? '—') ?></span>
                                    <span class="pay-date-sub"><?= esc($row->NAMA_UNIT ?? '-') ?></span>
                                </td>
                                <td class="pay-cell-stack" data-label="Penerima">
                                    <span class="pay-person-name"><?= esc($row->NAMA_AKUN ?? '-') ?></span>
                                    <span class="pay-date-sub"><?= esc($row->nama_bank ?: 'Tanpa bank') ?><?= !empty($row->atas_nama) ? ' · ' . esc($row->atas_nama) : '' ?></span>
                                </td>
                                <td class="pay-amount" data-label="Jumlah">
                                    <?= $fmtRupiah($row->jumlah ?? 0) ?>
                                    <span class="pay-type <?= strtolower((string) ($row->jenis ?? '')) === 'debet' ? 'is-debet' : 'is-kredit' ?>"><?= esc(strtoupper((string) ($row->jenis ?? '-'))) ?></span>
                                </td>
                                <td data-label="Status">
                                    <?php if ($isLocked): ?>
                                        <span class="pay-badge pay-badge--paid"><i class="ti ti-lock" aria-hidden="true"></i> Terkunci</span>
                                    <?php else: ?>
                                        <span class="pay-badge pay-badge--plan">Terbuka</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pay-cell-actions" data-label="Aksi">
                                    <?php if (
                                        session()->get('ID_JABATAN') == 1 ||
                                        (session()->get('ID_JABATAN') == 35 && session()->get('ID_UNIT') == 1)
                                    ): ?>
                                        <div class="pay-row-actions">
                                            <?php if (! $isLocked): ?>
                                                <button type="button"
                                                    class="pay-icon-btn pay-icon-btn--edit edit-button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#edit-kas-modal"
                                                    data-id="<?= esc($row->idkas_keluar) ?>"
                                                    data-tanggal="<?= esc($row->tanggal) ?>"
                                                    data-unit="<?= esc($row->idunit) ?>"
                                                    data-no-akun="<?= esc($row->no_akun) ?>"
                                                    data-kategori="<?= esc($row->kategori_idkategori) ?>"
                                                    data-deskripsi="<?= esc($row->deskripsi) ?>"
                                                    data-penerima="<?= esc($row->penerima) ?>"
                                                    data-no-rekening="<?= esc($row->no_rekening ?? '') ?>"
                                                    data-jumlah="<?= esc($row->jumlah) ?>"
                                                    data-jenis="<?= esc($row->jenis) ?>"
                                                    aria-label="Edit bonus/lembur <?= esc($row->deskripsi ?? '') ?>"
                                                    title="Edit">
                                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                                </button>
                                                <button type="button"
                                                    class="pay-icon-btn delete-button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#delete-kas-modal"
                                                    data-id="<?= esc($row->idkas_keluar) ?>"
                                                    aria-label="Hapus bonus/lembur <?= esc($row->deskripsi ?? '') ?>"
                                                    title="Hapus">
                                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                                </button>
                                                <form action="<?= base_url('payroll/lock') ?>" method="post">
                                                    <input type="hidden" name="idkas_keluar" value="<?= esc($row->idkas_keluar) ?>">
                                                    <button type="submit" class="pay-icon-btn" aria-label="Kunci transaksi" title="Kunci transaksi">
                                                        <i class="ti ti-lock" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= base_url('payroll/unlock') ?>" method="post">
                                                    <input type="hidden" name="idkas_keluar" value="<?= esc($row->idkas_keluar) ?>">
                                                    <button type="submit" class="pay-icon-btn pay-icon-btn--unlock" aria-label="Buka kunci transaksi" title="Buka kunci transaksi">
                                                        <i class="ti ti-lock-open" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="pay-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pay-empty" id="bonus-empty" hidden>
                <iconify-icon icon="solar:card-search-linear" width="30" height="30" aria-hidden="true"></iconify-icon>
                <h3>Tidak ada transaksi yang cocok</h3>
                <p>Ubah kata kunci pencarian.</p>
            </div>
        <?php endif; ?>
    </section>
<!-- MODAL SUSUN PAYROLL OTOMATIS -->
<!-- ====================================================== -->
<div class="modal fade" id="generate-payroll-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll/generate') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Susun Payroll Otomatis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis mb-3">
                        <div class="d-flex gap-2">
                            <i class="ti ti-info-circle fs-5 flex-shrink-0"></i>
                            <div class="small">
                                Nominal dihitung dari <code>salary_structures</code> (Gaji Pokok + Tunjangan Kinerja + Tunjangan Absen + Penempatan + Insentif) + Lembur Kas Keluar. Baris <strong>Rencana</strong> yang sudah ada akan dihitung ulang dan ditimpa; baris yang sudah <strong>Dibayar</strong> tidak diubah.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bulan Payroll</label>
                        <input type="month" class="form-control" name="bulan" value="<?= esc($bulan !== '' ? $bulan : date('Y-m')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jatuh Tempo</label>
                        <input type="date" class="form-control" name="due_date" value="<?= esc(date('Y-m-t', strtotime(($bulan !== '' ? $bulan : date('Y-m')) . '-01'))) ?>">
                        <div class="form-text">Kosongkan untuk menyetel otomatis ke tanggal akhir bulan.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batasi Unit</label>
                        <select class="form-select select" name="unit_ids[]" multiple size="4">
                            <option value="" selected>Semua Unit</option>
                            <?php foreach ($unit as $u): ?>
                                <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Proses Susun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL KOREKSI PAYROLL -->
<!-- ====================================================== -->
<div class="modal fade" id="edit-register-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll/update-register') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Koreksi Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="edit_register_id">
                    <p class="mb-3">Koreksi item gajian untuk karyawan: <strong id="edit_register_karyawan" class="text-primary">-</strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Total Gaji</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency-input" name="total" id="edit_register_total" inputmode="numeric" autocomplete="off" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jatuh Tempo</label>
                        <input type="date" class="form-control" name="due_date" id="edit_register_due" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Potongan Gaji <small class="text-muted fw-normal">(SP/dll, opsional)</small></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency-input" name="potongan" id="edit_register_potongan" inputmode="numeric" autocomplete="off" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keterangan Potongan</label>
                        <input type="text" class="form-control" name="potongan_keterangan" id="edit_register_potongan_keterangan" maxlength="255" placeholder="mis. SP1 - telat berulang">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bonus Gaji <small class="text-muted fw-normal">(bonus kinerja/dll, opsional)</small></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency-input" name="bonus" id="edit_register_bonus" inputmode="numeric" autocomplete="off" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keterangan Bonus</label>
                        <input type="text" class="form-control" name="bonus_keterangan" id="edit_register_bonus_keterangan" maxlength="255" placeholder="mis. Bonus kinerja Oktober">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan</label>
                        <textarea class="form-control" name="notes" id="edit_register_notes" rows="3" placeholder="Alasan koreksi atau catatan tambahan"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL INPUT PAYROLL GAJI (finance_payroll) -->
<!-- ====================================================== -->
<div class="modal fade" id="input-payroll-finance-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('finance/entry/payroll') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Input Payroll Gaji Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Karyawan</label>
                            <select class="form-select select" name="pegawai_id" id="input_pegawai" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>" data-unit="<?= esc($a->ID_UNIT ?? '') ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="unit_id" id="input_unit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jatuh Tempo (Jadwal Gajian)</label>
                            <input type="date" class="form-control" name="due_date" value="<?= esc(($show_all ? date('Y-m') : $bulan)) ?>-25" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Bayar <small class="text-muted fw-normal">(Opsional)</small></label>
                            <input type="date" class="form-control" name="paid_date">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Total Gaji</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="total" min="0" placeholder="0" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Catatan Tambahan"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL SUDAH DIBAYAR -->
<!-- ====================================================== -->
<div class="modal fade" id="bayar-payroll-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll/bayar') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="id" id="bayar_id">
                    <div class="avatar-lg bg-success-subtle text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="ti ti-cash fs-2"></i>
                    </div>
                    <p class="mb-3 fs-6">Tandai penggajian <strong id="bayar_karyawan" class="text-primary">-</strong> sebagai telah dibayar?</p>

                    <div class="text-start">
                        <label class="form-label fw-semibold">Tanggal Pelunasan / Bayar</label>
                        <input type="date" class="form-control" name="paid_date" id="bayar_tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4">Konfirmasi Bayar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL INPUT BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="input-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll/insert') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Input Bonus / Lembur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="idunit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" placeholder="Misal: Uang Lembur Project X" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Penerima</label>
                            <select class="form-select select" name="penerima" id="penerima" required>
                                <option value="">-- Pilih Penerima --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rekening Bank</label>
                            <select class="form-select select" name="no_rekening">
                                <option value="">-- Pilih Bank --</option>
                                <?php foreach ($bank as $b): ?>
                                    <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="jumlah" min="0" placeholder="0" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Mutasi</label>
                            <select class="form-select" name="jenis" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="debet">Debet</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL EDIT BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="edit-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll/update') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Edit Mutasi Bonus / Lembur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="idkas_keluar" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="idunit" id="edit_unit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" id="edit_deskripsi" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Penerima</label>
                            <select class="form-select select" name="penerima" id="edit_penerima" required>
                                <option value="">-- Pilih Penerima --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rekening Bank</label>
                            <select class="form-select select" name="no_rekening" id="edit_no_rekening">
                                <option value="">-- Pilih Bank --</option>
                                <?php foreach ($bank as $b): ?>
                                    <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis</label>
                            <select class="form-select" name="jenis" id="edit_jenis" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="debet">Debet</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL DELETE BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="delete-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('delete_kas_keluar') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="idkas_keluar" id="delete_id">
                    <div class="avatar-lg bg-danger-subtle text-danger rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="ti ti-trash fs-2"></i>
                    </div>
                    <p class="mb-0 fs-6">Apakah Anda yakin ingin menghapus data <strong>Bonus / Lembur</strong> ini? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer border-top bg-light-subtle justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
    $(document).ready(function () {
        // Select2
        $('#penerima').select2({ width: '100%', dropdownParent: $('#input-kas-modal') });
        $('#edit_penerima').select2({ width: '100%', dropdownParent: $('#edit-kas-modal') });
        $('#input_pegawai').select2({ width: '100%', dropdownParent: $('#input-payroll-finance-modal') });

        $('#input_pegawai').on('change', function () {
            const unitId = $(this).find('option:selected').data('unit');
            if (unitId) {
                $('#input_unit').val(String(unitId));
            }
        });

        // Aksi payroll
        $('#payroll-table').on('click', '.bayar-button', function () {
            const btn = $(this);
            $('#bayar_id').val(btn.data('id'));
            $('#bayar_karyawan').text(btn.data('karyawan'));
            $('#bayar_tanggal').val('<?= date('Y-m-d') ?>');
        });

        $('#payroll-table').on('click', '.edit-register-button', function () {
            const btn = $(this);
            $('#edit_register_id').val(btn.data('id'));
            $('#edit_register_karyawan').text(btn.data('karyawan'));
            const totalVal = parseInt(btn.data('total'), 10) || 0;
            $('#edit_register_total').val(totalVal === 0 ? '' : formatRupiah(totalVal, ''));
            $('#edit_register_due').val(btn.data('due'));
            $('#edit_register_notes').val(btn.data('notes') || '');
            const potVal = parseInt(btn.data('potongan'), 10) || 0;
            $('#edit_register_potongan').val(potVal === 0 ? '' : formatRupiah(potVal, ''));
            $('#edit_register_potongan_keterangan').val(btn.data('potongan-keterangan') || '');
            const bonusVal = parseInt(btn.data('bonus'), 10) || 0;
            $('#edit_register_bonus').val(bonusVal === 0 ? '' : formatRupiah(bonusVal, ''));
            $('#edit_register_bonus_keterangan').val(btn.data('bonus-keterangan') || '');
        });

        // Aksi bonus / lembur
        $('#bonus-table').on('click', '.edit-button', function () {
            const btn = $(this);
            $('#edit_id').val(btn.data('id'));
            $('#edit_tanggal').val(btn.data('tanggal'));
            $('#edit_unit').val(btn.data('unit'));
            $('#edit_deskripsi').val(btn.data('deskripsi'));
            $('#edit_penerima').val(btn.data('penerima')).trigger('change');
            $('#edit_no_rekening').val(btn.data('no_rekening')).trigger('change');
            $('#edit_jumlah').val(btn.data('jumlah'));
            $('#edit_jenis').val(btn.data('jenis'));
        });

        $('#bonus-table').on('click', '.delete-button', function () {
            $('#delete_id').val($(this).data('id'));
        });

        function formatRupiah(angka, prefix = 'Rp ') {
            const numberString = angka.toString().replace(/[^\d]/g, '');
            const split = numberString.split(',');
            const sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            const ribuan = split[0].substr(sisa).match(/\d{3}/g);

            if (ribuan) {
                const separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix + rupiah;
        }

        function parseRupiah(value) {
            return parseInt(value.toString().replace(/[^\d]/g, ''), 10) || 0;
        }

        $(document).on('input', '.currency-input', function (e) {
            const cursorPos = e.target.selectionStart;
            const oldLength = e.target.value.length;
            const raw = parseRupiah(e.target.value);
            e.target.value = raw === 0 ? '' : formatRupiah(raw, '');
            const newLength = e.target.value.length;
            e.target.selectionStart = e.target.selectionEnd = cursorPos + (newLength - oldLength);
        });

        // Filter payroll tanpa DataTables. Data payroll bulanan cukup kecil;
        // kalau tampilan "Semua Bulan" melewati ~300 baris, tambahkan paging.
        // shortcut: pagination nonaktif, cek volume saat semua bulan dipakai.
        const payrollTable = document.getElementById('payroll-table');
        if (payrollTable) {
            const rows = Array.from(payrollTable.querySelectorAll('tbody tr'));
            const search = document.getElementById('payroll-search');
            const chips = Array.from(document.querySelectorAll('[data-payroll-status]'));
            const result = document.getElementById('payroll-result');
            const empty = document.getElementById('payroll-empty');
            const reset = document.getElementById('payroll-reset');
            let activeStatus = 'all';

            const applyPayrollFilter = () => {
                const query = (search?.value || '').trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const matchesQuery = !query || row.textContent.toLowerCase().includes(query);
                    const matchesStatus = activeStatus === 'all'
                        || (activeStatus === 'telat' ? row.dataset.late === '1' : row.dataset.status === activeStatus);

                    const show = matchesQuery && matchesStatus;
                    row.hidden = !show;
                    if (show) visible++;
                });

                if (result) result.textContent = visible + ' baris';
                if (empty) empty.hidden = visible !== 0;
            };

            chips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    activeStatus = chip.dataset.payrollStatus;
                    chips.forEach((item) => {
                        const active = item === chip;
                        item.classList.toggle('is-active', active);
                        item.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                    applyPayrollFilter();
                });
            });

            search?.addEventListener('input', applyPayrollFilter);

            reset?.addEventListener('click', () => {
                if (search) search.value = '';
                activeStatus = 'all';
                chips.forEach((item) => {
                    const active = item.dataset.payrollStatus === 'all';
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                applyPayrollFilter();
                search?.focus();
            });

            applyPayrollFilter();
        }

        // Pencarian bonus / lembur.
        const bonusTable = document.getElementById('bonus-table');
        if (bonusTable) {
            const rows = Array.from(bonusTable.querySelectorAll('tbody tr'));
            const search = document.getElementById('bonus-search');
            const result = document.getElementById('bonus-result');
            const empty = document.getElementById('bonus-empty');

            const applyBonusFilter = () => {
                const query = (search?.value || '').trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const show = !query || row.textContent.toLowerCase().includes(query);
                    row.hidden = !show;
                    if (show) visible++;
                });

                if (result) result.textContent = visible + ' transaksi';
                if (empty) empty.hidden = visible !== 0;
            };

            search?.addEventListener('input', applyBonusFilter);
            applyBonusFilter();
        }
    });
</script>
