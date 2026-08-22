<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">


<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Kas Masuk</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Jurnal</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Kas Masuk</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <form action="<?= base_url('export_kas_masuk') ?>" id="form_kas_masuk" method="post" enctype="multipart/form-data">
        <div class="px-4 py-3 border-bottom">
            <button type="submit" class="btn btn-danger"
                style="margin-left: 20px; display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>
                Export
            </button>
        </div>

        <div class="row my-3 mx-1">
            <div class="mb-3 px-4">
                <label class="ms-3 me-2">Tanggal Awal:</label>
                <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                    style="width: auto; display: inline-block;" onchange="filterData()">

                <label class="ms-3 me-2">Tanggal Akhir:</label>
                <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                    style="width: auto; display: inline-block;" onchange="filterData()">

                <label class="ms-3 me-2">Nama Unit:</label>
                <select name="nama_unit" id="unitSelect" class="form-control d-inline"
                    style="width: auto; display: inline-block;" onchange="filterData()">
                    <option value="">Semua Unit</option>
                    <?php
                    $unitList = [];
                    foreach ($kas_masuk as $row) {
                        if (!in_array($row->NAMA_UNIT, $unitList)) {
                            $unitList[] = $row->NAMA_UNIT;
                            echo '<option value="' . esc($row->NAMA_UNIT) . '">' . esc($row->NAMA_UNIT) . '</option>';
                        }
                    }
                    ?>
                </select>

                <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
                <input type="hidden" id="hiddenNamaUnit" name="hiddenNamaUnit">
            </div>
        </div>
    </form>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2"></div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-kas-modal"
            style="display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:wallet-money-line-duotone" width="24" height="24" style="margin-right: 8px;">
            </iconify-icon>Input Kas Masuk
        </button>
    </div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>Tanggal</th>
                    <th>Unit</th>
                    <th>Nomor Akun</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th>Nama Bank</th>
                    <th>Penerima</th>
                    <th>No Rekening</th>
                    <th>Jumlah</th>
                    <th>Jenis</th>
                  
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($kas_masuk)): ?>
                    <?php foreach ($kas_masuk as $row): ?>
                        <tr>
                            <td><?= esc(date('d-m-Y', strtotime($row->tanggal))) ?></td>
                            <td><?= esc($row->NAMA_UNIT) ?></td>
                            <td><?= esc($row->no_akun) ?></td>
                            <td><?= esc($row->kategori) ?></td>
                            <td><?= esc($row->deskripsi) ?></td>
                            <td><?= esc($row->nama_bank) ?></td>
                            <td><?= esc($row->penerima) ?></td>
                            <td><?= esc($row->norek) ?></td>
                            <td>Rp.<?= number_format($row->jumlah, 0, ',', '.') ?></td>
                            <td><?= esc($row->jenis) ?></td>
                            
                        </tr>
                    <?php endforeach; ?>                
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input -->
<div class="modal fade" id="input-kas-modal" tabindex="-1" aria-labelledby="inputKasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <form action="<?= base_url('insert_kas_masuk') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Input Kas masuk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div style="display: flex; justify-content: space-between; padding: 20px;">
                        <div class="mb-3">
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input style="width: 500px;" type="date" class="form-control" name="tanggal" required>
                        </div>
                        <div class="mb-3">
                            <label style="margin-left: 20px;" for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea style="width: 500px; margin-left: 20px;" class="form-control" name="deskripsi"
                                required></textarea>
                        </div>

                    </div>


                    <div class="mb-3" style="padding-left: 20px;">
                        <label for="unit_idunit">Unit</label>
                        <select class="form-control select2" name="unit_idunit" id="unit_idunit" required>
                            <option value="">Pilih Unit</option>
                            <?php foreach ($unit as $b): ?>
                                <option value="<?= esc($b->idunit) ?>">
                                    <?= esc($b->NAMA_UNIT) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>


                    <div style="margin-left: 20px;">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#modalCariBuku">
                            Cari Buku
                        </button>
                    </div>

                    <div class="table-responsive px-3 mt-3">
                        <table class="table table-bordered" id="akun-terpilih-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: fit-content;">No Akun</th>
                                    <th style="width: fit-content;">Jenis Akun</th>
                                    <th style="width: fit-content;">kategori</th>
                                    <th style="width: fit-content;">Jenis Transaksi</th>
                                    <th style="width: fit-content;">No Rekening</th>
                                    <th style="width: fit-content;">Penerima</th>
                                    <th style="width: fit-content;">Jumlah</th>
                                    <th style="width: fit-content;">Jenis</th>
                                    <th style="width: fit-content;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="akun-terpilih-container">
                                <!-- Baris akan ditambahkan di sini -->
                            </tbody>
                        </table>
                    </div>


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Cari Buku -->
<div class="modal fade" id="modalCariBuku" tabindex="-1" aria-labelledby="modalCariBukuLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <!-- Modal ukuran besar -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Daftar Buku</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-striped" id="table_kasmasuk">
                    <thead>
                        <tr>
                            <th>No Akun</th>
                            <th>Nama Akun</th>
                            <th>Jenis Akun</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($no_akun as $row): ?>
                            <tr>
                                <td><?= esc($row->no_akun) ?></td>
                                <td><?= esc($row->nama_akun) ?></td>
                                <td><?= esc($row->jenis_akun) ?></td>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm pilih-akun"
                                        data-no="<?= esc($row->no_akun) ?>" data-jenis="<?= esc($row->jenis_akun) ?>"
                                        data-bs-dismiss="modal">
                                        Pilih
                                    </button>

                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>




