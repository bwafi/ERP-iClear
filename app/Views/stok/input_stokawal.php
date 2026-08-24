<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
>

<style>
/* ==========================================================
   SELECT2 — SAMA DENGAN BOOTSTRAP .form-select
========================================================== */

.pelanggan-select-search + .select2-container {
    width: 100% !important;
}


/* ==========================================================
   CONTAINER
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-selection--single {

    display: block !important;

    width: 100% !important;
    min-height: 38px !important;

    padding: 8px 48px 8px 16px !important;

    font-size: 0.9rem !important;
    font-weight: 500 !important;
    line-height: 1.5 !important;

    color: var(--bs-body-color) !important;

    background-color: transparent !important;

    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;

    background-repeat: no-repeat !important;

    background-position: right 16px center !important;

    background-size: 16px 12px !important;

    border: var(--bs-border-width) solid #aebcc3 !important;

    border-radius: 0.5rem !important;

    box-shadow: unset !important;

    transition:
        border-color 0.15s ease-in-out,
        box-shadow 0.15s ease-in-out !important;

    box-sizing: border-box !important;
}


/* ==========================================================
   RENDERED TEXT
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-selection--single
    .select2-selection__rendered {

    display: block !important;

    padding: 0 0 0 14px  !important;

    margin: 0 !important;

    width: 100% !important;
    height: auto;

    color: var(--bs-body-color) !important;

    font-size: 0.9rem !important;

    font-weight: 500 !important;

    line-height: 21px !important;

    white-space: nowrap !important;

    overflow: hidden !important;

    text-overflow: ellipsis !important;
}


/* ==========================================================
   HILANGKAN ARROW BAWAAN SELECT2
   karena kita pakai SVG Bootstrap
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-selection--single
    .select2-selection__arrow {

    display: none !important;
}


/* ==========================================================
   FOCUS
========================================================== */

.pelanggan-select-search
    + .select2-container--focus
    .select2-selection--single,

.pelanggan-select-search
    + .select2-container--open
    .select2-selection--single {

    border-color: #80c2ed !important;

    outline: 0 !important;

    box-shadow:
        0 0 0 0.25rem
        rgba(0, 133, 219, 0.25) !important;
}


/* ==========================================================
   DISABLED
========================================================== */

.pelanggan-select-search
    + .select2-container.select2-container--disabled
    .select2-selection--single {

    background-color:
        var(--bs-secondary-bg) !important;

    opacity: 1 !important;

    cursor: not-allowed !important;
}


/* ==========================================================
   CLEAR BUTTON
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-selection__clear {
        
    position: absolute !important;

    left: 12px !important;
    right: auto !important;

    top: 50% !important;

    /* naik sedikit dari center */
    transform: translateY(-65%) !important;

    margin: 0 !important;
    padding: 0 !important;

    font-size: 22px !important;

    line-height: 1 !important;

    color: #6c757d !important;

    z-index: 5 !important;

    /* buang styling bawaan */
    height: auto !important;
    width: auto !important;
}


/* ==========================================================
   DROPDOWN
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-dropdown {

    border:
        1px solid #aebcc3 !important;

    border-radius:
        0.5rem !important;

    overflow: hidden !important;

    box-shadow:
        0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;

    z-index: 9999 !important;
}


/* ==========================================================
   SEARCH
========================================================== */

.select2-search--dropdown {

    padding: 8px !important;

}


.select2-search--dropdown
.select2-search__field {

    width: 100% !important;

    height: 38px !important;

    padding: 8px 12px !important;

    font-size: 0.9rem !important;

    font-weight: 400 !important;

    line-height: 1.5 !important;

    color: var(--bs-body-color) !important;

    background-color: #fff !important;

    border:
        1px solid #aebcc3 !important;

    border-radius:
        0.5rem !important;

    outline: 0 !important;

    box-sizing: border-box !important;

}


.select2-search--dropdown
.select2-search__field:focus {

    border-color:
        #80c2ed !important;

    box-shadow:
        0 0 0 0.25rem
        rgba(0, 133, 219, 0.25) !important;

}


/* ==========================================================
   OPTION
========================================================== */

.select2-results__option {

    padding:
        8px 16px !important;

    font-size:
        0.9rem !important;

    font-weight:
        400 !important;

    line-height:
        1.5 !important;

    color:
        var(--bs-body-color) !important;

}


