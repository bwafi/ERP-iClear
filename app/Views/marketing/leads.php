<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Lead Marketing</h4>
            <small class="text-muted">Lead masuk → Follow Up → Won (tertaut Customer) / Lost. Customer WON jadi dasar KPI Customer, Conversion, dan Omzet Marketing.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active">Lead Marketing</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (['NEW', 'FOLLOW_UP', 'WON', 'LOST'] as $st) : ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        <div class="ms-auto">
            <a href="<?= base_url('marketing') ?>" class="btn btn-light">Kembali ke Marketing KPI</a>
        </div>
    </div>
</div>

<?php if ($canWrite) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">Tambah Lead</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('marketing/leads/simpan') ?>" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Nama / Customer</label>
                <input type="text" name="nama" class="form-control" placeholder="Nama lead" required>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1">No HP</label>
                <input type="text" name="no_hp" class="form-control" inputmode="numeric">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Channel / Source</label>
                <select name="source_id" class="form-select">
                    <option value="">-</option>
                    <?php foreach ($sources as $s) : ?>
                        <option value="<?= $s->id ?>"><?= esc($s->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1">Tipe</label>
                <select name="ads_organic" class="form-select">
                    <option value="ORGANIC">Organic</option>
                    <option value="ADS">Ads</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1">CS</label>
                <input type="text" name="cs" class="form-control" placeholder="CS">
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select">
                    <option>NEW</option>
                    <option>FOLLOW_UP</option>
                    <option>WON</option>
                    <option>LOST</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success w-100">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Data Lead — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($rows)) : ?>
            <div class="text-muted small">Belum ada lead untuk periode ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>No HP</th>
                            <th>Source</th>
                            <th class="text-center">Tipe</th>
                            <th>CS</th>
                            <th class="text-center">Status</th>
                            <th>Customer</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $lead) :
                            $src = isset($sourceMap[(int)$lead->source_id]) ? $sourceMap[(int)$lead->source_id] : null;
                        ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($lead->tanggal)) ?></td>
                                <td class="fw-semibold"><?= esc($lead->nama) ?></td>
                                <td><?= esc($lead->no_hp) ?></td>
                                <td><?= $src ? esc($src->name) : '-' ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $lead->ads_organic === 'ADS' ? 'text-bg-primary' : 'text-bg-light text-dark border' ?>"><?= $lead->ads_organic ?></span>
                                </td>
                                <td><?= esc($lead->cs) ?></td>
                                <td class="text-center">
                                    <?php
                                    if ($lead->status === 'WON') {
                                        $badge = 'text-bg-success';
                                    } elseif ($lead->status === 'LOST') {
                                        $badge = 'text-bg-danger';
                                    } elseif ($lead->status === 'FOLLOW_UP') {
                                        $badge = 'text-bg-warning';
                                    } else {
                                        $badge = 'text-bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= $lead->status ?></span>
                                </td>
                                <td><?= esc($lead->customer_id ?: '-') ?></td>
                                <td class="text-center" style="min-width:340px;">
                                    <?php if ($canWrite && in_array($lead->status, ['NEW', 'FOLLOW_UP'], true)) : ?>
                                        <div class="d-flex flex-wrap gap-1 align-items-center justify-content-end">
                                            <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0">
                                                <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                <input type="hidden" name="status" value="FOLLOW_UP">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Follow Up</button>
                                            </form>
                                            <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0" onsubmit="return confirm('Tandai lead status LOST?');">
                                                <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                <input type="hidden" name="status" value="LOST">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Lost</button>
                                            </form>
                                            <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0 d-flex gap-1 align-items-center">
                                                <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                <input type="hidden" name="status" value="WON">
                                                <select name="customer_id" class="form-select form-select-sm" style="width:auto; min-width:170px;" required>
                                                    <option value="">Pilih customer hasil conversion...</option>
                                                    <?php foreach ($customers as $cust) : ?>
                                                        <option value="<?= $cust->id_pelanggan ?>"><?= esc($cust->nama) ?> (<?= esc($cust->no_hp) ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="date" name="tanggal_won" class="form-control form-control-sm" style="width:auto;" value="<?= date('Y-m-d') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Won</button>
                                            </form>
                                        </div>
                                    <?php elseif ($lead->status === 'WON') : ?>
                                        <span class="text-success small">Won → Customer #<?= (int)$lead->customer_id ?></span>
                                    <?php elseif ($lead->status === 'LOST') : ?>
                                        <span class="text-muted small"><i class="bi bi-x-circle"></i> Lost</span>
                                    <?php else : ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                    <?php if ($canWrite) : ?>
                                        <form method="post" action="<?= base_url('marketing/leads/hapus') ?>" class="d-inline m-0" onsubmit="return confirm('Hapus lead ini?');">
                                            <input type="hidden" name="id" value="<?= $lead->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>