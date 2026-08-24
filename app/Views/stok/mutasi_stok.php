<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

<!-- Page Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Mutasi Stok</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Mutasi Stok</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Form & Main Content -->
<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2">
        <form action="<?= base_url('insert_mutasi') ?>" method="post" enctype="multipart/form-data">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="nama_unit1" class="form-label">Unit Pengirim</label>
                    <select class="form-select" id="unit1" name="unit1" required
                        onchange="tampilkanIdUnit1()">
                        <option value="" selected>Pilih Unit</option>
                        <?php foreach ($unit as $b): ?>
                            <option value="<?= $b->idunit; ?>"><?= $b->NAMA_UNIT; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input hidden name="id_unit1_text" type="hidden" id="id_unit1_text"
                        class="text-muted d-block mt-2"></input>
                </div>

                <div class="col-md-6">
                    <label for="nama_unit2" class="form-label">Unit Penerima</label>
                    <select class="form-select" id="unit2" name="unit2" required
                        onchange="tampilkanIdUnit2()">
                        <option value="" selected>Pilih Unit</option>
                        <?php foreach ($unit as $b): ?>
                            <option value="<?= $b->idunit; ?>"><?= $b->NAMA_UNIT; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="id_unit2_text" type="hidden" id="id_unit2_text"
                        class="text-muted d-block mt-2"></input>
                </div>


                <?php
                $isAdmin = session('ID_UNIT') == 1;
                $today   = date('Y-m-d');
                ?>

                <div class="col-md-3">
                    <label for="tanggal_kirim" class="form-label">Tanggal Kirim</label>
                    <input
                        type="date"
                        class="form-control"
                        id="tanggal_kirim"
                        name="tanggal_kirim"
                        value="<?= $today ?>"
                        <?= $isAdmin ? '' : 'readonly' ?>
                        required>
                </div>

                <div class="col-md-3">
                    <label for="tanggal_terima" class="form-label">Tanggal Terima</label>
                    <input
                        type="date"
                        class="form-control"
                        id="tanggal_terima"
                        name="tanggal_terima"
                        value="<?= $today ?>"
                        <?= $isAdmin ? '' : 'readonly' ?>
                        required>
                </div>

            </div>

            <!-- Button Trigger Modal -->
            <div class="mt-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#pilih-produk-modal" style="display: inline-flex; align-items: center;">
                    <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                        style="margin-right: 8px;"></iconify-icon>
                    Pilih Produk
                </button>
            </div>

            <!-- Modal Pilih Produk -->
            <div class="modal fade" id="pilih-produk-modal" tabindex="-1" aria-labelledby="produkModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Pilih Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive mb-4 px-4">
                                <table class="table border text-nowrap mb-0 align-middle" id="produk-modal-table">
                                    <thead class="text-dark fs-4">
                                        <tr>
                                            <th><input type="checkbox" id="select-all-produk"></th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Kode Barang</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                                            </th>

                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Imei</h6>
                                            </th>

                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Harga</h6>
                                            </th>

                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Kategori</h6>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produk as $p): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="produk-checkbox"
                                                        data-id="<?= $p->idbarang ?>" data-kode="<?= $p->kode_barang ?>"
                                                        data-nama="<?= $p->nama_barang ?>" data-harga="<?= $p->harga ?>"
                                                        data-harga_beli="<?= $p->harga_beli ?>"
                                                        data-harga_jual="<?= $p->harga ?>"
                                                        data-kategori="<?= $p->nama_kategori ?>"
                                                        data-input="<?= $p->input ?>"
                                                        data-imei="<?= $p->imei ?>">
                                                </td>
                                                <td><?= $p->kode_barang ?></td>
                                                <td><?= $p->nama_barang ?></td>
                                                <td><?= !empty($p->imei) ? esc($p->imei) : 'Tidak ada IMEI' ?></td>
                                                <td><?= 'Rp ' . number_format($p->harga_beli, 0, ',', '.') ?></td>
                                                <td><?= $p->nama_kategori ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="confirm-produk-btn" class="btn btn-primary">Pilih</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected Produk Table -->
            <div class="table-responsive mt-3 mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Imei</th>
                            <th>Harga Beli</th>
                            <th>Harga Jual</th>
                            <th>Harga Mutasi</th>
                            <th>Jumlah Kirim</th>
                            <th>Jumlah Terima</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="selected-produk-table">
                        <!-- Rows will be dynamically added -->
                    </tbody>
                    <tfoot>

                    </tfoot>
                </table>

                <button style="height: fit-content;" class="btn btn-success mt-3" type="submit">Simpan</button>




            </div>

            <script>
                function formatToRupiah(angka) {
                    const cleaned = angka.replace(/[^\d]/g, '');
                    const number = parseInt(cleaned) || 0;
                    return 'Rp ' + number.toLocaleString('id-ID');
                }

                function unformatRupiah(rupiah) {
                    return parseInt(rupiah.replace(/[^\d]/g, '')) || 0;
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const selectedTable = document.getElementById('selected-produk-table');
                    const confirmBtn = document.getElementById('confirm-produk-btn');
                    const selectAll = document.getElementById('select-all-produk');
                    const modalEl = document.getElementById('pilih-produk-modal');
                    const modalInstance = new bootstrap.Modal(modalEl);
                    const bayarInput = document.getElementById('bayar');

                    selectAll.addEventListener('change', function() {
                        document.querySelectorAll('.produk-checkbox').forEach(cb => cb.checked = this.checked);
                    });

                    confirmBtn.addEventListener('click', function() {
                        const dataTable = $('#produk-modal-table').DataTable();
                        const allCheckboxes = dataTable.rows({
                                search: 'applied'
                            })
                            .nodes().to$().find('.produk-checkbox:checked');

                        allCheckboxes.each(function() {
                            const cb = this;
                            const id = cb.getAttribute('data-id');
                            const kode = cb.getAttribute('data-kode');
                            const nama = cb.getAttribute('data-nama');
                            const harga_beli = cb.getAttribute('data-harga_beli');
                            const kategori = cb.getAttribute('data-kategori');
                            const imei = cb.getAttribute('data-imei');
                            const harga_jual = cb.getAttribute('data-harga_jual');

                            if (!document.getElementById('produk-row-' + id)) {
                                const row = document.createElement('tr');
                                row.id = 'produk-row-' + id;
                                row.innerHTML = `
                    <td>${kode}<input type="hidden" name="produk[${id}][id]" value="${id}">
                        <input type="hidden" name="produk[${id}][kode]" value="${kode}"></td>
                    <td>${nama}<input type="hidden" name="produk[${id}][nama]" value="${nama}"></td>
                    <td>${kategori}<input type="hidden" name="produk[${id}][kategori]" value="${kategori}"></td>
                    <td>${imei ? imei : '<span class="text-danger">Tidak ada IMEI</span>'}
                        <input type="hidden" name="produk[${id}][imei]" value="${imei}"></td>
                    <td>${formatToRupiah(harga_beli)}
                        <input type="hidden" name="produk[${id}][harga_beli]" value="${harga_beli}"></td>
                    <td>${formatToRupiah(harga_jual)}
                        <input type="hidden" name="produk[${id}][harga_jual]" value="${harga_jual}"></td>
                    <td>
                        <input required type="text" class="form-control harga-mutasi" placeholder="Rp 0">
                        <input type="hidden" name="produk[${id}][harga_mutasi]" class="harga-mutasi-hidden">
                    </td>
                    <td><input required type="number" name="produk[${id}][jumlah_kirim]" class="form-control jumlah-input" data-id="${id}" min="1"></td>
                    <td><input required type="number" name="produk[${id}][jumlah_terima]" class="form-control jumlah-input" data-id="${id}" min="1"></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="hapusProduk('${id}')">
                        <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="20" height="20"></iconify-icon></button></td>
                `;
                                selectedTable.appendChild(row);

                                // ✅ Validasi harga mutasi agar ≤ harga jual tanpa alert pop-up
                                const hargaMutasiInput = row.querySelector('.harga-mutasi');
                                const hargaMutasiHidden = row.querySelector('.harga-mutasi-hidden');
                                const hargaJual = parseInt(harga_jual);

                                hargaMutasiInput.addEventListener('input', function() {
                                    let cleaned = this.value.replace(/[^\d]/g, '');
                                    let numeric = parseInt(cleaned) || 0;

                                    if (numeric > hargaJual) {
                                        numeric = hargaJual;
                                        this.classList.add("is-invalid"); // Tambah efek merah
                                    } else {
                                        this.classList.remove("is-invalid");
                                    }

                                    hargaMutasiHidden.value = numeric;
                                    this.value = numeric ? formatToRupiah(numeric.toString()) : '';
                                    this.setSelectionRange(this.value.length, this.value.length);
                                });

                                row.querySelector('.jumlah-input').addEventListener('input', updateTotals);
                            }
                        });

                        modalInstance.hide();
                        setTimeout(() => {
                            document.body.classList.remove('modal-open');
                            document.body.style.removeProperty('padding-right');
                            document.body.style.removeProperty('overflow');
                            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                        }, 500);

                        dataTable.rows().nodes().to$().find('.produk-checkbox').prop('checked', false);
                        selectAll.checked = false;

                        updateTotals();
                    });

                    bayarInput.addEventListener('input', function() {
                        const numeric = this.value.replace(/[^\d]/g, '');
                        this.value = formatToRupiah(numeric);
                        updateHutang();
                    });
                });

                function hapusProduk(id) {
                    const row = document.getElementById('produk-row-' + id);
                    if (row) row.remove();
                    updateTotals();
                }

                function updateTotals() {
                    let total = 0;
                    let totalDiskon = 0;
                    let totalPPN = 0;

                    document.querySelectorAll('#selected-produk-table tr').forEach(row => {
                        const hargaInput = row.querySelector('input[name$="[harga]"]');
                        const jumlahInput = row.querySelector('.jumlah-input');
                        const diskonInput = row.querySelector('.diskon-input');
                        const ppnCheckbox = row.querySelector('.ppn-checkbox');

                        if (hargaInput && jumlahInput && diskonInput) {
                            const harga = parseInt(hargaInput.value) || 0;
                            const jumlah = parseInt(jumlahInput.value) || 0;
                            const diskon = parseFloat(diskonInput.value) || 0;
                            const isPpn = ppnCheckbox?.checked;

                            let subtotal = harga * jumlah;
                            let setelahDiskon = subtotal - diskon;
                            let ppnAmount = isPpn ? setelahDiskon * 0.11 : 0;

                            totalDiskon += diskon;
                            totalPPN += ppnAmount;
                            total += setelahDiskon + ppnAmount;
                        }
                    });

                    const totalDiskonInput = document.getElementById('total-diskon');
                    totalDiskonInput.min = totalDiskon;

                    let manualDiskon = parseFloat(totalDiskonInput.value);
                    if (isNaN(manualDiskon) || manualDiskon < totalDiskon) {
                        manualDiskon = totalDiskon;
                        totalDiskonInput.value = totalDiskon;
                    }

                    const totalHargaFinal = (total - (manualDiskon - totalDiskon));
                    document.getElementById('total-ppn').value = 'Rp ' + totalPPN.toLocaleString('id-ID');
                    document.getElementById('total-harga').value = 'Rp ' + totalHargaFinal.toLocaleString('id-ID');

                    updateHutang();
                }

                document.getElementById('total-diskon').addEventListener('input', () => {
                    updateTotals(true);
                });

                function updateHutang() {
                    const totalStr = document.getElementById('total-harga').value.replace(/[^\d]/g, '') || "0";
                    const bayarStr = document.getElementById('bayar').value || "Rp 0";
                    const bayar = unformatRupiah(bayarStr);
                    const total = parseInt(totalStr);
                    const hutang = Math.max(total - bayar, 0);

                    document.getElementById('hutang').value = 'Rp ' + hutang.toLocaleString('id-ID');
                }
            </script>



            <script>
                $(document).ready(function() {
                    var table = $('#produk-modal-table').DataTable();


                });
            </script>


            <script>
                document.getElementById('notaFileInput').addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    const preview = document.getElementById('previewNota');

                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        };

                        reader.readAsDataURL(file);
                    } else {
                        preview.src = '#';
                        preview.style.display = 'none';
                    }
                });
            </script>

            <script>
                function tampilkanIdUnit1() {
                    const select = document.getElementById('unit1');
                    const idUnit1Input = document.getElementById('id_unit1_text');
                    const selectedValue = select.value;
                    idUnit1Input.value = selectedValue;
                }

                function tampilkanIdUnit2() {
                    const select = document.getElementById('unit2');
                    const idUnit2Input = document.getElementById('id_unit2_text');
                    const selectedValue = select.value;
                    idUnit2Input.value = selectedValue;
                }
            </script>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const selectAllPPN = document.getElementById('select-all-ppn');

                    selectAllPPN.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.ppn-checkbox');
                        checkboxes.forEach(cb => cb.checked = selectAllPPN.checked);
                        updateTotals();
                    });
                });
            </script>