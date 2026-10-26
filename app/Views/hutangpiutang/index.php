<?php

use App\Services\Finance\HutangPiutangService;

$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$f = $filters ?? [];
$canInput = (bool) ($can_input ?? false);
$isPiutang = $jenis === 'piutang';

$bulanId = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tgl = static function ($value) use ($bulanId): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || substr($value, 0, 10) === '0000-00-00') {
        return '—';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }

    return date('j', $ts) . ' ' . $bulanId[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};

$tsHariIni = strtotime(date('Y-m-d'));
$dueMeta = static function ($value) use ($tgl, $tsHariIni): array {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || substr($value, 0, 10) === '0000-00-00') {
        return ['date' => '—', 'note' => 'Tanpa jatuh tempo', 'class' => ''];
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return ['date' => '—', 'note' => 'Tanggal tidak valid', 'class' => ''];
    }

    $hari = (int) floor(($ts - $tsHariIni) / 86400);
    if ($hari < 0) {
        return ['date' => $tgl($value), 'note' => 'Terlambat ' . abs($hari) . ' hari', 'class' => 'is-overdue'];
    }
    if ($hari === 0) {
        return ['date' => $tgl($value), 'note' => 'Jatuh tempo hari ini', 'class' => 'is-today'];
    }
    if ($hari === 1) {
        return ['date' => $tgl($value), 'note' => 'Besok', 'class' => 'is-soon'];
    }
    if ($hari <= 7) {
        return ['date' => $tgl($value), 'note' => $hari . ' hari lagi', 'class' => 'is-soon'];
    }

    return ['date' => $tgl($value), 'note' => '', 'class' => ''];
};

// Kriteria "boleh ditandai lunas legacy" untuk tombol cepat di list.
// Aturan yang sama persis dengan service — ini hanya pembantu tampilan.
$cutoffFinance = \App\Services\Finance\FinanceScopeService::cutoffDate();
$bisaLegacyRow = static function (array $r) use ($canInput, $cutoffFinance): bool {
    return $canInput
        && (int) ($r['is_projection'] ?? 0) === 0
        && $r['status'] !== HutangPiutangService::STATUS_LUNAS
        && (float) ($r['sisa'] ?? 0) > 0
        && ($r['cutoff_reason'] ?? null) !== HutangPiutangService::SETTLEMENT_LEGACY
        && \App\Services\Finance\FinanceScopeService::tanggalStr($r['tanggal'] ?? null) !== ''
        && \App\Services\Finance\FinanceScopeService::tanggalStr($r['tanggal'] ?? null) <= $cutoffFinance;
};

$labelSumber = [
    'pembelian' => HutangPiutangService::labelSumber('pembelian'),
    'piutang_pelanggan' => HutangPiutangService::labelSumber('piutang_pelanggan'),
    'kasbon' => HutangPiutangService::labelSumber('kasbon'),
    'piutang_legacy' => HutangPiutangService::labelSumber('piutang_legacy'),
    'jasa_teknisi' => HutangPiutangService::labelSumber('jasa_teknisi'),
    'kelebihan_transfer' => HutangPiutangService::labelSumber('kelebihan_transfer'),
    'retur_barang' => HutangPiutangService::labelSumber('retur_barang'),
    'manual' => HutangPiutangService::labelSumber('manual'),
    'mutasi_unit' => HutangPiutangService::labelSumber('mutasi_unit'),
];

$allowedSumber = $isPiutang
    ? ['piutang_pelanggan', 'kasbon', 'kelebihan_transfer', 'retur_barang', 'piutang_legacy', 'mutasi_unit']
    : ['pembelian', 'jasa_teknisi', 'mutasi_unit', 'manual'];

$statusOptions = [
    '' => 'Semua',
    HutangPiutangService::STATUS_BELUM => 'Belum Lunas',
    HutangPiutangService::STATUS_SEBAGIAN => 'Sebagian',
    HutangPiutangService::STATUS_LUNAS => 'Lunas',
];
$activeStatus = (string) ($f['status'] ?? '');
if (!array_key_exists($activeStatus, $statusOptions)) {
    $activeStatus = '';
}
$statusId = static function (string $status): string {
    return 'hp-status-' . ($status === '' ? 'semua' : str_replace('_', '-', $status));
};

