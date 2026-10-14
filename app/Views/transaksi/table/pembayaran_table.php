<?php
    $noService  = @$old_service_pelanggan->no_service ?? '';
    $dpTercatat = (int) (@$old_service_pelanggan->dp_bayar ?? 0);
    $totalAwal  = (int) (@$old_service_pelanggan->total_service ?? 0);
    $diskonAwal = (int) (@$old_service_pelanggan->total_diskon ?? 0);
    $bayarTunaiTercatat = max(0, (int) (@$old_service_pelanggan->bayar_tunai ?? 0));
    $transferTercatat = 0;
    $recordedBanks = [];
    foreach (($pembayaran_lama ?? []) as $transferLama) {
        $transferTercatat += max(0, (int) ($transferLama->jumlah ?? 0));
        $recordedBanks[] = [
            'id' => (int) ($transferLama->bank_idbank ?? 0),
            'jumlah' => max(0, (int) ($transferLama->jumlah ?? 0)),
        ];
    }
    $bayarTercatat = max(
        0,
        (int) (@$old_service_pelanggan->bayar ?? 0),
        $bayarTunaiTercatat + $transferTercatat
    );
    if (!empty($oldsparepart)) {
        $totalAwal = 0;
        foreach ($oldsparepart as $sparepartTersimpan) {
            $totalAwal += max(0, (int) ($sparepartTersimpan->sub_total ?? 0));
        }
    }
    $totalTerkumpul = $dpTercatat + $bayarTercatat;
    $isLunas = $totalAwal > 0 && $totalTerkumpul === $totalAwal;
    $isOverpaid = $totalAwal > 0 && $totalTerkumpul > $totalAwal && $totalTerkumpul > 0;
    // Kelebihan bayar diperlakukan sebagai salah input: form dibuka agar nominal
    // bisa diisi ulang oleh siapa pun yang mengakses halaman ini. Terkunci hanya
    // saat total tercatat sama persis dengan tagihan (lunas).
    $modeKoreksi = $isOverpaid;
    $paymentLocked = $isLunas;
    $dueAwal = $modeKoreksi
        ? max(0, $totalAwal - $dpTercatat)
        : max(0, $totalAwal - $dpTercatat - $bayarTercatat);
    $kelebihanAwal = max(0, $totalTerkumpul - $totalAwal);
    $rp = static fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
