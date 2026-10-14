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

$tutupBisaDisimpan = (bool)($tutup_bisa_disimpan ?? false);
$sudahDitutup      = (bool)($sudah_ditutup ?? false);
$tutupAlasan       = (string)($tutup_alasan ?? '');
$wibOffsetMenit    = (int)($wibOffsetMenit ?? 420);
$tanggalHariIni    = (string)($tanggal ?? date('Y-m-d'));
$hariIni           = (string)($hari_ini ?? date('Y-m-d'));
$isTanggalHariIni  = $tanggalHariIni === $hariIni;
$isAdminRoot       = (bool)($is_admin_root ?? false);
$unitId            = isset($unit) ? (int)$unit : 0;
$namaUnit = (string)($nama_unit ?? (isset($unit) ? ($unit === 1 ? 'Probolinggo' : ($unit === 2 ? 'Jember' : ($unit === 3 ? 'Banyuwangi' : 'Pandaan'))) : '—'));
$tanggalPrint = !empty($tutupkasir->tanggal) ? date('Y-m-d', strtotime((string)$tutupkasir->tanggal)) : null;
$printSesuaiPilihan = $tanggalPrint !== null && $tanggalPrint === $tanggalHariIni;
$dpService = $dp_service ?? ['ledger_ada' => false, 'total_tunai' => 0, 'total_transfer' => 0, 'total' => 0, 'jumlah' => 0, 'rincian' => []];
$netKasBank = (int)($tarik ?? 0) - (int)($setor ?? 0);
?>

