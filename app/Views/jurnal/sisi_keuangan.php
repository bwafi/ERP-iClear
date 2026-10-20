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

<?php if ($warranty_reconciliation !== null): ?>
    <div class="alert <?= $warranty_reconciliation['tanpa_jurnal'] + $warranty_reconciliation['tidak_sesuai'] + $warranty_reconciliation['jurnal_tanpa_mutasi'] === 0 ? 'alert-info' : 'alert-danger' ?> mx-4">
        <strong>Rekonsiliasi pengganti garansi:</strong>
        <?= (int) $warranty_reconciliation['jumlah_mutasi'] ?> mutasi,
        HPP Rp <?= number_format($warranty_reconciliation['hpp_total'], 0, ',', '.') ?>;
        <?= (int) $warranty_reconciliation['tanpa_jurnal'] ?> tanpa jurnal,
        <?= (int) $warranty_reconciliation['tidak_sesuai'] ?> jurnal tidak sesuai,
        <?= (int) $warranty_reconciliation['jurnal_tanpa_mutasi'] ?> jurnal tanpa mutasi.
    </div>
<?php endif; ?>

<?php if ($supplier_credit_reconciliation !== null): ?>
    <div class="alert <?= $supplier_credit_reconciliation['alokasi_tidak_sesuai'] + $supplier_credit_reconciliation['jurnal_tidak_sesuai'] + $supplier_credit_reconciliation['referensi_yatim'] === 0 ? 'alert-info' : 'alert-danger' ?> mx-4">
        <strong>Rekonsiliasi kredit supplier (posisi kini):</strong>
        <?= (int) $supplier_credit_reconciliation['jumlah_kredit'] ?> dokumen,
        sisa kredit Rp <?= number_format($supplier_credit_reconciliation['sisa_kredit'], 0, ',', '.') ?>;
        <?= (int) $supplier_credit_reconciliation['alokasi_tidak_sesuai'] ?> alokasi tidak sesuai,
        <?= (int) $supplier_credit_reconciliation['jurnal_tidak_sesuai'] ?> jurnal tidak sesuai,
        <?= (int) $supplier_credit_reconciliation['referensi_yatim'] ?> referensi yatim.
    </div>
<?php endif; ?>

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
        Akun belum terklasifikasi: <?= esc(implode(', ', array_map(static fn ($code) => trim((string) $code) === '' ? '(kode kosong)' : $code, $reconciliation['akun_tidak_terklasifikasi']))) ?>.
    <?php endif; ?>
    <strong>Belum Rekonsiliasi</strong> sampai saldo awal, nilai persediaan, dan transaksi antarcabang diverifikasi.
    <p class="mb-0 mt-2">Penjelasan selisih:
        selisih jurnal Rp <?= number_format($reconciliation['selisih_jurnal'], 0, ',', '.') ?>
        − saldo akun belum terklasifikasi Rp <?= number_format($reconciliation['saldo_akun_tidak_terklasifikasi'], 0, ',', '.') ?>
        + saldo laba/rugi sebelum tahun berjalan Rp <?= number_format($reconciliation['laba_sebelum_tahun_berjalan'], 0, ',', '.') ?>.
        Saldo laba tahun sebelumnya perlu ditelusuri ke jurnal penutupan/ekuitas; angka ini belum menjadi koreksi yang disetujui.
    </p>
</div>

<div class="alert <?= $source_exceptions['tidak_seimbang'] === [] && $source_exceptions['baris_tanpa_referensi'] === 0 && $invalid_journal_accounts === [] ? 'alert-info' : 'alert-warning' ?> mx-4">
    <strong>Pemeriksaan jurnal sumber:</strong>
    <?= count($source_exceptions['tidak_seimbang']) ?> referensi tidak seimbang;
    <?= (int) $source_exceptions['baris_tanpa_referensi'] ?> baris tanpa referensi;
    <?= (int) array_sum(array_column($invalid_journal_accounts, 'jumlah_baris')) ?> baris dalam
    <?= count($invalid_journal_accounts) ?> kelompok kode akun tidak valid/tidak terdaftar.
    Pemeriksaan ini hanya mencakup jurnal yang tercatat; kelengkapan posting terhadap semua transaksi operasional belum dibuktikan.
</div>

