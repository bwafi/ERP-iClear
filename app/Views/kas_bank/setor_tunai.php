<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
/**
 * SETOR TUNAI — KAS unit -> rekening bank.
 *
 * Berbeda dengan Transfer Internal: di sini satu kaki pastinya laci kas unit,
 * jadi posisi/entitlement unit ikut bergerak. Angka di halaman ini dikirim
 * controller dari KasBankCutoffService; view tidak menghitung apa pun.
 *
 * @var array<string, mixed> $akun_kas
 * @var array<string, mixed> $akun_bank
 * @var array<string, mixed> $preview
 * @var array<string, mixed> $input
 */
$rp        = static fn ($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap   = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}

$unitKasTanpaCutoff = [];
$saldoKasTerpilih   = null;
$potongRupiah       = static fn ($n) => number_format((float) $n, 0, ',', '.');
$inputNominal       = (int) ($input['nominal'] ?? 0);
?>

<?php if (($bisa_pilih_unit ?? false)) : ?>
    <form class="kb-card mb-4 p-3" method="get" action="<?= base_url('kas_bank/setor-tunai') ?>">
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
                <span>Setor tunai memindahkan uang dari laci kas unit ke rekening bank.
                    Otomatis tercatat sebagai <strong>perpindahan saldo</strong>, bukan pemasukan —
                    jadi tidak memengaruhi laba/rugi.</span>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php /* ---------- Cut-off belum lengkap ---------- */ ?>
<?php if (! empty($cutoff_info['unit_tanpa_closing'])) : ?>
    <div class="kb-banner is-danger mb-4">
        <div class="kb-banner-icon text-warning">
            <iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon>
        </div>
        <div class="kb-banner-content">
            <strong>Cut-off kas belum lengkap untuk sebagian unit</strong>
            <div class="mt-1">
                Unit berikut belum punya closing kas pada tanggal
                <span class="kb-mono"><?= esc($cutoff_info['tanggal_cutoff']) ?></span>:
                <?php foreach ($cutoff_info['unit_tanpa_closing'] as $namaUnit) : ?>
                    <span class="kb-badge kb-badge-amber ms-1"><?= esc($namaUnit) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="mt-2">
                Saldo laci unit-unit ini <strong>belum bisa dipakai</strong> sebagai acuan setor.
                Angka <span class="kb-mono">0</span> tidak boleh dibaca sebagai "laci kosong" —
                yang ada berarti belum ada data closing.
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- ========================= FORM ========================= -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="kb-card h-100">
            <div class="kb-card-header bg-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <div class="kb-step-badge bg-success">
                        <iconify-icon icon="bi:box-arrow-in-down"></iconify-icon>
                    </div>
                    <div>
                        <h6 class="kb-card-title mb-0">Setor Tunai</h6>
                        <span class="kb-card-sub text-muted">Kas unit &rarr; rekening bank</span>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <form method="post" action="<?= base_url('kas_bank/setor-tunai') ?>" id="form-setor">
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
                        <label class="kb-label mb-1">Dari Akun Kas <span class="kb-req">*</span></label>
                        <select name="akun_kas_id" class="form-select form-select-sm kb-select" required>
                            <option value="">— Pilih laci kas unit —</option>
                            <?php foreach (($akun_kas ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"
                                    <?= (int) ($input['akun_kas_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                    <?= esc(($unitMap[(int) $a->unit_id] ?? 'Unit') . ' – ' . $a->nama_akun) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kb-hint mt-1">Hanya rekening laci kas milik unit terpilih.</div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Ke Rekening Bank <span class="kb-req">*</span></label>
                        <select name="akun_bank_id" class="form-select form-select-sm kb-select" required>
                            <option value="">— Pilih rekening bank tujuan —</option>
                            <?php foreach (($akun_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"
                                    <?= (int) ($input['akun_bank_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                                    <?= esc($a->nama_akun) ?><?= (int) ($a->is_shared ?? 0) === 1 ? ' — Shared Account' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="kb-hint mt-1">
                            <span class="kb-badge kb-badge-blue">Shared Account</span>
                            berarti rekening dipakai bersama beberapa unit. Dana setor ini
                            menjadi hak unit di atas, bukan milik satu unit.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Tanggal Transaksi <span class="kb-req">*</span></label>
                        <input type="date" name="tanggal" class="form-control form-control-sm kb-input" required
                            min="<?= esc($cutoff_info['tanggal_mulai'] ?? '') ?>"
                            value="<?= esc($input['tanggal'] ?? date('Y-m-d')) ?>">
                        <div class="kb-hint mt-1">
                            Minimal <span class="kb-mono"><?= esc($cutoff_info['tanggal_mulai'] ?? '') ?></span>
                            (periode operasional baru). Transaksi sebelum tanggal ini tidak bisa
                            dicatat di ledger periode baru.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Nominal Setor (Rp) <span class="kb-req">*</span></label>
                        <input type="number" name="nominal" class="form-control form-control-sm kb-input kb-num"
                            min="1" step="1" required placeholder="cth: 5000000"
                            value="<?= $inputNominal > 0 ? $inputNominal : '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm kb-input"
                            placeholder="cth: setor kas harian ke BCA"
                            value="<?= esc($input['keterangan'] ?? '') ?>">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold d-flex align-items-center justify-content-center gap-1">
                            <iconify-icon icon="bi:eye"></iconify-icon>
                            Tampilkan Pratinjau
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm text-white fw-semibold d-flex align-items-center justify-content-center gap-1"
                            formaction="<?= base_url('kas_bank/setor-tunai/save') ?>"
                            <?= ($preview !== null && ! $preview['bisa_submit']) ? 'disabled' : '' ?>>
                            <iconify-icon icon="bi:check2-circle"></iconify-icon>
                            Simpan Setor Tunai
                        </button>
                    </div>
                    <div class="kb-hint mt-2 text-center">
                        Tekan <strong>Tampilkan Pratinjau</strong> dulu. Tombol simpan menolak
                        transaksi yang datanya belum lengkap.
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
                    <h5 class="kb-card-title mb-0">Dampak Saldo</h5>
                    <span class="kb-card-sub text-muted">Perkiraan sebelum transaksi disimpan.</span>
                </div>
            </div>
            <div class="kb-card-body">
                <?= $this->include('kas_bank/_pratinjau_tunai') ?>
            </div>
        </div>

        <div class="kb-card">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Riwayat Setor Tunai</h5>
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
                                    Belum ada riwayat setor tunai.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($transaksi ?? []) as $t) : ?>
                            <tr>
                                <td><span class="kb-badge kb-badge-blue fw-semibold kb-mono"><?= esc($t['transfer_ref']) ?></span></td>
                                <td class="text-secondary"><?= esc($t['tanggal']) ?></td>
                                <td><?= esc($t['unit_nama']) ?></td>
                                <td>
                                    <span class="kb-badge kb-badge-green">SETOR TUNAI</span>
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