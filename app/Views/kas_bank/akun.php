<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
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
$openingKas    = $opening_kas ?? [];
$openingKasById = $opening_kas_by_akun ?? [];
$openingKasBelum = (int) ($opening_kas_belum ?? 0);
$cutoffOpening = $opening_kas_cutoff ?? \App\Services\Finance\FinanceScopeService::kasBankCutoffDate();
$daftarKas     = array_values(array_filter(
    $akun_kas_bank ?? [],
    static fn($a) => (string) $a->tipe === 'KAS'
));

// Ringkasan untuk strip statistik
$daftarAkun   = $akun_kas_bank ?? [];
$akunJenis    = $akun_jenis ?? [];
$totalAkun    = count($daftarAkun);
$totalFisik   = 0;
$jumlahShared = 0;
$jumlahHO     = 0;
$jumlahOver   = 0;
foreach ($daftarAkun as $a) {
    $id     = (int) $a->idakun_kas_bank;
    $fisik  = (float) ($saldo_fisik_akun[$id] ?? 0);
    $jenis  = $akunJenis[$id] ?? (\App\Services\Finance\KasBankScopeService::KIND_UNIT);
    $totalFisik += $fisik;
    if ($jenis === \App\Services\Finance\KasBankScopeService::KIND_SHARED) {
        $jumlahShared++;
    } elseif ($jenis === \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO) {
        $jumlahHO++;
    }
    $sumAlokasi = array_sum(array_map(fn($al) => (int) $al->nominal, $alokasi[$id] ?? []));
    // Rekening Finance/HO tidak memakai alokasi, jadi tidak bisa "melebihi".
    if ($a->tipe === 'BANK'
        && $jenis !== \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO
        && $sumAlokasi > $fisik) {
        $jumlahOver++;
    }
}
?>