.select2-results__option--highlighted {

    background-color:
        #0085db !important;

    color:
        #fff !important;

}


/* ==========================================================
   SELECT2 PLACEHOLDER
========================================================== */

.pelanggan-select-search
    + .select2-container
    .select2-selection__placeholder {

    color:
        #6c757d !important;

    opacity:
        1 !important;

}

</style>


<!-- ==========================================================
     HEADER
=========================================================== -->

<div class="card shadow-none position-relative overflow-hidden mb-4">

    <div
        class="card-body d-flex align-items-center justify-content-between p-4"
    >

        <h4 class="fw-semibold mb-0">
            Datamaster Stok Awal
        </h4>

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb mb-0">

                <li class="breadcrumb-item">

                    <a
                        class="text-muted text-decoration-none"
                        href="<?= base_url('/') ?>"
                    >
                        Datamaster
                    </a>

                </li>

                <li
                    class="breadcrumb-item active"
                    aria-current="page"
                >
                    Stok Awal
                </li>

            </ol>

        </nav>

    </div>

</div>


<!-- ==========================================================
     CARD UTAMA
=========================================================== -->

<div class="card w-100 position-relative overflow-hidden">


    <!-- ======================================================
         FORM UTAMA
    ======================================================= -->

    <form
        id="stokAwalForm"
        action="<?= base_url('insert/stokawal') ?>"
        enctype="multipart/form-data"
        method="post"
    >


        <!-- ==================================================
             UNIT & SEARCH
        =================================================== -->

        <div
            class="d-flex justify-content-between align-items-center flex-wrap px-4 pt-4 pb-2"
        >


            <!-- UNIT -->

            <div
                class="d-flex align-items-center gap-3 mb-2 mb-md-0"
            >

                <label
                    for="global_unit"
                    class="col-form-label fw-semibold"
                >
                    Unit:
                </label>

                <div>

                    <select
                        name="global_unit"
                        id="global_unit"
                        class="form-select"
                        required
                        <?= session('ID_UNIT') == 1
                            ? ''
                            : 'readonly' ?>
                    >

                        <?php if (session('ID_UNIT') == 1): ?>

                            <?php foreach ($unit as $u): ?>

                                <?php if (
                                    $u &&
                                    isset($u->idunit)
                                ): ?>

                                    <option
                                        value="<?= esc($u->idunit) ?>"
                                    >
                                        <?= esc($u->NAMA_UNIT) ?>
                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <?php foreach ($unit as $u): ?>

                                <?php if (
                                    $u &&
                                    isset($u->idunit) &&
                                    $u->idunit ==
                                        session('ID_UNIT')
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


            <!-- SEARCH BARANG -->

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
                            href="<?= base_url(
                                'input_stokawal/' .
                                urlencode($jenis)
                            ) ?>"
                            class="btn btn-light border"
                        >
                            Reset
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ==================================================
             SELECTION INDICATOR
        =================================================== -->

        <div class="px-4 pt-3">

            <div
                id="selectionIndicator"
                class="card border-0 shadow-sm bg-light-subtle rounded-3 p-3 mb-3 border-start border-primary border-4"
                style="display: none !important;"
            >

                <div
                    class="d-flex align-items-center justify-content-between flex-wrap gap-3"
                >

                    <div
                        class="d-flex align-items-center gap-3"
                    >

                        <div
                            class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="
                                width: 45px;
                                height: 45px;
                                min-width: 45px;
                            "
                        >

                            <i class="bi bi-check2-square fs-4"></i>

                        </div>

                        <div>

                            <div
                                class="d-flex align-items-center gap-2"
                            >

                                <span
                                    class="badge bg-primary fs-6 px-2 py-1"
                                    id="selectedCount"
                                >
                                    0
                                </span>

                                <span
                                    class="fw-bold text-dark fs-5"
                                >
                                    Barang Terpilih
                                </span>

                            </div>

                            <p
                                class="text-muted small mb-0 mt-1"
                            >

                                <i
                                    class="bi bi-info-circle me-1"
                                ></i>

                                Pilihan produk tetap aman tersimpan
                                otomatis meskipun Anda berpindah halaman.

                            </p>

                        </div>

                    </div>


                    <div>

                        <button
                            type="button"
                            id="openCancelModalBtn"
                            class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 px-3 py-2 fw-semibold"
                        >

                            <i class="bi bi-trash3"></i>

                            Batalkan Semua

                        </button>

                    </div>

                </div>

            </div>

        </div>


        <!-- ==================================================
             TABLE
        =================================================== -->

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

                        $kode_barang =
                            $b->kode_barang;

                        $isImeiEmpty =
                            empty($b->imei);

                        ?>

                        <tr>


                            <!-- PILIH -->

                            <td class="text-center">

                                <input
                                    type="checkbox"
                                    class="product-checkbox form-check-input"
                                    value="<?= esc($kode_barang) ?>"
                                    id="product_<?= esc($kode_barang) ?>"
                                    onchange="toggleCheckbox('<?= esc($kode_barang) ?>')"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        cursor: pointer;
                                    "
                                >

                            </td>


                            <!-- BARANG -->

                            <td
                                style="
                                    min-width: 140px;
                                    text-align: center;
                                "
                            >

                                <p
                                    style="
                                        font-weight: bold;
                                        margin-bottom: 4px;
                                    "
                                >
                                    <?= esc($kode_barang) ?>
                                </p>

                                <p
                                    style="
                                        font-style: italic;
                                        margin-bottom: 0;
                                    "
                                >
                                    <?= esc($b->nama_barang) ?>
                                </p>

                            </td>


                            <!-- IMEI -->

                            <td>

                                <p
                                    style="
                                        font-style: italic;
                                        margin-bottom: 0;
                                    "
                                >
                                    <?= esc(
                                        $b->imei ??
                                        'tidak ada imei'
                                    ) ?>
                                </p>

                            </td>


                            <!-- HPP -->

                            <td>

                                <p
                                    style="
                                        font-style: italic;
                                        margin-bottom: 0;
                                    "
                                >
                                    <?= esc(
                                        $b->harga_beli ??
                                        'tidak ada HPP'
                                    ) ?>
                                </p>

                            </td>


                            <!-- JUMLAH -->

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


                            <!-- SATUAN -->

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


                            <!-- SUMBER -->

                            <td>

                                <select
                                    name="tipe_relasi[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input"
                                    id="tipe_relasi_<?= esc($kode_barang) ?>"
                                    onchange="toggleSumber('<?= esc($kode_barang) ?>')"
                                    <?= $isImeiEmpty
                                        ? 'disabled data-always-disabled="true"'
                                        : '' ?>
                                    style="min-width: 190px;"
                                >

                                    <option value="">
                                        -- Pilih Tipe --
                                    </option>

                                    <option
                                        value="suplier"
                                        <?= $isImeiEmpty
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Suplier
                                    </option>

                                    <option value="pelanggan">
                                        Pelanggan
                                    </option>

                                </select>

                            </td>


                            <!-- SUPLIER -->

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

                                        <option
                                            value="<?= esc(
                                                $s->id_suplier
                                            ) ?>"
                                        >
                                            <?= esc(
                                                $s->nama_suplier
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- PELANGGAN -->

                            <td>

                                <select
                                    name="id_pelanggan_text[<?= esc($kode_barang) ?>]"
                                    class="form-select product-input pelanggan-select-search"
                                    id="id_pelanggan_text_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 230px;"
                                >

                                    <option value="">
                                        -- Pilih Pelanggan --
                                    </option>

                                </select>

                            </td>


                            <!-- UNIT -->

                            <td>

                                <select
                                    name="id_unit_text[<?= esc($kode_barang) ?>]"
                                    id="id_unit_text_<?= esc($kode_barang) ?>"
                                    hidden
                                >

                                    <?php foreach ($unit as $u): ?>

                                        <?php if (
                                            $u &&
                                            isset($u->idunit)
                                        ): ?>

                                            <option
                                                value="<?= esc(
                                                    $u->idunit
                                                ) ?>"
                                            >
                                                <?= esc(
                                                    $u->NAMA_UNIT
                                                ) ?>
                                            </option>

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <!-- KODE BARANG -->

                            <td hidden>
                                <?= esc($kode_barang) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- ==================================================
             PAGINATION
        =================================================== -->

        <div class="px-4 pb-3">

            <div
                class="d-flex justify-content-between align-items-center flex-wrap gap-3"
            >

                <!-- INFO -->

                <div class="text-muted small">

                    Menampilkan

                    <strong>
                        <?= $total > 0
                            ? (($currentPage - 1) * $perPage) + 1
                            : 0 ?>
                    </strong>

                    -

                    <strong>
                        <?= min(
                            $currentPage * $perPage,
                            $total
                        ) ?>
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

                            <?php

                            $baseUrl = base_url(
                                'input_stokawal/' .
                                urlencode($jenis)
                            );

                            $searchQuery =
                                !empty($search)
                                    ? '&search=' .
                                      urlencode($search)
                                    : '';

                            ?>


                            <!-- SEBELUMNYA -->

                            <?php if ($currentPage > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=<?= $currentPage - 1 ?><?= $searchQuery ?>"
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


                            <?php if ($totalPages <= 7): ?>


                                <!-- SEMUA PAGE -->

                                <?php for (
                                    $i = 1;
                                    $i <= $totalPages;
                                    $i++
                                ): ?>

                                    <li
                                        class="page-item
                                        <?= $i === (int) $currentPage
                                            ? 'active'
                                            : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= $baseUrl ?>?page=<?= $i ?><?= $searchQuery ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                            <?php elseif ($currentPage <= 4): ?>


                                <!-- AWAL -->

                                <?php for (
                                    $i = 1;
                                    $i <= 4;
                                    $i++
                                ): ?>

                                    <li
                                        class="page-item
                                        <?= $i === (int) $currentPage
                                            ? 'active'
                                            : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= $baseUrl ?>?page=<?= $i ?><?= $searchQuery ?>"
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
                                        href="<?= $baseUrl ?>?page=<?= $totalPages - 1 ?><?= $searchQuery ?>"
                                    >
                                        <?= $totalPages - 1 ?>
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=<?= $totalPages ?><?= $searchQuery ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>

                                </li>


                            <?php elseif (
                                $currentPage >=
                                $totalPages - 3
                            ): ?>


                                <!-- AKHIR -->

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=1<?= $searchQuery ?>"
                                    >
                                        1
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=2<?= $searchQuery ?>"
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
                                        class="page-item
                                        <?= $i === (int) $currentPage
                                            ? 'active'
                                            : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= $baseUrl ?>?page=<?= $i ?><?= $searchQuery ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                            <?php else: ?>


                                <!-- TENGAH -->

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=1<?= $searchQuery ?>"
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
                                        class="page-item
                                        <?= $i === (int) $currentPage
                                            ? 'active'
                                            : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= $baseUrl ?>?page=<?= $i ?><?= $searchQuery ?>"
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
                                        href="<?= $baseUrl ?>?page=<?= $totalPages ?><?= $searchQuery ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>

                                </li>

                            <?php endif; ?>


                            <!-- BERIKUTNYA -->

                            <?php if (
                                $currentPage <
                                $totalPages
                            ): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= $baseUrl ?>?page=<?= $currentPage + 1 ?><?= $searchQuery ?>"
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


        <!-- ==================================================
             BUTTON
        =================================================== -->

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
                id="saveStockBtn"
            >
                <i class="bi bi-check2 me-1"></i>
                Simpan
            </button>

        </div>

    </form>

</div>


<!-- ==========================================================
     SEARCH FORM TERPISAH
=========================================================== -->

<form
    id="searchForm"
    action="<?= base_url(
        'input_stokawal/' .
        urlencode($jenis)
    ) ?>"
    method="get"
