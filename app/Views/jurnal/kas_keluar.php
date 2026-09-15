<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Kas Keluar</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Jurnal</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Kas Keluar</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session('sukses')) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= esc(session('sukses')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-3">
        <form id="filterForm" class="row g-2 align-items-end flex-fill">
            <div class="col-auto">
                <label class="form-label small mb-1">Tanggal Awal</label>
                <input type="date" name="tanggal_awal" id="startDate" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" id="endDate" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Unit</label>
                <select name="unit_id" id="unitSelect" class="form-select form-select-sm">
                    <option value="">Semua Unit</option>
                    <?php foreach ($unit as $u) : ?>
                        <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" id="btnApply" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <button type="button" id="btnReset" class="btn btn-sm btn-secondary">Reset</button>
            </div>
        </form>
        <div class="ms-auto d-flex gap-2">
            <form action="<?= base_url('export_kas_keluar') ?>" method="post" id="exportForm">
                <input type="hidden" name="tanggal_awal" id="expStart">
                <input type="hidden" name="tanggal_akhir" id="expEnd">
                <input type="hidden" name="unit_id" id="expUnit">
                <button type="submit" class="btn btn-danger">
                    <iconify-icon icon="solar:export-broken" width="20" height="20"></iconify-icon>
                    <span class="ms-1">Export</span>
                </button>
            </form>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-kas-modal">
                <iconify-icon icon="solar:wallet-money-line-duotone" width="20" height="20"></iconify-icon>
                <span class="ms-1">Input Kas Keluar</span>
            </button>
        </div>
    </div>

    <div class="card-body px-4 pt-3">
        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="table_kas_keluar" style="width:100%">
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
                        <th class="text-end">Jumlah</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-center" style="width:90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="table-light fw-semibold">
                        <td colspan="8" class="text-end">Total</td>
                        <td class="text-end" id="sumJumlah">-</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Input -->
<div class="modal fade" id="input-kas-modal" tabindex="-1" aria-labelledby="inputKasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form action="<?= base_url('insert_kas_keluar') ?>" method="post" id="form_kas_keluar">
                <div class="modal-header">
                    <h5 class="modal-title">Input Kas Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="tanggal" required>
                        </div>
                        <div class="col-md-4">
                            <label for="unit_idunit" class="form-label">Unit</label>
                            <select class="form-control" name="unit_idunit" id="unit_idunit" required>
                                <option value="">Pilih Unit</option>
                                <?php foreach ($unit as $u) : ?>
                                    <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" id="deskripsi"
                                placeholder="contoh: Pembelian ATK">
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0 text-primary"><i class="bi bi-journal-text me-1"></i>Posisi Akun</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnTambahAkun">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Akun
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="akun-terpilih-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:340px;">No Akun</th>
                                    <th style="min-width:150px;">Kategori</th>
                                    <th style="min-width:110px;">Sumber Dana</th>
                                    <th style="min-width:180px;">No Rekening</th>
                                    <th style="min-width:140px;">Penerima</th>
                                    <th style="min-width:110px;">Posisi</th>
                                    <th style="min-width:140px;">Jumlah</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="akun-terpilih-container"></tbody>
                        </table>
                    </div>

                    <div class="text-end fw-semibold">
                        Total: <span class="text-primary" id="sumRowJumlah">Rp 0</span>
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

<!-- Modal Edit -->
<div class="modal fade" id="edit-kas-modal" tabindex="-1" aria-labelledby="editKasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('update_kas_keluar') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kas Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idkas_keluar" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_tanggal" class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_kategori" class="form-label">Kategori</label>
                        <select class="form-control" name="kategori_idkategori" id="edit_kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_kas as $kat) : ?>
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
                        <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="edit_penerima" class="form-label">Penerima / Rekening</label>
                        <select class="form-control" name="penerima" id="edit_penerima">
                            <option value="">-- Choose --</option>
                            <?php foreach ($bank as $b) : ?>
                                <option value="<?= (int)$b->idbank ?>"><?= esc($b->nama_bank . ' ' . $b->atas_nama . ' : ' . $b->norek) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_posisi_drk" class="form-label">Posisi</label>
                        <select class="form-control" name="posisi_drk" id="edit_posisi_drk">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('delete_kas_keluar') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Kas Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="idkas_keluar">
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

        // ── DataTables server-side ─────────────────────────────────────
        const dt = $('#table_kas_keluar').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?= base_url('kas_keluar/datatables') ?>',
                type: 'GET',
                data: function(d) {
                    d.tanggal_awal = $('#startDate').val();
                    d.tanggal_akhir = $('#endDate').val();
                    d.unit_id = $('#unitSelect').val();
                }
            },
            order: [
                [0, 'desc']
            ],
            columns: [
                { data: 'tanggal' },
                { data: 'unit' },
                { data: 'no_akun' },
                { data: 'kategori' },
                { data: 'deskripsi' },
                { data: 'bank' },
                { data: 'penerima' },
                { data: 'norek' },
                { data: 'jumlah', className: 'text-end', render: $.fn.dataTable.render.number('.', ',', 0, 'Rp ') },
                { data: 'jenis', className: 'text-center' },
                { data: 'aksi', className: 'text-center', orderable: false, searchable: false }
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'Semua']
            ],
            drawCallback: function(settings) {
                // Total kolom jumlah pada halaman server-side via api
                const api = this.api();
                let total = 0;
                api.rows({ filter: 'applied' }).every(function() {
                    total += parseFloat(this.data().jumlah) || 0;
                });
                $('#sumJumlah').text('Rp ' + total.toLocaleString('id-ID'));
            }
        });

        $('#btnApply').on('click', function() {
            dt.ajax.reload();
        });
        $('#btnReset').on('click', function() {
            $('#startDate').val('');
            $('#endDate').val('');
            $('#unitSelect').val('');
            dt.ajax.reload();
        });

        // Export ikut filter aktif
        $('#exportForm').on('submit', function() {
            $('#expStart').val($('#startDate').val());
            $('#expEnd').val($('#endDate').val());
            $('#expUnit').val($('#unitSelect').val());
        });

        // ── Edit & Delete (event delegation) ──────────────────────────
        $('#table_kas_keluar').on('click', '.edit-button', function() {
            const btn = $(this);
            $('#edit_id').val(btn.data('id'));
            $('#edit_tanggal').val(btn.data('tanggal'));
            $('#edit_kategori').val(btn.data('kategori')).trigger('change');
            $('#edit_deskripsi').val(btn.data('deskripsi'));
            $('#edit_jumlah').val(btn.data('jumlah'));
            $('#edit_penerima').val(btn.data('idbank')).trigger('change');
            $('#edit_posisi_drk').val(btn.data('jenis') || 'debet');
        });
        $('#table_kas_keluar').on('click', '.delete-button', function() {
            $('#delete_id').val($(this).data('id'));
        });

        // ── Modal input (redesign) ────────────────────────────────────
        const akunOptions = `
            <option value="">-- Pilih No Akun --</option>
            <?php foreach ($no_akun as $a) : ?>
                <option value="<?= esc($a->no_akun) ?>"><?= esc($a->no_akun) ?> &mdash; <?= esc($a->nama_akun) ?></option>
            <?php endforeach; ?>
        `;
        const katOptions = `
            <option value="">-- Pilih Kategori --</option>
            <?php foreach ($kategori_kas as $kat) : ?>
                <option value="<?= esc($kat->idkategori_kas) ?>"><?= esc($kat->kategori) ?></option>
            <?php endforeach; ?>
        `;
        const bankOptions = `
            <option value="">-- Pilih No Rekening --</option>
            <?php foreach ($bank as $b) : ?>
                <option value="<?= (int)$b->idbank ?>"><?= esc($b->nama_bank . ' ' . $b->atas_nama . ' : ' . $b->norek) ?></option>
            <?php endforeach; ?>
        `;

        let akunIndex = 0;

        function formatRupiah(value) {
            const angka = value.replace(/[^0-9]/g, '');
            return angka ? Number(angka).toLocaleString('id-ID') : '';
        }

        function addAkunRow() {
            const container = document.getElementById('akun-terpilih-container');
            const row = document.createElement('tr');
            row.className = 'akun-row';
            row.innerHTML = `
                <td><select class="form-control form-select akun-no" name="akun[${akunIndex}][no_akun]" required>${akunOptions}</select></td>
                <td><select class="form-control akun-kategori" name="akun[${akunIndex}][kategori_idkategori]" required>${katOptions}</select></td>
                <td><select class="form-control akun-jenis-transaksi" name="akun[${akunIndex}][jenis_transaksi]">
                        <option value="cash" selected>Kas</option>
                        <option value="bank">Bank</option>
                    </select></td>
                <td><select class="form-control akun-rekening" name="akun[${akunIndex}][no_rekening]" disabled>${bankOptions}</select></td>
                <td><input type="text" class="form-control akun-penerima" name="akun[${akunIndex}][penerima]" placeholder="Nama penerima"></td>
                <td><select class="form-control akun-posisi" name="akun[${akunIndex}][posisi_drk]">
                        <option value="debet" selected>Debet</option>
                        <option value="kredit">Kredit</option>
                    </select></td>
                <td><input type="text" class="form-control text-end akun-jumlah" name="akun[${akunIndex}][jumlah]" placeholder="Rp 0" inputmode="numeric"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger akun-hapus" title="Hapus"><i class="bi bi-trash"></i></button>
                </td>
            `;

            $(row).find('.akun-rekening').select2({
                dropdownParent: $('#input-kas-modal'),
                width: '100%',
                placeholder: '-- Pilih No Rekening --'
            });

            $(row).find('.akun-no').select2({
                dropdownParent: $('#input-kas-modal'),
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Cari akun (mis. Piutang)',
                allowClear: true
            });

            const gl = row.querySelector('.akun-jenis-transaksi');
            const rek = row.querySelector('.akun-rekening');
            gl.addEventListener('change', function() {
                if (this.value === 'bank') {
                    rek.disabled = false;
                } else {
                    rek.disabled = true;
                    $(rek).val('').trigger('change');
                }
            });

            row.querySelector('.akun-jumlah').addEventListener('input', function() {
                this.value = formatRupiah(this.value);
                updateRowTotals();
            });

            row.querySelector('.akun-hapus').addEventListener('click', function() {
                row.remove();
                updateRowTotals();
            });

            container.appendChild(row);
            akunIndex++;
            updateRowTotals();
        }

        function updateRowTotals() {
            let total = 0;
            document.querySelectorAll('#akun-terpilih-container .akun-jumlah').forEach(function(el) {
                total += parseInt(el.value.replace(/[^0-9]/g, '') || 0, 10);
            });
            document.getElementById('sumRowJumlah').textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        document.getElementById('btnTambahAkun').addEventListener('click', addAkunRow);

        $('#input-kas-modal').on('shown.bs.modal', function() {
            document.getElementById('akun-terpilih-container').innerHTML = '';
            akunIndex = 0;
            const today = new Date();
            document.getElementById('tanggal').value =
                today.getFullYear() + '-' +
                String(today.getMonth() + 1).padStart(2, '0') + '-' +
                String(today.getDate()).padStart(2, '0');
            addAkunRow();
            document.getElementById('tanggal').focus();
        });

        // Bersihkan format Rp sebelum submit (nilai murni angka)
        document.getElementById('form_kas_keluar').addEventListener('submit', function() {
            // Re-enable unit yang dikunci agar nilainya ikut terkirim.
            const u = document.getElementById('unit_idunit');
            if (u.disabled) u.disabled = false;
            document.querySelectorAll('.akun-jumlah').forEach(function(el) {
                el.value = el.value.replace(/[^0-9]/g, '') || '0';
            });
        });

        // Default input unit dari akun yang login.
        // Admin root (1), Direktur (2), Manager (34), Admin Center (0) boleh pilih
        // semua unit; selain itu unit terkunci mengikuti unit akun yang login.
        const akunUnit = <?= (int)($akun->ID_UNIT ?? 0) ?>;
        const akunRole = <?= (int)($akun->ID_JABATAN ?? 0) ?>;
        const canPickUnit = [0, 1, 2, 34].includes(akunRole);

        if (akunUnit > 0) {
            $('#unit_idunit').val(akunUnit).trigger('change');
        }
        if (!canPickUnit && akunUnit > 0) {
            $('#unit_idunit').prop('disabled', true);
            $('#unit_idunit').closest('.col-md-4').find('label').append(' <span class="text-muted small">(unit otomatis)</span>');
        }

    });
</script>