<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>
<?= $this->include('kas_bank/_posisi_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format(abs((float) ($n ?? 0)), 0, ',', '.');
$signed = static fn($n) => ((float) $n < 0 ? '−' : '') . $rp($n);
$flow = static fn($n) => ((float) $n > 0 ? '+' : ((float) $n < 0 ? '−' : '')) . $rp($n);

$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$fmtTgl = static function ($ymd) use ($bulan) {
    $ts = $ymd ? strtotime((string) $ymd) : false;
    return $ts ? date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts) : '—';
};

$f = $filter ?? [];
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a->nama_akun;
}

$unitDipilih = (int) ($unit_terpilih ?? 0) > 0;
$unitNama = 'Semua cabang';
if ($unitDipilih && isset($unitMap[(int) $unit_terpilih])) {
    $unitNama = $unitMap[(int) $unit_terpilih];
}

$cutoff = \App\Services\Finance\FinanceScopeService::kasBankPeriodeMulaiDate();
$dari = $f['tanggal_awal'] ?? '';
$sampai = $f['tanggal_akhir'] ?? '';
$dariEfektif = $dari !== '' ? max($dari, $cutoff) : $cutoff;
$periodeFlow = $fmtTgl($dariEfektif) . ' – ' . $fmtTgl($sampai !== '' ? $sampai : date('Y-m-d'));

$totalFisik = (float) ($total_fisik_semua ?? 0);
$totalHak = array_sum(array_map('floatval', $saldo_unit_per_akun ?? []));
$heroTotal = $unitDipilih ? $totalHak : $totalFisik;

// KAS selalu milik satu unit dan tidak lewat tabel alokasi. Untuk identitas
// "fisik = dialokasikan + belum", porsi KAS dihitung 100% dialokasikan supaya
// uang laci tidak tampil sebagai saldo yang belum punya pemilik.
$totalAlokasiTampil = 0.0;
$totalBelumTampil = 0.0;
foreach (($akun_kas_bank ?? []) as $a) {
    $aid = (int) $a->idakun_kas_bank;
    if (($a->tipe ?? '') === 'KAS') {
        $totalAlokasiTampil += (float) ($saldo_fisik_per_akun[$aid] ?? 0);
    } else {
        $totalAlokasiTampil += (float) ($alokasi_total[$aid] ?? 0);
        $totalBelumTampil += (float) ($belum_dialokasikan[$aid] ?? 0);
    }
}

// Komposisi mengikuti angka yang sedang ditampilkan: fisik saat konsolidasi,
// hak unit saat satu cabang dipilih. Nilai negatif tidak bisa digambar.
$composeKas = max(0, (float) ($unitDipilih ? $total_kas : $total_fisik_kas));
$composeBank = max(0, (float) ($unitDipilih ? $total_bank : $total_fisik_bank));
$composeTotal = $composeKas + $composeBank;
$kasPct = $composeTotal > 0 ? round($composeKas / $composeTotal * 100) : 0;
$bankPct = $composeTotal > 0 ? 100 - $kasPct : 0;
$kasWidth = $composeTotal > 0 ? $composeKas / $composeTotal * 100 : 0;
$bankWidth = $composeTotal > 0 ? $composeBank / $composeTotal * 100 : 0;

$pemasukan = (int) ($ringkasan['PEMASUKAN'] ?? 0);
$pengeluaran = (int) ($ringkasan['PENGELUARAN'] ?? 0);
$transfer = (int) ($ringkasan['TRANSFER_INTERNAL'] ?? 0);
$antar = (int) ($ringkasan['PEMBAYARAN_ANTAR_UNIT'] ?? 0);
$netFlow = (int) ($net_cash_flow ?? 0);
$hasWarning = !empty($warning_alokasi);
$lastWarning = $hasWarning ? end($warning_alokasi) : null;
$jumlahRekening = count($akun_kas_bank ?? []);
?>

