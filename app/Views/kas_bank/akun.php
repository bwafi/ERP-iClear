<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>
<?= $this->include('kas_bank/_posisi_theme') ?>
<?= $this->include('kas_bank/_akun_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format(abs((float) ($n ?? 0)), 0, ',', '.');
$signed = static fn($n) => ((float) $n < 0 ? '−' : '') . $rp($n);

$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$fmtTgl = static function ($ymd) use ($bulan) {
    $ts = $ymd ? strtotime((string) $ymd) : false;
    return $ts ? date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts) : '—';
};

$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$bankMap = [];
foreach (($bank ?? []) as $b) {
    $bankMap[(string) $b->idbank] = $b;
}
$canInput = $bisa_pilih_unit ?? false;

// Opening KAS per rekening laci pada tanggal cut-off.
$openingKasById = $opening_kas_by_akun ?? [];
$openingKasBelum = (int) ($opening_kas_belum ?? 0);
$cutoffKas     = $opening_kas_cutoff ?? \App\Services\Finance\FinanceScopeService::kasBankCutoffDate();
$cutoffBank    = $bank_cutoff ?? \App\Services\Finance\FinanceScopeService::bankCutoffDate();
$daftarKas     = array_values(array_filter(
    $akun_kas_bank ?? [],
    static fn($a) => (string) $a->tipe === 'KAS'
));

$daftarAkun  = $akun_kas_bank ?? [];
$akunJenis   = $akun_jenis ?? [];
$totalAkun   = count($daftarAkun);
$jumlahAktif = 0;
$jumlahShared = 0;
$jumlahHO     = 0;
$jumlahOver   = 0;
$totalFisik   = 0.0;
$totalKas     = 0.0;
$totalBank    = 0.0;
$totalAlokasiTampil = 0.0;
$totalAlokasiBank   = 0.0;
$totalBelumTampil   = 0.0;

foreach ($daftarAkun as $a) {
    $id     = (int) $a->idakun_kas_bank;
    $fisik  = (float) ($saldo_fisik_akun[$id] ?? 0);
    $jenis  = $akunJenis[$id] ?? \App\Services\Finance\KasBankScopeService::KIND_UNIT;
    $totalFisik += $fisik;

    if (($a->status ?? '') === 'aktif') {
        $jumlahAktif++;
    }
    if ($jenis === \App\Services\Finance\KasBankScopeService::KIND_SHARED) {
        $jumlahShared++;
    } elseif ($jenis === \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO) {
        $jumlahHO++;
    }

    if ((string) $a->tipe === 'KAS') {
        $totalKas += $fisik;
        // KAS selalu milik satu unit dan tidak lewat tabel alokasi. Untuk
        // identitas "fisik = dialokasikan + belum", laci dihitung 100% sudah
        // punya pemilik supaya uang tunai tidak tampil sebagai saldo tanpa hak.
        $totalAlokasiTampil += $fisik;
        continue;
    }

    $totalBank += $fisik;
    $sumAlokasi = array_sum(array_map(fn($al) => (int) $al->nominal, $alokasi[$id] ?? []));
    $totalAlokasiTampil += $sumAlokasi;
    $totalAlokasiBank   += $sumAlokasi;
    $totalBelumTampil   += $fisik - $sumAlokasi;

    // Rekening Finance/HO tidak memakai alokasi, jadi tidak bisa "melebihi".
    if ($jenis !== \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO && $sumAlokasi > $fisik) {
        $jumlahOver++;
    }
}

$scopeLabel = 'Semua cabang';
if ((int) ($unit_terpilih ?? 0) > 0 && isset($unitMap[(int) $unit_terpilih])) {
    $scopeLabel = $unitMap[(int) $unit_terpilih];
}
?>

