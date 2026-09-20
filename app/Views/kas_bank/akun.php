<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Master Akun Kas &amp; Bank + Saldo Awal</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active">Master Akun</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$saldoAwalMap = [];
foreach (($saldo_awal ?? []) as $s) {
    $saldoAwalMap[(int) $s->akun_kas_bank_id] = $s;
}
$canInput = $bisa_pilih_unit ?? false;
?>

<?php if (($bisa_pilih_unit ?? false)) : ?>
    <form class="card mb-4" method="get" action="<?= base_url('kas_bank/akun') ?>">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Unit</label>
                    <select name="unit_id" class="form-select form-select-sm">
                        <option value="">Semua Unit</option>
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Tambah / Ubah Akun</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('kas_bank/akun/save') ?>">
                    <input type="hidden" name="idakun_kas_bank" id="idakun_kas_bank" value="">
                    <div class="mb-2">
                        <label class="form-label mb-1">Unit</label>
                        <select name="unit_id" id="unit_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Unit</option>
                            <?php foreach (($unit ?? []) as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Tipe</label>
                        <select name="tipe" id="tipe" class="form-select form-select-sm" required>
                            <option value="KAS">Kas</option>
                            <option value="BANK">Bank</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Nama Akun</label>
                        <input type="text" name="nama_akun" id="nama_akun" class="form-control form-control-sm" placeholder="cth: Kas Besar, BCA 1234..." required>
                    </div>
                    <div class="mb-2" id="bank-select-wrap">
                        <label class="form-label mb-1">Master Bank</label>
                        <select name="bank_idbank" id="bank_idbank" class="form-select form-select-sm">
                            <option value="">Pilih Bank</option>
                            <?php foreach (($bank ?? []) as $b) : ?>
                                <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank . ' - ' . $b->atas_nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Mapping COA (opsional)</label>
                        <select name="no_akun_coa" id="no_akun_coa" class="form-select form-select-sm">
                            <option value="">Tidak ada</option>
                            <?php foreach (($no_akun ?? []) as $na) : ?>
                                <option value="<?= esc($na->no_akun) ?>"><?= esc($na->no_akun . ' - ' . $na->nama_akun) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" id="status" class="form-select form-select-sm">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Simpan Akun</button>
                </form>
            </div>
        </div>

        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Saldo Awal Akun</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('kas_bank/saldo-awal/save') ?>">
                    <div class="mb-2">
                        <label class="form-label mb-1">Akun</label>
                        <select name="akun_kas_bank_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Akun</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc(($unitMap[(int) $a->unit_id] ?? '') . ' – ' . $a->nama_akun) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Saldo Awal (harus &gt; 0)</label>
                        <input type="text" name="saldo" class="form-control form-control-sm rupiah" placeholder="cth: 10.000.000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100">Simpan Saldo Awal</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Daftar Akun Kas &amp; Bank</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0" id="tbl-akun">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Unit</th>
                            <th>Tipe</th>
                            <th>Bank</th>
                            <th>Status</th>
                            <th>Saldo Aktual</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($a->nama_akun) ?><br><small class="text-muted"><?= esc($a->no_akun_coa ?? 'COA -') ?></small></td>
                                <td><?= esc($unitMap[(int) $a->unit_id] ?? 'Unit ' . $a->unit_id) ?></td>
                                <td><span class="badge bg-<?= $a->tipe === 'KAS' ? 'primary-subtle text-primary' : 'warning-subtle text-warning' ?>"><?= esc($a->tipe) ?></span></td>
                                <td><small><?= esc($a->bank_idbank ?? '-') ?></small></td>
                                <td><span class="badge bg-<?= $a->status === 'aktif' ? 'success-subtle text-success' : 'danger-subtle text-danger' ?>"><?= esc($a->status) ?></span></td>
                                <td><?= isset($saldoAwalMap[(int) $a->idakun_kas_bank]) ? $rp($saldoAwalMap[(int) $a->idakun_kas_bank]->saldo) : '-' ?></td>
                                <td class="text-end">
                                    <?php if ($canInput) : ?>
                                        <button class="btn btn-sm btn-light edit-akun"
                                            data-id="<?= (int) $a->idakun_kas_bank ?>"
                                            data-unit="<?= (int) $a->unit_id ?>"
                                            data-tipe="<?= esc($a->tipe) ?>"
                                            data-nama="<?= esc($a->nama_akun) ?>"
                                            data-bank="<?= esc($a->bank_idbank ?? '') ?>"
                                            data-coa="<?= esc($a->no_akun_coa ?? '') ?>"
                                            data-status="<?= esc($a->status) ?>">Edit</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var canEdit = <?= $canInput ? 'true' : 'false' ?>;
    document.querySelectorAll('.edit-akun').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('idakun_kas_bank').value = btn.dataset.id;
            document.getElementById('unit_id').value = btn.dataset.unit;
            document.getElementById('tipe').value = btn.dataset.tipe;
            document.getElementById('nama_akun').value = btn.dataset.nama;
            document.getElementById('bank_idbank').value = btn.dataset.bank;
            document.getElementById('no_akun_coa').value = btn.dataset.coa;
            document.getElementById('status').value = btn.dataset.status;
            document.getElementById('bank-select-wrap').style.display = btn.dataset.tipe === 'BANK' ? '' : 'none';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    document.getElementById('tipe').addEventListener('change', function () {
        document.getElementById('bank-select-wrap').style.display = this.value === 'BANK' ? '' : 'none';
    });
    if (!canEdit) { document.getElementById('bank-select-wrap').style.display = 'none'; }
})();
</script>