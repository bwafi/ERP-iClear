<?php
// Nama unit untuk kolom audit; petakan sekali saja.
$unitNama = [];
foreach ($unitList as $u) {
    $unitNama[(int)$u->idunit] = $u->NAMA_UNIT;
}
?>
<div class="card dm-page-header shadow-none border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="dm-page-heading">
            <span class="dm-page-kicker">Digital Marketing</span>
            <h1 class="dm-page-title">Campaign Digital Marketing</h1>
            <p class="dm-page-description">Campaign ditarik dari Meta. Yang bisa diubah di sini hanya unit pembebanan biaya — biaya iklan dicatat ke unit tersebut.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= base_url('marketing') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Campaign</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('success') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom">
        <h5 class="mb-0 fw-semibold">Daftar Campaign Digital Marketing</h5>
        <small class="text-muted">Unit ditandai <em>otomatis</em> bila hasil tebakan nama campaign, <em>manual</em> bila sudah dikoreksi.</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:megaphone-bold" class="text-muted fs-1 mb-2"></iconify-icon>
                <p class="text-muted mb-1">Belum ada campaign.</p>
                <small class="text-muted">Jalankan tarikan Meta (menu Performa Ads → Tarik dari Meta) untuk mengisinya.</small>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th class="ps-3 text-start">Nama Campaign</th>
                            <th class="text-center">Status</th>
                            <th class="text-start">Unit</th>
                            <th class="text-start">Campaign Meta</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-center pe-3">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $statusClass = [
                                'draft'  => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                'active' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                'done'   => 'bg-success-subtle text-success border border-success-subtle',
                            ];
                            $sc = $statusClass[$row->status] ?? $statusClass['draft'];
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-dark"><?= esc($row->nama) ?></td>
                                <td class="text-center">
                                    <span class="badge px-2 py-1 <?= $sc ?>">
                                        <?= esc($statusLabels[$row->status] ?? $row->status) ?>
                                    </span>
                                </td>
                                <td class="text-start">
                                    <?php if ($canWrite) : ?>
                                        <form method="post" action="<?= base_url('marketing/campaign/simpan') ?>" class="d-flex gap-1 align-items-center">
                                            <input type="hidden" name="id" value="<?= (int)$row->id ?>">
                                            <select name="unit_id" class="form-select form-select-sm" style="min-width: 11rem;">
                                                <option value="">-</option>
                                                <?php foreach ($unitList as $u) : ?>
                                                    <option value="<?= (int)$u->idunit ?>" <?= (int)$row->unit_id === (int)$u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Simpan unit">
                                                <iconify-icon icon="solar:diskette-bold" width="15" height="15"></iconify-icon>
                                            </button>
                                        </form>
                                    <?php else : ?>
                                        <?= esc($unitNama[(int)$row->unit_id] ?? '-') ?>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1">
                                        <?= ($row->unit_sumber ?? 'auto') === 'manual' ? 'manual' : 'otomatis' ?>
                                    </small>
                                </td>
                                <td class="text-start text-nowrap font-monospace small">
                                    <?php if (!empty($row->meta_campaign_id)) : ?>
                                        <?= esc($row->meta_campaign_id) ?>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-center text-nowrap pe-3">
                                        <form method="post" action="<?= base_url('marketing/campaign/hapus') ?>" class="d-inline"
                                            onsubmit="return confirm('Hapus campaign ini?');">
                                            <input type="hidden" name="id" value="<?= (int)$row->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <iconify-icon icon="solar:trash-bin-trash-bold" width="15" height="15"></iconify-icon>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
