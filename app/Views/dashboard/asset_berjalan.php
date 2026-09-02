<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Asset Berjalan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Asset Berjalan</li>
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
        <?php if (in_array($id_jabatan, [1, 34, 35])): ?>

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
    <!-- Aset Berjalan -->
    <!-- ===================== -->

    <div class="row g-4 mt-1 px-4">

        <!-- Omset Hari Ini -->
        <div class="col-md-4 col-lg-4">
            <div class="card bg-success-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Omset Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Omset Bulan ini
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

        <div class="col-md-4 col-lg-4">
            <div class="card bg-success-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Pengeluaran Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Pengeluaran Bulan ini
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
        <div class="col-md-4 col-lg-4">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total Tanggungan Asset Bulan Ini</h6>

                            <h3 class="fw-bold mb-0">
                                <?php
                                $total = 0;

                                if ($selected_unit == 4) {
                                    $total = (($omset_bulan - $pengeluaran - $totalGajiUnit) * 20 / 100);
                                } elseif ($selected_unit == 3) {
                                    $total = 0;
                                } elseif (in_array($selected_unit, [1, 2])) {
                                    $total = date('d') * 355000;
                                }
                                ?>

                                Rp <?= number_format($total, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Tanggungan Asset Berjalan, bulan <?= date('F Y') ?>
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

        <div class="col-md-4 col-lg-4">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Total Gaji</h6>

                            <h3 class="fw-bold mb-0">

                                Rp <?= number_format($totalGajiUnit, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total gaji bulan <?= date('F Y') ?>
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

        <div class="col-md-4 col-lg-4">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Hak Cabang</h6>

                            <h3 class="fw-bold mb-0">
                                <?php
                                $total = 0;

                                if (in_array($selected_unit, [3, 4])) {
                                    $total = 60 * (($omset_bulan - $pengeluaran - $totalGajiUnit)) / 100;
                                } elseif (in_array($selected_unit, [1, 2])) {
                                    $total = 0;
                                }
                                ?>

                                Rp <?= number_format($total, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Hak Cabang bulan <?= date('F Y') ?>
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

        <div class="col-md-4 col-lg-4">
            <div class="card bg-primary-subtle border-0 shadow-none h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="mb-1">Tagihan Center</h6>

                            <h3 class="fw-bold mb-0">
                                <?php
                                $total = 0;

                                if (in_array($selected_unit, [3, 4])) {
                                    $total = 40 * (($omset_bulan - $pengeluaran - $totalGajiUnit)) / 100;
                                } elseif (in_array($selected_unit, [1, 2])) {
                                    $total = 0;
                                }
                                ?>

                                Rp <?= number_format($total, 0, ',', '.') ?>
                            </h3>

                            <small class="text-muted">
                                Total Tagihan center bulan <?= date('F Y') ?>
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

        <div class="col-md-12 col-lg-12">
        </div>
    </div>

</div>
