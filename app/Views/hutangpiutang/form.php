<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Input Hutang Piutang</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active">Input</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= base_url('hutangpiutang/store') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Kategori Transaksi</label>
                    <select name="sumber_tipe" id="sumber_tipe" class="form-select" required>
                        <?php foreach (($input_types ?? []) as $key => $t) : ?>
                            <option value="<?= $key ?>" data-jenis="<?= esc($t['jenis'] ?? '') ?>"><?= esc($t['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Unit</label>
                    <select name="unit_id" class="form-select" required>
                        <?php foreach (($units ?? []) as $u) : ?>
                            <option value="<?= (int) $u->idunit ?>" <?= ($unit_id ?? null) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
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
                    <input type="text" name="nama_pihak" id="nama_pihak" class="form-control" placeholder="Nama bebas (tanpa master)">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jatuh Tempo</label>
                    <input type="date" name="jatuh_tempo" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total</label>
                    <input type="text" name="total" id="total" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Uraian</label>
                    <input type="text" name="uraian" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan <span class="text-danger" id="ket-wajib">*</span></label>
                    <textarea name="keterangan" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="<?= base_url('hutangpiutang/piutang') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) { jQuery('.select2').select2({ width: '100%' }); }

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

    function pihakOptions(jenis) {
        return jenis === 'hutang' ? ['suplier', 'teknisi', 'lainnya'] : ['suplier', 'pelanggan', 'pegawai', 'lainnya'];
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
        if (window.jQuery && jQuery.fn.select2) { jQuery(elPihak).select2({ width: '100%' }); }
    }

    function sync() {
        var src = elSumber.value;
        var rule = hrd[src] || hrd.manual;

        // jenis
        if (rule.jenis) {
            wrapJenis.classList.add('d-none');
            elJenis.value = rule.jenis;
            elJenis.disabled = true;
        } else {
            wrapJenis.classList.remove('d-none');
            elJenis.disabled = false;
        }
        var jenis = rule.jenis || elJenis.value;

        // tipe pihak
        var list = rule.pihak || pihakOptions(jenis);
        var current = elPihakTipe.value;
        if (list.indexOf(current) === -1) { current = list[0]; }
        fillPihakTipe(list, current);
        syncPihak();

        // keterangan wajib untuk kategori manual/baru
        var wajib = ['manual', 'jasa_teknisi', 'kelebihan_transfer', 'retur_barang'].indexOf(src) !== -1;
        ketWajib.classList.toggle('d-none', !wajib);
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
    sync();

    var total = document.getElementById('total');
    total.addEventListener('input', function () {
        var n = total.value.replace(/[^\d]/g, '');
        total.value = n ? Number(n).toLocaleString('id-ID') : '';
    });
});
</script>
