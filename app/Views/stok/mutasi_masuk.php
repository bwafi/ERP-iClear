<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$isLintas = in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);
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
        <h5 class="mb-0"><?= $isLintas ? 'Semua Mutasi Masuk' : 'Mutasi Masuk Untuk Unit Saya' ?></h5>
    </div>
    <div class="card-body table-responsive">
        <?php if (empty($items)) : ?>
            <p class="text-muted mb-0">Belum ada mutasi masuk.</p>
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
</div>

<div class="modal fade" id="modalTerimaMutasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formTerimaMutasi" method="post" class="modal-content">
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
})();
</script>