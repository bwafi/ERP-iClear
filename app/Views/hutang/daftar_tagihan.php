<?php

/**
 * Daftar tagihan hutang supplier + pencatatan pembayaran.
 *
 * Alur pembayaran tetap milik server: form ini hanya mengirim tunai/bank dan
 * sisa hasil perhitungan ke `PembayaranHutang::insert_cicilan`, yang menyimpan
 * pembayaran, posting ledger, memperbarui `pembelian`, lalu menyinkronkan
 * proyeksi `hutang_piutang`.
 */

$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');

$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tgl = static function ($value) use ($bulan): array {
    $value = trim((string) ($value ?? ''));
    if ($value === '' || substr($value, 0, 10) === '0000-00-00') {
        return ['ymd' => '', 'label' => '—'];
    }

    $ts = strtotime(substr($value, 0, 10));
    if ($ts === false) {
        return ['ymd' => '', 'label' => '—'];
    }

    return [
        'ymd'   => date('Y-m-d', $ts),
        'label' => date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts),
    ];
};

$hariIni = strtotime(date('Y-m-d'));

$dueMeta = static function ($value) use ($hariIni, $tgl): array {
    $info = $tgl($value);
    if ($info['ymd'] === '') {
        return $info + ['note' => 'Tanpa jatuh tempo', 'class' => '', 'status' => 'Tanpa tempo', 'tone' => 'is-ok'];
    }

    $hari = (int) floor((strtotime($info['ymd']) - $hariIni) / 86400);

    if ($hari < 0) {
        $note = 'Terlambat ' . abs($hari) . ' hari';

        return $info + ['note' => $note, 'class' => 'is-danger', 'status' => 'Terlambat', 'tone' => 'is-danger', 'days' => $hari];
    }

    if ($hari === 0) {
        return $info + ['note' => 'Jatuh tempo hari ini', 'class' => 'is-warn', 'status' => 'Hari ini', 'tone' => 'is-warn', 'days' => 0];
    }

    if ($hari === 1) {
        return $info + ['note' => 'Besok', 'class' => 'is-warn', 'status' => '≤7 hari', 'tone' => 'is-warn', 'days' => 1];
    }

    if ($hari <= 7) {
        return $info + ['note' => $hari . ' hari lagi', 'class' => 'is-warn', 'status' => '≤7 hari', 'tone' => 'is-warn', 'days' => $hari];
    }

    return $info + ['note' => $hari . ' hari lagi', 'class' => '', 'status' => 'Aman', 'tone' => 'is-ok', 'days' => $hari];
};

$rows = is_array($hutang ?? null) ? $hutang : [];

$totalSisa = 0;
$terlambat = 0;
$segera = 0;
$unitList = [];

foreach ($rows as $row) {
    $sisa = (float) ($row->sisa ?? 0);
    $totalSisa += $sisa;

    $due = $dueMeta($row->jatuh_tempo ?? null);
    if (($due['days'] ?? null) !== null) {
        if ($due['days'] < 0) {
            $terlambat++;
        } elseif ($due['days'] <= 7) {
            $segera++;
        }
    }

    $unit = trim((string) ($row->NAMA_UNIT ?? ''));
    if ($unit !== '' && !in_array($unit, $unitList, true)) {
        $unitList[] = $unit;
    }
}

sort($unitList);
$jumlahTagihan = count($rows);
?>

<?= view('hutang/_daftar_tagihan_theme') ?>

