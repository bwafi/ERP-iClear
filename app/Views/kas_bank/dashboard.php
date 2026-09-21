<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Dashboard Kas &amp; Bank</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$f = $filter ?? [];
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a->nama_akun;
}
$unitDipilih = (int) ($unit_terpilih ?? 0) > 0;
$jenisLabels = [
    'PEMASUKAN' => 'Pemasukan',
    'PENGELUARAN' => 'Pengeluaran',
    'TRANSFER_INTERNAL' => 'Transfer Internal',
    'PEMBAYARAN_ANTAR_UNIT' => 'Pembayaran Antar Unit',
];
?>

<form class="card mb-4" method="get" action="<?= base_url('kas_bank') ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <?php if (($bisa_pilih_unit ?? false)) : ?>
                <div class="col-md-2">
                    <label class="form-label mb-1">Unit</label>
                    <select name="unit_id" class="form-select form-select-sm">
                        <option value="">Semua Unit</option>
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label mb-1">Dari Tanggal</label>
                <input type="date" name="tanggal_awal" class="form-control form-control-sm" value="<?= esc($f['tanggal_awal'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Sampai Tanggal</label>
                <input type="date" name="tanggal_akhir" class="form-control form-control-sm" value="<?= esc($f['tanggal_akhir'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Akun</label>
                <select name="akun_id" class="form-select form-select-sm">
                    <option value="">Semua Akun</option>
                    <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                        <option value="<?= (int) $a->idakun_kas_bank ?>" <?= ($f['akun_id'] ?? 0) == $a->idakun_kas_bank ? 'selected' : '' ?>>
                            <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : 'Fisik – ') . $a->nama_akun) ?>
                            <?= $a->tipe === 'BANK' && empty($a->unit_id) ? ' (Bersama)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border bg-light-secondary overflow-hidden">
            <div class="card-body p-4">
                <p class="fs-3 fw-semibold text-dark mb-2">Total Kas <?= $unitDipilih ? '(unit terpilih)' : '' ?></p>
                <h3 class="fw-semibold mb-0 text-primary"><?= $rp($total_kas ?? 0) ?></h3>
                <?php if ($unitDipilih) : ?><small class="text-muted">Fisik semua unit: <?= $rp($total_fisik_kas ?? 0) ?></small><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border bg-light-warning overflow-hidden">
            <div class="card-body p-4">
                <p class="fs-3 fw-semibold text-dark mb-2">Total Bank <?= $unitDipilih ? '(unit terpilih)' : '' ?></p>
                <h3 class="fw-semibold mb-0 text-warning"><?= $rp($total_bank ?? 0) ?></h3>
                <?php if ($unitDipilih) : ?><small class="text-muted">Fisik semua unit: <?= $rp($total_fisik_bank ?? 0) ?></small><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border bg-light-success overflow-hidden">
            <div class="card-body p-4">
                <p class="fs-3 fw-semibold text-dark mb-2">Total Kas &amp; Bank <?= $unitDipilih ? '(unit terpilih)' : '' ?></p>
                <h3 class="fw-semibold mb-0 text-success"><?= $rp($total_semua ?? 0) ?></h3>
                <?php if ($unitDipilih) : ?><small class="text-muted">Fisik semua unit: <?= $rp($total_fisik_semua ?? 0) ?></small><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border bg-light-primary overflow-hidden">
            <div class="card-body p-4">
                <p class="fs-3 fw-semibold text-dark mb-2">Net Cash Flow <?= esc($f['tanggal_awal'] ?? '') ?: date('d M Y') ?></p>
                <h3 class="fw-semibold mb-0 <?= ($net_cash_flow ?? 0) < 0 ? 'text-danger' : 'text-primary' ?>"><?= $rp($net_cash_flow ?? 0) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($warning_alokasi)) : ?>
    <div class="alert alert-warning py-2">
        <strong>Perhatian:</strong> total alokasi saldo awal melebihi saldo fisik pada rekening:
        <?php foreach ($warning_alokasi as $ka) : ?>
            <span class="badge bg-danger-subtle text-danger ms-1"><?= esc($akunMap[$ka] ?? '#' . $ka) ?></span>
        <?php endforeach; ?>
        (atur ulang alokasi di Master Akun).
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Saldo per Rekening Fisik <?= $unitDipilih ? '(termasuk alokasi unit terpilih)' : '' ?></h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th class="text-end">Saldo Fisik</th>
                            <?php if ($unitDipilih) : ?>
                                <th class="text-end">Saldo Unit</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($akun_kas_bank)) : ?>
                            <tr><td colspan="<?= $unitDipilih ? 5 : 4 ?>" class="text-center text-muted">Belum ada akun kas/bank. Tambahkan lewat menu Master Akun &amp; Saldo Awal.</td></tr>
                        <?php endif; ?>
                        <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                            <?php $shared = (int) ($a->is_shared ?? 0) === 1 || ($a->tipe === 'BANK' && empty($a->unit_id)); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($a->nama_akun) ?>
                                        <?php if ($shared) : ?>
                                            <span class="badge bg-secondary">Bersama</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= esc($unitMap[(int) $a->unit_id] ?? 'Fisik lintas unit') ?><?= $a->bank_idbank ? ' • ' . esc($a->bank_idbank) : '' ?></small>
                                </td>
                                <td><span class="badge bg-<?= $a->tipe === 'KAS' ? 'primary-subtle text-primary' : 'warning-subtle text-warning' ?>"><?= esc($a->tipe) ?></span></td>
                                <td><span class="badge bg-<?= $a->status === 'aktif' ? 'success-subtle text-success' : 'danger-subtle text-danger' ?>"><?= esc($a->status) ?></span></td>
                                <td class="text-end fw-semibold"><?= $rp($saldo_fisik_per_akun[(int) $a->idakun_kas_bank] ?? 0) ?></td>
                                <?php if ($unitDipilih) : ?>
                                    <td class="text-end"><?= $rp($saldo_unit_per_akun[(int) $a->idakun_kas_bank] ?? 0) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Pergerakan Uang per Jenis</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Jenis</th><th class="text-end">Jumlah (periode)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jenisLabels as $k => $label) : ?>
                            <tr>
                                <td><?= $label ?></td>
                                <td class="text-end fw-semibold <?= in_array($k, ['PENGELUARAN'], true) ? 'text-danger' : 'text-success' ?>">
                                    <?= $rp($ringkasan[$k] ?? 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <small class="text-muted">
                    Net Cash Flow = Pemasukan − Pengeluaran. Transfer internal, pembayaran antar unit, dan saldo awal tidak termasuk (bukan arus kas operasional).
                    Sejak Finance cut-off, hanya transaksi pada/setelah <?= esc(\App\Services\Finance\FinanceScopeService::cutoffDate()) ?> yang dihitung
                    (baris "kas awal" & histori sebelum cut-off adalah legacy, tidak ikut).
                </small>
            </div>
        </div>
    </div>
</div>