>
</form>


<!-- ==========================================================
     MODAL — BATALKAN SEMUA
=========================================================== -->

<div
    class="modal fade"
    id="cancelConfirmModal"
    tabindex="-1"
    aria-labelledby="cancelConfirmModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <div class="modal-body text-center p-4">

                <div
                    class="text-danger bg-danger-subtle rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3"
                    style="
                        width: 60px;
                        height: 60px;
                    "
                >

                    <i class="bi bi-exclamation-triangle fs-2"></i>

                </div>


                <h5
                    class="fw-bold mb-2"
                    id="cancelConfirmModalLabel"
                >
                    Batalkan Semua Pilihan?
                </h5>


                <p class="text-muted small mb-4">

                    Semua barang yang telah Anda pilih beserta
                    pengisian jumlah dan satuannya akan dihapus
                    dari daftar sementara.

                </p>


                <div
                    class="d-flex justify-content-center gap-2"
                >

                    <button
                        type="button"
                        class="btn btn-light px-4"
                        data-bs-dismiss="modal"
                    >
                        Tidak, Kembali
                    </button>


                    <button
                        type="button"
                        id="confirmClearSelectionBtn"
                        class="btn btn-danger px-4"
                    >
                        Ya, Batalkan Semua
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ==========================================================
     MODAL — KONFIRMASI SIMPAN
