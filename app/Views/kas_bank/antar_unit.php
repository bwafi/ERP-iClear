<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$canTransaksi = $can_transaksi ?? false;
$canKelola = $bisa_pilih_unit ?? false;
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a;
}
$kelAkun = static function ($a) use ($unitMap) {
    return ((isset($unitMap[(int) $a->unit_id])) ? $unitMap[(int) $a->unit_id] : 'Pusat') . ' · ' . $a->nama_akun . ' (' . $a->tipe . ')';
};
$labelStatus = static fn($s) => ['belum_lunas' => 'Belum Lunas', 'sebagian' => 'Dibayar Sebagian', 'lunas' => 'Lunas'][$s] ?? $s;
$badgeStatus = static fn($s) => ['belum_lunas' => 'bg-warning-subtle text-warning-emphasis', 'sebagian' => 'bg-primary-subtle text-primary', 'lunas' => 'bg-success-subtle text-success'][$s] ?? 'bg-secondary-subtle text-secondary';
$hpOpen = array_filter($hp_hutang ?? [], static fn($h) => (int) $h->sisa > 0 && $h->status !== 'lunas');
$hpOpenCount = count($hpOpen);
$hpOpenTotal = array_sum(array_map(fn($h) => (int)$h->sisa, $hpOpen));
$piOpen = array_filter($hp_piutang ?? [], static fn($h) => (int)$h->sisa > 0 && $h->status !== 'lunas');
$piOpenCount = count($piOpen);
$piOpenTotal = array_sum(array_map(fn($h) => (int)$h->sisa, $piOpen));
$unitNamaTerpilih = (($unit_terpilih ?? 0) ? ($unitMap[(int)($unit_terpilih ?? 0)] ?? 'Cabang') : 'Semua Cabang');
?>

