<!-- HEADER -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Kasir Bulanan</h4>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none"
                        href="<?= base_url('/') ?>">
                        Dashboard
                    </a>
                </li>

                <li class="breadcrumb-item active" aria-current="page">
                    Kasir Bulanan
                </li>
            </ol>
        </nav>
    </div>
</div>


<!-- CARD -->
<div class="card w-100 position-relative overflow-hidden">

    <!-- HEADER CARD -->
    <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>
            <h5 class="mb-1 fw-semibold">
                Laporan Hari : (<?= $tanggal; ?>)
            </h5>

            <?php if ($tutupkasir): ?>

                <small class="text-muted">

                    Status :
                    <span class="badge bg-success">
                        <?= $tutupkasir->status ?>
                    </span>

                    |

                    Ditutup Oleh :
                    <b><?= $tutupkasir->NAMA_AKUN ?? '-' ?></b>

                </small>

            <?php endif; ?>
        </div>

        <!-- FILTER -->
        <form method="get" class="d-flex gap-2 flex-wrap">

            <input type="date"
                name="tanggal"
                class="form-control"
                value="<?= $tanggal ?>">

            <select name="unit"
                class="form-select">

                <option value="1"
                    <?= ($selected_unit ?? '') == 1 ? 'selected' : '' ?>>
                    Probolinggo
                </option>

                <option value="2"
                    <?= ($selected_unit ?? '') == 2 ? 'selected' : '' ?>>
                    Jember
                </option>

                <option value="3"
                    <?= ($selected_unit ?? '') == 3 ? 'selected' : '' ?>>
                    Banyuwangi
                </option>

                <option value="4"
                    <?= ($selected_unit ?? '') == 4 ? 'selected' : '' ?>>
                    Pandaan
                </option>

            </select>

            <button type="submit"
                class="btn btn-primary">

                <iconify-icon icon="solar:calendar-search-bold">
                </iconify-icon>

                Filter

            </button>

        </form>

    </div>


    <?php if (!$tutupkasir): ?>

        <div class="card-body">
            <div class="alert alert-warning mb-0">
                Data tutup kasir tanggal
                <b><?= $tanggal ?></b>
                tidak ditemukan.
            </div>
        </div>

    <?php else: ?>


        <div class="card-body px-4 pt-4 pb-2">

            <!-- HIDDEN -->
            <input type="hidden" name="awal_cash"
                value="<?= $kas_awalcash ?? 0 ?>">

            <input type="hidden" name="awal_transfer"
                value="<?= $kas_awaltf ?? 0 ?>">

            <input type="hidden" name="akhir_cash"
                value="<?= $kas_akhircash ?? 0 ?>">

            <input type="hidden" name="akhir_transfer"
                value="<?= $kas_akhirtf ?? 0 ?>">

            <input type="hidden" name="pendapatan_cash"
                value="<?= $cash ?? 0 ?>">

            <input type="hidden" name="pendapatan_transfer"
                value="<?= $transfer ?? 0 ?>">

            <input type="hidden" name="pengeluaran_cash"
                value="<?= $pengeluarancash ?? 0 ?>">

            <input type="hidden" name="pengeluaran_transfer"
                value="<?= $pengeluarantf ?? 0 ?>">


            <!-- SALDO AWAL -->
            <div class="row g-4">

                <div class="col-md-6">
                    <div class="alert alert-primary border-0">

                        <h6 class="fw-semibold mb-1">
                            Saldo Awal Cash
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($kas_awalcash ?? 0, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>

                <div class="col-md-6">
                    <div class="alert alert-primary border-0">

                        <h6 class="fw-semibold mb-1">
                            Saldo Awal Bank
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($kas_awaltf ?? 0, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>

            </div>


            <!-- PENDAPATAN -->
            <div class="row g-4">

                <!-- CASH -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-warning-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Pendapatan Cash
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format($cash ?? 0, 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:money-bag-bold"
                                    width="42"
                                    class="text-warning">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>


                <!-- TRANSFER -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-success-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Pendapatan Transfer
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format($transfer ?? 0, 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:card-transfer-bold"
                                    width="42"
                                    class="text-success">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>


                <!-- TOTAL -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-primary-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Total Pendapatan
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format($total_pendapatan ?? 0, 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:wallet-money-bold"
                                    width="42"
                                    class="text-primary">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>

            </div>


            <!-- PENGELUARAN -->
            <div class="row g-4 mt-1">

                <!-- CASH -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-danger-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Pengeluaran Cash
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format($pengeluarancash ?? 0, 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:bill-list-bold"
                                    width="42"
                                    class="text-danger">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>


                <!-- TRANSFER -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-danger-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Pengeluaran Transfer
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format($pengeluarantf ?? 0, 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:bill-list-bold"
                                    width="42"
                                    class="text-danger">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>


                <!-- TOTAL -->
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-danger-subtle border-0 shadow-none">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-1">
                                        Total Pengeluaran
                                    </h6>

                                    <h4 class="fw-bold mb-0">
                                        Rp <?= number_format(($pengeluarancash ?? 0) + ($pengeluarantf ?? 0), 0, ',', '.') ?>
                                    </h4>

                                </div>

                                <iconify-icon
                                    icon="solar:bill-list-bold"
                                    width="42"
                                    class="text-danger">
                                </iconify-icon>

                            </div>

                        </div>

                    </div>
                </div>

            </div>


            <!-- SALDO AKHIR -->
            <div class="row g-4 mt-1">

                <div class="col-md-6">
                    <div class="alert alert-success border-0">

                        <h6 class="fw-semibold mb-1">
                            Saldo Akhir Cash
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($kas_akhircash ?? 0, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>

                <div class="col-md-6">
                    <div class="alert alert-success border-0">

                        <h6 class="fw-semibold mb-1">
                            Saldo Akhir Bank
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($kas_akhirtf ?? 0, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>

            </div>


            <!-- LACI & SELISIH -->
            <div class="row g-4">

                <!-- SALDO LACI -->
                <div class="col-md-6">
                    <div class="alert alert-warning border-0">

                        <h6 class="fw-semibold mb-1">
                            Saldo Laci
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($tutupkasir->cash_laci ?? 0, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>


                <!-- SELISIH -->
                <div class="col-md-6">

                    <?php
                    $selisih =
                        ($tutupkasir->cash_laci ?? 0)
                        - ($tutupkasir->akhir_cash ?? 0);
                    ?>

                    <div class="alert alert-info border-0">

                        <h6 class="fw-semibold mb-1">
                            Selisih
                        </h6>

                        <h4 class="mb-0 fw-bold">
                            Rp <?= number_format($selisih, 0, ',', '.') ?>
                        </h4>

                    </div>
                </div>

            </div>


            <!-- PRINT -->
            <div class="text-end mt-4">

                <!-- <a href="<?= base_url('/cetak-tutup-kasir/' . $tutupkasir->idtutupkasir) ?>"
                    target="_blank"
                    class="btn btn-primary px-4">

                    <iconify-icon
                        icon="solar:printer-bold"
                        width="20">
                    </iconify-icon>

                    Print

                </a> -->

            </div>

        </div>

    <?php endif; ?>

</div>