=========================================================== -->

<div
    class="modal fade"
    id="saveConfirmModal"
    tabindex="-1"
    aria-labelledby="saveConfirmModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <div class="modal-body text-center p-4">


                <!-- ICON -->

                <div
                    class="text-primary bg-primary-subtle rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3"
                    style="
                        width: 60px;
                        height: 60px;
                    "
                >

                    <i class="bi bi-box-seam fs-2"></i>

                </div>


                <!-- TITLE -->

                <h5
                    class="fw-bold mb-2"
                    id="saveConfirmModalLabel"
                >
                    Simpan Stok Awal?
                </h5>


                <!-- DESCRIPTION -->

                <p class="text-muted small mb-2">

                    Anda akan menyimpan data stok awal untuk:

                </p>


                <!-- COUNT -->

                <div
                    class="bg-light rounded-3 py-2 px-3 mb-3"
                >

                    <strong
                        id="saveSelectedCount"
                        class="text-primary"
                    >
                        0
                    </strong>

                    <span class="text-muted">
                        barang terpilih
                    </span>

                </div>


                <!-- WARNING -->

                <p class="text-muted small mb-4">

                    Pastikan jumlah, satuan, sumber, suplier,
                    dan pelanggan sudah sesuai sebelum melanjutkan.

                </p>


                <!-- BUTTON -->

                <div
                    class="d-flex justify-content-center gap-2"
                >

                    <button
                        type="button"
                        class="btn btn-light px-4"
                        data-bs-dismiss="modal"
                    >
                        Batal
                    </button>


                    <button
                        type="button"
                        id="confirmSaveStockBtn"
                        class="btn btn-primary px-4"
                    >

                        <i class="bi bi-check2 me-1"></i>

                        Ya, Simpan

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ==========================================================
     JAVASCRIPT
