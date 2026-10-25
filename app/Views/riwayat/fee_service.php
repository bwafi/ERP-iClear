<!-- Card Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Omset Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Service</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Omset Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>
    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2"></div>
    </div>

    <!-- Filter + Export -->
    <form action="<?= base_url('laba_service/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger" style="margin-left:24px;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right:8px;"></iconify-icon>
            Export
        </button>
        <br><br>
        <div class="mb-3 px-4">
            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline" style="width:auto;"
                onchange="filterData()">
            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline" style="width:auto;"
                onchange="filterData()">
            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row mx-3">
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-dark text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total HPP</h4>
                        <h5 class="text-white" id="totalHPP">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-primary text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total Service</h4>
                        <h5 class="text-white" id="totalService">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-danger text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total Diskon</h4>
                        <h5 class="text-white" id="totalDiskon">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-info text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total Laba</h4>
                        <h5 class="text-white" id="totalLaba">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-warning text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">HPP Klaim</h4>
                        <h5 class="text-white" id="totalHppKlaim">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Teknisi</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Omset</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Detail</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): 
                $groups = [];
                foreach ($service as $row) {
                    $groups[$row->nama_teknisi][] = $row;
                }
            ?>
                <?php foreach ($groups as $nama_teknisi => $items): 
                $modalId = 'modal_' . md5($nama_teknisi);
                $totalLabaTeknisi = array_sum(array_column($items, 'omset'));
            ?>
                <tr class="teknisi-row" data-teknisi="<?= md5($nama_teknisi) ?>">
                    <td><?= esc($nama_teknisi) ?></td>
                    <td class="total-laba-per-teknisi" data-total="<?= $totalLabaTeknisi ?>">Rp.
                        <?= number_format($totalLabaTeknisi, 0, ',', '.') ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                            data-bs-target="#<?= $modalId ?>">Detail</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">Tidak ada data</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- HPP Klaim: loss mengikuti tanggal_hpp_klaim, bukan tanggal service. -->
    <div class="card w-100 position-relative overflow-hidden mb-4 mx-4" style="width: auto;">
        <div class="card-body">
            <h5 class="fw-semibold mb-3">HPP Klaim</h5>
            <div class="table-responsive">
                <table class="table border text-nowrap mb-0 align-middle" id="hpp_klaim_table">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th>Tanggal Klaim</th>
                            <th>No Service</th>
                            <th>Nama Teknisi</th>
                            <th>Barang</th>
                            <th class="text-end">HPP</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hpp_klaim ?? []) as $loss): ?>
                        <tr data-teknisi="<?= md5((string) ($loss->nama_teknisi ?? '')) ?>"
                            data-tanggal="<?= esc((string) $loss->tanggal_hpp_klaim, 'attr') ?>">
                            <td><?= date('d-m-Y', strtotime((string) $loss->tanggal_hpp_klaim)) ?></td>
                            <td><?= esc($loss->no_service) ?></td>
                            <td><?= esc($loss->nama_teknisi ?? '-') ?></td>
                            <td><?= esc($loss->nama_barang ?? '-') ?></td>
                            <td class="text-end">Rp. <?= number_format((float) $loss->hpp, 0, ',', '.') ?></td>
                            <td class="text-end"><?= (int) $loss->qty ?></td>
                            <td class="text-end hpp-klaim-total">Rp. <?= number_format((float) $loss->total, 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($hpp_klaim)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada HPP klaim pada periode ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">Total HPP Klaim:</th>
                            <th class="text-end" id="hppKlaimSectionTotal">Rp. <?= number_format((float) ($total_hpp_klaim ?? 0), 0, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Details -->
    <?php if (!empty($groups)): ?>
    <?php foreach ($groups as $nama => $items): 
$modalId = 'modal_' . md5($nama);
$tableId = 'datatable_' . md5($nama);
?>
    <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-labelledby="<?= $modalId ?>Label"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Service - <?= esc($nama) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 datatable-modal" id="<?= $tableId ?>">
                            <thead>
                                <tr>
                                    <th>No Service</th>
                                    <th>Tanggal Masuk</th>
                                    <th>Jenis Service</th>
                                    <th>Total HPP</th>
                                    <th>Total Service</th>
                                    <th>Total Diskon</th>
                                    <th>omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $r): ?>
                                <tr>
                                    <td><?= esc($r->no_service) ?></td>
                                    <td><?= date('d-m-Y', strtotime($r->created_at)) ?></td>
                                    <td>
                                        <?php 
                                        $isGaransi = !empty($r->service_by_garansi) || ($r->sub_total_garansi ?? 0) > 0;
                                        echo $isGaransi ? 'Garansi' : 'Reguler';
                                    ?>
                                    </td>
                                    <td>Rp. <?= number_format($r->total_hpp_penjualan,0,',','.') ?></td>
                                    <td>Rp. <?= number_format($r->total_service,0,',','.') ?></td>
                                    <td>Rp. <?= number_format($r->total_diskon,0,',','.') ?></td>
                                    <td>Rp. <?= number_format($r->omset,0,',','.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>


    <!-- JS -->
    <script>
    function toDateFormat(d) {
        const y = d.getFullYear(),
            m = String(d.getMonth() + 1).padStart(2, '0'),
            day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    window.onload = function() {
        const now = new Date();
        const prev = new Date(now);
        prev.setDate(now.getDate() - 15);
        document.getElementById('startDate').value = toDateFormat(prev);
        document.getElementById('endDate').value = toDateFormat(now);
        filterData();
        updateCardTotals();
        updateLabaPerTeknisi();

        document.querySelectorAll('.datatable-modal').forEach(table => {
            new DataTable(table);
        });
    };

    function filterData() {
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        const sd = s ? new Date(s) : null;
        const ed = e ? new Date(e) : null;

        document.querySelectorAll('.modal table tbody tr').forEach(r => {
            const dateText = r.children[1]?.textContent;
            if (!dateText) return;
            const [d, mo, y] = dateText.split('-');
            const dt = new Date(`${y}-${mo}-${d}`);
            r.style.display = ((sd && dt < sd) || (ed && dt > ed)) ? 'none' : '';
        });

        document.querySelectorAll('#hpp_klaim_table tbody tr[data-tanggal]').forEach(r => {
            const dt = new Date(r.getAttribute('data-tanggal') + 'T00:00:00');
            r.style.display = ((sd && dt < sd) || (ed && dt > ed)) ? 'none' : '';
        });

        updateCardTotals();
        updateLabaPerTeknisi();
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        filterData();
    }

    function formatRupiah(number) {
        return 'Rp. ' + number.toLocaleString('id-ID');
    }

    function updateCardTotals() {
        let totalHPP = 0,
            totalService = 0,
            totalDiskon = 0,
            totalLaba = 0,
            totalHppKlaim = 0;

        document.querySelectorAll('.modal table tbody tr').forEach(r => {
            if (r.style.display === 'none') return;
            totalHPP += parseInt(r.children[3].textContent.replace(/[^\d]/g, '')) || 0;
            totalService += parseInt(r.children[4].textContent.replace(/[^\d]/g, '')) || 0;
            totalDiskon += parseInt(r.children[5].textContent.replace(/[^\d]/g, '')) || 0;
            totalLaba += parseInt(r.children[6].textContent.replace(/[^\d]/g, '')) || 0;
        });

        document.querySelectorAll('#hpp_klaim_table tbody tr[data-tanggal]').forEach(r => {
            if (r.style.display === 'none') return;
            totalHppKlaim += parseInt(r.querySelector('.hpp-klaim-total').textContent.replace(/[^\d]/g, '')) || 0;
        });

        document.getElementById('totalHPP').textContent = formatRupiah(totalHPP);
        // Kartu omset net; baris per service tetap bruto sesuai spec.
        document.getElementById('totalService').textContent = formatRupiah(totalService - totalHppKlaim);
        document.getElementById('totalDiskon').textContent = formatRupiah(totalDiskon);
        document.getElementById('totalHppKlaim').textContent = formatRupiah(totalHppKlaim);
        document.getElementById('hppKlaimSectionTotal').textContent = formatRupiah(totalHppKlaim);
        document.getElementById('totalLaba').textContent = formatRupiah(totalLaba - totalHppKlaim);
    }

    function updateLabaPerTeknisi() {
        const lossByTeknisi = {};
        document.querySelectorAll('#hpp_klaim_table tbody tr[data-tanggal]').forEach(r => {
            if (r.style.display === 'none') return;
            const key = r.getAttribute('data-teknisi');
            lossByTeknisi[key] = (lossByTeknisi[key] || 0) +
                (parseInt(r.querySelector('.hpp-klaim-total').textContent.replace(/[^\d]/g, '')) || 0);
        });

        document.querySelectorAll('.teknisi-row').forEach(row => {
            const teknisiId = row.getAttribute('data-teknisi');
            const modal = document.getElementById('modal_' + teknisiId);
            let subtotal = 0;

            modal.querySelectorAll('tbody tr').forEach(r => {
                if (r.style.display === 'none') return;
                subtotal += parseInt(r.children[6].textContent.replace(/[^\d]/g, '')) || 0;
            });

            subtotal -= lossByTeknisi[teknisiId] || 0;

            const cell = row.querySelector('.total-laba-per-teknisi');
            cell.textContent = formatRupiah(subtotal);
            cell.setAttribute('data-total', subtotal);
        });
    }
    </script>