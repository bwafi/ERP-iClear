<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
// Nilai dari controller dipakai utuh; session hanya fallback supaya view ini
// masih bisa dirender sendiri (mis. dari script smoke test). Dua-duanya dulu
// dihitung di sini dan di controller, yang berarti satu bisa berubah tanpa
// yang lain — dan kontrol unit ikut hilang atau bocor.
$isLintas = $isLintas ?? in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);
$filter      = $filter      ?? ['search' => '', 'status' => '', 'dari' => '', 'sampai' => '', 'unit' => 0];
$currentPage = $currentPage ?? 1;
$perPage     = $perPage     ?? 25;
$total       = $total       ?? 0;
$totalPages  = $totalPages  ?? 1;

/**
 * Bangun query string yang mempertahankan filter + halaman yang sedang aktif.
 *
 * Dipakai untuk link paginasi dan untuk field `asal` pada form terima/batal.
 * Tanpa ini, setiap klik "next" menghapus filter yang sedang dipakai, dan
 * setiap terima/batal melempar user ke daftar polos.
 */
$qs = static function (array $overrides = []) use ($filter, $currentPage): string {
    // Perbandingan harus !== '' , bukan ?: . Di PHP, string '0' itu falsy,
    // jadi `?:` akan membuang status='0' — dan "Belum Diterima" justru
    // filter paling sering dipakai di halaman ini. Efeknya: user menyaring
    // status 0, klik halaman 2, dan filternya hilang diam-diam.
    $q = [
        'search' => $filter['search'] !== '' ? $filter['search'] : null,
        'status' => $filter['status'] !== '' ? $filter['status'] : null,
        'dari'   => $filter['dari'] !== '' ? $filter['dari'] : null,
        'sampai'=> $filter['sampai'] !== '' ? $filter['sampai'] : null,
        'unit'   => $filter['unit'] ? (int) $filter['unit'] : null,
        'page'   => $currentPage,
    ];
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') { unset($q[$k]); } else { $q[$k] = $v; }
    }

    return http_build_query(array_filter($q, static fn ($v) => $v !== null && $v !== ''));
};
$urlMasuk = static fn(array $o = []) => base_url('mutasi_stok/masuk') . ($qs($o) ? '?' . $qs($o) : '');
// Batal terima jauh lebih sempit daripada terima: hanya Admin Root. Menerima
// menambah satu dokumen keuangan, membatalkan menghapusnya, jadi yang boleh
// membatalkan tidak boleh sama longgarnya dengan yang boleh membuat.
$bisaBatal = in_array((int) session('ID_JABATAN'), [1], true);
?>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Konfirmasi Terima Mutasi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('mutasi_stok') ?>">Mutasi Stok</a>
                </li>
                <li class="breadcrumb-item active">Konfirmasi Terima</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session('sukses')) : ?>
    <div class="alert alert-success py-2"><?= esc(session('sukses')) ?></div>
<?php endif; ?>
<?php if (session('gagal')) : ?>
    <div class="alert alert-danger py-2"><?= esc(session('gagal')) ?></div>
<?php endif; ?>

