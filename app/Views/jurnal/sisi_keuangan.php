<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Posisi Keuangan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Keuangan</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Posisi Keuangan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="alert <?= $reconciliation['selisih'] === 0 && $reconciliation['selisih_jurnal'] === 0 && $reconciliation['akun_tidak_terklasifikasi'] === [] && $reconciliation['jumlah_akun'] > 0 ? 'alert-info' : 'alert-warning' ?> mx-4">
    <strong>Rekonsiliasi Neraca indikatif</strong> (saldo jurnal sampai <?= esc($reconciliation_date) ?>):
    Aset Rp <?= number_format($reconciliation['aset'], 0, ',', '.') ?>;
    Kewajiban Rp <?= number_format($reconciliation['kewajiban'], 0, ',', '.') ?>;
    Ekuitas Rp <?= number_format($reconciliation['ekuitas'], 0, ',', '.') ?>;
    Laba berjalan Rp <?= number_format($reconciliation['laba_berjalan'], 0, ',', '.') ?>;
    Selisih Rp <?= number_format($reconciliation['selisih'], 0, ',', '.') ?>.
    <?php if ($reconciliation['selisih_jurnal'] !== 0): ?>
        Jurnal tidak seimbang Rp <?= number_format($reconciliation['selisih_jurnal'], 0, ',', '.') ?>.
    <?php endif; ?>
    <?php if ($reconciliation['akun_tidak_terklasifikasi'] !== []): ?>
        Akun belum terklasifikasi: <?= esc(implode(', ', $reconciliation['akun_tidak_terklasifikasi'])) ?>.
    <?php endif; ?>
    <strong>Belum final</strong> sampai saldo awal, nilai persediaan, dan transaksi antarcabang diverifikasi.
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
        <h5 class="mb-0">Ringkasan Posisi Keuangan per Parent Akun</h5>
    </div>
    <div style="display: flex; justify-content: left;">

        <a href="#" onclick="cetakPDF()" class="btn btn-danger ml-2"
            style="width: 150px;  margin-right: 20px; margin-left: 20px;">Cetak PDF</a>

        <form action="<?= base_url('sisi_keuangan/export_excel') ?>" method="get" target="_blank">
            <input type="hidden" name="startDate" value="<?= esc($tanggal_awal) ?>">
            <input type="hidden" name="endDate" value="<?= esc($tanggal_akhir) ?>">
            <input type="hidden" name="filterUnit" value="<?= esc($id_unit) ?>">

            <input type="hidden" name="showZeroSaldo" id="excelShowZeroSaldo" value="1">
            <input type="hidden" name="showChildren" id="excelShowChildren" value="1">

            <button type="submit" class="btn btn-success ">Export Excel</button>
        </form>
    </div>

    <form method="get" class="mb-4" style="margin-left: 20px; margin-top: 20px;">
        <div style="display: flex; gap:40px">
            <div style="display: grid;">
                <label>Tanggal Awal:</label>
                <input type="date" name="startDate" class="form-control" value="<?= esc($tanggal_awal) ?>">
            </div>

            <div style="display: grid;">
                <label class="ml-2">Tanggal Akhir:</label>
                <input type="date" name="endDate" class="form-control" value="<?= esc($tanggal_akhir) ?>">
            </div>
            <div style="display: grid;">
                <label class="ml-2">Unit:</label>
                <select name="filterUnit" class="form-control">
                    <option value="">Semua Unit</option>
                    <?php foreach ($data_unit as $u): ?>
                        <option value="<?= $u->idunit ?>" <?= ($id_unit == $u->idunit) ? 'selected' : '' ?>>
                            <?= $u->NAMA_UNIT ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"
                style="width: 150px; height:60px; margin-top: 20px;">Tampilkan</button>
        </div>
        <div style="display: flex; justify-content: left; padding-left: 20px; margin-top: 20px;">
            <div class="form-check form-switch ms-4 mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="toggleZeroSaldo" checked>
                <label class="form-check-label" for="toggleZeroSaldo">Tampilkan Saldo 0</label>
            </div>

            <div class="form-check form-switch ms-4 mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="toggleChildren" checked>
                <label class="form-check-label" for="toggleChildren">Tampilkan Akun Anak</label>
            </div>
        </div>

        <input type="hidden" name="showZeroSaldo" id="inputShowZeroSaldo" value="1">
        <input type="hidden" name="showChildren" id="inputShowChildren" value="1">

    </form>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-5 fw-semibold mb-0">Kode Parent</h6>
                    </th>
                    <th>
                        <h6 class="fs-5 fw-semibold mb-0">Nama Akun Parent</h6>
                    </th>
                    <th class="text-end">
                        <h6 class="fs-5 fw-semibold mb-0">Debit Kredit</h6>
                    </th>
                </tr>
            </thead>
            <tbody>

                <!-- Grandparent Row (ASET) -->
                <?php if (!empty($data_grand_parent)):
                    $grandSaldo = $data_grand_parent->total_debet - $data_grand_parent->total_kredit;
                    $grandSaldoDisplay = number_format(abs($grandSaldo), 0, ',', '.');
                    $grandSaldoClass = $grandSaldo < 0 ? 'text-danger' : '';
                ?>
                    <tr class="table-dark fw-bold <?= ($grandSaldo == 0 ? 'saldo-zero' : '') ?>">
                        <td><?= esc($data_grand_parent->no_akun) ?></td>
                        <td><?= esc($data_grand_parent->nama_akun) ?></td>
                        <td class="text-end <?= $grandSaldoClass ?>"><?= $grandSaldoDisplay ?></td>
                    </tr>
                <?php endif; ?>

                <!-- Parent + Child Rows -->
                <?php if (!empty($data_parent)): ?>
                    <?php foreach ($data_parent as $row):
                        $saldo = $row['total_debet'] - $row['total_kredit'];
                        $saldoDisplay = number_format(abs($saldo), 0, ',', '.');
                        $saldoClass = $saldo < 0 ? 'text-danger' : '';
                    ?>
                        <tr class="table-light fw-bold <?= ($saldo == 0 ? 'saldo-zero' : '') ?>">
                            <td><?= esc($row['parent_no_akun']) ?></td>
                            <td><?= esc($row['parent_nama_akun']) ?></td>
                            <td class="text-end <?= $saldoClass ?>"><?= $saldoDisplay ?></td>
                        </tr>

                        <?php if (!empty($row['children'])): ?>
                            <?php foreach ($row['children'] as $child):
                                $childSaldo = $child->total_debet - $child->total_kredit;
                                $childDisplay = number_format(abs($childSaldo), 0, ',', '.');
                                $childClass = $childSaldo < 0 ? 'text-danger' : '';
                            ?>
                                <tr class="<?= ($childSaldo == 0 ? 'saldo-zero' : '') ?> child-row">
                                    <td><?= esc($child->no_akun) ?></td>
                                    <td class="ps-4">↳ <?= esc($child->nama_akun) ?></td>
                                    <td class="text-end <?= $childClass ?>"><?= $childDisplay ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data keuangan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>


            <?php if (!empty($data_grand_parent)):
                $grandSaldo = $data_grand_parent->total_debet - $data_grand_parent->total_kredit;
                $grandSaldoDisplay = number_format(abs($grandSaldo), 0, ',', '.');
                $grandSaldoClass = $grandSaldo < 0 ? 'text-danger' : '';
            ?>
                <tfoot>
                    <tr class="fw-bold text-dark">
                        <td colspan="2" class="text-end">Total Grandparent (<?= esc($data_grand_parent->nama_akun) ?>)</td>
                        <td class="text-end fw-semibold <?= $grandSaldoClass ?>"><?= $grandSaldoDisplay ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>