<div class="tutup-kasir tk-page">
    <header class="tk-header">
        <div class="container-fluid py-3">
            <nav aria-label="breadcrumb" class="d-flex align-items-center flex-wrap gap-3">
                <ol class="breadcrumb mb-0 flex-grow-1">
                    <li class="breadcrumb-item">
                        <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Tutup Kasir</li>
                </ol>
                <div class="tk-context">
                    <span><span class="text-muted">Unit</span> <strong><?= esc($namaUnit) ?> (#<?= $unitId ?>)</strong></span>
                    <span><span class="text-muted">Tanggal</span> <strong class="tk-tabular"><?= date('d M Y', strtotime($tanggalHariIni)) ?></strong></span>
                </div>
            </nav>
            <div class="tk-title-row">
                <div>
                    <h1 class="tk-page-title">Tutup Kasir</h1>
                    <p class="tk-page-subtitle mb-0">Hitung dan masukkan total uang fisik di laci.</p>
                </div>
                <?php if ($sudahDitutup) : ?>
                    <span class="tk-state tk-state--success"><iconify-icon icon="solar:check-circle-bold" width="17"></iconify-icon> Closing tersimpan</span>
                <?php elseif ($tutupBisaDisimpan) : ?>
                    <span class="tk-state tk-state--info"><span class="tk-state__dot"></span> Siap ditutup</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container-fluid py-4">
        <?php if ($isAdminRoot) : ?>
            <form action="<?= base_url('tutup_kasir') ?>" method="get" class="tk-admin-filter" aria-label="Pilih tanggal dan unit tutup kasir">
                <div class="tk-admin-filter__intro">
                    <strong>Filter Admin Root</strong>
                    <span>Pilih tanggal dan unit. Closing yang sudah tercatat tidak dapat dibuat ulang.</span>
                </div>
                <div>
                    <label for="filter_tanggal" class="form-label">Tanggal</label>
                    <input
                        type="date"
                        name="tanggal"
                        id="filter_tanggal"
                        class="form-control"
                        value="<?= esc($tanggalHariIni) ?>"
                        max="<?= esc($hariIni) ?>"
                        required
                    >
                </div>
                <div>
                    <label for="filter_unit" class="form-label">Unit</label>
                    <select name="unit" id="filter_unit" class="form-select" required>
                        <?php foreach (($unit_list ?? []) as $unitOption) : ?>
                            <option value="<?= (int)($unitOption['idunit'] ?? 0) ?>" <?= (int)($unitOption['idunit'] ?? 0) === $unitId ? 'selected' : '' ?>>
                                <?= esc($unitOption['NAMA_UNIT'] ?? ('Unit ' . (int)($unitOption['idunit'] ?? 0))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="tk-btn tk-btn--primary">
                    <iconify-icon icon="solar:filter-bold" width="18"></iconify-icon>
                    Tampilkan
                </button>
            </form>
        <?php endif; ?>

        <form action="<?= base_url('tutupkasir/tutup') ?>" method="post" id="formTutupKasir">
            <?= csrf_field() ?>
            <?php if ($isAdminRoot) : ?>
                <input type="hidden" name="tanggal" value="<?= esc($tanggalHariIni) ?>">
                <input type="hidden" name="unit" value="<?= $unitId ?>">
            <?php endif; ?>

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
                        <div class="tk-alert__title">Closing sudah tercatat</div>
                    <div class="tk-alert__desc">Unit ini sudah punya closing pada <?= esc(date('d M Y', strtotime($tanggalHariIni))) ?>.</div>
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

            <!-- Ringkasan saldo pembuka dan arus kas; saldo akhir sistem tidak ditampilkan saat closing. -->
            <section class="tk-section tk-flow-section mb-4" aria-labelledby="arusKasTitle">
                <header class="tk-section-header">
                    <div>
                        <h2 id="arusKasTitle" class="mb-1">Arus Kas <?= $isTanggalHariIni ? 'Hari Ini' : esc(date('d M Y', strtotime($tanggalHariIni))) ?></h2>
                        <p class="mb-0">Arus KAS dan transfer pada tanggal pilihan; setor/tarik memengaruhi KAS.</p>
                    </div>
                </header>
                <div class="tk-ledger-wrap">
                    <table class="tk-ledger">
                        <thead>
                            <tr>
                                <th scope="col">Ringkasan</th>
                                <th scope="col" class="text-end"><span class="tk-method"><span class="tk-method__dot tk-method__dot--cash"></span>KAS</span></th>
                                <th scope="col" class="text-end"><span class="tk-method"><span class="tk-method__dot tk-method__dot--transfer"></span>Transfer</span></th>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="tk-ledger__opening">
                                <th scope="row">
                                    <span>Saldo awal</span>
                                    <small class="tk-ledger__description">Saldo pembuka tanggal pilihan</small>
                                    <small class="tk-ledger__mobile-total"><?= ($belumAda || $tfBelumAda) ? 'Belum lengkap' : 'Total · Rp ' . number_format($awalCashNum + $awalTfNum, 0, ',', '.') ?></small>
                                </th>
                                <td data-label="KAS" class="text-end tk-tabular"><?= $belumAda ? '<span class="tk-ledger__missing">Belum ditetapkan</span>' : 'Rp ' . number_format($awalCashNum, 0, ',', '.') ?></td>
                                <td data-label="Transfer" class="text-end tk-tabular"><?= $tfBelumAda ? '<span class="tk-ledger__missing">Belum ditetapkan</span>' : 'Rp ' . number_format($awalTfNum, 0, ',', '.') ?></td>
                                <td data-label="Total" class="text-end tk-tabular"><?= ($belumAda || $tfBelumAda) ? '<span class="tk-ledger__missing">Belum lengkap</span>' : 'Rp ' . number_format($awalCashNum + $awalTfNum, 0, ',', '.') ?></td>
                            </tr>
                            <tr class="tk-ledger__income">
                                <th scope="row">
                                    <span>Pendapatan <em>+</em></span>
                                    <small class="tk-ledger__description">Penerimaan tunai dan transfer</small>
                                    <small class="tk-ledger__mobile-total">Total · Rp <?= number_format((int)($total_pendapatan ?? 0), 0, ',', '.') ?></small>
                                </th>
                                <td data-label="KAS" class="text-end tk-tabular">
                                    <strong>Rp <?= number_format((int)($kas_masuk ?? 0), 0, ',', '.') ?></strong>
                                    <?php if ((int)($dpService['total_tunai'] ?? 0) > 0) : ?>
                                        <small class="tk-ledger__note">Termasuk DP tunai Rp <?= number_format((int)$dpService['total_tunai'], 0, ',', '.') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Transfer" class="text-end tk-tabular">
                                    <strong>Rp <?= number_format((int)($transfer_masuk ?? 0), 0, ',', '.') ?></strong>
                                    <?php if ((int)($dpService['total_transfer'] ?? 0) > 0) : ?>
                                        <small class="tk-ledger__note">Termasuk DP transfer Rp <?= number_format((int)$dpService['total_transfer'], 0, ',', '.') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Total" class="text-end tk-tabular"><strong>Rp <?= number_format((int)($total_pendapatan ?? 0), 0, ',', '.') ?></strong></td>
                            </tr>
                            <tr class="tk-ledger__expense">
                                <th scope="row">
                                    <span>Pengeluaran <em>−</em></span>
                                    <small class="tk-ledger__description">Kas keluar tanggal pilihan</small>
                                    <small class="tk-ledger__mobile-total">Total · Rp <?= number_format((int)($total_pengeluaran ?? ((int)($kas_keluar ?? 0) + (int)($transfer_keluar ?? 0))), 0, ',', '.') ?></small>
                                </th>
                                <td data-label="KAS" class="text-end tk-tabular">− Rp <?= number_format((int)($kas_keluar ?? 0), 0, ',', '.') ?></td>
                                <td data-label="Transfer" class="text-end tk-tabular">− Rp <?= number_format((int)($transfer_keluar ?? 0), 0, ',', '.') ?></td>
                                <td data-label="Total" class="text-end tk-tabular">− Rp <?= number_format((int)($total_pengeluaran ?? ((int)($kas_keluar ?? 0) + (int)($transfer_keluar ?? 0))), 0, ',', '.') ?></td>
                            </tr>
                            <?php if (($transfer_internal['ada'] ?? false)) : ?>
                                <tr class="tk-ledger__internal">
                                    <th scope="row">
                                        <span>Perpindahan kas-bank</span>
                                        <small>Setor mengurangi KAS; tarik menambah KAS</small>
                                    </th>
                                    <td data-label="KAS" class="text-end tk-tabular">
                                        <?= $netKasBank > 0 ? '+' : ($netKasBank < 0 ? '−' : '') ?> Rp <?= number_format(abs($netKasBank), 0, ',', '.') ?>
                                        <?php if ((int)($setor ?? 0) > 0 || (int)($tarik ?? 0) > 0) : ?>
                                            <small class="tk-ledger__note">
                                                <?php if ((int)($setor ?? 0) > 0) : ?>Setor Rp <?= number_format((int)($setor ?? 0), 0, ',', '.') ?><?php endif; ?>
                                                <?php if ((int)($setor ?? 0) > 0 && (int)($tarik ?? 0) > 0) : ?> &middot; <?php endif; ?>
                                                <?php if ((int)($tarik ?? 0) > 0) : ?>Tarik Rp <?= number_format((int)($tarik ?? 0), 0, ',', '.') ?><?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Transfer" class="text-end tk-ledger__muted">Tidak mengubah saldo bank</td>
                                    <td data-label="Total" class="text-end tk-ledger__muted" aria-label="Mutasi internal, tidak dijumlahkan">—</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="tk-reconcile mb-4" aria-labelledby="uangLaciTitle">
                <div class="tk-reconcile__intro">
                    <span class="tk-reconcile__icon"><iconify-icon icon="solar:hand-money-bold" width="23"></iconify-icon></span>
                    <div>
                        <h2 id="uangLaciTitle">Hitung uang fisik di laci</h2>
                        <p class="mb-0">Hitung seluruh uang tunai di laci, lalu masukkan totalnya. Isi 0 jika laci memang kosong.</p>
                    </div>
                </div>
                <div class="tk-reconcile__body">
                    <div class="tk-reconcile__input">
                        <label for="uang_laci" class="tk-input__label">Total hasil hitung uang di laci</label>
                        <div class="tk-input-group">
                            <span class="input-group-text">Rp</span>
                            <input
                                type="text"
                                name="cash_laci"
                                id="uang_laci"
                                class="form-control"
                                placeholder="Masukkan jumlah"
                                inputmode="numeric"
                                required
                                autocomplete="off"
                                aria-describedby="uangLaciHint"
                                <?= $belumAda ? 'disabled' : '' ?>
                            >
                        </div>
                        <small id="uangLaciHint" class="tk-input__hint">Masukkan hasil hitung fisik, bukan angka dari ringkasan. Pemisah ribuan otomatis.</small>
                    </div>
                </div>
            </section>

            <details class="tk-details mb-4">
                <summary><iconify-icon icon="solar:calculator-bold" width="19"></iconify-icon> Cara data kas diproses</summary>
                <div class="tk-details__content">
                    <?php if ($belumAda) : ?>
                        <p class="mb-0">Belum tersedia karena saldo awal KAS belum ditetapkan.</p>
                    <?php else : ?>
                        <p class="mb-0">Data transaksi dan mutasi diproses otomatis oleh sistem. Masukkan hanya jumlah uang fisik yang sudah dihitung langsung dari laci.</p>
                    <?php endif; ?>
                </div>
            </details>

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
            <span class="tk-footer__meta">Pastikan seluruh uang fisik di laci sudah dihitung.</span>
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
                        <span class="tk-print-label">
                            Print
                            <?php if (!$printSesuaiPilihan) : ?>
                                <small>Closing terakhir · <?= esc(date('d M Y', strtotime((string)$tutupkasir->tanggal))) ?></small>
                            <?php endif; ?>
                        </span>
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
    const isTanggalHariIni = <?= $isTanggalHariIni ? 'true' : 'false' ?>;

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

        // Admin Root dapat menutup tanggal lampau yang belum memiliki closing;
        // batas jam harian hanya berlaku pada closing tanggal hari ini.
        if (!isTanggalHariIni) {
            btn.disabled = false;
            btn.innerHTML = `<iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon> Tutup Kasir`;
            btn.classList.remove('tk-btn--disabled');
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