<style>
    /* Paksa background serba putih dan clean card */
    .kb-clean-card {
        background-color: #ffffff !important;
        border: 1px solid var(--bs-border-color-translucent);
        border-radius: .75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        overflow: hidden;
    }

    .kb-au-mode {
        border: 1.5px solid var(--bs-border-color-translucent);
        border-radius: .65rem;
        padding: .9rem 1rem;
        background: #ffffff;
        position: relative;
        transition: .15s;
    }

    .kb-au-mode.is-transfer {
        border-color: rgba(37, 99, 235, .25);
        background: #ffffff;
    }

    .kb-au-mode.is-atribusi {
        border-color: rgba(245, 158, 11, .3);
        background: #ffffff;
    }

    .kb-metric-mini {
        border: 1px solid var(--bs-border-color-translucent);
        border-radius: .65rem;
        padding: .85rem 1rem;
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .03);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .kb-metric-mini .label {
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .kb-metric-mini .value {
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1;
    }

    .kb-metric-mini .sub {
        font-size: .72rem;
        color: var(--bs-secondary-color);
        margin-top: .2rem;
    }

    .kb-workspace {
        display: grid;
        grid-template-columns: 420px 1fr;
        gap: 1rem;
        align-items: start;
    }

    @media(max-width:1100px) {
        .kb-workspace {
            grid-template-columns: 1fr;
        }
    }

    .kb-clean-card .hd {
        padding: .85rem 1rem;
        border-bottom: 1px solid var(--bs-border-color-translucent);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        background: #ffffff;
    }

    .kb-clean-card .hd h2 {
        font-size: .9rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -.02em;
    }

    .kb-clean-card .hd p {
        font-size: .72rem;
        color: var(--bs-secondary-color);
        margin: .1rem 0 0;
    }

    .kb-live-badge {
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: .32rem .55rem;
        border-radius: 999px;
        border: 1px solid;
        white-space: nowrap;
    }

    .kb-live-badge.transfer {
        background: rgba(37, 99, 235, .08);
        border-color: rgba(37, 99, 235, .2);
        color: #2563eb;
    }

    .kb-live-badge.atribusi {
        background: rgba(245, 158, 11, .12);
        border-color: rgba(245, 158, 11, .3);
        color: #b45309;
    }

    .kb-live-badge.idle {
        background: #ffffff;
        border-color: var(--bs-border-color-translucent);
        color: var(--bs-secondary-color);
    }

    .kb-field-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .65rem;
    }

    @media(max-width:520px) {
        .kb-field-grid {
            grid-template-columns: 1fr;
        }
    }

    .kb-pair {
        display: grid;
        grid-template-columns: 1fr 28px 1fr;
        align-items: end;
        gap: .45rem;
    }

    .kb-pair-arrow {
        width: 28px;
        height: 32px;
        border: 1.5px solid var(--bs-border-color-translucent);
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #ffffff;
        color: var(--bs-secondary-color);
        margin-bottom: .15rem;
    }

    .kb-helper {
        font-size: .70rem;
        color: var(--bs-secondary-color);
        line-height: 1.45;
        margin-top: .3rem;
    }

    .kb-preview-empty {
        padding: 2.2rem 1.25rem;
        text-align: center;
        color: var(--bs-secondary-color);
        background: #ffffff;
    }

    .kb-preview-empty .ico {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        margin: 0 auto .75rem;
        background: #ffffff;
        border: 1px solid var(--bs-border-color-translucent);
    }

    .kb-hp-row {
        cursor: pointer;
        transition: .12s;
    }

    .kb-hp-row:hover {
        background: #f8fafc;
    }

    .kb-hp-row.is-selected {
        outline: 1.5px solid rgba(37, 99, 235, .3);
        outline-offset: -1.5px;
        background: rgba(37, 99, 235, .04);
    }

    .kb-tabs {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .kb-tabs .tab {
        font-size: .76rem;
        font-weight: 700;
        padding: .42rem .75rem;
        border-radius: 999px;
        border: 1px solid var(--bs-border-color-translucent);
        background: #ffffff;
        color: var(--bs-secondary-color);
        cursor: pointer;
    }

    .kb-tabs .tab.active {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
    }

    .kb-num {
        font-variant-numeric: tabular-nums;
        letter-spacing: -.02em;
    }
</style>

<div class="container-fluid px-0">

    <?php /* ==================== HERO HEADER ==================== */ ?>
    <div class="kb-clean-card mb-3">
        <div class="card-body p-4 bg-white">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <h4 class="fw-bold text-dark mb-1">Bayar Antar Unit</h4>
                    <p class="text-muted mb-0" style="font-size:.82rem">Melunasi hutang antar cabang dari <strong>mutasi stok</strong>. Pilih tagihan &rarr; pilih rekening &rarr; sistem tentukan otomatis apakah uang berpindah.</p>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-light text-dark border">Konteks: <?= esc($unitNamaTerpilih) ?></span>
                    <?php if ($hpOpenCount): ?>
                        <span class="badge bg-danger-subtle text-danger fw-semibold"><?= $hpOpenCount ?> hutang perlu dibayar</span>
                    <?php endif; ?>
                    <?php if (!$canTransaksi): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis">Mode lihat saja</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 border rounded-3 bg-white d-flex gap-3 align-items-start">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                            <iconify-icon icon="solar:transfer-horizontal-bold-duotone" width="20" height="20"></iconify-icon>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.82rem">Transfer Fisik — Uang Pindah</div>
                            <small class="text-muted" style="font-size:.74rem">Rekening pengirim &amp; penerima <strong>berbeda</strong>. Saldo kas/bank benar-benar berpindah.</small>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 border rounded-3 bg-white d-flex gap-3 align-items-start">
                        <div class="p-2 bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                            <iconify-icon icon="solar:calculator-minimalistic-bold-duotone" width="20" height="20"></iconify-icon>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.82rem">Atribusi — Tanpa Uang Pindah</div>
                            <small class="text-muted" style="font-size:.74rem">Rekening <strong>sama</strong>. Hanya hutang/piutang yang berkurang. Saldo tetap.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* ==================== METRICS ==================== */ ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="kb-metric-mini bg-white">
                <div>
                    <div class="label text-danger">
                        <iconify-icon icon="solar:bill-list-bold-duotone" class="fs-6"></iconify-icon> Hutang Saya
                    </div>
                    <div class="value text-danger my-1"><?= $rp($hpOpenTotal) ?></div>
                    <div class="sub"><?= $hpOpenCount ?> tagihan belum lunas &middot; wajib dibayar</div>
                </div>
                <span class="badge bg-danger-subtle text-danger fw-bold"><?= $hpOpenCount ?> Open</span>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="kb-metric-mini bg-white">
                <div>
                    <div class="label text-success">
                        <iconify-icon icon="solar:hand-money-bold-duotone" class="fs-6"></iconify-icon> Piutang Saya
                    </div>
                    <div class="value text-success my-1"><?= $rp($piOpenTotal) ?></div>
                    <div class="sub"><?= $piOpenCount ?> tagihan bisa ditagih ke cabang lain</div>
                </div>
                <span class="badge bg-success-subtle text-success fw-bold"><?= $piOpenCount ?> Open</span>
            </div>
        </div>
    </div>

    <?php /* ==================== WORKSPACE (FORM + PREVIEW) ==================== */ ?>
    <div class="kb-workspace mb-4">
        <!-- FORM PELUNASAN -->
        <div class="kb-clean-card bg-white" id="formCard">
            <div class="hd">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-1 bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px">
                        <iconify-icon icon="bi:send-fill" class="fs-7"></iconify-icon>
                    </div>
                    <div>
                        <h2>Form Pelunasan</h2>
                        <p>3 langkah — pilih tagihan, rekening, nominal</p>
                    </div>
                </div>
                <span id="modeBadge" class="kb-live-badge idle">Pilih rekening</span>
            </div>

            <div class="p-3 bg-white">
                <?php if (!$canTransaksi) : ?>
                    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-3 fs-7">
                        <iconify-icon icon="bi:shield-lock-fill" class="fs-6"></iconify-icon>
                        <span>Mode lihat saja — hubungi Finance / Manager untuk input pelunasan.</span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('kas_bank/antar-unit/save') ?>" enctype="multipart/form-data" id="formAntar">
                    <input type="hidden" name="submit_token" value="<?= esc($submit_token ?? '') ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">1 &middot; Hutang yang dibayar <span class="text-danger">*</span></label>
                        <select name="hutang_piutang_id" id="hp_hutang" class="form-select form-select-sm" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <option value="">— Pilih hutang —</option>
                            <?php foreach (($hp_hutang ?? []) as $h) :
                                $sisa = (int)($h->sisa ?? 0);
                                $isLunas = $sisa <= 0 || ($h->status ?? '') === 'lunas'; ?>
                                <option value="<?= (int) $h->id ?>"
                                    data-kode="<?= esc($h->kode ?? '') ?>"
                                    data-lawan="<?= esc($unitMap[(int)($h->lawan_unit_id ?? 0)] ?? 'U' . $h->lawan_unit_id) ?>"
                                    data-unit="<?= esc($unitMap[(int)($h->unit_id ?? 0)] ?? '') ?>"
                                    data-sisa="<?= $sisa ?>"
                                    data-total="<?= (int)($h->total ?? 0) ?>"
                                    data-dibayar="<?= (int)($h->total_dibayar ?? 0) ?>"
                                    data-status="<?= esc($h->status ?? '') ?>"
                                    data-jtempo="<?= esc($h->jatuh_tempo ?? '') ?>"
                                    data-sumber="<?= (int)($h->sumber_id ?? 0) ?>"
                                    <?= $isLunas ? 'disabled' : '' ?>>
                                    <?= esc($h->kode) ?> &middot; ke <?= esc($unitMap[(int)($h->lawan_unit_id ?? 0)] ?? 'U' . $h->lawan_unit_id) ?> &middot; sisa <?= $rp($sisa) ?> <?= $isLunas ? '(lunas)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kb-helper">Hanya hutang dengan sisa &gt; 0 yang bisa dipilih. Klik baris di tabel hutang untuk isi otomatis.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">2 &middot; Rekening</label>
                        <div class="kb-pair">
                            <div>
                                <div class="form-label text-muted mb-1" style="font-size:.68rem">Bayar DARI</div>
                                <select name="akun_pengirim_id" id="akun_pengirim" class="form-select form-select-sm" required <?= $canTransaksi ? '' : 'disabled' ?>>
                                    <option value="">Pilih pengirim</option>
                                    <?php foreach (($akun_pengirim ?? []) as $a) : ?>
                                        <option value="<?= (int) $a->idakun_kas_bank ?>" data-label="<?= esc($kelAkun($a)) ?>">
                                            <?= esc($kelAkun($a)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="kb-pair-arrow"><iconify-icon icon="solar:arrow-right-bold"></iconify-icon></div>
                            <div>
                                <div class="form-label text-muted mb-1" style="font-size:.68rem">Dibayar KE</div>
                                <select name="akun_penerima_id" id="akun_penerima" class="form-select form-select-sm" required <?= $canTransaksi ? '' : 'disabled' ?>>
                                    <option value="">Pilih penerima</option>
                                    <?php foreach (($akun_penerima ?? []) as $a) : ?>
                                        <option value="<?= (int) $a->idakun_kas_bank ?>" data-label="<?= esc($kelAkun($a)) ?>">
                                            <?= esc($kelAkun($a)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="rekHelper" class="kb-helper">Pilih dua rekening yang <strong>berbeda</strong> untuk pindah uang, atau <strong>sama</strong> untuk pelunasan tanpa pindah uang.</div>
                    </div>

                    <div class="kb-field-grid mb-3">
                        <div>
                            <label class="form-label small fw-semibold text-dark mb-1">3 &middot; Nominal (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="jumlah" id="jumlah_bayar" class="form-control form-control-sm text-end fw-bold" placeholder="5.000.000" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <div id="sisaHint" class="kb-helper" style="display:none"></div>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold text-dark mb-1">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" <?= $canTransaksi ? '' : 'disabled' ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">Bukti transfer <span class="text-muted fw-normal">(opsional — JPG/PNG/PDF)</span></label>
                        <input type="file" name="bukti" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp" <?= $canTransaksi ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">Catatan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm" placeholder="cth: Pelunasan mutasi 12 Jan — talangan BCA Bersama" <?= $canTransaksi ? '' : 'disabled' ?>>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold d-flex align-items-center justify-content-center gap-2 py-2" <?= $canTransaksi ? '' : 'disabled' ?>>
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-5"></iconify-icon> Simpan Pelunasan
                    </button>
                    <div id="modeExplain" class="kb-helper text-center mt-2" style="display:none"></div>
                </form>
            </div>
        </div>

        <!-- PRATINJAU TAGIHAN -->
        <div class="kb-clean-card bg-white" id="previewCard">
            <div class="hd">
                <div>
                    <h2>Pratinjau Tagihan</h2>
                    <p id="previewSub">Pilih hutang — detail &amp; barang mutasi muncul di sini</p>
                </div>
                <span id="previewStatus" class="badge bg-light text-muted border">Menunggu</span>
            </div>

            <div id="previewEmpty" class="kb-preview-empty">
                <div class="ico">
                    <iconify-icon icon="solar:bill-list-bold-duotone" class="fs-4 text-secondary"></iconify-icon>
                </div>
                <div class="fw-bold text-dark fs-6">Belum ada tagihan terpilih</div>
                <div class="text-muted small mt-1">Pilih dari dropdown di kiri atau klik <strong>Bayar</strong> pada tabel hutang di bawah.</div>
                <div class="mt-3 d-flex gap-1 justify-content-center flex-wrap">
                    <span class="badge bg-warning-subtle text-warning-emphasis">Tip: nominal otomatis terisi sisa tagihan</span>
                </div>
            </div>

            <div id="previewBody" style="display:none">
                <div class="p-3 bg-white border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div id="pvKode" class="fw-bold fs-6 text-dark"></div>
                            <div id="pvLawan" class="small text-muted"></div>
                            <div id="pvTempo" class="small text-muted mt-1" style="font-size:.72rem"></div>
                        </div>
                        <span id="pvStatus" class="badge"></span>
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col-4">
                            <div class="text-uppercase text-muted fw-bold" style="font-size:.65rem">Total</div>
                            <div id="pvTotal" class="kb-num fw-bold fs-6 text-dark"></div>
                        </div>
                        <div class="col-4">
                            <div class="text-uppercase text-muted fw-bold" style="font-size:.65rem">Dibayar</div>
                            <div id="pvDibayar" class="kb-num fw-bold fs-6 text-success"></div>
                        </div>
                        <div class="col-4">
                            <div class="text-uppercase text-muted fw-bold" style="font-size:.65rem">Sisa</div>
                            <div id="pvSisa" class="kb-num fw-bold fs-6 text-danger"></div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="progress" style="height:6px">
                            <div id="pvBar" class="progress-bar bg-success" role="progressbar" style="width:0%"></div>
                        </div>
                        <div id="pvBarLabel" class="text-muted mt-1" style="font-size:.68rem"></div>
                    </div>
                </div>

                <div id="pvItemsWrap" class="p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-bold small text-dark">Rincian barang mutasi</div>
                        <div id="pvNota" class="small text-muted"></div>
                    </div>
                    <div id="pvNoItems" class="small text-muted" style="display:none">Tidak ada rincian barang untuk hutang ini.</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:.78rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Barang</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="pvItems"></tbody>
                        </table>
                    </div>
                    <div id="pvItemsTotal" class="text-end mt-2 kb-num fw-bold small text-dark"></div>
                </div>

                <div id="pvModeExplain" class="p-3 bg-light border-top">
                    <div class="text-uppercase text-muted fw-bold mb-1" style="font-size:.68rem">Mode penyelesaian</div>
                    <div id="pvModeText" class="small"></div>
                </div>
            </div>
        </div>
    </div>

    <?php /* ==================== TABEL TAGIHAN ANTAR UNIT ==================== */ ?>
    <div class="kb-clean-card bg-white mb-4">
        <div class="hd">
            <div>
                <h2>Tagihan Antar Unit</h2>
                <p>Hutang = kewajiban cabang ini &middot; Piutang = hak tagih ke cabang lain</p>
            </div>
            <div class="kb-tabs" role="tablist">
                <button type="button" class="tab active" data-tab="hutang">Hutang <span class="badge bg-danger ms-1" style="font-size:.62rem"><?= $hpOpenCount ?></span></button>
                <button type="button" class="tab" data-tab="piutang">Piutang <span class="badge bg-success ms-1" style="font-size:.62rem"><?= $piOpenCount ?></span></button>
            </div>
        </div>

        <div id="pane-hutang" class="bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:110px">Kode</th>
                            <th style="min-width:150px">Cabang berhutang (penerima barang)</th>
                            <th style="min-width:130px">Pemberi barang &middot; berpiutang</th>
                            <th class="text-end" style="min-width:110px">Sisa</th>
                            <th style="min-width:110px">Status &middot; Jatuh tempo</th>
                            <th class="text-end" style="min-width:120px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hp_hutang ?? []) as $h) :
                            $sisa = (int)($h->sisa ?? 0);
                            $isLunas = $sisa <= 0 || ($h->status ?? '') === 'lunas';
                            $bukti = $detail_mutasi_map[(int)($h->sumber_id ?? 0)] ?? null; ?>
                            <tr class="kb-hp-row <?= $isLunas ? 'opacity-50' : '' ?>" data-hp-id="<?= (int)$h->id ?>" data-sisa="<?= $sisa ?>">
                                <td><span class="badge bg-light text-dark border"><?= esc($h->kode) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= esc($unitMap[(int)($h->unit_id ?? 0)] ?? 'U' . $h->unit_id) ?></div>
                                    <div class="text-muted small" style="font-size:.70rem"><?= esc($h->nama_pihak ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark d-flex align-items-center gap-1">
                                        <?= esc($unitMap[(int)($h->lawan_unit_id ?? 0)] ?? 'U' . $h->lawan_unit_id) ?>
                                        <iconify-icon icon="solar:arrow-right-linear" class="text-muted" style="font-size:.7rem"></iconify-icon>
                                    </div>
                                    <div class="text-muted small" style="font-size:.70rem"><?= $isLunas ? 'Lunas' : 'Sisa ' . $rp($sisa) ?></div>
                                </td>
                                <td class="text-end kb-num fw-bold <?= $isLunas ? 'text-muted' : 'text-danger' ?>"><?= $rp($sisa) ?></td>
                                <td>
                                    <div><span class="badge <?= $badgeStatus($h->status ?? '') ?>" style="font-size:.68rem"><?= $labelStatus($h->status ?? '') ?></span></div>
                                    <div class="text-muted small mt-1" style="font-size:.68rem">Jt: <?= esc($h->jatuh_tempo ?? '-') ?></div>
                                    <?php if ($bukti): ?><button type="button" class="btn btn-xs btn-outline-secondary mt-1 py-0 px-2" style="font-size:.68rem" data-bs-toggle="collapse" data-bs-target="#hp-barang-<?= (int)$h->id ?>">Lihat barang</button><?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!$isLunas && $canTransaksi): ?>
                                        <button type="button" class="btn btn-sm btn-primary py-1 px-3 fw-bold btn-pilih-hutang" data-id="<?= (int)$h->id ?>">Bayar</button>
                                    <?php elseif ($isLunas): ?>
                                        <span class="badge bg-success-subtle text-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($bukti): ?>
                                <tr class="collapse bg-light" id="hp-barang-<?= (int)$h->id ?>">
                                    <td colspan="6" class="p-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                            <span class="fw-bold small"><?= esc($bukti['no_nota'] ?? '') ?> &middot; <?= esc(date('Y-m-d', strtotime((string)($bukti['tanggal'] ?? '')))) ?></span>
                                            <span class="fw-bold text-primary kb-num small">Total: <?= $rp($bukti['total'] ?? 0) ?></span>
                                        </div>
                                        <table class="table table-sm table-bordered mb-0 bg-white" style="font-size:.76rem">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Barang</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Harga</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (($bukti['items'] ?? []) as $d): ?>
                                                    <tr>
                                                        <td><?= esc($d->nama_barang ?? '') ?></td>
                                                        <td class="text-end kb-num"><?= (float)($d->jumlah_kirim ?? 0) ?></td>
                                                        <td class="text-end kb-num"><?= $rp($d->harga_mutasi ?? 0) ?></td>
                                                        <td class="text-end kb-num fw-semibold"><?= $rp($d->nilai ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($hp_hutang)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted small">Belum ada hutang antar unit.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="pane-piutang" class="bg-white" style="display:none">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th>Cabang Penagih</th>
                            <th>Tagihan Ke</th>
                            <th class="text-end">Sisa</th>
                            <th>Status</th>
                            <th class="text-end">Rincian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hp_piutang ?? []) as $p) :
                            $bukti = $detail_mutasi_map[(int)($p->sumber_id ?? 0)] ?? null; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= esc($unitMap[(int)($p->unit_id ?? 0)] ?? 'U' . $p->unit_id) ?></div>
                                    <div class="text-muted small" style="font-size:.70rem"><?= esc($p->nama_pihak ?? '') ?></div>
                                </td>
                                <td><?= esc($unitMap[(int)($p->lawan_unit_id ?? 0)] ?? '-') ?></td>
                                <td class="text-end kb-num fw-bold text-success"><?= $rp($p->sisa ?? 0) ?></td>
                                <td><span class="badge <?= $badgeStatus($p->status ?? '') ?>"><?= $labelStatus($p->status ?? '') ?></span></td>
                                <td class="text-end">
                                    <?php if ($bukti): ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.68rem" data-bs-toggle="collapse" data-bs-target="#p-barang-<?= (int)$p->id ?>">Items</button>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($bukti): ?>
                                <tr class="collapse bg-light" id="p-barang-<?= (int)$p->id ?>">
                                    <td colspan="5" class="p-3">
                                        <div class="d-flex justify-content-between mb-2 small"><span class="fw-bold"><?= esc($bukti['no_nota'] ?? '') ?> &middot; <?= esc(date('Y-m-d', strtotime((string)($bukti['tanggal'] ?? '')))) ?></span><span class="fw-bold text-primary kb-num">Total: <?= $rp($bukti['total'] ?? 0) ?></span></div>
                                        <table class="table table-sm table-bordered mb-0 bg-white" style="font-size:.76rem">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Barang</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Harga</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (($bukti['items'] ?? []) as $d): ?>
                                                    <tr>
                                                        <td><?= esc($d->nama_barang ?? '') ?></td>
                                                        <td class="text-end kb-num"><?= (float)($d->jumlah_kirim ?? 0) ?></td>
                                                        <td class="text-end kb-num"><?= $rp($d->harga_mutasi ?? 0) ?></td>
                                                        <td class="text-end kb-num fw-semibold"><?= $rp($d->nilai ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($hp_piutang)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">Belum ada piutang antar unit.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php /* ==================== TABEL RIWAYAT PELUNASAN ==================== */ ?>
    <div class="kb-clean-card bg-white">
        <div class="hd">
            <div>
                <h2>Riwayat Pelunasan</h2>
                <p>Dibedakan otomatis — yang memindahkan uang vs yang hanya mengurangi hutang</p>
            </div>
            <div class="kb-tabs" role="tablist">
                <button type="button" class="tab active" data-rpane="real">Transfer Real <span class="badge bg-primary ms-1" style="font-size:.62rem"><?= count($pembayaran ?? []) ?></span></button>
                <button type="button" class="tab" data-rpane="atribusi">Atribusi <span class="badge bg-secondary ms-1" style="font-size:.62rem"><?= count($histori_atribusi ?? []) ?></span></button>
            </div>
        </div>

        <div id="rpane-real" class="bg-white">
            <div class="p-2 px-3 bg-primary-subtle text-primary border-bottom small d-flex gap-2 align-items-center" style="font-size:.72rem">
                <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-6"></iconify-icon>
                <span>Setiap baris = uang pindah antar rekening. Membatalkan akan mengembalikan sisa hutang/piutang.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th>Ref</th>
                            <th>Tanggal</th>
                            <th>Dari &rarr; Ke</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-center">Bukti</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pembayaran)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted small">Belum ada transfer real antar unit.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($pembayaran ?? []) as $t) :
                            $asal = $akunMap[(int)($t->akun_kas_bank_id ?? 0)] ?? null;
                            $tujuan = $akunMap[(int)($t->akun_tujuan_id ?? 0)] ?? null; ?>
                            <tr>
                                <td><span class="badge bg-warning-subtle text-warning-emphasis fw-semibold font-monospace"><?= esc($t->transfer_ref ?? '') ?></span></td>
                                <td class="text-muted"><?= esc($t->tanggal ?? '') ?></td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= $asal ? esc($kelAkun($asal)) : '-' ?></span>
                                    <span class="text-muted mx-1">&rarr;</span>
                                    <span class="fw-semibold text-dark"><?= $tujuan ? esc($kelAkun($tujuan)) : '-' ?></span>
                                </td>
                                <td class="text-end kb-num fw-bold text-dark"><?= $rp($t->jumlah ?? 0) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($t->bukti)): ?>
                                        <a href="<?= base_url($t->bukti) ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.68rem">Lihat</a>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (($t->arah ?? '') === 'KELUAR' && $canKelola): ?>
                                        <form method="post" class="d-inline" action="<?= base_url('kas_bank/antar-unit/reversal/' . (int)($t->idtransaksi ?? 0)) ?>" onsubmit="return confirm('Batalkan <?= esc($t->transfer_ref ?? '') ?> sebesar <?= $rp($t->jumlah ?? 0) ?>? Sisa hutang/piutang akan dikembalikan.')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size:.72rem">Batalkan</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="rpane-atribusi" class="bg-white" style="display:none">
            <div class="p-2 px-3 bg-warning-subtle text-warning-emphasis border-bottom small d-flex gap-2 align-items-center" style="font-size:.72rem">
                <iconify-icon icon="solar:calculator-minimalistic-bold-duotone" class="fs-6"></iconify-icon>
                <span>Tanpa gerakan uang — hanya hutang/piutang yang dilunasi. Saldo kas/bank tidak berubah.</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Ref Hutang</th>
                            <th>Keterangan</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($histori_atribusi)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">Belum ada pelunasan atribusi.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($histori_atribusi ?? []) as $p): ?>
                            <tr>
                                <td class="text-muted"><?= esc($p->tanggal_bayar ?? '') ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace">#<?= (int)($p->referensi_id ?? 0) ?></span></td>
                                <td class="text-secondary"><?= esc($p->keterangan ?? '') ?></td>
                                <td class="text-end kb-num fw-bold text-dark"><?= $rp($p->jumlah_bayar ?? 0) ?></td>
                                <td class="text-end">
                                    <?php if ($canKelola): ?>
                                        <form method="post" class="d-inline" action="<?= base_url('kas_bank/antar-unit/reversal-atribusi/' . (int)($p->referensi_id ?? 0)) ?>" onsubmit="return confirm('Batalkan atribusi ini? Sisa hutang/piutang akan dikembalikan. Saldo kas tidak berubah.')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size:.72rem">Batalkan</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const detailMap = <?= json_encode($detail_mutasi_map ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        const selHP = document.getElementById('hp_hutang');
        const selFrom = document.getElementById('akun_pengirim');
        const selTo = document.getElementById('akun_penerima');
        const inpJumlah = document.getElementById('jumlah_bayar');
        const badge = document.getElementById('modeBadge');
        const helper = document.getElementById('rekHelper');
        const modeExplain = document.getElementById('modeExplain');
        const sisaHint = document.getElementById('sisaHint');

        const pvEmpty = document.getElementById('previewEmpty');
        const pvBody = document.getElementById('previewBody');
        const pvSub = document.getElementById('previewSub');
        const pvKode = document.getElementById('pvKode');
        const pvLawan = document.getElementById('pvLawan');
        const pvTempo = document.getElementById('pvTempo');
        const pvStatus = document.getElementById('pvStatus');
        const pvTotal = document.getElementById('pvTotal');
        const pvDibayar = document.getElementById('pvDibayar');
        const pvSisa = document.getElementById('pvSisa');
        const pvBar = document.getElementById('pvBar');
        const pvBarLabel = document.getElementById('pvBarLabel');
        const pvNota = document.getElementById('pvNota');
        const pvItems = document.getElementById('pvItems');
        const pvNoItems = document.getElementById('pvNoItems');
        const pvItemsTotal = document.getElementById('pvItemsTotal');
        const pvModeText = document.getElementById('pvModeText');
        const previewStatus = document.getElementById('previewStatus');

        function fmtStatus(s) {
            const m = {
                belum_lunas: 'Belum Lunas',
                sebagian: 'Dibayar Sebagian',
                lunas: 'Lunas'
            };
            return m[s] || s;
        }

        function badgeClass(s) {
            if (s === 'belum_lunas') return 'badge bg-warning-subtle text-warning-emphasis';
            if (s === 'sebagian') return 'badge bg-primary-subtle text-primary';
            if (s === 'lunas') return 'badge bg-success-subtle text-success';
            return 'badge bg-secondary-subtle text-secondary';
        }

        function updateMode() {
            const f = selFrom.value,
                t = selTo.value;
            if (!f || !t) {
                badge.textContent = 'Pilih rekening';
                badge.className = 'kb-live-badge idle';
                helper.innerHTML = 'Pilih dua rekening yang <strong>berbeda</strong> untuk pindah uang, atau <strong>sama</strong> untuk pelunasan tanpa pindah uang.';
                modeExplain.style.display = 'none';
                if (pvModeText) pvModeText.innerHTML = '<span class="text-muted">Pilih kedua rekening untuk melihat mode penyelesaian.</span>';
                return;
            }
            const same = f === t;
            if (same) {
                badge.textContent = 'Atribusi — tanpa pindah uang';
                badge.className = 'kb-live-badge atribusi';
                helper.innerHTML = '<span class="text-warning-emphasis fw-bold">Atribusi:</span> rekening sama — <strong>saldo kas/bank tidak bergerak</strong>, hanya hutang/piutang berkurang.';
                modeExplain.style.display = 'block';
                modeExplain.innerHTML = 'Rekening sama terdeteksi &rarr; pelunasan <strong>tanpa gerakan kas</strong>. Cocok untuk rekening Bersama.';
                modeExplain.className = 'kb-helper text-center mt-2 text-warning-emphasis';
                if (pvModeText) pvModeText.innerHTML = '<span class="text-warning-emphasis">Rekening <strong>sama</strong> &rarr; <strong>Atribusi</strong>. Tidak ada uang yang pindah antar rekening. Sistem hanya mengurangi hutang/piutang.</span>';
            } else {
                badge.textContent = 'Transfer fisik — uang pindah';
                badge.className = 'kb-live-badge transfer';
                const fl = selFrom.options[selFrom.selectedIndex]?.dataset.label || '';
                const tl = selTo.options[selTo.selectedIndex]?.dataset.label || '';
                helper.innerHTML = '<span class="text-primary fw-bold">Transfer fisik:</span> uang pindah dari <strong>' + fl + '</strong> ke <strong>' + tl + '</strong>.';
                modeExplain.style.display = 'block';
                modeExplain.innerHTML = 'Rekening berbeda &rarr; <strong>uang benar-benar pindah</strong>. Akan tercatat sebagai KELUAR &amp; MASUK di riwayat.';
                modeExplain.className = 'kb-helper text-center mt-2 text-primary';
                if (pvModeText) pvModeText.innerHTML = '<span class="text-primary">Rekening <strong>berbeda</strong> &rarr; <strong>Transfer fisik</strong>. Saldo kas/bank akan berkurang di pengirim &amp; bertambah di penerima.</span>';
            }
        }

        function renderPreview() {
            const opt = selHP.options[selHP.selectedIndex];
            if (!opt || !opt.value) {
                pvEmpty.style.display = '';
                pvBody.style.display = 'none';
                pvSub.textContent = 'Pilih hutang — detail & barang mutasi muncul di sini';
                previewStatus.textContent = 'Menunggu';
                previewStatus.className = 'badge bg-light text-muted border';
                sisaHint.style.display = 'none';
                document.querySelectorAll('.kb-hp-row').forEach(r => r.classList.remove('is-selected'));
                return;
            }
            const sisa = parseInt(opt.dataset.sisa || '0', 10);
            const total = parseInt(opt.dataset.total || '0', 10);
            const dibayar = parseInt(opt.dataset.dibayar || '0', 10);
            const status = opt.dataset.status || '';
            const kode = opt.dataset.kode || opt.textContent.trim().slice(0, 24);
            const lawan = opt.dataset.lawan || '-';
            const unit = opt.dataset.unit || '';
            const jtempo = opt.dataset.jtempo || '-';
            const sumber = opt.dataset.sumber || '0';

            pvEmpty.style.display = 'none';
            pvBody.style.display = '';
            pvSub.textContent = unit ? unit + ' → ' + lawan : 'Ke ' + lawan;
            pvKode.textContent = kode;
            pvLawan.textContent = (unit ? unit + ' → ' : '') + lawan;
            pvTempo.textContent = 'Jatuh tempo: ' + jtempo;
            pvStatus.textContent = fmtStatus(status);
            pvStatus.className = badgeClass(status);
            pvTotal.textContent = rp(total);
            pvDibayar.textContent = rp(dibayar);
            pvSisa.textContent = rp(sisa);
            const pct = total > 0 ? Math.round(dibayar / total * 100) : 0;
            pvBar.style.width = pct + '%';
            pvBarLabel.textContent = pct + '% terbayar · sisa ' + rp(sisa);
            previewStatus.textContent = rp(sisa) + ' sisa';
            previewStatus.className = sisa > 0 ? 'badge bg-danger-subtle text-danger' : 'badge bg-success-subtle text-success';

            sisaHint.style.display = 'block';
            sisaHint.innerHTML = 'Sisa tagihan: <strong class="text-danger">' + rp(sisa) + '</strong> · klik untuk isi nominal';
            sisaHint.style.cursor = 'pointer';
            sisaHint.onclick = () => {
                inpJumlah.value = sisa.toLocaleString('id-ID');
                inpJumlah.dispatchEvent(new Event('input'));
            };

            if (!inpJumlah.value) inpJumlah.value = sisa.toLocaleString('id-ID');

            const det = detailMap[sumber] || detailMap[parseInt(sumber, 10)] || null;
            pvItems.innerHTML = '';
            if (det && det.items && det.items.length) {
                pvNoItems.style.display = 'none';
                pvNota.textContent = (det.no_nota || '') + ' · ' + (det.tanggal || '');
                pvItemsTotal.textContent = 'Total barang: ' + rp(det.total || 0);
                det.items.forEach(d => {
                    const tr = document.createElement('tr');
                    const nilai = d.nilai ?? ((parseFloat(d.jumlah_kirim || 0) * parseInt(d.harga_mutasi || 0)));
                    tr.innerHTML = '<td>' + (d.nama_barang || '') + '</td><td class="text-end kb-num">' + (parseFloat(d.jumlah_kirim || 0)) + '</td><td class="text-end kb-num">' + rp(d.harga_mutasi || 0) + '</td><td class="text-end kb-num fw-semibold">' + rp(nilai) + '</td>';
                    pvItems.appendChild(tr);
                });
            } else {
                pvNoItems.style.display = '';
                pvNota.textContent = '';
                pvItemsTotal.textContent = '';
            }

            document.querySelectorAll('.kb-hp-row').forEach(r => {
                r.classList.toggle('is-selected', r.dataset.hpId === opt.value);
            });
            updateMode();
        }

        selHP.addEventListener('change', renderPreview);
        selFrom.addEventListener('change', updateMode);
        selTo.addEventListener('change', updateMode);

        document.querySelectorAll('.btn-pilih-hutang').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                selHP.value = id;
                renderPreview();
                document.getElementById('formCard').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                selFrom.focus();
            });
        });
        document.querySelectorAll('.kb-hp-row[data-hp-id]').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('button') || e.target.closest('a')) return;
                const id = row.dataset.hpId;
                if (!id) return;
                selHP.value = id;
                renderPreview();
            });
        });

        document.querySelectorAll('.kb-tabs .tab[data-tab]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.kb-tabs .tab[data-tab]').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const v = tab.dataset.tab;
                document.getElementById('pane-hutang').style.display = v === 'hutang' ? '' : 'none';
                document.getElementById('pane-piutang').style.display = v === 'piutang' ? '' : 'none';
            });
        });
        document.querySelectorAll('.kb-tabs .tab[data-rpane]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.kb-tabs .tab[data-rpane]').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const v = tab.dataset.rpane;
                document.getElementById('rpane-real').style.display = v === 'real' ? '' : 'none';
                document.getElementById('rpane-atribusi').style.display = v === 'atribusi' ? '' : 'none';
            });
        });

        inpJumlah.addEventListener('input', function() {
            let v = this.value.replace(/[^0-9]/g, '');
            if (!v) {
                this.value = '';
                return;
            }
            this.value = Number(v).toLocaleString('id-ID');
        });

        renderPreview();
        updateMode();
    })();
</script>