<div class="card shadow-none border">
    <div class="card-header bg-transparent">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><?= $isLintas ? 'Semua Mutasi Masuk' : 'Mutasi Masuk Untuk Unit Saya' ?></h5>

            <form method="GET" action="<?= base_url('mutasi_stok/masuk') ?>"
                class="d-flex flex-wrap align-items-center gap-2">
                <input type="text" name="search" value="<?= esc($filter['search']) ?>"
                    class="form-control form-control-sm" style="width: 220px;"
                    placeholder="Cari no. nota mutasi...">

                <select name="status" class="form-select form-select-sm" style="width: 160px;">
                    <option value="">Semua status</option>
                    <option value="0" <?= $filter['status'] === '0' ? 'selected' : '' ?>>Belum diterima</option>
                    <option value="1" <?= $filter['status'] === '1' ? 'selected' : '' ?>>Sudah diterima</option>
                </select>

                <?php if ($isLintas) : ?>
                    <select name="unit" class="form-select form-select-sm" style="width: 180px;">
                        <option value="">Semua unit</option>
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= (int) $filter['unit'] === (int) $u->idunit ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <div class="input-group input-group-sm" style="width: 190px;">
                    <span class="input-group-text">Kirim</span>
                    <input type="date" name="dari" value="<?= esc($filter['dari']) ?>" class="form-control"
                        aria-label="Tanggal kirim dari">
                    <span class="input-group-text">s/d</span>
                    <input type="date" name="sampai" value="<?= esc($filter['sampai']) ?>" class="form-control"
                        aria-label="Tanggal kirim sampai">
                </div>

                <button type="submit" class="btn btn-sm btn-secondary">
                    <i class="ti ti-search"></i> Terapkan
                </button>
                <?php if ($filter['search'] !== '' || $filter['status'] !== '' || $filter['dari'] !== ''
                    || $filter['sampai'] !== '' || (int) $filter['unit'] > 0) : ?>
                    <a href="<?= base_url('mutasi_stok/masuk') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="card-body table-responsive">
        <?php if (empty($items)) : ?>
            <?php
            // Ditulis eksplisit, bukan `||` biasa: status '0' itu falsy, jadi
            // menyaring "Belum Diterima" akan terbaca sebagai "tidak ada
            // filter" lalu muncul pesan "belum ada mutasi masuk" yang bohong.
            $adaFilter = $filter['search'] !== '' || $filter['status'] !== ''
                || $filter['dari'] !== '' || $filter['sampai'] !== '' || (int) $filter['unit'] > 0;
            ?>
            <div class="text-center py-5">
                <i class="ti ti-mood-empty fs-2 d-block mb-2 text-secondary"></i>
                <p class="text-muted mb-2">
                    <?php if ($adaFilter) : ?>
                        Tidak ada mutasi yang cocok dengan filter ini.
                    <?php else : ?>
                        Belum ada mutasi masuk.
                    <?php endif; ?>
                </p>
                <?php if ($adaFilter) : ?>
                    <a href="<?= base_url('mutasi_stok/masuk') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-filter-off"></i> Reset filter
                    </a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Nota</th>
                        <th>Tgl Kirim</th>
                        <th>Pengirim</th>
                        <th>Penerima</th>
                        <th class="text-end">Nilai</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $m) : ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($m->no_nota_mutasi) ?><br>
                                <small class="text-muted">#<?= $m->idmutasi ?></small>
                            </td>
                            <td><?= esc($m->tanggal_kirim) ?></td>
                            <td><?= esc($m->nama_unit_kirim) ?></td>
                            <td><?= esc($m->nama_unit_terima) ?></td>
                            <td class="text-end fw-semibold"><?= $rp($m->total) ?></td>
                            <td>
                                <?php if ($m->status === '1') : ?>
                                    <span class="badge bg-success-subtle text-success">Diterima</span>
                                    <small class="d-block text-muted"><?= esc($m->tanggal_terima ?: '-') ?></small>
                                <?php else : ?>
                                    <span class="badge bg-warning-subtle text-warning">Belum diterima</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($m->status === '1' && $bisaBatal) : ?>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-batal-terima"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalBatalTerima"
                                        data-id="<?= $m->idmutasi ?>"
                                        data-nota="<?= esc($m->no_nota_mutasi) ?>"
                                        data-terima="<?= esc($m->nama_unit_terima) ?>"
                                        data-kirim="<?= esc($m->nama_unit_kirim) ?>"
                                        data-total="<?= (int) $m->total ?>"
                                        data-tanggal-terima="<?= esc($m->tanggal_terima ?: '') ?>">Batal Terima</button>
                                <?php endif; ?>
                                <?php if ($m->status !== '1') : ?>
                                    <button type="button"
                                        class="btn btn-sm btn-success btn-terima"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalTerimaMutasi"
                                        data-id="<?= $m->idmutasi ?>"
                                        data-nota="<?= esc($m->no_nota_mutasi) ?>"
                                        data-kirim="<?= esc($m->nama_unit_kirim) ?>"
                                        data-terima="<?= esc($m->nama_unit_terima) ?>"
                                        data-tanggal="<?= esc($m->tanggal_kirim) ?>"
                                        data-total="<?= (int) $m->total ?>"
                                        data-detail="<?= esc(json_encode(array_map(fn($d) => [
                                            'nama'   => $d->nama_barang,
                                            'jumlah' => (float) $d->jumlah_kirim,
                                            'harga'  => (float) $d->harga_mutasi,
                                            'nilai'  => (float) ($d->nilai ?? 0),
                                        ], $m->detail))) ?>">Terima</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-light"
                                    data-bs-toggle="collapse" data-bs-target="#detail-<?= $m->idmutasi ?>">Detail</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="detail-<?= $m->idmutasi ?>">
                            <td colspan="7" class="ps-4 py-1">
                                <table class="table table-sm table-striped align-middle mb-1 w-50">
                                    <thead>
                                        <tr><th>Barang</th><th class="text-end">Jml Kirim</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($m->detail as $d) : ?>
                                            <tr>
                                                <td><?= esc($d->nama_barang) ?></td>
                                                <td class="text-end"><?= (int) $d->jumlah_kirim ?></td>
                                                <td class="text-end"><?= $rp($d->harga_mutasi) ?></td>
                                                <td class="text-end"><?= $rp($d->nilai ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <small class="text-muted">Total: <strong><?= $rp($m->total) ?></strong></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-4 py-3 border-top">
        <span class="text-muted small">
            Menampilkan
            <?= $total > 0 ? ($currentPage - 1) * $perPage + 1 : 0 ?>
            - <?= min($currentPage * $perPage, $total) ?>
            dari <?= $total ?> mutasi
        </span>

        <nav aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $urlMasuk(['page' => $currentPage - 1]) ?>"
                        tabindex="-1" aria-label="Halaman sebelumnya">
                        <iconify-icon icon="solar:arrow-left-broken" width="18" height="18"></iconify-icon>
                    </a>
                </li>
                <?php
                $window   = 2;
                $start    = max(1, $currentPage - $window);
                $end      = min($totalPages, $currentPage + $window);
                $showHead = $start > 1;
                $showTail = $end < $totalPages;
                ?>
                <?php if ($showHead) : ?>
                    <li class="page-item"><a class="page-link" href="<?= $urlMasuk(['page' => 1]) ?>">1</a></li>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++) : ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $urlMasuk(['page' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($showTail) : ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <li class="page-item"><a class="page-link" href="<?= $urlMasuk(['page' => $totalPages]) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $urlMasuk(['page' => $currentPage + 1]) ?>"
                        aria-label="Halaman berikutnya">
                        <iconify-icon icon="solar:arrow-right-broken" width="18" height="18"></iconify-icon>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="modalTerimaMutasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formTerimaMutasi" method="post" class="modal-content">
            <input type="hidden" name="asal" value="<?= esc($qs()) ?>">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Terima Mutasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded-2 h-100">
                            <small class="text-muted d-block">No. Nota Mutasi</small>
                            <span class="fw-semibold" id="cm-nota">-</span>
                            <small class="text-muted d-block mt-2">Tanggal Kirim</small>
                            <span class="fw-semibold" id="cm-tanggal">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded-2 h-100">
                            <small class="text-muted d-block"><i class="ti ti-corner-up-left"></i> Pengirim</small>
                            <span class="fw-semibold" id="cm-kirim">-</span>
                            <small class="text-muted d-block mt-2"><i class="ti ti-corner-down-right"></i> Penerima</small>
                            <span class="fw-semibold" id="cm-terima">-</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-1">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="text-end">Jml Kirim</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="cm-detail"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-2 mb-3">
                    <span class="fw-semibold">Total Nilai Mutasi</span>
                    <span class="fs-5 fw-bold text-primary" id="cm-total">-</span>
                </div>

                <div class="alert alert-info py-2 mb-0">
                    <i class="ti ti-info-circle"></i> Dengan menekan <strong>Terima &amp; Buat H/P</strong>,
                    mutasi ditandai <strong>Diterima</strong> dan sistem otomatis membuat
                    <strong>Hutang/Piutang antar unit</strong> (pengirim berpiutang, penerima berhutang)
                    dengan <strong>jatuh tempo <?= date('Y-m-d', strtotime('+3 days')) ?></strong>
                    (+3 hari dari hari ini). Verifikasi harga &amp; jumlah di atas sebelum mengonfirmasi.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">
                    <i class="ti ti-check"></i> Terima &amp; Buat H/P
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<?php if ($bisaBatal) : ?>
<div class="modal fade" id="modalBatalTerima" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formBatalTerima" method="post" class="modal-content" novalidate>
            <input type="hidden" name="asal" value="<?= esc($qs()) ?>">
            <div class="modal-header">
                <h5 class="modal-title">Batalkan Penerimaan Mutasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded-2 h-100">
                            <small class="text-muted d-block">No. Nota Mutasi</small>
                            <span class="fw-semibold" id="bcb-nota">-</span>
                            <small class="text-muted d-block mt-2">Diterima pada</small>
                            <span class="fw-semibold" id="bcb-tanggal">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded-2 h-100">
                            <small class="text-muted d-block"><i class="ti ti-corner-up-left"></i> Pengirim</small>
                            <span class="fw-semibold" id="bcb-kirim">-</span>
                            <small class="text-muted d-block mt-2"><i class="ti ti-corner-down-right"></i> Penerima</small>
                            <span class="fw-semibold" id="bcb-terima">-</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-2 mb-3">
                    <span class="fw-semibold">Nilai Dokumen Dibatalkan</span>
                    <span class="fs-5 fw-bold text-danger" id="bcb-total">-</span>
                </div>

                <div class="alert alert-danger py-2 mb-3">
                    <i class="ti ti-alert-triangle"></i> Menekan <strong>Batalkan Penerimaan</strong> akan
                    mengembalikan mutasi ke status <strong>Belum Diterima</strong> dan membatalkan
                    <strong>Hutang/Piutang antar unit</strong> yang dibuat saat penerimaan
                    (nilai <span class="fw-semibold" id="bcb-total-inline">-</span>). Dokumennya tidak dihapus
                    fisik, tapi ditandai batal dan alasannya disimpan.
                </div>

                <div class="mb-2">
                    <label for="alasan_batal" class="form-label fw-semibold">
                        Alasan pembatalan <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="alasan_batal" name="alasan_batal" rows="3"
                        maxlength="255" required
                        placeholder="Contoh: salah klik tombol Terima pada nota ini."></textarea>
                    <div class="form-text" id="alasanBatalHelp">
                        Wajib diisi. Alasan ini tersimpan pada mutasi dan pada dokumen H/P yang dibatalkan.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kembali</button>
                <button type="submit" class="btn btn-danger">
                    <i class="ti ti-arrow-back-up"></i> Batalkan Penerimaan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var rp = function (n) {
        n = Number(n || 0);
        return 'Rp ' + n.toLocaleString('id-ID');
    };
    document.querySelectorAll('.btn-terima').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            var detail = JSON.parse(btn.dataset.detail || '[]');

            document.getElementById('cm-nota').textContent = btn.dataset.nota;
            document.getElementById('cm-tanggal').textContent = btn.dataset.tanggal;
            document.getElementById('cm-kirim').textContent = btn.dataset.kirim;
            document.getElementById('cm-terima').textContent = btn.dataset.terima;
            document.getElementById('cm-total').textContent = rp(btn.dataset.total);

            var tbody = document.getElementById('cm-detail');
            tbody.innerHTML = '';
            if (!detail.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada detail barang.</td></tr>';
            } else {
                detail.forEach(function (d) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + d.nama + '</td>' +
                        '<td class="text-end">' + Number(d.jumlah) + '</td>' +
                        '<td class="text-end">' + rp(d.harga) + '</td>' +
                        '<td class="text-end">' + rp(d.nilai) + '</td>';
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('formTerimaMutasi').setAttribute('action',
                <?= json_encode(base_url('mutasi_stok/terima/')) ?> + id);
        });
    });

    // Batal terima. Alasan wajib, dan divalidasi di sini supaya pesan
    // muncul di tempat yang diklik orang, bukan toasted dari server
    // setelah halaman sudah pindah.
    var formBatal = document.getElementById('formBatalTerima');
    if (formBatal) {
        var alasan = document.getElementById('alasan_batal');

        document.querySelectorAll('.btn-batal-terima').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var total = rp(btn.dataset.total);

                document.getElementById('bcb-nota').textContent = btn.dataset.nota;
                document.getElementById('bcb-kirim').textContent = btn.dataset.kirim;
                document.getElementById('bcb-terima').textContent = btn.dataset.terima;
                document.getElementById('bcb-tanggal').textContent = btn.dataset.tanggalTerima || '-';
                document.getElementById('bcb-total').textContent = total;
                document.getElementById('bcb-total-inline').textContent = total;

                // Dibuka berulang tidak boleh menyisakan alasan lama:
                // membatalkan mutasi berbeda butuh alasan yang berbeda.
                alasan.value = '';
                alasan.setCustomValidity('');

                formBatal.setAttribute('action',
                    <?= json_encode(base_url('mutasi_stok/batal-terima/')) ?> + btn.dataset.id);
            });
        });

        alasan.addEventListener('input', function () {
            alasan.setCustomValidity('');
        });

        formBatal.addEventListener('submit', function (e) {
            if (alasan.value.trim() !== '') { return; }

            e.preventDefault();
            alasan.setCustomValidity('Alasan pembatalan wajib diisi.');
            alasan.reportValidity();
            alasan.focus();
        });
    }
})();
</script>