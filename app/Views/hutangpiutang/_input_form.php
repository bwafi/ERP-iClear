<?php
$inModal = $in_modal ?? false;
$action = $form_action ?? base_url('hutangpiutang/store');
$cancel = $cancel_url ?? base_url('hutangpiutang/piutang');
$old = static function ($key, $default = '') {
    return function_exists('old') ? old($key, $default) : $default;
};
$selectedSumber = $old('sumber_tipe', $default_sumber ?? '');
?>
<form method="post" action="<?= $action ?>" id="hp-input-form">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Kategori Transaksi</label>
            <select name="sumber_tipe" id="sumber_tipe" class="form-select" required>
                <?php foreach (($input_types ?? []) as $key => $t) : ?>
                    <option value="<?= $key ?>" data-jenis="<?= esc($t['jenis'] ?? '') ?>" <?= $selectedSumber === $key ? 'selected' : '' ?>><?= esc($t['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Unit</label>
            <select name="unit_id" class="form-select" required>
                <?php foreach (($units ?? []) as $u) : ?>
                    <option value="<?= (int) $u->idunit ?>" <?= ($old('unit_id', $unit_id ?? null) == $u->idunit) ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 d-none" id="wrap-jenis">
            <label class="form-label">Jenis</label>
            <select name="jenis" id="jenis" class="form-select">
                <option value="hutang">Hutang (kami berutang)</option>
                <option value="piutang">Piutang (mereka berutang)</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Tipe Pihak</label>
            <select name="pihak_tipe" id="pihak_tipe" class="form-select" required></select>
        </div>

        <div class="col-md-6" id="wrap-pihak">
            <label class="form-label" id="label-pihak">Pihak</label>
            <select name="pihak_id" id="pihak_id" class="form-select select2"></select>
        </div>
        <div class="col-md-6 d-none" id="wrap-nama">
            <label class="form-label">Nama Pihak</label>
            <input type="text" name="nama_pihak" id="nama_pihak" class="form-control" placeholder="Nama bebas (tanpa master)" value="<?= esc($old('nama_pihak')) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?= esc($old('tanggal', date('Y-m-d'))) ?>" required>
        </div>
        <div class="col-md-3" id="wrap-jatuh-tempo">
            <label class="form-label">Jatuh Tempo</label>
            <input type="date" name="jatuh_tempo" id="jatuh_tempo" class="form-control" value="<?= esc($old('jatuh_tempo')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Total</label>
            <input type="text" name="total" id="hp-total" class="form-control" value="<?= esc($old('total')) ?>" required>
        </div>
        <div class="col-12">
            <label class="form-label">Keterangan <span class="text-danger" id="ket-wajib">*</span></label>
            <textarea name="keterangan" class="form-control" rows="2"><?= esc($old('keterangan')) ?></textarea>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <?php if ($inModal) : ?>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <?php else : ?>
            <a href="<?= $cancel ?>" class="btn btn-light">Batal</a>
        <?php endif; ?>
    </div>
</form>

<script>
(function () {
    if (window.__hpInputFormInit) { return; }
    window.__hpInputFormInit = true;

    function init() {
        var DATA = {
            pelanggan: <?= json_encode(array_map(function ($p) { return ['id' => (int) $p->id_pelanggan, 'text' => $p->nama]; }, ($pelanggan ?? []))) ?>,
            pegawai: <?= json_encode(array_map(function ($p) { return ['id' => (int) $p->ID_AKUN, 'text' => $p->NAMA_AKUN]; }, ($pegawai ?? []))) ?>,
            suplier: <?= json_encode(array_map(function ($p) { return ['id' => (int) $p->id_suplier, 'text' => $p->nama_suplier]; }, ($suplier ?? []))) ?>,
            teknisi: <?= json_encode(array_map(function ($p) { return ['id' => (int) $p->ID_AKUN, 'text' => $p->NAMA_AKUN]; }, ($teknisi ?? []))) ?>
        };

        var hrd = {
            kasbon:             { jenis: 'piutang', pihak: ['pegawai'] },
            piutang_pelanggan:  { jenis: 'piutang', pihak: ['suplier', 'pelanggan', 'pegawai', 'lainnya'] },
            jasa_teknisi:       { jenis: 'hutang',  pihak: ['suplier', 'teknisi', 'lainnya'] },
            kelebihan_transfer: { jenis: 'piutang', pihak: ['suplier'] },
            retur_barang:       { jenis: 'piutang', pihak: ['suplier'] },
            manual:             { jenis: null,      pihak: null }
        };
        var LABEL = { suplier: 'Supplier', pelanggan: 'Customer', pegawai: 'Karyawan', teknisi: 'Teknisi', lainnya: 'Lainnya' };
        var INIT = {
            sumber: <?= json_encode($selectedSumber) ?>,
            jenis: <?= json_encode($old('jenis')) ?>,
            pihakTipe: <?= json_encode($old('pihak_tipe')) ?>,
            pihakId: <?= json_encode((string) $old('pihak_id')) ?>
        };

        var elSumber = document.getElementById('sumber_tipe');
        var elJenis = document.getElementById('jenis');
        var wrapJenis = document.getElementById('wrap-jenis');
        var elPihakTipe = document.getElementById('pihak_tipe');
        var elPihak = document.getElementById('pihak_id');
        var wrapPihak = document.getElementById('wrap-pihak');
        var wrapNama = document.getElementById('wrap-nama');
        var elNama = document.getElementById('nama_pihak');
        var labelPihak = document.getElementById('label-pihak');
        var ketWajib = document.getElementById('ket-wajib');
        var wrapJatuhTempo = document.getElementById('wrap-jatuh-tempo');
        var elJatuhTempo = document.getElementById('jatuh_tempo');

        function pihakOptions(jenis) {
            return jenis === 'hutang' ? ['suplier', 'teknisi', 'lainnya'] : ['suplier', 'pelanggan', 'pegawai', 'lainnya'];
        }

        function firstOfNextMonth() {
            var d = new Date();
            var m = d.getMonth() + 1;
            var y = d.getFullYear();
            if (m > 11) { m = 0; y += 1; }
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            return y + '-' + pad(m + 1) + '-01';
        }

        function select2Opts() {
            var opts = { width: '100%' };
            var modal = jQuery(elPihak).closest('.modal');
            if (modal.length) { opts.dropdownParent = modal; }
            return opts;
        }

        function fillPihakTipe(list, selected) {
            elPihakTipe.innerHTML = '';
            list.forEach(function (t) {
                var o = document.createElement('option');
                o.value = t; o.textContent = LABEL[t] || t;
                if (t === selected) { o.selected = true; }
                elPihakTipe.appendChild(o);
            });
        }

        function fillPihak(tipe) {
            if (window.jQuery && jQuery.fn.select2) { jQuery(elPihak).select2('destroy'); }
            elPihak.innerHTML = '<option value="">-- Pilih --</option>';
            (DATA[tipe] || []).forEach(function (item) {
                var o = document.createElement('option');
                o.value = item.id; o.textContent = item.text;
                elPihak.appendChild(o);
            });
            if (window.jQuery && jQuery.fn.select2) { jQuery(elPihak).select2(select2Opts()); }
        }

        function sync() {
            var src = elSumber.value;
            var rule = hrd[src] || hrd.manual;

            if (rule.jenis) {
                wrapJenis.classList.add('d-none');
                elJenis.value = rule.jenis;
                elJenis.disabled = true;
            } else {
                wrapJenis.classList.remove('d-none');
                elJenis.disabled = false;
            }
            var jenis = rule.jenis || elJenis.value;

            var list = rule.pihak || pihakOptions(jenis);
            var current = elPihakTipe.value;
            if (list.indexOf(current) === -1) { current = list[0]; }
            fillPihakTipe(list, current);
            syncPihak();

            var wajib = ['manual', 'jasa_teknisi', 'kelebihan_transfer', 'retur_barang'].indexOf(src) !== -1;
            ketWajib.classList.toggle('d-none', !wajib);

            // Kasbon dipotong dari slip gaji: jatuh tempo otomatis tgl 1 bulan depan.
            var kasbon = src === 'kasbon';
            elJatuhTempo.readOnly = kasbon;
            elJatuhTempo.classList.toggle('bg-light', kasbon);
            if (kasbon) { elJatuhTempo.value = firstOfNextMonth(); }
        }

        function syncPihak() {
            var tipe = elPihakTipe.value;
            var lain = tipe === 'lainnya';
            wrapPihak.classList.toggle('d-none', lain);
            wrapNama.classList.toggle('d-none', !lain);
            elPihak.disabled = lain;
            elPihak.required = !lain;
            elNama.required = lain;
            labelPihak.textContent = LABEL[tipe] || 'Pihak';
            if (!lain) { fillPihak(tipe); }
        }

        elSumber.addEventListener('change', function () { elPihakTipe.value = ''; elJenis.value = ''; sync(); });
        elJenis.addEventListener('change', function () {
            if (hrd[elSumber.value].pihak) { sync(); } else { elPihakTipe.value = ''; sync(); }
        });
        elPihakTipe.addEventListener('change', syncPihak);

        if (INIT.sumber && hrd[INIT.sumber]) { elSumber.value = INIT.sumber; }
        if (INIT.jenis) { elJenis.value = INIT.jenis; }
        sync();
        if (INIT.pihakTipe) {
            elPihakTipe.value = INIT.pihakTipe;
            syncPihak();
        }
        if (INIT.pihakId) {
            fillPihak(elPihakTipe.value);
            elPihak.value = INIT.pihakId;
            if (window.jQuery && jQuery.fn.select2) { jQuery(elPihak).trigger('change'); }
        }

        var total = document.getElementById('hp-total');
        total.addEventListener('input', function () {
            var n = total.value.replace(/[^\d]/g, '');
            total.value = n ? Number(n).toLocaleString('id-ID') : '';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
