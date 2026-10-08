<?= $this->include('jurnal/_tutup_kasir_theme') ?>

<?php
// ==============================================================
// DATA PERSIAPAN — SAMA DENGAN YANG DIPAKAI SERVER SAAT SIMPAN
// ==============================================================
$awalKas   = $saldo_awal_kas ?? ['ada' => false, 'nilai' => null, 'sumber' => '', 'pesan' => ''];
$awalTf    = $saldo_awal_tf  ?? ['ada' => false, 'nilai' => null, 'sumber' => '', 'pesan' => ''];
$belumAda  = !$awalKas['ada'];
$tfBelumAda = !$awalTf['ada'];

$awalCashNum = $belumAda ? 0 : (int)$awalKas['nilai'];
$awalTfNum   = $tfBelumAda ? 0 : (int)$awalTf['nilai'];

// Saldo seharusnya (sistem) — dihitung ulang server, di sini hanya untuk tampilan
$akhirCashSistem = $belumAda ? null : ($awalCashNum + ($kas_masuk ?? 0) - ($kas_keluar ?? 0) - ($setor ?? 0) + ($tarik ?? 0));
$akhirTfSistem   = $tfBelumAda ? null : ($awalTfNum + ($transfer_masuk ?? 0) - ($transfer_keluar ?? 0));

$tutupBisaDisimpan = (bool)($tutup_bisa_disimpan ?? false);
$sudahDitutup      = (bool)($sudah_ditutup ?? false);
$tutupAlasan       = (string)($tutup_alasan ?? '');
$wibOffsetMenit    = (int)($wibOffsetMenit ?? 420);
$tanggalHariIni    = (string)($tanggal ?? date('Y-m-d'));
$unitId            = isset($unit) ? (int)$unit : 0;
$namaUnit = isset($unit) ? ($unit === 1 ? 'Probolinggo' : ($unit === 2 ? 'Jember' : ($unit === 3 ? 'Banyuwangi' : 'Pandaan'))) : '—';
?>

