<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Omset Bulanan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Omset Bulanan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
        <?php
        $bulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        ?>

        <h5 class="mb-0 fw-semibold">
            Laporan Bulan <?= $bulan[date('n')] ?>
        </h5>
        <?php if (in_array($id_jabatan, [1, 0, 35])): ?>

            <div class="row mt-3">
                <div class="col-md-4">

                    <form method="GET">

                        <label class="form-label">
                            Pilih Unit
                        </label>

                        <select
                            name="unit"
                            class="form-select"
                            onchange="this.form.submit()">

                            <?php foreach ($list_unit as $u): ?>

                                <option
                                    value="<?= $u['idunit'] ?>"
                                    <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>

                                    <?= $u['NAMA_UNIT'] ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </form>

                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- ===================== -->
    <!-- OMSET BULANAN -->
    <!-- ===================== -->

    <div class="row g-4 mt-1 px-4">
        <div class="col-md-12 col-lg-12">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div class="col-md-5 col-lg-5">
                            <h6 class="mb-1">Product Service Fast Moving</h6>

                            <h3 class="fw-bold mb-0">
                                <?= ($bestsellerproduct->keyword_hp) ?>
                            </h3>

                            <small class="text-muted">
                                Total Terjual : <?= number_format($bestsellerproduct->total ?? 0, 0, ',', '.') ?>
                            </small>
                        </div>

                        <div class="col-md-5 col-lg-5">
                            <h6 class="mb-1">Sparepart Best Seller</h6>

                            <h3 class="fw-bold mb-0">
                                <?= ($bestseller->nama_barang) ?>
                            </h3>

                            <small class="text-muted">
                                Total Terjual : <?= number_format($bestseller->total_penjualan ?? 0, 0, ',', '.') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:medal-ribbons-star-bold-duotone"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-6">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total Pelanggan Masuk Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                <?= number_format($pelanggan_bulan ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total pelanggan bulan <?= date('F Y') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:user-bold"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-6">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total Sparepart Keluar Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                <?= number_format($sparepart_keluar ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Sparepart bulan <?= date('F Y') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:archive-up-bold-duotone"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

        <!-- Omset Hari Ini -->
        <div class="col-md-6 col-lg-6">
            <div class="card bg-success-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Omset Hari Ini</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($omset_hari_ini ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Profit penjualan hari ini
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:chart-bold"
                            width="52"
                            class="text-success">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

        <!-- Omset Bulan Ini -->
        <div class="col-md-6 col-lg-6">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total Omset Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total profit bulan <?= date('F Y') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:wallet-money-bold"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-6">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total HPP</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($hpp ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total hpp bulan <?= date('F Y') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:wallet-money-bold"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-6">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">HPP Global</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($hpp_global ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                HPP Global bulan <?= date('F Y') ?>
                            </small>
                        </div>

                        <iconify-icon
                            icon="solar:wallet-money-bold"
                            width="52"
                            class="text-primary">
                        </iconify-icon>

                    </div>

                </div>
            </div>
        </div>

    </div>


    <!-- ===================== -->
    <!-- GRAFIK OMSET -->
    <!-- ===================== -->

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">

            <div class="row g-3 align-items-end">

                <div class="col-md-4">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" id="startDate" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" id="endDate" class="form-control">
                </div>

                <div class="col-md-4">
                    <button class="btn btn-primary w-100" onclick="filterData()">
                        Filter Data
                    </button>
                </div>

            </div>

        </div>
    </div>

    <div class="card mt-4 border-0 shadow-sm">


        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>
                    <h5 class="fw-semibold mb-1">
                        Grafik Omset Harian
                    </h5>

                    <small class="text-muted">
                        Omset per hari bulan <?= date('F Y') ?>
                    </small>
                </div>

                <iconify-icon
                    icon="solar:graph-up-bold"
                    width="34"
                    class="text-primary">
                </iconify-icon>

            </div>

            <canvas id="chartOmset" height="90"></canvas>

        </div>

    </div>

    <div class="card mt-4 border-0 shadow-sm">
        <div class="card border-0 shadow-sm mt-4">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div>
                        <h5 class="fw-semibold mb-1">
                            Detail Omset Harian
                        </h5>

                        <small class="text-muted">
                            Omset per hari bulan <?= date('F Y') ?>
                        </small>
                    </div>

                    <iconify-icon
                        icon="solar:bill-list-bold"
                        width="30"
                        class="text-primary">
                    </iconify-icon>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle table-bordered" id="tableOmset">

                        <thead class="table-light">

                            <tr>
                                <th width="10%">No</th>
                                <th>Tanggal</th>
                                <th class="text-end">Omset</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php $no = 1; ?>
                            <?php foreach ($listHari as $hari): ?>

                                <tr>

                                    <td><?= $no++ ?></td>

                                    <td>
                                        <?= date('d F Y', strtotime($hari['tanggal'])) ?>
                                    </td>

                                    <td class="text-end fw-semibold">

                                        <?php if ($hari['total'] != 0): ?>

                                            <span class="<?= $hari['total'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $hari['total'] < 0 ? '-Rp ' . number_format(abs($hari['total']), 0, ',', '.') : 'Rp ' . number_format($hari['total'], 0, ',', '.') ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Rp 0
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                        <tfoot class="table-light">

                            <tr>

                                <th colspan="2" class="text-end">
                                    Total Bulan Ini
                                </th>

                                <th class="text-end text-primary" id="totalOmset">
                                    Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?>
                                </th>

                            </tr>

                        </tfoot>

                    </table>

                </div>

            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // ==============================
    // DATA DARI PHP
    // ==============================

    const rawData = [
        <?php foreach ($listHari as $item): ?> {
                tanggal: "<?= $item['tanggal'] ?>",
                label: "<?= date('d', strtotime($item['tanggal'])) ?>",
                total: <?= $item['total'] ?? 0 ?>
            },
        <?php endforeach; ?>
    ];

    // ==============================
    // CHART
    // ==============================

    const ctx = document.getElementById('chartOmset');

    let chartOmset = new Chart(ctx, {

        type: 'line',

        data: {
            labels: [],
            datasets: [{
                label: 'Omset',
                data: [],
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4
            }]
        },

        options: {

            responsive: true,

            plugins: {
                legend: {
                    display: false
                }
            },

            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' +
                                new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });

    // ==============================
    // RENDER AWAL
    // ==============================

    renderChart(rawData);

    // ==============================
    // FUNCTION RENDER CHART
    // ==============================

    function renderChart(data) {
        chartOmset.data.labels = data.map(item => item.label);

        chartOmset.data.datasets[0].data =
            data.map(item => item.total);

        chartOmset.update();
    }

    // ==============================
    // FILTER DATA
    // ==============================

    function filterData() {
        const startDate =
            document.getElementById('startDate').value;

        const endDate =
            document.getElementById('endDate').value;

        let filtered = rawData;

        if (startDate && endDate) {
            filtered = rawData.filter(item => {

                return item.tanggal >= startDate &&
                    item.tanggal <= endDate;

            });
        }

        // UPDATE CHART
        renderChart(filtered);

        // UPDATE TABEL
        updateTable(filtered);
    }

    // ==============================
    // UPDATE TABLE
    // ==============================

    function updateTable(data) {
        let tbody = '';

        let totalBulan = 0;

        data.forEach((item, index) => {

            totalBulan += parseInt(item.total);

            tbody += `
            <tr>
                <td>${index + 1}</td>

                <td>${item.tanggal}</td>

                <td class="text-end fw-semibold">
                    <span class="${
                        item.total > 0
                        ? 'text-success'
                        : 'text-muted'
                    }">

                        Rp ${new Intl.NumberFormat('id-ID')
                            .format(item.total)}

                    </span>
                </td>
            </tr>
        `;
        });

        document.querySelector('#tableOmset tbody')
            .innerHTML = tbody;

        document.getElementById('totalOmset')
            .innerHTML =
            'Rp ' +
            new Intl.NumberFormat('id-ID')
            .format(totalBulan);
    }
</script>