<div class="kbx kba">

    <!-- Diagnosa konfigurasi rekening yang belum bisa dipakai transaksi -->
    <?= $this->include('kas_bank/_diagnostik') ?>

    <div class="kbx-toolbar kba-toolbar">
        <?php if ($canInput) : ?>
            <form method="get" action="<?= base_url('kas_bank/akun') ?>" class="kba-filter">
                <div class="kbx-field kbx-field--unit">
                    <label for="akun-unit" class="kbx-label">Cabang</label>
                    <select name="unit_id" id="akun-unit" class="form-select kbx-control" onchange="this.form.submit()">
                        <option value="">Semua cabang</option>
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>

        <div class="kbx-field kba-field--search">
            <label for="kb-search" class="kbx-label">Cari rekening</label>
            <input type="search" id="kb-search" class="form-control kbx-control" placeholder="Nama, bank, COA…" aria-label="Cari rekening">
        </div>

        <?php if ($canInput) : ?>
            <div class="kbx-actions">
                <button type="button" class="btn btn-primary kbx-apply" id="btn-new-akun">Tambah rekening</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ringkasan -->
    <section class="kbx-sheet kba-summary" aria-labelledby="kba-ringkas">
        <h2 id="kba-ringkas" class="visually-hidden">Ringkasan rekening</h2>

        <div class="kbx-meta">
            <span>Posisi per <b><?= $fmtTgl(date('Y-m-d')) ?></b> · <b><?= esc($scopeLabel) ?></b></span>
            <span>Saldo awal bank <b><?= $fmtTgl($cutoffBank) ?></b> · opening kas <b><?= $fmtTgl($cutoffKas) ?></b></span>
        </div>

        <div class="kba-summary-body">
            <div class="kba-summary-main">
                <span class="kbx-label">Saldo fisik gabungan</span>
                <div class="kba-total kbx-num <?= $totalFisik < 0 ? 'kbx-neg' : '' ?>"><?= $signed($totalFisik) ?></div>
                <div class="kba-total-note"><?= $totalAkun ?> rekening · <?= $jumlahAktif ?> aktif</div>
            </div>

            <dl class="kba-facts">
                <div>
                    <dt>Kas tunai</dt>
                    <dd class="<?= $totalKas < 0 ? 'kbx-neg' : '' ?>"><?= $signed($totalKas) ?></dd>
                </div>
                <div>
                    <dt>Bank</dt>
                    <dd class="<?= $totalBank < 0 ? 'kbx-neg' : '' ?>"><?= $signed($totalBank) ?></dd>
                </div>
                <div>
                    <dt>Rekening bersama</dt>
                    <dd><?= $jumlahShared ?></dd>
                </div>
                <div>
                    <dt>Finance/HO</dt>
                    <dd><?= $jumlahHO ?></dd>
                </div>
            </dl>
        </div>

        <div class="kba-summary-foot">
            <span>Fisik <b class="kbx-num"><?= $signed($totalFisik) ?></b></span>
            <span>= Dialokasikan <b class="kbx-num"><?= $signed($totalAlokasiTampil) ?></b></span>
            <span>+ Belum dialokasikan <b class="kbx-num <?= $totalBelumTampil < 0 ? 'kbx-neg' : '' ?>"><?= $signed($totalBelumTampil) ?></b></span>
            <?php if ($jumlahOver > 0) : ?>
                <span class="is-over"><?= $jumlahOver ?> rekening melebihi saldo</span>
            <?php endif; ?>
        </div>
    </section>

    <!-- Catat & atur -->
    <section class="kba-record" aria-labelledby="kba-catat">
        <div class="kba-section-head">
            <div>
                <h2 id="kba-catat" class="kba-section-title">Catat &amp; atur</h2>
                <p class="kba-section-sub">
                    Daftarkan rekening, catat saldo awal, bagi hak cabang, lalu tetapkan opening kas laci.
                </p>
            </div>
            <?php if (! $canInput) : ?>
                <span class="kba-readonly">Hanya lihat · hubungi Finance untuk mengubah</span>
            <?php endif; ?>
        </div>

        <div class="kba-tabs" role="tablist" aria-label="Pekerjaan rekening">
            <button type="button" class="kba-tab" role="tab" id="tab-rekening"
                aria-controls="panel-rekening" aria-selected="true" tabindex="0">
                <iconify-icon icon="bi:wallet2" aria-hidden="true"></iconify-icon>
                <span>Rekening</span>
                <span class="kba-tab-n"><?= $totalAkun ?></span>
            </button>
            <button type="button" class="kba-tab" role="tab" id="tab-saldo-awal"
                aria-controls="panel-saldo-awal" aria-selected="false" tabindex="-1">
                <iconify-icon icon="bi:bank" aria-hidden="true"></iconify-icon>
                <span>Saldo awal</span>
                <span class="kba-tab-n"><?= $fmtTgl($cutoffBank) ?></span>
            </button>
            <button type="button" class="kba-tab" role="tab" id="tab-jatah"
                aria-controls="panel-jatah" aria-selected="false" tabindex="-1">
                <iconify-icon icon="bi:pie-chart" aria-hidden="true"></iconify-icon>
                <span>Jatah cabang</span>
                <span class="kba-tab-n"><?= $rp($totalAlokasiBank) ?></span>
            </button>
            <button type="button" class="kba-tab" role="tab" id="tab-opening-kas"
                aria-controls="panel-opening-kas" aria-selected="false" tabindex="-1">
                <iconify-icon icon="bi:cash-stack" aria-hidden="true"></iconify-icon>
                <span>Opening kas</span>
                <span class="kba-tab-n">
                    <?= $openingKasBelum > 0 ? $openingKasBelum . ' laci belum' : count($daftarKas) . ' laci siap' ?>
                </span>
            </button>
        </div>

        <noscript>
            <style>.kba-panel[hidden] { display: block !important; }</style>
        </noscript>

        <!-- Tab 1: rekening -->
        <div class="kba-panel" role="tabpanel" id="panel-rekening" aria-labelledby="tab-rekening" tabindex="0">
            <form method="post" action="<?= base_url('kas_bank/akun/save') ?>" id="form-akun">
                <input type="hidden" name="idakun_kas_bank" id="idakun_kas_bank" value="">

                <div class="kba-form-head">
                    <div class="kba-seg" role="radiogroup" aria-label="Tipe rekening">
                        <label class="kba-seg-opt">
                            <input type="radio" name="tipe" value="KAS" checked>
                            <span>Kas</span>
                        </label>
                        <label class="kba-seg-opt">
                            <input type="radio" name="tipe" value="BANK">
                            <span>Bank</span>
                        </label>
                    </div>
                    <span class="kba-form-mode" id="form-mode">Rekening baru</span>
                </div>

                <div class="kba-form-grid">
                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="nama_akun">Nama rekening <span class="kba-req">*</span></label>
                        <input type="text" name="nama_akun" id="nama_akun" class="form-control kbx-control" placeholder="Mis. Kas Kasir 1, BCA Utama" required>
                    </div>

                    <div class="kba-field">
                        <label class="kbx-label" for="unit_id">Cabang pemilik</label>
                        <select name="unit_id" id="unit_id" class="form-select kbx-control">
                            <option value="">Lintas unit / bersama</option>
                            <?php foreach (($unit ?? []) as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="kba-field">
                        <label class="kbx-label" for="status">Status</label>
                        <select name="status" id="status" class="form-select kbx-control">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>

                    <div class="kba-field kba-field--full" id="bank-select-wrap">
                        <label class="kbx-label" for="bank_idbank">Master bank <span class="kba-req">*</span></label>
                        <select name="bank_idbank" id="bank_idbank" class="form-select kbx-control">
                            <option value="">Pilih master bank</option>
                            <?php foreach (($bank ?? []) as $b) : ?>
                                <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank . ' - ' . $b->atas_nama . ' (' . $b->norek . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kba-hint">Wajib untuk tipe Bank. Satu rekening fisik = satu akun bank.</div>
                    </div>

                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="no_akun_coa">Akun COA</label>
                        <select name="no_akun_coa" id="no_akun_coa" class="form-select kbx-control">
                            <option value="">Pilih COA</option>
                            <?php foreach (($no_akun ?? []) as $na) : ?>
                                <option value="<?= esc($na->no_akun) ?>"><?= esc($na->no_akun . ' - ' . $na->nama_akun) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="kba-field kba-field--full" id="shared-wrap">
                        <div class="form-check form-switch kba-switch">
                            <input class="form-check-input" type="checkbox" name="is_shared" id="is_shared" value="1">
                            <label class="form-check-label" for="is_shared">Dipakai bersama lebih dari satu unit</label>
                        </div>
                    </div>
                </div>

                <div class="kba-panel-foot">
                    <button type="button" class="kba-link-danger d-none" id="btn-reset-form">Batal edit</button>
                    <button type="submit" class="btn btn-primary kbx-apply">Simpan rekening</button>
                </div>
            </form>
        </div>

        <!-- Tab 2: saldo awal -->
        <div class="kba-panel" role="tabpanel" id="panel-saldo-awal" aria-labelledby="tab-saldo-awal" tabindex="0" hidden>
            <p class="kba-panel-note">
                Saldo fisik terverifikasi pada tanggal cut-off bank, bukan hasil penjumlahan transaksi.
            </p>

            <form method="post" action="<?= base_url('kas_bank/saldo-awal/save') ?>">
                <input type="hidden" name="tanggal" value="<?= esc($cutoffBank) ?>">

                <div class="kba-form-grid">
                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="saldo-akun">Rekening <span class="kba-req">*</span></label>
                        <select name="akun_kas_bank_id" id="saldo-akun" class="form-select kbx-control kba-akun-select" required>
                            <option value="">Pilih rekening</option>
                            <?php foreach ($daftarAkun as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>">
                                    <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="kba-field">
                        <span class="kbx-label">Tanggal cut-off</span>
                        <p class="kba-locked">
                            <iconify-icon icon="bi:lock" aria-hidden="true"></iconify-icon>
                            <?= $fmtTgl($cutoffBank) ?>
                        </p>
                    </div>
                    <div class="kba-field">
                        <label class="kbx-label" for="saldo-nominal">Nominal (Rp) <span class="kba-req">*</span></label>
                        <input type="text" name="saldo" id="saldo-nominal" class="form-control kbx-control rupiah kbx-num" placeholder="0" inputmode="numeric" required>
                    </div>

                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="saldo-ket">Keterangan</label>
                        <input type="text" name="keterangan" id="saldo-ket" class="form-control kbx-control" placeholder="Opsional">
                    </div>
                </div>

                <div class="kba-panel-foot">
                    <span class="kba-foot-note">Mengubah statement di tanggal cut-off yang sama mengganti nilai lama.</span>
                    <button type="submit" class="btn btn-primary kbx-apply">Simpan saldo awal</button>
                </div>
            </form>
        </div>

        <!-- Tab 3: jatah cabang -->
        <div class="kba-panel" role="tabpanel" id="panel-jatah" aria-labelledby="tab-jatah" tabindex="0" hidden>
            <p class="kba-panel-note">
                Bagi saldo bank menjadi hak tiap cabang. Total hak idealnya sama dengan saldo fisik.
            </p>

            <form method="post" action="<?= base_url('kas_bank/saldo-alokasi/save') ?>">
                <div class="kba-form-grid">
                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="jatah-akun">Rekening bank <span class="kba-req">*</span></label>
                        <select name="akun_kas_bank_id" id="jatah-akun" class="form-select kbx-control kba-akun-select" required>
                            <option value="">Pilih rekening</option>
                            <?php foreach ($daftarAkun as $a) : ?>
                                <?php
                                // Rekening Finance/HO tidak butuh & tidak boleh
                                // punya alokasi unit — jangan tawarkan di sini.
                                $jenisOpsi = $akunJenis[(int) $a->idakun_kas_bank]
                                    ?? \App\Services\Finance\KasBankScopeService::KIND_UNIT;
                                ?>
                                <?php if ($a->tipe === 'BANK'
                                    && $jenisOpsi !== \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO) : ?>
                                    <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($a->nama_akun) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="kba-field">
                        <label class="kbx-label" for="jatah-unit">Cabang pemilik hak <span class="kba-req">*</span></label>
                        <select name="unit_id" id="jatah-unit" class="form-select kbx-control" required>
                            <option value="">Pilih cabang</option>
                            <?php foreach (($unit ?? []) as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="kba-field">
                        <label class="kbx-label" for="jatah-nominal">Nominal hak (Rp) <span class="kba-req">*</span></label>
                        <input type="text" name="nominal" id="jatah-nominal" class="form-control kbx-control rupiah kbx-num" placeholder="0" inputmode="numeric" required>
                    </div>

                    <div class="kba-field kba-field--full">
                        <label class="kbx-label" for="jatah-ket">Keterangan</label>
                        <input type="text" name="keterangan" id="jatah-ket" class="form-control kbx-control" placeholder="Opsional">
                    </div>
                </div>

                <div class="kba-panel-foot">
                    <span class="kba-foot-note">Menyimpan untuk cabang yang sama mengganti nominal hak, bukan menambah.</span>
                    <button type="submit" class="btn btn-primary kbx-apply">Simpan hak unit</button>
                </div>
            </form>
        </div>

        <!-- Tab 4: opening kas -->
        <div class="kba-panel" role="tabpanel" id="panel-opening-kas" aria-labelledby="tab-opening-kas" tabindex="0" hidden>
            <p class="kba-panel-note">
                Opening ini <strong>bukan</strong> transaksi kas dan tidak ikut masuk cash-in.
                Laci tidak punya statement bank; setelah tersimpan, saldo laci mulai hari setelah
                cut-off memakai baseline ini.
            </p>

            <?php if (! $canInput) : ?>
                <p class="kba-panel-note">Anda tidak punya hak input. Hubungi Finance untuk menetapkan opening KAS.</p>
            <?php endif; ?>

            <?php if ($openingKasBelum > 0) : ?>
                <div class="kb-banner is-warning mb-3">
                    <div class="kb-banner-icon">
                        <iconify-icon icon="bi:exclamation-triangle-fill" aria-hidden="true"></iconify-icon>
                    </div>
                    <div class="kb-banner-content">
                        <strong><?= $openingKasBelum ?> laci belum punya opening KAS</strong>
                        <div class="mt-1">
                            Saldo laci itu belum bisa dipakai sebagai acuan Setor maupun Penarikan,
                            dan belum tampil sebagai saldo awal.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($daftarKas === []) : ?>
                <p class="kba-panel-note">Belum ada rekening laci kas.</p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="kba-open-table">
                        <thead>
                            <tr>
                                <th scope="col">Laci kas</th>
                                <th scope="col" class="num">Opening</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarKas as $a) :
                                $id  = (int) $a->idakun_kas_bank;
                                $row = $openingKasById[$id] ?? null;
                                ?>
                                <tr>
                                    <td data-label="Laci kas">
                                        <?= esc($a->nama_akun) ?>
                                        <div class="kba-hint">
                                            Unit <?= esc($unitMap[(int) ($row->unit_id ?? $a->unit_id)] ?? '—') ?>
                                        </div>
                                    </td>
                                    <td class="num" data-label="Opening">
                                        <?php if ($row !== null) : ?>
                                            <?= esc($rp((int) $row->opening)) ?>
                                        <?php else : ?>
                                            <span class="kba-tag">Belum ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Keterangan">
                                        <?php if ($row !== null && ! empty($row->keterangan)) : ?>
                                            <span class="kbx-acct-meta"><?= esc($row->keterangan) ?></span>
                                        <?php else : ?>
                                            <span class="kbx-acct-meta">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="kba-panel-rule"></div>

                <form method="post" action="<?= base_url('kas_bank/opening-kas/save') ?>">
                    <div class="kba-form-grid">
                        <div class="kba-field kba-field--full">
                            <label class="kbx-label" for="opening-akun">Laci kas <span class="kba-req">*</span></label>
                            <select name="akun_kas_bank_id" id="opening-akun" class="form-select kbx-control kba-akun-select" required>
                                <option value="">Pilih laci kas</option>
                                <?php foreach ($daftarKas as $a) : ?>
                                    <option value="<?= (int) $a->idakun_kas_bank ?>">
                                        <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="kba-field">
                            <label class="kbx-label" for="opening-nominal">Opening (Rp) <span class="kba-req">*</span></label>
                            <input type="text" name="opening" id="opening-nominal" class="form-control kbx-control rupiah kbx-num"
                                placeholder="0" inputmode="numeric" required>
                        </div>
                        <div class="kba-field">
                            <span class="kbx-label">Tanggal cut-off</span>
                            <p class="kba-locked">
                                <iconify-icon icon="bi:lock" aria-hidden="true"></iconify-icon>
                                <?= $fmtTgl($cutoffKas) ?>
                            </p>
                        </div>

                        <div class="kba-field kba-field--full">
                            <label class="kbx-label" for="opening-ket">Keterangan</label>
                            <input type="text" name="keterangan" id="opening-ket" class="form-control kbx-control" placeholder="Opsional">
                        </div>
                    </div>

                    <div class="kba-panel-foot">
                        <span class="kba-foot-note">Mengubah opening mengganti baseline; saldo setelah cut-off dihitung dari baseline baru + mutasi.</span>
                        <button type="submit" class="btn btn-primary kbx-apply">Simpan opening KAS</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Buku rekening -->
    <section class="kbx-ledger kba-ledger" aria-labelledby="kba-buku">
        <div class="kbx-ledger-head">
            <div>
                <h2 id="kba-buku" class="kbx-ledger-title">Buku rekening</h2>
                <div class="kbx-ledger-sub">
                    <?= $totalAkun ?> rekening · <?= $jumlahAktif ?> aktif · <?= esc($scopeLabel) ?>
                </div>
            </div>
            <div class="kbx-ledger-legend" aria-hidden="true">
                <span class="kbx-kind is-kas">Kas</span>
                <span class="kbx-kind is-bank">Bank</span>
            </div>
        </div>

        <table class="kbx-table kba-table" id="tbl-akun">
            <caption class="visually-hidden">Saldo fisik dan porsi yang sudah dialokasikan per rekening</caption>
            <thead>
                <tr>
                    <th scope="col">Rekening</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="num">Saldo fisik</th>
                    <th scope="col" class="num">Dialokasikan</th>
                    <th scope="col" class="kba-col-action"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarAkun)) : ?>
                    <tr>
                        <td colspan="5" class="kbx-empty">
                            <iconify-icon icon="bi:inbox" aria-hidden="true"></iconify-icon>
                            <div>Belum ada rekening. Daftarkan rekening pertama di langkah 1.</div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($daftarAkun as $a) : ?>
                        <?php
                        $idAkun       = (int) $a->idakun_kas_bank;
                        $fisik        = (float) ($saldo_fisik_akun[$idAkun] ?? 0);
                        $alokasiAkun  = $alokasi[$idAkun] ?? [];
                        $totalAlokasi = array_sum(array_map(fn($al) => (int) $al->nominal, $alokasiAkun));
                        $shared       = (int) ($a->is_shared ?? 0) === 1;
                        $jenis        = $akunJenis[$idAkun]
                            ?? \App\Services\Finance\KasBankScopeService::KIND_UNIT;
                        $isHO         = $jenis === \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO;
                        $isShared     = $jenis === \App\Services\Finance\KasBankScopeService::KIND_SHARED;
                        $isBank       = (string) $a->tipe === 'BANK';
                        $entitled     = $akun_scope[$idAkun] ?? [];
                        $entitledNama = implode(', ', array_map(
                            static fn ($uid) => (string) ($unitMap[(int) $uid] ?? ('Unit ' . $uid)),
                            $entitled
                        ));
                        $bankInfo     = $bankMap[(string) $a->bank_idbank] ?? null;
                        $nilai        = $isBank ? (float) $totalAlokasi : $fisik;
                        $pct          = $fisik > 0 ? max(0, min(100, $nilai / $fisik * 100)) : 0;
                        $over         = $isBank && ! $isHO && $nilai > $fisik;
                        $belum        = $fisik - $nilai;

                        $meta = [];
                        if ($isBank) {
                            $meta[] = $bankInfo
                                ? $bankInfo->nama_bank . ' · ' . $bankInfo->norek
                                : ($a->bank_idbank ? 'Bank #' . $a->bank_idbank : 'Master bank belum dipilih');
                        } else {
                            $meta[] = 'Laci kas · ' . ($unitMap[(int) $a->unit_id] ?? 'Lintas unit');
                        }
                        if (! empty($a->no_akun_coa)) {
                            $meta[] = 'COA ' . $a->no_akun_coa;
                        }
                        ?>
                        <tr class="kbx-row-main kb-row-main" data-search="<?= esc(strtolower($a->nama_akun . ' ' . ($unitMap[(int) $a->unit_id] ?? '') . ' ' . ($a->no_akun_coa ?? '') . ' ' . ($bankInfo->nama_bank ?? '') . ' finance ho shared unit')) ?>">
                            <td data-label="Rekening">
                                <div class="kbx-acct-name">
                                    <?= esc($a->nama_akun) ?>
                                    <?php if ($isHO) : ?>
                                        <span class="kba-tag is-ho">Finance/HO</span>
                                    <?php elseif ($isShared) : ?>
                                        <span class="kba-tag">
                                            Shared Antar Unit · <?= $entitledNama !== '' ? esc($entitledNama) : 'tanpa alokasi' ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="kba-tag">Unit</span>
                                    <?php endif; ?>
                                    <?php if (($a->status ?? '') !== 'aktif') : ?>
                                        <span class="kba-status">Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                                <div class="kbx-acct-meta"><?= esc(implode(' · ', $meta)) ?></div>
                                <?php if ($isHO) : ?>
                                    <div class="kbx-acct-meta">
                                        Tanpa alokasi unit · tujuan transfer semua unit · sumber hanya Admin Root / Finance
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="kbx-cell-kind" data-label="Jenis">
                                <span class="kbx-kind <?= $isBank ? 'is-bank' : 'is-kas' ?>"><?= $isBank ? 'Bank' : 'Kas' ?></span>
                            </td>
                            <td class="num kbx-num" data-label="Saldo fisik"><?= $signed($fisik) ?></td>
                            <td class="num kbx-cell-share" data-label="Dialokasikan">
                                <div class="kbx-num <?= $nilai < 0 ? 'kbx-neg' : ($nilai == 0 ? 'is-zero' : '') ?>"><?= $signed($nilai) ?></div>
                                <?php if ($isBank && ! $isHO && $fisik > 0) : ?>
                                    <div class="kbx-share is-bank <?= $over ? 'is-over' : '' ?>" aria-hidden="true">
                                        <span style="width: <?= number_format($pct, 2, '.', '') ?>%"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="kbx-share-note">
                                    <?php if ($isHO) : ?>
                                        Tanpa alokasi unit
                                    <?php elseif (! $isBank) : ?>
                                        Milik unit
                                    <?php elseif ($nilai < 0) : ?>
                                        <span class="kbx-flag">Saldo minus</span>
                                    <?php elseif ($over) : ?>
                                        <span class="kbx-flag">Melebihi fisik</span>
                                    <?php elseif ($nilai == 0) : ?>
                                        Belum ada hak unit
                                    <?php elseif ($belum == 0) : ?>
                                        Penuh
                                    <?php else : ?>
                                        Belum dibagi <?= $signed($belum) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="kba-cell-action">
                                <?php if ($canInput) : ?>
                                    <button type="button" class="kba-row-act"
                                        data-goto="<?= $isBank ? 'saldo-awal' : 'opening-kas' ?>"
                                        data-akun="<?= $idAkun ?>"
                                        aria-label="<?= $isBank ? 'Isi saldo awal' : 'Isi opening kas' ?> <?= esc($a->nama_akun) ?>">
                                        <iconify-icon icon="<?= $isBank ? 'bi:bank' : 'bi:cash-stack' ?>" aria-hidden="true"></iconify-icon>
                                        <?= $isBank ? 'Isi saldo' : 'Isi opening' ?>
                                    </button>
                                    <button type="button" class="kba-icon-btn edit-akun" title="Edit rekening" aria-label="Edit <?= esc($a->nama_akun) ?>"
                                        data-id="<?= $idAkun ?>"
                                        data-unit="<?= (int) $a->unit_id ?>"
                                        data-tipe="<?= esc($a->tipe) ?>"
                                        data-nama="<?= esc($a->nama_akun) ?>"
                                        data-bank="<?= esc($a->bank_idbank ?? '') ?>"
                                        data-coa="<?= esc($a->no_akun_coa ?? '') ?>"
                                        data-status="<?= esc($a->status) ?>"
                                        data-shared="<?= $shared ? '1' : '0' ?>">
                                        <iconify-icon icon="bi:pencil" aria-hidden="true"></iconify-icon>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($isShared && ! empty($alokasiAkun)) : ?>
                            <tr class="kba-row-alloc">
                                <td colspan="5">
                                    <div class="kba-alloc">
                                        <span class="kba-alloc-label">Hak unit</span>
                                        <?php foreach ($alokasiAkun as $al) : ?>
                                            <span class="kba-chip">
                                                <?= esc($unitMap[(int) $al->unit_id] ?? 'Unit ' . $al->unit_id) ?>
                                                <b><?= $rp($al->nominal) ?></b>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <tr id="kb-no-result" class="d-none">
                        <td colspan="5" class="kbx-empty">Tidak ada rekening yang cocok dengan pencarian.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (! empty($daftarAkun)) : ?>
                <tfoot>
                    <tr>
                        <td colspan="2">Total <?= $totalAkun ?> rekening</td>
                        <td class="num kbx-num" data-label="Saldo fisik"><?= $signed($totalFisik) ?></td>
                        <td class="num kbx-num" data-label="Dialokasikan">
                            <?= $signed($totalAlokasiTampil) ?>
                            <div class="kbx-share-note">
                                <?php if ($totalBelumTampil < 0) : ?>
                                    Melebihi <?= $signed($totalBelumTampil) ?>
                                <?php elseif ($totalBelumTampil > 0) : ?>
                                    Belum dibagi <?= $signed($totalBelumTampil) ?>
                                <?php else : ?>
                                    Penuh
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="kba-cell-action"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </section>
</div>

