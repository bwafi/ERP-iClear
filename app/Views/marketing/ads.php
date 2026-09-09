<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Biaya Iklan (Ads Cost)</h4>
            <small class="text-muted">Input biaya iklan per periode + channel/campaign. Dipakai menghitung CPL &amp; ROAS (hanya biaya = paid leads).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active">Biaya Iklan</li>
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
    <div class="card-body">
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
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="<?= base_url('marketing') ?>" class="btn btn-light">Kembali ke Marketing KPI</a>
            </div>
        </form>
    </div>
</div>

<?php if ($canWrite) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">Tambah Biaya Iklan</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('marketing/ads/simpan') ?>" class="row g-2 align-items-end">
            <div class="col-md-1">
                <label class="form-label mb-1">Bulan</label>
                <select name="period_month" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $i === $bulan ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1">Tahun</label>
                <input type="number" name="period_year" class="form-control" value="<?= $tahun ?>" min="2000" max="2100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Channel</label>
                <select name="channel_id" class="form-select">
                    <option value="">Semua / Umum</option>
                    <?php foreach ($channels as $ch) : ?>
                        <option value="<?= $ch->id ?>"><?= esc($ch->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Campaign</label>
                <input type="text" name="campaign" class="form-control" placeholder="mis. Promo Ramadhan">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Biaya (Rp)</label>
                <input type="text" name="amount" class="form-control angka-ribuan" inputmode="numeric" placeholder="500.000" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Catatan</label>
                <input type="text" name="note" class="form-control">
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
        <h5 class="mb-0">Data Biaya Iklan — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
    </div>
    <div class="card-body">
        <?php $channelName = []; ?>
        <?php foreach ($channels as $ch) : ?>
            <?php $channelName[(int)$ch->id] = $ch->name; ?>
        <?php endforeach; ?>
        <?php if (empty($rows)) : ?>
            <div class="text-muted small">Belum ada biaya iklan untuk periode ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Periode</th>
                            <th>Channel</th>
                            <th>Campaign</th>
                            <th class="text-end">Biaya</th>
                            <th>Catatan</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total = 0; ?>
                        <?php foreach ($rows as $row) : $total += (float)$row->amount; ?>
                            <tr>
                                <td><?= date('Y-m', mktime(0, 0, 0, $row->period_month, 1, $row->period_year)) ?></td>
                                <td><?= isset($channelName[(int)$row->channel_id]) ? esc($channelName[(int)$row->channel_id]) : 'Umum' ?></td>
                                <td><?= esc($row->campaign) ?></td>
                                <td class="text-end fw-semibold">Rp <?= number_format($row->amount, 0, ',', '.') ?></td>
                                <td><small class="text-muted"><?= esc($row->note) ?></small></td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-center">
                                        <form method="post" action="<?= base_url('marketing/ads/hapus') ?>" class="m-0" onsubmit="return confirm('Hapus biaya iklan ini?');">
                                            <input type="hidden" name="id" value="<?= $row->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">Total Biaya Iklan Bulan Ini</th>
                            <th class="text-end fw-bold">Rp <?= number_format($total, 0, ',', '.') ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function fmtRibuan(v) {
        while (/(\d+)(\d{3})/.test(v)) v = v.replace(/(\d+)(\d{3})/, '$1.$2');
        return v;
    }
    var amt = document.querySelector('input[name="amount"]');
    if (amt) {
        amt.addEventListener('input', function() {
            var raw = this.value.replace(/\D/g, '');
            this.value = raw !== '' ? fmtRibuan(raw) : '';
        });
        amt.form.addEventListener('submit', function() {
            amt.value = amt.value.replace(/\./g, '');
        });
    }
</script>