<!-- Modal Edit -->
<div class="modal fade" id="edit-kas-modal" tabindex="-1" aria-labelledby="editKasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('update_kas_masuk') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kas masuk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idkas_masuk" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_tanggal" class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_kategori" class="form-label">Kategori</label>
                        <select class="form-control" name="kategori_idkategori" id="edit_kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_kas as $kat): ?>
                                <option value="<?= esc($kat->idkategori_kas) ?>"><?= esc($kat->kategori) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="edit_deskripsi" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jumlah" class="form-label">Jumlah</label>
                        <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_penerima">Penerima</label>
                        <select class="form-control select2" name="penerima" id="edit_penerima" required>
                            <option value="">Pilih Bank</option>
                            <?php foreach ($bank as $b): ?>
                                <option value="<?= esc($b->idbank) ?>">
                                    <?= esc($b->nama_bank . ' ' . ($b->atas_nama). ' : ' . $b->norek) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>

                    <div class="mb-3">
                        <label for="edit_posisi_drk" class="form-label">Posisi</label>
                        <select class="form-control" name="posisi_drk" id="edit_posisi_drk">
                            <option value="">-- Pilih --</option>
                            <option value="debet">Debet</option>
                            <option value="kredit">Kredit</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="delete-kas-modal" tabindex="-1" aria-labelledby="deleteKasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('delete_kas_masuk') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Kas masuk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="idkas_masuk">
                    <p>Apakah Anda yakin ingin menghapus data ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('#zero_config').addEventListener('click', function(e) {
            if (e.target.closest('.edit-button')) {
                const btn = e.target.closest('.edit-button');

                // Isi field biasa
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_tanggal').value = btn.dataset.tanggal;
                document.getElementById('edit_kategori').value = btn.dataset.kategori;
                document.getElementById('edit_deskripsi').value = btn.dataset.deskripsi;
                document.getElementById('edit_jumlah').value = btn.dataset.jumlah;
                document.getElementById('edit_posisi_drk').value = btn.dataset.jenis;

                // Atur value select2 (penerima / idbank)
                const selectPenerima = $('#edit_penerima');
                const idbank = btn.dataset.idbank;

                // Set value dan trigger select2
                selectPenerima.val(idbank).trigger('change');
            }

            if (e.target.closest('.delete-button')) {
                const btn = e.target.closest('.delete-button');
                document.getElementById('delete_id').value = btn.dataset.id;
            }
        });
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        let akunIndex = 0;

        // Inisialisasi DataTable
        let table = $('#table_kasmasuk').DataTable();

        // Event delegation: tombol pilih-akun tetap berfungsi walaupun pagination
        $('#table_kasmasuk').on('click', '.pilih-akun', function() {
            const noAkun = $(this).attr('data-no');
            const jenisAkun = $(this).attr('data-jenis');

            const container = document.getElementById('akun-terpilih-container');

            const row = document.createElement('tr');
            row.className = 'akun-row';
            row.innerHTML = `
            <td><input type="text" class="form-control" name="akun[${akunIndex}][no_akun]" value="${noAkun}" readonly></td>
            <td><input type="text" class="form-control" name="akun[${akunIndex}][jenis_akun]" value="${jenisAkun}" readonly></td>
            <td>
                <select style="width: 100px;" class="form-control" name="akun[${akunIndex}][kategori_idkategori]">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategori_kas as $kat): ?>
                        <option value="<?= esc($kat->idkategori_kas) ?>"><?= esc($kat->kategori) ?></option>
                    <?php endforeach; ?>
                </select>
            </td> 
            <td>
                <select class="form-control jenis-transaksi" name="akun[${akunIndex}][jenis_transaksi]">
                    <option value="">-- Pilih Jenis Transaksi --</option>
                    <option value="cash">Kas</option>
                    <option value="bank">Bank</option>
                </select>
            </td>
            <td>
                <select class="form-control select2-rekening" name="akun[${akunIndex}][no_rekening]" disabled>
                    <?= esc('<option value="">-- Pilih No Rekening --</option>') ?>
                    <?php foreach ($bank as $b): ?>
                        <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank) . ' ' . ($b->atas_nama)?> : <?= esc($b->norek) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" class="form-control" name="akun[${akunIndex}][penerima]" placeholder=""></td>
            <td><input type="text" class="form-control jumlah-input" name="akun[${akunIndex}][jumlah]" placeholder="Rp."></td>
            <td>
                <select style="width:100px;" class="form-control" name="akun[${akunIndex}][posisi_drk]">
                    <option value="">-- Pilih --</option>
                    <option value="debet">Debet</option>
                    <option value="kredit">Kredit</option>
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm remove-akun">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

            container.appendChild(row);
            akunIndex++;

            // Select2
            $(row).find('.select2-rekening').select2({
                dropdownParent: $('#input-kas-modal'),
                width: '100%'
            });

            // Enable/Disable rekening saat pilih jenis transaksi
            row.querySelector('.jenis-transaksi').addEventListener('change', function() {
                const rekeningSelect = row.querySelector('.select2-rekening');
                if (this.value === 'bank') rekeningSelect.disabled = false;
                else {
                    rekeningSelect.disabled = true;
                    $(rekeningSelect).val('').trigger('change');
                }
            });

            // Format input jumlah ke Rupiah ribuan (tanpa Rp)
            const jumlahInput = row.querySelector('.jumlah-input');
            jumlahInput.addEventListener('input', function() {
                let angka = this.value.replace(/[^0-9]/g, "");
                this.value = 'Rp.' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            });

            // Tutup modal Cari Buku → kembali buka modal input kas
            bootstrap.Modal.getInstance(document.getElementById('modalCariBuku')).hide();
            new bootstrap.Modal(document.getElementById('input-kas-modal')).show();
        });

        // Hapus row akun
        document.getElementById('akun-terpilih-container').addEventListener('click', function(e) {
            if (e.target.closest('.remove-akun')) e.target.closest('tr').remove();
        });

        document.getElementById('form_kas_masuk').addEventListener('submit', function() {
            document.querySelectorAll('.jumlah-input').forEach(function(el) {
                el.value = el.value.replace(/[^0-9]/g, ""); // hapus Rp, titik, semua non angka
            });
        });


    });
</script>


<script>
    const bankOptions = `
        <option value="">-- Pilih No Rekening --</option>
        <?php foreach ($bank as $b): ?>
            <option value="<?= esc($b->idbank) ?>">
                <?= esc($b->nama_bank) . ' ' . ($b->atas_nama)?> : <?= esc($b->norek) ?>
            </option>
        <?php endforeach; ?>
    `;
</script>

<script>
    $(document).ready(function() {
        $('#edit_penerima').select2({
            dropdownParent: $('#edit-kas-modal')
        });
    });

    $(document).ready(function() {
        $('#unit_idunit').select2({
            dropdownParent: $('#input-kas-modal')
        });
    });
</script>


<script>
    window.onload = function() {
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');

        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);


        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        startDateInput.value = toDateInputValue(fifteenDaysAgo);
        endDateInput.value = toDateInputValue(today);

        const unitSelect = document.getElementById('unitSelect');
        if (unitSelect.options.length > 1) {
            unitSelect.selectedIndex = 1;
        }

        filterData();
    };

    function filterData() {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        const selectedUnit = document.getElementById('unitSelect').value.toLowerCase();

        const rows = document.querySelectorAll('#zero_config tbody tr');
        rows.forEach(row => {
            const dateCell = row.children[0];
            const unitCell = row.children[1];
            if (!dateCell || !unitCell) return;

            // Ambil dan parsing tanggal
            const dateText = dateCell.textContent.trim();
            const parts = dateText.split('-');
            const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`); // ubah ke Y-m-d

            const startDate = start ? new Date(start) : null;
            const endDate = end ? new Date(end) : null;

            // Ambil dan cocokan nama unit
            const unitName = unitCell.textContent.trim().toLowerCase();
            const unitMatch = selectedUnit === "" || unitName === selectedUnit;

            let dateMatch = true;
            if (startDate && rowDate < startDate) dateMatch = false;
            if (endDate && rowDate > endDate) dateMatch = false;

            // Tampilkan baris jika dua-duanya match
            row.style.display = (unitMatch && dateMatch) ? '' : 'none';
        });
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('unitSelect').value = '';
        filterData();
    }
</script>