=========================================================== -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script
    src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"
></script>


<script>

/* ============================================================
   STORAGE
============================================================ */

const STORAGE_KEY =
    'stokAwalSelectedProducts';

let selectedProducts = {};


/* ============================================================
   LOAD STORAGE
============================================================ */

function loadSelectedProducts() {

    try {

        const saved =
            localStorage.getItem(
                STORAGE_KEY
            );

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


/* ============================================================
   SAVE STORAGE
============================================================ */

function saveSelectedProducts() {

    try {

        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(
                selectedProducts
            )
        );

    } catch (error) {

        console.error(
            'Gagal menyimpan selection:',
            error
        );

    }

    updateSelectionIndicator();

}


/* ============================================================
   INDIKATOR
============================================================ */

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


    countElement.textContent =
        count;


    if (count > 0) {

        indicator.style.setProperty(
            'display',
            'block',
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


/* ============================================================
   DATA PRODUK
============================================================ */

function getProductData(
    kodeBarang
) {

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


/* ============================================================
   SAVE DATA PRODUK
============================================================ */

function saveProductData(
    kodeBarang
) {

    const checkbox =
        document.getElementById(
            'product_' + kodeBarang
        );


    if (
        !checkbox ||
        !checkbox.checked
    ) {
        return;
    }


    selectedProducts[kodeBarang] =
        getProductData(
            kodeBarang
        );


    saveSelectedProducts();

}


/* ============================================================
   SELECT2 PELANGGAN
============================================================ */

function initPelangganSelect() {

    $('.pelanggan-select-search')
        .each(function () {

            const select =
                $(this);


            if (
                select.hasClass(
                    'select2-hidden-accessible'
                )
            ) {
                return;
            }


            select.select2({

                width: '100%',

                placeholder:
                    '-- Pilih Pelanggan --',

                allowClear: true,

                minimumInputLength: 2,

                ajax: {

                    url:
                        '<?= base_url(
                            'pelanggan/search'
                        ) ?>',

                    dataType: 'json',

                    delay: 300,

                    data: function (
                        params
                    ) {

                        return {
                            q: params.term
                        };

                    },

                    processResults:
                        function (data) {

                            return {
                                results: data
                            };

                        },

                    cache: true

                }
            });

     $(document).on(
            'mouseenter',
            '.pelanggan-select-search + .select2-container .select2-selection__clear',
            function () {
                $(this).attr('title', 'Hapus pilihan');
            }
    );

            /* ==================================================
               EVENT SELECT2
            ================================================== */

            select.on(
                'change',
                function () {

                    const kodeBarang =
                        getKodeBarangFromId(
                            this.id
                        );


                    if (!kodeBarang) {
                        return;
                    }


                    saveProductData(
                        kodeBarang
                    );

                }
            );

        });

}


/* ============================================================
   TOGGLE CHECKBOX
============================================================ */

function toggleCheckbox(
    kodeBarang
) {

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


    /* ========================================================
       CENTANG
    ======================================================== */

    if (checked) {

        if (
            !selectedProducts[
                kodeBarang
            ]
        ) {

            selectedProducts[
                kodeBarang
            ] = {

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

                sumber.disabled =
                    false;

            }

        }


        saveProductData(
            kodeBarang
        );


        toggleSumber(
            kodeBarang
        );

    }


    /* ========================================================
       UNCHECK
    ======================================================== */

    else {

        delete selectedProducts[
            kodeBarang
        ];


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

                sumber.value = '';

                sumber.disabled =
                    true;

            }

        }


        if (suplier) {

            suplier.value = '';

            suplier.disabled =
                true;

        }


        if (pelanggan) {

            $(pelanggan)
                .val(null)
                .trigger('change');

            pelanggan.disabled =
                true;

            $(pelanggan)
                .prop(
                    'disabled',
                    true
                );

        }


        saveSelectedProducts();

    }

}


/* ============================================================
   TOGGLE SUMBER
============================================================ */

function toggleSumber(
    kodeBarang
) {

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


    if (
        !sumber ||
        !checkbox
    ) {
        return;
    }


    /* ========================================================
       RESET
    ======================================================== */

    if (suplier) {

        suplier.disabled =
            true;

    }


    if (pelanggan) {

        $(pelanggan)
            .prop(
                'disabled',
                true
            );

    }


    if (!checkbox.checked) {
        return;
    }


    /* ========================================================
       SUPPLIER
    ======================================================== */

    if (
        sumber.value === 'suplier' &&
        suplier
    ) {

        suplier.disabled =
            false;

    }


    /* ========================================================
       PELANGGAN
    ======================================================== */

    else if (
        sumber.value === 'pelanggan' &&
        pelanggan
    ) {

        $(pelanggan)
            .prop(
                'disabled',
                false
            );

    }


    saveProductData(
        kodeBarang
    );

}


/* ============================================================
   RESTORE PELANGGAN
============================================================ */

function restorePelanggan(
    kodeBarang,
    pelangganId
) {

    const pelanggan =
        document.getElementById(
            'id_pelanggan_text_' +
            kodeBarang
        );


    if (
        !pelanggan ||
        !pelangganId
    ) {
        return;
    }


    $.ajax({

        url:
            '<?= base_url(
                'pelanggan/search'
            ) ?>',

        type: 'GET',

        dataType: 'json',

        data: {
            search: pelangganId
        },

        success:
            function (data) {

                const pelangganData =
                    data.find(
                        function (item) {

                            return String(
                                item.id
                            ) ===
                                String(
                                    pelangganId
                                );

                        }
                    );


                if (!pelangganData) {
                    return;
                }


                if (
                    $(pelanggan)
                        .find(
                            'option[value="' +
                            pelangganData.id +
                            '"]'
                        )
                        .length === 0
                ) {

                    const option =
                        new Option(
                            pelangganData.text,
                            pelangganData.id,
                            true,
                            true
                        );


                    $(pelanggan)
                        .append(
                            option
                        );

                }


                $(pelanggan)
                    .val(
                        pelangganData.id
                    )
                    .trigger(
                        'change'
                    );

            },

        error:
            function (xhr) {

                console.error(
                    'Gagal restore pelanggan:',
                    xhr.responseText
                );

            }

    });

}


