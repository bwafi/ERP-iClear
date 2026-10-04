<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Detail <?= esc($row->kode) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </nav>
    </div>
</div>

<?php
use App\Services\Finance\HutangPiutangService;

$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$authoritative = in_array($row->sumber_tipe, HutangPiutangService::AUTHORITATIVE_SUMBER, true);
$statusClass = $row->status === 'lunas' ? 'success' : ($row->status === 'sebagian' ? 'warning' : 'secondary');
$bisaKompensasi = $can_input && $authoritative && $row->status !== 'lunas' && !empty($lawan_kompensasi);

// Pelunasan legacy: hanya hutang Outstanding bertanggal <= cut-off Finance,
// dan hanya yang BUKAN proksi (proksi ditimpa sync dari transaksi sumber).
$cutoffFinance = \App\Services\Finance\FinanceScopeService::cutoffDate();
// Pakai helper service, bukan `$row->cutoff_reason` mentah: pembatalan
// mengosongkan cutoff_reason tetapi tetap meninggalkan jejak settlement,
// jadi hanya helper yang tahu bedanya "aktif" vs "pernah dibatalkan".
$hpService     = new HutangPiutangService();
$settledLegacy = $hpService->settlementLegacyAktif($row);
$pernahLegacy  = $settledLegacy
    || (! empty($row->tanggal_settlement_legacy) && ! empty($row->legacy_dibatalkan_at));
$outstanding   = $row->status !== 'lunas' && (float) $row->sisa > 0;
$bisaLegacy    = $can_input && $outstanding && ! $settledLegacy
    && (int) $row->is_projection === 0
    && \App\Services\Finance\FinanceScopeService::tanggalStr($row->tanggal) <= $cutoffFinance;
