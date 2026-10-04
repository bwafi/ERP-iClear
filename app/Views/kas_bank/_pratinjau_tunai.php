<?php
/**
 * Pratinjau (preview) Setor Tunai & Penarikan Tunai.
 *
 * Dipakai kedua halaman. Pratinjau ini READ-ONLY: tidak ada efek ke saldo.
 * Semua angkanya dikirim controller dari KasBankCutoffService supaya yang
 * tampil di layar sama dengan angka yang dipakai service saat menyimpan.
 *
 * Dua hal yang dijaga di sini:
 *
 *   1. "Shared Account" ditulis terbuka. Rekening bersama TIDAK milik satu
 *      unit, jadi tidak boleh ditampilkan seolah-olah milik unit pemohon.
 *
 *   2. Saldo yang belum terverifikasi ditampilkan sebagai "belum tersedia",
 *      BUKAN Rp 0. Angka 0 yang belum diverifikasi artinya "belum tahu",
 *      bukan "tidak ada uang" — menampilkan 0 akan membuat user salah
 *      mengambil keputusan.
 *
 * @var array<string, mixed>          $preview
 * @var array<string, mixed>          $cutoff_info
 */
$rp        = static fn ($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$isSetor   = ($preview['arah'] ?? '') === 'setor';
$arahLabel = $isSetor ? 'Setor Tunai' : 'Penarikan Tunai';
$unitNama  = $preview['unit_nama'] ?? null;
$bank      = $preview['bank'] ?? [];
$kas       = $preview['kas'] ?? [];
$nominal   = (int) ($preview['nominal'] ?? 0);
$blokir    = $preview['blokir'] ?? [];
$bisaSubmit = (bool) ($preview['bisa_submit'] ?? false);
?>

<?php if ($preview !== null) : ?>

    <?php if (! empty($blokir)) : ?>
        <div class="alert alert-danger py-2 px-3 mb-3">
            <div class="d-flex align-items-start gap-2">
                <iconify-icon icon="bi:shield-exclamation" class="kb-ico flex-shrink-0"></iconify-icon>
                <div>
                    <strong><?= esc($arahLabel) ?> belum bisa diproses</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <?php foreach ($blokir as $alasan) : ?>
                            <li><?= esc($alasan) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="kb-tile-sm mb-3">
        <div class="kb-pane-head mb-2">
            <span class="kb-pane-title">Pratinjau</span>
            <?php if ($unitNama !== null) : ?>
                <span class="kb-badge kb-badge-muted">Unit transaksi: <?= esc($unitNama) ?></span>
            <?php endif; ?>
        </div>

        <?php /* ---------- KAKI KAS ---------- */ ?>
        <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom">
            <div>
                <div class="fw-semibold text-emphasis">
                    Kas <?= esc($unitNama ?? '—') ?>
                    <?php if (($kas['nama'] ?? null) !== null) : ?>
                        <span class="kb-badge kb-badge-muted ms-1"><?= esc($kas['nama']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="kb-hint">Rekening laci kas unit</div>
            </div>
            <div class="text-end">
                <?php if ($isSetor) : ?>
                    <div class="kb-num fw-bold text-danger">- <?= $rp($nominal) ?></div>
                <?php else : ?>
                    <div class="kb-num fw-bold text-success">+ <?= $rp($nominal) ?></div>
                <?php endif; ?>
                <div class="kb-hint">
                    <?php if (($kas['saldo'] ?? null) === null) : ?>
                        belum dapat dihitung
                    <?php else : ?>
                        saldo <?= $rp($kas['saldo']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* ---------- KAKI BANK ---------- */ ?>
        <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom">
            <div>
                <div class="fw-semibold text-emphasis">
                    <?= esc($bank['nama'] ?? '—') ?>
                    <?php if (! empty($bank['norek'])) : ?>
                        <span class="kb-mono ms-1"><?= esc($bank['norek']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="kb-hint">
                    <?php if (! empty($bank['is_shared'])) : ?>
                        <span class="kb-badge kb-badge-blue">Shared Account</span>
                    <?php else : ?>
                        Rekening bank
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <?php if ($isSetor) : ?>
                    <div class="kb-num fw-bold text-success">+ <?= $rp($nominal) ?></div>
                <?php else : ?>
                    <div class="kb-num fw-bold text-danger">- <?= $rp($nominal) ?></div>
                <?php endif; ?>
                <div class="kb-hint">
                    <?php if (($bank['saldo'] ?? null) === null) : ?>
                        belum tersedia
                    <?php else : ?>
                        saldo fisik <?= $rp($bank['saldo']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* ---------- STATUS STATEMENT ---------- */ ?>
        <?php if (! empty($bank['akun_id'])) : ?>
            <div class="py-2 border-bottom">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <span class="kb-label">Saldo statement cut-off</span>
                    <?php if (! empty($bank['terverifikasi'])) : ?>
                        <span class="kb-badge kb-badge-green">TERVERIFIKASI</span>
                    <?php else : ?>
                        <span class="kb-badge kb-badge-amber">BELUM DIVERIFIKASI</span>
                    <?php endif; ?>
                </div>
                <div class="kb-hint mt-1">
                    Statement <?= esc($cutoff_info['tanggal_cutoff'] ?? '') ?>
                    <?php if (empty($bank['terverifikasi'])) : ?>
                        — koran bank belum diverifikasi Finance, jadi saldo fisik di atas
                        <span class="fw-semibold">belum tersedia</span>. Nilai placeholder
                        <span class="kb-mono">0</span> tidak ditampilkan sebagai saldo faktual.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ---------- PENEMPATAN UNIT ---------- */ ?>
        <?php if (! empty($bank['is_shared'])) : ?>
            <div class="py-2 border-bottom">
                <div class="d-flex justify-content-between align-items-baseline">
                    <div>
                        <div class="fw-semibold text-emphasis">
                            Unit allocation — <?= esc($unitNama ?? '—') ?>
                        </div>
                        <div class="kb-hint">
                            Rekening bersama: dana ini menjadi hak unit tersebut, bukan saldo
                            rekening milik satu unit.
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if ($isSetor) : ?>
                            <div class="kb-num fw-bold text-success">+ <?= $rp($nominal) ?></div>
                            <div class="kb-hint">
                                <?= $rp($preview['posisi_unit_sebelum'] ?? 0) ?> &rarr;
                                <?= $rp($preview['posisi_unit_setelah'] ?? 0) ?>
                            </div>
                        <?php else : ?>
                            <div class="kb-num fw-bold text-danger">- <?= $rp($nominal) ?></div>
                            <div class="kb-hint">
                                <?= $rp($preview['entitlement_sebelum'] ?? 0) ?> &rarr;
                                <?= $rp($preview['entitlement_setelah'] ?? 0) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="py-2">
                <div class="kb-hint">
                    Rekening ini bukan shared account, jadi dana tidak dibagi ke unit lain —
                    seluruhnya untuk unit <?= esc($unitNama ?? '—') ?>.
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php else : ?>
    <div class="kb-tile-sm mb-3">
        <div class="kb-pane-head mb-2">
            <span class="kb-pane-title">Pratinjau</span>
        </div>
        <div class="kb-empty">
            <iconify-icon icon="bi:calculator" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
            Isi tanggal, rekening, dan nominal lalu tekan
            <strong>Tampilkan Pratinjau</strong> untuk melihat dampak saldo
            sebelum disimpan.
        </div>
    </div>
<?php endif; ?>