/* ============================================================
   RESTORE PRODUCT
============================================================ */

function restoreProduct(
    kodeBarang
) {

    const data =
        selectedProducts[
            kodeBarang
        ];


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
            'satuan_terkecil_' +
            kodeBarang
        );

    const sumber =
        document.getElementById(
            'tipe_relasi_' + kodeBarang
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


    if (checkbox) {

        checkbox.checked =
            true;

    }


    if (jumlah) {

        jumlah.disabled =
            false;

        jumlah.value =
            data.jumlah ?? '';

    }


    if (satuan) {

        satuan.disabled =
            false;

        satuan.value =
            data.satuan_terkecil ??
            '';

    }


    if (sumber) {

        sumber.value =
            data.tipe_relasi ??
            '';


        if (
            sumber.hasAttribute(
                'data-always-disabled'
            )
        ) {

            sumber.disabled =
                true;

        } else {

            sumber.disabled =
                false;

        }

    }


    if (suplier) {

        suplier.value =
            data.id_suplier_text ??
            '';

    }


    if (
        data.id_pelanggan_text
    ) {

        restorePelanggan(
            kodeBarang,
            data.id_pelanggan_text
        );

    }


    toggleSumber(
        kodeBarang
    );

}


/* ============================================================
   GET KODE BARANG
============================================================ */

