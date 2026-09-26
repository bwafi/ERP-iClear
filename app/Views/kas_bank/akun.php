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
$bankMap = [];
foreach (($bank ?? []) as $b) {
    $bankMap[(string) $b->idbank] = $b;
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
                    <label class="form-label mb-1">Unit (filter otomatis)</label>
                    <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Unit</option>
                        <?php foreach (($unit ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-text">BANK = rekening fisik (bisa dipakai lintas unit, tandai <strong>Rekening Bersama</strong>). KAS = fisik per unit.</div>
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
                        <label class="form-label mb-1">Unit <small class="text-muted">(wajib utk KAS)</small></label>
                        <select name="unit_id" id="unit_id" class="form-select form-select-sm">
                            <option value="">Tidak ada (rekening fisik lintas unit)</option>
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
                        <input type="text" name="nama_akun" id="nama_akun" class="form-control form-control-sm" placeholder="cth: Kas Besar, BCA SABRINA..." required>
                    </div>
                    <div class="mb-2" id="bank-select-wrap">
                        <label class="form-label mb-1">Master Bank</label>
                        <select name="bank_idbank" id="bank_idbank" class="form-select form-select-sm">
                            <option value="">Pilih Bank</option>
                            <?php foreach (($bank ?? []) as $b) : ?>
                                <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank . ' - ' . $b->atas_nama . ' (' . $b->norek . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2" id="shared-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_shared" id="is_shared" value="1">
                            <label class="form-check-label" for="is_shared">Rekening Bersama (dipakai &gt; 1 unit)</label>
                        </div>
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

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Saldo Awal Akun (Fisik)</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('kas_bank/saldo-awal/save') ?>">
                    <div class="mb-2">
                        <label class="form-label mb-1">Akun</label>
                        <select name="akun_kas_bank_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Akun</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun) ?></option>
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
                <div class="form-text mt-2">Satu saldo awal per rekening fisik. Bagikan ke unit di kartu <strong>Alokasi Saldo Unit</strong>.</div>
            </div>
        </div>

        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Alokasi Saldo Awal per Unit</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= base_url('kas_bank/saldo-alokasi/save') ?>">
                    <div class="mb-2">
                        <label class="form-label mb-1">Rekening Bank</label>
                        <select name="akun_kas_bank_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Rekening</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <?php if ($a->tipe === 'BANK') : ?>
                                    <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($a->nama_akun) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Unit</label>
                        <select name="unit_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Unit</option>
                            <?php foreach (($unit ?? []) as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Nominal Alokasi</label>
                        <input type="text" name="nominal" class="form-control form-control-sm rupiah" placeholder="cth: 5.000.000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-info w-100">Simpan Alokasi</button>
                </form>
                <div class="form-text mt-2">Alokasi tidak mengubah saldo fisik; total alokasi per rekening tidak boleh melebihi saldo fisiknya.</div>
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
                            <?php
                            $fisik = $saldo_fisik_akun[(int) $a->idakun_kas_bank] ?? 0;
                            $alokasiAkun = $alokasi[(int) $a->idakun_kas_bank] ?? [];
                            $totalAlokasi = array_sum(array_map(fn($al) => (int) $al->nominal, $alokasiAkun));
                            $shared = (int) ($a->is_shared ?? 0) === 1 || ($a->tipe === 'BANK' && empty($a->unit_id));
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($a->nama_akun) ?>
                                    <?php if ($shared) : ?>
                                        <span class="badge bg-secondary" title="Rekening fisik dipakai lintas unit">Bersama</span>
                                    <?php endif; ?><br>
                                    <small class="text-muted"><?= esc($a->no_akun_coa ?? 'COA -') ?></small>
                                </td>
                                <td><?= esc($unitMap[(int) $a->unit_id] ?? 'Fisik lintas unit') ?></td>
                                <td><span class="badge bg-<?= $a->tipe === 'KAS' ? 'primary-subtle text-primary' : 'warning-subtle text-warning' ?>"><?= esc($a->tipe) ?></span></td>
                                <td>
                                    <small>
                                        <?= isset($bankMap[(string) $a->bank_idbank]) ? esc($bankMap[(string) $a->bank_idbank]->nama_bank . ' (' . $bankMap[(string) $a->bank_idbank]->norek . ')') : esc($a->bank_idbank ?? '-') ?>
                                    </small>
                                </td>
                                <td><span class="badge bg-<?= $a->status === 'aktif' ? 'success-subtle text-success' : 'danger-subtle text-danger' ?>"><?= esc($a->status) ?></span></td>
                                <td>
                                    <?= $rp($fisik) ?>
                                    <?php if ($a->tipe === 'BANK' && $totalAlokasi > 0) : ?>
                                        <br><small class="text-muted">Alokasi unit: <?= $rp($totalAlokasi) ?></small>
                                        <?php if ($totalAlokasi > $fisik) : ?>
                                            <span class="badge bg-danger-subtle text-danger">Alokasi &gt; saldo fisik</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($canInput) : ?>
                                        <button class="btn btn-sm btn-light edit-akun"
                                            data-id="<?= (int) $a->idakun_kas_bank ?>"
                                            data-unit="<?= (int) $a->unit_id ?>"
                                            data-tipe="<?= esc($a->tipe) ?>"
                                            data-nama="<?= esc($a->nama_akun) ?>"
                                            data-bank="<?= esc($a->bank_idbank ?? '') ?>"
                                            data-coa="<?= esc($a->no_akun_coa ?? '') ?>"
                                            data-status="<?= esc($a->status) ?>"
                                            data-shared="<?= $shared ? '1' : '0' ?>">Edit</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($alokasiAkun)) : ?>
                                <tr class="table-light">
                                    <td colspan="7" class="ps-4 py-1">
                                        <small class="text-muted">Alokasi saldo awal per unit:</small>
                                        <?php foreach ($alokasiAkun as $al) : ?>
                                            <span class="badge bg-info-subtle text-info me-2"><?= esc($unitMap[(int) $al->unit_id] ?? 'Unit ' . $al->unit_id) ?>: <?= $rp($al->nominal) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
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
    function toggleByTipe() {
        var tipe = document.getElementById('tipe').value;
        document.getElementById('bank-select-wrap').style.display = tipe === 'BANK' ? '' : 'none';
        document.getElementById('shared-wrap').style.display = tipe === 'BANK' ? '' : 'none';
    }
    document.querySelectorAll('.edit-akun').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('idakun_kas_bank').value = btn.dataset.id;
            document.getElementById('unit_id').value = btn.dataset.unit;
            document.getElementById('tipe').value = btn.dataset.tipe;
            document.getElementById('nama_akun').value = btn.dataset.nama;
            document.getElementById('bank_idbank').value = btn.dataset.bank;
            document.getElementById('no_akun_coa').value = btn.dataset.coa;
            document.getElementById('status').value = btn.dataset.status;
            document.getElementById('is_shared').checked = btn.dataset.shared === '1';
            toggleByTipe();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    document.getElementById('tipe').addEventListener('change', toggleByTipe);
    if (!canEdit) {
        document.getElementById('bank-select-wrap').style.display = 'none';
        document.getElementById('shared-wrap').style.display = 'none';
        document.getElementById('is_shared').disabled = true;
    }
})();
</script>