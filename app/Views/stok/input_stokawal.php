<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="card shadow-none position-relative overflow-hidden mb-4">

    <div class="card-body d-flex align-items-center justify-content-between p-4">

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

                <li class="breadcrumb-item active" aria-current="page">
                    Stok Awal
                </li>

            </ol>

        </nav>

    </div>

</div>


<div class="card w-100 position-relative overflow-hidden">

    <form
        action="<?= base_url('insert/stokawal') ?>"
        enctype="multipart/form-data"
        method="post"
    >

        <!-- UNIT -->
        <div style="display: flex; margin-top: 20px; margin-left: 20px; gap: 20px;">

            <label
                for="global_unit"
                class="col-form-label"
            >
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


        <!-- TABLE -->
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

                            <!-- PILIH -->
                            <td>

                                <input
                                    type="checkbox"
                                    name="selected_products[]"
                                    value="<?= esc($kode_barang) ?>"
                                    id="product_<?= esc($kode_barang) ?>"
                                    onchange="toggleCheckbox('<?= esc($kode_barang) ?>')"
                                >

                            </td>


                            <!-- BARANG -->
                            <td style="min-width: 140px; text-align: center;">

                                <p style="font-weight: bold; margin-bottom: 4px;">
                                    <?= esc($kode_barang) ?>
                                </p>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->nama_barang) ?>
                                </p>

                            </td>


                            <!-- IMEI -->
                            <td>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->imei ?? 'tidak ada imei') ?>
                                </p>

                            </td>


                            <!-- HPP -->
                            <td>

                                <p style="font-style: italic; margin-bottom: 0;">
                                    <?= esc($b->harga_beli ?? 'tidak ada HPP') ?>
                                </p>

                            </td>


                            <!-- JUMLAH -->
                            <td>

                                <input
                                    type="number"
                                    name="jumlah[<?= esc($kode_barang) ?>]"
                                    class="form-control"
                                    id="jumlah_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 120px;"
                                    required
                                >

                            </td>


                            <!-- SATUAN -->
                            <td>

                                <select
                                    name="satuan_terkecil[<?= esc($kode_barang) ?>]"
                                    class="form-select"
                                    id="satuan_terkecil_<?= esc($kode_barang) ?>"
                                    disabled
                                    style="min-width: 190px;"
                                    required
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
                                    class="form-select"
                                    id="tipe_relasi_<?= esc($kode_barang) ?>"
                                    onchange="toggleSumber('<?= esc($kode_barang) ?>')"
                                    <?= $isImeiEmpty ? 'disabled' : '' ?>
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


                            <!-- SUPLIER -->
                            <td>

                                <select
                                    name="id_suplier_text[<?= esc($kode_barang) ?>]"
                                    class="form-select"
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


                            <!-- PELANGGAN -->
                            <td>

                                <select
                                    name="id_pelanggan_text[<?= esc($kode_barang) ?>]"
                                    class="form-select"
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


                            <!-- UNIT HIDDEN -->
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


                            <!-- KODE BARANG -->
                            <td hidden>
                                <?= esc($kode_barang) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- SERVER-SIDE PAGINATION -->
        <div class="px-4 pb-3">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <!-- INFO KIRI -->
                <div class="text-muted small">

                    Menampilkan

                    <strong>
                        <?= $total > 0
                            ? (($currentPage - 1) * $perPage) + 1
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


                <!-- PAGINATION KANAN -->
                <?php if ($totalPages > 1): ?>

                    <nav aria-label="Pagination stok awal">

                        <ul class="pagination mb-0">

                            <!-- SEBELUMNYA -->
                            <?php if ($currentPage > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $currentPage - 1 ?>"
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
                            /*
                             * Pagination:
                             *
                             * <= 7 halaman:
                             * 1 2 3 4 5 6 7
                             *
                             * Halaman awal:
                             * 1 2 3 4 ... 8 9
                             *
                             * Halaman tengah:
                             * 1 ... 4 5 6 ... 9
                             *
                             * Halaman akhir:
                             * 1 2 ... 6 7 8 9
                             */

                            if ($totalPages <= 7):

                                for ($i = 1; $i <= $totalPages; $i++):
                            ?>

                                    <li
                                        class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                            <?php elseif ($currentPage <= 4): ?>

                                <!-- 1 2 3 4 ... 8 9 -->

                                <?php for ($i = 1; $i <= 4; $i++): ?>

                                    <li
                                        class="page-item <?= $i === (int) $currentPage ? 'active' : '' ?>"
                                    >

                                        <a
                                            class="page-link"
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?>"
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
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages - 1 ?>"
                                    >
                                        <?= $totalPages - 1 ?>
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>

                                </li>


                            <?php elseif ($currentPage >= $totalPages - 3): ?>

                                <!-- 1 2 ... 6 7 8 9 -->

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=1"
                                    >
                                        1
                                    </a>

                                </li>


                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=2"
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
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?>"
                                        >
                                            <?= $i ?>
                                        </a>

                                    </li>

                                <?php endfor; ?>


                            <?php else: ?>

                                <!-- 1 ... 4 5 6 ... 9 -->

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=1"
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
                                            href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $i ?>"
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
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $totalPages ?>"
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
                                        href="<?= base_url('input_stokawal/' . urlencode($jenis)) ?>?page=<?= $currentPage + 1 ?>"
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


        <!-- BUTTON -->
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


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


<script>

function toggleCheckbox(kodeBarang) {

    const checkbox = document.getElementById(
        'product_' + kodeBarang
    );

    if (!checkbox) {
        return;
    }

    const checked = checkbox.checked;

    const jumlah = document.getElementById(
        'jumlah_' + kodeBarang
    );

    const satuan = document.getElementById(
        'satuan_terkecil_' + kodeBarang
    );

    const sumber = document.getElementById(
        'tipe_relasi_' + kodeBarang
    );

    const suplier = document.getElementById(
        'id_suplier_text_' + kodeBarang
    );

    const pelanggan = document.getElementById(
        'id_pelanggan_text_' + kodeBarang
    );


    // JUMLAH
    if (jumlah) {

        jumlah.disabled = !checked;

        if (!checked) {
            jumlah.value = '';
        }

    }


    // SATUAN
    if (satuan) {

        satuan.disabled = !checked;

        if (!checked) {
            satuan.value = '';
        }

    }


    // TIPE RELASI
    if (sumber) {

        if (!sumber.hasAttribute('disabled')) {
            sumber.disabled = !checked;
        }

        if (!checked) {
            sumber.value = '';
        }

    }


    // SUPLIER
    if (suplier) {

        suplier.disabled = true;

        if (!checked) {
            suplier.value = '';
        }

    }


    // PELANGGAN
    if (pelanggan) {

        pelanggan.disabled = true;

        if (!checked) {
            pelanggan.value = '';
        }

    }


    // UPDATE TIPE SUMBER
    if (checked) {

        setTimeout(function () {

            toggleSumber(kodeBarang);

        }, 10);

    }

}


function toggleSumber(kodeBarang) {

    const sumber = document.getElementById(
        'tipe_relasi_' + kodeBarang
    );

    const suplier = document.getElementById(
        'id_suplier_text_' + kodeBarang
    );

    const pelanggan = document.getElementById(
        'id_pelanggan_text_' + kodeBarang
    );

    const checkbox = document.getElementById(
        'product_' + kodeBarang
    );


    if (!sumber || !checkbox) {
        return;
    }


    // RESET
    if (suplier) {

        suplier.disabled = true;
        suplier.value = '';

    }

    if (pelanggan) {

        pelanggan.disabled = true;
        pelanggan.value = '';

    }


    if (!checkbox.checked) {
        return;
    }


    // AKTIFKAN BERDASARKAN TIPE
    if (sumber.value === 'suplier' && suplier) {

        suplier.disabled = false;

    } else if (
        sumber.value === 'pelanggan' &&
        pelanggan
    ) {

        pelanggan.disabled = false;

    }

}


$(document).ready(function () {

    /*
     * DataTables hanya digunakan untuk fitur tabel.
     * Pagination dilakukan sepenuhnya oleh server.
     */

    $('#table_barang').DataTable({

        responsive: true,

        autoWidth: false,

        paging: false,

        searching: false,

        info: false

    });


    /*
     * Inisialisasi item tanpa IMEI.
     */

    document
        .querySelectorAll('[id^="tipe_relasi_"]')
        .forEach(function (sumber) {

            if (!sumber.hasAttribute('disabled')) {
                return;
            }

            const kodeBarang = sumber.id.replace(
                'tipe_relasi_',
                ''
            );

            const checkbox = document.getElementById(
                'product_' + kodeBarang
            );

            if (!checkbox || !checkbox.checked) {
                return;
            }

            const suplier = document.getElementById(
                'id_suplier_text_' + kodeBarang
            );

            if (suplier) {
                suplier.disabled = false;
            }

        });

});

</script>