?>
<style>
    /* ==========================================================================
       Step 4 — Pembayaran (scoped to .sv-pay)
       Two clearly separated zones so the operator never guesses which field is
       editable ("Dapat diisi") and which is computed by the system ("Otomatis").
       Own-world: Bootstrap Modernize tokens only -- no new palette, no gradient.
       ========================================================================== */
    .sv-pay .form-label {
        font-weight: 600;
    }

    .sv-pay .form-text {
        font-size: .78rem;
    }

    .sv-pay-editable,
    .sv-pay-dp-editable {
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
    }

    /* Intro / legend ---------------------------------------------------------- */
    .sv-pay-intro {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1rem;
        padding: .75rem 1rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-secondary-bg);
    }

    .sv-pay-ticket {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-variant-numeric: tabular-nums;
    }

    .sv-pay-ticket__label {
        font-size: .72rem;
        letter-spacing: .02em;
        color: var(--bs-secondary-color);
    }

    .sv-pay-ticket__value {
        font-weight: 600;
        color: var(--bs-heading-color);
    }

    .sv-pay-key {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem .75rem;
        margin-left: auto;
    }

    .sv-pay-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1;
        padding: .3rem .6rem;
        border-radius: 999px;
        border: 1px solid transparent;
    }

    .sv-pay-chip--edit {
        color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        border-color: var(--bs-primary-border-subtle);
    }

    .sv-pay-chip--auto {
        color: var(--bs-body-color);
        background: var(--bs-tertiary-bg);
        border-color: var(--bs-border-color);
    }

    .sv-pay-chip i {
        font-size: .85rem;
    }

    /* Section shell ----------------------------------------------------------- */
    .sv-pay-section {
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-body-bg);
    }

    /* Only a section that is alone in its column may stretch to fill the
       column height. Forcing height:100% on stacked sections makes the second
       one overflow the column and overlap the step footer. */
    .sv-pay-section--fill {
        height: 100%;
    }

    .sv-pay-section__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem 1rem;
        border-bottom: 1px solid var(--bs-border-color);
    }

    .sv-pay-section__title {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        font-weight: 600;
        color: var(--bs-heading-color);
    }

    .sv-pay-section__title i {
        color: var(--bs-primary);
    }

    .sv-pay-section__body {
        padding: 1rem;
    }

    /* Editable method selector ------------------------------------------------ */
    .sv-methods {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .5rem;
    }

    .sv-method {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .sv-method__input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .sv-method__box {
        display: flex;
        flex-direction: column;
        gap: .15rem;
        height: 100%;
        padding: .7rem .75rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        background: var(--bs-body-bg);
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }

    .sv-method__box i {
        font-size: 1.1rem;
        color: var(--bs-secondary-color);
    }

    .sv-method__label {
        font-weight: 600;
        color: var(--bs-heading-color);
        line-height: 1.25;
    }

    .sv-method__hint {
        font-size: .72rem;
        color: var(--bs-secondary-color);
        line-height: 1.3;
    }

    .sv-method:hover .sv-method__box {
        border-color: var(--bs-primary-border-subtle);
        background: var(--bs-primary-bg-subtle);
    }

    .sv-method__input:focus-visible + .sv-method__box {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }

    .sv-method__input:checked + .sv-method__box {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        box-shadow: 0 0 0 1px var(--bs-primary) inset;
    }

    .sv-method__input:checked + .sv-method__box i,
    .sv-method__input:checked + .sv-method__box .sv-method__label {
        color: var(--bs-primary);
    }

    /* Editable vs locked fields ---------------------------------------------- */
    .sv-pay-input {
        border-color: var(--bs-border-color);
    }

    .sv-pay-input:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 .2rem var(--bs-primary-bg-subtle);
    }

    /* Auto / recap panel ------------------------------------------------------ */
    .sv-recap {
        margin: 0;
    }

    .sv-recap__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: .55rem 0;
    }

    .sv-recap__row + .sv-recap__row {
        border-top: 1px solid var(--bs-border-color);
    }

    .sv-recap dt {
        font-weight: 500;
        color: var(--bs-secondary-color);
        margin: 0;
    }

    .sv-recap dd {
        margin: 0;
        font-weight: 600;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .sv-recap__row--due {
        margin-top: .35rem;
        padding-top: .7rem;
        border-top: 1px solid var(--bs-border-color);
    }

    .sv-recap__row--due dt {
        color: var(--bs-heading-color);
        font-weight: 600;
    }

    .sv-recap__row--due dd {
        font-size: 1.35rem;
        line-height: 1.1;
        font-weight: 700;
        color: var(--bs-primary);
    }

    .sv-recap__row--change dd {
        font-weight: 700;
    }

    .sv-recap__row--change dd.is-change {
        color: var(--bs-success);
    }

    .sv-recap-note {
        margin: .75rem 0 0;
        font-size: .78rem;
        color: var(--bs-secondary-color);
        display: flex;
        gap: .4rem;
    }

    .sv-recap-note i {
        margin-top: .1rem;
    }

    /* Bank rows --------------------------------------------------------------- */
    .sv-bank-row {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
    }

    .sv-bank-empty {
        padding: .85rem 1rem;
        border: 1px dashed var(--bs-border-color);
        border-radius: 10px;
        color: var(--bs-secondary-color);
        font-size: .82rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    @media (max-width: 575.98px) {
        .sv-methods {
            grid-template-columns: 1fr;
        }

        .sv-pay-key {
            margin-left: 0;
        }
    }
</style>

<form action="<?php echo base_url('insert/service/savePembayaran') ?>" id="form_pemPembayaran"
    enctype="multipart/form-data" method="post" class="sv-pay" novalidate
    data-dp-tercatat="<?= $dpTercatat ?>" data-bayar-tercatat="<?= $bayarTercatat ?>"
    data-lunas="<?= $isLunas ? '1' : '0' ?>" data-overpaid="<?= $isOverpaid ? '1' : '0' ?>"
    data-koreksi-mode="<?= $modeKoreksi ? '1' : '0' ?>"
    data-recorded-tunai="<?= $bayarTunaiTercatat ?>"
    data-recorded-banks="<?= htmlspecialchars(json_encode($recordedBanks), ENT_QUOTES) ?>"
    data-total="<?= $totalAwal ?>" data-diskon="<?= $diskonAwal ?>">

    <input type="hidden" name="idservice_p" value="<?php echo @$idservice ?>">
    <input type="hidden" id="idpelayar" value="<?php echo @$idservice ?>">

    <!-- Values carried to the server; never typed by the operator -->
    <input type="hidden" name="no_service_pembayaran" value="<?= htmlspecialchars($noService) ?>">
    <input type="hidden" name="diskon_pembayaran" id="diskon_pembayaran" value="<?= $rp($diskonAwal) ?>">
    <input type="hidden" name="total_harga_pembayaran" id="total_harga_pembayaran" value="<?= $rp($totalAwal) ?>">
    <input type="hidden" name="total_harga_pembayaran_akhir" id="total_harga_pembayaran_akhir" value="<?= $rp($dueAwal) ?>">
    <input type="hidden" name="kembalian" id="kembalian" value="Rp 0">
    <input type="hidden" name="koreksi_pembayaran" id="koreksi_pembayaran" value="<?= $modeKoreksi ? '1' : '0' ?>">

    <!-- Intro: ticket identity + field-state legend -->
    <div class="sv-pay-intro mb-3">
        <div class="sv-pay-ticket">
            <i class="bi bi-receipt text-primary"></i>
            <span class="sv-pay-ticket__label">No. Service</span>
            <span class="sv-pay-ticket__value"><?= $noService !== '' ? htmlspecialchars($noService) : 'Belum tersimpan' ?></span>
        </div>
        <div class="sv-pay-key">
            <span class="sv-pay-chip sv-pay-chip--auto <?= $paymentLocked ? '' : 'd-none' ?>" id="sv-pay-locked-chip">
                <i class="bi bi-lock-fill"></i><span id="sv-pay-locked-chip-label">Lunas · terkunci</span>
            </span>
            <span class="sv-pay-chip sv-pay-chip--edit <?= ($paymentLocked || $modeKoreksi) ? 'd-none' : '' ?>" id="sv-pay-edit-chip"
                title="Kolom ini diisi oleh operator.">
                <i class="bi bi-pencil-fill"></i> Dapat diisi
            </span>
            <span class="sv-pay-chip sv-pay-chip--edit <?= $modeKoreksi ? '' : 'd-none' ?>" id="sv-pay-correction-chip">
                <i class="bi bi-pencil-square"></i> Koreksi nominal
            </span>
            <span class="sv-pay-chip sv-pay-chip--auto <?= ($paymentLocked || $modeKoreksi) ? 'd-none' : '' ?>" id="sv-pay-auto-chip"
                title="Nilai dihitung sistem, tidak dapat diubah dari sini.">
                <i class="bi bi-lock-fill"></i> Otomatis
            </span>
        </div>
    </div>

    <div id="sv-payment-status-notice"
        class="alert <?= $isLunas ? 'alert-success' : (($isOverpaid || ($dpTercatat + $bayarTercatat) > 0) ? 'alert-warning' : 'alert-warning d-none') ?> d-flex align-items-start gap-2 mb-3"
        role="status" aria-live="polite">
            <i id="sv-payment-status-icon" class="bi <?= $isLunas ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?> mt-1" aria-hidden="true"></i>
            <div>
                <strong id="sv-payment-status-title"><?= $isLunas ? 'Service sudah lunas.' : ($isOverpaid ? 'Koreksi pembayaran diperlukan.' : 'Ada sisa tagihan.') ?></strong>
                <div class="small" id="sv-payment-status-copy">
                    <?php if ($isLunas): ?>
                        Total <?= $rp($totalAwal) ?> sudah tertutup oleh DP <?= $rp($dpTercatat) ?>
                        dan pembayaran <?= $rp($bayarTercatat) ?>. Pembayaran baru dinonaktifkan.
                    <?php elseif ($isOverpaid): ?>
                        Pembayaran tercatat lebih <?= $rp($kelebihanAwal) ?> dari total. Masukkan ulang nominal tunai/transfer yang benar; nilai lama akan diganti.
                    <?php elseif (($dpTercatat + $bayarTercatat) > 0): ?>
                        Sisa tagihan saat ini <?= $rp($dueAwal) ?>.
                    <?php else: ?>
                        Pilih metode pembayaran untuk melanjutkan.
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <div class="row g-3">
        <!-- ============================ EDITABLE ZONE ============================ -->
        <div class="col-lg-7">
            <fieldset class="sv-pay-editable" <?= $paymentLocked ? 'disabled' : '' ?>>

            <!-- Metode pembayaran -->
            <section class="sv-pay-section mb-3">
                <header class="sv-pay-section__head">
                    <span class="sv-pay-section__title"><i class="bi bi-wallet2"></i> Metode Pembayaran</span>
                    <span class="sv-pay-chip sv-pay-chip--auto <?= $paymentLocked ? '' : 'd-none' ?>" id="sv-pay-method-locked-chip"><i class="bi bi-lock-fill"></i><span class="ms-1" data-locked-copy>Terkunci · lunas</span></span>
                    <span class="sv-pay-chip sv-pay-chip--edit <?= ($paymentLocked || $modeKoreksi) ? 'd-none' : '' ?>" id="sv-pay-method-edit-chip"><i class="bi bi-pencil-fill"></i> Dapat diisi</span>
                    <span class="sv-pay-chip sv-pay-chip--edit <?= $modeKoreksi ? '' : 'd-none' ?>" id="sv-pay-method-correction-chip"><i class="bi bi-pencil-square"></i> Koreksi nominal</span>
                </header>
                <div class="sv-pay-section__body">
                    <div class="sv-methods" role="radiogroup" aria-label="Metode pembayaran">
                        <label class="sv-method">
                            <input class="sv-method__input" type="radio" name="metode_bayar" value="tunai">
                            <span class="sv-method__box">
                                <i class="bi bi-cash-coin"></i>
                                <span class="sv-method__label">Tunai</span>
                                <span class="sv-method__hint">Uang tunai dari laci</span>
                            </span>
                        </label>
                        <label class="sv-method">
                            <input class="sv-method__input" type="radio" name="metode_bayar" value="transfer">
                            <span class="sv-method__box">
                                <i class="bi bi-bank2"></i>
                                <span class="sv-method__label">Transfer</span>
                                <span class="sv-method__hint">Masuk ke rekening bank</span>
                            </span>
                        </label>
                        <label class="sv-method">
                            <input class="sv-method__input" type="radio" name="metode_bayar" value="tunai_transfer">
                            <span class="sv-method__box">
                                <i class="bi bi-shuffle"></i>
                                <span class="sv-method__label">Tunai + Transfer</span>
                                <span class="sv-method__hint">Gabungan dua cara bayar</span>
                            </span>
                        </label>
                    </div>

                    <!-- Empty state: nothing is fillable until a method is chosen -->
                    <div class="sv-bank-empty mt-3 sv-method-empty">
                        <i class="bi bi-arrow-up-circle"></i>
                        <span>Pilih salah satu metode di atas untuk membuka kolom jumlah pembayaran.</span>
                    </div>

                    <!-- Tunai -->
                    <div class="mt-3 sv-zone sv-zone-tunai d-none">
                        <label class="form-label" for="bayar">Uang Tunai Diterima</label>
                        <input type="text" name="bayar_pembayaran" id="bayar"
                            class="form-control form-control-lg sv-pay-input" placeholder="Rp 0"
                            inputmode="numeric" autocomplete="off" aria-describedby="bayar-help">
                        <div class="form-text" id="bayar-help">Jumlah uang tunai yang diterima dari pelanggan.</div>
                    </div>

                    <!-- Transfer -->
                    <div class="mt-3 sv-zone sv-zone-transfer d-none">
                        <label class="form-label d-block">Pembayaran via Bank</label>
                        <div id="bank-payment-container"></div>
                        <div class="sv-bank-empty mt-2" id="bank-empty">
                            <i class="bi bi-info-circle"></i>
                            <span>Belum ada bank. Klik “Tambah Bank” untuk mencatat transfer.</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="tambah-bank">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Bank
                        </button>
                    </div>
                </div>
            </section>

            <!-- DP opsional -->
            <section class="sv-pay-section">
                <header class="sv-pay-section__head">
                    <span class="sv-pay-section__title"><i class="bi bi-piggy-bank"></i> Uang Muka (DP)</span>
                    <span class="sv-pay-chip sv-pay-chip--auto <?= $paymentLocked ? '' : 'd-none' ?>" id="sv-pay-dp-locked-chip"><i class="bi bi-lock-fill"></i><span class="ms-1" data-dp-locked-copy>Terkunci · lunas</span></span>
                    <span class="sv-pay-chip sv-pay-chip--auto <?= $modeKoreksi ? '' : 'd-none' ?>" id="sv-pay-dp-correction-chip"><i class="bi bi-lock-fill"></i> DP tetap</span>
                    <span class="sv-pay-chip sv-pay-chip--auto <?= ($paymentLocked || $modeKoreksi) ? 'd-none' : '' ?>" id="sv-pay-dp-optional-chip"><i class="bi bi-slash-circle"></i> Opsional</span>
                </header>
                <div class="sv-pay-section__body">
                    <div class="alert alert-info d-flex align-items-start gap-2 py-2 px-3 mb-3" role="status">
                        <i class="bi bi-wallet2 mt-1"></i>
                        <div class="small">
                            DP tersimpan saat ini: <strong><?= $rp($dpTercatat) ?></strong>.
                            Nilai ini tidak dapat diubah dari sini; gunakan menu DP Service untuk settlement/refund.
                        </div>
                    </div>

                    <fieldset class="sv-pay-dp-editable" <?= ($modeKoreksi || $paymentLocked) ? 'disabled' : '' ?>>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="dp_bayar_pembayaran">Tambah Uang Muka Baru</label>
                            <input type="text" name="dp_bayar_pembayaran" id="dp_bayar_pembayaran"
                                class="form-control sv-pay-input" value="Rp 0" placeholder="Rp 0"
                                inputmode="numeric" autocomplete="off" aria-describedby="dp-bayar-help">
                            <div class="form-text" id="dp-bayar-help">Isi hanya bila menerima DP baru. Biarkan Rp 0 bila tidak ada.</div>
                        </div>
                        <div class="col-sm-6 d-none" id="dp-metode-pembayaran-col">
                            <label class="form-label" for="dp_metode_bayar">Metode DP</label>
                            <select id="dp_metode_bayar" name="dp_metode_bayar" class="form-select sv-pay-input">
                                <option value="">-- Pilih Metode --</option>
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                            <div class="form-text">Wajib bila DP baru diisi.</div>
                        </div>
                        <div class="col-sm-6 d-none" id="dp-bank-pembayaran-col">
                            <label class="form-label" for="dp_bank_idbank">Rekening Tujuan (DP)</label>
                            <select id="dp_bank_idbank" name="dp_bank_idbank" class="form-select sv-pay-input">
                                <option value="">-- Pilih Rekening --</option>
                                <?php if (!empty($bank)): ?>
                                    <?php foreach ($bank as $p): ?>
                                        <option value="<?= htmlspecialchars($p->idbank) ?>">
                                            <?= htmlspecialchars($p->nama_bank) . ' ' . ($p->atas_nama ?? '') ?> : <?= htmlspecialchars($p->norek ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 d-none" id="dp-referensi-pembayaran-col">
                            <label class="form-label" for="dp_referensi">No. Referensi Transfer (DP)</label>
                            <input type="text" name="dp_referensi" id="dp_referensi"
                                class="form-control sv-pay-input" autocomplete="off">
                            <div class="form-text">Opsional.</div>
                        </div>
                    </div>
                    </fieldset>
                </div>
            </section>
            </fieldset>
        </div>

        <!-- ============================= AUTO ZONE ============================== -->
        <div class="col-lg-5">
            <section class="sv-pay-section sv-pay-section--fill">
                <header class="sv-pay-section__head">
                    <span class="sv-pay-section__title"><i class="bi bi-calculator"></i> Rincian Tagihan</span>
                    <span class="sv-pay-chip sv-pay-chip--auto"><i class="bi bi-lock-fill"></i> Otomatis</span>
                </header>
                <div class="sv-pay-section__body">
                    <dl class="sv-recap">
                        <div class="sv-recap__row">
                            <dt>Total harga</dt>
                            <dd id="sv-total-harga"><?= $rp($totalAwal) ?></dd>
                        </div>
                        <div class="sv-recap__row">
                            <dt>Diskon</dt>
                            <dd id="sv-diskon"><?= $diskonAwal > 0 ? '&minus; ' . $rp($diskonAwal) : $rp(0) ?></dd>
                        </div>
                        <div class="sv-recap__row" id="sv-dp-row" <?= $dpTercatat > 0 ? '' : 'hidden' ?>>
                            <dt>DP diterima</dt>
                            <dd id="sv-dp">&minus; <?= $rp($dpTercatat) ?></dd>
                        </div>
                        <div class="sv-recap__row" id="sv-pembayaran-tercatat-row" <?= $bayarTercatat > 0 ? '' : 'hidden' ?>>
                            <dt>Pembayaran tersimpan</dt>
                            <dd id="sv-pembayaran-tercatat"><?= $rp($bayarTercatat) ?></dd>
                        </div>
                        <div class="sv-recap__row sv-recap__row--due">
                            <dt id="sv-payment-due-label"><?= $modeKoreksi ? 'Nominal pembayaran pengganti' : 'Sisa tagihan' ?></dt>
                            <dd id="sv-harus-dibayar" aria-live="polite"><?= $rp($dueAwal) ?></dd>
                        </div>
                        <div class="sv-recap__row">
                            <dt>Dibayar</dt>
                            <dd id="sv-dibayar" aria-live="polite">Rp 0</dd>
                        </div>
                        <div class="sv-recap__row sv-recap__row--change">
                            <dt>Kembalian</dt>
                            <dd id="sv-kembalian" aria-live="polite">Rp 0</dd>
                        </div>
                    </dl>
                    <p class="sv-recap-note">
                        <i class="bi bi-info-circle"></i>
                        <span>Nilai ini dihitung dari tahap <strong>Sparepart</strong>. Untuk mengubahnya, kembali ke tahap Sparepart.</span>
                    </p>
                </div>
            </section>
        </div>
    </div>

    <div class="service-step-footer">
        <button type="button" class="btn btn-outline-secondary" id="btn-previous-to-sparepart">
            <i class="bi bi-arrow-left me-1"></i> Sebelumnya
        </button>
        <button type="submit" id="submitSemuaForm" class="btn btn-success" <?= $paymentLocked ? 'disabled' : '' ?>>
            <i id="sv-pay-submit-icon" class="bi <?= $isLunas ? 'bi-check-circle-fill' : ($modeKoreksi ? 'bi-pencil-square' : 'bi-check2-circle') ?> me-1"></i>
            <span id="sv-pay-submit-label"><?= $isLunas ? 'Sudah Lunas' : ($modeKoreksi ? 'Koreksi Pembayaran' : 'Simpan Pembayaran') ?></span>
        </button>
    </div>
</form>

<script>
    (function () {
        "use strict";

        var root = document.getElementById("form_pemPembayaran");
        if (!root) return;

        var $ = window.jQuery;
        var dpTercatat = parseInt(root.dataset.dpTercatat || "0", 10) || 0;
        var bayarTercatat = parseInt(root.dataset.bayarTercatat || "0", 10) || 0;
        var initialTotal = parseInt(root.dataset.total || "0", 10) || 0;
        var initialDiskon = parseInt(root.dataset.diskon || "0", 10) || 0;

        function el(id) { return document.getElementById(id); }
        function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
        function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

        function parseRp(value) {
            if (!value) return 0;
            return parseInt(String(value).replace(/[^0-9]/g, ""), 10) || 0;
        }

        function formatRp(number) {
            return "Rp " + (number || 0).toLocaleString("id-ID");
        }

        function setText(id, text) {
            var node = el(id);
            if (node) node.textContent = text;
        }

        function isVisible(node) {
            return !!node && !node.classList.contains("d-none");
        }

        /* ---------------------------------------------------------------- zones */

        var zoneTunai = qs(".sv-zone-tunai", root);
        var zoneTransfer = qs(".sv-zone-transfer", root);
        var methodEmpty = qs(".sv-method-empty", root);
        var bankEmpty = el("bank-empty");

        function currentMethod() {
            var checked = qs('input[name="metode_bayar"]:checked', root);
            return checked ? checked.value : "";
        }

        function applyMethod(method) {
            root.dataset.method = method || "";

            if (methodEmpty) methodEmpty.classList.toggle("d-none", !!method);
            if (zoneTunai) zoneTunai.classList.toggle("d-none", method !== "tunai" && method !== "tunai_transfer");
            if (zoneTransfer) zoneTransfer.classList.toggle("d-none", method !== "transfer" && method !== "tunai_transfer");

            if ((method === "transfer" || method === "tunai_transfer") &&
                qsa(".sv-bank-row", root).length === 0) {
                tambahBarisBank();
            }

            hitungKembalian();
        }

        /* ------------------------------------------------------------- bank rows */

        function bankRowMarkup(index) {
            var options = '<option disabled selected>Pilih Bank</option>';
            <?php foreach ($bank as $p): ?>
            options += '<option value="<?= htmlspecialchars($p->idbank) ?>">' +
                <?= json_encode(htmlspecialchars($p->nama_bank) . ' ' . ($p->atas_nama ?? '') . ' : ' . htmlspecialchars($p->norek ?? '')) ?> +
                '</option>';
            <?php endforeach; ?>
            return '' +
                '<div class="sv-bank-row mb-2">' +
                '  <div class="flex-grow-1" style="min-width:0;">' +
                '    <select name="bank[' + index + '][id]" class="form-select bank-select" style="width:100%;" required>' + options + '</select>' +
                '  </div>' +
                '  <div style="width:9.5rem;flex-shrink:0;">' +
                '    <input type="text" class="form-control bank-amount sv-pay-input" name="bank[' + index + '][jumlah]" value="Rp 0" inputmode="numeric" autocomplete="off" aria-label="Jumlah transfer">' +
                '  </div>' +
                '  <button type="button" class="btn btn-outline-danger hapus-bank" aria-label="Hapus baris bank">&times;</button>' +
                '</div>';
        }

        function refreshBankEmpty() {
            if (bankEmpty) bankEmpty.classList.toggle("d-none", qsa(".sv-bank-row", root).length > 0);
        }

        function attachBankRowEvents(row) {
            var amount = row.querySelector(".bank-amount");
            if (amount) {
                amount.addEventListener("input", function () {
                    amount.value = formatRp(parseRp(amount.value));
                    hitungKembalian();
                });
            }
            var remove = row.querySelector(".hapus-bank");
            if (remove) {
                remove.addEventListener("click", function () {
                    row.remove();
                    refreshBankEmpty();
                    hitungKembalian();
                });
            }
        }

        function appendBankRow(preselectedId, amountValue) {
            var container = el("bank-payment-container");
            if (!container) return null;
            var index = qsa(".sv-bank-row", container).length;
            container.insertAdjacentHTML("beforeend", bankRowMarkup(index));
            var row = container.lastElementChild;
            var select = row.querySelector(".bank-select");
            var amount = row.querySelector(".bank-amount");
            if (select && preselectedId) select.value = String(preselectedId);
            if (amount && amountValue) amount.value = formatRp(amountValue);
            attachBankRowEvents(row);

            if (select && $ && $.fn && $.fn.select2) {
                $(select).select2({ dropdownParent: $("body") });
            }
            refreshBankEmpty();
            return row;
        }

        function tambahBarisBank() {
            appendBankRow(null, 0);
        }

        var tambahBank = el("tambah-bank");
        if (tambahBank) tambahBank.addEventListener("click", tambahBarisBank);

        /* ------------------------------------------------------------------ DP */

        function applyDp() {
            var dp = parseRp(el("dp_bayar_pembayaran") ? el("dp_bayar_pembayaran").value : 0);
            var show = dp > 0;
            var isTransfer = el("dp_metode_bayar") && el("dp_metode_bayar").value === "transfer";
            var showBank = show && isTransfer;

            el("dp-metode-pembayaran-col") && el("dp-metode-pembayaran-col").classList.toggle("d-none", !show);
            el("dp-bank-pembayaran-col") && el("dp-bank-pembayaran-col").classList.toggle("d-none", !showBank);
            el("dp-referensi-pembayaran-col") && el("dp-referensi-pembayaran-col").classList.toggle("d-none", !showBank);

            if (!show && el("dp_metode_bayar")) el("dp_metode_bayar").value = "";
            if (!showBank) {
                if (el("dp_bank_idbank")) el("dp_bank_idbank").value = "";
                if (el("dp_referensi")) el("dp_referensi").value = "";
            }
        }

        var dpInput = el("dp_bayar_pembayaran");
        if (dpInput) {
            dpInput.addEventListener("input", function () {
                dpInput.value = formatRp(parseRp(dpInput.value));
                applyDp();
            });
        }
        var dpMetode = el("dp_metode_bayar");
        if (dpMetode) dpMetode.addEventListener("change", applyDp);

        /* ---------------------------------------------------------------- recap */

        function sourceTotal() {
            var body = el("sparepart-table-body");
            var hasRows = body && body.children.length > 0;
            if (hasRows) {
                var sp = el("harga_akhir_sparepart");
                if (sp) return parseRp(sp.value);
            }
            return initialTotal;
        }

        function sourceDiskon() {
            var body = el("sparepart-table-body");
            var hasRows = body && body.children.length > 0;
            if (hasRows) {
                var sp = el("diskon_sparepart");
                if (sp) return parseRp(sp.value);
            }
            return initialDiskon;
        }

        function totalDibayar() {
            var total = 0;
            if (isVisible(zoneTunai) && el("bayar")) total += parseRp(el("bayar").value);
            if (isVisible(zoneTransfer)) {
                qsa(".bank-amount", root).forEach(function (input) { total += parseRp(input.value); });
            }
            return total;
        }

        function hitungKembalian() {
            var due = parseRp(el("total_harga_pembayaran_akhir") ? el("total_harga_pembayaran_akhir").value : 0);
            var paid = totalDibayar();
            var change = Math.max(0, paid - due);

            if (el("kembalian")) el("kembalian").value = formatRp(change);

            setText("sv-dibayar", formatRp(paid));
            setText("sv-kembalian", formatRp(change));
            var changeCell = el("sv-kembalian");
            if (changeCell) changeCell.classList.toggle("is-change", change > 0);
        }

        function syncBill() {
            var total = sourceTotal();
            var diskon = sourceDiskon();
            var totalTerkumpul = dpTercatat + bayarTercatat;
            var lunasNow = total > 0 && totalTerkumpul === total;
            var overpaidNow = total > 0 && totalTerkumpul > total && totalTerkumpul > 0;
            var correctionNow = overpaidNow;
            var lockedNow = lunasNow;
            var due = correctionNow
                ? Math.max(0, total - dpTercatat)
                : Math.max(0, total - dpTercatat - bayarTercatat);

            if (el("diskon_pembayaran")) el("diskon_pembayaran").value = formatRp(diskon);
            if (el("total_harga_pembayaran")) el("total_harga_pembayaran").value = formatRp(total);
            if (el("total_harga_pembayaran_akhir")) el("total_harga_pembayaran_akhir").value = formatRp(due);

            setText("sv-total-harga", formatRp(total));
            setText("sv-diskon", diskon > 0 ? "\u2212 " + formatRp(diskon) : formatRp(0));

            var dpRow = el("sv-dp-row");
            if (dpRow) dpRow.hidden = dpTercatat <= 0;
            setText("sv-dp", "\u2212 " + formatRp(dpTercatat));
            var pembayaranTercatatRow = el("sv-pembayaran-tercatat-row");
            if (pembayaranTercatatRow) pembayaranTercatatRow.hidden = bayarTercatat <= 0;
            setText("sv-pembayaran-tercatat", formatRp(bayarTercatat));
            setText("sv-harus-dibayar", formatRp(due));

            // Sparepart dapat berubah di tab sebelumnya tanpa reload halaman.
            // Status hanya benar-benar lunas bila total tercatat sama persis.
            root.dataset.lunas = lunasNow ? "1" : "0";
            root.dataset.overpaid = overpaidNow ? "1" : "0";
            root.dataset.koreksiMode = correctionNow ? "1" : "0";
            if (el("koreksi_pembayaran")) el("koreksi_pembayaran").value = correctionNow ? "1" : "0";
            var editable = qs(".sv-pay-editable", root);
            if (editable) editable.disabled = lockedNow;
            var dpEditable = qs(".sv-pay-dp-editable", root);
            if (dpEditable) dpEditable.disabled = lockedNow || correctionNow;
            var submitButton = el("submitSemuaForm");
            if (submitButton) submitButton.disabled = lockedNow;

            ["sv-pay-locked-chip", "sv-pay-method-locked-chip", "sv-pay-dp-locked-chip"].forEach(function (id) {
                var chip = el(id);
                if (chip) chip.classList.toggle("d-none", !lockedNow);
            });
            ["sv-pay-edit-chip", "sv-pay-auto-chip", "sv-pay-method-edit-chip", "sv-pay-dp-optional-chip"].forEach(function (id) {
                var chip = el(id);
                if (chip) chip.classList.toggle("d-none", lockedNow || correctionNow);
            });
            ["sv-pay-correction-chip", "sv-pay-method-correction-chip", "sv-pay-dp-correction-chip"].forEach(function (id) {
                var chip = el(id);
                if (chip) chip.classList.toggle("d-none", !correctionNow);
            });

            var lockedLabel = el("sv-pay-locked-chip-label");
            if (lockedLabel) lockedLabel.textContent = "Lunas · terkunci";
            var methodLockedLabel = qs("[data-locked-copy]", root);
            if (methodLockedLabel) methodLockedLabel.textContent = "Terkunci · lunas";
            var dpLockedLabel = qs("[data-dp-locked-copy]", root);
            if (dpLockedLabel) dpLockedLabel.textContent = "Terkunci · lunas";

            var submitIcon = el("sv-pay-submit-icon");
            var submitIconName = lunasNow ? "bi-check-circle-fill" : (correctionNow ? "bi-pencil-square" : "bi-check2-circle");
            if (submitIcon) submitIcon.className = "bi " + submitIconName + " me-1";
            setText("sv-pay-submit-label", lunasNow ? "Sudah Lunas" : (correctionNow ? "Koreksi Pembayaran" : "Simpan Pembayaran"));
            setText("sv-payment-due-label", correctionNow ? "Nominal pembayaran pengganti" : "Sisa tagihan");

            var notice = el("sv-payment-status-notice");
            var showNotice = lunasNow || overpaidNow || totalTerkumpul > 0;
            if (notice) {
                notice.classList.toggle("d-none", !showNotice);
                notice.classList.toggle("alert-success", lunasNow);
                notice.classList.toggle("alert-warning", showNotice && !lunasNow);
            }
            var noticeIcon = el("sv-payment-status-icon");
            if (noticeIcon) noticeIcon.className = "bi " + (lunasNow ? "bi-check-circle-fill" : "bi-exclamation-circle-fill") + " mt-1";
            setText("sv-payment-status-title", lunasNow
                ? "Service sudah lunas."
                : (overpaidNow ? "Koreksi pembayaran diperlukan." : "Ada sisa tagihan."));
            setText("sv-payment-status-copy", lunasNow
                ? "Total " + formatRp(total) + " sama dengan DP " + formatRp(dpTercatat) +
                    " dan pembayaran " + formatRp(bayarTercatat) + ". Pembayaran baru dikunci."
                : (overpaidNow
                    ? "Tercatat lebih " + formatRp(totalTerkumpul - total) + ". Masukkan ulang nominal tunai/transfer yang benar; nominal lama akan diganti. DP tetap."
                    : "Sisa tagihan saat ini " + formatRp(due) + " setelah DP " + formatRp(dpTercatat) +
                        " dan pembayaran tersimpan " + formatRp(bayarTercatat) + "."));

            hitungKembalian();

            if (typeof window.refreshServiceState === "function") window.refreshServiceState();
        }

        /* --------------------------------------------------------------- events */

        qsa('input[name="metode_bayar"]', root).forEach(function (radio) {
            radio.addEventListener("change", function () { applyMethod(radio.value); });
        });

        if (el("bayar")) {
            el("bayar").addEventListener("input", function (e) {
                e.target.value = formatRp(parseRp(e.target.value));
                hitungKembalian();
            });
        }

        var previous = el("btn-previous-to-sparepart");
        if (previous) {
            previous.addEventListener("click", function () {
                var trigger = document.querySelector("#sparepart-tab");
                if (trigger) new bootstrap.Tab(trigger).show();
            });
        }

        /* Keep the recap live when the Sparepart step changes underneath it. */
        var resyncTimer = null;
        function scheduleSync() {
            clearTimeout(resyncTimer);
            resyncTimer = setTimeout(syncBill, 90);
        }
        document.addEventListener("input", scheduleSync);
        document.addEventListener("change", scheduleSync);

        var spBody = el("sparepart-table-body");
        if (spBody && window.MutationObserver) {
            new MutationObserver(scheduleSync).observe(spBody, { childList: true, subtree: true });
        }

        /* ---------------------------------------------------------- validation */

        root.addEventListener("submit", function (e) {
            if (root.dataset.lunas === "1") {
                e.preventDefault();
                alert("Service ini sudah lunas. Pembayaran baru tidak dapat ditambahkan.");
                return;
            }

            var idPela = el("idpelayar") ? el("idpelayar").value : "";
            if (!idPela || idPela.trim() === "") {
                e.preventDefault();
                alert("Silakan pilih pelanggan terlebih dahulu melalui tombol input data pelanggan pada tab Pelanggan, lalu simpan.");
                return;
            }

            var method = currentMethod();
            if (!method) {
                e.preventDefault();
                alert("Pilih metode pembayaran terlebih dahulu.");
                return;
            }

            if (method === "transfer" || method === "tunai_transfer") {
                var rows = qsa(".sv-bank-row", root);
                var hasBank = rows.some(function (row) {
                    var select = row.querySelector(".bank-select");
                    var amount = row.querySelector(".bank-amount");
                    return !!(select && select.value) && parseRp(amount ? amount.value : 0) > 0;
                });
                var cash = parseRp(el("bayar") ? el("bayar").value : 0);
                if (method === "transfer" && !hasBank) {
                    e.preventDefault();
                    alert("Isi minimal satu rekening dan jumlah transfer.");
                    return;
                }
                if (method === "tunai_transfer" && !hasBank && cash <= 0) {
                    e.preventDefault();
                    alert("Isi jumlah tunai dan/atau transfer terlebih dahulu.");
                    return;
                }
            }

            var dpVal = dpInput ? parseRp(dpInput.value) : 0;
            if (dpVal > 0) {
                var metodeDp = el("dp_metode_bayar") ? el("dp_metode_bayar").value : "";
                if (!metodeDp) {
                    e.preventDefault();
                    alert("DP diisi. Silakan pilih Metode DP (Tunai / Transfer).");
                    return;
                }
                if (metodeDp === "transfer" && el("dp_bank_idbank") && !el("dp_bank_idbank").value) {
                    e.preventDefault();
                    alert("Metode Transfer (DP) wajib memilih Rekening Tujuan.");
                    return;
                }
            }

            var due = parseRp(el("total_harga_pembayaran_akhir") ? el("total_harga_pembayaran_akhir").value : 0);
            var paid = totalDibayar();
            if (paid < due && !confirm("Jumlah dibayar (" + formatRp(paid) + ") kurang dari tagihan (" +
                formatRp(due) + "). Lanjutkan simpan?")) {
                e.preventDefault();
            }
        });

        /* ----------------------------------------------------------------- init */

        document.addEventListener("DOMContentLoaded", function () {
            seedCorrectionFields();
            applyMethod(currentMethod());
            applyDp();
            syncBill();
        });

        /* When the recorded payment exceeds the total, an Admin Root may correct
           it. Pre-fill the recorded cash/transfer so the operator edits the wrong
           value instead of retyping the whole payment from scratch. */
        function seedCorrectionFields() {
            if (root.dataset.koreksiMode !== "1") return;

            var recordedTunai = parseInt(root.dataset.recordedTunai || "0", 10) || 0;
            var banks = [];
            try {
                banks = JSON.parse(root.dataset.recordedBanks || "[]") || [];
            } catch (e) {
                banks = [];
            }
            banks = banks.filter(function (b) { return b && ((b.id || 0) > 0 || (b.jumlah || 0) > 0); });

            if (el("bayar")) el("bayar").value = recordedTunai > 0 ? formatRp(recordedTunai) : "";
            banks.forEach(function (b) { appendBankRow(b.id || null, b.jumlah || 0); });

            var method = banks.length > 0
                ? (recordedTunai > 0 ? "tunai_transfer" : "transfer")
                : "tunai";
            var radio = qs('input[name="metode_bayar"][value="' + method + '"]', root);
            if (radio) radio.checked = true;
            applyMethod(method);
        }

        /* Expose for parity with other payment steps. */
        window.hitungKembalian = hitungKembalian;
        window.syncPembayaranState = syncBill;
    })();
</script>
