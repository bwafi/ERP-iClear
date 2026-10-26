<?php

use App\Services\Finance\HutangPiutangService;

$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');

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
$tglWaktu = static function ($value) use ($bulanId): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || substr($value, 0, 10) === '0000-00-00') {
        return '—';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }

    return date('j', $ts) . ' ' . $bulanId[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H:i', $ts);
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

$jenis          = (string) ($row->jenis ?? 'hutang');
$isPiutang      = $jenis === 'piutang';
$status         = (string) ($row->status ?? '');
$statusClass    = $status === HutangPiutangService::STATUS_LUNAS
    ? 'is-success'
    : ($status === HutangPiutangService::STATUS_SEBAGIAN ? 'is-warning' : 'is-neutral');
$statusLabel    = HutangPiutangService::labelStatus($status);
$sumberLabel    = HutangPiutangService::labelSumber((string) ($row->sumber_tipe ?? ''));
$scope          = (string) ($row->scope ?? 'active');
$scopeLabel     = ['legacy' => 'Legacy', 'opening' => 'Opening'][$scope] ?? '';
$authoritative  = in_array($row->sumber_tipe, HutangPiutangService::AUTHORITATIVE_SUMBER, true);
$isMutasi       = $row->sumber_tipe === HutangPiutangService::SUMBER_MUTASI;
$unitName       = trim((string) ($unit->NAMA_UNIT ?? '')) ?: 'Tanpa unit';
$namaPihak      = trim((string) ($row->nama_pihak ?? '')) ?: 'Tanpa nama';
$due            = $dueMeta($row->jatuh_tempo ?? null);
$outstanding    = $status !== HutangPiutangService::STATUS_LUNAS && (float) ($row->sisa ?? 0) > 0;
if (! $outstanding && $due['class'] !== 'is-overdue') {
    // Dokumen selesai bukan lagi "jatuh tempo"; hitung mundur disembunyikan,
    // tetapi keterlambatan tetap ditampilkan.
    $due['note']  = '';
    $due['class'] = '';
}
$dueNote        = $due['note'];
$total          = max(0, (float) ($row->total ?? 0));
$dibayar        = (float) ($row->total_dibayar ?? 0);
$sisa           = max(0, (float) ($row->sisa ?? 0));
$persen         = $total > 0
    ? min(100, max(0, (int) round($dibayar / $total * 100)))
    : ($status === HutangPiutangService::STATUS_LUNAS ? 100 : 0);

// Pelunasan legacy: hanya hutang Outstanding bertanggal <= cut-off Finance,
// dan hanya yang BUKAN proksi (proksi ditimpa sync dari transaksi sumber).
$cutoffFinance = \App\Services\Finance\FinanceScopeService::cutoffDate();
// Pakai helper service, bukan `$row->cutoff_reason` mentah: pembatalan
// mengosongkan cutoff_reason tetapi tetap meninggalkan jejak settlement,
// jadi hanya helper yang tahu bedanya "aktif" vs "pernah dibatalkan".
$hpService     = new HutangPiutangService();
$settledLegacy = $hpService->settlementLegacyAktif($row);
$pernahLegacy  = $settledLegacy
    || (! empty($row->tanggal_settlement_legacy) && ! empty($row->legacy_dibatalkan_at));
$bisaLegacy    = $can_input && $outstanding && ! $settledLegacy
    && (int) $row->is_projection === 0
    && \App\Services\Finance\FinanceScopeService::tanggalStr($row->tanggal) <= $cutoffFinance;
$alasanLegacy  = 'Hanya hutang outstanding bertanggal pada atau sebelum ' . $cutoffFinance . ' yang bisa ditandai lunas legacy.';
if ($can_input && ! $settledLegacy && (int) $row->is_projection === 1) {
    $alasanLegacy = 'Posisi ini proksi dari transaksi sumber, sehingga penandaan akan ditimpa saat sinkronisasi.';
} elseif ($can_input && ! $settledLegacy && ! $outstanding) {
    $alasanLegacy = 'Hutang ini sudah lunas.';
}

$bisaBayar     = $can_input && $authoritative && $status !== HutangPiutangService::STATUS_LUNAS
    && $row->sumber_tipe !== HutangPiutangService::SUMBER_KASBON && $outstanding;
$bisaKompensasi = $can_input && $authoritative && $status !== HutangPiutangService::STATUS_LUNAS && !empty($lawan_kompensasi);

// Pelunasan antar unit langsung dari halaman ini. Form hanya dirender bila
// controller sudah menyiapkan rekening pengirim/penerima + token.
$antarUnit    = $antar_unit ?? null;
$unitMapAu    = $antarUnit['unit_map'] ?? [];
$labelAkunAu  = static function ($a) use ($unitMapAu) {
    $unitId   = (int) ($a->unit_id ?? 0);
    $unitNama = $unitId > 0 ? ($unitMapAu[$unitId] ?? ('Unit ' . $unitId)) : 'Pusat';

    return $unitNama . ' · ' . ($a->nama_akun ?? '-') . ' (' . ($a->tipe ?? '-') . ')';
};
$bisaAntarUnit = $can_input && $isMutasi && $outstanding && !empty($antarUnit);
$lawanUnitId   = (int) ($row->lawan_unit_id ?? 0);
$lawanUnit     = $lawanUnitId > 0 ? ($unitMapAu[$lawanUnitId] ?? ('Unit ' . $lawanUnitId)) : null;
$pasanganHutang  = $antarUnit['hutang'] ?? null;
$pasanganPiutang = $antarUnit['piutang'] ?? null;

$labelPembayaran = static function ($sumber): string {
    $sumber = trim((string) ($sumber ?? ''));
    if ($sumber === '') {
        return 'Pembayaran';
    }

    $map = [
        'antar_unit' => 'Antar unit',
        'kas_bank'   => 'Kas & Bank',
        'legacy'     => 'Legacy',
        'manual'     => 'Manual',
    ];

    return $map[$sumber] ?? ucwords(str_replace(['_', '-'], ' ', $sumber));
};

$pembayaranTerakhir = ! empty($pembayaran) ? $pembayaran[array_key_last($pembayaran)] : null;
$hasUraian          = trim((string) ($row->uraian ?? '')) !== '';
$hasKeterangan      = trim((string) ($row->keterangan ?? '')) !== '';
$hasNotes           = $hasUraian || $hasKeterangan || $isMutasi;
$notesSubtitle      = ($hasUraian || $hasKeterangan)
    ? 'Uraian dan keterangan yang tersimpan.'
    : 'Konteks posisi antar unit.';
?>

<?= view('hutangpiutang/_list_theme') ?>
<?= view('hutangpiutang/_detail_theme') ?>

<div class="hp-list hp-detail <?= $isPiutang ? 'hp-list--piutang' : 'hp-list--hutang' ?>">
    <section class="hp-hero">
        <div class="hp-hero__main">
            <nav class="hp-crumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                    <li><a href="<?= base_url('hutangpiutang/' . $jenis) ?>"><?= esc(ucfirst($jenis)) ?></a></li>
                    <li aria-current="page"><?= esc($row->kode) ?></li>
                </ol>
            </nav>

            <div class="hp-hero__title-row">
                <span class="hp-hero__mark" aria-hidden="true">
                    <iconify-icon icon="<?= $isPiutang ? 'solar:hand-money-bold-duotone' : 'solar:wallet-money-bold-duotone' ?>"></iconify-icon>
                </span>
                <div class="hp-detail__heading">
                    <h1>Detail <?= $isPiutang ? 'Piutang' : 'Hutang' ?></h1>
                    <p class="hp-detail__subline">
                        <span class="hp-detail__code"><?= esc($row->kode) ?></span>
                        <span><?= esc($namaPihak) ?></span>
                        <span><?= esc($unitName) ?></span>
                    </p>
                    <div class="hp-detail__badges">
                        <span class="hp-status <?= $statusClass ?>"><?= esc($statusLabel) ?></span>
                        <span class="hp-tag"><?= esc($sumberLabel) ?></span>
                        <?php if ($scopeLabel !== '') : ?>
                            <span class="hp-tag"><?= esc($scopeLabel) ?></span>
                        <?php endif; ?>
                        <?php if ((int) ($row->is_projection ?? 0) === 1) : ?>
                            <span class="hp-tag">Proyeksi</span>
                        <?php endif; ?>
                        <?php if ($settledLegacy) : ?>
                            <span class="hp-tag hp-tag--warn">Lunas legacy</span>
                        <?php elseif ($pernahLegacy) : ?>
                            <span class="hp-tag">Legacy dibatalkan</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="hp-hero__actions hp-detail__actions">
            <a class="hp-btn hp-btn--ghost" href="<?= base_url('hutangpiutang/' . $jenis) ?>">
                <iconify-icon icon="solar:alt-arrow-left-linear" aria-hidden="true"></iconify-icon>
                Kembali
            </a>
            <a class="hp-btn hp-btn--ghost" href="<?= base_url('hutangpiutang/cetak/' . $row->id) ?>" target="_blank" rel="noopener">
                <iconify-icon icon="solar:printer-bold" aria-hidden="true"></iconify-icon>
                Cetak
            </a>
            <?php if ($bisaBayar) : ?>
                <button type="button" class="hp-btn hp-btn--primary" data-bs-toggle="modal" data-bs-target="#bayar-modal">
                    <iconify-icon icon="solar:wallet-money-bold" aria-hidden="true"></iconify-icon>
                    Catat Pembayaran
                </button>
            <?php elseif ($bisaAntarUnit) : ?>
                <button type="button" class="hp-btn hp-btn--primary" data-bs-toggle="modal" data-bs-target="#antar-unit-modal">
                    <iconify-icon icon="solar:transfer-horizontal-bold-duotone" aria-hidden="true"></iconify-icon>
                    Catat Pelunasan Antar Unit
                </button>
            <?php endif; ?>
            <?php if ($bisaKompensasi) : ?>
                <button type="button" class="hp-btn hp-btn--ghost" data-bs-toggle="modal" data-bs-target="#kompensasi-modal">
                    <iconify-icon icon="solar:refresh-circle-bold" aria-hidden="true"></iconify-icon>
                    Kompensasi
                </button>
            <?php endif; ?>
            <?php if ($settledLegacy && $can_input) : ?>
                <button type="button" class="hp-btn hp-btn--warn" data-bs-toggle="modal" data-bs-target="#batal-legacy-modal">
                    <iconify-icon icon="solar:undo-left-bold" aria-hidden="true"></iconify-icon>
                    Batalkan Pelunasan Legacy
                </button>
            <?php elseif ($bisaLegacy) : ?>
                <button type="button" class="hp-btn hp-btn--ghost" data-bs-toggle="modal" data-bs-target="#legacy-modal">
                    <iconify-icon icon="solar:shield-check-bold" aria-hidden="true"></iconify-icon>
                    Tandai Lunas Legacy
                </button>
            <?php endif; ?>
        </div>
    </section>

    <section class="hp-detail__balance" aria-label="Ringkasan nilai">
        <div class="hp-detail__balance-main">
            <div class="hp-detail__eyebrow"><?= $isPiutang ? 'Sisa piutang' : 'Sisa hutang' ?></div>
            <div class="hp-detail__balance-value <?= $outstanding ? '' : 'is-clear' ?>"><?= $rp($sisa) ?></div>
            <div class="hp-detail__balance-sub">
                <span><?= $outstanding ? ($isPiutang ? 'Belum diterima' : 'Belum dibayar') : 'Tidak ada sisa' ?></span>
                <span aria-hidden="true">·</span>
                <span>Total <?= $rp($total) ?></span>
            </div>
            <?php if ($settledLegacy && $dibayar <= 0) : ?>
                <div class="hp-detail__settlement">
                    <iconify-icon icon="solar:shield-check-bold" aria-hidden="true"></iconify-icon>
                    <span>Ditutup lewat settlement legacy — bukan pembayaran.</span>
                </div>
            <?php else : ?>
                <div class="hp-detail__progress">
                    <div class="hp-detail__progress-head">
                        <span><?= $isPiutang ? 'Diterima' : 'Dibayar' ?> <?= $rp($dibayar) ?></span>
                        <strong><?= $persen ?>%</strong>
                    </div>
                    <div class="hp-detail__bar" role="progressbar" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progress pembayaran">
                        <span style="width: <?= $persen ?>%"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hp-detail__balance-side">
            <div class="hp-detail__side-row">
                <span class="hp-detail__side-label">Status</span>
                <span class="hp-status <?= $statusClass ?>"><?= esc($statusLabel) ?></span>
            </div>
            <div class="hp-detail__side-row">
                <span class="hp-detail__side-label">Jatuh tempo</span>
                <div class="hp-due <?= esc($due['class']) ?>">
                    <span class="hp-due__date"><?= esc($due['date']) ?></span>
                    <?php if ($dueNote !== '') : ?>
                        <small><?= esc($dueNote) ?></small>
                    <?php endif; ?>
                    <?php if ($can_edit_jatuh_tempo ?? false) : ?>
                        <form method="post" action="<?= base_url('hutangpiutang/ubah-jatuh-tempo') ?>" class="d-flex gap-2 mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
                            <input type="date" name="jatuh_tempo" class="form-control form-control-sm"
                                   value="<?= esc(substr((string) ($row->jatuh_tempo ?? ''), 0, 10)) ?>" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Ubah</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hp-detail__side-row">
                <span class="hp-detail__side-label">Pembayaran terakhir</span>
                <div class="hp-detail__side-value">
                    <?php if ($pembayaranTerakhir) : ?>
                        <strong><?= $rp($pembayaranTerakhir->jumlah_bayar ?? 0) ?></strong>
                        <small><?= esc($tgl($pembayaranTerakhir->tanggal_bayar ?? null)) ?></small>
                    <?php else : ?>
                        <span class="hp-detail__side-value-empty">Belum ada</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($can_input && $outstanding && $isMutasi && empty($antarUnit)) : ?>
        <div class="hp-note hp-note--warn">
            <iconify-icon icon="solar:danger-triangle-bold" aria-hidden="true"></iconify-icon>
            <p class="mb-0">Pelunasan antar unit belum bisa dicatat dari halaman ini — rekening atau posisi tidak memenuhi syarat.</p>
        </div>
    <?php endif; ?>
    <?php if ($outstanding && $isMutasi && $isPiutang) : ?>
        <div class="hp-note">
            <iconify-icon icon="solar:info-circle-bold-duotone" aria-hidden="true"></iconify-icon>
            <p class="mb-0">Pelunasan dilakukan oleh unit yang berhutang. Form pelunasan mencatat dana dari unit penghutang ke unit ini.</p>
        </div>
    <?php endif; ?>
    <?php if ($can_input && ! $settledLegacy && ! $bisaLegacy && ! $outstanding) : ?>
        <div class="hp-note">
            <iconify-icon icon="solar:info-circle-bold-duotone" aria-hidden="true"></iconify-icon>
            <p class="mb-0"><?= esc($alasanLegacy) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($row->sumber_tipe === HutangPiutangService::SUMBER_PEMBELIAN && $outstanding) : ?>
        <div class="hp-note">
            <iconify-icon icon="solar:info-circle-bold-duotone" aria-hidden="true"></iconify-icon>
            <p class="mb-0">Pembayaran hutang supplier dicatat lewat <a href="<?= base_url('daftar_tagihan') ?>">Daftar Tagihan Hutang</a>.</p>
        </div>
    <?php endif; ?>

    <div class="hp-detail__grid">
        <section class="hp-panel hp-detail__panel" aria-labelledby="hp-doc-title">
            <div class="hp-detail__panel-head">
                <span class="hp-detail__panel-icon" aria-hidden="true">
                    <iconify-icon icon="solar:document-text-bold"></iconify-icon>
                </span>
                <div>
                    <h2 id="hp-doc-title">Informasi dokumen</h2>
                    <p>Rincian posisi dan asal transaksi.</p>
                </div>
            </div>

            <dl class="hp-detail__facts">
                <div class="hp-detail__fact">
                    <dt>Kode</dt>
                    <dd><?= esc($row->kode) ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Pihak</dt>
                    <dd><?= esc($namaPihak) ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Unit</dt>
                    <dd><?= esc($unitName) ?></dd>
                </div>
                <?php if ($isMutasi && $lawanUnit !== null) : ?>
                    <div class="hp-detail__fact">
                        <dt>Unit lawan</dt>
                        <dd><?= esc($lawanUnit) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="hp-detail__fact">
                    <dt>Tanggal</dt>
                    <dd><?= esc($tgl($row->tanggal ?? null)) ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Jatuh tempo</dt>
                    <dd>
                        <?= esc($due['date']) ?>
                        <?php if ($dueNote !== '') : ?>
                            <span class="hp-detail__fact-note">(<?= esc($dueNote) ?>)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Sumber</dt>
                    <dd><?= esc($sumberLabel) ?></dd>
                </div>
                <?php if ($scopeLabel !== '') : ?>
                    <div class="hp-detail__fact">
                        <dt>Scope</dt>
                        <dd><?= esc($scopeLabel) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($isMutasi && $pasanganHutang && $pasanganPiutang) : ?>
                    <div class="hp-detail__fact hp-detail__fact--wide">
                        <dt>Pasangan</dt>
                        <dd class="hp-detail__pair">
                            <span class="hp-detail__pair-item">
                                <span class="hp-detail__pair-label">Hutang</span>
                                <a href="<?= base_url('hutangpiutang/detail/' . (int) $pasanganHutang->id) ?>"><?= esc($pasanganHutang->kode) ?></a>
                            </span>
                            <span class="hp-detail__pair-item">
                                <span class="hp-detail__pair-label">Piutang</span>
                                <a href="<?= base_url('hutangpiutang/detail/' . (int) $pasanganPiutang->id) ?>"><?= esc($pasanganPiutang->kode) ?></a>
                            </span>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <div class="hp-detail__stack">
            <section class="hp-panel hp-detail__panel" aria-labelledby="hp-history-title">
            <div class="hp-detail__panel-head">
                <span class="hp-detail__panel-icon" aria-hidden="true">
                    <iconify-icon icon="solar:history-bold-duotone"></iconify-icon>
                </span>
                <div>
                    <h2 id="hp-history-title">Riwayat pembayaran</h2>
                    <p><?= count($pembayaran) ?> transaksi tercatat.</p>
                </div>
            </div>

            <?php if (empty($pembayaran)) : ?>
                <div class="hp-detail__empty">
                    <span class="hp-detail__empty-icon" aria-hidden="true">
                        <iconify-icon icon="solar:bill-list-bold-duotone"></iconify-icon>
                    </span>
                    <h3>Belum ada pembayaran</h3>
                    <p><?= $outstanding ? 'Pembayaran yang dicatat akan muncul di sini.' : 'Posisi ini sudah tidak memiliki sisa.' ?></p>
                </div>
            <?php else : ?>
                <ol class="hp-detail__timeline">
                    <?php foreach ($pembayaran as $p) :
                        $tunai = (float) ($p->bayar_tunai ?? 0);
                        $bank  = (float) ($p->bayar_bank ?? 0);
                    ?>
                        <li class="hp-detail__timeline-item">
                            <span class="hp-detail__timeline-dot" aria-hidden="true"></span>
                            <div class="hp-detail__timeline-body">
                                <div class="hp-detail__timeline-head">
                                    <strong><?= $rp($p->jumlah_bayar ?? 0) ?></strong>
                                    <span><?= esc($tgl($p->tanggal_bayar ?? null)) ?></span>
                                </div>
                                <div class="hp-detail__timeline-meta">
                                    <?php if ($tunai > 0) : ?>
                                        <span>Tunai <?= $rp($tunai) ?></span>
                                    <?php endif; ?>
                                    <?php if ($bank > 0) : ?>
                                        <span>Bank <?= $rp($bank) ?></span>
                                    <?php endif; ?>
                                    <span class="hp-tag"><?= esc($labelPembayaran($p->sumber ?? '')) ?></span>
                                </div>
                                <?php if (trim((string) ($p->keterangan ?? '')) !== '') : ?>
                                    <p class="hp-detail__timeline-note"><?= esc($p->keterangan) ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            </section>

            <?php if (! empty($row->created_at) || ! empty($row->updated_at)) : ?>
                <section class="hp-panel hp-detail__panel hp-detail__trail" aria-labelledby="hp-trail-title">
                    <div class="hp-detail__panel-head">
                        <span class="hp-detail__panel-icon" aria-hidden="true">
                            <iconify-icon icon="solar:clock-circle-bold"></iconify-icon>
                        </span>
                        <div>
                            <h2 id="hp-trail-title">Jejak dokumen</h2>
                            <p>Waktu pencatatan sistem.</p>
                        </div>
                    </div>
                    <dl class="hp-detail__facts">
                        <?php if (! empty($row->created_at)) : ?>
                            <div class="hp-detail__fact">
                                <dt>Dibuat</dt>
                                <dd><?= esc($tglWaktu($row->created_at)) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($row->updated_at)) : ?>
                            <div class="hp-detail__fact">
                                <dt>Diperbarui</dt>
                                <dd><?= esc($tglWaktu($row->updated_at)) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasNotes) : ?>
        <section class="hp-panel hp-detail__panel hp-detail__notes" aria-labelledby="hp-notes-title">
            <div class="hp-detail__panel-head">
                <span class="hp-detail__panel-icon" aria-hidden="true">
                    <iconify-icon icon="solar:file-text-line-duotone"></iconify-icon>
                </span>
                <div>
                    <h2 id="hp-notes-title">Catatan dokumen</h2>
                    <p><?= esc($notesSubtitle) ?></p>
                </div>
            </div>

            <?php if ($hasUraian || $hasKeterangan) : ?>
                <dl class="hp-detail__facts hp-detail__facts--notes">
                    <?php if ($hasUraian) : ?>
                        <div class="hp-detail__fact hp-detail__fact--wide">
                            <dt>Uraian</dt>
                            <dd class="hp-detail__prose"><?= esc($row->uraian) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($hasKeterangan) : ?>
                        <div class="hp-detail__fact hp-detail__fact--wide">
                            <dt>Keterangan</dt>
                            <dd class="hp-detail__prose"><?= esc($row->keterangan) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>

            <?php if ($isMutasi) : ?>
                <div class="hp-detail__context">
                    <iconify-icon icon="solar:transfer-horizontal-bold-duotone" aria-hidden="true"></iconify-icon>
                    <p class="mb-0">
                        <?= $isPiutang
                            ? 'Sisi piutang dari mutasi antar unit. Pelunasan dicatat oleh unit yang berhutang dan mengurangi sisa pasangannya.'
                            : 'Sisi hutang dari mutasi antar unit. Pelunasan mengurangi sisa hutang ini sekaligus sisa piutang pasangannya.' ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($settledLegacy || $pernahLegacy) : ?>
        <section class="hp-panel hp-detail__panel hp-detail__audit <?= $settledLegacy ? 'is-warning' : 'is-neutral' ?>" aria-labelledby="hp-audit-title">
            <div class="hp-detail__panel-head">
                <span class="hp-detail__panel-icon" aria-hidden="true">
                    <iconify-icon icon="<?= $settledLegacy ? 'solar:shield-check-bold' : 'solar:refresh-circle-bold' ?>"></iconify-icon>
                </span>
                <div>
                    <h2 id="hp-audit-title"><?= $settledLegacy ? 'Lunas legacy' : 'Pelunasan legacy dibatalkan' ?></h2>
                    <p>Penandaan administratif, bukan transaksi pembayaran.</p>
                </div>
                <span class="hp-tag <?= $settledLegacy ? 'hp-tag--warn' : '' ?>">
                    <?= $settledLegacy ? 'Settlement aktif' : 'Dibatalkan' ?>
                </span>
            </div>

            <dl class="hp-detail__facts">
                <div class="hp-detail__fact">
                    <dt>Tanggal settlement</dt>
                    <dd><?= esc($tgl($row->tanggal_settlement_legacy ?? null)) ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Jenis settlement</dt>
                    <dd><span class="hp-tag">legacy</span></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Dicatat oleh</dt>
                    <dd><?= esc($row->cutoff_closed_by ? '#' . $row->cutoff_closed_by : '—') ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Waktu pencatatan</dt>
                    <dd><?= esc($tglWaktu($row->cutoff_closed_at ?? null)) ?></dd>
                </div>
                <div class="hp-detail__fact">
                    <dt>Sisa sebelum settlement</dt>
                    <dd><?= $rp($row->sisa_legacy_sebelum ?? 0) ?></dd>
                </div>
                <?php if (! $settledLegacy) : ?>
                    <div class="hp-detail__fact">
                        <dt>Status</dt>
                        <dd>Sudah dibatalkan — kembali outstanding</dd>
                    </div>
                    <div class="hp-detail__fact">
                        <dt>Waktu pembatalan</dt>
                        <dd><?= esc($tglWaktu($row->legacy_dibatalkan_at ?? null)) ?></dd>
                    </div>
                    <div class="hp-detail__fact">
                        <dt>Dibatalkan oleh</dt>
                        <dd><?= esc($row->legacy_dibatalkan_by ? '#' . $row->legacy_dibatalkan_by : '—') ?></dd>
                    </div>
                    <div class="hp-detail__fact hp-detail__fact--wide">
                        <dt>Alasan pembatalan</dt>
                        <dd class="hp-detail__prose"><?= esc($row->legacy_alasan_pembatalan ?: '—') ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <p class="hp-detail__audit-note">
                Penandaan administratif — bukan transaksi pembayaran. Tidak ada jurnal, tidak ada movement Kas/Bank, dan saldo kas/bank tidak berubah.
                <?php if (! $settledLegacy) : ?>
                    Jejak settlement tetap disimpan sebagai riwayat.
                <?php endif; ?>
            </p>
        </section>
    <?php endif; ?>
