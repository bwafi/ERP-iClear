<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Akun Social Media</h4>
            <small class="text-white-50">Data akun didukung scraping Bright Data. Unit mengikuti tabel Unit ERP.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('sosial/kpi') ?>">Social Media</a></li>
                <li class="breadcrumb-item active text-white">Akun</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('sukses')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= esc(session()->getFlashdata('sukses')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if ($canManage) : ?>
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#akunModal">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Akun
                </button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tableAkun">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Unit</th>
                        <th>Nama Akun</th>
                        <th>Username</th>
                        <th>External ID</th>
                        <th>Profile URL</th>
                        <th>Provider</th>
                        <th class="text-center">Status</th>
                        <?php if ($canManage) : ?><th class="text-center">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $a) : ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= ucfirst(esc($a->platform)) ?></span></td>
                            <td><?= esc($a->NAMA_UNIT ?? '-') ?></td>
                            <td><?= esc($a->account_name) ?></td>
                            <td><?= esc($a->username ?? '-') ?></td>
                            <td class="text-muted"><?= esc($a->external_account_id ?? '-') ?></td>
                            <td style="max-width:260px;"><a href="<?= esc($a->profile_url, 'attr') ?>" target="_blank" class="text-truncate d-block"><?= esc($a->profile_url) ?></a></td>
                            <td><?= esc($a->provider ?? '-') ?></td>
                            <td class="text-center">
                                <?php if ($canManage) : ?>
                                    <button type="button" class="btn btn-sm btn-<?= $a->is_active ? 'success' : 'secondary' ?> toggle-btn"
                                        data-id="<?= (int)$a->id ?>" data-active="<?= (int)$a->is_active ?>">
                                        <?= $a->is_active ? 'Aktif' : 'Nonaktif' ?>
                                    </button>
                                <?php else : ?>
                                    <span class="badge bg-<?= $a->is_active ? 'success' : 'secondary' ?>"><?= $a->is_active ? 'Aktif' : 'Nonaktif' ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($canManage) : ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn me-1" data-bs-toggle="modal" data-bs-target="#akunModal"
                                        data-id="<?= (int)$a->id ?>"
                                        data-unit="<?= (int)$a->unit_id ?>"
                                        data-platform="<?= esc($a->platform, 'attr') ?>"
                                        data-name="<?= esc($a->account_name, 'attr') ?>"
                                        data-username="<?= esc($a->username ?? '', 'attr') ?>"
                                        data-extid="<?= esc($a->external_account_id ?? '', 'attr') ?>"
                                        data-url="<?= esc($a->profile_url, 'attr') ?>"
                                        data-provider="<?= esc($a->provider ?? '', 'attr') ?>"
                                        data-active="<?= (int)$a->is_active ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form action="<?= base_url('sosial/account/hapus') ?>" method="post" class="d-inline"
                                        onsubmit="return confirm('Hapus akun ini? Posts & snapshot terkait ikut terhapus.')">
                                        <input type="hidden" name="id" value="<?= (int)$a->id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManage) : ?>
<div class="modal fade" id="akunModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('sosial/account/simpan') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="akunModalTitle">Tambah Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="f_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Platform</label>
                            <select name="platform" id="f_platform" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($platforms as $p) : ?>
                                    <option value="<?= esc($p) ?>"><?= ucfirst(esc($p)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <select name="unit_id" id="f_unit" class="form-select" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($units as $u) : ?>
                                    <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Akun</label>
                            <input type="text" name="account_name" id="f_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="f_username" class="form-control" placeholder="@username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">External Account ID</label>
                            <input type="text" name="external_account_id" id="f_extid" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Profile URL</label>
                            <input type="url" name="profile_url" id="f_url" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provider</label>
                            <input type="text" name="provider" id="f_provider" class="form-control" value="bright_data">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="f_active" class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('akunModal');
        const t = document.getElementById('akunModalTitle');

        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                t.textContent = 'Edit Akun';
                modal.querySelector('[name=id]').value = btn.dataset.id;
                modal.querySelector('#f_platform').value = btn.dataset.platform;
                modal.querySelector('#f_unit').value = btn.dataset.unit;
                modal.querySelector('#f_name').value = btn.dataset.name;
                modal.querySelector('#f_username').value = btn.dataset.username;
                modal.querySelector('#f_extid').value = btn.dataset.extid;
                modal.querySelector('#f_url').value = btn.dataset.url;
                modal.querySelector('#f_provider').value = btn.dataset.provider;
                modal.querySelector('#f_active').value = btn.dataset.active;
            });
        });
        document.querySelector('[data-bs-target="#akunModal"].btn-primary')?.addEventListener('click', function() {
            t.textContent = 'Tambah Akun';
            modal.querySelectorAll('input,select').forEach(function(f) { if (f.name !== 'provider') f.value = ''; });
            modal.querySelector('[name=provider]').value = 'bright_data';
        });

        document.querySelectorAll('.toggle-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = btn.dataset.id;
                fetch('<?= base_url('sosial/account/toggle') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id)
                })
                .then(r => r.json())
                .then(function(d) {
                    if (d.is_active === 0) {
                        btn.dataset.active = '0';
                        btn.textContent = 'Nonaktif';
                        btn.className = btn.className.replace('btn-success', 'btn-secondary');
                    } else {
                        btn.dataset.active = '1';
                        btn.textContent = 'Aktif';
                        btn.className = btn.className.replace('btn-secondary', 'btn-success');
                    }
                });
            });
        });
    });
</script>