<div class="kbx">
    <form method="get" action="<?= base_url('kas_bank') ?>" class="kbx-toolbar">
        <?php if (($bisa_pilih_unit ?? false)) : ?>
            <div class="kbx-field kbx-field--unit">
                <label for="f-unit" class="kbx-label">Cabang</label>
                <select name="unit_id" id="f-unit" class="form-select kbx-control">
                    <option value="">Semua cabang (konsolidasi)</option>
                    <?php foreach (($unit ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="kbx-field kbx-field--date">
            <label for="f-dari" class="kbx-label">Dari</label>
            <input type="date" id="f-dari" name="tanggal_awal" class="form-control kbx-control" value="<?= esc($dari) ?>">
        </div>
        <div class="kbx-field kbx-field--date">
            <label for="f-sampai" class="kbx-label">Sampai</label>
            <input type="date" id="f-sampai" name="tanggal_akhir" class="form-control kbx-control" value="<?= esc($sampai) ?>">
        </div>
        <div class="kbx-field kbx-field--akun">
            <label for="f-akun" class="kbx-label">Rekening</label>
            <select name="akun_id" id="f-akun" class="form-select kbx-control">
                <option value="">Semua rekening</option>
                <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                    <option value="<?= (int) $a->idakun_kas_bank ?>" <?= (int) ($f['akun_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                        <?= esc($a->nama_akun) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="kbx-actions">
            <button class="btn btn-primary kbx-apply" type="submit">
                <iconify-icon icon="bi:funnel-fill" class="kb-ico"></iconify-icon>
                Terapkan
            </button>
            <a href="<?= base_url('kas_bank') ?>" class="kbx-reset">Reset</a>
        </div>
    </form>

    <section class="kbx-sheet" aria-labelledby="kbx-posisi">
        <h2 id="kbx-posisi" class="visually-hidden">Posisi kas dan bank</h2>

        <div class="kbx-meta">
            <span>Posisi per <b><?= $fmtTgl(date('Y-m-d')) ?></b> · <b><?= esc($unitNama) ?></b></span>
            <span>Cut-off arus kas <b><?= $fmtTgl($cutoff) ?></b></span>
        </div>

        <div class="kbx-hero">
            <div class="kbx-hero-main">
                <span class="kbx-label"><?= $unitDipilih ? 'Hak unit ini' : 'Total posisi fisik' ?></span>
                <div class="kbx-total kbx-num <?= $heroTotal < 0 ? 'kbx-neg' : '' ?>"><?= $signed($heroTotal) ?></div>
                <div class="kbx-total-note">
                    <?php if ($unitDipilih) : ?>
                        Fisik semua unit: <?= $rp($totalFisik) ?>
                    <?php else : ?>
                        Uang nyata di <?= $jumlahRekening ?> rekening aktif
                    <?php endif; ?>
                </div>

                <div class="kbx-split">
                    <div>
                        <span class="kbx-label">Kas tunai</span>
                        <div class="kbx-split-value kbx-num <?= (float) $total_kas < 0 ? 'kbx-neg' : '' ?>"><?= $signed($total_kas ?? 0) ?></div>
                        <div class="kbx-split-note"><?= $unitDipilih ? 'Hak unit · fisik ' . $rp($total_fisik_kas) : 'Laci & brankas' ?></div>
                    </div>
                    <div>
                        <span class="kbx-label">Saldo bank</span>
                        <div class="kbx-split-value kbx-num <?= (float) $total_bank < 0 ? 'kbx-neg' : '' ?>"><?= $signed($total_bank ?? 0) ?></div>
                        <div class="kbx-split-note"><?= $unitDipilih ? 'Hak unit · fisik ' . $rp($total_fisik_bank) : 'Buku rekening bank' ?></div>
                    </div>
                </div>
            </div>

            <div class="kbx-flow">
                <div class="kbx-flow-title">
                    <span>Arus kas</span>
                    <span><?= $periodeFlow ?></span>
                </div>
                <div class="kbx-flow-row">
                    <span class="kbx-flow-label">Pemasukan</span>
                    <span class="kbx-flow-value kbx-num <?= $pemasukan !== 0 ? 'kbx-pos' : 'is-zero' ?>"><?= $flow($pemasukan) ?></span>
                </div>
                <div class="kbx-flow-row">
                    <span class="kbx-flow-label">Pengeluaran</span>
                    <span class="kbx-flow-value kbx-num <?= $pengeluaran !== 0 ? 'kbx-neg' : 'is-zero' ?>"><?= $flow(-$pengeluaran) ?></span>
                </div>
                <div class="kbx-flow-row">
                    <span class="kbx-flow-label">Transfer internal</span>
                    <span class="kbx-flow-value kbx-num <?= $transfer !== 0 ? '' : 'is-zero' ?>"><?= $flow($transfer) ?></span>
                </div>
                <div class="kbx-flow-row">
                    <span class="kbx-flow-label">Antar cabang</span>
                    <span class="kbx-flow-value kbx-num <?= $antar !== 0 ? '' : 'is-zero' ?>"><?= $flow($antar) ?></span>
                </div>
                <div class="kbx-flow-row">
                    <span class="kbx-flow-label">Arus kas operasional</span>
                    <span class="kbx-flow-value kbx-num <?= $netFlow < 0 ? 'kbx-neg' : ($netFlow > 0 ? 'kbx-pos' : 'is-zero') ?>"><?= $flow($netFlow) ?></span>
                </div>
                <p class="kbx-flow-note">Belum termasuk transfer internal &amp; antar cabang.</p>
            </div>
        </div>

        <div class="kbx-compose">
            <div class="kbx-bar" role="img" aria-label="Komposisi kas <?= $kasPct ?> persen, bank <?= $bankPct ?> persen">
                <span class="is-kas" style="width: <?= number_format($kasWidth, 2, '.', '') ?>%"></span>
                <span class="is-bank" style="width: <?= number_format($bankWidth, 2, '.', '') ?>%"></span>
            </div>
            <div class="kbx-legend">
                <span class="kbx-legend-item is-kas">Kas <?= $kasPct ?>%</span>
                <span class="kbx-legend-item is-bank">Bank <?= $bankPct ?>%</span>
            </div>
            <p class="kbx-note">
                Saldo fisik adalah uang nyata di rekening bank atau laci kas. Hak unit adalah porsi yang boleh
                dipakai cabang ini; selisihnya adalah saldo yang belum dialokasikan ke unit mana pun.
            </p>
        </div>

        <div class="kbx-identity">
            <?php if ($unitDipilih) : ?>
                <span>Fisik semua unit <b class="kbx-num"><?= $signed($totalFisik) ?></b></span>
                <span>Hak unit ini <b class="kbx-num"><?= $signed($totalHak) ?></b></span>
            <?php else : ?>
                <span>Fisik <b class="kbx-num"><?= $signed($totalFisik) ?></b></span>
                <span>= Dialokasikan <b class="kbx-num"><?= $signed($totalAlokasiTampil) ?></b></span>
                <span>+ Belum dialokasikan <b class="kbx-num <?= $totalBelumTampil < 0 ? 'kbx-neg' : '' ?>"><?= $signed($totalBelumTampil) ?></b></span>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($hasWarning) : ?>
        <div class="kbx-alert" role="alert">
            <iconify-icon icon="bi:exclamation-triangle-fill" aria-hidden="true"></iconify-icon>
            <div>
                <strong>Alokasi melebihi saldo fisik.</strong>
                Rekening:
                <?php foreach (($warning_alokasi ?? []) as $ka) : ?>
                    <span class="fw-semibold"><?= esc($akunMap[$ka] ?? '#' . $ka) ?></span><?= $ka !== $lastWarning ? ', ' : '.' ?>
                <?php endforeach; ?>
                <a href="<?= base_url('kas_bank/akun') ?>">Perbaiki di Rekening &amp; Saldo</a>.
            </div>
        </div>
    <?php endif; ?>

    <?= $this->include('kas_bank/_diagnostik') ?>

    <section class="kbx-ledger" aria-labelledby="kbx-buku">
        <div class="kbx-ledger-head">
            <div>
                <h2 id="kbx-buku" class="kbx-ledger-title">Buku rekening</h2>
                <div class="kbx-ledger-sub">
                    <?= $jumlahRekening ?> rekening aktif · <?= $unitDipilih ? 'hak unit di samping saldo fisik' : 'saldo fisik dan porsi yang sudah dialokasikan' ?>
                </div>
            </div>
            <div class="kbx-ledger-legend" aria-hidden="true">
                <span class="kbx-kind is-kas">Kas</span>
                <span class="kbx-kind is-bank">Bank</span>
            </div>
        </div>

        <table class="kbx-table">
            <caption class="visually-hidden">Saldo fisik dan <?= $unitDipilih ? 'hak unit' : 'alokasi unit' ?> per rekening</caption>
            <thead>
                <tr>
                    <th scope="col">Rekening</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="num">Saldo fisik</th>
                    <th scope="col" class="num"><?= $unitDipilih ? 'Hak unit ini' : 'Dialokasikan' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($jumlahRekening === 0) : ?>
                    <tr>
                        <td colspan="4" class="kbx-empty">
                            <iconify-icon icon="bi:inbox" aria-hidden="true"></iconify-icon>
                            <div>Belum ada rekening aktif pada cakupan ini.</div>
                            <a href="<?= base_url('kas_bank/akun') ?>" class="fw-semibold">Tambah atau aktifkan rekening</a>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                        <?php
                        $akunId = (int) $a->idakun_kas_bank;
                        $shared = (int) ($a->is_shared ?? 0) === 1;
                        $entitled = $akun_scope[$akunId] ?? [];
                        $entitledNama = implode(', ', array_map(
                            static fn($uid) => (string) ($unitMap[(int) $uid] ?? ('Unit ' . $uid)),
                            $entitled
                        ));
                        $isBank = ($a->tipe ?? '') === 'BANK';
                        $fisik = (float) ($saldo_fisik_per_akun[$akunId] ?? 0);
                        $alokasi = (float) ($alokasi_total[$akunId] ?? 0);
                        $belum = (float) ($belum_dialokasikan[$akunId] ?? 0);
                        $hak = (float) ($saldo_unit_per_akun[$akunId] ?? 0);
                        $nilai = $unitDipilih ? $hak : ($isBank ? $alokasi : $fisik);
                        $pct = $fisik > 0 ? max(0, min(100, $nilai / $fisik * 100)) : 0;
                        $pctLabel = (int) round($pct);
                        $over = $nilai > $fisik;
                        $meta = $shared
                            ? 'Shared · ' . ($entitledNama !== '' ? $entitledNama : 'belum dialokasikan')
                            : ($unitMap[(int) $a->unit_id] ?? 'Tanpa unit');
                        ?>
                        <tr>
                            <td data-label="Rekening">
                                <div class="kbx-acct-name"><?= esc($a->nama_akun) ?></div>
                                <div class="kbx-acct-meta"><?= esc($meta) ?></div>
                            </td>
                            <td class="kbx-cell-kind" data-label="Jenis">
                                <span class="kbx-kind <?= $isBank ? 'is-bank' : 'is-kas' ?>"><?= $isBank ? 'Bank' : 'Kas' ?></span>
                            </td>
                            <td class="num kbx-num" data-label="Saldo fisik"><?= $signed($fisik) ?></td>
                            <td class="num kbx-cell-share" data-label="<?= $unitDipilih ? 'Hak unit ini' : 'Dialokasikan' ?>">
                                <div class="kbx-num <?= $nilai < 0 ? 'kbx-neg' : ($nilai == 0 ? 'is-zero' : '') ?>"><?= $signed($nilai) ?></div>
                                <?php if ($fisik > 0) : ?>
                                    <div class="kbx-share <?= $isBank ? 'is-bank' : '' ?> <?= $over ? 'is-over' : '' ?>" aria-hidden="true">
                                        <span style="width: <?= number_format($pct, 2, '.', '') ?>%"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="kbx-share-note">
                                    <?php if ($nilai < 0) : ?>
                                        <span class="kbx-flag">Saldo minus</span>
                                    <?php elseif ($over) : ?>
                                        <span class="kbx-flag">Melebihi fisik</span>
                                    <?php elseif ($unitDipilih) : ?>
                                        <?= $pctLabel ?>% dari fisik
                                    <?php elseif (! $isBank) : ?>
                                        Milik unit
                                    <?php elseif ($belum == 0) : ?>
                                        Penuh
                                    <?php else : ?>
                                        Belum <?= $signed($belum) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($jumlahRekening > 0) : ?>
                <tfoot>
                    <tr>
                        <td colspan="2">Total <?= $jumlahRekening ?> rekening</td>
                        <td class="num kbx-num" data-label="Saldo fisik"><?= $signed($totalFisik) ?></td>
                        <td class="num kbx-num" data-label="<?= $unitDipilih ? 'Hak unit ini' : 'Dialokasikan' ?>">
                            <?= $signed($unitDipilih ? $totalHak : $totalAlokasiTampil) ?>
                            <div class="kbx-share-note">
                                <?php if ($unitDipilih) : ?>
                                    <?= $totalFisik > 0 ? (int) round($totalHak / $totalFisik * 100) : 0 ?>% dari fisik
                                <?php else : ?>
                                    Belum <?= $signed($totalBelumTampil) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </section>
</div>
