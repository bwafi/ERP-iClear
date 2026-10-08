<?php
/**
 * Banner diagnosa konfigurasi akun kas/bank.
 *
 * Muncul hanya kalau ada konfigurasi rekening/akun yang belum lengkap
 * (pemilihan rekening, alokasi shared account, atau opening/baseline KAS)
 * sehingga beberapa transaksi tidak bisa dipromosikan ke ledger `transaksi_kas_bank`.
 * Banner ini bersifat konfiguratif, bukan berarti seluruh jurnal operasional
 * wajib masuk `transaksi_kas_bank`.
 *
 * @var array<int, array{level:string, judul:string, detail:array<int,string>, aksi:string}> $diagnostik_konfigurasi
 */
$diagnostik = $diagnostik_konfigurasi ?? [];
?>
<?php if ($diagnostik !== []) : ?>
    <div class="kb-banner is-danger">
        <div class="kb-banner-icon text-danger">
            <iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon>
        </div>
        <div class="kb-banner-content">
            <strong>Konfigurasi rekening kas/bank belum lengkap</strong>
            <div class="mt-1">
                Ini berkaitan dengan <span class="fw-semibold">konfigurasi rekening, alokasi shared account,
                atau opening/baseline KAS</span>. Beberapa transaksi yang membutuhkan
                penentuan rekening fisik <span class="fw-semibold">tidak dapat diposting ke
                <code>transaksi_kas_bank</code></span> hingga konfigurasi diperbaiki.
                Transaksi jurnal aslinya tetap tersimpan sesuai sumbernya.
            </div>

            <?php foreach ($diagnostik as $d) : ?>
                <div class="mt-3">
                    <div class="fw-semibold"><?= esc($d['judul']) ?></div>
                    <div class="kb-alloc mt-2">
                        <?php foreach ($d['detail'] as $item) : ?>
                            <span class="kb-badge kb-badge-red"><?= esc($item) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2"><?= esc($d['aksi']) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="mt-3">
                <a href="<?= base_url('kas_bank/akun') ?>" class="fw-semibold text-decoration-underline">Rekening &amp; Saldo</a>
                &rarr; untuk menambahkan rekening, unit pemilik, dan Hak Unit.
            </div>
        </div>
    </div>
<?php endif; ?>