function getKodeBarangFromId(
    id
) {

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
            id.startsWith(
                prefix
            )
        ) {

            return id.substring(
                prefix.length
            );

        }

    }


    return null;

}


/* ============================================================
   BIND INPUT
============================================================ */

function bindProductInputs() {

    document
        .querySelectorAll(
            '.product-input'
        )
        .forEach(
            function (input) {


                input.addEventListener(
                    'input',
                    function () {

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
                    'blur',
                    function () {

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
                    function () {

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

            }
        );

}


/* ============================================================
   RESTORE CURRENT PAGE
============================================================ */

function restoreCurrentPage() {

    document
        .querySelectorAll(
            '.product-checkbox'
        )
        .forEach(
            function (checkbox) {

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

            }
        );


    bindProductInputs();

    updateSelectionIndicator();

}


/* ============================================================
   CLEAR ALL
============================================================ */

const openCancelModalBtn =
    document.getElementById(
        'openCancelModalBtn'
    );

const confirmClearSelectionBtn =
    document.getElementById(
        'confirmClearSelectionBtn'
    );


if (openCancelModalBtn) {

    openCancelModalBtn.addEventListener(
        'click',
        function () {

            const modalElement =
                document.getElementById(
                    'cancelConfirmModal'
                );


            if (
                modalElement &&
                typeof bootstrap !==
                    'undefined'
            ) {

                const modal =
                    new bootstrap.Modal(
                        modalElement
                    );


                modal.show();

            }

        }
    );

}


if (
    confirmClearSelectionBtn
) {

    confirmClearSelectionBtn.addEventListener(
        'click',
        function () {


            selectedProducts = {};


            localStorage.removeItem(
                STORAGE_KEY
            );


            document
                .querySelectorAll(
                    '.product-checkbox'
                )
                .forEach(
                    function (checkbox) {


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

                            jumlah.value =
                                '';

                            jumlah.disabled =
                                true;

                        }


                        if (satuan) {

                            satuan.value =
                                '';

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

                            $(pelanggan)
                                .val(null)
                                .trigger(
                                    'change'
                                );

                            $(pelanggan)
                                .prop(
                                    'disabled',
                                    true
                                );

                        }

                    }
                );


            updateSelectionIndicator();


            const modalElement =
                document.getElementById(
                    'cancelConfirmModal'
                );


            if (
                modalElement &&
                typeof bootstrap !==
                    'undefined'
            ) {

                const modal =
                    bootstrap.Modal
                        .getInstance(
                            modalElement
                        );


                if (modal) {
                    modal.hide();
                }

            }

        }
    );

}


/* ============================================================
   CREATE HIDDEN INPUT
============================================================ */

function createHiddenInput(
    form,
    name,
    value
) {

    const input =
        document.createElement(
            'input'
        );


    input.type =
        'hidden';

    input.name =
        name;

    input.value =
        value;

    input.className =
        'generated-selection-input';


    form.appendChild(
        input
    );

}


/* ============================================================
   SUBMIT STOK AWAL
============================================================ */

const stokAwalForm =
    document.getElementById(
        'stokAwalForm'
    );


const saveConfirmModalElement =
    document.getElementById(
        'saveConfirmModal'
    );


const confirmSaveStockBtn =
    document.getElementById(
        'confirmSaveStockBtn'
    );


const saveSelectedCount =
    document.getElementById(
        'saveSelectedCount'
    );


let confirmedSave =
    false;


/* ============================================================
   PROSES SUBMIT SEBENARNYA
============================================================ */

function submitStokAwal() {

    const form =
        stokAwalForm;


    if (!form) {
        return;
    }


    /* ========================================================
       HAPUS HIDDEN INPUT LAMA
    ======================================================== */

    form
        .querySelectorAll(
            '.generated-selection-input'
        )
        .forEach(
            function (input) {

                input.remove();

            }
        );


    const selectedIds =
        Object.keys(
            selectedProducts
        );


    /* ========================================================
       BUAT HIDDEN INPUT
    ======================================================== */

    selectedIds.forEach(
        function (kodeBarang) {

            const data =
                selectedProducts[
                    kodeBarang
                ];


            createHiddenInput(
                form,
                'selected_products[]',
                kodeBarang
            );


            createHiddenInput(
                form,
                'jumlah[' +
                    kodeBarang +
                    ']',
                data.jumlah ?? ''
            );


            createHiddenInput(
                form,
                'satuan_terkecil[' +
                    kodeBarang +
                    ']',
                data.satuan_terkecil ??
                    ''
            );


            createHiddenInput(
                form,
                'tipe_relasi[' +
                    kodeBarang +
                    ']',
                data.tipe_relasi ?? ''
            );


            createHiddenInput(
                form,
                'id_suplier_text[' +
                    kodeBarang +
                    ']',
                data.id_suplier_text ??
                    ''
            );


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


    /* ========================================================
       HAPUS STORAGE
    ======================================================== */

    localStorage.removeItem(
        STORAGE_KEY
    );


    /* ========================================================
       TANDAI SUDAH DIKONFIRMASI
    ======================================================== */

    confirmedSave =
        true;


    /* ========================================================
       SUBMIT NATIVE
    ======================================================== */

    form.submit();

}


/* ============================================================
   KLIK SIMPAN
============================================================ */

if (stokAwalForm) {

    stokAwalForm.addEventListener(
        'submit',
        function (event) {


            /* ==================================================
               JIKA SUDAH DIKONFIRMASI
            ================================================== */

            if (confirmedSave) {

                return;

            }


            /* ==================================================
               STOP SUBMIT LANGSUNG
            ================================================== */

            event.preventDefault();


            const selectedIds =
                Object.keys(
                    selectedProducts
                );


            /* ==================================================
               CEK PRODUK
            ================================================== */

            if (
                selectedIds.length ===
                0
            ) {

                alert(
                    'Tidak ada produk yang dipilih.'
                );

                return;

            }


            /* ==================================================
               TAMPILKAN JUMLAH PRODUK
            ================================================== */

            if (saveSelectedCount) {

                saveSelectedCount.textContent =
                    selectedIds.length;

            }


            /* ==================================================
               TAMPILKAN MODAL
            ================================================== */

            if (
                saveConfirmModalElement &&
                typeof bootstrap !==
                    'undefined'
            ) {

                const modal =
                    bootstrap.Modal
                        .getOrCreateInstance(
                            saveConfirmModalElement
                        );


                modal.show();

            }

        }
    );

}


/* ============================================================
   KONFIRMASI SIMPAN
============================================================ */

if (confirmSaveStockBtn) {

    confirmSaveStockBtn.addEventListener(
        'click',
        function () {


            /* ==================================================
               NONAKTIFKAN TOMBOL
            ================================================== */

            confirmSaveStockBtn.disabled =
                true;


            confirmSaveStockBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                ' Menyimpan...';


            /* ==================================================
               TUTUP MODAL
            ================================================== */

            if (
                saveConfirmModalElement &&
                typeof bootstrap !==
                    'undefined'
            ) {

                const modal =
                    bootstrap.Modal
                        .getInstance(
                            saveConfirmModalElement
                        );


                if (modal) {

                    modal.hide();

                }

            }


            /* ==================================================
               SUBMIT
            ================================================== */

            setTimeout(
                function () {

                    submitStokAwal();

                },
                150
            );

        }
    );

}


/* ============================================================
   INITIALIZE
============================================================ */

loadSelectedProducts();


$(document).ready(
    function () {


        /* ======================================================
           DATATABLE
        ====================================================== */

        $('#table_barang').DataTable({

            responsive: true,

            autoWidth: false,

            paging: false,

            searching: false,

            info: false

        });


        /* ======================================================
           SELECT2
        ====================================================== */

        initPelangganSelect();


        /* ======================================================
           RESTORE SELECTION
        ====================================================== */

        restoreCurrentPage();

    }
);

</script>