<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Stok Opname</h4>
            <span class="text-muted small">Pencatatan stok fisik bertahap (DRAFT) lalu difinalisasi (FINAL) — riwayat & KPI hanya memakai hasil final</span>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stok Opname</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('sukses')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= session()->getFlashdata('sukses') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('gagal')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= session()->getFlashdata('gagal') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Banner lanjutkan DRAFT yang menggantung lintas hari -->
<?php if (!empty($canMutate) && !empty($draftTerbuka) && (string)$draftTerbuka->tanggal !== $tanggal) : ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
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

<!-- Progres KPI stok opname bulan ini -->
<?php if (!empty($kpiBulanIni)) : ?>
    <?php $kpi = $kpiBulanIni; ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">
                    <i class="bi bi-trophy"></i> KPI Stok Opname — <?= esc(date('F Y', strtotime($kpi['bulan'] . '-01'))) ?>
                </span>
                <span class="small text-muted">
                    <strong><?= (int)$kpi['final'] ?></strong> periode FINAL / target <?= (int)$kpi['target'] ?>
                    (<?= (int)$kpi['pct'] ?>%)
                </span>
            </div>
            <div class="progress progress-so rounded-3" style="height:8px">
                <div class="progress-bar <?= $kpi['final'] >= $kpi['target'] ? 'bg-success' : 'bg-primary' ?>"
                     role="progressbar" style="width: <?= (int)$kpi['pct'] ?>%"></div>
            </div>
            <small class="text-muted">
                Hanya periode yang seluruh barang berstok terisi dan sudah difinalisasi yang dihitung.
            </small>
        </div>
    </div>
<?php endif; ?>

<!-- Pilih unit & tanggal (hanya role lintas-unit; operator mengikuti unit & tanggal sendiri) -->
<?php if (!empty($canPickUnit)) : ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <form method="get" action="<?= base_url('stok_opname') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1 fw-semibold">Unit</label>
                    <select name="unit" class="form-select">
                        <?php foreach ($unitList as $u) : ?>
                            <option value="<?= (int)$u->idunit ?>" <?= (int)$u->idunit === (int)$unit ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1 fw-semibold">Tanggal Opname</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= esc($tanggal) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><iconify-icon icon="solar:magnifer-bold" class="me-1"></iconify-icon>Tampilkan</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

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
?>

