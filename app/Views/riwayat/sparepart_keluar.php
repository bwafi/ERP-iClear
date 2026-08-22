<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Sparepart Keluar</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Sparepart Keluar</li>
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
            Laporan Bulan <?= $bulan[date('n')]?>
        </h5>      
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

                            <?php foreach($list_unit as $u): ?>

                                <option
                                    value="<?= $u['idunit'] ?>"
                                    <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>

                                    <?= $u['NAMA_UNIT'] ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                        
                        <br>
                        
                        <label class="form-label">
                            Pilih Tanggal
                        </label>

                        <input
                            name="day"
                            class="form-select"
                            type="date"
                            value="<?= $selected_day ?? '' ?>"
                            onchange="this.form.submit()">

                    </form>

                </div>
            </div>
    </div>

    <!-- ===================== -->
    <!-- Aset Berjalan -->
    <!-- ===================== -->

    <div class="row g-4 mt-1 px-4">
    <div class="col-12">
        <div class="table-responsive">
            <table class="table table-bordered align-middle text-nowrap mb-0">
                <thead class="text-dark">
                    <tr>
                        <th>Kode Invoice</th>
                        <th>Tanggal</th>
                        <th>Nama Sparepart</th>
                        <th>Nama Unit</th>
                        <th>HPP</th>
                        <th>Sub Total</th>
                        <th width="120">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($data_penjualan)): ?>
                        <?php foreach ($data_penjualan as $no => $row): ?>
                            <tr>
                                <td><?= esc($row['kode_invoice']) ?></td>

                                <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>

                                <td><?= esc($row['nama_barang']) ?></td>

                                <td><?= esc($row['NAMA_UNIT']) ?></td>

                                <td>
                                    Rp <?= number_format($row['hpp_penjualan'], 0, ',', '.') ?>
                                </td>

                                <td>
                                    Rp <?= number_format($row['sub_total'], 0, ',', '.') ?>
                                </td>

                                <td>
                                    <button
                                        class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDetail<?= $no ?>">

                                        <iconify-icon
                                            icon="solar:folder-favourite-bookmark-broken"
                                            width="20">
                                        </iconify-icon>

                                        <span class="d-none d-md-inline">
                                            Lihat Detail
                                        </span>
                                    </button>
                                </td>
                            </tr>
                            <!-- Modal -->
                            <div class="modal fade"
                                id="modalDetail<?= $no ?>"
                                tabindex="-1">

                                <div class="modal-dialog">
                                    <div class="modal-content">

                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Detail Sparepart
                                            </h5>

                                            <button type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal">
                                            </button>
                                        </div>

                                        <div class="modal-body">
                                            <p><strong>Tanggal :</strong>
                                                <?= date('d-m-Y', strtotime($row['tanggal'])) ?>
                                            </p>
                                            
                                            <p><strong>Invoice :</strong>
                                                <?= esc($row['kode_invoice']) ?>
                                            </p>
                                            
                                            <p><strong>Id Detail :</strong>
                                                <?= esc($row['iddetail_penjualan']) ?>
                                            </p>
                                            
                                            <p><strong>Nama Sparepart :</strong>
                                                <?= esc($row['nama_barang']) ?>
                                            </p>

                                            <p><strong>Unit :</strong>
                                                <?= esc($row['NAMA_UNIT']) ?>
                                            </p>

                                            <p><strong>HPP :</strong>
                                                Rp <?= number_format($row['hpp_penjualan'], 0, ',', '.') ?>
                                            </p>

                                            <p><strong>Sub Total :</strong>
                                                Rp <?= number_format($row['sub_total'], 0, ',', '.') ?>
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    
</div>