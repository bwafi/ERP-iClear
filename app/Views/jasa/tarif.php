<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Master Tarif Jasa</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Master Jasa</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Tarif Jasa</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('sukses')): ?>
    <div class="alert alert-success" role="alert"><?= esc(session()->getFlashdata('sukses')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('gagal')): ?>
    <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('gagal')) ?></div>
<?php endif; ?>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <p class="text-muted mb-0" style="max-width: 66ch;">Jasa dijual di kasir seperti biasa, tetapi tidak memiliki
            stok dan tidak masuk stok opname atau nilai persediaan.</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambah-jasa-modal"
            style="display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:add-circle-line-duotone" width="24" height="24"
                style="margin-right: 8px;"></iconify-icon>Tambah Jasa
        </button>
    </div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="tarif_jasa_table">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kode</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Jasa</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Harga Jual</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Aksi</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jasa as $j): ?>
                    <tr>
                        <td><?= esc($j->kode_barang) ?></td>
                        <td><strong><?= esc($j->nama_barang) ?></strong></td>
                        <td><?= 'Rp ' . number_format($j->harga, 0, ',', '.') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#ubah-jasa-modal" data-id="<?= (int) $j->idbarang ?>"
                                data-nama="<?= esc($j->nama_barang) ?>" data-harga="<?= (float) $j->harga ?>">
                                Ubah
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal tambah jasa -->
<div class="modal fade" id="tambah-jasa-modal" tabindex="-1" aria-labelledby="tambah-jasa-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= base_url('tarif_jasa/simpan') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="tambah-jasa-label">Tambah Jasa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="jasa-kode">Kode</label>
                    <input type="text" class="form-control" id="jasa-kode" name="kode_barang" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="jasa-nama">Nama Jasa</label>
                    <input type="text" class="form-control" id="jasa-nama" name="nama_barang" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="jasa-harga">Harga Jual</label>
                    <input type="number" class="form-control" id="jasa-harga" name="harga" min="1" step="1" required>
                </div>
                <div class="form-text">Jasa tidak memiliki stok; harga ini yang dipakai kasir.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ubah jasa -->
<div class="modal fade" id="ubah-jasa-modal" tabindex="-1" aria-labelledby="ubah-jasa-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= base_url('tarif_jasa/ubah') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="idbarang" id="ubah-jasa-id">
            <div class="modal-header">
                <h5 class="modal-title" id="ubah-jasa-label">Ubah Jasa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="ubah-jasa-nama">Nama Jasa</label>
                    <input type="text" class="form-control" id="ubah-jasa-nama" name="nama_barang" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ubah-jasa-harga">Harga Jual</label>
                    <input type="number" class="form-control" id="ubah-jasa-harga" name="harga" min="1" step="1"
                        required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        var table = $('#tarif_jasa_table').DataTable();

        $('#ubah-jasa-modal').on('show.bs.modal', function(event) {
            const btn = $(event.relatedTarget);
            $('#ubah-jasa-id').val(btn.data('id'));
            $('#ubah-jasa-nama').val(btn.data('nama'));
            $('#ubah-jasa-harga').val(btn.data('harga'));
        });
    });
</script>