<?php if ($can_input && $authoritative && $row->status !== 'lunas') : ?>
<div class="modal fade" id="bayar-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/bayar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Pembayaran <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Sisa: <strong><?= $rp($row->sisa) ?></strong></p>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bayar Tunai</label>
                    <input type="text" name="bayar_tunai" id="d-tunai" class="form-control" value="0">
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
                    <input type="text" name="bayar_bank" id="d-bank" class="form-control" value="0">
                </div>
                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($antarUnit)) : ?>
<div class="modal fade" id="antar-unit-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('kas_bank/antar-unit/save') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $antarUnit['hutang']->id ?>">
            <input type="hidden" name="submit_token" value="<?= esc($antarUnit['submit_token']) ?>">
            <input type="hidden" name="kembali_ke" value="<?= esc($antarUnit['kembali_ke']) ?>">
            <div class="modal-header">
                <h5 class="modal-title">Pelunasan Antar Unit — <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Hutang <strong><?= esc($antarUnit['hutang']->kode) ?></strong>
                    (<?= esc($unitMapAu[(int) $antarUnit['hutang']->unit_id] ?? 'Unit ' . (int) $antarUnit['hutang']->unit_id) ?>)
                    dibayar ke piutang <strong><?= esc($antarUnit['piutang']->kode) ?></strong>
                    (<?= esc($unitMapAu[(int) $antarUnit['piutang']->unit_id] ?? 'Unit ' . (int) $antarUnit['piutang']->unit_id) ?>).
                    Sisa maksimal: <strong><?= $rp($antarUnit['batas_jumlah']) ?></strong>.
                </p>

                <?php if (empty($antarUnit['akun_pengirim']) || empty($antarUnit['akun_penerima'])) : ?>
                    <div class="alert alert-warning py-2 px-3 small">
                        Belum ada rekening kas/bank yang bisa dipakai untuk unit ini.
                        Hubungi Finance untuk melengkapi rekening lalu coba lagi.
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control"
                           value="<?= date('Y-m-d') ?>" min="<?= esc($antarUnit['periode_mulai']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Jumlah</label>
                    <input type="text" name="jumlah" class="form-control text-end"
                           value="<?= (int) $antarUnit['batas_jumlah'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rekening pengirim (unit penghutang)</label>
                    <select name="akun_pengirim_id" class="form-select" required>
                        <option value="">-- Pilih rekening --</option>
                        <?php foreach ($antarUnit['akun_pengirim'] as $a) : ?>
                            <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkunAu($a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rekening penerima (unit berpiutang)</label>
                    <select name="akun_penerima_id" class="form-select" required>
                        <option value="">-- Pilih rekening --</option>
                        <?php foreach ($antarUnit['akun_penerima'] as $a) : ?>
                            <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkunAu($a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Pilih rekening yang sama bila pelunasan hanya menggeser hak saldo antar unit tanpa memindahkan uang fisik.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Bukti transfer <span class="text-muted">(opsional — JPG/PNG/PDF)</span></label>
                    <input type="file" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.webp">
                </div>

                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" <?= (empty($antarUnit['akun_pengirim']) || empty($antarUnit['akun_penerima'])) ? 'disabled' : '' ?>>Simpan Pelunasan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($bisaKompensasi) : ?>
<div class="modal fade" id="kompensasi-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/kompensasi') ?>">
            <?= csrf_field() ?>
            <?php if ($row->jenis === 'hutang') : ?>
                <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <?php else : ?>
                <input type="hidden" name="lawan_id" value="<?= (int) $row->id ?>">
            <?php endif; ?>
            <div class="modal-header">
                <h5 class="modal-title">Kompensasi <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Sisa <?= esc($row->jenis) ?> ini: <strong><?= $rp($row->sisa) ?></strong>. Pilih posisi lawan milik pihak yang sama.</p>
                <div class="mb-3">
                    <label class="form-label">Posisi <?= $row->jenis === 'hutang' ? 'Piutang' : 'Hutang' ?> Lawan</label>
                    <select name="<?= $row->jenis === 'hutang' ? 'lawan_id' : 'hutang_piutang_id' ?>" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($lawan_kompensasi as $l) : ?>
                            <option value="<?= (int) $l->id ?>"><?= esc($l->kode . ' — ' . $l->nama_pihak . ' — sisa ' . $rp($l->sisa)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nilai Kompensasi</label>
                    <input type="text" name="jumlah" class="form-control" value="0" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kompensasi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($bisaLegacy) : ?>
<div class="modal fade" id="legacy-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/tandai-lunas-legacy') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Tandai Lunas Legacy — <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-ds-dismiss="modal" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 px-3 small">
                    <strong>Ini bukan transaksi pembayaran.</strong>
                    Aksi ini hanya menandai posisi hutang sebagai sudah lunas secara faktual
                    (mis. sudah dikompensasi atau saling hapus). Tidak ada jurnal,
                    tidak ada pembukuan kas/bank, dan saldo Kas/Bank
                    <strong>tidak berubah</strong>.
                </div>

                <p class="mb-3">Sisa outstanding: <strong><?= $rp($row->sisa) ?></strong></p>

                <div class="mb-3">
                    <label class="form-label">Tanggal Settlement</label>
                    <input type="date" name="tanggal_settlement" class="form-control"
                           value="<?= esc($row->tanggal_settlement_legacy ?: $cutoffFinance) ?>"
                           min="<?= esc($row->tanggal) ?>" max="<?= esc($cutoffFinance) ?>" required>
                    <div class="form-text">
                        Harus pada atau sebelum Finance cut-off <strong><?= esc($cutoffFinance) ?></strong>.
                        Settlement setelah cut-off bukan transaksi legacy.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Keterangan / Alasan</label>
                    <textarea name="keterangan" class="form-control" rows="2"
                              placeholder="mis. Sudah dikompensasi dengan mutasi antar unit, diverifikasi Finance."></textarea>
                    <div class="form-text">Tercatat permanen di audit trail bersama nama dan waktu pencatatan.</div>
                </div>

                <div class="small text-muted">
                    Terdata pada tabel <code>hutang_piutang</code>: status, sisa,
                    tanggal settlement, jenis settlement <code>legacy</code>, dan pencatat.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">
                    Tandai Lunas Legacy
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($settledLegacy && $can_input) : ?>
<div class="modal fade" id="batal-legacy-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/batalkan-lunas-legacy') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Batalkan Pelunasan Legacy — <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 px-3 small">
                    Posisi hutang akan dikembalikan jadi outstanding sebesar
                    <strong><?= $rp($row->sisa_legacy_sebelum) ?></strong>.
                    Settlement dicatat pada
                    <strong><?= $row->tanggal_settlement_legacy ? date('d-m-Y', strtotime($row->tanggal_settlement_legacy)) : '-' ?></strong>.
                    <br><br>
                    Jejak settlement <strong>tidak dihapus</strong> — hanya penanda aktifnya yang dicabut,
                    dan pembatalan ini dicatat sebagai audit trail terpisah.
                    Tidak ada jurnal, tidak ada kas/bank, saldo tidak berubah.
                </div>

                <div class="mb-3">
                    <label class="form-label">Alasan Pembatalan</label>
                    <textarea name="alasan" class="form-control" rows="2"
                              placeholder="mis. Bukan faktual lunas — masih ada sisa yang belum diterima."></textarea>
                    <div class="form-text">
                        Disimpan permanen bersama nama dan waktu pembatalan.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-outline-warning">Ya, Batalkan Pelunasan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($antarUnit)) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (new URLSearchParams(window.location.search).get('bayar') !== '1') {
        return;
    }
    var el = document.getElementById('antar-unit-modal');
    if (el && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
});
</script>
<?php endif; ?>

</div>
