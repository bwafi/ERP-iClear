<?php
/**
 * Banner diagnosa konfigurasi akun kas/bank.
 *
 * Muncul hanya kalau ada rekening yang belum bisa dipakai transaksi. Isinya
 * murni konfigurasi — tidak ada angka saldo atau arus kas di sini, jadi
 * halaman ini tidak mengubah laporan mana pun.
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
            <strong>Ada rekening yang belum bisa dipakai transaksi</strong>
            <div class="mt-1">
                Transaksi tetap tersimpan di jurnal aslinya, tapi <span class="fw-semibold">tidak masuk ke ledger
                <code>transaksi_kas_bank</code></span>. Perbaiki dulu, lalu jalankan
                <code>php spark kasbank:backfill</code> untuk mem-posting ulang yang tertinggal.
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
