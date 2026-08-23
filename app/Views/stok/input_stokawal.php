<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">
            Datamaster Stok Awal
        </h4>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">
                        Datamaster
                    </a>
                </li>

                <li class="breadcrumb-item active" aria-current="page">
                    Stok Awal
                </li>
            </ol>
        </nav>
    </div>
</div>


<div class="card w-100 position-relative overflow-hidden">

    <!-- ==========================================================
         FORM UTAMA
    =========================================================== -->

    <form
        id="stokAwalForm"
        action="<?= base_url('insert/stokawal') ?>"
        enctype="multipart/form-data"
        method="post"
    >

        <!-- ======================================================
             UNIT & SEARCH
        ======================================================= -->

        <div class="d-flex justify-content-between align-items-center flex-wrap px-4 pt-4 pb-2">

            <!-- UNIT -->

            <div class="d-flex align-items-center gap-3 mb-2 mb-md-0">

                <label for="global_unit" class="col-form-label fw-semibold">
                    Unit:
                </label>

                <div>

                    <select
                        name="global_unit"
                        id="global_unit"
                        class="form-select"
                        required
                        <?= session('ID_UNIT') == 1 ? '' : 'readonly' ?>
                    >

                        <?php if (session('ID_UNIT') == 1): ?>

                            <?php foreach ($unit as $u): ?>

                                <?php if ($u && isset($u->idunit)): ?>

                                    <option value="<?= esc($u->idunit) ?>">
                                        <?= esc($u->NAMA_UNIT) ?>
                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <?php foreach ($unit as $u): ?>

                                <?php if (
                                    $u &&
                                    isset($u->idunit) &&
                                    $u->idunit == session('ID_UNIT')
                                ): ?>

                                    <option
                                        value="<?= esc($u->idunit) ?>"
                                        selected
                                    >
                                        <?= esc($u->NAMA_UNIT) ?>
                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </select>

                </div>

            </div>


            <!-- SEARCH -->

            <div class="mb-2 mb-md-0">

                <div class="input-group">

                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="form-control"
                        value="<?= esc($search ?? '') ?>"
                        placeholder="Cari nama barang, kode barang, atau IMEI..."
                        form="searchForm"
                    >

                    <button
                        type="submit"
                        form="searchForm"
                        class="btn btn-primary"
                    >
                        Cari
                    </button>

                    <?php if (!empty($search)): ?>

                        <a
                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>"
                            class="btn btn-light border"
                        >
                            Reset
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ======================================================
             INDIKATOR SELECTION
        ======================================================= -->

        <div class="px-4 pt-2">

            <div
                id="selectionIndicator"
                class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3"
                style="display: none !important;"
            >

                <div>

                    <strong>
                        <span id="selectedCount">0</span>
                        barang dipilih
                    </strong>

                    <div class="small mt-1">
                        Pilihan tetap tersimpan saat berpindah halaman.
                    </div>

                </div>

                <button
                    type="button"
                    id="clearSelection"
                    class="btn btn-sm btn-outline-danger"
                >
                    Batalkan Semua
                </button>

            </div>

        </div>


        <!-- ======================================================
             TABLE
        ======================================================= -->

        <div class="table-responsive mb-4 px-4">

            <table
                class="table table-bordered align-middle"
                id="table_barang"
            >

                <thead>

                    <tr>

                        <th>
                            Pilih
                        </th>

                        <th style="text-align: center;">
                            Nama Barang
                        </th>

                        <th style="text-align: center;">
                            Imei
                        </th>

                        <th>
                            HPP
                        </th>

                        <th>
                            Jumlah
                        </th>

                        <th>
                            Satuan Terkecil
                        </th>

                        <th>
                            Sumber
                        </th>

                        <th>
                            Suplier
                        </th>

                        <th>
                            Pelanggan
                        </th>

                        <th hidden>
                            Unit
                        </th>

                        <th hidden>
                            Kode Barang
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($barang as $b): ?>

                        <?php
                        $kode_barang = $b->kode_barang;
                        $isImeiEmpty = empty($b->imei);
                        ?>

                        <tr>

                            <!-- ==================================================
                                 PILIH
                            =================================================== -->

                            <td>

                                <input
                                    type="checkbox"
                                    class="product-checkbox"
                                    value="<?= esc($kode_barang) ?>"
                                    id="product_<?= esc($kode_barang) ?>"
                                    onchange="toggleCheckbox('<?= esc($kode_barang) ?>')"
                                >

                            </td>


                            <!-- ==================================================
                                 BARANG
                            =================================================== -->

                            <td style="min-width: 140px; text-align: center;">

                                <p style="font-weight: bold; margin-bottom: 4px;">
                                    <?= esc($kode_barang) ?>
                                </p>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->nama_barang) ?>
                                </p>

                            </td>


                            <!-- ==================================================
                                 IMEI
                            =================================================== -->

                            <td>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->imei ?? 'tidak ada imei') ?>
                                </p>

                            </td>


                            <!-- ==================================================
                                 HPP
                            =================================================== -->

                            <td>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->harga_beli ?? 'tidak ada HPP') ?>
                                </p>

                            </td>


                            <!-- ==================================================
                                 JUMLAH
                            =================================================== -->

                            <td>

                                <input
                                    type="number"
                                    name="jumlah[<?= esc($kode_barang) ?>]"
                                    class="form-control product-input"
                                    id="jumlah_<?= esc($kode_barang) ?>"
                                    disabled
                                    min="1"
                                    style="min-width: 120px;"
                                >

                            </td>


                            <!-- ==================================================
                                 SATUAN
                            =================================================== -->

                            <td>

                                <select
                                    name="satuan_terkecil[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input"
                                    id="satuan_terkecil_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 190px;"
                                >

                                    <option value="">
                                        -- Pilih Satuan --
                                    </option>

                                    <option value="pcs">
                                        pcs
                                    </option>

                                    <option value="pack">
                                        pack
                                    </option>

                                </select>

                            </td>


                            <!-- ==================================================
                                 SUMBER
                            =================================================== -->

                            <td>

                                <select
                                    name="tipe_relasi[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input"
                                    id="tipe_relasi_<?= esc($kode_barang) ?>"
                                    onchange="toggleSumber('<?= esc($kode_barang) ?>')"
                                    <?= $isImeiEmpty ? 'disabled data-always-disabled="true"' : '' ?>
                                    style="min-width: 190px;"
                                >

                                    <option value="">
                                        -- Pilih Tipe --
                                    </option>

                                    <option
                                        value="suplier"
                                        <?= $isImeiEmpty ? 'selected' : '' ?>
                                    >
                                        Suplier
                                    </option>

                                    <option value="pelanggan">
                                        Pelanggan
                                    </option>

                                </select>

                            </td>


                            <!-- ==================================================
                                 SUPLIER
                            =================================================== -->

                            <td>

                                <select
                                    name="id_suplier_text[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input"
                                    id="id_suplier_text_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 190px;"
                                >

                                    <option value="">
                                        -- Pilih Suplier --
                                    </option>

                                    <?php foreach ($suplier as $s): ?>

                                        <option value="<?= esc($s->id_suplier) ?>">
                                            <?= esc($s->nama_suplier) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- ==================================================
                                 PELANGGAN
                            =================================================== -->

                            <td>

                                <select
                                    name="id_pelanggan_text[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input"
                                    id="id_pelanggan_text_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 190px;"
                                >

                                    <option value="">
                                        -- Pilih Pelanggan --
                                    </option>

                                    <?php foreach ($pelanggan as $p): ?>

                                        <option value="<?= esc($p->id_pelanggan) ?>">
                                            <?= esc($p->nama) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- ==================================================
                                 UNIT HIDDEN
                            =================================================== -->

                            <td>

                                <select
                                    name="id_unit_text[<?= esc($kode_barang) ?>]"
                                    id="id_unit_text_<?= esc($kode_barang) ?>"
                                    hidden
                                >

                                    <?php foreach ($unit as $u): ?>

                                        <?php if ($u && isset($u->idunit)): ?>

                                            <option value="<?= esc($u->idunit) ?>">
                                                <?= esc($u->NAMA_UNIT) ?>
                                            </option>

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- ==================================================
                                 KODE BARANG
                            =================================================== -->

                            <td hidden>
                                <?= esc($kode_barang) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- ======================================================
             SERVER-SIDE PAGINATION
        ======================================================= -->

        <div class="px-4 pb-3">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <!-- INFO -->

                <div class="text-muted small">

                    Menampilkan

                    <strong>
                        <?= $total > 0
                            ? ($currentPage - 1) * $perPage + 1
                            : 0
                        ?>
                    </strong>

                    -

                    <strong>
                        <?= min($currentPage * $perPage, $total) ?>
                    </strong>

                    dari

                    <strong>
                        <?= $total ?>
                    </strong>

                    barang

                </div>


                <!-- PAGINATION -->

                <?php if ($totalPages > 1): ?>

                    <nav aria-label="Pagination stok awal">

                        <ul class="pagination mb-0">

                            <!-- SEBELUMNYA -->

                            <?php if ($currentPage > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $currentPage - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        Sebelumnya
                                    </a>

                                </li>

                            <?php else: ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        Sebelumnya
                                    </span>

                                </li>

                            <?php endif; ?>


                            <?php

                            if ($totalPages <= 7):

                                for ($i = 1; $i <= $totalPages; $i++):
                            ?>

                                <li
                                    class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                >

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        <?= $i ?>
                                    </a>

                                </li>

                            <?php
                                endfor;

                            elseif ($currentPage <= 4):
                            ?>

                                <?php for ($i = 1; $i <= 4; $i++): ?>

                                    <li
                                        class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        <?= $totalPages - 1 ?>
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>

                                </li>


                            <?php
                            elseif ($currentPage >= $totalPages - 3):
                            ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        1
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=2<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        2
                                    </a>

                                </li>


                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>


                                <?php for (
                                    $i = $totalPages - 3;
                                    $i <= $totalPages;
                                    $i++
                                ): ?>

                                    <li
                                        class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                            <?php else: ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        1
                                    </a>

                                </li>


                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>


                                <?php for (
                                    $i = $currentPage - 1;
                                    $i <= $currentPage + 1;
                                    $i++
                                ): ?>

                                    <li
                                        class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>

                                </li>

                            <?php endif; ?>


                            <!-- BERIKUTNYA -->

                            <?php if ($currentPage < $totalPages): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $currentPage + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                                    >
                                        Berikutnya
                                    </a>

                                </li>

                            <?php else: ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        Berikutnya
                                    </span>

                                </li>

                            <?php endif; ?>

                        </ul>

                    </nav>

                <?php endif; ?>

            </div>

        </div>


        <!-- ======================================================
             BUTTON
        ======================================================= -->

        <div class="px-4 pb-4">

            <button
                type="button"
                class="btn bg-danger-subtle text-danger"
                onclick="window.history.back()"
            >
                Close
            </button>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Simpan
            </button>

        </div>

    </form>

</div>


<!-- ============================================================
     SEARCH FORM TERPISAH
============================================================= -->

<form
    id="searchForm"
    action="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>"
    method="get"
></form>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


<script>

    // ============================================================
    // STORAGE
    // ============================================================

    const STORAGE_KEY = 'stokAwalSelectedProducts';

    let selectedProducts = {};


    // ============================================================
    // LOAD STORAGE
    // ============================================================

    function loadSelectedProducts() {

        try {

            const saved =
                localStorage.getItem(STORAGE_KEY);

            if (saved) {

                selectedProducts =
                    JSON.parse(saved);

            }

        } catch (error) {

            console.error(
                'Gagal membaca selection:',
                error
            );

            selectedProducts = {};

        }

    }


    // ============================================================
    // SAVE STORAGE
    // ============================================================

    function saveSelectedProducts() {

        try {

            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(selectedProducts)
            );

        } catch (error) {

            console.error(
                'Gagal menyimpan selection:',
                error
            );

        }

        updateSelectionIndicator();

    }


    // ============================================================
    // INDIKATOR
    // ============================================================

    function updateSelectionIndicator() {

        const indicator =
            document.getElementById(
                'selectionIndicator'
            );

        const countElement =
            document.getElementById(
                'selectedCount'
            );

        if (!indicator || !countElement) {
            return;
        }

        const count =
            Object.keys(
                selectedProducts
            ).length;


        countElement.textContent = count;


        if (count > 0) {

            indicator.style.setProperty(
                'display',
                'flex',
                'important'
            );

        } else {

            indicator.style.setProperty(
                'display',
                'none',
                'important'
            );

        }

    }


    // ============================================================
    // GET DATA PRODUK DI HALAMAN
    // ============================================================

    function getProductData(kodeBarang) {

        const jumlah =
            document.getElementById(
                'jumlah_' + kodeBarang
            );

        const satuan =
            document.getElementById(
                'satuan_terkecil_' + kodeBarang
            );

        const sumber =
            document.getElementById(
                'tipe_relasi_' + kodeBarang
            );

        const suplier =
            document.getElementById(
                'id_suplier_text_' + kodeBarang
            );

        const pelanggan =
            document.getElementById(
                'id_pelanggan_text_' + kodeBarang
            );


        return {

            jumlah:
                jumlah
                    ? jumlah.value
                    : '',

            satuan_terkecil:
                satuan
                    ? satuan.value
                    : '',

            tipe_relasi:
                sumber
                    ? sumber.value
                    : '',

            id_suplier_text:
                suplier
                    ? suplier.value
                    : '',

            id_pelanggan_text:
                pelanggan
                    ? pelanggan.value
                    : ''

        };

    }


    // ============================================================
    // SAVE DATA PRODUK
    // ============================================================

    function saveProductData(kodeBarang) {

        const checkbox =
            document.getElementById(
                'product_' + kodeBarang
            );

        if (!checkbox || !checkbox.checked) {
            return;
        }


        selectedProducts[kodeBarang] =
            getProductData(kodeBarang);


        saveSelectedProducts();

    }


    // ============================================================
    // TOGGLE CHECKBOX
    // ============================================================

    function toggleCheckbox(kodeBarang) {

        const checkbox =
            document.getElementById(
                'product_' + kodeBarang
            );

        if (!checkbox) {
            return;
        }


        const checked =
            checkbox.checked;


        const jumlah =
            document.getElementById(
                'jumlah_' + kodeBarang
            );

        const satuan =
            document.getElementById(
                'satuan_terkecil_' + kodeBarang
            );

        const sumber =
            document.getElementById(
                'tipe_relasi_' + kodeBarang
            );

        const suplier =
            document.getElementById(
                'id_suplier_text_' + kodeBarang
            );

        const pelanggan =
            document.getElementById(
                'id_pelanggan_text_' + kodeBarang
            );


        // ========================================================
        // CENTANG
        // ========================================================

        if (checked) {

            if (!selectedProducts[kodeBarang]) {

                selectedProducts[kodeBarang] = {

                    jumlah: '',

                    satuan_terkecil: '',

                    tipe_relasi:
                        sumber
                            ? sumber.value
                            : '',

                    id_suplier_text: '',

                    id_pelanggan_text: ''

                };

            }


            if (jumlah) {

                jumlah.disabled = false;

            }


            if (satuan) {

                satuan.disabled = false;

            }


            if (sumber) {

                if (
                    !sumber.hasAttribute(
                        'data-always-disabled'
                    )
                ) {

                    sumber.disabled = false;

                }

            }


            saveProductData(kodeBarang);

            toggleSumber(kodeBarang);

        }


        // ========================================================
        // UNCHECK
        // ========================================================

        else {

            delete selectedProducts[kodeBarang];


            if (jumlah) {

                jumlah.value = '';

                jumlah.disabled = true;

            }


            if (satuan) {

                satuan.value = '';

                satuan.disabled = true;

            }


            if (sumber) {

                if (
                    !sumber.hasAttribute(
                        'data-always-disabled'
                    )
                ) {

                    sumber.value = '';

                    sumber.disabled = true;

                }

            }


            if (suplier) {

                suplier.value = '';

                suplier.disabled = true;

            }


            if (pelanggan) {

                pelanggan.value = '';

                pelanggan.disabled = true;

            }


            saveSelectedProducts();

        }

    }


    // ============================================================
    // TOGGLE SUMBER
    // ============================================================

    function toggleSumber(kodeBarang) {

        const sumber =
            document.getElementById(
                'tipe_relasi_' + kodeBarang
            );

        const suplier =
            document.getElementById(
                'id_suplier_text_' + kodeBarang
            );

        const pelanggan =
            document.getElementById(
                'id_pelanggan_text_' + kodeBarang
            );

        const checkbox =
            document.getElementById(
                'product_' + kodeBarang
            );


        if (!sumber || !checkbox) {
            return;
        }


        // Reset supplier & pelanggan

        if (suplier) {

            suplier.disabled = true;

        }


        if (pelanggan) {

            pelanggan.disabled = true;

        }


        if (!checkbox.checked) {
            return;
        }


        // Supplier

        if (
            sumber.value === 'suplier' &&
            suplier
        ) {

            suplier.disabled = false;

        }


        // Pelanggan

        else if (
            sumber.value === 'pelanggan' &&
            pelanggan
        ) {

            pelanggan.disabled = false;

        }


        saveProductData(kodeBarang);

    }


    // ============================================================
    // RESTORE PRODUCT
    // ============================================================

    function restoreProduct(kodeBarang) {

        const data =
            selectedProducts[kodeBarang];

        if (!data) {
            return;
        }


        const checkbox =
            document.getElementById(
                'product_' + kodeBarang
            );

        const jumlah =
            document.getElementById(
                'jumlah_' + kodeBarang
            );

        const satuan =
            document.getElementById(
                'satuan_terkecil_' + kodeBarang
            );

        const sumber =
            document.getElementById(
                'tipe_relasi_' + kodeBarang
            );

        const suplier =
            document.getElementById(
                'id_suplier_text_' + kodeBarang
            );

        const pelanggan =
            document.getElementById(
                'id_pelanggan_text_' + kodeBarang
            );


        if (checkbox) {

            checkbox.checked = true;

        }


        if (jumlah) {

            jumlah.disabled = false;

            jumlah.value =
                data.jumlah ?? '';

        }


        if (satuan) {

            satuan.disabled = false;

            satuan.value =
                data.satuan_terkecil ?? '';

        }


        if (sumber) {

            sumber.value =
                data.tipe_relasi ?? '';


            if (
                sumber.hasAttribute(
                    'data-always-disabled'
                )
            ) {

                sumber.disabled = true;

            } else {

                sumber.disabled = false;

            }

        }


        if (suplier) {

            suplier.value =
                data.id_suplier_text ?? '';

        }


        if (pelanggan) {

            pelanggan.value =
                data.id_pelanggan_text ?? '';

        }


        toggleSumber(kodeBarang);

    }


    // ============================================================
    // GET KODE BARANG DARI ID INPUT
    // ============================================================

    function getKodeBarangFromId(id) {

        const prefixes = [

            'jumlah_',

            'satuan_terkecil_',

            'tipe_relasi_',

            'id_suplier_text_',

            'id_pelanggan_text_'

        ];


        for (
            const prefix of prefixes
        ) {

            if (
                id.startsWith(prefix)
            ) {

                return id.substring(
                    prefix.length
                );

            }

        }


        return null;

    }


    // ============================================================
    // BIND INPUT
    // ============================================================

    function bindProductInputs() {

        document
            .querySelectorAll(
                '.product-input'
            )
            .forEach(function(input) {

                input.addEventListener(
                    'input',
                    function() {

                        const kodeBarang =
                            getKodeBarangFromId(
                                input.id
                            );


                        if (kodeBarang) {

                            saveProductData(
                                kodeBarang
                            );

                        }

                    }
                );


                input.addEventListener(
                    'change',
                    function() {

                        const kodeBarang =
                            getKodeBarangFromId(
                                input.id
                            );


                        if (!kodeBarang) {
                            return;
                        }


                        saveProductData(
                            kodeBarang
                        );


                        if (
                            input.id.startsWith(
                                'tipe_relasi_'
                            )
                        ) {

                            toggleSumber(
                                kodeBarang
                            );

                        }

                    }
                );

            });

    }


    // ============================================================
    // RESTORE CURRENT PAGE
    // ============================================================

    function restoreCurrentPage() {

        document
            .querySelectorAll(
                '.product-checkbox'
            )
            .forEach(function(checkbox) {

                const kodeBarang =
                    checkbox.value;


                if (
                    selectedProducts[
                        kodeBarang
                    ]
                ) {

                    restoreProduct(
                        kodeBarang
                    );

                }

            });


        bindProductInputs();


        updateSelectionIndicator();

    }


    // ============================================================
    // CLEAR ALL SELECTION
    // ============================================================

    const clearButton =
        document.getElementById(
            'clearSelection'
        );


    if (clearButton) {

        clearButton.addEventListener(
            'click',
            function() {

                const confirmed =
                    confirm(
                        'Batalkan semua barang yang sudah dipilih?'
                    );


                if (!confirmed) {
                    return;
                }


                selectedProducts = {};


                localStorage.removeItem(
                    STORAGE_KEY
                );


                document
                    .querySelectorAll(
                        '.product-checkbox'
                    )
                    .forEach(function(checkbox) {

                        checkbox.checked =
                            false;


                        const kodeBarang =
                            checkbox.value;


                        const jumlah =
                            document.getElementById(
                                'jumlah_' +
                                kodeBarang
                            );

                        const satuan =
                            document.getElementById(
                                'satuan_terkecil_' +
                                kodeBarang
                            );

                        const sumber =
                            document.getElementById(
                                'tipe_relasi_' +
                                kodeBarang
                            );

                        const suplier =
                            document.getElementById(
                                'id_suplier_text_' +
                                kodeBarang
                            );

                        const pelanggan =
                            document.getElementById(
                                'id_pelanggan_text_' +
                                kodeBarang
                            );


                        if (jumlah) {

                            jumlah.value = '';

                            jumlah.disabled =
                                true;

                        }


                        if (satuan) {

                            satuan.value = '';

                            satuan.disabled =
                                true;

                        }


                        if (sumber) {

                            if (
                                !sumber.hasAttribute(
                                    'data-always-disabled'
                                )
                            ) {

                                sumber.value =
                                    '';

                                sumber.disabled =
                                    true;

                            }

                        }


                        if (suplier) {

                            suplier.value =
                                '';

                            suplier.disabled =
                                true;

                        }


                        if (pelanggan) {

                            pelanggan.value =
                                '';

                            pelanggan.disabled =
                                true;

                        }

                    });


                updateSelectionIndicator();

            }
        );

    }


    // ============================================================
    // CREATE HIDDEN INPUT
    // ============================================================

    function createHiddenInput(
        form,
        name,
        value
    ) {

        const input =
            document.createElement(
                'input'
            );


        input.type = 'hidden';

        input.name = name;

        input.value = value;

        input.className =
            'generated-selection-input';


        form.appendChild(
            input
        );

    }


    // ============================================================
    // SUBMIT FORM
    // ============================================================

    const stokAwalForm =
        document.getElementById(
            'stokAwalForm'
        );


    if (stokAwalForm) {

        stokAwalForm.addEventListener(
            'submit',
            function(event) {

                const form = this;


                // Hapus hidden input lama

                form
                    .querySelectorAll(
                        '.generated-selection-input'
                    )
                    .forEach(function(input) {

                        input.remove();

                    });


                const selectedIds =
                    Object.keys(
                        selectedProducts
                    );


                // Tidak ada barang

                if (
                    selectedIds.length === 0
                ) {

                    event.preventDefault();

                    alert(
                        'Tidak ada produk yang dipilih.'
                    );

                    return;

                }


                // ==================================================
                // GENERATE HIDDEN INPUT
                // ==================================================

                selectedIds.forEach(
                    function(kodeBarang) {

                        const data =
                            selectedProducts[
                                kodeBarang
                            ];


                        // selected_products[]

                        createHiddenInput(
                            form,
                            'selected_products[]',
                            kodeBarang
                        );


                        // jumlah[]

                        createHiddenInput(
                            form,
                            'jumlah[' +
                            kodeBarang +
                            ']',
                            data.jumlah ??
                            ''
                        );


                        // satuan_terkecil[]

                        createHiddenInput(
                            form,
                            'satuan_terkecil[' +
                            kodeBarang +
                            ']',
                            data.satuan_terkecil ??
                            ''
                        );


                        // tipe_relasi[]

                        createHiddenInput(
                            form,
                            'tipe_relasi[' +
                            kodeBarang +
                            ']',
                            data.tipe_relasi ??
                            ''
                        );


                        // supplier

                        createHiddenInput(
                            form,
                            'id_suplier_text[' +
                            kodeBarang +
                            ']',
                            data.id_suplier_text ??
                            ''
                        );


                        // pelanggan

                        createHiddenInput(
                            form,
                            'id_pelanggan_text[' +
                            kodeBarang +
                            ']',
                            data.id_pelanggan_text ??
                            ''
                        );

                    }
                );


                // Hapus state setelah hidden input
                // berhasil dibuat.

                localStorage.removeItem(
                    STORAGE_KEY
                );

            }
        );

    }


    // ============================================================
    // INITIALIZE
    // ============================================================

    loadSelectedProducts();


    $(document).ready(function() {

        $('#table_barang').DataTable({

            responsive: true,

            autoWidth: false,

            paging: false,

            searching: false,

            info: false

        });


        restoreCurrentPage();

    });

</script>