<div class="tutup-kasir tk-page">
    <!-- ===== HEADER FLAT ===== -->
    <header class="tk-header">
        <div class="container-fluid py-3">
            <nav aria-label="breadcrumb" class="d-flex align-items-center flex-wrap gap-3 mb-2">
                <ol class="breadcrumb mb-0 me-auto">
                    <li class="breadcrumb-item">
                        <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Tutup Kasir</li>
                </ol>
                <div class="text-end text-nowrap">
                    <div class="small text-muted">Unit: <span class="fw-medium"><?= esc($namaUnit) ?> (#<?= $unitId ?>)</span></div>
                    <div class="small text-muted">Tanggal: <span class="fw-medium" style="font-variant-numeric: tabular-nums;"><?= date('d M Y', strtotime($tanggalHariIni)) ?></span></div>
                </div>
            </nav>
        </div>
    </header>

    <main class="container-fluid py-4">
        <form action="<?= base_url('tutupkasir/tutup') ?>" method="post" id="formTutupKasir">
            <?= csrf_field() ?>

            <!-- Hidden fields hanya untuk tampilan/audit — server menghitung ulang -->
            <input type="hidden" name="awal_cash"        value="<?= $belumAda ? '' : $awalCashNum ?>">
            <input type="hidden" name="awal_transfer"    value="<?= $tfBelumAda ? '' : $awalTfNum ?>">
            <input type="hidden" name="akhir_cash"       value="<?= $akhirCashSistem === null ? '' : (int)$akhirCashSistem ?>">
            <input type="hidden" name="akhir_transfer"   value="<?= $akhirTfSistem === null ? '' : (int)$akhirTfSistem ?>">
            <input type="hidden" name="pendapatan_cash"  value="<?= (int)($kas_masuk ?? 0) ?>">
            <input type="hidden" name="pendapatan_transfer" value="<?= (int)($transfer_masuk ?? 0) ?>">
            <input type="hidden" name="pengeluaran_cash" value="<?= (int)($kas_keluar ?? 0) ?>">
            <input type="hidden" name="pengeluaran_transfer" value="<?= (int)($transfer_keluar ?? 0) ?>">
            <input type="hidden" name="setor"            value="<?= (int)($setor ?? 0) ?>">
            <input type="hidden" name="tarik"            value="<?= (int)($tarik ?? 0) ?>">

            <!-- ===== ALERT ZONE ===== -->
            <?php if ($belumAda || $tfBelumAda) : ?>
                <div class="tk-alert tk-alert--warning mb-3" role="alert">
                    <iconify-icon icon="solar:danger-triangle-bold" width="20"></iconify-icon>
                    <div class="flex-grow-1">
                        <div class="tk-alert__title">Saldo awal belum ditetapkan</div>
                        <div class="tk-alert__desc">Angka nol di bawah <strong>bukan</strong> berarti laci kosong. Itu berarti belum ada sumber saldo yang sah.</div>
                        <?php if ($belumAda) : ?>
                            <div class="tk-alert__desc mt-1"><?= esc($awalKas['pesan']) ?></div>
                        <?php endif; ?>
                        <?php if ($tfBelumAda) : ?>
                            <div class="tk-alert__desc mt-1"><?= esc($awalTf['pesan']) ?></div>
                        <?php endif; ?>
                        <div class="tk-alert__desc mt-2">
                            Tetapkan baseline di menu <a href="<?= base_url('kas_bank/akun') ?>" class="tk-alert__link">Kas & Bank &rarr; Opening KAS</a>.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($sudahDitutup) : ?>
                <div class="tk-alert tk-alert--danger mb-3" role="alert">
                    <iconify-icon icon="solar:check-circle-bold" width="20"></iconify-icon>
                    <div>
                        <div class="tk-alert__title">Sudah tutup kasir hari ini</div>
                        <div class="tk-alert__desc">Unit ini sudah punya closing untuk tanggal hari ini.</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$tutupBisaDisimpan && !$sudahDitutup && ($belumAda || $tfBelumAda)) : ?>
                <div class="tk-alert tk-alert--danger mb-3" role="alert">
                    <iconify-icon icon="solar:close-circle-bold" width="20"></iconify-icon>
                    <div>
                        <div class="tk-alert__title">Tutup kasir ditolak</div>
                        <div class="tk-alert__desc"><?= esc($tutupAlasan) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ===== HERO SECTION ===== -->
            <section class="tk-hero mb-4">
                <!-- Kiri: Saldo Awal -->
                <div class="tk-hero__balance tk-card">
                    <div class="d-flex align-items-center mb-3">
                        <iconify-icon icon="solar:wallet-bold" width="28" class="text-primary me-2"></iconify-icon>
                        <h5 class="mb-0 fw-semibold">Saldo Awal</h5>
                    </div>
                    <?php if ($belumAda && $tfBelumAda) : ?>
                        <div class="tk-balance__amount text-warning">Belum ditetapkan</div>
                        <div class="tk-balance__breakdown">Tetapkan saldo awal untuk melanjutkan</div>
                    <?php else : ?>
                        <div class="tk-balance__amount">
                            Rp <?= number_format($awalCashNum + $awalTfNum, 0, ',', '.') ?>
                        </div>
                        <div class="tk-balance__breakdown">
                            <?php if (!$belumAda) : ?>
                                Kas <strong class="tk-tabular">Rp <?= number_format($awalCashNum, 0, ',', '.') ?></strong>
                            <?php endif; ?>
                            <?php if (!$tfBelumAda && !$belumAda) : ?>
                                &middot;
                            <?php endif; ?>
                            <?php if (!$tfBelumAda) : ?>
                                Transfer <strong class="tk-tabular">Rp <?= number_format($awalTfNum, 0, ',', '.') ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php if (!$belumAda && !empty($awalKas['pesan'])) : ?>
                            <div class="tk-balance__note"><?= esc($awalKas['pesan']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Kanan: Input Uang Laci -->
                <div class="tk-hero__input">
                    <label for="uang_laci" class="tk-input__label">
                        <iconify-icon icon="solar:hand-money-bold" width="24"></iconify-icon>
                        Total Uang di Laci
                    </label>
                    <div class="tk-input-group mb-2">
                        <span class="input-group-text fw-semibold">Rp</span>
                        <input
                            type="text"
                            name="cash_laci"
                            id="uang_laci"
                            class="form-control"
                            placeholder="0"
                            inputmode="numeric"
                            required
                            autocomplete="off"
                            <?= $belumAda ? 'disabled' : '' ?>
                        >
                    </div>
                    <small class="tk-input__hint d-block mb-3">
                        Isi sesuai uang fisik yang ada di kas/laci. Isi <strong>0</strong> kalau laci memang kosong.
                        Pemisah ribuan otomatis memakai titik, jadi <strong>1.500.000</strong> berarti 1.500.000.
                    </small>
                </div>
            </section>

            <!-- ===== RINGKASAN PENDAPATAN & PENGELUARAN (BOARD) ===== -->
            <section class="tk-section mb-4">
                <header class="p-3 border-bottom">
                    <h5 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                        <iconify-icon icon="solar:document-text-bold" width="22"></iconify-icon>
                        Ringkasan Pendapatan & Pengeluaran
                    </h5>
                </header>
                <div class="p-3">
                    <div class="tk-summary">
                        <!-- Pendapatan Cash -->
                        <article class="tk-summary__item tk-summary__item--income">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pendapatan Cash</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:money-bag-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)($kas_masuk ?? 0), 0, ',', '.') ?>
                            </div>
                        </article>

                        <!-- Pendapatan Transfer -->
                        <article class="tk-summary__item tk-summary__item--income">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pendapatan Transfer</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:card-transfer-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)($transfer_masuk ?? 0), 0, ',', '.') ?>
                            </div>
                        </article>

                        <!-- Total Pendapatan -->
                        <article class="tk-summary__item tk-summary__item--income-total">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Total Pendapatan</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:wallet-money-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)($total_pendapatan ?? 0), 0, ',', '.') ?>
                            </div>
                        </article>

                        <!-- Pengeluaran Cash -->
                        <article class="tk-summary__item tk-summary__item--expense">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pengeluaran Cash</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)($kas_keluar ?? 0), 0, ',', '.') ?>
                            </div>
                        </article>

                        <!-- Pengeluaran Transfer -->
                        <article class="tk-summary__item tk-summary__item--expense">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pengeluaran Transfer</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)($transfer_keluar ?? 0), 0, ',', '.') ?>
                            </div>
                        </article>

                        <!-- Total Pengeluaran -->
                        <article class="tk-summary__item tk-summary__item--expense-total">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Total Pengeluaran</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">
                                Rp <?= number_format((int)(($kas_keluar ?? 0) + ($transfer_keluar ?? 0)), 0, ',', '.') ?>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ===== COLLAPSIBLE: SALDO SEHARUSNYA ===== -->
            <details class="tk-details tk-card tk-details mb-4">
                <summary>
                    <iconify-icon icon="solar:calculator-bold" width="22"></iconify-icon>
                    Saldo Seharusnya (Sistem)
                </summary>
                <div class="tk-details__content">
                    <?php if ($akhirCashSistem === null) : ?>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Status</span>
                            <span class="tk-details__value text-warning">Belum tersedia</span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Alasan</span>
                            <span class="tk-details__value">Saldo awal belum ditetapkan, jadi sistem tidak menghitung angka apa pun.</span>
                        </div>
                    <?php else : ?>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Saldo Awal</span>
                            <span class="tk-details__value tk-tabular">Rp <?= number_format($awalCashNum, 0, ',', '.') ?></span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Kas Masuk (Pendapatan)</span>
                            <span class="tk-details__value tk-tabular text-success">+ Rp <?= number_format((int)($kas_masuk ?? 0), 0, ',', '.') ?></span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Kas Keluar (Pengeluaran)</span>
                            <span class="tk-details__value tk-tabular text-danger">- Rp <?= number_format((int)($kas_keluar ?? 0), 0, ',', '.') ?></span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Setor (Kas &rarr; Bank)</span>
                            <span class="tk-details__value tk-tabular text-info">- Rp <?= number_format((int)($setor ?? 0), 0, ',', '.') ?></span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label">Tarik (Bank &rarr; Kas)</span>
                            <span class="tk-details__value tk-tabular text-info">+ Rp <?= number_format((int)($tarik ?? 0), 0, ',', '.') ?></span>
                        </div>
                        <div class="tk-details__row">
                            <span class="tk-details__label"><strong>Saldo Seharusnya</strong></span>
                            <span class="tk-details__value tk-tabular"><strong>Rp <?= number_format($akhirCashSistem, 0, ',', '.') ?></strong></span>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <!-- ===== COLLAPSIBLE: PERPINDAHAN KAS <-> BANK ===== -->
            <?php if (($transfer_internal['ada'] ?? false)) : ?>
            <details class="tk-details tk-card tk-details mb-4">
                <summary>
                    <iconify-icon icon="solar:swap-horizontal-bold" width="22"></iconify-icon>
                    Perpindahan Kas &harr; Bank Hari Ini
                </summary>
                <div class="tk-details__content">
                    <div class="tk-details__row">
                        <span class="tk-details__label">Setor (Kas &rarr; Bank)</span>
                        <span class="tk-details__value tk-tabular text-info">Rp <?= number_format((int)($setor ?? 0), 0, ',', '.') ?></span>
                    </div>
                    <div class="tk-details__row">
                        <span class="tk-details__label">Tarik (Bank &rarr; Kas)</span>
                        <span class="tk-details__value tk-tabular text-info">Rp <?= number_format((int)($tarik ?? 0), 0, ',', '.') ?></span>
                    </div>
                    <div class="tk-details__row">
                        <span class="tk-details__label"><strong>Net Perpindahan</strong></span>
                        <span class="tk-details__value tk-tabular"><strong><?= (int)($tarik ?? 0) - (int)($setor ?? 0) >= 0 ? '+' : '' ?><?= number_format((int)($tarik ?? 0) - (int)($setor ?? 0), 0, ',', '.') ?></strong></span>
                    </div>
                </div>
            </details>
            <?php endif; ?>

            <!-- ===== PERINGATAN TERLAMBAT (JS shows/hides) ===== -->
            <div class="tk-late d-none" id="peringatanTerlambat" role="alert">
                <iconify-icon icon="solar:danger-triangle-bold" width="22"></iconify-icon>
                <div>
                    <div class="tk-late__title">Closing terlambat</div>
                    <div class="tk-late__desc">
                        Sekarang sudah lewat <strong>23:00 WIB</strong>. Tutup Kasir tetap
                        <strong>dibuka</strong> supaya kasir bisa tetap menutup buku hari ini,
                        tapi closing ini tercatat di luar jam closing normal.
                    </div>
                </div>
            </div>

            <!-- ===== STICKY FOOTER ===== -->
            <footer class="tk-footer">
                <button
                    type="submit"
                    id="btnTutupKasir"
                    class="tk-btn tk-btn--danger"
                    <?= (!$tutupBisaDisimpan || $sudahDitutup) ? 'disabled' : '' ?>
                >
                    <iconify-icon icon="solar:lock-keyhole-bold"></iconify-icon>
                    Tutup Kasir
                </button>

                <?php if (!empty($tutupkasir) && !empty($tutupkasir->idtutupkasir)) : ?>
                    <a href="<?= base_url('/cetak-tutup-kasir/' . $tutupkasir->idtutupkasir) ?>"
                       target="_blank"
                       class="tk-btn tk-btn--primary">
                        <iconify-icon icon="solar:printer-bold"></iconify-icon>
                        Print
                    </a>
                <?php endif; ?>
            </footer>
        </form>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn   = document.getElementById('btnTutupKasir');
    const form  = btn?.closest('form');
    const peringatan = document.getElementById('peringatanTerlambat');

    if (!btn || !form) return;

    const tutupBisaDisimpan = <?= $tutupBisaDisimpan ? 'true' : 'false' ?>;
    const sudahDitutup      = <?= $sudahDitutup ? 'true' : 'false' ?>;
    const OFFSET_WIB_MENIT = <?= $wibOffsetMenit ?? 420 ?>;

    // Waktu dinding WIB (Asia/Jakarta)
    function waktuWib() {
        const d = new Date(Date.now() + OFFSET_WIB_MENIT * 60000);
        return {
            jam: d.getUTCHours(),
            menit: d.getUTCMinutes()
        };
    }

    function cekWaktu() {
        if (sudahDitutup) {
            btn.disabled = true;
            btn.innerHTML = `<iconify-icon icon="solar:check-circle-bold" width="20"></iconify-icon> Sudah Tutup Kasir`;
            btn.classList.add('tk-btn--disabled');
            if (peringatan) peringatan.classList.add('d-none');
            return;
        }
        if (!tutupBisaDisimpan) {
            btn.disabled = true;
            btn.innerHTML = `<iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon> Saldo Awal Belum Ditetapkan`;
            btn.classList.add('tk-btn--disabled');
            if (peringatan) peringatan.classList.add('d-none');
            return;
        }

        const now = waktuWib();
        const totalMenit = now.jam * 60 + now.menit;

        // Window: <20:45 disabled, 20:45-22:59 normal, >=23:00 enabled + warning
        const MULAI_NORMAL = 20 * 60 + 45;
        const BATAS_TERLAMBAT = 23 * 60;
        const terlambat = totalMenit >= BATAS_TERLAMBAT;
        btn.innerHTML = terlambat
            ? `<iconify-icon icon="solar:danger-triangle-bold" width="20"></iconify-icon> Tutup Kasir (Terlambat)`
            : `<iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon> Tutup Kasir`;

        if (totalMenit < MULAI_NORMAL) {
            btn.disabled = true;
            btn.innerHTML = `<iconify-icon icon="solar:clock-circle-bold" width="20"></iconify-icon> Belum Waktunya`;
            btn.classList.add('tk-btn--disabled');
            if (peringatan) peringatan.classList.add('d-none');
        } else {
            btn.disabled = false;
            btn.classList.remove('tk-btn--disabled');
            if (peringatan) {
                peringatan.classList.toggle('d-none', !terlambat);
            }
        }
    }

    // Double-submit guard
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Memproses...`;
    });

    // Format Rupiah di input uang laci (pemisah ribuan titik)
    const uangLaci = document.getElementById('uang_laci');
    if (uangLaci) {
        uangLaci.addEventListener('input', function (e) {
            let v = e.target.value.replace(/[^0-9]/g, '');
            if (v === '') {
                e.target.value = '';
                return;
            }
            const parts = [];
            while (v.length > 3) {
                parts.unshift(v.slice(-3));
                v = v.slice(0, -3);
            }
            if (v.length) parts.unshift(v);
            e.target.value = parts.join('.');
        });
    }

    cekWaktu();
    setInterval(cekWaktu, 10000);
});
</script>