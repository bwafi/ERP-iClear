<?= $this->include('stok/_opname_theme') ?>

<?php
$isDraft   = $periode && $periode->status === 'DRAFT';
$isFinal   = $periode && $periode->status === 'FINAL';
$totalItem = intval($periode->total_barang ?? count($items));
$terisi    = intval($periode->terisi_barang ?? 0);
$sisa      = max(0, $totalItem - $terisi);
$pct       = $totalItem > 0 ? round(($terisi / $totalItem) * 100) : 0;
$namaUnit  = '';
foreach ($unitList as $u) {
    if ((int)$u->idunit === (int)$unit) { $namaUnit = $u->NAMA_UNIT; break; }
}
$kompTotal = $periode ? (float)$periode->jumlah_komp : null;
$selisih   = ($periode && $periode->jumlah_selisih !== null) ? (float)$periode->jumlah_selisih : null;

// Stempel pembekuan: kapan stok komputer disalin dari stok_barang ke daftar opname.
$freezeTs  = $periode ? strtotime((string)($periode->created_at ?? '')) : false;
$freezeBy  = $periode ? (int)($periode->mulai_by ?? 0) : 0;
$freezeTxt = ($freezeTs !== false && $freezeTs > 0) ? date('d/m/Y H:i', $freezeTs) : '';
?>