<!-- Kartu status + indikator -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <?php if (!$periode) : ?>
                            <span class="badge bg-secondary fs-6">BELUM DIMULAI</span>
                        <?php elseif ($isDraft) : ?>
                            <span class="badge bg-warning-subtle text-warning fs-6">DRAFT</span>
                        <?php else : ?>
                            <span class="badge bg-success-subtle text-success fs-6">FINAL</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?= esc($namaUnit) ?></h6>
                        <small class="text-muted"><?= esc($tanggal) ?></small>
                    </div>
                    <?php if ($periode && $isFinal && $periode->tanggal_finalisasi) : ?>
                        <div class="text-muted small">
                            <i class="bi bi-shield-check"></i>
                            Difinalisasi oleh user #<?= (int)$periode->finalisasi_by ?> pada
                            <?= esc(date('d/m/Y H:i', strtotime($periode->tanggal_finalisasi))) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($periode && $isDraft) : ?>
                    <?php
                        $wajibTotal = 0; $wajibTerisi = 0;
                        foreach ($items as $it) {
                            $komp = (float)($it['jumlah_komp'] ?? 0);
                            if ($komp != 0) { $wajibTotal++; if ($it['terisi']) $wajibTerisi++; }
                        }
                        $wpct = $wajibTotal > 0 ? min(100, round(($wajibTerisi / $wajibTotal) * 100)) : 100;
                        $wk = max(0, $wajibTotal - $wajibTerisi);
                    ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">Progres input</span>
                            <span id="soProgText"><?= $wajibTerisi ?> dari <?= $wajibTotal ?> barang berstok terisi (<?= $wpct ?>%)</span>
                        </div>
                        <div class="progress progress-so rounded-3 mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="soProgBar" style="width: <?= $wpct ?>%"></div>
                        </div>
                        <?php if ($wk > 0) : ?>
                            <small class="text-danger mt-1 d-inline-block" id="soSisaHint">
                                <i class="bi bi-exclamation-triangle"></i> Masih ada <?= $wk ?> barang berstok (stok > 0) yang belum diisi jumlah real.
                            </small>
                        <?php else : ?>
                            <small class="text-success mt-1 d-inline-block" id="soSisaHint"><i class="bi bi-check-circle"></i> Semua barang berstok sudah terisi — siap difinalisasi.</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-5">
                <div class="row text-center g-2">
                    <div class="col-4">
                        <div class="border rounded-3 p-2 bg-light">
                            <div class="fs-5 fw-bold text-primary"><?= number_format($totalItem, 0, ',', '.') ?></div>
                            <div class="small text-muted">Total Barang</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded-3 p-2 bg-light">
                            <div class="fs-5 fw-bold <?= ($periode && $isFinal) ? 'text-success' : 'text-warning' ?>">
                                <?= $periode ? number_format($periode->jumlah_komp ?? 0, 0, ',', '.') : '-' ?>
                            </div>
                            <div class="small text-muted">Stok Komputer</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded-3 p-2 bg-light">
                            <div class="fs-5 fw-bold <?= ($periode && (float)($periode->jumlah_selisih ?? 0) > 0) ? 'text-danger' : (($periode && (float)($periode->jumlah_selisih ?? 0) < 0) ? 'text-success' : 'text-muted') ?>">
                                <?= $periode && $periode->jumlah_selisih !== null ? number_format((float)$periode->jumlah_selisih, 0, ',', '.') : '-' ?>
                            </div>
                            <div class="small text-muted">Total Selisih</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol aksi (hanya untuk yang boleh mengubah; role mode-lihat hanya melihat) -->
        <div class="border-top pt-3 mt-3 d-flex gap-2 flex-wrap align-items-center">
            <?php if (empty($canMutate)) : ?>
                <span class="badge bg-info-subtle text-info fs-6">
                    <i class="bi bi-eye"></i> Mode lihat — role ini tidak dapat mengubah/simpan/finalisasi.
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
                <form method="post" action="<?= base_url('stok_opname/reopen') ?>" class="d-flex flex-wrap gap-2 align-items-start"
                    id="formReopen">
                    <?= csrf_field() ?>
                    <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                    <div class="me-2" style="min-width:320px">
                        <input type="text" name="alasan" class="form-control form-control-sm" maxlength="255"
                            placeholder="Alasan reopen (wajib)" required>
                    </div>
                    <button type="submit" class="btn btn-outline-warning">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>Reopen / Koreksi
                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabel barang -->
