<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0"><?= $jenis === 'piutang' ? 'Daftar Piutang' : 'Daftar Hutang' ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active"><?= ucfirst($jenis) ?></li>
            </ol>
        </nav>
    </div>
</div>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$f = $filters ?? [];
$canInput = $can_input ?? false;
use App\Services\Finance\HutangPiutangService;

$isPiutang = $jenis === 'piutang';
$labelSumber = [
    'pembelian' => HutangPiutangService::labelSumber('pembelian'),
    'piutang_pelanggan' => HutangPiutangService::labelSumber('piutang_pelanggan'),
    'kasbon' => HutangPiutangService::labelSumber('kasbon'),
    'piutang_legacy' => HutangPiutangService::labelSumber('piutang_legacy'),
    'jasa_teknisi' => HutangPiutangService::labelSumber('jasa_teknisi'),
    'kelebihan_transfer' => HutangPiutangService::labelSumber('kelebihan_transfer'),
    'retur_barang' => HutangPiutangService::labelSumber('retur_barang'),
    'manual' => HutangPiutangService::labelSumber('manual'),
];
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= base_url('hutangpiutang/' . $jenis) ?>">
            <div class="col-md-3">
                <label class="form-label mb-1">Cari</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Kode / nama pihak" value="<?= esc($f['q'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="belum_lunas" <?= ($f['status'] ?? '') === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                    <option value="sebagian" <?= ($f['status'] ?? '') === 'sebagian' ? 'selected' : '' ?>>Sebagian</option>
                    <option value="lunas" <?= ($f['status'] ?? '') === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Sumber</label>
                <select name="sumber_tipe" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php
                    $allowedSumber = $isPiutang
                        ? ['piutang_pelanggan', 'kasbon', 'kelebihan_transfer', 'retur_barang', 'piutang_legacy']
                        : ['pembelian', 'jasa_teknisi', 'manual'];
                    foreach ($allowedSumber as $k) : ?>
                        <option value="<?= $k ?>" <?= ($f['sumber_tipe'] ?? '') === $k ? 'selected' : '' ?>><?= esc($labelSumber[$k] ?? $k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Bulan</label>
                <input type="month" name="bulan" class="form-control form-control-sm" value="<?= esc($f['bulan'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">Semua Unit</option>
                    <?php foreach (($units ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_id ?? null) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>

        <?php if ($canInput) : ?>
            <a href="<?= base_url('hutangpiutang/form') ?>" class="btn btn-sm btn-success mt-3">+ Input Hutang / Piutang</a>
        <?php endif; ?>
        <?php if (!$isPiutang) : ?>
            <div class="alert alert-info mt-3 mb-0 py-2 small">
                Hutang supplier otomatis terbentuk dari transaksi <strong>Pembelian</strong>. Pembayaran dilakukan melalui
                <a href="<?= base_url('daftar_tagihan') ?>">Daftar Tagihan Hutang</a>. Saldo hutang manual/teknisi dapat dibayar dari halaman detail.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tanggal</th>
                        <th>Pihak</th>
                        <th>Sumber</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Dibayar</th>
                        <th class="text-end">Sisa</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="10" class="text-center text-muted">Belum ada data.</td></tr>
                    <?php else : foreach ($rows as $row) :
                        $authoritative = in_array($row['sumber_tipe'], HutangPiutangService::AUTHORITATIVE_SUMBER, true);
                        $statusClass = $row['status'] === 'lunas' ? 'success' : ($row['status'] === 'sebagian' ? 'warning' : 'secondary');
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($row['kode']) ?></td>
                            <td><?= $row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : '-' ?></td>
                            <td><?= esc($row['nama_pihak']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= esc($labelSumber[$row['sumber_tipe']] ?? $row['sumber_tipe']) ?></span></td>
                            <td><?= $row['jatuh_tempo'] ? date('d-m-Y', strtotime($row['jatuh_tempo'])) : '-' ?></td>
                            <td class="text-end"><?= $rp($row['total']) ?></td>
                            <td class="text-end"><?= $rp($row['total_dibayar']) ?></td>
                            <td class="text-end fw-semibold"><?= $rp($row['sisa']) ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= esc(\App\Services\Finance\HutangPiutangService::labelStatus($row['status'])) ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= base_url('hutangpiutang/detail/' . $row['id']) ?>" class="btn btn-sm btn-light">Detail</a>
                                <?php if ($authoritative && $canInput && $row['status'] !== 'lunas') : ?>
                                    <button type="button" class="btn btn-sm btn-primary btn-bayar"
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-kode="<?= esc($row['kode']) ?>"
                                        data-pihak="<?= esc($row['nama_pihak']) ?>"
                                        data-sisa="<?= (int) $row['sisa'] ?>">Bayar</button>
                                <?php endif; ?>
                                <?php if ($authoritative) : ?>
                                    <a href="<?= base_url('hutangpiutang/cetak/' . $row['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Bukti</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canInput) : ?>
<div class="modal fade" id="bayar-modal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= base_url('hutangpiutang/bayar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="hutang_piutang_id" id="bayar-id">
            <div class="modal-header">
                <h5 class="modal-title">Pembayaran <span id="bayar-kode"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong id="bayar-pihak"></strong><br><span class="text-muted">Sisa: <span id="bayar-sisa"></span></span></p>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bayar Tunai</label>
                    <input type="text" name="bayar_tunai" id="bayar-tunai" class="form-control" value="0">
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
                    <input type="text" name="bayar_bank" id="bayar-bank" class="form-control" value="0">
                </div>
                <div class="mb-0">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('bayar-modal'));
    document.querySelectorAll('.btn-bayar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('bayar-id').value = btn.dataset.id;
            document.getElementById('bayar-kode').textContent = btn.dataset.kode;
            document.getElementById('bayar-pihak').textContent = btn.dataset.pihak;
            document.getElementById('bayar-sisa').textContent = 'Rp ' + Number(btn.dataset.sisa).toLocaleString('id-ID');
            document.getElementById('bayar-tunai').value = btn.dataset.sisa;
            document.getElementById('bayar-bank').value = 0;
            modal.show();
        });
    });
});
</script>
<?php endif; ?>