<div class="stok-opname">

    <!-- Header halaman -->
    <header class="card so-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h4 class="fw-semibold mb-1">Stok Opname</h4>
                <p class="text-muted mb-0">Pencatatan stok fisik bertahap (DRAFT) lalu difinalisasi (FINAL) — riwayat &amp; KPI hanya memakai hasil final</p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Stok Opname</li>
                </ol>
            </nav>
        </div>

        <?php if (!empty($canPickUnit)) : ?>
            <div class="so-scope">
                <form method="get" action="<?= base_url('stok_opname') ?>" class="row g-2 align-items-end">
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label">Unit</label>
                        <select name="unit" class="form-select form-select-sm">
                            <?php foreach ($unitList as $u) : ?>
                                <option value="<?= (int)$u->idunit ?>" <?= (int)$u->idunit === (int)$unit ? 'selected' : '' ?>>
                                    <?= esc($u->NAMA_UNIT) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">Tanggal Opname</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= esc($tanggal) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2 col-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <iconify-icon icon="solar:magnifer-bold" class="me-1"></iconify-icon>Tampilkan
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (session()->getFlashdata('sukses')) : ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill mt-1"></i>
            <div class="flex-grow-1"><?= session()->getFlashdata('sukses') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('gagal')) : ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div class="flex-grow-1"><?= session()->getFlashdata('gagal') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <!-- Banner lanjutkan DRAFT yang menggantung lintas hari -->
    <?php if (!empty($canMutate) && !empty($draftTerbuka) && (string)$draftTerbuka->tanggal !== $tanggal) : ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <i class="bi bi-pencil-square"></i>
                Ada stok opname <strong>belum difinalisasi</strong> untuk unit ini
                (tanggal <strong><?= esc(date('d/m/Y', strtotime($draftTerbuka->tanggal))) ?></strong>,
                <?= (int)$draftTerbuka->terisi_barang ?>/<?= (int)$draftTerbuka->total_barang ?> terisi).
                One unit hanya boleh punya satu draft terbuka.
            </div>
            <a class="btn btn-sm btn-warning" href="<?= base_url('stok_opname?unit=' . (int)$unit . '&tanggal=' . esc($draftTerbuka->tanggal)) ?>">
                <i class="bi bi-arrow-right-circle"></i> Lanjutkan draft ini
            </a>
        </div>
    <?php endif; ?>

    <!-- Papan kendali: empat angka periode -->
    <section class="card so-board mb-3" aria-label="Ringkasan periode stok opname">
        <div class="card-body">
            <div class="so-board__grid">

                <div class="so-cell">
                    <span class="so-cell__label">Total Barang</span>
                    <span class="so-cell__value"><?= number_format($totalItem, 0, ',', '.') ?></span>
                    <span class="so-cell__note">
                        <?php if (!$periode) : ?>
                            Daftar dibuat saat Mulai Opname
                        <?php elseif ($isDraft) : ?>
                            <?= number_format($terisi, 0, ',', '.') ?> terisi · <?= number_format($sisa, 0, ',', '.') ?> sisa
                        <?php else : ?>
                            Seluruh barang berstok terisi
                        <?php endif; ?>
                    </span>
                </div>

                <div class="so-cell">
                    <span class="so-cell__label">Stok Komputer</span>
                    <span class="so-cell__value <?= $kompTotal === null ? 'is-empty' : '' ?>">
                        <?= $kompTotal === null ? '—' : number_format($kompTotal, 0, ',', '.') ?>
                    </span>
                    <span class="so-cell__note">
                        <?php if ($periode) : ?>
                            <iconify-icon icon="solar:lock-keyhole-bold" width="14" height="14"></iconify-icon>
                            Dibekukan saat mulai opname
                        <?php else : ?>
                            Disalin dari stok kartu saat mulai
                        <?php endif; ?>
                    </span>
                </div>

                <div class="so-cell">
                    <span class="so-cell__label">Total Selisih</span>
                    <?php if ($selisih === null) : ?>
                        <span class="so-cell__value is-empty">—</span>
                    <?php else : ?>
                        <span class="so-cell__value <?= $selisih > 0 ? 'is-neg' : ($selisih < 0 ? 'is-pos' : '') ?>">
                            <?= $selisih > 0 ? '+' : '' ?><?= number_format($selisih, 0, ',', '.') ?>
                        </span>
                    <?php endif; ?>
                    <span class="so-cell__note">
                        <?= $selisih === null ? 'Terhitung setiap kali jumlah real disimpan' : 'Real − komputer, seluruh baris' ?>
                    </span>
                </div>

                <div class="so-cell">
                    <span class="so-cell__label">KPI Stok Opname<?php if (!empty($kpiBulanIni)) : ?> — <?= esc(date('F Y', strtotime($kpiBulanIni['bulan'] . '-01'))) ?><?php endif; ?></span>
                    <?php if (!empty($kpiBulanIni)) : ?>
                        <?php $kpi = $kpiBulanIni; ?>
                        <span class="so-cell__value">
                            <?= (int)$kpi['final'] ?><span class="so-den"> / <?= (int)$kpi['target'] ?> periode FINAL</span>
                        </span>
                        <div class="so-meter" role="img" aria-label="KPI <?= (int)$kpi['pct'] ?> persen dari target bulanan">
                            <div class="so-meter__bar" style="width: <?= (int)$kpi['pct'] ?>%"></div>
                        </div>
                        <span class="so-cell__note"><?= (int)$kpi['pct'] ?>% target bulanan · hanya periode FINAL yang dihitung</span>
                    <?php else : ?>
                        <span class="so-cell__value is-empty">—</span>
                        <span class="so-cell__note">Belum ada data KPI bulan ini</span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>

    <!-- Status periode + aksi -->
    <section class="card so-status mb-3">
        <div class="card-body">
            <div class="so-status__top">
                <div class="so-status__id">
                    <?php if (!$periode) : ?>
                        <span class="so-state-chip is-idle">BELUM DIMULAI</span>
                    <?php elseif ($isDraft) : ?>
                        <span class="so-state-chip is-draft">DRAFT — dapat diedit</span>
                    <?php else : ?>
                        <span class="so-state-chip is-final">FINAL — kunci data</span>
                    <?php endif; ?>

                    <div class="so-status__unit">
                        <h6><?= esc($namaUnit) ?></h6>
                        <small><?= esc(date('d/m/Y', strtotime($tanggal))) ?> · <?= esc($tanggal) ?></small>
                    </div>

                    <?php if ($periode) : ?>
                        <span class="so-freeze">
                            <iconify-icon icon="solar:lock-keyhole-bold" width="14" height="14"></iconify-icon>
                            <?php if ($freezeTxt !== '') : ?>
                                Stok komputer dibekukan <strong><?= $freezeTxt ?></strong><?= $freezeBy > 0 ? ' oleh <strong>user #' . $freezeBy . '</strong>' : '' ?>
                            <?php else : ?>
                                Stok komputer dibekukan saat periode dimulai
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($periode && $isFinal && $periode->tanggal_finalisasi) : ?>
                        <span class="so-finalised">
                            <i class="bi bi-shield-check"></i>
                            Difinalisasi oleh user #<?= (int)$periode->finalisasi_by ?> pada
                            <?= esc(date('d/m/Y H:i', strtotime($periode->tanggal_finalisasi))) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="so-status__actions">
                    <?php if (empty($canMutate)) : ?>
                        <span class="so-state-chip is-idle">
                            <i class="bi bi-eye"></i> Mode lihat — role ini tidak dapat mengubah/simpan/finalisasi
                        </span>
                    <?php elseif (!$periode) : ?>
                        <form method="post" action="<?= base_url('stok_opname/mulai') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                            <button type="button" class="btn btn-primary" id="btnMulaiOpname">
                                <iconify-icon icon="solar:play-bold" class="me-1"></iconify-icon>Mulai Opname
                            </button>
                        </form>
                    <?php elseif ($isDraft) : ?>
                        <button type="submit" form="formOpname" name="aksi" value="simpan" class="btn btn-primary">
                            <iconify-icon icon="solar:save-bold" class="me-1"></iconify-icon>Simpan Draft
                        </button>
                        <?php if ($sisa > 0) : ?>
                            <button type="button" class="btn btn-success" disabled
                                title="Finalisasi hanya bisa dilakukan setelah semua barang berstok terisi.">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>Finalisasi
                            </button>
                        <?php else : ?>
                            <button type="submit" form="formOpname" name="aksi" value="finalisasi" class="btn btn-success btn-finalize">
                                <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>Finalisasi
                            </button>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php if (!empty($canReopen)) : ?>
                            <form method="post" action="<?= base_url('stok_opname/reopen') ?>" class="d-flex flex-wrap gap-2 align-items-center"
                                id="formReopen">
                                <?= csrf_field() ?>
                                <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                                <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                                <label class="visually-hidden" for="soAlasanReopen">Alasan reopen</label>
                                <input type="text" id="soAlasanReopen" name="alasan" class="form-control form-control-sm" maxlength="255"
                                    placeholder="Alasan reopen (wajib)" required style="min-width:16rem">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>Reopen / Koreksi
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isDraft) : ?>
                <?php
                    $wajibTotal = 0; $wajibTerisi = 0;
                    foreach ($items as $it) {
                        $komp = (float)($it['jumlah_komp'] ?? 0);
                        if ($komp != 0) { $wajibTotal++; if ($it['terisi']) $wajibTerisi++; }
                    }
                    $wpct = $wajibTotal > 0 ? min(100, round(($wajibTerisi / $wajibTotal) * 100)) : 100;
                    $wk = max(0, $wajibTotal - $wajibTerisi);
                ?>
                <div class="so-status__meter">
                    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1">
                        <span class="fw-semibold" style="font-size:.875rem">Progres input</span>
                        <span class="so-progress-text text-muted" id="soProgText"><?= $wajibTerisi ?> dari <?= $wajibTotal ?> barang berstok terisi (<?= $wpct ?>%)</span>
                    </div>
                    <div class="progress progress-so">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                             id="soProgBar" style="width: <?= $wpct ?>%; --so-prog: <?= (float) $wpct / 100 ?>"></div>
                    </div>
                    <?php if ($wk > 0) : ?>
                        <small class="so-hint text-danger" id="soSisaHint">
                            <i class="bi bi-exclamation-triangle"></i> Masih ada <?= $wk ?> barang berstok (stok &gt; 0) yang belum diisi jumlah real.
                        </small>
                    <?php else : ?>
                        <small class="so-hint text-success" id="soSisaHint">
                            <i class="bi bi-check-circle"></i> Semua barang berstok sudah terisi — siap difinalisasi.
                        </small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ledger barang opname -->
    <section class="card so-ledger">
        <div class="card-header">
            <h5 class="mb-0">Daftar Barang Opname</h5>
            <?php if ($periode) : ?>
                <span class="so-state-chip <?= $isFinal ? 'is-final' : 'is-draft' ?>">
                    <?= $isFinal ? 'FINAL — kunci data' : 'DRAFT — dapat diedit' ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <?php if (!$periode) : ?>
                <div class="so-empty">
                    <span class="so-empty__icon">
                        <iconify-icon icon="solar:clipboard-list-line-duotone"></iconify-icon>
                    </span>
                    <p class="so-empty__title">Belum ada daftar opname untuk tanggal ini</p>
                    <p class="so-empty__text">
                        Klik <strong>Mulai Opname</strong> pada panel status untuk menyalin daftar barang berstok
                        unit ini, lalu isi <strong>Jumlah Real</strong> sesuai hitung fisik — boleh dicicil dan
                        dilanjutkan di hari lain.
                    </p>
                    <span class="so-empty__stamp">
                        <iconify-icon icon="solar:lock-keyhole-bold" width="14" height="14"></iconify-icon>
                        Saat mulai, stok komputer tiap barang dibekukan pada saat itu juga
                    </span>
                </div>
            <?php elseif (empty($items)) : ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    Tidak ada barang untuk diopname (daftar barang kosong).
                </div>
            <?php else : ?>

                <?php if ($isDraft) : ?>
                    <form method="post" action="<?= base_url('stok_opname/simpan') ?>" id="formOpname">
                        <?= csrf_field() ?>
                        <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                        <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

                        <div class="so-command">
                            <div class="so-filter" role="group" aria-label="Filter status isi barang" id="soFilter">
                                <button type="button" class="btn btn-warning fw-semibold" data-filter="belum">
                                    Belum Terisi <span class="badge text-bg-light" id="cntBelum"></span>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-filter="semua">
                                    Semua <span class="badge text-bg-light" id="cntSemua"></span>
                                </button>
                                <?php if (!empty($canMutate)) : ?>
                                    <button type="button" class="btn btn-outline-danger" data-filter="unsaved">
                                        Belum Disimpan <span class="badge text-bg-light" id="cntUnsaved"></span>
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-success" data-filter="sudah">
                                    Sudah Terisi <span class="badge text-bg-light" id="cntSudah"></span>
                                </button>
                            </div>

                            <div class="so-search">
                                <label class="visually-hidden" for="soSearch">Cari kode atau nama barang</label>
                                <input type="search" id="soSearch" class="form-control form-control-sm"
                                    placeholder="Cari kode / nama barang..." autocomplete="off">
                            </div>

                            <div class="so-spacer"></div>

                            <?php if (!empty($canFilterSelisih)) : ?>
                                <div class="so-field">
                                    <label for="soSelisih">Selisih</label>
                                    <select id="soSelisih" class="form-select form-select-sm w-auto">
                                        <option value="all">Semua</option>
                                        <option value="nz">Ada Selisih (≠ 0)</option>
                                        <option value="plus">Lebih (+)</option>
                                        <option value="minus">Kurang (−)</option>
                                        <option value="zero">Presisi (= 0)</option>
                                        <option value="stok0">Stok 0 (tidak wajib)</option>
                                        <option value="stokplus">Stok &gt; 0 (wajib)</option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="so-field">
                                <label for="soSort">Urut</label>
                                <select id="soSort" class="form-select form-select-sm w-auto">
                                    <option value="recent">Terbaru diinput</option>
                                    <option value="kode">Kode A–Z</option>
                                </select>
                            </div>

                            <div class="so-field">
                                <label for="soPageSize">Tampil</label>
                                <select id="soPageSize" class="form-select form-select-sm w-auto">
                                    <option value="50">50</option>
                                    <option value="100" selected>100</option>
                                    <option value="200">200</option>
                                    <option value="500">500</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive so-scroll">
                            <table class="table so-table align-middle" id="opnameTable">
                                <thead>
                                    <tr>
                                        <th scope="col" class="so-idx">#</th>
                                        <th scope="col">Kode</th>
                                        <th scope="col">Nama Barang</th>
                                        <th scope="col" class="text-center">Stok Komputer</th>
                                        <th scope="col" class="text-center">Jumlah Real</th>
                                        <th scope="col" class="text-center">Selisih</th>
                                        <th scope="col" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="soBody"></tbody>
                            </table>
                        </div>

                        <div class="so-pager">
                            <small id="soInfo"></small>
                            <ul class="pagination pagination-sm mb-0" id="soPagination"></ul>
                        </div>
                    </form>

                    <p class="so-footnote">
                        <i class="bi bi-info-circle"></i>
                        Isi <strong>Jumlah Real</strong> sesuai hasil hitung fisik. Selisih dihitung otomatis.
                        Simpan draft kapan saja (dicicil, boleh dilanjutkan di hari lain).
                        <strong>Finalisasi</strong> baru bisa dilakukan setelah <strong>seluruh</strong> barang berstok terisi.
                        Kosongkan kolom untuk membatalkan isian.
                    </p>

                <?php else : ?>

                    <div class="table-responsive so-scroll">
                        <table class="table so-table align-middle" id="opnameTable">
                            <thead>
                                <tr>
                                    <th scope="col" class="so-idx">#</th>
                                    <th scope="col">Kode</th>
                                    <th scope="col">Nama Barang</th>
                                    <th scope="col" class="text-center">Stok Komputer</th>
                                    <th scope="col" class="text-center">Jumlah Real</th>
                                    <th scope="col" class="text-center">Selisih</th>
                                    <th scope="col" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $it) : ?>
                                    <tr data-terisi="<?= $it['terisi'] ? '1' : '0' ?>">
                                        <td class="so-idx"><?= $i + 1 ?></td>
                                        <td class="so-kode"><?= esc($it['kode_barang']) ?></td>
                                        <td>
                                            <?= esc($it['nama_barang']) ?>
                                            <?php if ($it['jenis_hp'] || $it['warna']) : ?>
                                                <span class="so-sub">
                                                    <?= esc($it['jenis_hp']) ?><?= $it['warna'] ? ' · ' . esc($it['warna']) : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="so-num"><?= number_format($it['jumlah_komp'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <span class="so-num" style="font-size:.9375rem">
                                                <?= $it['jumlah_real'] !== null ? number_format($it['jumlah_real'], 0, ',', '.') : '—' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($it['jumlah_selisih'] !== null) : ?>
                                                <span class="so-delta <?= $it['selisih_negatif'] ? 'is-neg' : ($it['selisih_positif'] ? 'is-pos' : 'is-zero') ?>">
                                                    <?= $it['jumlah_selisih'] > 0 ? '+' : '' ?><?= number_format($it['jumlah_selisih'], 0, ',', '.') ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="so-delta is-zero">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="so-state <?= $it['terisi'] ? 'is-done' : '' ?>">
                                                <?= $it['terisi'] ? 'Terisi' : 'Belum' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="so-footnote">
                        <i class="bi bi-lock"></i>
                        Periode ini sudah <strong>FINAL</strong>. Untuk mengubah data, gunakan tombol <strong>Reopen / Koreksi</strong>
                        dengan alasan, lalu finalisasi ulang.
                    </p>

                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>

    <!-- Jejak audit + riwayat periode -->
    <?php if (!empty($auditTrail) || !empty($historis)) : ?>
        <?php $duoCol = (!empty($auditTrail) && !empty($historis)) ? 'col-lg-6' : 'col-12'; ?>
        <div class="row g-3 mt-1">

            <?php if (!empty($auditTrail)) : ?>
                <div class="<?= $duoCol ?>">
                    <section class="card so-trail h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Aktivitas Periode Ini</h6>
                        </div>
                        <div class="card-body">
                            <ul>
                                <?php foreach ($auditTrail as $a) : ?>
                                    <li>
                                        <span>
                                            <span class="badge bg-<?= $a['aksi'] === 'finalisasi' ? 'success' : ($a['aksi'] === 'reopen' ? 'warning' : 'secondary') ?>-subtle text-<?= $a['aksi'] === 'finalisasi' ? 'success' : ($a['aksi'] === 'reopen' ? 'warning' : 'secondary') ?>">
                                                <?= esc($a['aksi']) ?>
                                            </span>
                                            <?= (int)$a['jumlah_terisi'] ?>/<?= (int)$a['jumlah_barang'] ?> terisi
                                            <?php if (!empty($a['catatan'])) : ?>
                                                — <em><?= esc($a['catatan']) ?></em>
                                            <?php endif; ?>
                                        </span>
                                        <span class="so-trail__meta">
                                            user #<?= (int)$a['actor_id'] ?> ·
                                            <?= esc(date('d/m/Y H:i', strtotime((string)$a['created_at']))) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if (!empty($historis)) : ?>
                <div class="<?= $duoCol ?>">
                    <section class="card so-history h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-calendar3"></i> Riwayat Periode Opname — <?= esc($namaUnit) ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Barang</th>
                                            <th class="text-end">Stok Komputer</th>
                                            <th class="text-end">Selisih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historis as $hp) : ?>
                                            <?php $hpSelisih = $hp->jumlah_selisih; ?>
                                            <tr>
                                                <td>
                                                    <?= esc(date('d/m/Y', strtotime($hp->tanggal))) ?>
                                                    <?php if ((int)$hp->unit_idunit === (int)$unit && $hp->tanggal === $tanggal) : ?>
                                                        <span class="badge bg-primary-subtle text-primary ms-1">aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($hp->status === 'FINAL') : ?>
                                                        <span class="so-state-chip is-final">FINAL</span>
                                                    <?php else : ?>
                                                        <span class="so-state-chip is-draft">DRAFT</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= (int)$hp->total_barang ?> (<?= (int)$hp->terisi_barang ?> terisi)</td>
                                                <td class="text-end"><?= number_format((float)$hp->jumlah_komp, 0, ',', '.') ?></td>
                                                <td class="text-end <?= ($hpSelisih !== null && (float)$hpSelisih != 0) ? 'fw-bold' : 'text-muted' ?>">
                                                    <?= $hpSelisih !== null ? number_format((float)$hpSelisih, 0, ',', '.') : '—' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>

<?php
    // Data JSON untuk table list cerdas (hanya digunakan saat DRAFT).
    $opnameItemsJson = '[]';
    if ($isDraft) {
        $payload = [];
        foreach ($items as $it) {
            $payload[] = [
                'id'      => (int)$it['barang_id'],
                'kode'    => (string)$it['kode_barang'],
                'nama'    => (string)$it['nama_barang'],
                'jenis'   => (string)$it['jenis_hp'],
                'warna'   => (string)$it['warna'],
                'komp'    => (float)$it['jumlah_komp'],
                'real'    => $it['jumlah_real'] !== null ? (float)$it['jumlah_real'] : null,
                'terisi'  => (bool)$it['terisi'],
            ];
        }
        $opnameItemsJson = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
?>

<script>
    $(function() {
        // ============================================================
        //  LIST CERDAS (hanya saat DRAFT): filter + cari + pagination
        // ============================================================
        var soItems = <?= $opnameItemsJson ?>;
        // Role pengawas: hanya melihat (input readonly, tanpa aksi mutasi).
        var soReadonly = <?= empty($canMutate) ? 'true' : 'false' ?>;
        // Status "belum disimpan" per baris + urutan terbaru diinput (client-side).
        var soSeq = 0;
        soItems.forEach(function(it) {
            it.origReal  = it.real;
            it.dirty     = false; // nilainya beda dari kondisi terakhir tersimpan
            it.touchedAt = 0;     // urutan terbaru diinput (semakin besar = semakin baru)
        });

        function soEsc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        function soNorm(v) {
            return (v === null || v === undefined || v === '') ? '' : String(v).trim();
        }
        function soFmt(n) {
            if (n === null || n === undefined || n === '') return '';
            return Number(n).toLocaleString('id-ID');
        }
        function soSelHtml(it) {
            if (it.real === null || it.real === '') return '<span class="so-delta is-zero">—</span>';
            var s = it.real - it.komp;
            var cls = s < 0 ? 'is-neg' : (s > 0 ? 'is-pos' : 'is-zero');
            var sign = s > 0 ? '+' : '';
            return '<span class="so-delta ' + cls + '">' + sign + soFmt(s) + '</span>';
        }
        function soStatusHtml(filled) {
            return filled
                ? '<span class="so-state is-done">Terisi</span>'
                : '<span class="so-state">Belum</span>';
        }

        var soState = { filter: 'belum', q: '', page: 1, size: 100, sort: 'recent', selisih: 'all' };

        // Nilai selisih per barang (null bila belum diisi real).
        function soSelisih(it) {
            if (it.real === null || it.real === '') return null;
            return Number(it.real) - Number(it.komp);
        }

        function soMatches(it) {
            // Saat ada kata kunci pencarian, semua filter diabaikan:
            // barang yang cocok tetap tampil, status terlihat via badge.
            var q = soState.q.toLowerCase();
            if (q) {
                return (it.kode.toLowerCase().indexOf(q) !== -1) || (it.nama.toLowerCase().indexOf(q) !== -1);
            }
            if (soState.filter === 'belum' && it.terisi) return false;
            if (soState.filter === 'sudah' && !it.terisi) return false;
            if (soState.filter === 'unsaved' && !it.dirty) return false;
            // Filter selisih (hanya untuk role pengawas yang punya akses).
            if (soState.selisih !== 'all') {
                var sl = soSelisih(it);
                if (soState.selisih === 'stok0') {
                    if (it.komp != 0) return false;
                } else if (soState.selisih === 'stokplus') {
                    if (it.komp == 0) return false;
                } else {
                    if (sl === null) return false;
                    if (soState.selisih === 'nz' && sl === 0) return false;
                    if (soState.selisih === 'plus' && !(sl > 0)) return false;
                    if (soState.selisih === 'minus' && !(sl < 0)) return false;
                    if (soState.selisih === 'zero' && sl !== 0) return false;
                }
            }
            return true;
        }

        function soSort(list) {
            if (soState.sort === 'kode') {
                return list.sort(function(a, b) { return String(a.kode).localeCompare(String(b.kode)); });
            }
            // Terbaru diinput: barang yang belum pernah disentuh tetap di bawah (urut kode).
            return list.sort(function(a, b) {
                if (a.touchedAt && !b.touchedAt) return -1;
                if (!a.touchedAt && b.touchedAt) return 1;
                if (a.touchedAt && b.touchedAt) return b.touchedAt - a.touchedAt;
                return String(a.kode).localeCompare(String(b.kode));
            });
        }

        function soUpdateProgressAndCounts() {
            var total = soItems.length;
            var filled = soItems.filter(function(i) { return i.terisi; }).length;
            var wajibTotal = soItems.filter(function(i) { return i.komp != 0; }).length;
            var wajibFilled = soItems.filter(function(i) { return i.komp != 0 && i.terisi; }).length;
            var wajibKurang = Math.max(0, wajibTotal - wajibFilled);
            var unsaved = soItems.filter(function(i) { return i.dirty; }).length;
            var pct = wajibTotal > 0 ? Math.round((wajibFilled / wajibTotal) * 100) : 100;
            $('#cntSemua').text(total);
            $('#cntBelum').text(total - filled);
            $('#cntSudah').text(filled);
            $('#cntUnsaved').text(unsaved);

            document.getElementById('soProgBar').style.setProperty('--so-prog', pct / 100);
            $('#soProgText').text(wajibFilled + ' dari ' + wajibTotal + ' barang berstok terisi (' + pct + '%)');
            var hint = wajibKurang > 0
                ? '<i class="bi bi-exclamation-triangle"></i> Masih ada ' + wajibKurang + ' barang berstok (stok > 0) yang belum diisi jumlah real.'
                : '<i class="bi bi-check-circle"></i> Semua barang berstok sudah terisi — siap difinalisasi.';
            $('#soSisaHint').toggleClass('text-danger text-success', wajibKurang > 0).html(hint);
        }

        function soRender() {
            var list = soSort(soItems.filter(soMatches));
            var pages = Math.max(1, Math.ceil(list.length / soState.size));
            if (soState.page > pages) soState.page = pages;
            var start = (soState.page - 1) * soState.size;
            var slice = list.slice(start, start + soState.size);

            var html = '';
            slice.forEach(function(it, i) {
                var realVal = it.real !== null && it.real !== '' ? it.real : '';
                var rowNo = start + i + 1;
                html += '<tr class="' + ((it.komp != 0 && !it.terisi) ? 'table-warning' : '') + '" data-id="' + it.id + '" data-terisi="' + (it.terisi ? '1' : '0') + '">'
                    + '<td class="so-idx">' + rowNo + '</td>'
                    + '<td class="so-kode">' + soEsc(it.kode) + '</td>'
                    + '<td>' + soEsc(it.nama)
                    + ((it.jenis || it.warna) ? '<span class="so-sub">' + soEsc(it.jenis) + (it.warna ? ' · ' + soEsc(it.warna) : '') + '</span>' : '')
                    + '</td>'
                    + '<td class="so-num">' + soFmt(it.komp) + '</td>'
                    + '<td class="text-center"><input type="number" step="1" min="0" class="form-control form-control-sm text-center input-real" name="items[' + it.id + '][jumlah_real]" value="' + soEsc(realVal) + '" placeholder="0" aria-label="Jumlah real ' + soEsc(it.kode) + '"' + (soReadonly ? ' readonly' : '') + '></td>'
                    + '<td class="text-center">' + soSelHtml(it) + '</td>'
                    + '<td class="text-center">' + soStatusHtml(it.terisi) + '</td>'
                    + '</tr>';
            });
            $('#soBody').html(html);

            $('#soInfo').text(
                (slice.length === 0 ? '0' : (start + 1) + '–' + (start + slice.length)) +
                ' dari ' + list.length + ' barang'
            );
            soRenderPagination(pages);
            soUpdateProgressAndCounts();
        }

        function soRenderPagination(pages) {
            var cur = soState.page;
            var items = '';
            items += '<li class="page-item ' + (cur === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-pg="' + (cur - 1) + '">&laquo;</a></li>';
            var from = Math.max(1, cur - 2);
            var to = Math.min(pages, from + 4);
            for (var p = from; p <= to; p++) {
                items += '<li class="page-item ' + (p === cur ? 'active' : '') + '"><a class="page-link" href="#" data-pg="' + p + '">' + p + '</a></li>';
            }
            items += '<li class="page-item ' + (cur === pages ? 'disabled' : '') + '"><a class="page-link" href="#" data-pg="' + (cur + 1) + '">&raquo;</a></li>';
            $('#soPagination').html(items);
        }

        $('#soPagination').on('click', 'a.page-link', function(e) {
            e.preventDefault();
            var pg = parseInt($(this).attr('data-pg'), 10);
            if (pg >= 1) { soState.page = pg; soRender(); }
        });

        $('#soFilter button').on('click', function() {
            $('#soFilter button').removeClass('btn-warning btn-danger fw-semibold').addClass('btn-outline-secondary');
            var f = $(this).attr('data-filter');
            $(this).removeClass('btn-outline-secondary').addClass((f === 'unsaved' ? 'btn-danger' : 'btn-warning') + ' fw-semibold');
            soState.filter = f;
            soState.page = 1;
            soRender();
        });

        $('#soSearch').on('input', function() {
            soState.q = $(this).val();
            soState.page = 1;
            soRender();
        });

        // Enter pada pencarian tidak boleh meng-submit form opname.
        $('#soSearch').on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); }
        });

        $('#soSort').on('change', function() {
            soState.sort = $(this).val();
            soState.page = 1;
            soRender();
        });

        $('#soSelisih').on('change', function() {
            soState.selisih = $(this).val();
            soState.page = 1;
            soRender();
        });

        $('#soPageSize').on('change', function() {
            soState.size = parseInt($(this).val(), 10);
            soState.page = 1;
            soRender();
        });

        // Enter pada input Jumlah Real = pindah ke baris berikutnya (bukan submit),
        // sehingga tidak memicu banyak validasi/peringatan.
        $('#opnameTable').on('keydown', '.input-real', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                var $inputs = $('#opnameTable .input-real');
                var idx = $inputs.index(this);
                if (idx < $inputs.length - 1) {
                    $inputs.eq(idx + 1).focus();
                }
            }
        });

        // Hitung selisih otomatis + update progress realtime + tandai "belum disimpan".
        // Baris yang baru terisi langsung hilang dari tab "Belum Terisi".
        $('#opnameTable').on('input', '.input-real', function() {
            var $input = $(this);
            var $tr = $input.closest('tr');
            var id = parseInt($tr.attr('data-id'), 10);
            var it = null;
            for (var k = 0; k < soItems.length; k++) { if (soItems[k].id === id) { it = soItems[k]; break; } }
            if (!it) return;

            var raw = $input.val();
            var becameFilled = (raw !== '');
            it.real = becameFilled ? raw : null;
            it.terisi = becameFilled;
            it.dirty = soNorm(it.real) !== soNorm(it.origReal);
            it.touchedAt = ++soSeq;

            // Update selisih & badge pada baris yang terlihat.
            $tr.find('td:nth-child(6)').html(soSelHtml(it));
            $tr.find('td:last-child').html(soStatusHtml(it.terisi));
            $tr.toggleClass('table-warning', it.komp != 0 && !it.terisi);
            $tr.attr('data-terisi', it.terisi ? '1' : '0');

            // Masih mengetik angka (bukan selesai) -> jangan render ulang.
            if ($input.val() === '') {
                if (soState.filter === 'belum' || soState.filter === 'unsaved') { soRender(); soFocusFirstInput(); }
            }
            soUpdateProgressAndCounts();
        });

        // Saat input selesai (blur/change) baris baru terisi:
        // - filter = Belum Terisi (tanpa pencarian) -> row disembunyikan & fokus pindah.
        // - filter = Belum Disimpan & nilai kembali sama dgn tersimpan -> row keluar & fokus pindah.
        $('#opnameTable').on('change', '.input-real', function() {
            var $tr = $(this).closest('tr');
            var id = parseInt($tr.attr('data-id'), 10);
            var it = null;
            for (var k = 0; k < soItems.length; k++) { if (soItems[k].id === id) { it = soItems[k]; break; } }
            if (!it) return;

            var needHide = false;
            if (it.terisi && soState.filter === 'belum' && !soState.q) needHide = true;
            if (soState.filter === 'unsaved' && !it.dirty && !soState.q) needHide = true;
            if (needHide) {
                var nextId = null;
                var $inputs = $('#opnameTable .input-real');
                var idx = $inputs.index(this);
                if (idx < $inputs.length - 1) { nextId = $inputs.eq(idx + 1).attr('name'); }
                soRender();
                soFocusFirstInput(nextId);
            }
        });

        function soFocusFirstInput(no) {
            var $inputs = $('#opnameTable .input-real');
            if ($inputs.length) {
                if (no) { $inputs.filter('[name="' + no + '"]').first().focus(); }
                else { $inputs.first().focus(); }
            }
        }

        // Finalisasi: tampilkan PERINGATAN bila masih ada yang belum terisi,
        // namun tetap DIPERBOLEHKAN melanjutkan finalisasi.
        // Sebelum submit, sinkronkan SEMUA baris (semua halaman) ke form sebagai hidden input,
        // supaya nilai yang diinput di halaman lain ikut terkirim (tidak hilang).
        function soSyncToForm() {
            var $form = $('#formOpname');
            $form.find('input[data-so-sync]').remove();
            for (var k = 0; k < soItems.length; k++) {
                var it = soItems[k];
                var v = soNorm(it.real);
                $form.append(
                    '<input type="hidden" data-so-sync="1" name="items[' + it.id + '][jumlah_real]" value="' + soEsc(v) + '">'
                );
            }
        }

        var soReadonlyGuard = soReadonly;
        var soFinalSubmit = false;
        $('.btn-finalize').on('click', function(e) {
            e.preventDefault();
            if (soReadonlyGuard) return;
            if (soFinalSubmit) {
                soSyncToForm();
                $('#formOpname').submit();
                return;
            }
            var wajibKosong = soItems.filter(function(i) { return i.komp != 0 && !i.terisi; }).length;
            var semuaKosong = soItems.filter(function(i) { return !i.terisi; }).length;
            $('#modalFinalTitle').text(wajibKosong > 0 ? 'Peringatan Finalisasi' : 'Konfirmasi Finalisasi');
            var html = '';
            if (wajibKosong > 0) {
                html = '<div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle"></i> Masih ada <strong>' + wajibKosong + '</strong> barang berstok (stok > 0) yang belum diisi Jumlah Real.</div>';
            }
            html += '<p>Finalisasi stok opname ini?</p><p class="text-muted small">Setelah final, data dihitung pada KPI &amp; riwayat dan periode terkunci. Data dapat diubah hanya dengan Reopen.</p>';
            if (semuaKosong > wajibKosong) {
                html += '<p class="text-muted small">Catatan: ' + (semuaKosong - wajibKosong) + ' barang dengan stok 0 (tidak wajib) dibiarkan kosong.</p>';
            }
            $('#modalFinalBody').html(html);
            $('#modalFinalisasi').modal('show');
        });
        $('#formOpname').on('submit', function(e) {
            if (soReadonlyGuard) { e.preventDefault(); return; }
            soSyncToForm();
        });

        $('#btnModalFinalYes').on('click', function() {
            soSyncToForm();
            soFinalSubmit = true;
            $('#formOpname').append('<input type="hidden" name="aksi" value="finalisasi">');
            $('#formOpname').submit();
        });

        // Inisialisasi pertama.
        if (soItems.length) {
            soRender();
            if (!soReadonly) soFocusFirstInput();
        }
        $('#btnMulaiOpname').on('click', function() {
            $('#modalMulaiTitle').text('Mulai Stok Opname');
            $('#modalMulaiBody').html(
                '<p>Mulai stok opname untuk unit ini pada tanggal <strong><?= esc($tanggal) ?></strong>?</p>'
                + '<p class="text-muted small">Daftar barang akan diambil otomatis dari barang yang berstok (termasuk stok 0 sesuai pengaturan terbaru).</p>'
                + '<div class="alert alert-info mb-0 d-flex gap-2"><i class="bi bi-lock-fill mt-1"></i>'
                + '<div><strong>Stok komputer dibekukan pada saat ini juga.</strong> '
                + 'Angka acuan tiap barang disalin dari stok kartu waktu Anda menekan Mulai, '
                + 'sehingga penjualan atau mutasi setelahnya tidak mengubah kolom Stok Komputer di daftar opname. '
                + 'Selisih tetap dihitung dari angka beku tersebut.</div></div>'
            );
            $('#modalMulai').modal('show');
        });
        $('#btnModalMulaiYes').on('click', function() {
            $('form[action$="stok_opname/mulai"]').submit();
        });
        $('#formReopen').on('submit', function(e) {
            e.preventDefault();
            $('#modalReopenTitle').text('Buka Kembali (Reopen)');
            $('#modalReopenBody').html('<p>Buka kembali stok opname FINAL ini?</p><p class="text-muted small">Nilai final sebelumnya akan ditandai tidak aktif dan tetap tersimpan di riwayat. Alasan wajib telah terisi.</p>');
            $('#modalReopen').attr('data-target-form', '#formReopen');
            $('#modalReopen').modal('show');
        });
        $('#btnModalReopenYes').on('click', function() {
            var target = $('#modalReopen').attr('data-target-form');
            $(target).off('submit').submit();
        });
    });
</script>

<!-- Modal Konfirmasi Finalisasi -->
<div class="modal fade" id="modalFinalisasi" tabindex="-1" aria-labelledby="modalFinalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalFinalTitle">Konfirmasi Finalisasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalFinalBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnModalFinalYes"><i class="bi bi-check-lg"></i> Ya, Finalisasi</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Mulai Opname -->
<div class="modal fade" id="modalMulai" tabindex="-1" aria-labelledby="modalMulaiTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalMulaiTitle">Mulai Stok Opname</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalMulaiBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnModalMulaiYes"><i class="bi bi-play-fill"></i> Ya, Mulai</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Reopen -->
<div class="modal fade" id="modalReopen" tabindex="-1" aria-labelledby="modalReopenTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalReopenTitle">Buka Kembali (Reopen)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalReopenBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="btnModalReopenYes"><i class="bi bi-pencil-square"></i> Ya, Reopen</button>
      </div>
    </div>
  </div>
</div>