$totalSisa = 0.0;
$totalDibayar = 0.0;
$totalNilai = 0.0;
$openCount = 0;
$overdueCount = 0;
$dueTodayCount = 0;
$statusCounts = [
    HutangPiutangService::STATUS_BELUM => 0,
    HutangPiutangService::STATUS_SEBAGIAN => 0,
    HutangPiutangService::STATUS_LUNAS => 0,
];

foreach ($rows as $r) {
    $sisa = max(0, (float) ($r['sisa'] ?? 0));
    $totalSisa += $sisa;
    $totalDibayar += (float) ($r['total_dibayar'] ?? 0);
    $totalNilai += (float) ($r['total'] ?? 0);

    $status = (string) ($r['status'] ?? '');
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }

    if ($status === HutangPiutangService::STATUS_LUNAS || $sisa <= 0) {
        continue;
    }

    $openCount++;
    $jatuhTempo = trim((string) ($r['jatuh_tempo'] ?? ''));
    if ($jatuhTempo === '' || substr($jatuhTempo, 0, 10) === '0000-00-00') {
        continue;
    }

    $ts = strtotime($jatuhTempo);
    if ($ts === false) {
        continue;
    }

    $hari = (int) floor(($ts - $tsHariIni) / 86400);
    if ($hari < 0) {
        $overdueCount++;
    } elseif ($hari === 0) {
        $dueTodayCount++;
    }
}

$statusSummary = [];
if ($statusCounts[HutangPiutangService::STATUS_BELUM] > 0) {
    $statusSummary[] = $statusCounts[HutangPiutangService::STATUS_BELUM] . ' belum';
}
if ($statusCounts[HutangPiutangService::STATUS_SEBAGIAN] > 0) {
    $statusSummary[] = $statusCounts[HutangPiutangService::STATUS_SEBAGIAN] . ' sebagian';
}
if ($statusCounts[HutangPiutangService::STATUS_LUNAS] > 0) {
    $statusSummary[] = $statusCounts[HutangPiutangService::STATUS_LUNAS] . ' lunas';
}
$statusSummary = $statusSummary ? implode(' · ', $statusSummary) : 'Tidak ada dokumen';

$activeFilterCount = 0;
foreach (['q', 'status', 'sumber_tipe', 'bulan', 'scope'] as $key) {
    if (trim((string) ($f[$key] ?? '')) !== '') {
        $activeFilterCount++;
    }
}

$resetUrl = base_url('hutangpiutang/' . $jenis);
$jumlahDokumen = count($rows);
$emptyTitle = $activeFilterCount > 0 ? 'Tidak ada hasil' : 'Belum ada dokumen';
$emptyText = $activeFilterCount > 0
    ? 'Coba longgarkan filter atau reset untuk melihat semua dokumen.'
    : ($isPiutang
        ? 'Piutang dari pelanggan, kasbon, dan mutasi antar unit akan muncul di sini.'
        : 'Hutang dari pembelian, jasa teknisi, dan mutasi antar unit akan muncul di sini.');
?>

<?= view('hutangpiutang/_list_theme') ?>