<div class="dt-page">
    <header class="dt-head">
        <div>
            <nav class="dt-crumb" aria-label="Breadcrumb">
                <a href="<?= base_url('hutangpiutang/hutang') ?>">Hutang</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Tagihan Supplier</span>
            </nav>
            <h1>Tagihan Supplier</h1>
            <p class="dt-head__desc">
                Pelunasan hutang pembelian. Cari nota, cek jatuh tempo, lalu catat pembayaran tunai atau bank —
                pembayaran langsung memperbarui sisa hutang dan kas/bank unit.
            </p>
        </div>
        <div class="dt-head__actions">
            <a class="dt-btn dt-btn--ghost" href="<?= base_url('umur_hutang') ?>">
                <iconify-icon icon="solar:clock-circle-linear" aria-hidden="true"></iconify-icon>
                Umur hutang
            </a>
            <a class="dt-btn dt-btn--ghost" href="<?= base_url('riwayat_pembayaran_hutang') ?>">
                <iconify-icon icon="solar:history-linear" aria-hidden="true"></iconify-icon>
                Riwayat
            </a>
        </div>
    </header>

    <section class="dt-summary" aria-label="Ringkasan tagihan">
        <div class="dt-summary__cell">
            <span class="dt-summary__label">Tagihan terbuka</span>
            <span class="dt-summary__value"><?= number_format($jumlahTagihan, 0, ',', '.') ?></span>
        </div>
        <div class="dt-summary__cell">
            <span class="dt-summary__label">Sisa hutang</span>
            <span class="dt-summary__value dt-summary__value--lead"><?= $rp($totalSisa) ?></span>
        </div>
        <div class="dt-summary__cell">
            <span class="dt-summary__label">Jatuh tempo ≤7 hari</span>
            <span class="dt-summary__value <?= $segera > 0 ? 'is-warn' : '' ?>"><?= number_format($segera, 0, ',', '.') ?></span>
        </div>
        <div class="dt-summary__cell">
            <span class="dt-summary__label">Terlambat</span>
            <span class="dt-summary__value <?= $terlambat > 0 ? 'is-danger' : '' ?>"><?= number_format($terlambat, 0, ',', '.') ?></span>
        </div>
    </section>

    <section class="dt-sheet" aria-labelledby="dt-list-title">
        <h2 id="dt-list-title" class="visually-hidden">Daftar tagihan terbuka</h2>

        <div class="dt-toolbar">
            <div class="dt-search">
                <iconify-icon icon="solar:magnifer-linear" aria-hidden="true"></iconify-icon>
                <label class="visually-hidden" for="dt-search">Cari nota atau unit</label>
                <input type="search" id="dt-search" placeholder="Cari nota atau unit…" autocomplete="off">
            </div>

            <form class="dt-filters" id="dt-filter" method="post" action="<?= base_url('export_daftar_hutang') ?>">
                <label class="dt-field dt-field--date">
                    <span>Dari</span>
                    <input type="date" name="tanggal_awal" id="dt-start">
                </label>
                <label class="dt-field dt-field--date">
                    <span>Sampai</span>
                    <input type="date" name="tanggal_akhir" id="dt-end">
                </label>
                <label class="dt-field">
                    <span>Unit</span>
                    <select name="nama_unit" id="dt-unit">
                        <option value="">Semua unit</option>
                        <?php foreach ($unitList as $unit) : ?>
                            <option value="<?= esc($unit) ?>"><?= esc($unit) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="dt-toolbar__actions">
                    <button type="button" class="dt-btn dt-btn--ghost" id="dt-reset">Reset</button>
                    <button type="submit" class="dt-btn dt-btn--ghost">
                        <iconify-icon icon="solar:download-minimalistic-linear" aria-hidden="true"></iconify-icon>
                        Export
                    </button>
                </div>
            </form>
        </div>

        <?php if ($rows === []) : ?>
            <div class="dt-empty">
                <iconify-icon icon="solar:bill-list-linear" aria-hidden="true"></iconify-icon>
                <h2>Tidak ada tagihan terbuka</h2>
                <p>Semua pembelian sudah lunas, atau belum ada pembelian yang tercatat.</p>
            </div>
        <?php else : ?>
            <div class="dt-table-wrap" id="dt-table-wrap">
                <table class="dt-table" id="tagihan-table">
                    <caption>Daftar tagihan hutang supplier yang belum lunas</caption>
                    <thead>
                        <tr>
                            <th scope="col">Tagihan</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Jatuh tempo</th>
                            <th scope="col" class="is-num">Sisa</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="is-num"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody id="dt-rows">
                        <?php foreach ($rows as $row) :
                            $sisa = (float) ($row->sisa ?? 0);
                            $dibayar = (float) ($row->total_bayar ?? $row->bayar ?? 0);
                            $total = (float) ($row->total_transaksi ?? 0);
                            if ($total <= 0) {
                                $total = $sisa + $dibayar;
                            }
                            $due = $dueMeta($row->jatuh_tempo ?? null);
                            $masuk = $tgl($row->tanggal_masuk ?? null);
                            $nota = trim((string) ($row->no_nota_supplier ?? '')) ?: ('#' . (int) $row->idpembelian);
                            $unit = trim((string) ($row->NAMA_UNIT ?? '')) ?: 'Tanpa unit';
                        ?>
                            <tr data-row
                                data-tanggal="<?= esc($masuk['ymd']) ?>"
                                data-jatuh="<?= esc($due['ymd']) ?>"
                                data-unit="<?= esc($unit) ?>"
                                data-search="<?= esc(strtolower($nota . ' ' . $unit)) ?>">
                                <td data-label="Tagihan">
                                    <span class="dt-doc">
                                        <span class="dt-doc__code"><?= esc($nota) ?></span>
                                        <span class="dt-doc__meta">Masuk <?= esc($masuk['label']) ?></span>
                                    </span>
                                </td>
                                <td data-label="Unit">
                                    <span><?= esc($unit) ?></span>
                                </td>
                                <td data-label="Jatuh tempo">
                                    <span class="dt-due <?= esc($due['class']) ?>">
                                        <span class="dt-due__date"><?= esc($due['label']) ?></span>
                                        <span class="dt-due__note"><?= esc($due['note']) ?></span>
                                    </span>
                                </td>
                                <td data-label="Sisa" class="is-num">
                                    <span class="dt-money">
                                        <span class="dt-money__value"><?= $rp($sisa) ?></span>
                                        <span class="dt-money__meta">Dibayar <?= $rp($dibayar) ?> dari <?= $rp($total) ?></span>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <span class="dt-status <?= esc($due['tone']) ?>"><?= esc($due['status']) ?></span>
                                </td>
                                <td data-label="Aksi" class="is-num">
                                    <button type="button" class="dt-btn dt-btn--solid dt-btn--pay"
                                        data-bs-toggle="modal"
                                        data-bs-target="#bayar-tagihan-modal"
                                        data-idpembelian="<?= (int) $row->idpembelian ?>"
                                        data-nota="<?= esc($nota) ?>"
                                        data-unit="<?= esc($unit) ?>"
                                        data-masuk="<?= esc($masuk['label']) ?>"
                                        data-jatuh="<?= esc($due['label']) ?>"
                                        data-sisa="<?= (int) round($sisa) ?>">
                                        <iconify-icon icon="solar:wallet-money-linear" aria-hidden="true"></iconify-icon>
                                        Bayar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="dt-empty" id="dt-empty" hidden>
                <iconify-icon icon="solar:magnifer-zoom-out-linear" aria-hidden="true"></iconify-icon>
                <h2>Tidak ada tagihan yang cocok</h2>
                <p>Ubah kata kunci, rentang tanggal, atau pilihan unit.</p>
            </div>

            <footer class="dt-table__foot">
                <span><strong id="dt-count"><?= number_format($jumlahTagihan, 0, ',', '.') ?></strong> tagihan tampil</span>
                <span>Diurutkan dari jatuh tempo terdekat</span>
            </footer>
        <?php endif; ?>
    </section>
