<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
/**
 * SETOR TUNAI — KAS unit -> rekening bank.
 *
 * Berbeda dengan Transfer Internal: di sini satu kaki pastinya laci kas unit,
 * jadi posisi/entitlement unit ikut bergerak. Angka di halaman ini dikirim
 * controller dari KasBankCutoffService; view tidak menghitung apa pun.
 *
 * @var array<string, mixed> $akun_kas
 * @var array<string, mixed> $akun_bank
 * @var array<string, mixed> $preview
 * @var array<string, mixed> $input
 */
$rp      = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}

$inputNominal  = (int) ($input['nominal'] ?? 0);
$inputKasId    = (int) ($input['akun_kas_id'] ?? 0);
$inputBankId   = (int) ($input['akun_bank_id'] ?? 0);
$simpanNonaktif = ($preview !== null && ! $preview['bisa_submit']);
?>

<!-- Mode Switcher Perpindahan Uang -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2 bg-light rounded-3">
        <div class="nav nav-pills nav-fill gap-2">
            <a href="<?= base_url('kas_bank/transfer') ?>" class="nav-link bg-white text-dark border fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:transfer-horizontal-bold-duotone" class="fs-5"></iconify-icon>
                <span>Pindah Saldo (Bank &rarr; Bank)</span>
            </a>
            <a href="<?= base_url('kas_bank/setor-tunai') ?>" class="nav-link active bg-success text-white fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:cash-out-bold-duotone" class="fs-5"></iconify-icon>
                <span>Setor Tunai (Laci &rarr; Bank)</span>
            </a>
            <a href="<?= base_url('kas_bank/penarikan-tunai') ?>" class="nav-link bg-white text-dark border fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:hand-money-bold-duotone" class="fs-5 text-warning-emphasis"></iconify-icon>
                <span>Tarik Tunai (Bank &rarr; Laci)</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* Semua gaya halaman ini diawali "stn-" supaya tidak bentrok dengan _theme. */
    .stn-wrap {
        --stn-ok: var(--bs-success, #198754);
        --stn-line: var(--bs-border-color, rgba(128, 128, 128, .25));
    }



    /* Alur setor: elemen utama halaman */
    .stn-route {
        display: grid;
        grid-template-columns: 1fr minmax(120px, 220px) 1fr;
        align-items: center;
        gap: 0;
        padding: 1.25rem 1.5rem;
    }

    .stn-end {
        min-width: 0;
    }

    .stn-end small {
        display: block;
        color: var(--bs-secondary-color, #6c757d);
        margin-bottom: .15rem;
    }

    .stn-end strong {
        display: block;
        font-size: 1.05rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .stn-end.is-empty strong {
        font-weight: 400;
        color: var(--bs-secondary-color, #6c757d);
    }

    .stn-end.is-right {
        text-align: right;
    }

    .stn-flow {
        position: relative;
        text-align: center;
        padding: 0 .75rem;
    }

    .stn-flow-amount {
        font-size: 1.35rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .stn-flow-amount.is-empty {
        font-weight: 400;
        font-size: 1rem;
        color: var(--bs-secondary-color, #6c757d);
    }

    .stn-flow-line {
        height: 2px;
        background: var(--stn-ok);
        margin: .45rem 0;
        position: relative;
        border-radius: 2px;
    }

    .stn-flow-line::after {
        content: "";
        position: absolute;
        right: -1px;
        top: 50%;
        width: 9px;
        height: 9px;
        border-top: 2px solid var(--stn-ok);
        border-right: 2px solid var(--stn-ok);
        transform: translateY(-50%) rotate(45deg);
    }

    .stn-flow-note {
        font-size: .78rem;
        color: var(--bs-secondary-color, #6c757d);
    }

    /* Form */
    .stn-group+.stn-group {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--stn-line);
    }

    .stn-group-title {
        font-weight: 600;
        margin-bottom: .75rem;
    }

    .stn-nominal {
        font-size: 1.25rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .stn-nominal-echo {
        min-height: 1.2em;
        margin-top: .25rem;
        font-size: .85rem;
        color: var(--stn-ok);
        font-variant-numeric: tabular-nums;
    }

    .stn-actions {
        position: sticky;
        bottom: 0;
        background: var(--bs-body-bg, #fff);
        padding-top: .75rem;
        margin-top: 1.25rem;
        border-top: 1px solid var(--stn-line);
    }

    /* Riwayat: tiap operasi satu blok, dua kaki berdampingan */
    .stn-ledger {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .stn-entry {
        display: grid;
        grid-template-columns: 7.5rem 1fr auto;
        gap: .5rem 1.25rem;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--stn-line);
    }

    .stn-entry:first-child {
        border-top: 0;
    }

    .stn-entry-date {
        color: var(--bs-secondary-color, #6c757d);
        font-variant-numeric: tabular-nums;
    }

    .stn-entry-date .kb-mono {
        display: block;
        margin-top: .2rem;
        font-size: .78rem;
    }

    .stn-leg {
        display: flex;
        gap: .5rem;
        align-items: baseline;
    }

    .stn-leg-sign {
        width: 1rem;
        font-weight: 700;
        flex-shrink: 0;
        text-align: center;
    }

    .stn-leg.is-out .stn-leg-sign {
        color: var(--bs-danger, #dc3545);
    }

    .stn-leg.is-in .stn-leg-sign {
        color: var(--stn-ok);
    }

    .stn-entry-sub {
        color: var(--bs-secondary-color, #6c757d);
        font-size: .85rem;
        margin-top: .3rem;
    }

    .stn-entry-amount {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
    }

    .stn-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--bs-secondary-color, #6c757d);
    }

    @media (max-width: 767.98px) {
        .stn-route {
            grid-template-columns: 1fr;
            gap: .75rem;
            padding: 1rem;
        }

        .stn-end.is-right {
            text-align: left;
        }

        .stn-flow {
            padding: 0;
            text-align: left;
        }

        .stn-flow-line {
            width: 2px;
            height: 1.5rem;
            margin: .25rem 0 .25rem .25rem;
        }

        .stn-flow-line::after {
            right: auto;
            left: 50%;
            top: auto;
            bottom: -1px;
            transform: translateX(-50%) rotate(135deg);
        }

        .stn-entry {
            grid-template-columns: 1fr auto;
        }

        .stn-entry-date {
            grid-column: 1 / -1;
        }
    }
</style>

<div class="stn-wrap">

    <?php if (($bisa_pilih_unit ?? false)) : ?>
        <form class="kb-card mb-3 p-3" method="get" action="<?= base_url('kas_bank/setor-tunai') ?>">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-4">
                    <label class="kb-label mb-1" for="stn-unit">Unit transaksi</label>
                    <select id="stn-unit" name="unit_id" class="form-select form-select-sm kb-select" onchange="this.form.submit()">
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8 text-muted d-flex align-items-start gap-2">
                    <iconify-icon icon="bi:info-circle-fill" class="text-info kb-ico flex-shrink-0 mt-1"></iconify-icon>
                    <span>Setor tunai memindahkan uang dari laci kas unit ke rekening bank. Dicatat sebagai
                        <strong>perpindahan saldo</strong>, bukan pemasukan, jadi tidak memengaruhi laba/rugi.</span>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php /* ---------- Cut-off belum lengkap ---------- */ ?>
    <?php if (! empty($cutoff_info['unit_tanpa_closing'])) : ?>
        <div class="kb-banner is-danger mb-3" role="alert">
            <div class="kb-banner-icon text-warning">
                <iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon>
            </div>
            <div class="kb-banner-content">
                <strong>Closing kas belum ada untuk sebagian unit</strong>
                <div class="mt-1">
                    Per tanggal <span class="kb-mono"><?= esc($cutoff_info['tanggal_cutoff']) ?></span>, unit ini belum punya closing kas:
                    <?php foreach ($cutoff_info['unit_tanpa_closing'] as $namaUnit) : ?>
                        <span class="kb-badge kb-badge-amber ms-1"><?= esc($namaUnit) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    Saldo laci unit-unit ini belum bisa jadi acuan setor. Angka <span class="kb-mono">0</span>
                    bukan berarti laci kosong, melainkan data closing belum ada.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================= ALUR SETOR (live) ========================= -->
    <div class="kb-card mb-4" aria-live="polite">
        <div class="stn-route">
            <div class="stn-end is-empty" id="stn-from">
                <small>Dari laci kas</small>
                <strong>Belum dipilih</strong>
            </div>
            <div class="stn-flow">
                <div class="stn-flow-amount is-empty" id="stn-amount">Isi nominal</div>
                <div class="stn-flow-line"></div>
                <div class="stn-flow-note">Perpindahan saldo, bukan pemasukan</div>
            </div>
            <div class="stn-end is-right is-empty" id="stn-to">
                <small>Ke rekening bank</small>
                <strong>Belum dipilih</strong>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ========================= FORM ========================= -->
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="kb-card">
                <div class="kb-card-header">
                    <div>
                        <h6 class="kb-card-title mb-0">Catat setor tunai</h6>
                        <span class="kb-card-sub text-muted">Cek pratinjau dulu, lalu simpan.</span>
                    </div>
                </div>
                <div class="p-3">
                    <form method="post" action="<?= base_url('kas_bank/setor-tunai') ?>" id="form-setor">
                        <?= csrf_field() ?>
                        <input type="hidden" name="submit_token" value="<?= esc($submit_token ?? '') ?>">
                        <input type="hidden" name="operation_key" value="<?= esc($operation_key ?? '') ?>">

                        <?php if (($bisa_pilih_unit ?? false)) : ?>
                            <input type="hidden" name="unit_id" value="<?= (int) ($unit_terpilih ?? 0) ?>">
                        <?php else : ?>
                            <input type="hidden" name="unit_id" value="<?= (int) session('ID_UNIT') ?>">
                        <?php endif; ?>

                        <div class="stn-group">
                            <div class="stn-group-title">Rute dana</div>

                            <?php if (($bisa_pilih_unit ?? false)) : ?>
                                <div class="mb-3">
                                    <label class="kb-label mb-1">Unit cabang</label>
                                    <input type="text" class="form-control form-control-sm kb-input bg-tertiary"
                                        value="<?= esc($unitMap[(int) ($unit_terpilih ?? 0)] ?? '—') ?>" readonly>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="kb-label mb-1" for="stn-kas">Dari akun kas <span class="kb-req">*</span></label>
                                <select id="stn-kas" name="akun_kas_id" class="form-select form-select-sm kb-select" required>
                                    <option value="">Pilih laci kas unit</option>
                                    <?php foreach (($akun_kas ?? []) as $a) : ?>
                                        <option value="<?= (int) $a->idakun_kas_bank ?>"
                                            <?= $inputKasId === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                            <?= esc(($unitMap[(int) $a->unit_id] ?? 'Unit') . ' – ' . $a->nama_akun) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="kb-hint mt-1">Hanya laci kas milik unit terpilih.</div>
                            </div>

                            <div class="mb-0">
                                <label class="kb-label mb-1" for="stn-bank">Ke rekening bank <span class="kb-req">*</span></label>
                                <select id="stn-bank" name="akun_bank_id" class="form-select form-select-sm kb-select" required>
                                    <option value="">Pilih rekening tujuan</option>
                                    <?php foreach (($akun_bank ?? []) as $a) : ?>
                                        <option value="<?= (int) $a->idakun_kas_bank ?>"
                                            <?= $inputBankId === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                            <?= esc($a->nama_akun) ?><?= (int) ($a->is_shared ?? 0) === 1 ? ' — Shared Account' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="kb-hint mt-1">
                                    <span class="kb-badge kb-badge-blue">Shared Account</span>
                                    dipakai bersama beberapa unit. Dana yang disetor tetap menjadi hak unit di atas,
                                    bukan milik satu unit.
                                </div>
                            </div>
                        </div>

                        <div class="stn-group">
                            <div class="stn-group-title">Detail setoran</div>

                            <div class="mb-3">
                                <label class="kb-label mb-1" for="stn-nominal">Nominal (Rp) <span class="kb-req">*</span></label>
                                <input type="number" id="stn-nominal" name="nominal"
                                    class="form-control kb-input kb-num stn-nominal"
                                    min="1" step="1" required inputmode="numeric" placeholder="5000000"
                                    value="<?= $inputNominal > 0 ? $inputNominal : '' ?>">
                                <div class="stn-nominal-echo" id="stn-nominal-echo"></div>
                            </div>

                            <div class="mb-3">
                                <label class="kb-label mb-1" for="stn-tanggal">Tanggal transaksi <span class="kb-req">*</span></label>
                                <input type="date" id="stn-tanggal" name="tanggal" class="form-control form-control-sm kb-input" required
                                    min="<?= esc($cutoff_info['tanggal_mulai'] ?? '') ?>"
                                    value="<?= esc($input['tanggal'] ?? date('Y-m-d')) ?>">
                                <div class="kb-hint mt-1">
                                    Paling awal <span class="kb-mono"><?= esc($cutoff_info['tanggal_mulai'] ?? '') ?></span>,
                                    awal periode operasional baru. Transaksi sebelum itu tidak bisa dicatat di ledger baru.
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="kb-label mb-1" for="stn-ket">Keterangan</label>
                                <input type="text" id="stn-ket" name="keterangan" class="form-control form-control-sm kb-input"
                                    placeholder="Contoh: setor kas harian ke BCA"
                                    value="<?= esc($input['keterangan'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="stn-actions">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold d-flex align-items-center justify-content-center gap-1">
                                    <iconify-icon icon="bi:eye"></iconify-icon>
                                    Lihat pratinjau
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm text-white fw-semibold d-flex align-items-center justify-content-center gap-1"
                                    formaction="<?= base_url('kas_bank/setor-tunai/save') ?>"
                                    <?= $simpanNonaktif ? 'disabled' : '' ?>>
                                    <iconify-icon icon="bi:check2-circle"></iconify-icon>
                                    Simpan setor tunai
                                </button>
                            </div>
                            <div class="kb-hint mt-2 text-center">
                                <?php if ($simpanNonaktif) : ?>
                                    Simpan belum bisa dipakai. Lengkapi data yang ditandai di pratinjau.
                                <?php else : ?>
                                    Lihat pratinjau dulu. Simpan menolak transaksi yang datanya belum lengkap.
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================= PRATINJAU + RIWAYAT ========================= -->
        <div class="col-12 col-lg-7 col-xl-8">
            <div class="kb-card mb-4">
                <div class="kb-card-header">
                    <div>
                        <h5 class="kb-card-title mb-0">Dampak saldo</h5>
                        <span class="kb-card-sub text-muted">Perkiraan sebelum transaksi disimpan.</span>
                    </div>
                </div>
                <div class="kb-card-body">
                    <?= $this->include('kas_bank/_pratinjau_tunai') ?>
                </div>
            </div>

            <div class="kb-card">
                <div class="kb-card-header">
                    <div>
                        <h5 class="kb-card-title mb-0">Riwayat setor tunai</h5>
                        <span class="kb-card-sub text-muted">Satu blok = satu operasi, dengan dua kaki movement.</span>
                    </div>
                </div>

                <?php if (empty($transaksi)) : ?>
                    <div class="stn-empty">
                        <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
                        Belum ada setor tunai. Isi form di samping untuk mencatat yang pertama.
                    </div>
                <?php else : ?>
                    <ul class="stn-ledger">
                        <?php foreach ($transaksi as $t) : ?>
                            <li class="stn-entry">
                                <div class="stn-entry-date">
                                    <?= esc($t['tanggal']) ?>
                                    <span class="kb-mono"><?= esc($t['transfer_ref']) ?></span>
                                </div>
                                <div>
                                    <?php foreach ($t['legs'] as $leg) : ?>
                                        <div class="stn-leg <?= $leg['arah'] === 'KELUAR' ? 'is-out' : 'is-in' ?>">
                                            <span class="stn-leg-sign" aria-label="<?= $leg['arah'] === 'KELUAR' ? 'keluar' : 'masuk' ?>"><?= $leg['arah'] === 'KELUAR' ? '−' : '+' ?></span>
                                            <span>
                                                <?= esc($leg['nama']) ?>
                                                <?php if (! empty($leg['is_shared'])) : ?>
                                                    <span class="kb-badge kb-badge-blue ms-1">Shared</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="stn-entry-sub">
                                        <?= esc($t['unit_nama']) ?>
                                        <?php if (! empty($t['keterangan'])) : ?>
                                            &middot; <?= esc($t['keterangan']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="stn-entry-amount"><?= $rp($t['jumlah']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var kas = document.getElementById('stn-kas');
        var bank = document.getElementById('stn-bank');
        var nominal = document.getElementById('stn-nominal');
        var echo = document.getElementById('stn-nominal-echo');
        var from = document.getElementById('stn-from');
        var to = document.getElementById('stn-to');
        var amount = document.getElementById('stn-amount');
        if (!kas || !bank || !nominal) return;

        function labelOf(select) {
            var opt = select.options[select.selectedIndex];
            return opt && opt.value ? opt.text.trim() : '';
        }

        function setEnd(el, text, placeholder) {
            el.querySelector('strong').textContent = text || placeholder;
            el.classList.toggle('is-empty', !text);
        }

        function rupiah(n) {
            return 'Rp ' + Number(n).toLocaleString('id-ID');
        }

        function render() {
            setEnd(from, labelOf(kas), 'Belum dipilih');
            setEnd(to, labelOf(bank), 'Belum dipilih');

            var n = parseInt(nominal.value, 10);
            if (n > 0) {
                amount.textContent = rupiah(n);
                amount.classList.remove('is-empty');
                echo.textContent = rupiah(n);
            } else {
                amount.textContent = 'Isi nominal';
                amount.classList.add('is-empty');
                echo.textContent = '';
            }
        }

        [kas, bank].forEach(function(el) {
            el.addEventListener('change', render);
        });
        nominal.addEventListener('input', render);
        render();
    })();
</script>