<script>
    (function() {
        var canEdit = <?= $canInput ? 'true' : 'false' ?>;
        var $ = function(id) {
            return document.getElementById(id);
        };

        // ===== Tab =====
        var tabs = Array.prototype.slice.call(document.querySelectorAll('.kba-tab'));

        function panelOf(tab) {
            return document.getElementById(tab.getAttribute('aria-controls'));
        }

        function tabFor(nama) {
            return tabs.filter(function(t) {
                return t.getAttribute('aria-controls') === 'panel-' + nama;
            })[0] || null;
        }

        function activate(tab, pindahFokus) {
            if (!tab) {
                return;
            }
            tabs.forEach(function(t) {
                var aktif = t === tab;
                t.setAttribute('aria-selected', aktif ? 'true' : 'false');
                t.tabIndex = aktif ? 0 : -1;
                var panel = panelOf(t);
                if (panel) {
                    panel.hidden = !aktif;
                }
            });
            if (pindahFokus) {
                tab.focus();
            }
            if (window.history && history.replaceState) {
                history.replaceState(null, '', '#' + tab.getAttribute('aria-controls').replace('panel-', ''));
            }
        }

        tabs.forEach(function(tab, i) {
            tab.addEventListener('click', function() {
                activate(tab, false);
            });
            tab.addEventListener('keydown', function(e) {
                var next = null;
                if (e.key === 'ArrowRight') {
                    next = tabs[(i + 1) % tabs.length];
                } else if (e.key === 'ArrowLeft') {
                    next = tabs[(i - 1 + tabs.length) % tabs.length];
                } else if (e.key === 'Home') {
                    next = tabs[0];
                } else if (e.key === 'End') {
                    next = tabs[tabs.length - 1];
                }
                if (next) {
                    e.preventDefault();
                    activate(next, true);
                }
            });
        });

        // ===== Rekening terpilih dipakai bersama antar tab =====
        var akunSelects = Array.prototype.slice.call(
            document.querySelectorAll('.kba-record select[name="akun_kas_bank_id"]')
        );

        function pilihAkun(id) {
            akunSelects.forEach(function(sel) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === id) {
                        sel.value = id;
                        return;
                    }
                }
            });
        }

        akunSelects.forEach(function(sel) {
            sel.addEventListener('change', function() {
                pilihAkun(sel.value);
            });
        });

        // ===== Aksi per baris: buka tab yang tepat dengan rekening terpilih =====
        document.querySelectorAll('.kba-row-act').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tab = tabFor(btn.dataset.goto);
                if (!tab) {
                    return;
                }
                activate(tab, false);
                pilihAkun(btn.dataset.akun);
                var panel = panelOf(tab);
                var nominal = panel
                    ? panel.querySelector('input[name="saldo"], input[name="nominal"], input[name="opening"]')
                    : null;
                if (nominal) {
                    nominal.focus();
                }
            });
        });

        // ===== Form rekening =====
        function tipeValue() {
            var radio = document.querySelector('#form-akun input[name="tipe"]:checked');
            return radio ? radio.value : 'KAS';
        }

        function toggleByTipe() {
            var isBank = tipeValue() === 'BANK';
            $('bank-select-wrap').style.display = isBank ? '' : 'none';
            $('shared-wrap').style.display = isBank ? '' : 'none';
        }

        function exitEditMode() {
            $('form-akun').reset();
            $('idakun_kas_bank').value = '';
            $('form-mode').textContent = 'Rekening baru';
            $('btn-reset-form').classList.add('d-none');
            toggleByTipe();
        }

        document.querySelectorAll('#form-akun input[name="tipe"]').forEach(function(radio) {
            radio.addEventListener('change', toggleByTipe);
        });

        document.querySelectorAll('.edit-akun').forEach(function(btn) {
            btn.addEventListener('click', function() {
                activate(tabFor('rekening'), false);

                $('idakun_kas_bank').value = btn.dataset.id;
                $('unit_id').value = btn.dataset.unit;
                $('nama_akun').value = btn.dataset.nama;
                $('bank_idbank').value = btn.dataset.bank;
                $('no_akun_coa').value = btn.dataset.coa;
                $('status').value = btn.dataset.status;
                $('is_shared').checked = btn.dataset.shared === '1';

                var radio = document.querySelector('#form-akun input[name="tipe"][value="' + btn.dataset.tipe + '"]');
                if (radio) {
                    radio.checked = true;
                }

                $('form-mode').textContent = 'Edit rekening';
                $('btn-reset-form').classList.remove('d-none');
                toggleByTipe();
                $('nama_akun').focus();
            });
        });

        $('btn-reset-form').addEventListener('click', exitEditMode);

        var btnNew = $('btn-new-akun');
        if (btnNew) {
            btnNew.addEventListener('click', function() {
                activate(tabFor('rekening'), false);
                exitEditMode();
                $('nama_akun').focus();
            });
        }

        if (!canEdit) {
            $('bank-select-wrap').style.display = 'none';
            $('shared-wrap').style.display = 'none';
            $('is_shared').disabled = true;
        }

        // Format rupiah saat mengetik
        document.querySelectorAll('input.rupiah').forEach(function(input) {
            input.addEventListener('input', function() {
                var val = this.value.replace(/[^,\d]/g, '');
                var split = val.split(',');
                var sisa = split[0].length % 3;
                var rupiah = split[0].substr(0, sisa);
                var ribuan = split[0].substr(sisa).match(/\d{3}/g);
                if (ribuan) {
                    rupiah += (sisa ? '.' : '') + ribuan.join('.');
                }
                this.value = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            });
        });

        // Pencarian tabel
        var search = $('kb-search');
        if (search) {
            search.addEventListener('input', function() {
                var q = this.value.trim().toLowerCase();
                var shown = 0;
                document.querySelectorAll('#tbl-akun tr.kb-row-main').forEach(function(row) {
                    var match = !q || row.dataset.search.indexOf(q) !== -1;
                    row.classList.toggle('d-none', !match);
                    var next = row.nextElementSibling;
                    if (next && next.classList.contains('kba-row-alloc')) {
                        next.classList.toggle('d-none', !match);
                    }
                    if (match) shown++;
                });
                var empty = $('kb-no-result');
                if (empty) empty.classList.toggle('d-none', shown > 0);
            });
        }

        // Tab awal: dari hash URL (mis. setelah simpan opening), kalau tidak ada pakai tab pertama.
        activate(tabFor((window.location.hash || '').replace('#', '')) || tabs[0], false);
        toggleByTipe();
    })();
</script>
