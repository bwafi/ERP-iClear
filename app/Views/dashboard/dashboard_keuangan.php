<div class="card mb-4">
    <div class="card-body">
        <!-- Filter Section -->
        <form method="get" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= esc($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= esc($end_date) ?>">
                </div>
                <div class="col-md-3">
                    <label for="unit_id" class="form-label">Unit</label>
                    <select class="form-select" id="unit_id" name="unit_id">
                        <option value="">-- Semua Unit --</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit->idunit ?>" <?= ($unit_id == $unit->idunit ? 'selected' : '') ?>>
                                <?= esc($unit->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

        <!-- Metrics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Kas Masuk</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_kas_masuk, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-arrow-down-left fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Kas Keluar</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_kas_keluar, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-arrow-up-right fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-<?= $saldo_kas >= 0 ? 'primary' : 'warning' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Saldo Kas</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($saldo_kas, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-wallet fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Debet</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_debet, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-arrow-down fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Kredit</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_kredit, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-arrow-up fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Hutang</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_hutang, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-alert-circle fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Total Piutang</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($total_piutang, 0, ',', '.') ?></h3>
                            </div>
                            <i class="ti ti-receipt fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laba Rugi Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-bg-<?= ($laba_rugi_jurnal['laba_rugi'] ?? 0) >= 0 ? 'success' : 'danger' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Laba/Rugi (Jurnal)</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($laba_rugi_jurnal['laba_rugi'] ?? 0, 0, ',', '.') ?></h3>
                                <small class="text-white-50">
                                    Pendapatan: Rp <?= number_format($laba_rugi_jurnal['total_pendapatan'] ?? 0, 0, ',', '.') ?><br>
                                    Biaya: Rp <?= number_format($laba_rugi_jurnal['total_biaya'] ?? 0, 0, ',', '.') ?>
                                </small>
                            </div>
                            <i class="ti ti-chart-line fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-bg-<?= ($laba_rugi_transaksi['laba_rugi'] ?? 0) >= 0 ? 'success' : 'danger' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white-50 mb-2">Laba/Rugi (Transaksi)</h6>
                                <h3 class="text-white mb-0">Rp <?= number_format($laba_rugi_transaksi['laba_rugi'] ?? 0, 0, ',', '.') ?></h3>
                                <small class="text-white-50">
                                    Pendapatan: Rp <?= number_format($laba_rugi_transaksi['pendapatan']['total'] ?? 0, 0, ',', '.') ?><br>
                                    Biaya: Rp <?= number_format($laba_rugi_transaksi['biaya']['total'] ?? 0, 0, ',', '.') ?>
                                </small>
                            </div>
                            <i class="ti ti-chart-bar fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laba Rugi Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Laba Rugi Berdasarkan Jurnal</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Keterangan</th>
                                        <th class="text-end">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Total Pendapatan</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_jurnal['total_pendapatan'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Biaya</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_jurnal['total_biaya'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr class="table-<?= ($laba_rugi_jurnal['laba_rugi'] ?? 0) >= 0 ? 'success' : 'danger' ?>">
                                        <td><strong>Laba / Rugi</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_jurnal['laba_rugi'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($laba_rugi_jurnal['pendapatan'])): ?>
                            <div class="mt-3">
                                <h6 class="text-success">Detail Pendapatan:</h6>
                                <ul class="list-unstyled small">
                                    <?php foreach ($laba_rugi_jurnal['pendapatan'] as $item): ?>
                                        <li class="d-flex justify-content-between">
                                            <span><?= esc($item->nama_akun ?? $item->no_akun) ?></span>
                                            <span>Rp <?= number_format($item->saldo ?? 0, 0, ',', '.') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($laba_rugi_jurnal['biaya'])): ?>
                            <div class="mt-3">
                                <h6 class="text-danger">Detail Biaya:</h6>
                                <ul class="list-unstyled small">
                                    <?php foreach ($laba_rugi_jurnal['biaya'] as $item): ?>
                                        <li class="d-flex justify-content-between">
                                            <span><?= esc($item->nama_akun ?? $item->no_akun) ?></span>
                                            <span>Rp <?= number_format($item->saldo ?? 0, 0, ',', '.') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Laba Rugi Berdasarkan Transaksi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Keterangan</th>
                                        <th class="text-end">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="2" class="bg-light"><strong>Pendapatan:</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Penjualan (POS)</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['pendapatan']['penjualan'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Service</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['pendapatan']['service'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kas Masuk</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['pendapatan']['kas_masuk'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Pendapatan</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_transaksi['pendapatan']['total'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="bg-light"><strong>Biaya:</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Kas Keluar</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['biaya']['kas_keluar'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>HPP Penjualan</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['biaya']['hpp_penjualan'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>HPP Service</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['biaya']['hpp_service'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td>HPP Klaim</td>
                                        <td class="text-end">Rp <?= number_format($laba_rugi_transaksi['biaya']['hpp_klaim'] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Biaya</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_transaksi['biaya']['total'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr class="table-<?= ($laba_rugi_transaksi['laba_rugi'] ?? 0) >= 0 ? 'success' : 'danger' ?>">
                                        <td><strong>Laba / Rugi</strong></td>
                                        <td class="text-end"><strong>Rp <?= number_format($laba_rugi_transaksi['laba_rugi'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Grafik Kas Masuk & Keluar</h5>
                        <canvas id="kasChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Top 5 Kategori Kas Masuk</h5>
                        <canvas id="kategoriChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Status Pelunasan Piutang</h5>
                        <canvas id="pelunasanChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Aging Piutang</h5>
                        <canvas id="agingChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Kas Masuk & Keluar Chart
    const ctxKas = document.getElementById('kasChart').getContext('2d');
    new Chart(ctxKas, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                    label: 'Kas Masuk',
                    data: <?= json_encode($chart_masuk) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Kas Keluar',
                    data: <?= json_encode($chart_keluar) ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

    // Kategori Kas Masuk Chart
    const ctxKategori = document.getElementById('kategoriChart').getContext('2d');
    new Chart(ctxKategori, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($kategori_masuk_labels) ?>,
            datasets: [{
                data: <?= json_encode($kategori_masuk_data) ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

    const pelunasanCtx = document.getElementById('pelunasanChart').getContext('2d');
    new Chart(pelunasanCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($piutang_status_labels) ?>,
            datasets: [{
                data: <?= json_encode($piutang_status_data) ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(75, 192, 192, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.label}: ${ctx.raw} tagihan`
                    }
                }
            }
        }
    });

    const agingCtx = document.getElementById('agingChart').getContext('2d');
    new Chart(agingCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($piutang_aging_labels) ?>,
            datasets: [{
                data: <?= json_encode($piutang_aging_data) ?>,
                backgroundColor: [
                    'rgba(99, 132, 255, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.raw ?
                            `${ctx.label}: Rp ${Number(ctx.raw).toLocaleString('id-ID')}` :
                            `${ctx.label}: Rp 0`
                    }
                }
            }
        }
    });
</script>