<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Penilaian KPI</h4>
            <small class="text-muted">Pilih pegawai untuk melihat detail KPI dan memberikan nilai manual.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active">Penilaian KPI</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cari Nama</label>
                <input type="text" class="form-control" id="searchNama" placeholder="Ketik nama pegawai...">
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tblKpi">
                <thead class="table-light">
                    <tr>
                        <th>Nama Pegawai</th>
                        <th>Jabatan</th>
                        <th>Unit / Cabang</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($list_karyawan)) : ?>
                        <?php foreach ($list_karyawan as $k) : ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($k['NAMA_AKUN']) ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= esc($k['NAMA_JABATAN'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><?= esc($k['NAMA_UNIT'] ?? 'Unit ' . $k['ID_UNIT']) ?></td>
                                <td class="text-center">
                                    <a href="<?= base_url('/penilaian/kpi/detail/' . $k['ID_AKUN'] . '?bulan=' . $bulan . '&tahun=' . $tahun) ?>"
                                        class="btn btn-sm btn-primary">
                                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                Tidak ada pegawai dalam lingkup akses Anda.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('searchNama').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tblKpi tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