$alasanLegacy  = 'Hanya hutang outstanding bertanggal pada atau sebelum ' . $cutoffFinance . ' yang bisa ditandai lunas legacy.';
if ($can_input && ! $settledLegacy && (int) $row->is_projection === 1) {
    $alasanLegacy = 'Posisi ini proksi dari transaksi sumber, sehingga penandaan akan ditimpa saat sinkronisasi.';
} elseif ($can_input && ! $settledLegacy && ! $outstanding) {
    $alasanLegacy = 'Hutang ini sudah lunas.';
}
?>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-<?= $row->jenis === 'hutang' ? 'danger' : 'success' ?>"><?= esc(HutangPiutangService::labelSumber($row->sumber_tipe)) ?></span>
                    <span class="badge bg-<?= $statusClass ?>"><?= esc(\App\Services\Finance\HutangPiutangService::labelStatus($row->status)) ?></span>
                </div>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Kode</td><td class="text-end fw-semibold"><?= esc($row->kode) ?></td></tr>
                    <tr><td class="text-muted">Pihak</td><td class="text-end"><?= esc($row->nama_pihak) ?></td></tr>
                    <tr><td class="text-muted">Unit</td><td class="text-end"><?= esc($unit->NAMA_UNIT ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Tanggal</td><td class="text-end"><?= $row->tanggal ? date('d-m-Y', strtotime($row->tanggal)) : '-' ?></td></tr>
                    <tr><td class="text-muted">Jatuh Tempo</td><td class="text-end"><?= $row->jatuh_tempo ? date('d-m-Y', strtotime($row->jatuh_tempo)) : '-' ?></td></tr>
                    <tr><td class="text-muted">Uraian</td><td class="text-end"><?= esc($row->uraian ?: '-') ?></td></tr>
                    <tr><td class="text-muted">Total</td><td class="text-end fw-semibold"><?= $rp($row->total) ?></td></tr>
                    <tr><td class="text-muted">Total Dibayar</td><td class="text-end"><?= $rp($row->total_dibayar) ?></td></tr>
                    <tr><td class="text-muted">Sisa</td><td class="text-end fw-semibold text-danger"><?= $rp($row->sisa) ?></td></tr>
                    <tr><td class="text-muted">Keterangan</td><td class="text-end"><?= esc($row->keterangan ?: '-') ?></td></tr>
                </table>

                <?php if ($settledLegacy || $pernahLegacy) : ?>
                    <div class="alert alert-<?= $settledLegacy ? 'warning' : 'secondary' ?> mt-3 mb-0">
                        <h6 class="fw-semibold mb-2"><iconify-icon icon="solar:shield-check-bold" width="18"></iconify-icon>
                            <?= $settledLegacy ? 'Ditandai Lunas Legacy' : 'Pelunasan Legacy Pernah Dibatalkan' ?>
                        </h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="text-muted">Tanggal settlement</td><td class="text-end fw-semibold"><?= $row->tanggal_settlement_legacy ? date('d-m-Y', strtotime($row->tanggal_settlement_legacy)) : '-' ?></td></tr>
                            <tr><td class="text-muted">Jenis settlement</td><td class="text-end"><code>legacy</code></td></tr>
                            <tr><td class="text-muted">Dicatat oleh</td><td class="text-end"><?= esc($row->cutoff_closed_by ? ('#' . $row->cutoff_closed_by) : '-') ?></td></tr>
                            <tr><td class="text-muted">Waktu pencatatan</td><td class="text-end"><?= $row->cutoff_closed_at ? date('d-m-Y H:i', strtotime($row->cutoff_closed_at)) : '-' ?></td></tr>
                            <tr><td class="text-muted">Sisa sebelum settlement</td><td class="text-end"><?= $rp($row->sisa_legacy_sebelum) ?></td></tr>
                        </table>
                        <?php if (! $settledLegacy) : ?>
                            <hr class="my-2">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted">Status</td><td class="text-end"><span class="badge bg-secondary">Sudah dibatalkan — kembali outstanding</span></td></tr>
                                <tr><td class="text-muted">Waktu pembatalan</td><td class="text-end fw-semibold"><?= $row->legacy_dibatalkan_at ? date('d-m-Y H:i', strtotime($row->legacy_dibatalkan_at)) : '-' ?></td></tr>
                                <tr><td class="text-muted">Dibatalkan oleh</td><td class="text-end"><?= esc($row->legacy_dibatalkan_by ? ('#' . $row->legacy_dibatalkan_by) : '-') ?></td></tr>
                                <tr><td class="text-muted">Alasan pembatalan</td><td class="text-end"><?= esc($row->legacy_alasan_pembatalan ?: '-') ?></td></tr>
                            </table>
                        <?php endif; ?>
                        <div class="small text-muted mt-2 mb-0">
                            Penandaan administratif — bukan transaksi pembayaran. Tidak ada jurnal,
                            tidak ada movement Kas/Bank, dan saldo kas/bank tidak berubah.
                            <?php if (! $settledLegacy) : ?>
                                Jejak settlement tetap disimpan sebagai riwayat.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <a href="<?= base_url('hutangpiutang/cetak/' . $row->id) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Cetak Bukti</a>
                    <?php if ($can_input && $authoritative && $row->status !== 'lunas') : ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bayar-modal">Catat Pembayaran</button>
                    <?php endif; ?>
                    <?php if ($bisaKompensasi) : ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#kompensasi-modal">Kompensasi</button>
                    <?php endif; ?>
                    <?php if ($settledLegacy && $can_input) : ?>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#batal-legacy-modal">Batalkan Pelunasan Legacy</button>
                    <?php elseif ($bisaLegacy) : ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#legacy-modal">Tandai Lunas Legacy</button>
                    <?php endif; ?>
                </div>
                <?php if ($can_input && ! $settledLegacy && ! $bisaLegacy && ! $outstanding) : ?>
                    <div class="small text-muted mt-2 mb-0"><?= esc($alasanLegacy) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Riwayat Pembayaran</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Tanggal</th><th class="text-end">Tunai</th><th class="text-end">Bank</th><th class="text-end">Jumlah</th><th>Sumber</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pembayaran)) : ?>
                                <tr><td colspan="6" class="text-center text-muted">Belum ada pembayaran.</td></tr>
                            <?php else : foreach ($pembayaran as $p) : ?>
                                <tr>
                                    <td><?= $p->tanggal_bayar ? date('d-m-Y', strtotime($p->tanggal_bayar)) : '-' ?></td>
                                    <td class="text-end"><?= $rp($p->bayar_tunai) ?></td>
                                    <td class="text-end"><?= $rp($p->bayar_bank) ?></td>
                                    <td class="text-end fw-semibold"><?= $rp($p->jumlah_bayar) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= esc($p->sumber) ?></span></td>
                                    <td><?= esc($p->keterangan ?: '-') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($can_input && $authoritative && $row->status !== 'lunas') : ?>
<div class="modal fade" id="bayar-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/bayar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Pembayaran <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Sisa: <strong><?= $rp($row->sisa) ?></strong></p>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bayar Tunai</label>
                    <input type="text" name="bayar_tunai" id="d-tunai" class="form-control" value="0">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank</label>
                    <select name="bank_idbank" class="form-select">
                        <option value="">-</option>
                        <?php foreach (($bank ?? []) as $b) : ?>
                            <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bayar Bank</label>
                    <input type="text" name="bayar_bank" id="d-bank" class="form-control" value="0">
                </div>
                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($bisaKompensasi) : ?>
