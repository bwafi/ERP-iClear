<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">

        <h4 class="fw-semibold mb-0">
            Gaji
        </h4>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none"
                        href="<?= base_url('/') ?>">
                        Dashboard
                    </a>
                </li>

                <li class="breadcrumb-item active">
                    Gaji
                </li>
            </ol>
        </nav>

    </div>
</div>

<div class="card shadow-sm border-0">

    <div class="card-body">

        <form method="get">

            <div class="row">

                <div class="col-md-4">

                    <label class="form-label">
                        Nama Karyawan
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= $karyawan['NAMA_AKUN'] ?>"
                        readonly>

            </div>

        </form>

    </div>

</div>

<div class="row mt-4">

    <!-- BARIS 1 -->
    <div class="col-md-3 mb-4">
        <div class="card bg-primary-subtle border-0">
            <div class="card-body">
                <h6>Skor Kinerja</h6>
                <h3 class="fw-bold text-success">
                    <?= $skor_total ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card border-0">
            <div class="card-body">
                <h6>Gaji Pokok</h6>
                <h3 class="fw-bold text-success">
                    Rp <?= number_format($gaji_pokok,0,',','.') ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success-subtle border-0">
            <div class="card-body">
                <h6>Tunjangan Kinerja</h6>
                <h3 class="fw-bold text-success">
                    Rp <?= number_format($tunjangan_kinerja,0,',','.') ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success-subtle border-0">
            <div class="card-body">
                <h6>Tunjangan Absen</h6>
                <h3 class="fw-bold text-success">
                    Rp <?= number_format($tunjangan_absen,0,',','.') ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- BARIS 2 -->
    <div class="col-md-6"></div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success-subtle border-0">
            <div class="card-body">
                <h6>Tunjangan Penempatan</h6>
                <h3 class="fw-bold text-success">
                    Rp <?= number_format($tunjangan_penempatan->tunjangan_penempatan,0,',','.') ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success-subtle border-0">
            <div class="card-body">
                <h6>Insentif</h6>
                <h3 class="fw-bold text-success">
                    Rp <?= number_format($insentif,0,',','.') ?>
                </h3>
            </div>
        </div>
    </div>

</div>

<div class="row mt-4">

    <div class="col-md-12">

        <div class="card border-0 bg-success-subtle">

            <div class="card-body">

                <h5 class="mb-2">
                    Total Diterima
                </h5>

                <h2 class="fw-bold text-success">
                    Rp <?= number_format($gaji,0,',','.') ?>
                </h2>

                <small>
                    *Note : Total Diterima Belum Termasuk Komisi dan insentif
                </small>
            </div>

        </div>

    </div>

</div>

<div class="card mt-4 shadow-sm border-0">

    <div class="card-header">

        <h5 class="mb-0">
            Detail Penilaian Kinerja
        </h5>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Kriteria</th>
                        <th>Bobot</th>
                        <th>Nilai</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if(!empty($detail_kpi)): ?>

                        <?php $no = 1; ?>

                        <?php foreach($detail_kpi as $kpi): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <?= $kpi['nama'] ?>
                                </td>

                                <td>
                                    <?= $kpi['bobot'] ?>
                                </td>

                                <td>

                                    <?php

                                    if($kpi['nilai'] >= 90){

                                        $badge = 'success';

                                    } elseif($kpi['nilai'] >= 75){

                                        $badge = 'warning';

                                    } else {

                                        $badge = 'danger';

                                    }

                                    ?>

                                    <span class="badge bg-<?= $badge ?>">
                                        <?= $kpi['nilai'] ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="4" class="text-center py-4">

                                Belum ada data KPI

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
<div class="card mt-4 shadow-sm border-0">

    <div class="card-header">

        <h5 class="mb-0">
            Detail Penilaian Absen
        </h5>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>No</th>
                        <th>Kriteria</th>
                        <th>Bobot</th>
                        <th>Nilai</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if(!empty($detail_absen)): ?>

                        <?php $no = 1; ?>

                        <?php foreach($detail_absen as $absen): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <?= $absen['nama'] ?>
                                </td>

                                <td>
                                    <?= $absen['bobot'] ?>
                                </td>

                                <td>

                                    <?php

                                    if($absen['nilai'] >= 90){

                                        $badge = 'success';

                                    } elseif($absen['nilai'] >= 75){

                                        $badge = 'warning';

                                    } else {

                                        $badge = 'danger';

                                    }

                                    ?>

                                    <span class="badge bg-<?= $badge ?>">
                                        <?= $absen['nilai'] ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="4" class="text-center py-4">

                                Belum ada data Absen

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>