<!-- Page Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Laporan Laba Rugi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Home</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Laporan Laba Rugi</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filter Form -->
<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <form method="get" action="<?= base_url('LaporanKeuangan/laba_rugi') ?>" id="filterForm">
            <div class="row mb-3">

                <div class="col-md-3">
                    <label class="form-label">Tanggal Awal:</label>
                    <input type="date" name="tanggal_awal" id="tanggalAwal" class="form-control"
                        value="<?= $tanggal_awal ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir:</label>
                    <input type="date" name="tanggal_akhir" id="tanggalAkhir" class="form-control"
                        value="<?= $tanggal_akhir ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tampil Saldo:</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="toggleZeroSaldo" value="1" checked="true">
                        <label class="form-check-label" for="toggleZeroSaldo">Tampilkan Saldo 0</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit:</label>
                    <select name="id_unit[]" id="idUnit" class="form-control select2">
                        <option value="">Semua Unit</option>
                        <?php foreach ($unit as $u): ?>
                            <option value="<?= $u->idunit ?>"
                                <?= (is_array($id_unit) && in_array($u->idunit, $id_unit)) ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jenis Laporan:</label>
                    <select name="jenis_laporan" id="jenisLaporan" class="form-control" onchange="document.getElementById('filterForm').submit();">
                        <option value="jurnal" <?= ($jenis_laporan == 'jurnal') ? 'selected' : '' ?>>Berdasarkan Jurnal</option>
                        <option value="transaksi" <?= ($jenis_laporan == 'transaksi') ? 'selected' : '' ?>>Berdasarkan Transaksi</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <iconify-icon icon="solar:filter-bold" width="20" height="20"></iconify-icon>
                        Filter
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilter()">
                        <iconify-icon icon="solar:restart-bold" width="20" height="20"></iconify-icon>
                        Reset
                    </button>
                    <button type="button" class="btn btn-success" onclick="printLaporanStandar()">
                        <iconify-icon icon="solar:print" width="20" height="20"></iconify-icon>
                        Cetak Laporan (Standar)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($jenis_laporan == 'jurnal' && !empty($data_jurnal)): ?>
    <!-- Laporan Berdasarkan Jurnal -->
    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body">
            <h5 class="card-title mb-4">Laporan Laba Rugi Berdasarkan Jurnal</h5>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Pendapatan</h6>
                            <h4 class="mb-0" id="totalPendapatanJurnal">
                                <?= 'Rp ' . number_format($data_jurnal['total_pendapatan'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Biaya Pokok</h6>
                            <h4 class="mb-0" id="totalBiayaPokokJurnal">
                                <?= 'Rp ' . number_format($data_jurnal['total_beban_pokok_penjualan'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Biaya Operasional</h6>
                            <h4 class="mb-0" id="totalBiayaOperasionalJurnal">
                                <?= 'Rp ' . number_format($data_jurnal['total_beban_operasional'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card <?= $data_jurnal['laba_rugi'] >= 0 ? 'text-bg-primary' : 'text-bg-warning' ?> text-white">
                        <div class="card-body">
                            <h6 class="card-title">Laba / Rugi</h6>
                            <h4 class="mb-0" id="labaRugiJurnal">
                                <?= 'Rp ' . number_format($data_jurnal['laba_rugi'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Pendapatan -->
            <div class="mb-4">
                <h6 class="fw-bold">Pendapatan</h6>
                <button class="btn btn-outline-success btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailJurnalPendapatan">
                    Detail Pendapatan
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jurnal['pendapatan'])): ?>
                                <?php foreach ($data_jurnal['pendapatan'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item->no_akun ?? $item['no_akun'] ?? '') ?></td>
                                        <td><?= esc($item->nama_akun ?? $item['nama_akun'] ?? '') ?></td>
                                        <td class="text-end"><?= 'Rp ' . number_format($item->saldo ?? $item['saldo'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data pendapatan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Pendapatan:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_jurnal['total_pendapatan'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Tabel Beban Pokok Penjualan -->
            <div class="mb-4">
                <h6 class="fw-bold">Beban Pokok Penjualan</h6>
                <button class="btn btn-outline-primary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailJurnalBebanPokok">
                    Detail Beban Pokok
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jurnal['beban_pokok_penjualan'])): ?>
                                <?php foreach ($data_jurnal['beban_pokok_penjualan'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item->no_akun ?? $item['no_akun'] ?? '') ?></td>
                                        <td><?= esc($item->nama_akun ?? $item['nama_akun'] ?? '') ?></td>
                                        <td class="text-end"><?= 'Rp ' . number_format($item->saldo ?? $item['saldo'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data beban pokok penjualan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Beban Pokok Penjualan:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_jurnal['total_beban_pokok_penjualan'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Tabel Beban Operasional -->
            <div class="mb-4">
                <h6 class="fw-bold">Beban Operasional</h6>
                <button class="btn btn-outline-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailJurnalBebanOperasional">
                    Detail Beban Operasional
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jurnal['beban_operasional'])): ?>
                                <?php foreach ($data_jurnal['beban_operasional'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item->no_akun ?? $item['no_akun'] ?? '') ?></td>
                                        <td><?= esc($item->nama_akun ?? $item['nama_akun'] ?? '') ?></td>
                                        <td class="text-end"><?= 'Rp ' . number_format($item->saldo ?? $item['saldo'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data beban operasional</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Beban Operasional:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_jurnal['total_beban_operasional'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Tabel Pendapatan Non-Operasional -->
            <div class="mb-4">
                <h6 class="fw-bold">Pendapatan Non-Operasional</h6>
                <button class="btn btn-outline-info btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailJurnalPendapatanNonOperasional">
                    Detail Pendapatan Non-Operasional
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jurnal['pendapatan_non_operasional'])): ?>
                                <?php foreach ($data_jurnal['pendapatan_non_operasional'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item->no_akun ?? $item['no_akun'] ?? '') ?></td>
                                        <td><?= esc($item->nama_akun ?? $item['nama_akun'] ?? '') ?></td>
                                        <td class="text-end"><?= 'Rp ' . number_format($item->saldo ?? $item['saldo'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data pendapatan non-operasional</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Pendapatan Non-Operasional:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_jurnal['total_pendapatan_non_operasional'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Tabel Beban Non-Operasional -->
            <div class="mb-4">
                <h6 class="fw-bold">Beban Non-Operasional</h6>
                <button class="btn btn-outline-warning btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailJurnalBebanNonOperasional">
                    Detail Beban Non-Operasional
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>No Akun</th>
                                <th>Nama Akun</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jurnal['beban_non_operasional'])): ?>
                                <?php foreach ($data_jurnal['beban_non_operasional'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item->no_akun ?? $item['no_akun'] ?? '') ?></td>
                                        <td><?= esc($item->nama_akun ?? $item['nama_akun'] ?? '') ?></td>
                                        <td class="text-end"><?= 'Rp ' . number_format($item->saldo ?? $item['saldo'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Tidak ada data beban non-operasional</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total Beban Non-Operasional:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_jurnal['total_beban_non_operasional'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($jenis_laporan == 'transaksi' && !empty($data_transaksi)): ?>
    <!-- Laporan Berdasarkan Transaksi -->
    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body">
            <h5 class="card-title mb-4">Laporan Laba Rugi Berdasarkan Transaksi</h5>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Pendapatan</h6>
                            <h4 class="mb-0">
                                <?= 'Rp ' . number_format($data_transaksi['pendapatan']['total'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Biaya</h6>
                            <h4 class="mb-0">
                                <?= 'Rp ' . number_format($data_transaksi['biaya']['total'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card <?= $data_transaksi['laba_rugi'] >= 0 ? 'text-bg-primary' : 'text-bg-warning' ?> text-white">
                        <div class="card-body">
                            <h6 class="card-title">Laba / Rugi</h6>
                            <h4 class="mb-0">
                                <?= 'Rp ' . number_format($data_transaksi['laba_rugi'], 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel Pendapatan -->
            <div class="mb-4">
                <h5 class="fw-bold">PENDAPATAN</h5>
                <button class="btn btn-outline-primary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailPendapatan">
                    Detail Pendapatan
                </button>
                <br>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis Pendapatan</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Penjualan (POS)</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['pendapatan']['penjualan'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>Service</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['pendapatan']['service'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>Kas Masuk</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['pendapatan']['kas_masuk'], 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th class="text-end">Total Pendapatan:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_transaksi['pendapatan']['total'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Tabel Biaya -->
            <div class="mb-4">
                <h5 class="fw-bold">BIAYA</h5>
                <button class="btn btn-outline-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalDetailBiaya">
                    Detail Biaya
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis Biaya</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Kas Keluar</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['biaya']['kas_keluar'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>HPP Penjualan</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['biaya']['hpp_penjualan'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>HPP Service</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['biaya']['hpp_service'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>HPP Klaim</td>
                                <td class="text-end"><?= 'Rp ' . number_format($data_transaksi['biaya']['hpp_klaim'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th class="text-end">Total Biaya:</th>
                                <th class="text-end"><?= 'Rp ' . number_format($data_transaksi['biaya']['total'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Tidak ada data -->
    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body text-center">
            <p class="text-muted">Silakan pilih filter tanggal dan unit untuk menampilkan data.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($jenis_laporan == 'transaksi' && !empty($data_transaksi)): ?>
    <div class="modal fade" id="modalDetailPendapatan" tabindex="-1" aria-labelledby="modalDetailPendapatanLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetailPendapatanLabel">Detail Pendapatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="pendapatanTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-penjualan" data-bs-toggle="tab" data-bs-target="#tab-pane-penjualan" type="button" role="tab">
                                Penjualan (POS)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-service" data-bs-toggle="tab" data-bs-target="#tab-pane-service" type="button" role="tab">
                                Service
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-kas-masuk" data-bs-toggle="tab" data-bs-target="#tab-pane-kas-masuk" type="button" role="tab">
                                Kas Masuk
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-pane-penjualan" role="tabpanel">
                            <?php $detailPenjualan = $data_transaksi['detail']['pendapatan']['penjualan'] ?? []; ?>
                            <?php if (!empty($detailPenjualan)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>No Invoice</th>
                                                <th>Pelanggan</th>
                                                <th>Unit</th>
                                                <th class="text-end">Total Penjualan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailPenjualan as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                                    <td><?= esc($row->kode_invoice) ?></td>
                                                    <td><?= esc($row->nama_pelanggan ?? '-') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->total_penjualan ?? 0, 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada transaksi penjualan pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tab-pane-service" role="tabpanel">
                            <?php $detailService = $data_transaksi['detail']['pendapatan']['service'] ?? []; ?>
                            <?php if (!empty($detailService)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>No Service</th>
                                                <th>Pelanggan</th>
                                                <th>Unit</th>
                                                <th class="text-end">Harus Dibayar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailService as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->created_at))) ?></td>
                                                    <td><?= esc($row->no_service) ?></td>
                                                    <td><?= esc($row->nama_pelanggan ?? '-') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->harus_dibayar ?? 0, 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada transaksi service pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tab-pane-kas-masuk" role="tabpanel">
                            <?php $detailKasMasuk = $data_transaksi['detail']['pendapatan']['kas_masuk'] ?? []; ?>
                            <?php if (!empty($detailKasMasuk)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Kategori</th>
                                                <th>Deskripsi</th>
                                                <th>Unit</th>
                                                <th class="text-end">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailKasMasuk as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                                    <td><?= esc($row->kategori ?? '-') ?></td>
                                                    <td><?= esc($row->deskripsi ?? '-') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->jumlah ?? 0, 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada kas masuk pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetailBiaya" tabindex="-1" aria-labelledby="modalDetailBiayaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetailBiayaLabel">Detail Biaya</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="biayaTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-kas-keluar" data-bs-toggle="tab" data-bs-target="#tab-pane-kas-keluar" type="button" role="tab">
                                Kas Keluar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-hpp-penjualan" data-bs-toggle="tab" data-bs-target="#tab-pane-hpp-penjualan" type="button" role="tab">
                                HPP Penjualan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-hpp-service" data-bs-toggle="tab" data-bs-target="#tab-pane-hpp-service" type="button" role="tab">
                                HPP Service
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-pane-kas-keluar" role="tabpanel">
                            <?php $detailKasKeluar = $data_transaksi['detail']['biaya']['kas_keluar'] ?? []; ?>
                            <?php if (!empty($detailKasKeluar)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Kategori</th>
                                                <th>Deskripsi</th>
                                                <th>Unit</th>
                                                <th class="text-end">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailKasKeluar as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                                    <td><?= esc($row->kategori ?? '-') ?></td>
                                                    <td><?= esc($row->deskripsi ?? '-') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->jumlah ?? 0, 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada kas keluar pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tab-pane-hpp-penjualan" role="tabpanel">
                            <?php $detailHppPenjualan = $data_transaksi['detail']['biaya']['hpp_penjualan'] ?? []; ?>
                            <?php if (!empty($detailHppPenjualan)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>No Invoice</th>
                                                <th>Barang</th>
                                                <th class="text-end">Qty</th>
                                                <th class="text-end">HPP</th>
                                                <th class="text-end">Total HPP</th>
                                                <th>Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailHppPenjualan as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                                    <td><?= esc($row->kode_invoice ?? '-') ?></td>
                                                    <td><?= esc($row->nama_barang ?? '-') ?></td>
                                                    <td class="text-end"><?= number_format($row->jumlah ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->hpp_penjualan ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->total_hpp ?? 0, 0, ',', '.') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada HPP penjualan pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tab-pane-hpp-service" role="tabpanel">
                            <?php $detailHppService = $data_transaksi['detail']['biaya']['hpp_service'] ?? []; ?>
                            <?php if (!empty($detailHppService)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>No Service</th>
                                                <th>Barang</th>
                                                <th class="text-end">Qty</th>
                                                <th class="text-end">HPP</th>
                                                <th class="text-end">Total HPP</th>
                                                <th>Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detailHppService as $row): ?>
                                                <tr>
                                                    <td><?= esc(date('d/m/Y', strtotime($row->created_at))) ?></td>
                                                    <td><?= esc($row->no_service ?? '-') ?></td>
                                                    <td><?= esc($row->nama_barang ?? '-') ?></td>
                                                    <td class="text-end"><?= number_format($row->jumlah ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->hpp_penjualan ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= 'Rp ' . number_format($row->total_hpp ?? 0, 0, ',', '.') ?></td>
                                                    <td><?= esc($row->nama_unit ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ada HPP service pada rentang ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($jenis_laporan == 'jurnal' && !empty($data_jurnal)): ?>
    <div class="modal fade" id="modalDetailJurnalPendapatan" tabindex="-1" aria-labelledby="modalDetailJurnalPendapatanLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetailJurnalPendapatanLabel">Detail Pendapatan (Jurnal)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php $detailPendapatanJurnal = $data_jurnal['detail']['pendapatan'] ?? []; ?>
                    <?php if (!empty($detailPendapatanJurnal)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>No Akun</th>
                                        <th>Nama Akun</th>
                                        <th>Keterangan</th>
                                        <th>Unit</th>
                                        <th class="text-end">Debet</th>
                                        <th class="text-end">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailPendapatanJurnal as $row): ?>
                                        <tr>
                                            <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                            <td><?= esc($row->no_akun) ?></td>
                                            <td><?= esc($row->nama_akun) ?></td>
                                            <td><?= esc($row->keterangan ?? '-') ?></td>
                                            <td><?= esc($row->nama_unit ?? '-') ?></td>
                                            <td class="text-end"><?= 'Rp ' . number_format($row->debet ?? 0, 0, ',', '.') ?></td>
                                            <td class="text-end"><?= 'Rp ' . number_format($row->kredit ?? 0, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Belum ada transaksi pendapatan untuk rentang ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetailJurnalBiaya" tabindex="-1" aria-labelledby="modalDetailJurnalBiayaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetailJurnalBiayaLabel">Detail Biaya (Jurnal)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php $detailBiayaJurnal = $data_jurnal['detail']['biaya'] ?? []; ?>
                    <?php if (!empty($detailBiayaJurnal)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>No Akun</th>
                                        <th>Nama Akun</th>
                                        <th>Keterangan</th>
                                        <th>Unit</th>
                                        <th class="text-end">Debet</th>
                                        <th class="text-end">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailBiayaJurnal as $row): ?>
                                        <tr>
                                            <td><?= esc(date('d/m/Y', strtotime($row->tanggal))) ?></td>
                                            <td><?= esc($row->no_akun) ?></td>
                                            <td><?= esc($row->nama_akun) ?></td>
                                            <td><?= esc($row->keterangan ?? '-') ?></td>
                                            <td><?= esc($row->nama_unit ?? '-') ?></td>
                                            <td class="text-end"><?= 'Rp ' . number_format($row->debet ?? 0, 0, ',', '.') ?></td>
                                            <td class="text-end"><?= 'Rp ' . number_format($row->kredit ?? 0, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Belum ada transaksi biaya untuk rentang ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Set default tanggal (15 hari terakhir)
        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        // Set default jika belum ada nilai
        if (!document.getElementById('tanggalAwal').value) {
            document.getElementById('tanggalAwal').value = toDateInputValue(fifteenDaysAgo);
        }
        if (!document.getElementById('tanggalAkhir').value) {
            document.getElementById('tanggalAkhir').value = toDateInputValue(today);
        }
    });

    function resetFilter() {
        document.getElementById('tanggalAwal').value = '';
        document.getElementById('tanggalAkhir').value = '';
        // Reset multiple select
        var select = document.getElementById('idUnit');
        for (var i = 0; i < select.options.length; i++) {
            select.options[i].selected = false;
        }
        document.getElementById('filterForm').submit();
    }

    function printLaporanStandar() {
        var tanggalAwal = document.getElementById('tanggalAwal').value;
        var tanggalAkhir = document.getElementById('tanggalAkhir').value;
        var select = document.getElementById('idUnit');
        var selectedUnits = Array.from(select.selectedOptions).map(option => option.value);
        var idunit = selectedUnits.length > 0 ? selectedUnits.join(',') : '';
        var show_saldo_0 = 9;
        if (document.getElementById('toggleZeroSaldo').checked) {
            show_saldo_0 = 1;
        }

        var url = '<?= base_url('LaporanKeuangan/laba_rugi_standar/cetak') ?>?tanggal_awal=' + tanggalAwal + '&tanggal_akhir=' + tanggalAkhir + '&id_unit=' + idunit + '&show_saldo_0=' + show_saldo_0;
        window.open(url, '_blank');
    }
</script>