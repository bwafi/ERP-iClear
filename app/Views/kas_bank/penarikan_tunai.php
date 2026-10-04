<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
/**
 * PENARIKAN TUNAI — rekening bank -> laci kas unit.
 *
 * Posisi unit di rekening bank adalah batas penarikan, BUKAN saldo fisik
 * rekening. Kalau rekening bersama punya saldo besar yang sebagian besar
 * masih LEGACY / belum dialokasikan, unit tidak boleh menarik sampai sebesar
 * itu hanya karena "uangnya ada di rekening".
 *
 * @var array<string, mixed> $akun_bank
 * @var array<string, mixed> $akun_kas
 * @var array<string, mixed> $preview
 * @var array<string, mixed> $input
 */
$rp      = static fn ($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}

$inputNominal = (int) ($input['nominal'] ?? 0);
?>

<?php if (($bisa_pilih_unit ?? false)) : ?>
    <form class="kb-card mb-4 p-3" method="get" action="<?= base_url('kas_bank/penarikan-tunai') ?>">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-4">
                <label class="kb-label mb-1">Unit Transaksi</label>
                <select name="unit_id" class="form-select form-select-sm kb-select" onchange="this.form.submit()">
                    <?php foreach (($unit ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8 text-muted d-flex align-items-center gap-2">
                <iconify-icon icon="bi:info-circle-fill" class="text-info kb-ico flex-shrink-0"></iconify-icon>
                <span>Penarikan tunai memindahkan uang dari rekening bank ke laci kas unit.
                    Batas penarikan adalah <strong>posisi unit di rekening itu</strong>, bukan
                    saldo seluruh rekening.</span>
            </div>
        </div>
    </form>
<?php endif; ?>

<div class="row g-4">
    <!-- ========================= FORM ========================= -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="kb-card h-100">
            <div class="kb-card-header bg-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <div class="kb-step-badge bg-warning">
                        <iconify-icon icon="bi:box-arrow-up"></iconify-icon>
                    </div>
                    <div>
                        <h6 class="kb-card-title mb-0">Penarikan Tunai</h6>
                        <span class="kb-card-sub text-muted">Rekening bank &rarr; kas unit</span>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <form method="post" action="<?= base_url('kas_bank/penarikan-tunai') ?>" id="form-penarikan">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submit_token" value="<?= esc($submit_token ?? '') ?>">
                    <input type="hidden" name="operation_key" value="<?= esc($operation_key ?? '') ?>">

                    <?php if (($bisa_pilih_unit ?? false)) : ?>
                        <div class="mb-3">
                            <label class="kb-label mb-1">Unit Cabang</label>
                            <input type="text" class="form-control form-control-sm kb-input bg-tertiary"
                                value="<?= esc($unitMap[(int) ($unit_terpilih ?? 0)] ?? '—') ?>" readonly>
                            <input type="hidden" name="unit_id" value="<?= (int) ($unit_terpilih ?? 0) ?>">
                        </div>
                    <?php else : ?>
                        <input type="hidden" name="unit_id" value="<?= (int) session('ID_UNIT') ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Dari Rekening Bank <span class="kb-req">*</span></label>
                        <select name="akun_bank_id" class="form-select form-select-sm kb-select" required>
                            <option value="">— Pilih rekening bank sumber —</option>
                            <?php foreach (($akun_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"
                                    <?= (int) ($input['akun_bank_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                    <?= esc($a->nama_akun) ?><?= (int) ($a->is_shared ?? 0) === 1 ? ' — Shared Account' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kb-hint mt-1">
                            Hanya rekening yang punya <strong>hak unit ini</strong> yang bisa dipilih
                            sebagai sumber penarikan.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Ke Akun Kas <span class="kb-req">*</span></label>
                        <select name="akun_kas_id" class="form-select form-select-sm kb-select" required>
                            <option value="">— Pilih laci kas tujuan —</option>
                            <?php foreach (($akun_kas ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"
                                    <?= (int) ($input['akun_kas_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                    <?= esc(($unitMap[(int) $a->unit_id] ?? 'Unit') . ' – ' . $a->nama_akun) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kb-hint mt-1">Harus laci kas milik unit yang sama.</div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Tanggal Transaksi <span class="kb-req">*</span></label>
                        <input type="date" name="tanggal" class="form-control form-control-sm kb-input" required
                            min="<?= esc($cutoff_info['tanggal_mulai'] ?? '') ?>"
                            value="<?= esc($input['tanggal'] ?? date('Y-m-d')) ?>">
                        <div class="kb-hint mt-1">
                            Minimal <span class="kb-mono"><?= esc($cutoff_info['tanggal_mulai'] ?? '') ?></span>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Nominal Penarikan (Rp) <span class="kb-req">*</span></label>
                        <input type="number" name="nominal" class="form-control form-control-sm kb-input kb-num"
                            min="1" step="1" required placeholder="cth: 2000000"
                            value="<?= $inputNominal > 0 ? $inputNominal : '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm kb-input"
                            placeholder="cth: tarik kas untuk operasional"
                            value="<?= esc($input['keterangan'] ?? '') ?>">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-outline-warning btn-sm fw-semibold d-flex align-items-center justify-content-center gap-1">
                            <iconify-icon icon="bi:eye"></iconify-icon>
                            Tampilkan Pratinjau
                        </button>
                        <button type="submit" class="btn btn-warning btn-sm fw-semibold d-flex align-items-center justify-content-center gap-1"
                            formaction="<?= base_url('kas_bank/penarikan-tunai/save') ?>"
                            <?= ($preview !== null && ! $preview['bisa_submit']) ? 'disabled' : '' ?>>
                            <iconify-icon icon="bi:check2-circle"></iconify-icon>
                            Simpan Penarikan Tunai
                        </button>
                    </div>
                    <div class="kb-hint mt-2 text-center">
                        Tekan <strong>Tampilkan Pratinjau</strong> dulu untuk melihat sisa
                        entitlement unit di rekening tersebut.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================= PRATINJAU + RIWAYAT ========================= -->
    <div class="col-12 col-lg-7 col-xl-8">
        <div class="kb-card mb-4">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Dampak Saldo &amp; Entitlement</h5>
                    <span class="kb-card-sub text-muted">
                        Penarikan hanya boleh insofar posisi unit pada rekening ini.
                    </span>
                </div>
            </div>
            <div class="kb-card-body">
                <?= $this->include('kas_bank/_pratinjau_tunai') ?>
            </div>
        </div>

        <div class="kb-card">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Riwayat Penarikan Tunai</h5>
                    <span class="kb-card-sub text-muted">Satu baris = satu operasi (dua kaki movement).</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Referensi</th>
                            <th>Tanggal</th>
                            <th>Unit</th>
                            <th>Jenis</th>
                            <th>Kaki Movement</th>
                            <th class="text-end">Nominal</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksi)) : ?>
                            <tr>
                                <td colspan="7" class="kb-empty">
                                    <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
                                    Belum ada riwayat penarikan tunai.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($transaksi ?? []) as $t) : ?>
                            <tr>
                                <td><span class="kb-badge kb-badge-amber fw-semibold kb-mono"><?= esc($t['transfer_ref']) ?></span></td>
                                <td class="text-secondary"><?= esc($t['tanggal']) ?></td>
                                <td><?= esc($t['unit_nama']) ?></td>
                                <td>
                                    <span class="kb-badge kb-badge-amber">PENARIKAN TUNAI</span>
                                </td>
                                <td class="kb-meta">
                                    <?php foreach ($t['legs'] as $leg) : ?>
                                        <div>
                                            <?= $leg['arah'] === 'KELUAR' ? '−' : '+' ?>
                                            <?= esc($leg['nama']) ?>
                                            <?php if (! empty($leg['is_shared'])) : ?>
                                                <span class="kb-badge kb-badge-blue ms-1">Shared</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end kb-num fw-bold"><?= $rp($t['jumlah']) ?></td>
                                <td class="kb-meta"><?= esc($t['keterangan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>