<div class="hp-list <?= $isPiutang ? 'hp-list--piutang' : 'hp-list--hutang' ?>">
    <section class="hp-hero">
        <div class="hp-hero__main">
            <nav class="hp-crumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                    <li aria-current="page"><?= esc(ucfirst($jenis)) ?></li>
                </ol>
            </nav>

            <div class="hp-hero__title-row">
                <span class="hp-hero__mark" aria-hidden="true">
                    <iconify-icon icon="<?= $isPiutang ? 'solar:hand-money-bold-duotone' : 'solar:wallet-money-bold-duotone' ?>"></iconify-icon>
                </span>
                <div>
                    <h1><?= $isPiutang ? 'Daftar Piutang' : 'Daftar Hutang' ?></h1>
                    <p><?= $isPiutang
                        ? 'Pantau tagihan yang belum tertagih, jatuh tempo, dan penerimaan dalam satu daftar.'
                        : 'Pantau kewajiban yang belum selesai, jatuh tempo, dan pelunasan dalam satu daftar.' ?></p>
                </div>
            </div>
        </div>

        <div class="hp-hero__actions">
            <a class="hp-btn hp-btn--ghost" href="<?= base_url('hutangpiutang/dashboard') ?>">
                <iconify-icon icon="solar:chart-bold-duotone" aria-hidden="true"></iconify-icon>
                Dashboard
            </a>
            <?php if ($canInput) : ?>
                <button type="button" class="hp-btn hp-btn--primary" data-bs-toggle="modal" data-bs-target="#input-modal">
                    <iconify-icon icon="solar:add-circle-bold" aria-hidden="true"></iconify-icon>
                    Input <?= $isPiutang ? 'Piutang' : 'Hutang' ?>
                </button>
            <?php endif; ?>
        </div>
    </section>

    <section class="hp-panel hp-filter" aria-label="Filter daftar">
        <form method="get" action="<?= base_url('hutangpiutang/' . $jenis) ?>">
            <div class="hp-filter__top">
                <div class="hp-field hp-field--search">
                    <label for="hp-q">Cari</label>
                    <div class="hp-input-icon">
                        <iconify-icon icon="solar:magnifer-linear" aria-hidden="true"></iconify-icon>
                        <input type="search" id="hp-q" name="q" class="form-control hp-control"
                               placeholder="Kode, nama pihak, atau uraian"
                               value="<?= esc($f['q'] ?? '') ?>" autocomplete="off">
                    </div>
                </div>

                <div class="hp-field">
                    <label for="hp-unit">Unit</label>
                    <select id="hp-unit" name="unit_id" class="form-select hp-control">
                        <option value="">Semua unit</option>
                        <?php foreach (($units ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_id ?? null) == $u->idunit ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hp-field">
                    <label for="hp-bulan">Bulan</label>
                    <input type="month" id="hp-bulan" name="bulan" class="form-control hp-control"
                           value="<?= esc($f['bulan'] ?? '') ?>">
                </div>

                <div class="hp-field hp-field--action">
                    <button type="submit" class="hp-btn hp-btn--primary">
                        <iconify-icon icon="solar:filter-bold" aria-hidden="true"></iconify-icon>
                        Terapkan
                    </button>
                    <?php if ($activeFilterCount > 0) : ?>
                        <a class="hp-btn hp-btn--ghost" href="<?= $resetUrl ?>">Reset</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hp-filter__bottom">
                <div class="hp-chips" role="group" aria-label="Filter status">
                    <?php foreach ($statusOptions as $value => $label) : $id = $statusId((string) $value); ?>
                        <input class="hp-chip-input" type="radio" name="status" id="<?= esc($id) ?>"
                               value="<?= esc($value) ?>" <?= $activeStatus === (string) $value ? 'checked' : '' ?>>
                        <label class="hp-chip" for="<?= esc($id) ?>"><?= esc($label) ?></label>
                    <?php endforeach; ?>
                </div>

                <div class="hp-inline">
                    <label class="hp-mini" for="hp-sumber">
                        <span>Sumber</span>
                        <select id="hp-sumber" name="sumber_tipe" class="form-select hp-control hp-control--sm">
                            <option value="">Semua</option>
                            <?php foreach ($allowedSumber as $k) : ?>
                                <option value="<?= esc($k) ?>" <?= ($f['sumber_tipe'] ?? '') === $k ? 'selected' : '' ?>>
                                    <?= esc($labelSumber[$k] ?? $k) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="hp-mini" for="hp-scope">
                        <span>Scope</span>
                        <select id="hp-scope" name="scope" class="form-select hp-control hp-control--sm">
                            <option value="">Aktif</option>
                            <option value="semua" <?= ($f['scope'] ?? '') === 'semua' ? 'selected' : '' ?>>Semua data</option>
                            <option value="legacy" <?= ($f['scope'] ?? '') === 'legacy' ? 'selected' : '' ?>>Legacy</option>
                            <option value="opening" <?= ($f['scope'] ?? '') === 'opening' ? 'selected' : '' ?>>Opening</option>
                        </select>
                    </label>
                </div>
            </div>
        </form>

        <?php if (!$isPiutang) : ?>
            <div class="hp-note">
                <iconify-icon icon="solar:info-circle-bold-duotone" aria-hidden="true"></iconify-icon>
                <p class="mb-0">
                    Hutang supplier otomatis terbentuk dari transaksi <strong>Pembelian</strong>.
                    Pembayaran dilakukan lewat <a href="<?= base_url('daftar_tagihan') ?>">Daftar Tagihan Hutang</a>.
                    Saldo manual dan jasa teknisi dapat dibayar dari halaman detail.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <section class="hp-board" aria-label="Ringkasan daftar">
        <div class="hp-board__cell">
            <div class="hp-board__label"><?= $isPiutang ? 'Sisa piutang' : 'Sisa hutang' ?></div>
            <div class="hp-board__value is-accent"><?= $rp($totalSisa) ?></div>
            <div class="hp-board__note"><?= $openCount > 0 ? $openCount . ' dokumen belum selesai' : 'Tidak ada saldo terbuka' ?></div>
        </div>

        <div class="hp-board__cell">
            <div class="hp-board__label">Terlambat</div>
            <div class="hp-board__value <?= $overdueCount > 0 ? 'is-danger' : '' ?>">
                <?= number_format($overdueCount, 0, ',', '.') ?> <small>dokumen</small>
            </div>
            <div class="hp-board__note">
                <?php if ($overdueCount === 0 && $dueTodayCount === 0) : ?>
                    Tidak ada yang lewat jatuh tempo
                <?php elseif ($overdueCount === 0) : ?>
                    <?= $dueTodayCount ?> jatuh tempo hari ini
                <?php elseif ($dueTodayCount > 0) : ?>
                    + <?= $dueTodayCount ?> jatuh tempo hari ini
                <?php else : ?>
                    Perlu ditindaklanjuti
                <?php endif; ?>
            </div>
        </div>

        <div class="hp-board__cell">
            <div class="hp-board__label"><?= $isPiutang ? 'Sudah diterima' : 'Sudah dibayar' ?></div>
            <div class="hp-board__value"><?= $rp($totalDibayar) ?></div>
            <div class="hp-board__note"><?= $totalNilai > 0 ? 'dari ' . $rp($totalNilai) . ' nilai dokumen' : 'Belum ada nilai dokumen' ?></div>
        </div>

        <div class="hp-board__cell">
            <div class="hp-board__label">Dokumen</div>
            <div class="hp-board__value"><?= number_format($jumlahDokumen, 0, ',', '.') ?> <small>baris</small></div>
            <div class="hp-board__note"><?= esc($statusSummary) ?></div>
        </div>
    </section>

    <section class="hp-panel hp-table-card">
        <div class="hp-table-card__head">
            <div>
                <h2><?= $isPiutang ? 'Daftar dokumen piutang' : 'Daftar dokumen hutang' ?></h2>
                <p><?= number_format($jumlahDokumen, 0, ',', '.') ?> dokumen · Ringkasan mengikuti filter aktif</p>
            </div>
            <span class="hp-table-card__sort">
                <iconify-icon icon="solar:list-check-bold" aria-hidden="true"></iconify-icon>
                Terbaru
            </span>
        </div>

        <?php if (empty($rows)) : ?>
            <div class="hp-empty">
                <div class="hp-empty__icon" aria-hidden="true">
                    <iconify-icon icon="solar:document-text-bold"></iconify-icon>
                </div>
                <h3><?= esc($emptyTitle) ?></h3>
                <p><?= esc($emptyText) ?></p>
                <?php if ($activeFilterCount > 0) : ?>
                    <a class="hp-btn hp-btn--ghost" href="<?= $resetUrl ?>">Reset filter</a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="hp-table-wrap">
                <table class="table hp-table" aria-label="<?= $isPiutang ? 'Daftar dokumen piutang' : 'Daftar dokumen hutang' ?>">
                    <thead>
                        <tr>
                            <th scope="col">Dokumen</th>
                            <th scope="col">Pihak</th>
                            <th scope="col">Jatuh tempo</th>
                            <th scope="col" class="text-end">Nilai</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $authoritative = in_array($row['sumber_tipe'], HutangPiutangService::AUTHORITATIVE_SUMBER, true);
                            $status = (string) ($row['status'] ?? '');
                            $statusTone = $status === HutangPiutangService::STATUS_LUNAS
                                ? 'is-success'
                                : ($status === HutangPiutangService::STATUS_SEBAGIAN ? 'is-warning' : 'is-neutral');
                            $scopeLabel = ['legacy' => 'Legacy', 'opening' => 'Opening'][$row['scope'] ?? ''] ?? '';
                            $cutoffReason = $row['cutoff_reason'] ?? null;
                            $settledLegacy = $cutoffReason === HutangPiutangService::SETTLEMENT_LEGACY;
                            $bisaLegacy = $bisaLegacyRow($row);
                            $due = $dueMeta($row['jatuh_tempo'] ?? null);
                            $namaUnit = trim((string) ($row['NAMA_UNIT'] ?? '')) ?: 'Tanpa unit';
                            $namaPihak = trim((string) ($row['nama_pihak'] ?? '')) ?: 'Tanpa nama';
                            $namaLawan = trim((string) ($row['nama_lawan'] ?? ''));
                            $arahMutasi = '';
                            if ($row['sumber_tipe'] === HutangPiutangService::SUMBER_MUTASI && $namaLawan !== '') {
                                $arahMutasi = $row['jenis'] === 'piutang'
                                    ? $namaPihak . ' → ' . $namaLawan
                                    : $namaLawan . ' → ' . $namaPihak;
                            }
                            $canPayAuthoritative = $canInput
                                && $authoritative
                                && $status !== HutangPiutangService::STATUS_LUNAS
                                && $row['sumber_tipe'] !== HutangPiutangService::SUMBER_KASBON
                                && (float) ($row['sisa'] ?? 0) > 0;
                            $canPayAntarUnit = $canInput
                                && $row['sumber_tipe'] === HutangPiutangService::SUMBER_MUTASI
                                && $status !== HutangPiutangService::STATUS_LUNAS
                                && ($row['scope'] ?? 'active') !== 'legacy'
                                && (float) ($row['sisa'] ?? 0) > 0;
                        ?>
                            <tr>
                                <td class="hp-cell-doc" data-label="Dokumen">
                                    <div class="hp-doc">
                                        <span class="hp-doc__code"><?= esc($row['kode']) ?></span>
                                        <span class="hp-doc__meta"><?= $tgl($row['tanggal'] ?? null) ?> &middot; <?= esc($namaUnit) ?></span>
                                    </div>
                                </td>

                                <td data-label="Pihak">
                                    <div class="hp-party">
                                        <span class="hp-party__name"><?= esc($arahMutasi !== '' ? $arahMutasi : $namaPihak) ?></span>
                                        <span class="hp-party__meta">
                                            <span class="hp-tag"><?= esc($labelSumber[$row['sumber_tipe']] ?? $row['sumber_tipe']) ?></span>
                                            <?php if ($scopeLabel !== '') : ?>
                                                <span class="hp-tag"><?= esc($scopeLabel) ?></span>
                                            <?php endif; ?>
                                            <?php if ($settledLegacy) : ?>
                                                <span class="hp-tag hp-tag--warn">Lunas legacy</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Jatuh tempo">
                                    <div class="hp-due <?= esc($due['class']) ?>">
                                        <span class="hp-due__date"><?= esc($due['date']) ?></span>
                                        <?php if ($due['note'] !== '') : ?>
                                            <small><?= esc($due['note']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-end" data-label="Nilai">
                                    <div class="hp-money">
                                        <span class="hp-money__sisa <?= (float) ($row['sisa'] ?? 0) <= 0 ? 'is-zero' : '' ?>"><?= $rp($row['sisa']) ?></span>
                                        <span class="hp-money__meta">Dibayar <?= $rp($row['total_dibayar']) ?> dari <?= $rp($row['total']) ?></span>
                                    </div>
                                </td>

                                <td data-label="Status">
                                    <span class="hp-status <?= $statusTone ?>"><?= esc(HutangPiutangService::labelStatus($status)) ?></span>
                                </td>

                                <td class="text-end hp-cell-actions" data-label="Aksi">
                                    <div class="hp-actions">
                                        <div class="hp-actions__main">
                                            <a class="hp-btn hp-btn--ghost hp-btn--sm" href="<?= base_url('hutangpiutang/detail/' . $row['id']) ?>">Detail</a>

                                            <?php if ($canPayAuthoritative) : ?>
                                                <button type="button" class="hp-btn hp-btn--primary hp-btn--sm btn-bayar"
                                                    data-id="<?= (int) $row['id'] ?>"
                                                    data-kode="<?= esc($row['kode']) ?>"
                                                    data-pihak="<?= esc($namaPihak) ?>"
                                                    data-sisa="<?= (int) $row['sisa'] ?>">Bayar</button>
                                            <?php elseif ($canPayAntarUnit) : ?>
                                                <a class="hp-btn hp-btn--primary hp-btn--sm"
                                                   href="<?= base_url('hutangpiutang/detail/' . $row['id'] . '?bayar=1') ?>">Lunasi</a>
                                            <?php endif; ?>

                                            <?php if ($authoritative) : ?>
                                                <a class="hp-btn hp-btn--ghost hp-btn--icon hp-btn--sm"
                                                   href="<?= base_url('hutangpiutang/cetak/' . $row['id']) ?>"
                                                   target="_blank" rel="noopener"
                                                   title="Cetak bukti" aria-label="Cetak bukti">
                                                    <iconify-icon icon="solar:printer-bold" aria-hidden="true"></iconify-icon>
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($bisaLegacy) : ?>
                                            <a class="hp-action-note"
                                               href="<?= base_url('hutangpiutang/detail/' . $row['id']) ?>"
                                               title="Tandai lunas legacy tanpa pembayaran, jurnal, atau perubahan saldo kas/bank">
                                                <iconify-icon icon="solar:check-circle-linear" aria-hidden="true"></iconify-icon>
                                                Tandai lunas legacy
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($canInput) : ?>
        <div class="modal fade" id="input-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Input Hutang / Piutang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= view('hutangpiutang/_input_form', [
                            'input_types' => $input_types ?? [],
                            'pelanggan' => $pelanggan ?? [],
                            'pegawai' => $pegawai ?? [],
                            'suplier' => $suplier ?? [],
                            'teknisi' => $teknisi ?? [],
                            'bank' => $bank ?? [],
                            'units' => $units ?? [],
                            'unit_id' => $unit_id ?? null,
                            'in_modal' => true,
                            'default_sumber' => $isPiutang ? 'piutang_pelanggan' : 'jasa_teknisi',
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (function_exists('old') && old('sumber_tipe')) : ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById('input-modal');
                    if (el && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(el).show(); }
                });
            </script>
        <?php endif; ?>

        <div class="modal fade" id="bayar-modal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/bayar') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hutang_piutang_id" id="bayar-id">
                    <div class="modal-header">
                        <h5 class="modal-title">Pembayaran <span id="bayar-kode"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong id="bayar-pihak"></strong><br><span class="text-muted">Sisa: <span id="bayar-sisa"></span></span></p>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Bayar</label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bayar Tunai</label>
                            <input type="text" name="bayar_tunai" id="bayar-tunai" class="form-control" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bank</label>
                            <select name="bank_idbank" class="form-select">
                                <option value="">-</option>
                                <?php foreach (($bank ?? []) as $b) : ?>
                                    <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bayar Bank</label>
                            <input type="text" name="bayar_bank" id="bayar-bank" class="form-control" value="0">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = new bootstrap.Modal(document.getElementById('bayar-modal'));
                document.querySelectorAll('.btn-bayar').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('bayar-id').value = btn.dataset.id;
                        document.getElementById('bayar-kode').textContent = btn.dataset.kode;
                        document.getElementById('bayar-pihak').textContent = btn.dataset.pihak;
                        document.getElementById('bayar-sisa').textContent = 'Rp ' + Number(btn.dataset.sisa).toLocaleString('id-ID');
                        document.getElementById('bayar-tunai').value = btn.dataset.sisa;
                        document.getElementById('bayar-bank').value = 0;
                        modal.show();
                    });
                });
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.hp-chip-input').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!input.form) {
                        return;
                    }
                    if (typeof input.form.requestSubmit === 'function') {
                        input.form.requestSubmit();
                    } else {
                        input.form.submit();
                    }
                });
            });
        });
    </script>
</div>