<script>
    function cetakPDF() {
        const startDate = document.querySelector('[name="startDate"]').value;
        const endDate = document.querySelector('[name="endDate"]').value;
        const unit = document.querySelector('[name="filterUnit"]').value;
        const showZero = document.getElementById('toggleZeroSaldo').checked ? 1 : 0;
        const showChildren = document.getElementById('toggleChildren').checked ? 1 : 0;

        const params = new URLSearchParams({
            startDate: startDate,
            endDate: endDate,
            filterUnit: unit,
            showZeroSaldo: showZero,
            showChildren: showChildren
        });

        const url = '<?= base_url('cetak/posisi_keuangan') ?>?' + params.toString();
        window.open(url, '_blank');
    }
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleSaldo = document.getElementById('toggleZeroSaldo');
        const toggleChildren = document.getElementById('toggleChildren');

        function applyFilters() {
            const showZero = toggleSaldo.checked;
            const showChildren = toggleChildren.checked;

            document.querySelectorAll('tbody tr').forEach(row => {
                let isZero = row.classList.contains('saldo-zero');
                let isChild = row.classList.contains('child-row');

                // Default tampil
                let show = true;

                // Jika saldo zero tidak ditampilkan → sembunyikan baris saldo zero
                if (!showZero && isZero) show = false;

                // Jika anak akun tidak ditampilkan → sembunyikan row child
                if (!showChildren && isChild) show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        toggleSaldo.addEventListener('change', applyFilters);
        toggleChildren.addEventListener('change', applyFilters);

        applyFilters();
    });
</script>