<?php
$rankedSources = $source_exceptions['tidak_seimbang'];
usort($rankedSources, static fn ($left, $right) => abs($right['selisih']) <=> abs($left['selisih']));
?>
<details class="card mx-4 mb-4">
    <summary class="p-3">Rincian 50 sumber selisih terbesar dari <?= count($rankedSources) ?> referensi</summary>
    <div class="table-responsive p-3"><table class="table table-sm">
        <thead><tr><th>Cabang ID</th><th>Sumber</th><th>Referensi</th><th>Debit</th><th>Kredit</th><th>Selisih</th><th>Bukti</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($rankedSources, 0, 50) as $source): ?>
            <?php $sourceUrl = base_url('laporan_jurnal') . '?' . http_build_query([
                'endDate' => $reconciliation_date, 'filterUnit' => $source['id_unit'],
                'tabel_referensi' => $source['tabel_referensi'], 'id_referensi' => $source['id_referensi'],
            ]); ?>
            <tr><td><?= (int) $source['id_unit'] ?></td><td><?= esc($source['tabel_referensi']) ?></td>
                <td><?= esc($source['id_referensi']) ?></td>
                <td><?= number_format($source['debet'], 0, ',', '.') ?></td>
                <td><?= number_format($source['kredit'], 0, ',', '.') ?></td>
                <td><?= number_format($source['selisih'], 0, ',', '.') ?></td>
                <td><a href="<?= esc($sourceUrl, 'attr') ?>">Lihat jurnal sumber</a></td></tr>
        <?php endforeach ?>
        </tbody>
    </table></div>
</details>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="px-4 py-3 border-bottom">
        <h5 class="mb-0">Saldo Neraca per <?= esc($reconciliation_date) ?></h5>
        <small class="text-muted">Saldo jurnal historis sampai tanggal laporan; laba tahun berjalan ditampilkan terpisah.</small>
    </div>
    <div class="table-responsive px-4 py-3">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>Kode akun</th><th>Nama akun</th><th>Kelompok</th><th class="text-end">Saldo</th><th>Rincian</th></tr>
            </thead>
            <tbody>
                <?php foreach ($balance_sheet_accounts as $account): ?>
                    <?php $journalUrl = base_url('laporan_jurnal') . '?' . http_build_query(array_filter([
                        'endDate' => $reconciliation_date,
                        'filterUnit' => $id_unit,
                        'no_akun' => $account['no_akun'],
                    ], static fn ($value) => $value !== null && $value !== '')); ?>
                    <tr>
                        <td><?= esc($account['no_akun']) ?></td>
                        <td><?= esc($account['nama_akun']) ?></td>
                        <td><?= esc($account['kelompok']) ?></td>
                        <td class="text-end">Rp <?= number_format($account['saldo'], 0, ',', '.') ?></td>
                        <td><a href="<?= esc($journalUrl) ?>">Buka jurnal</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($balance_sheet_accounts === []): ?>
                    <tr><td colspan="5" class="text-center">Tidak ada saldo akun Neraca untuk cakupan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-4 pb-3">
        <strong>Perbandingan dengan <?= esc($previous_reconciliation_date) ?>:</strong>
        Aset Rp <?= number_format($reconciliation_comparison['aset'], 0, ',', '.') ?>;
        Kewajiban Rp <?= number_format($reconciliation_comparison['kewajiban'], 0, ',', '.') ?>;
        Ekuitas Rp <?= number_format($reconciliation_comparison['ekuitas'], 0, ',', '.') ?>;
        Laba berjalan Rp <?= number_format($reconciliation_comparison['laba_berjalan'], 0, ',', '.') ?>;
        Selisih Rp <?= number_format($reconciliation_comparison['selisih'], 0, ',', '.') ?>.
    </div>
    <div class="px-4 pb-3">
        <?php if ($inventory_reconciliation['available']): ?>
            <?php $inventoryTotal = $inventory_reconciliation['total']; ?>
            <strong>Rekonsiliasi persediaan:</strong>
            kuantitas selisih <?= number_format($inventoryTotal['selisih_jumlah']) ?>;
            nilai ledger Rp <?= number_format($inventoryTotal['nilai_ledger'], 0, ',', '.') ?>;
            nilai buku besar Rp <?= number_format($inventoryTotal['nilai_buku'], 0, ',', '.') ?>;
            selisih Rp <?= number_format($inventoryTotal['selisih_nilai'], 0, ',', '.') ?>.
        <?php else: ?>
            <strong>Rekonsiliasi persediaan belum tersedia:</strong> <?= esc($inventory_reconciliation['reason']) ?>
        <?php endif; ?>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
        <h5 class="mb-0">Ringkasan Mutasi Jurnal per Parent Akun (rentang tanggal)</h5>
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
