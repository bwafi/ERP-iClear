<?= $this->include('stok/_mutasi_theme') ?>

<?php
$stokUnit    = $stok_unit ?? [];
$suplierUnit = $suplier_unit ?? [];
$unitAktif   = (int) session('ID_UNIT');
$namaUnit    = trim((string) session('NAMA_UNIT'));
$isAdmin     = $unitAktif === 1;
$today       = date('Y-m-d');

$jabatan  = (int) session('ID_JABATAN');
// Hanya Admin Root (jabatan 1) yang boleh memilih unit pengirim lain.
$bolehPilihUnit = $jabatan === 1;

// Unit cabang hanya boleh mengirim dari unitnya sendiri (validasi server
// memaksa hal yang sama), jadi pilihannya dipersempit: menampilkan semua unit
// hanya akan membuat stok terlihat 0 untuk unit yang tidak ia punya.
$pilihanKirim = [];
foreach ($unit as $b) {
    if ($bolehPilihUnit || (int) $b->idunit === $unitAktif) {
        $pilihanKirim[] = $b;
    }
}
if ($pilihanKirim === []) {
    $pilihanKirim[] = (object) ['idunit' => $unitAktif, 'NAMA_UNIT' => 'Unit #' . $unitAktif];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_FORCE_OBJECT;
$jsonStok = json_encode(empty($stokUnit) ? new stdClass() : $stokUnit, $jsonFlags) ?: '{}';
$jsonSuplier = json_encode(empty($suplierUnit) ? new stdClass() : $suplierUnit, $jsonFlags) ?: '{}';
?>

<div class="mutasi-stok">

    <header class="ms-head">
        <div>
            <nav class="ms-crumb" aria-label="breadcrumb">
                <a href="<?= base_url('/') ?>">Stok</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Mutasi Stok</span>
            </nav>
            <h1>Mutasi Stok</h1>
            <p>Catat perpindahan barang antar unit. Stok dan suplier mengikuti unit pengirim yang dipilih.</p>
        </div>

        <div class="ms-context">
            <iconify-icon icon="solar:buildings-2-line-duotone" aria-hidden="true"></iconify-icon>
            <span>
                <small>Unit aktif</small>
                <strong><?= esc($namaUnit !== '' ? $namaUnit : 'Unit #' . $unitAktif) ?></strong>
            </span>
        </div>
    </header>

    <form id="formMutasi" action="<?= base_url('insert_mutasi') ?>" method="post">
        <section class="ms-panel" aria-labelledby="ms-route-title">
            <div class="ms-panel__head">
                <div class="ms-panel__title">
                    <span class="ms-icon" aria-hidden="true">
                        <iconify-icon icon="solar:routing-2-line-duotone"></iconify-icon>
                    </span>
                    <div>
                        <h2 id="ms-route-title">Rute pengiriman</h2>
                        <p>Tentukan unit asal dan tujuan sebelum menyusun barang.</p>
                    </div>
                </div>
            </div>

            <div class="ms-route">
                <div>
                    <label class="ms-label" for="unit1">Unit pengirim</label>
                    <select class="form-select ms-control" id="unit1" name="unit1" required>
                        <?php if ($bolehPilihUnit) : ?>
                            <option value="" selected>Pilih unit asal</option>
                        <?php endif; ?>
                        <?php foreach ($pilihanKirim as $b) : ?>
                            <option value="<?= (int) $b->idunit ?>"
                                <?= ! $bolehPilihUnit || count($pilihanKirim) === 1 ? 'selected' : '' ?>>
                                <?= esc($b->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="id_unit1_text" id="id_unit1_text">
                </div>

                <div class="ms-route__arrow" aria-hidden="true">
                    <iconify-icon icon="solar:arrow-right-line-duotone"></iconify-icon>
                </div>

                <div>
                    <label class="ms-label" for="unit2">Unit penerima</label>
                    <select class="form-select ms-control" id="unit2" name="unit2" required>
                        <option value="" selected>Pilih unit tujuan</option>
                        <?php foreach ($unit as $b) : ?>
                            <option value="<?= (int) $b->idunit ?>"><?= esc($b->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="id_unit2_text" id="id_unit2_text">
                </div>
            </div>

            <?php if (! $bolehPilihUnit) : ?>
                <p class="ms-lock-note ms-route__note">
                    <iconify-icon icon="solar:lock-keyhole-minimalistic-line-duotone" aria-hidden="true"></iconify-icon>
                    Anda hanya bisa mengirim dari unit Anda.
                </p>
            <?php endif; ?>

            <div class="ms-dates">
                <div>
                    <label class="ms-label" for="tanggal_kirim">Tanggal kirim</label>
                    <input type="date" class="form-control ms-control" id="tanggal_kirim" name="tanggal_kirim"
                        value="<?= $today ?>" <?= $isAdmin ? '' : 'readonly' ?> required>
                    <small class="ms-help">
                        Barang keluar dari unit pengirim. Tanggal terima dicatat otomatis saat unit penerima
                        mengonfirmasi di Mutasi Masuk.
                    </small>
                </div>

                <?php if (! $isAdmin) : ?>
                    <p class="ms-lock-note">
                        <iconify-icon icon="solar:lock-keyhole-minimalistic-line-duotone" aria-hidden="true"></iconify-icon>
                        Tanggal dikunci untuk unit Anda.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="ms-panel" aria-labelledby="ms-items-title">
            <div class="ms-panel__head">
                <div class="ms-panel__title">
                    <span class="ms-icon" aria-hidden="true">
                        <iconify-icon icon="solar:box-minimalistic-line-duotone"></iconify-icon>
                    </span>
                    <div>
                        <h2 id="ms-items-title">Barang yang dikirim</h2>
                        <p>Pilih produk, lalu isi jumlah kirim. Harga mutasi terisi dari harga beli dan bisa diubah.</p>
                    </div>
                </div>

                <button type="button" class="ms-btn ms-btn--primary d-none" id="ms-open-picker"
                    data-bs-toggle="modal" data-bs-target="#pilih-produk-modal">
                    <iconify-icon icon="solar:add-circle-line-duotone" aria-hidden="true"></iconify-icon>
                    Pilih produk
                </button>
            </div>

            <div class="ms-empty" id="ms-empty">
                <span class="ms-empty__icon" aria-hidden="true">
                    <iconify-icon icon="solar:box-minimalistic-line-duotone"></iconify-icon>
                </span>
                <h3>Belum ada barang</h3>
                <p>Tambahkan produk dari stok unit pengirim untuk mulai menyusun mutasi ini.</p>
                <button type="button" class="ms-btn ms-btn--primary" data-bs-toggle="modal"
                    data-bs-target="#pilih-produk-modal">
                    <iconify-icon icon="solar:add-circle-line-duotone" aria-hidden="true"></iconify-icon>
                    Pilih produk
                </button>
            </div>

            <div class="ms-table-wrap d-none" id="ms-table-wrap">
                <table class="ms-table ms-table--items">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="ms-money">Harga beli</th>
                            <th class="ms-money">Harga jual</th>
                            <th>Harga mutasi</th>
                            <th>Jumlah kirim</th>
                            <th class="ms-money">Total</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="selected-produk-table">
                        <!-- Baris ditambahkan dari modal Pilih Produk -->
                    </tbody>
                </table>
            </div>

            <div class="ms-summary d-none" id="ms-summary" aria-live="polite">
                <p class="ms-summary__count mb-0">
                    <strong id="ms-item-count">0</strong> produk ·
                    <strong id="ms-qty-total">0</strong> unit dikirim
                </p>
                <button type="submit" class="ms-btn ms-btn--primary ms-btn--lg">
                    <iconify-icon icon="solar:diskette-line-duotone" aria-hidden="true"></iconify-icon>
                    Simpan mutasi
                </button>
            </div>
        </section>

        <!-- Modal Pilih Produk -->
        <div class="modal fade" id="pilih-produk-modal" tabindex="-1" aria-labelledby="produkModalLabel"
            aria-describedby="produkModalDesc" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content ms-modal">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="produkModalLabel">Pilih produk</h2>
                            <p id="produkModalDesc">Stok dan suplier mengikuti unit pengirim. Hanya produk dengan stok tersedia yang ditampilkan.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="ms-modal__toolbar">
                        <label class="ms-search">
                            <span class="visually-hidden">Cari produk</span>
                            <iconify-icon icon="solar:magnifer-line-duotone" aria-hidden="true"></iconify-icon>
                            <input type="search" id="produk-search" placeholder="Cari kode, nama, atau suplier…"
                                autocomplete="off">
                        </label>
                        <span class="ms-pick-count"><strong id="ms-pick-count">0</strong> dipilih</span>
                    </div>

                    <div class="modal-body">
                        <div class="ms-table-wrap">
                            <table class="ms-table ms-table--picker" id="produk-modal-table">
                                <thead>
                                    <tr>
                                        <th class="ms-check">
                                            <input type="checkbox" class="form-check-input" id="select-all-produk"
                                                aria-label="Pilih semua produk yang tampil">
                                        </th>
                                        <th>Barang</th>
                                        <th>Stok</th>
                                        <th>Suplier</th>
                                        <th class="ms-money">Harga beli</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Diisi DataTables server-side dari /mutasi_stok/produk -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <span class="ms-modal__foot-note">Harga mutasi diisi setelah produk ditambahkan.</span>
                        <button type="button" class="ms-btn ms-btn--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="ms-btn ms-btn--primary" id="confirm-produk-btn" disabled>
                            Tambahkan <span id="ms-pick-count-btn">0</span> produk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const stokUnit    = <?= $jsonStok ?>;
    const suplierUnit = <?= $jsonSuplier ?>;
    const unitAktif   = <?= $unitAktif ?>;

    const selected    = document.getElementById('selected-produk-table');
    const emptyState  = document.getElementById('ms-empty');
    const tableWrap   = document.getElementById('ms-table-wrap');
    const summary     = document.getElementById('ms-summary');
    const openPicker  = document.getElementById('ms-open-picker');
    const itemCount   = document.getElementById('ms-item-count');
    const qtyTotal    = document.getElementById('ms-qty-total');
    const unit1       = document.getElementById('unit1');
    const unit2       = document.getElementById('unit2');
    const pickCount   = document.getElementById('ms-pick-count');
    const pickCountBtn = document.getElementById('ms-pick-count-btn');
    const confirmBtn  = document.getElementById('confirm-produk-btn');
    const selectAll   = document.getElementById('select-all-produk');
    const searchInput = document.getElementById('produk-search');
    const modalEl     = document.getElementById('pilih-produk-modal');
    const modal       = bootstrap.Modal.getOrCreateInstance(modalEl);

    const angka = (n) => Number(n || 0).toLocaleString('id-ID');

    function formatToRupiah(value) {
        const number = parseInt(String(value).replace(/[^\d]/g, ''), 10) || 0;
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    // Pilihan disimpan di Map supaya tidak hilang saat pindah halaman/search
    // (DataTables server-side hanya menyimpan baris halaman aktif di DOM).
    const picked = new Map();

    const pickerTable = $('#produk-modal-table').DataTable({
        serverSide: true,
        processing: true,
        pageLength: 10,
        lengthChange: false,
        order: [[1, 'asc']],
        dom: 'rt<"ms-dt-foot"ip>',
        columnDefs: [{ orderable: false, targets: [0, 5] }],
        ajax: {
            url: '<?= base_url('mutasi_stok/produk') ?>',
            data: function (d) {
                d.unit = unitDipilih();
            }
        },
        columns: [
            { data: 0 }, { data: 1 }, { data: 2 },
            { data: 3 }, { data: 4 }, { data: 5 }
        ],
        drawCallback: function () {
            $('#produk-modal-table tbody tr').each(function () {
                const cb = this.querySelector('.produk-checkbox');
                if (cb && picked.has(parseInt(cb.dataset.id, 10))) {
                    cb.checked = true;
                    this.classList.add('is-selected');
                }
            });
            updatePickCount();
        },
        language: {
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ produk',
            infoEmpty: 'Tidak ada produk',
            infoFiltered: '',
            zeroRecords: 'Tidak ada produk dengan stok tersedia.',
            emptyTable: 'Belum ada produk.',
            paginate: { previous: '‹', next: '›' }
        }
    });

    function unitDipilih() {
        return String(unit1.value || unitAktif);
    }

    // Stok & suplier mengikuti unit pengirim, bukan unit yang login: admin
    // pusat bisa membuat mutasi dari unit lain dan tetap melihat angka benar.
    function segarkanStok(reloadPicker) {
        const unit = unitDipilih();
        const stokMap = stokUnit[unit] || {};
        const suplierMap = suplierUnit[unit] || {};

        // Produk yang dicentang tapi stoknya habis di unit baru dilepas;
        // yang masih ada ikut memakai stok/suplier unit baru.
        for (const [id, data] of Array.from(picked.entries())) {
            const stok = Number(stokMap[id] || 0);
            if (stok <= 0) {
                picked.delete(id);
            } else {
                data.stok = stok;
                data.suplier = suplierMap[id] || '';
            }
        }

        if (reloadPicker) {
            pickerTable.ajax.reload(null, false);
        }
        updatePickCount();

        document.querySelectorAll('#selected-produk-table tr').forEach(function (row) {
            const id = row.dataset.id;
            const stok = Number(stokMap[id] || 0);
            const suplier = suplierMap[id] || '';
            const note = row.querySelector('.js-stock-note');
            const meta = row.querySelector('.js-item-stock');
            if (note) {
                note.textContent = 'Stok ' + angka(stok);
            }
            if (meta) {
                meta.innerHTML = 'Stok unit pengirim: <strong>' + angka(stok) + '</strong>' +
                    (suplier ? ' · ' + escapeHtml(suplier) : '');
                meta.classList.toggle('is-empty-stock', stok <= 0);
            }
        });
    }

    function updateSummary() {
        const rows = selected.querySelectorAll('tr');
        let qty = 0;

        rows.forEach(function (row) {
            const input = row.querySelector('input[name$="[jumlah_kirim]"]');
            qty += parseInt(input ? input.value : 0, 10) || 0;
        });

        itemCount.textContent = angka(rows.length);
        qtyTotal.textContent = angka(qty);

        const ada = rows.length > 0;
        emptyState.classList.toggle('d-none', ada);
        tableWrap.classList.toggle('d-none', !ada);
        summary.classList.toggle('d-none', !ada);
        openPicker.classList.toggle('d-none', !ada);
    }

    function updatePickCount() {
        const jumlah = picked.size;
        pickCount.textContent = angka(jumlah);
        pickCountBtn.textContent = angka(jumlah);
        confirmBtn.disabled = jumlah === 0;

        const tampil = pickerTable.rows({ page: 'current' }).nodes().toArray()
            .map(function (row) { return row.querySelector('.produk-checkbox'); })
            .filter(Boolean);
        const dipilih = tampil.filter(function (cb) {
            return picked.has(parseInt(cb.dataset.id, 10));
        }).length;
        selectAll.checked = tampil.length > 0 && dipilih === tampil.length;
        selectAll.indeterminate = dipilih > 0 && dipilih < tampil.length;
    }

    function dataProduk(cb) {
        return {
            id: parseInt(cb.dataset.id, 10) || 0,
            nama: cb.dataset.nama || '',
            kode: cb.dataset.kode || '',
            kategori: cb.dataset.kategori || '',
            imei: cb.dataset.imei || '',
            suplier: cb.dataset.suplier || '',
            stok: parseInt(cb.dataset.stok, 10) || 0,
            hargaBeli: parseInt(cb.dataset.harga_beli, 10) || 0,
            hargaJual: parseInt(cb.dataset.harga_jual, 10) || 0,
        };
    }

    function tambahBaris(data) {
        const id = data.id;
        if (!id || document.getElementById('produk-row-' + id)) {
            return;
        }

        const nama = data.nama;
        const kode = data.kode;
        const kategori = data.kategori;
        const imei = data.imei;
        const suplier = data.suplier;
        const stok = data.stok;
        const hargaBeli = data.hargaBeli;
        const hargaJual = data.hargaJual;

        const row = document.createElement('tr');
        row.id = 'produk-row-' + id;
        row.dataset.id = String(id);
        row.className = 'is-new';
        row.innerHTML = `
            <td data-label="Barang">
                <span class="ms-item-name">${escapeHtml(nama)}</span>
                <span class="ms-item-meta">${escapeHtml(kode)}${kategori ? ' · ' + escapeHtml(kategori) : ''}${imei ? ' · IMEI ' + escapeHtml(imei) : ''}</span>
                <span class="ms-item-meta js-item-stock${stok <= 0 ? ' is-empty-stock' : ''}">Stok unit pengirim: <strong>${angka(stok)}</strong>${suplier ? ' · ' + escapeHtml(suplier) : ''}</span>
                <input type="hidden" name="produk[${id}][id]" value="${id}">
                <input type="hidden" name="produk[${id}][kode]" value="${escapeHtml(kode)}">
                <input type="hidden" name="produk[${id}][nama]" value="${escapeHtml(nama)}">
                <input type="hidden" name="produk[${id}][kategori]" value="${escapeHtml(kategori)}">
                <input type="hidden" name="produk[${id}][imei]" value="${escapeHtml(imei)}">
            </td>
            <td data-label="Harga beli" class="ms-money">
                ${formatToRupiah(hargaBeli)}
                <input type="hidden" name="produk[${id}][harga_beli]" value="${hargaBeli}">
            </td>
            <td data-label="Harga jual" class="ms-money">
                ${formatToRupiah(hargaJual)}
                <input type="hidden" name="produk[${id}][harga_jual]" value="${hargaJual}">
            </td>
            <td data-label="Harga mutasi">
                <input required type="text" inputmode="numeric" placeholder="Rp 0"
                    class="form-control ms-control harga-mutasi" value="${formatToRupiah(hargaBeli)}"
                    title="Maks. ${formatToRupiah(hargaJual)}"
                    aria-label="Harga mutasi ${escapeHtml(nama)}, maksimal ${formatToRupiah(hargaJual)}">
                <input type="hidden" name="produk[${id}][harga_mutasi]" class="harga-mutasi-hidden" value="${hargaBeli}">
            </td>
            <td data-label="Jumlah kirim">
                <input required type="number" min="1" step="1" inputmode="numeric"
                    name="produk[${id}][jumlah_kirim]"
                    class="form-control ms-control jumlah-input"
                    aria-label="Jumlah kirim ${escapeHtml(nama)}">
                <small class="ms-help js-stock-note">Stok ${angka(stok)}</small>
            </td>
            <td data-label="Total" class="ms-money js-total-mutasi">${formatToRupiah(0)}</td>
            <td data-label="Aksi">
                <button type="button" class="ms-icon-btn" data-remove="${id}"
                    aria-label="Hapus ${escapeHtml(nama)}" title="Hapus">
                    <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="18" height="18"></iconify-icon>
                </button>
            </td>`;

        selected.appendChild(row);

        const hargaInput = row.querySelector('.harga-mutasi');
        const hargaHidden = row.querySelector('.harga-mutasi-hidden');
        const jumlahInput = row.querySelector('.jumlah-input');
        const totalCell = row.querySelector('.js-total-mutasi');

        function segarkanTotal() {
            const harga = parseInt(hargaHidden.value, 10) || 0;
            const qty = parseInt(jumlahInput.value, 10) || 0;
            totalCell.textContent = formatToRupiah(harga * qty);
        }

        hargaInput.addEventListener('input', function () {
            let numeric = parseInt(this.value.replace(/[^\d]/g, ''), 10) || 0;

            if (numeric > hargaJual) {
                numeric = hargaJual;
                this.classList.add('is-invalid');
                this.setAttribute('aria-invalid', 'true');
            } else {
                this.classList.remove('is-invalid');
                this.removeAttribute('aria-invalid');
            }

            hargaHidden.value = numeric;
            this.value = numeric ? formatToRupiah(numeric) : '';
            this.setSelectionRange(this.value.length, this.value.length);
            segarkanTotal();
        });

        jumlahInput.addEventListener('input', function () {
            segarkanTotal();
            updateSummary();
        });
    }

    unit1.addEventListener('change', function () {
        document.getElementById('id_unit1_text').value = this.value;
        segarkanStok(true);
    });

    // Produk baru hanya bisa dipilih setelah unit penerima diisi. Unit
    // pengirim sudah otomatis terisi sesuai unit user (atau pilihan Admin Root).
    function segarkanTombolProduk() {
        const siap = unit2.value !== '';
        document.querySelectorAll('[data-bs-target="#pilih-produk-modal"]').forEach(function (btn) {
            btn.disabled = !siap;
            btn.title = siap ? '' : 'Pilih unit penerima dulu.';
        });
    }

    unit2.addEventListener('change', function () {
        document.getElementById('id_unit2_text').value = this.value;
        segarkanTombolProduk();
    });

    searchInput.addEventListener('input', function () {
        pickerTable.search(this.value).draw();
    });

    // Enter di kolom cari tidak boleh mengirim form mutasi di belakang modal.
    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    selectAll.addEventListener('change', function () {
        const checked = this.checked;
        pickerTable.rows({ page: 'current' }).nodes().toArray().forEach(function (row) {
            const cb = row.querySelector('.produk-checkbox');
            if (!cb) {
                return;
            }
            cb.checked = checked;
            $(cb).trigger('change');
        });
        updatePickCount();
    });

    $('#produk-modal-table').on('change', '.produk-checkbox', function () {
        const id = parseInt(this.dataset.id, 10) || 0;
        if (id) {
            if (this.checked) {
                picked.set(id, dataProduk(this));
            } else {
                picked.delete(id);
            }
        }
        $(this).closest('tr').toggleClass('is-selected', this.checked);
        updatePickCount();
    });

    // Klik di mana pun pada baris = pilih/batalkan produk. Klik langsung pada
    // checkbox dilewati supaya tidak ter-toggle dua kali.
    $('#produk-modal-table tbody').on('click', 'tr', function (event) {
        if ($(event.target).is('.produk-checkbox')) {
            return;
        }
        const cb = this.querySelector('.produk-checkbox');
        if (!cb) {
            return;
        }
        cb.checked = !cb.checked;
        $(cb).trigger('change');
    });

    confirmBtn.addEventListener('click', function () {
        picked.forEach(function (data) {
            tambahBaris(data);
        });

        picked.clear();
        pickerTable.rows({ page: 'current' }).nodes().to$().find('.produk-checkbox')
            .prop('checked', false)
            .closest('tr').removeClass('is-selected');
        selectAll.checked = false;
        selectAll.indeterminate = false;
        pickerTable.search('').draw();
        searchInput.value = '';
        updatePickCount();
        updateSummary();
        modal.hide();
    });

    selected.addEventListener('click', function (event) {
        const tombol = event.target.closest('[data-remove]');
        if (!tombol) {
            return;
        }
        const row = tombol.closest('tr');
        if (row) {
            row.remove();
            updateSummary();
        }
    });

    modalEl.addEventListener('show.bs.modal', function () {
        searchInput.value = '';
        pickerTable.search('').draw();
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        pickerTable.columns.adjust();
        searchInput.focus();
    });

    document.getElementById('id_unit1_text').value = unit1.value;
    document.getElementById('id_unit2_text').value = unit2.value;
    segarkanTombolProduk();
    segarkanStok(false);
    updateSummary();
    updatePickCount();
});
</script>
