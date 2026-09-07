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

<!-- Pilih unit & tanggal (hanya admin root / manager; operator mengikuti unit & tanggal sendiri) -->
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
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">Progres input</span>
                            <span id="soProgText"><?= $terisi ?> dari <?= $totalItem ?> barang terisi (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress progress-so rounded-3 mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="soProgBar" style="width: <?= $pct ?>%"></div>
                        </div>
                        <?php if ($sisa > 0) : ?>
                            <small class="text-danger mt-1 d-inline-block" id="soSisaHint">
                                <i class="bi bi-exclamation-triangle"></i> Masih ada <?= $sisa ?> barang belum diisi jumlah real.
                            </small>
                        <?php else : ?>
                            <small class="text-success mt-1 d-inline-block" id="soSisaHint"><i class="bi bi-check-circle"></i> Semua barang sudah terisi — siap difinalisasi.</small>
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

        <!-- Tombol aksi (hanya untuk yang boleh mengubah; pengawas hanya melihat) -->
        <div class="border-top pt-3 mt-3 d-flex gap-2 flex-wrap align-items-center">
            <?php if (empty($canMutate)) : ?>
                <span class="badge bg-info-subtle text-info fs-6">
                    <i class="bi bi-eye"></i> Mode lihat — pengawas tidak dapat mengubah/simpan/finalisasi.
                </span>
            <?php elseif (!$periode) : ?>
                <form method="post" action="<?= base_url('stok_opname/mulai') ?>">
                    <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Mulai stok opname untuk unit ini pada tanggal ' + '<?= esc($tanggal, 'js') ?>' + '? Daftar barang otomatis diambil dari stok kartu.')">
                        <iconify-icon icon="solar:play-bold" class="me-1"></iconify-icon>Mulai Opname
                    </button>
                </form>
            <?php elseif ($isDraft) : ?>
                <button type="submit" form="formOpname" name="aksi" value="simpan" class="btn btn-primary">
                    <iconify-icon icon="solar:save-bold" class="me-1"></iconify-icon>Simpan Draft
                </button>
                <button type="submit" form="formOpname" name="aksi" value="finalisasi" class="btn btn-success btn-finalize">
                    <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>Finalisasi
                </button>
            <?php else : ?>
                <form method="post" action="<?= base_url('stok_opname/reopen') ?>" onsubmit="return confirm('Buka kembali stok opname FINAL ini? Hasil final unit/tanggal ini akan dihapus dan dihitung ulang setelah finalisasi baru. Lanjutkan?');">
                    <input type="hidden" name="unit" value="<?= (int)$unit ?>">
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                    <button type="submit" class="btn btn-outline-warning">
                        <iconify-icon icon="solar:refresh-bold" class="me-1"></iconify-icon>Reopen / Koreksi
                    </button>
                </form>
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
                    Simpan draft kapan saja (dicicil), lalu <strong>Finalisasi</strong> setelah semua barang terisi.
                </div>
            <?php else : ?>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle"></i>
                    Periode ini sudah <strong>FINAL</strong>. Untuk mengubah data, gunakan tombol <strong>Reopen / Koreksi</strong> lalu finalisasi ulang.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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
                if (sl === null) return false;
                if (soState.selisih === 'nz' && sl === 0) return false;
                if (soState.selisih === 'plus' && !(sl > 0)) return false;
                if (soState.selisih === 'minus' && !(sl < 0)) return false;
                if (soState.selisih === 'zero' && sl !== 0) return false;
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
            var unsaved = soItems.filter(function(i) { return i.dirty; }).length;
            var pct = total > 0 ? Math.round((filled / total) * 100) : 0;
            $('#cntSemua').text(total);
            $('#cntBelum').text(total - filled);
            $('#cntSudah').text(filled);
            $('#cntUnsaved').text(unsaved);

            $('#soProgBar').css('width', pct + '%');
            $('#soProgText').text(filled + ' dari ' + total + ' barang terisi (' + pct + '%)');
            var hint = (total - filled) > 0
                ? '<i class="bi bi-exclamation-triangle"></i> Masih ada ' + (total - filled) + ' barang belum diisi jumlah real.'
                : '<i class="bi bi-check-circle"></i> Semua barang sudah terisi — siap difinalisasi.';
            $('#soSisaHint').toggleClass('text-danger text-success', (total - filled) > 0).html(hint);
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
                html += '<tr class="' + (it.terisi ? '' : 'table-warning') + '" data-id="' + it.id + '" data-terisi="' + (it.terisi ? '1' : '0') + '">'
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
            $tr.toggleClass('table-warning', !it.terisi);
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
        $('#formOpname').on('submit', function(e) {
            if (soReadonlyGuard) { e.preventDefault(); return; }
            var aksi = $(this).find('button[type=submit]:focus').attr('name') || $(document.activeElement).attr('name');
            if (aksi !== 'finalisasi') {
                soSyncToForm();
                return;
            }
            var kosong = soItems.filter(function(i) { return !i.terisi; }).length;
            var msg = 'Finalisasi stok opname ini?\n\n' +
                'Setelah final, data dihitung pada KPI & riwayat dan periode terkunci. Data dapat diubah hanya dengan Reopen.';
            if (kosong > 0) {
                msg = 'PERINGATAN: masih ada ' + kosong + ' barang yang belum diisi Jumlah Real.\n' +
                    'Finalisasi tetap dapat dilanjutkan sesuai kebijakan.\n\n' + msg;
            }
            if (!confirm(msg)) {
                e.preventDefault();
                return;
            }
            soSyncToForm();
        });

        // Inisialisasi pertama.
        if (soItems.length) {
            soRender();
            if (!soReadonly) soFocusFirstInput();
        }
    });
</script>