<div class="kb">

    <!-- Diagnosa konfigurasi rekening yang belum bisa dipakai transaksi -->
    <?= $this->include('kas_bank/_diagnostik') ?>

    <!-- Ringkasan -->
    <div class="kb-stats">
        <div class="kb-stat">
            <span class="kb-stat-label">Total rekening</span>
            <span class="kb-stat-value"><?= $totalAkun ?></span>
        </div>
        <div class="kb-stat">
            <span class="kb-stat-label">Saldo fisik gabungan</span>
            <span class="kb-stat-value kb-mono"><?= $rp($totalFisik) ?></span>
        </div>
        <div class="kb-stat">
            <span class="kb-stat-label">Rekening bersama</span>
            <span class="kb-stat-value"><?= $jumlahShared ?></span>
        </div>
        <div class="kb-stat">
            <span class="kb-stat-label">Rekening Finance/HO</span>
            <span class="kb-stat-value"><?= $jumlahHO ?></span>
        </div>
        <div class="kb-stat <?= $jumlahOver > 0 ? 'is-warn' : '' ?>">
            <span class="kb-stat-label">Alokasi melebihi saldo</span>
            <span class="kb-stat-value"><?= $jumlahOver ?></span>
        </div>
    </div>

    <!-- Filter cabang -->
    <?php if ($canInput) : ?>
        <form method="get" action="<?= base_url('kas_bank/akun') ?>" class="kb-filter">
            <label for="akun-unit" class="kb-label mb-0">Cabang</label>
            <select name="unit_id" id="akun-unit" class="form-select kb-input kb-filter-select" onchange="this.form.submit()">
                <option value="">Semua cabang</option>
                <?php foreach (($unit ?? []) as $u) : ?>
                    <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>>
                        <?= esc($u->NAMA_UNIT) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="kb-hint">
                <iconify-icon icon="bi:info-circle" class="kb-ico"></iconify-icon>
                Rekening kas (tunai) khusus satu cabang. Rekening bank bisa dipakai bersama.
            </span>
        </form>
    <?php endif; ?>

    <div class="kb-layout">

        <!-- Panel form -->
        <aside class="kb-side">
            <div class="kb-card">
                <ul class="kb-tabs nav" id="formStepsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="kb-tab active" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1-pane" type="button" role="tab">
                            <span class="kb-step">1</span>Rekening
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="kb-tab" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2-pane" type="button" role="tab">
                            <span class="kb-step">2</span>Saldo awal
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="kb-tab" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3-pane" type="button" role="tab">
                            <span class="kb-step">3</span>Hak unit
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="kb-tab" id="step4-tab" data-bs-toggle="tab" data-bs-target="#step4-pane" type="button" role="tab">
                            <span class="kb-step">4</span>Opening KAS
                        </button>
                    </li>
                </ul>

                <div class="kb-card-body tab-content" id="formStepsTabContent">

                    <!-- Langkah 1 -->
                    <div class="tab-pane fade show active" id="step1-pane" role="tabpanel">
                        <div class="kb-pane-head">
                            <div>
                                <div class="kb-pane-title" id="form-title">Tambah rekening</div>
                                <div class="kb-hint">Daftarkan kas atau bank sebelum mencatat saldo.</div>
                            </div>
                            <button type="button" class="kb-link-danger d-none" id="btn-reset-form">Batal edit</button>
                        </div>

                        <form method="post" action="<?= base_url('kas_bank/akun/save') ?>" id="form-akun">
                            <input type="hidden" name="idakun_kas_bank" id="idakun_kas_bank" value="">

                            <div class="kb-row">
                                <div class="kb-field">
                                    <label class="kb-label" for="tipe">Tipe <span class="kb-req">*</span></label>
                                    <select name="tipe" id="tipe" class="form-select kb-input" required>
                                        <option value="KAS">Kas (tunai)</option>
                                        <option value="BANK">Bank</option>
                                    </select>
                                </div>
                                <div class="kb-field">
                                    <label class="kb-label" for="status">Status</label>
                                    <select name="status" id="status" class="form-select kb-input">
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label" for="nama_akun">Nama rekening <span class="kb-req">*</span></label>
                                <input type="text" name="nama_akun" id="nama_akun" class="form-control kb-input" placeholder="Mis. Kas Kasir 1, BCA Utama" required>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label" for="unit_id">Cabang pemilik</label>
                                <select name="unit_id" id="unit_id" class="form-select kb-input">
                                    <option value="">Lintas unit / bersama</option>
                                    <?php foreach (($unit ?? []) as $u) : ?>
                                        <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="kb-field" id="bank-select-wrap">
                                <label class="kb-label" for="bank_idbank">Master bank</label>
                                <select name="bank_idbank" id="bank_idbank" class="form-select kb-input">
                                    <option value="">Pilih master bank</option>
                                    <?php foreach (($bank ?? []) as $b) : ?>
                                        <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank . ' - ' . $b->atas_nama . ' (' . $b->norek . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="kb-field" id="shared-wrap">
                                <div class="form-check form-switch kb-switch">
                                    <input class="form-check-input" type="checkbox" name="is_shared" id="is_shared" value="1">
                                    <label class="form-check-label" for="is_shared">Dipakai bersama lebih dari satu unit</label>
                                </div>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label" for="no_akun_coa">Akun COA</label>
                                <select name="no_akun_coa" id="no_akun_coa" class="form-select kb-input">
                                    <option value="">Pilih COA</option>
                                    <?php foreach (($no_akun ?? []) as $na) : ?>
                                        <option value="<?= esc($na->no_akun) ?>"><?= esc($na->no_akun . ' - ' . $na->nama_akun) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary kb-btn">Simpan rekening</button>
                        </form>
                    </div>

                    <!-- Langkah 2 -->
                    <div class="tab-pane fade" id="step2-pane" role="tabpanel">
                        <div class="kb-pane-head">
                            <div>
                                <div class="kb-pane-title">Catat saldo awal</div>
                                <div class="kb-hint">Saldo fisik pada tanggal mulai pencatatan.</div>
                            </div>
                        </div>
                        <form method="post" action="<?= base_url('kas_bank/saldo-awal/save') ?>">
                            <div class="kb-field">
                                <label class="kb-label">Rekening <span class="kb-req">*</span></label>
                                <select name="akun_kas_bank_id" class="form-select kb-input" required>
                                    <option value="">Pilih rekening</option>
                                    <?php foreach ($daftarAkun as $a) : ?>
                                        <option value="<?= (int) $a->idakun_kas_bank ?>">
                                            <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="kb-row">
                                <div class="kb-field">
                                    <label class="kb-label">Tanggal <span class="kb-req">*</span></label>
                                    <?php
                                    // Default TANGGAL CUT-OFF, bukan hari ini. saveSaldoAwal()
                                    // menolak tanggal selain cut-off, jadi default date('Y-m-d')
                                    // membuat operator gagal menyimpan dengan pesan yang membingungkan.
                                    $tanggalCutoffForm = \App\Services\Finance\FinanceScopeService::kasBankCutoffDate();
                                    ?>
                                    <input type="date" name="tanggal" class="form-control kb-input"
                                        value="<?= esc($tanggalCutoffForm) ?>"
                                        min="<?= esc($tanggalCutoffForm) ?>" max="<?= esc($tanggalCutoffForm) ?>"
                                        required>
                                    <div class="kb-hint">
                                        Opening balance — saldo riil terverifikasi pada akhir
                                        <?= esc($tanggalCutoffForm) ?>, bukan hasil SUM transaksi.
                                    </div>
                                </div>
                                <div class="kb-field">
                                    <label class="kb-label">Nominal (Rp) <span class="kb-req">*</span></label>
                                    <input type="text" name="saldo" class="form-control kb-input rupiah kb-mono" placeholder="0" inputmode="numeric" required>
                                </div>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label">Keterangan</label>
                                <input type="text" name="keterangan" class="form-control kb-input" placeholder="Opsional">
                            </div>

                            <button type="submit" class="btn btn-success kb-btn">Simpan saldo awal</button>
                        </form>
                    </div>

                    <!-- Langkah 3 -->
                    <div class="tab-pane fade" id="step3-pane" role="tabpanel">
                            <div class="kb-pane-head">
                                <div>
                                    <div class="kb-pane-title">Bagi hak per unit</div>
                                    <div class="kb-hint">Tentukan porsi saldo bank bersama untuk tiap cabang.
                                        Rekening Finance/HO tidak termasuk karena tidak memakai alokasi.</div>
                                </div>
                            </div>
                            <form method="post" action="<?= base_url('kas_bank/saldo-alokasi/save') ?>">
                            <div class="kb-field">
                                <label class="kb-label">Rekening bank <span class="kb-req">*</span></label>
                                <select name="akun_kas_bank_id" class="form-select kb-input" required>
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

                            <div class="kb-field">
                                <label class="kb-label">Cabang pemilik hak <span class="kb-req">*</span></label>
                                <select name="unit_id" class="form-select kb-input" required>
                                    <option value="">Pilih cabang</option>
                                    <?php foreach (($unit ?? []) as $u) : ?>
                                        <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label">Nominal hak (Rp) <span class="kb-req">*</span></label>
                                <input type="text" name="nominal" class="form-control kb-input rupiah kb-mono" placeholder="0" inputmode="numeric" required>
                            </div>

                            <div class="kb-field">
                                <label class="kb-label">Keterangan</label>
                                <input type="text" name="keterangan" class="form-control kb-input" placeholder="Opsional">
                            </div>

                            <button type="submit" class="btn btn-info text-white kb-btn">Simpan hak unit</button>
                        </form>
                    </div>

                    <!-- Langkah 4: Opening KAS -->
                    <div class="tab-pane fade" id="step4-pane" role="tabpanel">
                        <div class="kb-pane-head">
                            <div>
                                <div class="kb-pane-title">Baseline laci kas</div>
                                <div class="kb-hint">
                                    Opening = uang fisik yang ada di laci pada akhir
                                    <?= esc($cutoffOpening) ?>, diinput oleh Finance. Tanggalnya
                                    dikunci karena harus sama dengan opening bank.
                                </div>
                            </div>
                        </div>

                        <div class="kb-hint mb-2">
                            Opening ini <strong>bukan</strong> transaksi kas dan tidak ikut masuk
                            cash-in. Laci kas tidak punya statement bank dan tidak ada verifikasi —
                            begitu opening tersimpan, saldo laci mulai 1 hari setelah cut-off
                            memakai baseline ini.
                        </div>

                        <?php if (! $canInput) : ?>
                            <div class="kb-hint">Anda tidak punya hak input. Hubungi Finance untuk menetapkan opening KAS.</div>
                        <?php endif; ?>

                        <?php if ($openingKasBelum > 0) : ?>
                            <div class="kb-banner is-warning mb-2">
                                <div class="kb-banner-icon">
                                    <iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon>
                                </div>
                                <div class="kb-banner-content">
                                    <strong><?= $openingKasBelum ?> laci belum punya opening KAS</strong>
                                    <div class="mt-1">
                                        Saldo laci itu belum bisa dipakai sebagai acuan Setor maupun
                                        Penarikan, dan belum tampil sebagai saldo awal.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($daftarKas === []) : ?>
                            <div class="kb-hint">Belum ada rekening laci kas.</div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table kb-table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Laci kas</th>
                                            <th class="text-end">Opening</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daftarKas as $a) :
                                            $id  = (int) $a->idakun_kas_bank;
                                            $row = $openingKasById[$id] ?? null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <?= esc($a->nama_akun) ?>
                                                    <div class="kb-hint">
                                                        Unit <?= esc($unitMap[(int) ($row->unit_id ?? $a->unit_id)] ?? '—') ?>
                                                    </div>
                                                </td>
                                                <td class="text-end kb-mono">
                                                    <?php if ($row !== null) : ?>
                                                        <?= esc($rp((int) $row->opening)) ?>
                                                    <?php else : ?>
                                                        <span class="kb-badge kb-badge-muted">Belum ada saldo awal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row !== null && ! empty($row->keterangan)) : ?>
                                                        <span class="kb-meta"><?= esc($row->keterangan) ?></span>
                                                    <?php else : ?>
                                                        <span class="kb-meta">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <hr class="my-3">

                            <div class="kb-pane-title mb-2">Tetapkan / ubah opening</div>
                            <form method="post" action="<?= base_url('kas_bank/opening-kas/save') ?>">
                                <div class="kb-field">
                                    <label class="kb-label">Laci kas <span class="kb-req">*</span></label>
                                    <select name="akun_kas_bank_id" class="form-select kb-input" required>
                                        <option value="">Pilih laci kas</option>
                                        <?php foreach ($daftarKas as $a) : ?>
                                            <option value="<?= (int) $a->idakun_kas_bank ?>">
                                                <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="kb-row">
                                    <div class="kb-field">
                                        <label class="kb-label">Tanggal <span class="kb-req">*</span></label>
                                        <input type="date" name="tanggal_display" class="form-control kb-input"
                                            value="<?= esc($cutoffOpening) ?>"
                                            min="<?= esc($cutoffOpening) ?>" max="<?= esc($cutoffOpening) ?>"
                                            readonly aria-readonly="true">
                                        <div class="kb-hint">
                                            Dikunci di tanggal cut-off, sama dengan opening bank.
                                        </div>
                                    </div>
                                    <div class="kb-field">
                                        <label class="kb-label">Opening (Rp) <span class="kb-req">*</span></label>
                                        <input type="text" name="opening" class="form-control kb-input rupiah kb-mono"
                                            placeholder="0" inputmode="numeric" required>
                                    </div>
                                </div>

                                <div class="kb-field">
                                    <label class="kb-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control kb-input" placeholder="Opsional">
                                </div>

                                <div class="kb-hint mb-2">
                                    Angka ini adalah saldo riil fisik laci pada akhir
                                    <?= esc($cutoffOpening) ?>. Mengubahnya mengganti baseline;
                                    saldo setelah cut-off dihitung dari baseline baru + mutasi.
                                </div>

                                <button type="submit" class="btn btn-success kb-btn">Simpan opening KAS</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Tabel -->
        <section class="kb-main">
            <div class="kb-card">
                <div class="kb-card-head">
                    <div>
                        <div class="kb-title">Daftar rekening</div>
                        <div class="kb-hint">Master kas dan bank internal · <?= $totalAkun ?> rekening</div>
                    </div>
                    <input type="search" id="kb-search" class="form-control kb-input kb-search" placeholder="Cari rekening…" aria-label="Cari rekening">
                </div>

                <div class="table-responsive">
                    <table class="table kb-table align-middle mb-0" id="tbl-akun">
                        <thead>
                            <tr>
                                <th class="ps-3">Rekening</th>
                                <th>Cabang</th>
                                <th>Tipe</th>
                                <th>Bank</th>
                                <th>Status</th>
                                <th class="text-end">Saldo fisik</th>
                                <th class="text-center pe-3"><span class="visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarAkun)) : ?>
                                <tr>
                                    <td colspan="7" class="kb-empty">
                                        <iconify-icon icon="bi:inbox" class="kb-ico-lg"></iconify-icon>
                                        <div>Belum ada rekening. Mulai dari langkah 1 di panel kiri.</div>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($daftarAkun as $a) : ?>
                                    <?php
                                    $idAkun       = (int) $a->idakun_kas_bank;
                                    $fisik        = $saldo_fisik_akun[$idAkun] ?? 0;
                                    $alokasiAkun  = $alokasi[$idAkun] ?? [];
                                    $totalAlokasi = array_sum(array_map(fn($al) => (int) $al->nominal, $alokasiAkun));
                                    $shared       = (int) ($a->is_shared ?? 0) === 1;
                                    $jenis        = $akunJenis[$idAkun]
                                        ?? \App\Services\Finance\KasBankScopeService::KIND_UNIT;
                                    $isHO         = $jenis === \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO;
                                    $isShared     = $jenis === \App\Services\Finance\KasBankScopeService::KIND_SHARED;
                                    // Account scope = unit yang punya alokasi.
                                    // Untuk Finance/HO ini SELALU kosong — bukan
                                    //artinya salah: rekening HO memang tidak punya
                                    // unit pemilik dan tidak butuh alokasi.
                                    $entitled     = $akun_scope[$idAkun] ?? [];
                                    $entitledNama = implode(', ', array_map(
                                        static fn ($uid) => (string) ($unitMap[(int) $uid] ?? ('Unit ' . $uid)),
                                        $entitled
                                    ));
                                    $bankInfo     = $bankMap[(string) $a->bank_idbank] ?? null;
                                    ?>
                                    <tr class="kb-row-main" data-search="<?= esc(strtolower($a->nama_akun . ' ' . ($unitMap[(int) $a->unit_id] ?? '') . ' ' . ($a->no_akun_coa ?? '') . ' ' . ($bankInfo->nama_bank ?? '') . ' finance ho shared unit')) ?>">
                                        <td class="ps-3">
                                            <div class="kb-name">
                                                <?= esc($a->nama_akun) ?>
                                                <?php if ($isHO) : ?>
                                                    <span class="kb-badge kb-badge-purple">Finance/HO</span>
                                                <?php elseif ($isShared) : ?>
                                                    <span class="kb-badge kb-badge-muted">
                                                        <?= $entitledNama !== ''
                                                            ? 'Shared Antar Unit · ' . esc($entitledNama)
                                                            : 'Shared Antar Unit · belum dialokasikan' ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="kb-badge kb-badge-muted">Unit</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($isHO) : ?>
                                                <div class="kb-meta">
                                                    Tanpa alokasi unit · tujuan transfer semua unit ·
                                                    sumber hanya Admin Root / Finance
                                                </div>
                                            <?php endif; ?>
                                            <div class="kb-meta">COA <span class="kb-mono"><?= esc($a->no_akun_coa ?: '-') ?></span></div>
                                        </td>
                                        <td class="kb-sub"><?= esc($unitMap[(int) $a->unit_id] ?? 'Lintas unit') ?></td>
                                        <td>
                                            <?php if ($a->tipe === 'KAS') : ?>
                                                <span class="kb-badge kb-badge-blue">Kas</span>
                                            <?php else : ?>
                                                <span class="kb-badge kb-badge-amber">Bank</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bankInfo) : ?>
                                                <div class="kb-sub-strong"><?= esc($bankInfo->nama_bank) ?></div>
                                                <div class="kb-meta kb-mono"><?= esc($bankInfo->norek) ?></div>
                                            <?php else : ?>
                                                <span class="kb-meta"><?= esc($a->bank_idbank ?: '-') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($a->status === 'aktif') : ?>
                                                <span class="kb-badge kb-badge-green">Aktif</span>
                                            <?php else : ?>
                                                <span class="kb-badge kb-badge-red">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="kb-amount"><?= $rp($fisik) ?></div>
                                            <?php if ($isHO) : ?>
                                                <div class="kb-meta">Tanpa alokasi unit</div>
                                            <?php elseif ($a->tipe === 'BANK' && $totalAlokasi > 0) : ?>
                                                <div class="kb-meta kb-mono">
                                                    Dialokasikan <?= $rp($totalAlokasi) ?>
                                                    <?php if ($totalAlokasi > $fisik) : ?><span class="kb-badge kb-badge-red ms-1">Melebihi saldo</span><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-3">
                                            <?php if ($canInput) : ?>
                                                <button type="button" class="kb-icon-btn edit-akun" title="Edit rekening" aria-label="Edit <?= esc($a->nama_akun) ?>"
                                                    data-id="<?= $idAkun ?>"
                                                    data-unit="<?= (int) $a->unit_id ?>"
                                                    data-tipe="<?= esc($a->tipe) ?>"
                                                    data-nama="<?= esc($a->nama_akun) ?>"
                                                    data-bank="<?= esc($a->bank_idbank ?? '') ?>"
                                                    data-coa="<?= esc($a->no_akun_coa ?? '') ?>"
                                                    data-status="<?= esc($a->status) ?>"
                                                    data-shared="<?= $shared ? '1' : '0' ?>">
                                                    <iconify-icon icon="bi:pencil" class="kb-ico"></iconify-icon>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($alokasiAkun)) : ?>
                                        <tr class="kb-row-alloc">
                                            <td colspan="7" class="ps-3 pe-3">
                                                <div class="kb-alloc">
                                                    <span class="kb-meta">Hak unit</span>
                                                    <?php foreach ($alokasiAkun as $al) : ?>
                                                        <span class="kb-chip">
                                                            <?= esc($unitMap[(int) $al->unit_id] ?? 'Unit ' . $al->unit_id) ?>
                                                            <b class="kb-mono"><?= $rp($al->nominal) ?></b>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <tr id="kb-no-result" class="d-none">
                                    <td colspan="7" class="kb-empty">Tidak ada rekening yang cocok dengan pencarian.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    (function() {
        var canEdit = <?= $canInput ? 'true' : 'false' ?>;
        var $ = function(id) {
            return document.getElementById(id);
        };

        function toggleByTipe() {
            var isBank = $('tipe').value === 'BANK';
            $('bank-select-wrap').style.display = isBank ? '' : 'none';
            $('shared-wrap').style.display = isBank ? '' : 'none';
        }

        function exitEditMode() {
            $('form-akun').reset();
            $('idakun_kas_bank').value = '';
            $('form-title').textContent = 'Tambah rekening';
            $('btn-reset-form').classList.add('d-none');
            toggleByTipe();
        }

        document.querySelectorAll('.edit-akun').forEach(function(btn) {
            btn.addEventListener('click', function() {
                new bootstrap.Tab($('step1-tab')).show();

                $('idakun_kas_bank').value = btn.dataset.id;
                $('unit_id').value = btn.dataset.unit;
                $('tipe').value = btn.dataset.tipe;
                $('nama_akun').value = btn.dataset.nama;
                $('bank_idbank').value = btn.dataset.bank;
                $('no_akun_coa').value = btn.dataset.coa;
                $('status').value = btn.dataset.status;
                $('is_shared').checked = btn.dataset.shared === '1';

                $('form-title').textContent = 'Edit rekening';
                $('btn-reset-form').classList.remove('d-none');
                toggleByTipe();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });

        $('btn-reset-form').addEventListener('click', exitEditMode);
        $('tipe').addEventListener('change', toggleByTipe);

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
                    if (next && next.classList.contains('kb-row-alloc')) {
                        next.classList.toggle('d-none', !match);
                    }
                    if (match) shown++;
                });
                var empty = $('kb-no-result');
                if (empty) empty.classList.toggle('d-none', shown > 0);
            });
        }

        toggleByTipe();
    })();
</script>
