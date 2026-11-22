<?php
/**
 * Kas Masuk — ledger-first, DataTables server-side.
 *
 * Kontrak POST lama dipertahankan: insert memakai `akun[i][*]`, update memakai
 * `idkas_masuk` + field tunggal, delete memakai `idkas_masuk`.
 * Tema scoped ada di jurnal/_kas_masuk_theme.
 */
?>
<?= $this->include('jurnal/_kas_masuk_theme') ?>
<!-- tema: app/Views/jurnal/_kas_masuk_theme.php -->

<div class="km-page">
    <?php if (session('sukses')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc(session('sukses')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <?php if (session('gagal')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc(session('gagal')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <header class="km-masthead">
        <div>
            <nav aria-label="Remah roti">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a class="text-secondary text-decoration-none" href="<?= base_url('/') ?>">Jurnal</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kas Masuk</li>
                </ol>
            </nav>
            <h1>Kas Masuk</h1>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
            <div class="km-scope-readout" id="kmScope"></div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="kmOpenInput">
                <iconify-icon icon="solar:add-circle-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                <span>Input Kas Masuk</span>
            </button>
        </div>
    </header>

    <!-- ── Modal input ─────────────────────────────────────────────── -->
    <div class="modal fade km-modal" id="kmInputModal" tabindex="-1" aria-labelledby="kmInputTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="kmInputTitle">
                        Input Kas Masuk
                        <span class="km-modal-sub">Satu transaksi, satu kartu akun</span>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <form action="<?= base_url('insert_kas_masuk') ?>" method="post" id="kmFormInsert" novalidate>
                        <div class="km-tx-grid">
                            <div>
                                <label class="km-field-label" for="kmTanggal">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" id="kmTanggal" required>
                            </div>
                            <div>
                                <label class="km-field-label" for="kmUnit">Unit</label>
                                <select class="form-select" name="unit_idunit" id="kmUnit" required>
                                    <option value="">Pilih Unit</option>
                                    <?php foreach ($unit_filter as $u): ?>
                                        <option value="<?= (int) $u->idunit ?>" <?= (int) $u->idunit === (int) $default_filter_unit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="is-full">
                                <label class="km-field-label" for="kmDeskripsi">Deskripsi</label>
                                <input type="text" class="form-control" name="deskripsi" id="kmDeskripsi"
                                    placeholder="contoh: Setoran penjualan tunai" maxlength="255" required>
                            </div>
                        </div>

                        <div class="km-subhead">
                            <span class="km-field-label" id="kmAkunLabel">Rincian akun</span>
                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-3 py-2" id="kmAddAkun">
                                <iconify-icon icon="solar:add-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
                                <span>Tambah akun</span>
                            </button>
                        </div>

                        <div id="kmAkunBody" aria-labelledby="kmAkunLabel"></div>

                        <p class="km-note">
                            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                            <span>Pilih <b>Kas</b> untuk uang tunai, atau <b>Bank</b> lalu pilih rekening. Jumlah hanya angka; format ribuan ditambahkan otomatis.</span>
                        </p>

                        <div class="km-modal-foot">
                            <div class="km-total-line">
                                <span>Total draf</span>
                                <b class="km-num" id="kmDraftTotal">Rp 0</b>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan Kas Masuk</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal edit ──────────────────────────────────────────────── -->
    <div class="modal fade km-modal" id="kmEditModal" tabindex="-1" aria-labelledby="kmEditTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form action="<?= base_url('update_kas_masuk') ?>" method="post" id="kmFormEdit">
                    <div class="modal-header">
                        <h2 class="modal-title" id="kmEditTitle">Edit Kas Masuk</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="idkas_masuk" id="kmEditId">
                        <input type="hidden" name="idunit" id="kmEditUnit">
                        <div class="km-tx-grid">
                            <div>
                                <label class="km-field-label" for="kmEditTanggal">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" id="kmEditTanggal" required>
                            </div>
                            <div>
                                <label class="km-field-label" for="kmEditKategori">Kategori</label>
                                <select class="form-select" name="kategori_idkategori" id="kmEditKategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori_kas as $kat): ?>
                                        <option value="<?= (int) $kat->idkategori_kas ?>"><?= esc($kat->kategori) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="is-full">
                                <label class="km-field-label" for="kmEditDeskripsi">Deskripsi</label>
                                <input type="text" class="form-control" name="deskripsi" id="kmEditDeskripsi" maxlength="255" required>
                            </div>
                            <div class="is-full">
                                <label class="km-field-label" for="kmEditRekening">Rekening</label>
                                <select class="form-select" name="penerima" id="kmEditRekening">
                                    <option value="">— Tunai / tanpa rekening —</option>
                                </select>
                            </div>
                            <div class="is-full">
                                <label class="km-field-label" for="kmEditJumlah">Jumlah</label>
                                <input type="text" class="form-control text-end km-num" name="jumlah" id="kmEditJumlah" inputmode="numeric" required>
                            </div>
                        </div>
                        <p class="km-note">
                            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                            <span>Mode edit mengubah tanggal, kategori, deskripsi, jumlah, dan rekening. Unit dan No. Akun tetap seperti saat transaksi dibuat.</span>
                        </p>
                    </div>
                    <div class="modal-footer km-modal-foot">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modal hapus ─────────────────────────────────────────────── -->
    <div class="modal fade km-modal" id="kmDeleteModal" tabindex="-1" aria-labelledby="kmDeleteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form action="<?= base_url('delete_kas_masuk') ?>" method="post" id="kmDeleteForm">
                    <div class="modal-header">
                        <h2 class="modal-title" id="kmDeleteTitle">Hapus Kas Masuk</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="idkas_masuk" id="kmDeleteId">
                        <div class="km-del-body" id="kmDeleteBody"></div>
                    </div>
                    <div class="modal-footer km-modal-foot">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Ledger ─────────────────────────────────────────────────── -->
    <section class="km-deck" aria-labelledby="kmLedgerTitle">
        <h2 class="visually-hidden" id="kmLedgerTitle">Daftar kas masuk</h2>

        <div class="km-cmdbar">
            <div class="km-search" id="kmSearchWrap" data-filled="false">
                <label class="visually-hidden" for="kmSearch">Cari kas masuk</label>
                <iconify-icon class="km-search-icon" icon="solar:magnifier-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                <input type="search" class="form-control" id="kmSearch"
                    placeholder="Cari ID, deskripsi, kategori, penerima, No. Akun…"
                    autocomplete="off" spellcheck="false">
                <button type="button" class="km-clear" id="kmSearchClear" aria-label="Bersihkan pencarian">
                    <iconify-icon icon="solar:close-circle-linear" width="18" height="18"></iconify-icon>
                </button>
            </div>

            <div>
                <label class="km-field-label" for="kmStart">Dari</label>
                <input type="date" class="form-control" id="kmStart">
            </div>
            <div>
                <label class="km-field-label" for="kmEnd">Sampai</label>
                <input type="date" class="form-control" id="kmEnd">
            </div>
            <div>
                <label class="km-field-label" for="kmUnitFilter">Unit</label>
                <select class="form-select" id="kmUnitFilter" <?= $can_filter_unit ? '' : 'disabled' ?>>
                    <?php if ($show_all_units): ?>
                        <option value="" <?= (int) $default_filter_unit === 0 ? 'selected' : '' ?>>Semua unit</option>
                    <?php endif; ?>
                    <?php foreach ($unit_filter as $u): ?>
                        <option value="<?= (int) $u->idunit ?>" <?= (int) $u->idunit === (int) $default_filter_unit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="km-field-label" for="kmKategoriFilter">Kategori</label>
                <select class="form-select" id="kmKategoriFilter">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategori_kas as $kat): ?>
                        <option value="<?= (int) $kat->idkategori_kas ?>"><?= esc($kat->kategori) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="km-cmdbar-actions">
                <form action="<?= base_url('export_kas_masuk') ?>" method="post" id="kmExportForm">
                    <input type="hidden" name="tanggal_awal" id="kmExpStart">
                    <input type="hidden" name="tanggal_akhir" id="kmExpEnd">
                    <input type="hidden" name="nama_unit" id="kmExpUnit">
                    <button type="submit" class="btn btn-outline-secondary" title="Export memakai filter tanggal dan unit yang aktif">
                        <iconify-icon icon="solar:export-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                        <span>Export</span>
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" id="kmResetFilter" hidden>Reset</button>
            </div>
        </div>

        <div class="km-sum">
            <div class="km-sum-cell">
                <span>Total terfilter</span>
                <b class="km-num" id="kmSumTotal">Rp 0</b>
                <small id="kmSumCount">0 transaksi</small>
            </div>
            <div class="km-sum-cell">
                <span>Transaksi</span>
                <b class="km-num" id="kmSumCountBig">0</b>
                <small>baris pada filter</small>
            </div>
            <div class="km-sum-cell">
                <span>Tunai</span>
                <b class="km-num" id="kmSumTunai">Rp 0</b>
                <small>tanpa rekening</small>
            </div>
            <div class="km-sum-cell">
                <span>Transfer</span>
                <b class="km-num" id="kmSumTransfer">Rp 0</b>
                <small>dengan rekening</small>
            </div>
        </div>

        <div class="km-tablewrap" id="kmTableWrap">
            <table class="table km-table" id="kmTable" style="width: 100%">
                <thead>
                    <tr>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Unit</th>
                        <th scope="col">Akun</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col">Rekening</th>
                        <th scope="col">Penerima</th>
                        <th scope="col" class="is-amount">Jumlah</th>
                        <?php if ($can_act): ?>
                            <th scope="col"><span class="visually-hidden">Aksi</span></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="km-empty" id="kmEmpty">
            <span class="km-tile" aria-hidden="true">
                <iconify-icon icon="solar:inbox-line-linear" width="22" height="22"></iconify-icon>
            </span>
            <h3 id="kmEmptyTitle">Belum ada kas masuk yang cocok</h3>
            <p id="kmEmptyText">Coba longgarkan filter tanggal, atau pakai ID untuk melompat ke satu transaksi tertentu.</p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button type="button" class="btn btn-primary mt-0" id="kmEmptyInput">Input Kas Masuk</button>
                <button type="button" class="btn btn-outline-secondary mt-0" id="kmEmptyReset">Bersihkan filter</button>
            </div>
        </div>

        <div class="km-pager">
            <label class="visually-hidden" for="kmLength">Baris per halaman</label>
            <select class="form-select form-select-sm" id="kmLength">
                <option value="10">10 per halaman</option>
                <option value="25" selected>25 per halaman</option>
                <option value="50">50 per halaman</option>
                <option value="100">100 per halaman</option>
                <option value="-1">Semua</option>
            </select>
            <div class="dataTables_info" id="kmInfo" role="status" aria-live="polite"></div>
            <nav aria-label="Navigasi halaman" class="ms-auto">
                <ul class="pagination pagination-sm" id="kmPager"></ul>
            </nav>
        </div>
    </section>
</div>

<script>
window.KM_BANKS = <?= json_encode($bank_per_unit, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.KM_KATEGORI = <?= json_encode(array_map(static function ($k) {
    return ['id' => (int) $k->idkategori_kas, 'nama' => (string) $k->kategori];
}, $kategori_kas), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.KM_CAN_ACT = <?= $can_act ? 'true' : 'false' ?>;

(function () {
    'use strict';

    function byId(id) { return document.getElementById(id); }

    function rupiah(n) {
        var v = Math.round(Number(n) || 0);
        return 'Rp ' + v.toLocaleString('id-ID');
    }

    function tanggalPendek(s) {
        if (!s) return '—';
        var p = String(s).slice(0, 10).split('-');
        return p.length === 3 ? (p[2] + '-' + p[1] + '-' + p[0]) : String(s);
    }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = String(text);
        return n;
    }

    function markup(node) {
        if (!node) return '';
        if (node.nodeType === 11) {
            var box = document.createElement('div');
            box.appendChild(node);
            return box.innerHTML;
        }
        return node.outerHTML;
    }

    /* ── Input: kartu akun ────────────────────────────────────────── */
    var akunIndex = 0;
    var akunBody = byId('kmAkunBody');

    // Opsi dirender sekali dari daftar yang sudah difilter server
    // (`ModelNoAkun::getAkunMasuk`), jadi akun terlarang tidak pernah muncul.
    var AKUN_OPTIONS = '<option value="">-- Pilih No. Akun --</option><?php foreach ($no_akun as $a): ?><option value="<?= esc($a->no_akun) ?>" data-jenis="<?= esc($a->jenis_akun) ?>"><?= esc($a->no_akun) ?> &mdash; <?= esc($a->nama_akun) ?></option><?php endforeach; ?>';
    var KATEGORI_OPTIONS = '<option value="">Pilih Kategori</option><?php foreach ($kategori_kas as $kat): ?><option value="<?= (int) $kat->idkategori_kas ?>"><?= esc($kat->kategori) ?></option><?php endforeach; ?>';

    function bankOptions(unitId, selected) {
        var list = window.KM_BANKS[unitId] || [];
        var html = '<option value="">Pilih Rekening</option>';
        list.forEach(function (b) {
            var label = (b.nama_bank || '') + ' ' + (b.atas_nama || '') + ' : ' + (b.norek || '');
            html += '<option value="' + String(b.idbank).replace(/"/g, '&quot;') + '"' +
                (String(b.idbank) === String(selected) ? ' selected' : '') + '>' +
                label.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
        });
        return html;
    }

    function setSelectInvalid(select, invalid) {
        select.classList.toggle('is-invalid', invalid);
        var container = jQuery(select).next('.select2-container');
        if (container.length) {
            container.find('.select2-selection--single').toggleClass('is-invalid', invalid);
        }
    }

    function updateDraftTotal() {
        var total = 0;
        Array.prototype.forEach.call(akunBody.querySelectorAll('.km-jumlah'), function (input) {
            total += parseInt(input.value.replace(/[^0-9]/g, '') || '0', 10);
        });
        byId('kmDraftTotal').textContent = rupiah(total);
    }

    function renumberAkun() {
        Array.prototype.forEach.call(akunBody.querySelectorAll('.km-tx'), function (card, i) {
            var no = card.querySelector('.km-tx-no');
            if (no) no.textContent = String(i + 1);
        });
    }

    function wireAkun(card) {
        var jenis = card.querySelector('.km-jenis');
        var rekening = card.querySelector('.km-rekening');
        var jumlah = card.querySelector('.km-jumlah');
        var akun = card.querySelector('.km-akun');
        var kategori = card.querySelector('.km-kategori');
        var jenisAkun = card.querySelector('.km-jenis-akun');

        function syncRekening() {
            var isBank = jenis.value === 'bank';
            rekening.disabled = !isBank;
            if (!isBank) {
                jQuery(rekening).val(null).trigger('change.select2');
            }
        }

        function syncJenisAkun() {
            var opt = akun.options[akun.selectedIndex];
            jenisAkun.value = opt ? (opt.dataset.jenis || '') : '';
            setSelectInvalid(akun, false);
        }

        jenis.addEventListener('change', syncRekening);
        akun.addEventListener('change', syncJenisAkun);
        kategori.addEventListener('change', function () {
            kategori.classList.remove('is-invalid');
        });
        syncRekening();
        syncJenisAkun();

        jumlah.addEventListener('input', function () {
            var angka = this.value.replace(/[^0-9]/g, '');
            this.value = angka ? 'Rp ' + Number(angka).toLocaleString('id-ID') : '';
            this.classList.remove('is-invalid');
            updateDraftTotal();
        });
    }

    function addAkun() {
        var i = akunIndex++;
        var card = document.createElement('div');
        card.className = 'km-tx';
        card.innerHTML =
            '<div class="km-tx-head">' +
            '<span>Akun <b class="km-tx-no"></b></span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger km-remove" aria-label="Hapus akun">' +
            '<iconify-icon icon="solar:trash-bin-minimalistic-linear" width="16" height="16" aria-hidden="true"></iconify-icon>' +
            '</button></div>' +
            '<div class="km-tx-grid">' +
            '<div class="is-full"><label class="km-field-label" for="kmAkun' + i + '">No. Akun</label>' +
            '<select class="form-select km-akun" id="kmAkun' + i + '" name="akun[' + i + '][no_akun]" required>' + AKUN_OPTIONS + '</select></div>' +
            '<div class="is-full"><label class="km-field-label" for="kmKategori' + i + '">Kategori</label>' +
            '<select class="form-select km-kategori" id="kmKategori' + i + '" name="akun[' + i + '][kategori_idkategori]" required>' + KATEGORI_OPTIONS + '</select></div>' +
            '<div><label class="km-field-label" for="kmJenis' + i + '">Sumber dana</label>' +
            '<select class="form-select km-jenis" id="kmJenis' + i + '" name="akun[' + i + '][jenis_transaksi]">' +
            '<option value="cash" selected>Kas</option><option value="bank">Bank</option></select></div>' +
            '<div><label class="km-field-label" for="kmRekening' + i + '">No. Rekening</label>' +
            '<select class="form-select km-rekening" id="kmRekening' + i + '" name="akun[' + i + '][no_rekening]" disabled>' + bankOptions(byId('kmUnit').value, '') + '</select></div>' +
            '<div><label class="km-field-label" for="kmPenerima' + i + '">Penerima</label>' +
            '<input type="text" class="form-control" id="kmPenerima' + i + '" name="akun[' + i + '][penerima]" placeholder="Nama penerima" maxlength="255"></div>' +
            '<div><label class="km-field-label" for="kmJenisAkun' + i + '">Jenis Akun</label>' +
            '<input type="text" class="form-control km-jenis-akun" id="kmJenisAkun' + i + '" name="akun[' + i + '][jenis_akun]" readonly placeholder="&mdash;"></div>' +
            '<div class="is-full"><label class="km-field-label" for="kmJumlah' + i + '">Jumlah</label>' +
            '<input type="text" class="form-control text-end km-num km-jumlah" id="kmJumlah' + i + '" name="akun[' + i + '][jumlah]" inputmode="numeric" placeholder="Rp 0" required></div>' +
            '</div>';

        akunBody.appendChild(card);

        jQuery(card).find('.km-akun').select2({
            dropdownParent: jQuery('#kmInputModal'),
            width: '100%',
            placeholder: 'Cari akun (mis. Pendapatan)',
            allowClear: true
        });
        jQuery(card).find('.km-rekening').select2({
            dropdownParent: jQuery('#kmInputModal'),
            width: '100%',
            placeholder: 'Pilih rekening',
            allowClear: true
        });

        card.querySelector('.km-remove').addEventListener('click', function () {
            jQuery(card).find('select').select2('destroy');
            card.remove();
            renumberAkun();
            updateDraftTotal();
        });

        wireAkun(card);
        renumberAkun();
        updateDraftTotal();
        jQuery(card).find('.km-akun').select2('open');

        return card;
    }

    byId('kmAddAkun').addEventListener('click', addAkun);

    byId('kmUnit').addEventListener('change', function () {
        var unitId = this.value;
        Array.prototype.forEach.call(akunBody.querySelectorAll('.km-rekening'), function (select) {
            var selected = select.value;
            jQuery(select).html(bankOptions(unitId, selected)).trigger('change.select2');
        });
    });

    byId('kmInputModal').addEventListener('shown.bs.modal', function () {
        if (akunBody.querySelectorAll('.km-tx').length === 0) addAkun();
    });

    byId('kmFormInsert').addEventListener('submit', function (e) {
        var cards = akunBody.querySelectorAll('.km-tx');
        if (cards.length === 0) {
            e.preventDefault();
            addAkun();
            return;
        }

        var valid = true;
        Array.prototype.forEach.call(cards, function (card) {
            var jumlah = card.querySelector('.km-jumlah');
            var kategori = card.querySelector('.km-kategori');
            var akun = card.querySelector('.km-akun');
            var angka = jumlah.value.replace(/[^0-9]/g, '');
            jumlah.value = angka;
            if (!angka || Number(angka) <= 0) {
                jumlah.classList.add('is-invalid');
                valid = false;
            } else {
                jumlah.classList.remove('is-invalid');
            }
            if (!kategori.value) {
                kategori.classList.add('is-invalid');
                valid = false;
            } else {
                kategori.classList.remove('is-invalid');
            }
            if (!akun.value) {
                setSelectInvalid(akun, true);
                valid = false;
            } else {
                setSelectInvalid(akun, false);
            }
        });

        if (!valid) {
            e.preventDefault();
            var first = akunBody.querySelector('.is-invalid');
            if (first) first.focus();
        }
    });

    byId('kmOpenInput').addEventListener('click', function () {
        bootstrap.Modal.getOrCreateInstance(byId('kmInputModal')).show();
    });

    byId('kmEmptyInput').addEventListener('click', function () {
        bootstrap.Modal.getOrCreateInstance(byId('kmInputModal')).show();
    });

    /* ── Filter & DataTables server-side ──────────────────────────── */
    var search = byId('kmSearch');
    var start = byId('kmStart');
    var end = byId('kmEnd');
    var unit = byId('kmUnitFilter');
    var kategori = byId('kmKategoriFilter');
    var resetBtn = byId('kmResetFilter');
    var tableWrap = byId('kmTableWrap');
    var empty = byId('kmEmpty');
    var lastPayload = {};

    function hasFilter() {
        return search.value.trim() !== '' || start.value !== '' || end.value !== '' ||
            (unit.value !== '' && !unit.disabled) || kategori.value !== '';
    }

    function syncReset() {
        resetBtn.hidden = !hasFilter();
        byId('kmSearchWrap').dataset.filled = search.value.trim() !== '' ? 'true' : 'false';
    }

    function rupiahNode(n) {
        return el('span', 'km-amount', rupiah(n));
    }

    function cellTanggal(row) {
        return el('span', 'km-num', tanggalPendek(row.tanggal));
    }

    function cellUnit(row) {
        return el('span', '', row.unit || '—');
    }

    function cellAkun(row) {
        var frag = document.createDocumentFragment();
        frag.appendChild(el('span', 'km-mono', row.no_akun || '—'));
        if (row.nama_akun) frag.appendChild(el('span', 'km-sub', row.nama_akun));
        return frag;
    }

    function cellKategori(row) {
        return el('span', '', row.kategori || '—');
    }

    function cellDeskripsi(row) {
        var span = el('span', 'km-clip', row.deskripsi || '—');
        span.title = row.deskripsi || '';
        return span;
    }

    function cellRekening(row) {
        if (!row.idbank && !row.nama_bank) return el('span', 'km-sub', 'Tunai');
        var frag = document.createDocumentFragment();
        frag.appendChild(el('span', '', row.nama_bank || row.idbank));
        if (row.norek) frag.appendChild(el('span', 'km-sub', row.norek));
        return frag;
    }

    function cellPenerima(row) {
        return el('span', '', row.penerima || '—');
    }

    function cellJumlah(row) {
        var frag = document.createDocumentFragment();
        frag.appendChild(rupiahNode(row.jumlah));
        if (row.jenis) frag.appendChild(el('span', 'km-tag mt-1', row.jenis));
        return frag;
    }

    function cellAksi(row) {
        var wrap = el('div', 'd-flex justify-content-end gap-1');

        var edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'km-rowbtn km-edit';
        edit.title = 'Edit transaksi #' + row.id;
        edit.setAttribute('aria-label', 'Edit transaksi #' + row.id);
        edit.dataset.id = String(row.id);
        edit.innerHTML = '<iconify-icon icon="solar:pen-2-linear" width="16" height="16"></iconify-icon>';

        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'km-rowbtn is-danger km-del';
        del.title = 'Hapus transaksi #' + row.id;
        del.setAttribute('aria-label', 'Hapus transaksi #' + row.id);
        del.dataset.id = String(row.id);
        del.innerHTML = '<iconify-icon icon="solar:trash-bin-minimalistic-linear" width="16" height="16"></iconify-icon>';

        wrap.appendChild(edit);
        wrap.appendChild(del);
        return wrap;
    }

    var COLS = [
        { key: 'tanggal', label: 'Tanggal', cls: 'km-num', render: cellTanggal },
        { key: 'unit', label: 'Unit', render: cellUnit },
        { key: 'no_akun', label: 'Akun', render: cellAkun },
        { key: 'kategori', label: 'Kategori', render: cellKategori },
        { key: 'deskripsi', label: 'Deskripsi', render: cellDeskripsi },
        { key: 'nama_bank', label: 'Rekening', render: cellRekening },
        { key: 'penerima', label: 'Penerima', render: cellPenerima },
        { key: 'jumlah', label: 'Jumlah', cls: 'is-amount', render: cellJumlah }
    ];
    if (window.KM_CAN_ACT) {
        COLS.push({ key: 'aksi', label: 'Aksi', orderable: false, render: cellAksi });
    }

    function renderSummary(json) {
        byId('kmSumTotal').textContent = rupiah(json.sumTotal || 0);
        byId('kmSumCount').textContent = (json.countFiltered || 0) + ' transaksi';
        byId('kmSumCountBig').textContent = String(json.countFiltered || 0);
        byId('kmSumTunai').textContent = rupiah(json.sumTunai || 0);
        byId('kmSumTransfer').textContent = rupiah(json.sumTransfer || 0);
        byId('kmScope').textContent = (json.recordsTotal || 0) + ' baris dalam scope unit Anda';
    }

    function infoText(api, json) {
        var pi = api.page.info();
        if (!json.recordsFiltered) return '';
        return 'Menampilkan ' + (pi.start + 1) + '–' + pi.end + ' dari ' + json.recordsFiltered + ' transaksi';
    }

    function renderPager(api) {
        var ul = byId('kmPager');
        ul.innerHTML = '';
        var info = api.page.info();
        if (info.pages <= 1) return;

        function item(label, page, opts) {
            var o = opts || {};
            var li = el('li', 'page-item' + (o.active ? ' active' : '') + (o.disabled ? ' disabled' : ''));
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = label;
            a.setAttribute('aria-label', o.label || ('Halaman ' + (page + 1)));
            if (o.disabled) {
                a.setAttribute('aria-disabled', 'true');
                a.tabIndex = -1;
            } else {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    api.page(page).draw(false);
                });
            }
            li.appendChild(a);
            return li;
        }

        var current = info.page + 1;
        ul.appendChild(item('&lsaquo;', current - 2, { disabled: current === 1, label: 'Halaman sebelumnya' }));

        var win = 2;
        var from = Math.max(0, current - 1 - win);
        var to = Math.min(info.pages, from + win * 2 + 1);
        from = Math.max(0, to - win * 2 - 1);

        if (from > 0) {
            ul.appendChild(item('1', 0));
            if (from > 1) ul.appendChild(item('…', -1, { disabled: true }));
        }
        for (var p = from; p < to; p++) {
            ul.appendChild(item(String(p + 1), p, { active: p === current - 1 }));
        }
        if (to < info.pages) {
            if (to < info.pages - 1) ul.appendChild(item('…', -1, { disabled: true }));
            ul.appendChild(item(String(info.pages), info.pages - 1));
        }

        ul.appendChild(item('&rsaquo;', current, { disabled: current === info.pages, label: 'Halaman berikutnya' }));
    }

    var dt = jQuery('#kmTable').DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        ajax: {
            url: '<?= base_url('kas_masuk/datatables') ?>',
            type: 'GET',
            data: function (d) {
                d.tanggal_awal = start.value;
                d.tanggal_akhir = end.value;
                d.unit_id = unit.disabled ? '' : unit.value;
                d.kategori_id = kategori.value;
            }
        },
        order: [[0, 'desc']],
        columns: COLS.map(function (c) {
            return {
                data: c.key,
                className: c.cls || '',
                orderable: c.orderable !== false,
                searchable: false,
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    return markup(c.render(row || {}));
                }
            };
        }),
        pageLength: 25,
        lengthChange: false,
        dom: 'rt',
        language: { emptyTable: '', zeroRecords: '' },
        drawCallback: function (settings) {
            var json = settings.json || {};
            var rows = json.data || [];
            lastPayload = json;

            var api = this.api();
            COLS.forEach(function (c, i) {
                var header = api.column(i).header();
                if (header) header.dataset.label = c.label;
                api.column(i).nodes().toArray().forEach(function (td) {
                    td.dataset.label = c.label;
                });
            });

            renderSummary(json);
            renderPager(api);
            syncReset();

            var kosong = rows.length === 0;
            tableWrap.hidden = kosong;
            empty.classList.toggle('is-shown', kosong);
            byId('kmPager').parentElement.hidden = kosong;
            byId('kmInfo').textContent = kosong ? '' : infoText(api, json);
        }
    });

    // Debounce pencarian agar tidak membanjiri server.
    var searchTimer = null;
    search.addEventListener('input', function () {
        syncReset();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { dt.ajax.reload(); }, 300);
    });

    [start, end, unit, kategori].forEach(function (input) {
        input.addEventListener('change', function () {
            syncReset();
            dt.ajax.reload();
        });
    });

    byId('kmLength').addEventListener('change', function () {
        var v = parseInt(this.value, 10);
        dt.page.len(v < 1 ? -1 : v).draw();
    });

    byId('kmSearchClear').addEventListener('click', function () {
        search.value = '';
        syncReset();
        dt.ajax.reload();
    });

    function resetFilters() {
        search.value = '';
        start.value = '';
        end.value = '';
        if (!unit.disabled) unit.value = '';
        kategori.value = '';
        syncReset();
        dt.ajax.reload();
    }

    resetBtn.addEventListener('click', resetFilters);
    byId('kmEmptyReset').addEventListener('click', resetFilters);

    /* ── Export ──────────────────────────────────────────────────── */
    byId('kmExportForm').addEventListener('submit', function () {
        byId('kmExpStart').value = start.value;
        byId('kmExpEnd').value = end.value;
        var opt = unit.options[unit.selectedIndex];
        byId('kmExpUnit').value = unit.value === '' ? '' : (opt ? opt.textContent.trim() : '');
    });

    /* ── Aksi baris: edit & hapus ────────────────────────────────── */
    jQuery('#kmTable tbody').on('click', '.km-edit', function () {
        var id = String(this.dataset.id);
        var row = (lastPayload.data || []).filter(function (r) { return String(r.id) === id; })[0];
        if (!row) return;

        byId('kmEditId').value = row.id;
        byId('kmEditUnit').value = row.idunit;
        byId('kmEditTanggal').value = String(row.tanggal || '').slice(0, 10);
        byId('kmEditKategori').value = row.kategori_id || '';
        byId('kmEditDeskripsi').value = row.deskripsi || '';
        byId('kmEditJumlah').value = rupiah(row.jumlah);
        byId('kmEditRekening').innerHTML = '<option value="">— Tunai / tanpa rekening —</option>' +
            bankOptions(row.idunit, row.idbank);

        bootstrap.Modal.getOrCreateInstance(byId('kmEditModal')).show();
    });

    jQuery('#kmTable tbody').on('click', '.km-del', function () {
        var id = String(this.dataset.id);
        var row = (lastPayload.data || []).filter(function (r) { return String(r.id) === id; })[0];
        if (!row) return;

        byId('kmDeleteId').value = row.id;
        var body = byId('kmDeleteBody');
        body.innerHTML = '';
        body.appendChild(el('span', '', 'Hapus kas masuk'));
        body.appendChild(el('b', 'km-mono', '#' + row.id));
        body.appendChild(el('span', '', tanggalPendek(row.tanggal) + (row.unit ? ' · ' + row.unit : '')));
        body.appendChild(el('b', 'km-num', rupiah(row.jumlah)));
        if (row.deskripsi) body.appendChild(el('span', 'km-clip', row.deskripsi));

        bootstrap.Modal.getOrCreateInstance(byId('kmDeleteModal')).show();
    });

    byId('kmFormEdit').addEventListener('submit', function () {
        var jumlah = byId('kmEditJumlah');
        jumlah.value = jumlah.value.replace(/[^0-9]/g, '');
    });

    /* ── Awal ────────────────────────────────────────────────────── */
    var today = new Date();
    var awal = new Date();
    awal.setDate(today.getDate() - 15);

    function toDateValue(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    start.value = toDateValue(awal);
    end.value = toDateValue(today);
    byId('kmTanggal').value = toDateValue(today);
    syncReset();
})();
</script>