</div>

<div class="modal fade dt-pay-modal" id="bayar-tagihan-modal" tabindex="-1"
    aria-labelledby="bayarTagihanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="dt-pay-form" method="post"
            action="<?= base_url('update_cicilan_hutang') ?>" novalidate>
            <input type="hidden" name="idpembelian" id="dt-pay-id">
            <input type="hidden" name="sisa" id="dt-pay-sisa-input" value="0">

            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="bayarTagihanLabel">Bayar tagihan</h2>
                    <div class="dt-modal-meta" id="dt-pay-nota">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="dt-modal-summary">
                    <span>Sisa tagihan</span>
                    <strong id="dt-pay-sisa-label">Rp 0</strong>
                </div>

                <p class="dt-modal-meta" id="dt-pay-detail">—</p>

                <div class="mb-3">
                    <label class="dt-modal-label" for="dt-pay-tunai">Bayar tunai</label>
                    <input type="text" class="dt-modal-input" id="dt-pay-tunai" name="bayar_tunai"
                        inputmode="numeric" autocomplete="off" value="Rp 0">
                    <div class="dt-modal-hint">
                        <button type="button" class="dt-link-btn" id="dt-pay-full">Isi sisa penuh</button>
                    </div>
                </div>

                <div class="dt-modal-grid">
                    <div>
                        <label class="dt-modal-label" for="dt-pay-bank">Bayar bank</label>
                        <input type="text" class="dt-modal-input" id="dt-pay-bank" name="bayar_bank"
                            inputmode="numeric" autocomplete="off" value="Rp 0"
                            <?= empty($bank) ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label class="dt-modal-label" for="dt-pay-rekening">Rekening bank</label>
                        <?php if (empty($bank)) : ?>
                            <input type="text" class="dt-modal-input" value="Belum ada rekening" disabled>
                            <input type="hidden" name="bank_idbank" id="dt-pay-rekening" value="">
                            <div class="dt-modal-hint">Pembayaran bank nonaktif sampai rekening ditambahkan.</div>
                        <?php else : ?>
                            <select class="dt-modal-input" id="dt-pay-rekening" name="bank_idbank">
                                <option value="">Pilih rekening (opsional)</option>
                                <?php foreach ($bank as $b) : ?>
                                    <option value="<?= esc($b->idbank) ?>">
                                        <?= esc($b->nama_bank) ?><?= !empty($b->norek) ? ' · ' . esc($b->norek) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dt-modal-calc">
                    <div class="dt-modal-calc__row">
                        <span>Sisa setelah bayar</span>
                        <strong id="dt-pay-after">Rp 0</strong>
                    </div>
                    <div class="dt-modal-calc__row">
                        <span>Kembalian</span>
                        <strong id="dt-pay-change">Rp 0</strong>
                    </div>
                </div>

                <p class="dt-modal-error" id="dt-pay-error" role="alert"></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="dt-btn dt-btn--ghost" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="dt-btn dt-btn--solid" id="dt-pay-submit" disabled>
                    Simpan pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var page = document.querySelector('.dt-page');
        if (!page) {
            return;
        }

        var rows = Array.prototype.slice.call(document.querySelectorAll('#dt-rows [data-row]'));
        var tbody = document.getElementById('dt-rows');
        var search = document.getElementById('dt-search');
        var start = document.getElementById('dt-start');
        var end = document.getElementById('dt-end');
        var unit = document.getElementById('dt-unit');
        var reset = document.getElementById('dt-reset');
        var tableWrap = document.getElementById('dt-table-wrap');
        var empty = document.getElementById('dt-empty');
        var count = document.getElementById('dt-count');

        function applyFilter() {
            var q = (search.value || '').trim().toLowerCase();
            var from = start.value || '';
            var to = end.value || '';
            var selectedUnit = unit.value || '';
            var shown = 0;

            rows.forEach(function (row) {
                var matchQ = q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
                var tanggal = row.getAttribute('data-tanggal') || '';
                var matchFrom = from === '' || (tanggal !== '' && tanggal >= from);
                var matchTo = to === '' || (tanggal !== '' && tanggal <= to);
                var matchUnit = selectedUnit === '' || row.getAttribute('data-unit') === selectedUnit;
                var visible = matchQ && matchFrom && matchTo && matchUnit;

                row.hidden = !visible;
                if (visible) {
                    shown++;
                }
            });

            if (count) {
                count.textContent = shown.toLocaleString('id-ID');
            }
            if (tableWrap) {
                tableWrap.hidden = shown === 0;
            }
            if (empty) {
                empty.hidden = shown !== 0;
            }
        }

        // Urutkan dari jatuh tempo terdekat supaya tagihan mendesak tampil dulu.
        rows.sort(function (a, b) {
            return (a.getAttribute('data-jatuh') || '9999-12-31')
                .localeCompare(b.getAttribute('data-jatuh') || '9999-12-31');
        }).forEach(function (row) {
            if (tbody) {
                tbody.appendChild(row);
            }
        });

        [search, start, end, unit].forEach(function (el) {
            if (el) {
                el.addEventListener('input', applyFilter);
                el.addEventListener('change', applyFilter);
            }
        });

        if (reset) {
            reset.addEventListener('click', function () {
                search.value = '';
                start.value = '';
                end.value = '';
                unit.value = '';
                applyFilter();
                search.focus();
            });
        }

        applyFilter();

        // ---------- Modal pembayaran ----------
        var modal = document.getElementById('bayar-tagihan-modal');
        if (!modal) {
            return;
        }

        var form = document.getElementById('dt-pay-form');
        var idInput = document.getElementById('dt-pay-id');
        var sisaInput = document.getElementById('dt-pay-sisa-input');
        var notaLabel = document.getElementById('dt-pay-nota');
        var detailLabel = document.getElementById('dt-pay-detail');
        var sisaLabel = document.getElementById('dt-pay-sisa-label');
        var tunaiInput = document.getElementById('dt-pay-tunai');
        var bankInput = document.getElementById('dt-pay-bank');
        var rekeningSelect = document.getElementById('dt-pay-rekening');
        var afterLabel = document.getElementById('dt-pay-after');
        var changeLabel = document.getElementById('dt-pay-change');
        var errorLabel = document.getElementById('dt-pay-error');
        var submit = document.getElementById('dt-pay-submit');
        var fullButton = document.getElementById('dt-pay-full');
        var sisaHutang = 0;

        function digits(value) {
            return String(value || '').replace(/\D/g, '');
        }

        function toNumber(value) {
            return parseInt(digits(value), 10) || 0;
        }

        function formatRupiah(value) {
            return 'Rp ' + (value || 0).toLocaleString('id-ID');
        }

        function refreshTotal() {
            var tunai = toNumber(tunaiInput.value);
            var bank = toNumber(bankInput.value);
            var total = tunai + bank;
            var setelah = Math.max(0, sisaHutang - total);
            var kembali = Math.max(0, total - sisaHutang);
            var bankDipilih = !rekeningSelect || rekeningSelect.tagName !== 'SELECT' || rekeningSelect.value !== '';
            var bankKurang = bank > 0 && !bankDipilih;

            sisaInput.value = setelah;
            afterLabel.textContent = formatRupiah(setelah);
            changeLabel.textContent = formatRupiah(kembali);
            errorLabel.textContent = bankKurang ? 'Pilih rekening bank untuk pembayaran lewat bank.' : '';

            if (rekeningSelect && rekeningSelect.tagName === 'SELECT') {
                rekeningSelect.classList.toggle('is-invalid', bankKurang);
            }

            submit.disabled = total <= 0 || bankKurang;
        }

        function bindRupiahInput(input) {
            if (!input) {
                return;
            }

            input.addEventListener('input', function () {
                var value = toNumber(input.value);
                input.value = formatRupiah(value);
                refreshTotal();
            });

            input.addEventListener('focus', function () {
                if (toNumber(input.value) === 0) {
                    input.value = '';
                }
            });

            input.addEventListener('blur', function () {
                input.value = formatRupiah(toNumber(input.value));
                refreshTotal();
            });
        }

        bindRupiahInput(tunaiInput);
        bindRupiahInput(bankInput);

        if (rekeningSelect) {
            rekeningSelect.addEventListener('change', refreshTotal);
        }

        if (fullButton) {
            fullButton.addEventListener('click', function () {
                var bank = toNumber(bankInput.value);
                tunaiInput.value = formatRupiah(Math.max(0, sisaHutang - bank));
                refreshTotal();
                tunaiInput.focus();
            });
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) {
                return;
            }

            sisaHutang = parseInt(button.getAttribute('data-sisa'), 10) || 0;

            idInput.value = button.getAttribute('data-idpembelian') || '';
            notaLabel.textContent = button.getAttribute('data-nota') || '—';
            detailLabel.textContent = [
                button.getAttribute('data-unit') || '',
                'Masuk ' + (button.getAttribute('data-masuk') || '—'),
                'Jatuh tempo ' + (button.getAttribute('data-jatuh') || '—')
            ].filter(Boolean).join(' · ');

            sisaLabel.textContent = formatRupiah(sisaHutang);
            tunaiInput.value = 'Rp 0';
            bankInput.value = 'Rp 0';
            if (rekeningSelect && rekeningSelect.tagName === 'SELECT') {
                rekeningSelect.value = '';
                rekeningSelect.classList.remove('is-invalid');
            }
            errorLabel.textContent = '';
            refreshTotal();
        });

        form.addEventListener('submit', function (event) {
            var total = toNumber(tunaiInput.value) + toNumber(bankInput.value);
            var bank = toNumber(bankInput.value);
            var bankKurang = bank > 0 && rekeningSelect && rekeningSelect.tagName === 'SELECT' && rekeningSelect.value === '';

            if (total <= 0 || bankKurang) {
                event.preventDefault();
                if (total <= 0) {
                    errorLabel.textContent = 'Isi nominal tunai atau bank terlebih dahulu.';
                }
            }
        });
    });
</script>