<div class="card shadow-sm border-0">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Daftar Barang Opname</h5>
        <?php if ($periode) : ?>
            <span class="badge bg-<?= $isFinal ? 'success' : 'warning' ?>-subtle text-<?= $isFinal ? 'success' : 'warning' ?>">
                <?= $isFinal ? 'FINAL — kunci data' : 'DRAFT — dapat diedit' ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$periode) : ?>
            <div class="alert alert-info mb-0">
                Belum ada periode stok opname untuk unit/tanggal ini.
                Klik <strong>Mulai Opname</strong> untuk membuat daftar barang dari stok kartu dan mulai input secara bertahap.
            </div>
        <?php elseif (empty($items)) : ?>
            <div class="alert alert-warning mb-0">Tidak ada barang untuk diopname (daftar barang kosong).</div>
        <?php else : ?>
            <?php if ($isDraft) : ?>
                <form method="post" action="<?= base_url('stok_opname/simpan') ?>" id="formOpname">
                    <?= csrf_field() ?>
            <?php endif; ?>
            <input type="hidden" name="unit" value="<?= (int)$unit ?>">
            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

            <?php if ($isDraft) : ?>

                <!-- Toolbar list cerdas -->
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-auto">
                        <div class="btn-group btn-group-sm" role="group" id="soFilter">
                            <button type="button" class="btn btn-warning fw-semibold" data-filter="belum">
                                Belum Terisi <span class="badge text-bg-light ms-1" id="cntBelum"></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-filter="semua">
                                Semua <span class="badge text-bg-light ms-1" id="cntSemua"></span>
                            </button>
                            <?php if (!empty($canMutate)) : ?>
                                <button type="button" class="btn btn-outline-danger" data-filter="unsaved">
                                    Belum Disimpan <span class="badge text-bg-light ms-1" id="cntUnsaved"></span>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-success" data-filter="sudah">
                                Sudah Terisi <span class="badge text-bg-light ms-1" id="cntSudah"></span>
                            </button>
                        </div>
                    </div>
                    <div class="col-auto">
                        <input type="search" id="soSearch" class="form-control form-control-sm" placeholder="Cari kode / nama barang..."
                            autocomplete="off">
                    </div>
                    <div class="col-auto ms-auto d-flex align-items-center gap-3">
                        <?php if (!empty($canFilterSelisih)) : ?>
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted mb-0">Selisih</label>
                                <select id="soSelisih" class="form-select form-select-sm w-auto">
                                    <option value="all">Semua</option>
                                    <option value="nz">Ada Selisih (≠ 0)</option>
                                    <option value="plus">Lebih (+)</option>
                                    <option value="minus">Kurang (−)</option>
                                    <option value="zero">Presisi (= 0)</option>
                                    <option value="stok0">Stok 0 (tidak wajib)</option>
                                    <option value="stokplus">Stok > 0 (wajib)</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">Urut</label>
                            <select id="soSort" class="form-select form-select-sm w-auto">
                                <option value="recent">Terbaru diinput</option>
                                <option value="kode">Kode A–Z</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">Tampil</label>
                            <select id="soPageSize" class="form-select form-select-sm w-auto">
                                <option value="50">50</option>
                                <option value="100" selected>100</option>
                                <option value="200">200</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive so-scroll">
                    <table class="table table-sm align-middle table-hover" id="opnameTable">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th class="text-center">Stok Komputer</th>
                                <th class="text-center" width="130">Jumlah Real</th>
                                <th class="text-center" width="110">Selisih</th>
                                <th class="text-center" width="110">Status</th>
                            </tr>
                        </thead>
                        <tbody id="soBody"></tbody>
                    </table>
                </div>

                <!-- Pagination + info -->
                <div class="d-flex justify-content-between align-items-center flex-wrap mt-2 gap-2">
                    <small class="text-muted" id="soInfo"></small>
                    <ul class="pagination pagination-sm mb-0" id="soPagination"></ul>
                </div>

            <?php else : ?>

                <div class="table-responsive so-scroll">
                    <table class="table table-sm align-middle table-hover" id="opnameTable">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th class="text-center">Stok Komputer</th>
                                <th class="text-center" width="130">Jumlah Real</th>
                                <th class="text-center" width="110">Selisih</th>
                                <th class="text-center" width="110">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $it) : ?>
                                <tr class="<?= ($isDraft && !$it['terisi']) ? 'table-warning' : '' ?>"
                                    data-terisi="<?= $it['terisi'] ? '1' : '0' ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td class="fw-semibold"><?= esc($it['kode_barang']) ?></td>
                                    <td>
                                        <?= esc($it['nama_barang']) ?>
                                        <?php if ($it['jenis_hp'] || $it['warna']) : ?>
                                            <br><small class="text-muted">
                                                <?= esc($it['jenis_hp']) ?><?= $it['warna'] ? ' · ' . esc($it['warna']) : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?= number_format($it['jumlah_komp'], 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?= $it['jumlah_real'] !== null ? number_format($it['jumlah_real'], 0, ',', '.') : '-' ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($it['jumlah_selisih'] !== null) : ?>
                                            <span class="fw-bold <?= $it['selisih_negatif'] ? 'text-danger' : ($it['selisih_positif'] ? 'text-success' : 'text-muted') ?>">
                                                <?= $it['jumlah_selisih'] > 0 ? '+' : '' ?><?= number_format($it['jumlah_selisih'], 0, ',', '.') ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $it['terisi'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                            <?= $it['terisi'] ? 'Terisi' : 'Belum' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <?php if ($isDraft) : ?>
                </form>
            <?php endif; ?>

            <?php if ($isDraft) : ?>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i>
                    Isi <strong>Jumlah Real</strong> sesuai hasil hitung fisik. Selisih dihitung otomatis.
                    Simpan draft kapan saja (dicicil, boleh dilanjutkan di hari lain).
                    <strong>Finalisasi</strong> baru bisa dilakukan setelah <strong>seluruh</strong> barang berstok terisi.
                    Kosongkan kolom untuk membatalkan isian.
                </div>
            <?php else : ?>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i>
                    Periode ini sudah <strong>FINAL</strong>. Untuk mengubah data, gunakan tombol <strong>Reopen / Koreksi</strong>
                    dengan alasan, lalu finalisasi ulang.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Jejak audit periode -->
<?php if (!empty($auditTrail)) : ?>
    <div class="card shadow-sm border-0 mt-3">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Aktivitas Periode Ini</h6></div>
        <div class="card-body py-2 px-4">
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($auditTrail as $a) : ?>
                    <li class="border-bottom py-1 d-flex flex-wrap gap-2 justify-content-between">
                        <span>
                            <span class="badge bg-<?= $a['aksi'] === 'finalisasi' ? 'success' : ($a['aksi'] === 'reopen' ? 'warning' : 'secondary') ?>-subtle text-<?= $a['aksi'] === 'finalisasi' ? 'success' : ($a['aksi'] === 'reopen' ? 'warning' : 'secondary') ?>">
                                <?= esc($a['aksi']) ?>
                            </span>
                            <?= (int)$a['jumlah_terisi'] ?>/<?= (int)$a['jumlah_barang'] ?> terisi
                            <?php if (!empty($a['catatan'])) : ?>
                                — <em><?= esc($a['catatan']) ?></em>
                            <?php endif; ?>
                        </span>
                        <span class="text-muted">
                            user #<?= (int)$a['actor_id'] ?> ·
                            <?= esc(date('d/m/Y H:i', strtotime((string)$a['created_at']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Riwayat periode unit ini -->
<?php if (!empty($historis)) : ?>
    <div class="card shadow-sm border-0 mt-3">
        <div class="card-header">
            <h6 class="mb-0">Riwayat Periode Opname — <?= esc($namaUnit) ?></h6>
        </div>
        <div class="card-body py-2 px-4">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Barang</th>
                            <th class="text-center">Stok Komputer</th>
                            <th class="text-center">Selisih</th>
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
                                        <span class="badge bg-success-subtle text-success">FINAL</span>
                                    <?php else : ?>
                                        <span class="badge bg-warning-subtle text-warning">DRAFT</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= (int)$hp->total_barang ?> (<?= (int)$hp->terisi_barang ?> terisi)</td>
                                <td class="text-end"><?= number_format((float)$hp->jumlah_komp, 0, ',', '.') ?></td>
                                <td class="text-end <?= ($hpSelisih !== null && (float)$hpSelisih != 0) ? 'fw-bold' : 'text-muted' ?>">
                                    <?= $hpSelisih !== null ? (float)$hpSelisih : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

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
    // Track progress bar: jangan polos/putih, tampilkan jelas.
    $(function() {
        $('head').append(
            '<style>' +
            '.progress-so { background: #fde9c8 !important; height: 14px; ' +
            '  border: 1px solid #f5d0a0; box-shadow: inset 0 1px 2px rgba(0,0,0,.08); }' +
            '.progress-so .progress-bar { background: linear-gradient(90deg, #fbbf24, #f97316); ' +
            '  border-radius: 0; }' +
            '.card-status-so { background: #fff8ec !important; border-left: 4px solid #f59e0b; }' +
            '.so-scroll { max-height: 65vh; }' +
            '#opnameTable thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }' +
            '</style>'
        );

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
            if (it.real === null || it.real === '') return '<span class="text-muted">-</span>';
            var s = it.real - it.komp;
            var cls = s < 0 ? 'text-danger' : (s > 0 ? 'text-success' : 'text-muted');
            var sign = s > 0 ? '+' : '';
            return '<span class="fw-bold ' + cls + '">' + sign + soFmt(s) + '</span>';
        }
        function soStatusHtml(filled) {
            return filled
                ? '<span class="badge bg-success-subtle text-success">Terisi</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Belum</span>';
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

            $('#soProgBar').css('width', pct + '%');
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
                    + '<td>' + rowNo + '</td>'
                    + '<td class="fw-semibold">' + soEsc(it.kode) + '</td>'
                    + '<td>' + soEsc(it.nama)
                    + ((it.jenis || it.warna) ? '<br><small class="text-muted">' + soEsc(it.jenis) + (it.warna ? ' · ' + soEsc(it.warna) : '') + '</small>' : '')
                    + '</td>'
                    + '<td class="text-center fw-bold">' + soFmt(it.komp) + '</td>'
                    + '<td class="text-center"><input type="number" step="1" min="0" class="form-control form-control-sm text-center input-real" name="items[' + it.id + '][jumlah_real]" value="' + soEsc(realVal) + '" placeholder="0"' + (soReadonly ? ' readonly' : '') + '></td>'
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
            html += '<p>Finalisasi stok opname ini?</p><p class="text-muted small">Setelah final, data dihitung pada KPI & riwayat dan periode terkunci. Data dapat diubah hanya dengan Reopen.</p>';
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
            $('#modalMulaiBody').html('<p>Mulai stok opname untuk unit ini pada tanggal <strong><?= esc($tanggal) ?></strong>?</p><p class="text-muted small">Daftar barang akan diambil otomatis dari barang yang berstok (termasuk stok 0 sesuai pengaturan terbaru).</p>');
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