<div class="modal fade" id="kompensasi-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/kompensasi') ?>">
            <?= csrf_field() ?>
            <?php if ($row->jenis === 'hutang') : ?>
                <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <?php else : ?>
                <input type="hidden" name="lawan_id" value="<?= (int) $row->id ?>">
            <?php endif; ?>
            <div class="modal-header">
                <h5 class="modal-title">Kompensasi <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Sisa <?= esc($row->jenis) ?> ini: <strong><?= $rp($row->sisa) ?></strong>. Pilih posisi lawan milik pihak yang sama.</p>
                <div class="mb-3">
                    <label class="form-label">Posisi <?= $row->jenis === 'hutang' ? 'Piutang' : 'Hutang' ?> Lawan</label>
                    <select name="<?= $row->jenis === 'hutang' ? 'lawan_id' : 'hutang_piutang_id' ?>" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($lawan_kompensasi as $l) : ?>
                            <option value="<?= (int) $l->id ?>"><?= esc($l->kode . ' — ' . $l->nama_pihak . ' — sisa ' . $rp($l->sisa)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nilai Kompensasi</label>
                    <input type="text" name="jumlah" class="form-control" value="0" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kompensasi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($bisaLegacy) : ?>
<div class="modal fade" id="legacy-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/tandai-lunas-legacy') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Tandai Lunas Legacy — <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-ds-dismiss="modal" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 px-3 small">
                    <strong>Ini bukan transaksi pembayaran.</strong>
                    Aksi ini hanya menandai posisi hutang sebagai sudah lunas secara faktual
                    (mis. sudah dikompensasi atau saling hapus). Tidak ada jurnal,
                    tidak ada pembukuan kas/bank, dan saldo Kas/Bank
                    <strong>tidak berubah</strong>.
                </div>

                <p class="mb-3">Sisa outstanding: <strong><?= $rp($row->sisa) ?></strong></p>

                <div class="mb-3">
                    <label class="form-label">Tanggal Settlement</label>
                    <input type="date" name="tanggal_settlement" class="form-control"
                           value="<?= esc($row->tanggal_settlement_legacy ?: $cutoffFinance) ?>"
                           min="<?= esc($row->tanggal) ?>" max="<?= esc($cutoffFinance) ?>" required>
                    <div class="form-text">
                        Harus pada atau sebelum Finance cut-off <strong><?= esc($cutoffFinance) ?></strong>.
                        Settlement setelah cut-off bukan transaksi legacy.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Keterangan / Alasan</label>
                    <textarea name="keterangan" class="form-control" rows="2"
                              placeholder="mis. Sudah dikompensasi dengan mutasi antar unit, diverifikasi Finance."></textarea>
                    <div class="form-text">Tercatat permanen di audit trail bersama nama dan waktu pencatatan.</div>
                </div>

                <div class="small text-muted">
                    Terdata pada tabel <code>hutang_piutang</code>: status, sisa,
                    tanggal settlement, jenis settlement <code>legacy</code>, dan pencatat.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">
                    Tandai Lunas Legacy
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($settledLegacy && $can_input) : ?>
<div class="modal fade" id="batal-legacy-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/batalkan-lunas-legacy') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" value="<?= (int) $row->id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Batalkan Pelunasan Legacy — <?= esc($row->kode) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 px-3 small">
                    Posisi hutang akan dikembalikan jadi outstanding sebesar
                    <strong><?= $rp($row->sisa_legacy_sebelum) ?></strong>.
                    Settlement dicatat pada
                    <strong><?= $row->tanggal_settlement_legacy ? date('d-m-Y', strtotime($row->tanggal_settlement_legacy)) : '-' ?></strong>.
                    <br><br>
                    Jejak settlement <strong>tidak dihapus</strong> — hanya penanda aktifnya yang dicabut,
                    dan pembatalan ini dicatat sebagai audit trail terpisah.
                    Tidak ada jurnal, tidak ada kas/bank, saldo tidak berubah.
                </div>

                <div class="mb-3">
                    <label class="form-label">Alasan Pembatalan</label>
                    <textarea name="alasan" class="form-control" rows="2"
                              placeholder="mis. Bukan faktual lunas — masih ada sisa yang belum diterima."></textarea>
                    <div class="form-text">
                        Disimpan permanen bersama nama dan waktu pembatalan.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-outline-warning">Ya, Batalkan Pelunasan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
