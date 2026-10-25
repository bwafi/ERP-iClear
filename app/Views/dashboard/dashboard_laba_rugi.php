<div class="card mb-4">
    <div class="card-body">
        <!-- Filter Section -->
        <form method="get" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_month" class="form-label">Bulan Mulai</label>
                    <input type="month" class="form-control" id="start_month" name="start_month" value="<?= esc($start_month) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_month" class="form-label">Bulan Akhir</label>
                    <input type="month" class="form-control" id="end_month" name="end_month" value="<?= esc($end_month) ?>">
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label for="jenis_laporan" class="form-label">Jenis Laporan</label>
                    <select class="form-select" id="jenis_laporan" name="jenis_laporan">
                        <option value="all" <?= ($jenis_laporan == 'all' ? 'selected' : '') ?>>Semua</option>
                        <option value="jurnal" <?= ($jenis_laporan == 'jurnal' ? 'selected' : '') ?>>Jurnal</option>
                        <option value="transaksi" <?= ($jenis_laporan == 'transaksi' ? 'selected' : '') ?>>Transaksi</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <?php 
            $colSize = ($jenis_laporan == 'all') ? 'col-md-6' : 'col-md-12';
            ?>
            <?php if ($jenis_laporan == 'jurnal' || $jenis_laporan == 'all'): ?>
            <div class="<?= $colSize ?>">
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
            <?php endif; ?>
            
            <?php if ($jenis_laporan == 'transaksi' || $jenis_laporan == 'all'): ?>
            <div class="<?= $colSize ?>">
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
            <?php endif; ?>
        </div>

        <!-- Laba Rugi Detail Section -->
        <div class="row mb-4">
            <?php if ($jenis_laporan == 'jurnal' || $jenis_laporan == 'all'): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Laba Rugi Berdasarkan Jurnal</h5>
                        <small class="text-muted">Periode: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></small>
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
                                        <td class="text-end"><strong class="text-success">Rp <?= number_format($laba_rugi_jurnal['total_pendapatan'] ?? 0, 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Biaya</strong></td>
                                        <td class="text-end"><strong class="text-danger">Rp <?= number_format($laba_rugi_jurnal['total_biaya'] ?? 0, 0, ',', '.') ?></strong></td>
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
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Akun</th>
                                            <th class="text-end">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laba_rugi_jurnal['pendapatan'] as $item): ?>
                                        <tr>
                                            <td><?= esc($item->nama_akun ?? $item->no_akun) ?></td>
                                            <td class="text-end">Rp <?= number_format($item->saldo ?? 0, 0, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($laba_rugi_jurnal['biaya'])): ?>
                        <div class="mt-3">
                            <h6 class="text-danger">Detail Biaya:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Akun</th>
                                            <th class="text-end">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laba_rugi_jurnal['biaya'] as $item): ?>
                                        <tr>
                                            <td><?= esc($item->nama_akun ?? $item->no_akun) ?></td>
                                            <td class="text-end">Rp <?= number_format($item->saldo ?? 0, 0, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($jenis_laporan == 'transaksi' || $jenis_laporan == 'all'): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Laba Rugi Berdasarkan Transaksi</h5>
                        <small class="text-muted">Periode: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></small>
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
                                        <td class="text-end"><strong class="text-success">Rp <?= number_format($laba_rugi_transaksi['pendapatan']['total'] ?? 0, 0, ',', '.') ?></strong></td>
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
                                        <td class="text-end"><strong class="text-danger">Rp <?= number_format($laba_rugi_transaksi['biaya']['total'] ?? 0, 0, ',', '.') ?></strong></td>
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
            <?php endif; ?>
        </div>

        <!-- Chart Section -->
        <?php if (!empty($chart_data)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Grafik Laba Rugi Per Bulan</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="labaRugiChart" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if (!empty($chart_data)): 
    // Prepare chart data
    $chartLabels = array_column($chart_data, 'label');
    $jurnalPendapatan = array_map(function($item) { return $item['jurnal']['pendapatan'] ?? 0; }, $chart_data);
    $jurnalBiaya = array_map(function($item) { return $item['jurnal']['biaya'] ?? 0; }, $chart_data);
    $jurnalLabaRugi = array_map(function($item) { return $item['jurnal']['laba_rugi'] ?? 0; }, $chart_data);
    $transaksiPendapatan = array_map(function($item) { return $item['transaksi']['pendapatan'] ?? 0; }, $chart_data);
    $transaksiBiaya = array_map(function($item) { return $item['transaksi']['biaya'] ?? 0; }, $chart_data);
    $transaksiLabaRugi = array_map(function($item) { return $item['transaksi']['laba_rugi'] ?? 0; }, $chart_data);
    
    // Build datasets array
    $datasets = [];
    if ($jenis_laporan == 'jurnal' || $jenis_laporan == 'all') {
        $datasets[] = [
            'label' => 'Pendapatan (Jurnal)',
            'data' => $jurnalPendapatan,
            'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
            'borderColor' => 'rgba(75, 192, 192, 1)',
            'borderWidth' => 1
        ];
        $datasets[] = [
            'label' => 'Biaya (Jurnal)',
            'data' => $jurnalBiaya,
            'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
            'borderColor' => 'rgba(255, 99, 132, 1)',
            'borderWidth' => 1
        ];
        $datasets[] = [
            'label' => 'Laba/Rugi (Jurnal)',
            'data' => $jurnalLabaRugi,
            'type' => 'line',
            'borderColor' => 'rgba(54, 162, 235, 1)',
            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
            'borderWidth' => 2,
            'fill' => true,
            'tension' => 0.3
        ];
    }
    if ($jenis_laporan == 'transaksi' || $jenis_laporan == 'all') {
        $datasets[] = [
            'label' => 'Pendapatan (Transaksi)',
            'data' => $transaksiPendapatan,
            'backgroundColor' => 'rgba(153, 102, 255, 0.6)',
            'borderColor' => 'rgba(153, 102, 255, 1)',
            'borderWidth' => 1
        ];
        $datasets[] = [
            'label' => 'Biaya (Transaksi)',
            'data' => $transaksiBiaya,
            'backgroundColor' => 'rgba(255, 159, 64, 0.6)',
            'borderColor' => 'rgba(255, 159, 64, 1)',
            'borderWidth' => 1
        ];
        $datasets[] = [
            'label' => 'Laba/Rugi (Transaksi)',
            'data' => $transaksiLabaRugi,
            'type' => 'line',
            'borderColor' => 'rgba(255, 206, 86, 1)',
            'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
            'borderWidth' => 2,
            'fill' => true,
            'tension' => 0.3
        ];
    }
?>
// Laba Rugi Chart
const ctxLabaRugi = document.getElementById('labaRugiChart').getContext('2d');

new Chart(ctxLabaRugi, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: <?= json_encode($datasets) ?>
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
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
                beginAtZero: false,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

