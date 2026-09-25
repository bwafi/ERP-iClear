<!-- Page Header & Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4 bg-light-subtle border">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-semibold mb-1 text-dark">Omset Bulanan</h4>
            <p class="fs-3 text-muted mb-0">Laporan ringkasan penjualan, performa produk, dan tren pendapatan harian</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item text-primary fw-medium" aria-current="page">Omset Bulanan</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Period Navigation Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <a href="<?= base_url('omset_bulanan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary"></iconify-icon>
                <span class="fw-medium d-none d-md-inline"><?= date('F Y', mktime(0, 0, 0, $bulanSebelum, 1, $tahunSebelum)) ?></span>
                <span class="fw-medium d-md-none">Sebelumnya</span>
            </a>

            <div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-pill bg-primary-subtle bg-opacity-10 border-primary-subtle">
                <iconify-icon icon="solar:calendar-bold-duotone" class="text-primary fs-5"></iconify-icon>
                <span class="fs-4 fw-bold text-primary"><?= esc($periodeLabel) ?></span>
            </div>

            <?php if (!$isBulanBerjalan): ?>
                <?php $bulanSesudah = (int)date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <?php $tahunSesudah = (int)date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <a href="<?= base_url('omset_bulanan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                    class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                    <span class="fw-medium d-none d-md-inline"><?= date('F Y', mktime(0, 0, 0, $bulanSesudah, 1, $tahunSesudah)) ?></span>
                    <span class="fw-medium d-md-none">Berikutnya</span>
                    <iconify-icon icon="solar:arrow-right-bold-duotone" class="text-primary"></iconify-icon>
                </a>
            <?php else: ?>
                <span class="btn btn-light text-muted border disabled d-inline-flex align-items-center gap-2 py-2 px-3 opacity-50" title="Bulan berikutnya belum tersedia">
                    <span class="fw-medium d-none d-md-inline">Berikutnya</span>
                    <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter Toolbar (For Authorized Roles) -->
<?php if (in_array($id_jabatan, [1, 0, 34, 40])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-3 text-dark">Pilih Unit Cabang</label>
                    <select name="unit" class="form-select bg-white" onchange="this.form.submit()">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                <?= $u['NAMA_UNIT'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-3 text-dark">Bulan Pelaporan</label>
                    <select name="bulan" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-3 text-dark">Tahun</label>
                    <select name="tahun" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = date('Y'); $i >= date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Top Performance & Statistics Cards Grid -->
<div class="row g-4 mb-4">
    <!-- Fast Moving Service -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary-subtle text-primary mb-2 px-2.5 py-1 fw-semibold">Fast Moving Service</span>
                        <h4 class="fw-bold mb-1 text-dark"><?= esc($bestsellerproduct->keyword_hp ?? '-') ?></h4>
                        <span class="fs-3 text-muted">Total Terjual: <strong class="text-dark"><?= number_format($bestsellerproduct->total ?? 0, 0, ',', '.') ?></strong></span>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:medal-ribbons-star-bold-duotone" width="36" height="36"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Best Seller Sparepart -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-info-subtle text-info mb-2 px-2.5 py-1 fw-semibold">Sparepart Best Seller</span>
                        <h4 class="fw-bold mb-1 text-dark"><?= esc($bestseller->nama_barang ?? '-') ?></h4>
                        <span class="fs-3 text-muted">Total Terjual: <strong class="text-dark"><?= number_format($bestseller->total_penjualan ?? 0, 0, ',', '.') ?></strong></span>
                    </div>
                    <div class="p-3 bg-info-subtle rounded-3 text-info d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:box-minimalistic-bold-duotone" width="36" height="36"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pelanggan Masuk -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold tracking-wider d-block mb-1">Total Pelanggan Masuk</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= number_format($pelanggan_bulan ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted"><?= esc($periodeLabel) ?> · <?= number_format($countService ?? 0) ?> service / <?= number_format($countSales ?? 0) ?> penjualan</small>
                    </div>
                    <div class="p-3 bg-secondary-subtle rounded-3 text-dark d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:user-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Sparepart Keluar -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold tracking-wider d-block mb-1">Total Sparepart Keluar</span>
                        <h3 class="fw-bold mb-1 text-dark"><?= number_format($sparepart_keluar ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">Periode <?= esc($periodeLabel) ?></small>
                    </div>
                    <div class="p-3 bg-warning-subtle rounded-3 text-warning d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:archive-up-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Omset Hari Ini -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0 bg-success-subtle bg-opacity-10 border-success-subtle">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-success fw-bold tracking-wider d-block mb-1">Omset Hari Ini</span>
                        <h3 class="fw-bold mb-1 text-success">Rp <?= number_format($omset_hari_ini ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">Akumulasi pendapatan hari ini</small>
                    </div>
                    <div class="p-3 bg-success-subtle rounded-3 text-success d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:chart-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Omset Bulan Terpilih -->
    <div class="col-md-6 col-lg-6">
        <div class="card border shadow-none h-100 mb-0 bg-primary-subtle bg-opacity-10 border-primary-subtle">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-primary fw-bold tracking-wider d-block mb-1">Total Omset Bulan Ini</span>
                        <h3 class="fw-bold mb-1 text-primary">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">
                            Periode <?= esc($periodeLabel) ?> ·
                            <?php if ($pertumbuhan_omset > 0): ?>
                                <span class="text-success fw-semibold"><iconify-icon icon="solar:arrow-up-bold-duotone"></iconify-icon> +<?= number_format($pertumbuhan_omset, 1) ?>%</span>
                            <?php elseif ($pertumbuhan_omset < 0): ?>
                                <span class="text-danger fw-semibold"><iconify-icon icon="solar:arrow-down-bold-duotone"></iconify-icon> <?= number_format($pertumbuhan_omset, 1) ?>%</span>
                            <?php else: ?>
                                <span class="text-muted">±0%</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Perbandingan Bulan Sebelumnya -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold tracking-wider d-block mb-1">Bulan Sebelumnya</span>
                        <h4 class="fw-bold mb-1 text-dark">Rp <?= number_format($omset_bulan_lalu ?? 0, 0, ',', '.') ?></h4>
                        <small class="text-muted"><?= esc($periodeLaluLabel) ?> · Selisih <?= $selisih_omset >= 0 ? '+' : '' ?>Rp <?= number_format(abs($selisih_omset ?? 0), 0, ',', '.') ?></small>
                    </div>
                    <div class="p-3 bg-secondary-subtle rounded-3 text-dark d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:history-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rata-rata & Hari Terbaik -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold tracking-wider d-block mb-1">Rata-rata / Hari</span>
                        <h4 class="fw-bold mb-1 text-dark">Rp <?= number_format($omset_rata_rata ?? 0, 0, ',', '.') ?></h4>
                        <small class="text-muted">Berdasarkan <?= date('t', mktime(0, 0, 0, $bulan, 1, $tahun)) ?> hari</small>
                    </div>
                    <div class="p-3 bg-info-subtle rounded-3 text-info d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:calculator-minimalistic-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hari dengan Omset Tertinggi -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold tracking-wider d-block mb-1">Hari Omset Tertinggi</span>
                        <h4 class="fw-bold mb-1 text-dark"><?= $hariTerbaik ? date('d F Y', strtotime($hariTerbaik)) : '-' ?></h4>
                        <small class="text-muted"><?= $hariTerbaik ? 'Rp ' . number_format($omsetTerbaik ?? 0, 0, ',', '.') : 'Belum ada data' ?></small>
                    </div>
                    <div class="p-3 bg-danger-subtle rounded-3 text-danger d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:medal-star-bold-duotone" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HPP Metrics -->
    <div class="col-md-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Total HPP</span>
                <h5 class="fw-bold text-dark mb-0">Rp <?= number_format($hpp ?? 0, 0, ',', '.') ?></h5>
                <small class="text-muted"><?= esc($periodeLabel) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Value</span>
                <h5 class="fw-bold text-dark mb-0">Rp <?= number_format($hpp_global ?? 0, 0, ',', '.') ?></h5>
                <small class="text-muted"><?= esc($periodeLabel) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Bulan Pelaporan</span>
                <h5 class="fw-bold text-dark mb-0"><?= esc($periodeLabel) ?></h5>
                <small class="text-muted"><?= $isBulanBerjalan ? 'Bulan berjalan' : 'Periode lampau' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Date Filtering Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium fs-3">Dari Tanggal</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium fs-3">Sampai Tanggal</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" onclick="filterData()">
                    <iconify-icon icon="solar:filter-bold-duotone" width="18" height="18"></iconify-icon>
                    Filter Grafik & Tabel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-semibold mb-1">Grafik Omset Harian</h5>
                <small class="text-muted">Tren pendapatan per hari untuk bulan <?= esc($periodeLabel) ?></small>
            </div>
            <div class="p-2 bg-light rounded-2 text-primary">
                <iconify-icon icon="solar:graph-up-bold-duotone" width="24" height="24"></iconify-icon>
            </div>
        </div>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="chartOmset"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Table Section -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-semibold mb-1">Detail Omset Harian</h5>
                <small class="text-muted">Rincian pendapatan per tanggal bulan <?= esc($periodeLabel) ?></small>
            </div>
            <div class="p-2 bg-light rounded-2 text-primary">
                <iconify-icon icon="solar:bill-list-bold-duotone" width="24" height="24"></iconify-icon>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover border mb-0" id="tableOmset">
                <thead class="table-light text-dark fs-4">
                    <tr>
                        <th width="8%" class="text-center">No</th>
                        <th>Tanggal</th>
                        <th class="text-end">Omset</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($listHari as $hari): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td class="fw-medium text-dark"><?= date('d F Y', strtotime($hari['tanggal'])) ?></td>
                            <td class="text-end fw-semibold">
                                <?php if ($hari['total'] != 0): ?>
                                    <span class="<?= $hari['total'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $hari['total'] < 0 ? '-Rp ' . number_format(abs($hari['total']), 0, ',', '.') : 'Rp ' . number_format($hari['total'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Rp 0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end fw-bold text-dark">Total Bulan Ini</th>
                        <th class="text-end text-primary fw-bold fs-4" id="totalOmset">
                            Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const rawData = [
        <?php foreach ($listHari as $item): ?> {
                tanggal: "<?= $item['tanggal'] ?>",
                label: "<?= date('d M', strtotime($item['tanggal'])) ?>",
                total: <?= $item['total'] ?? 0 ?>
            },
        <?php endforeach; ?>
    ];

    const ctx = document.getElementById('chartOmset').getContext('2d');
    let chartOmset = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Omset Harian',
                data: [],
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.05)',
                borderColor: '#0d6efd',
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw || 0;
                            return ' Omset: Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                            }
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    renderChart(rawData);

    function renderChart(data) {
        chartOmset.data.labels = data.map(item => item.label);
        chartOmset.data.datasets[0].data = data.map(item => item.total);
        chartOmset.update();
    }

    function filterData() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        let filtered = rawData;

        if (startDate && endDate) {
            filtered = rawData.filter(item => {
                return item.tanggal >= startDate && item.tanggal <= endDate;
            });
        }

        renderChart(filtered);
        updateTable(filtered);
    }

    function updateTable(data) {
        let tbody = '';
        let totalBulan = 0;

        data.forEach((item, index) => {
            let val = parseInt(item.total) || 0;
            totalBulan += val;

            let formattedDate = new Date(item.tanggal).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });

            tbody += `
                <tr>
                    <td class="text-center text-muted">${index + 1}</td>
                    <td class="fw-medium text-dark">${formattedDate}</td>
                    <td class="text-end fw-semibold">
                        <span class="${val > 0 ? 'text-success' : (val < 0 ? 'text-danger' : 'text-muted')}">
                            ${val < 0 ? '-Rp ' : 'Rp '} ${new Intl.NumberFormat('id-ID').format(Math.abs(val))}
                        </span>
                    </td>
                </tr>
            `;
        });

        document.querySelector('#tableOmset tbody').innerHTML = tbody || '<tr><td colspan="3" class="text-center text-muted py-3">Tidak ada data untuk rentang tanggal ini</td></tr>';
        document.getElementById('totalOmset').innerHTML = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalBulan);
    }
</script>
