/**
 * Regression test client-side untuk form Rekonsiliasi Harian.
 *
 * Menjalankan <script> yang benar-benar ada di
 * app/Views/inc/rek_input_js.php di atas DOM minimal, lalu
 * mensimulasikan ketikan/paste pengguna. Tujuannya menjaga dua hal yang
 * sering rewel:
 *   1. Titik ribuan ter-format OTOMATIS saat mengetik angka saja.
 *   2. Input tidak tersangkut setelah titik terpasang (regresi "1.0005").
 *   3. Paste negatif / desimal ditolak (parity dengan server).
 *   4. Status komponen OTOMATIS dari aktual vs ERP (tanpa checkbox).
 *
 * Jalankan: node app/Scripts/rekon_js_test.js
 * Output:  "PASS: <n>  FAIL: <m>"  (exit code 1 bila ada failure)
 */
'use strict';

const fs = require('fs');
const path = require('path');

// Logika input dipindah ke partial agar form DAN panel per-hari di daftar
// bulanan memakai SATU implementasi. Test ini tetap menguji kode yang
// benar-benar dirender, hanya sumbernya partial, bukan view form.
const VIEW = path.join(__dirname, '..', 'Views', 'inc', 'rek_input_js.php');

let pass = 0;
let fail = 0;

function check(name, actual, expected) {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a === e) {
        pass++;
        console.log('PASS  ' + name);
    } else {
        fail++;
        console.log('FAIL  ' + name + '\n        expected ' + e + '\n        actual   ' + a);
    }
}

// --- ekstrak blok <script> dari view -----------------------------------------
const viewSrc = fs.readFileSync(VIEW, 'utf8');
const match = viewSrc.match(/<script>([\s\S]*?)<\/script>/);
if (!match) {
    console.log('FAIL  blok <script> tidak ditemukan di ' + VIEW);
    console.log('\nPASS: 0  FAIL: 1');
    process.exit(1);
}

function makeClassList() {
    const set = new Set();
    return {
        add(c) { set.add(c); },
        remove(c) { set.delete(c); },
        contains(c) { return set.has(c); },
    };
}

/** Label baris di view (dipakai form untuk prefiks pesan live-region). */
const LABELS = {
    cash_masuk: 'Cash Masuk',
    transfer_masuk: 'Transfer Masuk',
    kas_keluar: 'Kas Keluar',
};

function makeInput(group, erp, value) {
    return {
        value: value || '',
        dataset: {
            group: group,
            erp: String(erp),
            selishtext: 'selisih_' + group,
            status: 'status_' + group,
            lastValid: value || '',
        },
        attrs: {},
        selectionStart: 0,
        selectionEnd: 0,
        _listeners: {},
        classList: makeClassList(),
        addEventListener(ev, fn) { (this._listeners[ev] = this._listeners[ev] || []).push(fn); },
        setAttribute(k, v) { this.attrs[k] = v; },
        removeAttribute(k) { delete this.attrs[k]; },
        setSelectionRange(a, b) { this.selectionStart = a; this.selectionEnd = b; },
        /** Baris tabel: form membaca .rk-k untuk nama kelompok saat pesan. */
        closest(sel) {
            if (sel !== 'tr') { return null; }
            return {
                querySelector(s) {
                    return s === '.rk-k' ? { textContent: LABELS[group] || group } : null;
                },
            };
        },
        fire(ev, extra) {
            const evt = Object.assign({
                defaultPrevented: false,
                preventDefault() { this.defaultPrevented = true; },
            }, extra || {});
            (this._listeners[ev] || []).forEach((fn) => fn.call(this, evt));
            return evt;
        },
        /** Ketik satu per satu seperti pengguna sungguhan. */
        type(str) {
            for (const ch of str) {
                this.value += ch;
                this.fire('input');
            }
            return this.value;
        },
    };
}

const inputs = [
    makeInput('cash_masuk', 500000, ''),
    makeInput('transfer_masuk', 0, ''),
    makeInput('kas_keluar', 100000, ''),
];

const els = {
    'selisih_cash_masuk': { textContent: '' },
    'selisih_transfer_masuk': { textContent: '' },
    'selisih_kas_keluar': { textContent: '' },
    'status_cash_masuk': { innerHTML: '' },
    'status_transfer_masuk': { innerHTML: '' },
    'status_kas_keluar': { innerHTML: '' },
    // Pesan error per-field (view: <div class="rk-msg" id="msg_<?= key ?>">).
    // Redesign memakai pesan di bawah field masing-masing, bukan satu hint global.
    'msg_cash_masuk': { textContent: '' },
    'msg_transfer_masuk': { textContent: '' },
    'msg_kas_keluar': { textContent: '' },
};

// Unit input dipanggil sebagai factory: script mencari elemen ber-atribut
// [data-rk-input], lalu memasang penangan pada .rupiah-rekon di dalamnya.
// Mock ini meniru bentuk itu. Prefix diuji lewat koefisien 0 (halaman form)
// DAN lewat unit kedua ber-prefix (daftar bulanan) di blok paling bawah.
const unitDefault = {
    getAttribute: () => '',
    querySelectorAll(sel) { return sel === '.rupiah-rekon' ? inputs : []; },
};

global.document = {
    addEventListener(ev, fn) { if (ev === 'DOMContentLoaded') { global.__boot = fn; } },
    querySelectorAll(sel) {
        if (sel === '.rupiah-rekon') { return inputs; }
        if (sel === '[data-rk-input]') { return global.__units || [unitDefault]; }
        return [];
    },
    getElementById(id) { return els[id] || null; },
};

// --- jalankan script view -----------------------------------------------------
// eslint-disable-next-line no-eval
eval(match[1]);
if (typeof global.__boot !== 'function') {
    console.log('FAIL  DOMContentLoaded tidak terpasang');
    console.log('\nPASS: 0  FAIL: 1');
    process.exit(1);
}
global.__boot();

const cash = inputs[0];
const transfer = inputs[1];
const keluar = inputs[2];

// === 1. Format ribuan otomatis ===============================================
check('ketik 1500000 -> "1.500.000"', cash.type('1500000'), '1.500.000');
check('titik otomatis tanpa user mengetik pemisah', cash.value.includes('.'), true);
check('kursor berada di akhir', cash.selectionStart, cash.value.length);
check('input valid (tidak is-invalid)', cash.classList.contains('is-invalid'), false);
check('input sah tidak menyisakan pesan error', els['msg_cash_masuk'].textContent, '');

cash.value = '';
cash.fire('input');
check('ketik 1000 -> "1.000"', cash.type('1000'), '1.000');

// REGRESI UTAMA: dulu "1.000" + "5" = "1.0005" gagal aturan 3 digit -> tersangkut.
cash.value = '1.000500';
cash.fire('input');
check('regresi: bisa lanjut diketik setelah titik', cash.value, '1.000.500');

cash.value = '';
cash.fire('input');
check('ketik 12345 -> "12.345"', cash.type('12345'), '12.345');
check('ketik 999999999 -> "999.999.999"', (cash.value = '', cash.fire('input'), cash.type('999999999')), '999.999.999');
check('kosong tetap kosong', (cash.value = '', cash.fire('input'), cash.value), '');

// koma manual dinormalisasi ke titik (tidak menggagalkan input)
cash.value = '';
cash.fire('input');
check('koma manual dinormalisasi', cash.type('1,000'), '1.000');

// === 2. Status & selisih live =================================================
check('selisih live untuk 12.345 vs ERP 500.000', (() => {
    cash.value = '';
    cash.fire('input');
    cash.type('12345');
    return els['selisih_cash_masuk'].textContent;
})(), 'Rp -487.655');
// Status otomatis: tidak ada lagi checkbox "Sudah Diperiksa".
check('badge "Selisih" langsung saat aktual != ERP', els['status_cash_masuk'].innerHTML.includes('Selisih'), true);
check('badge "Selisih" tanpa perlu interaksi lain', els['status_cash_masuk'].innerHTML.includes('Selisih'), true);

cash.value = '';
cash.fire('input');
check('badge "Belum diperiksa" saat aktual kosong', els['status_cash_masuk'].innerHTML.includes('Belum diperiksa'), true);
check('selisih "—" saat aktual kosong', els['selisih_cash_masuk'].textContent, '\u2014');

cash.value = '';
cash.fire('input');
cash.type('500000');
check('badge "Cocok" saat aktual = ERP', els['status_cash_masuk'].innerHTML.includes('Cocok'), true);

// actual 0 adalah nilai sah: harus "Selisih"/"Cocok", BUKAN "Belum diperiksa".
cash.value = '';
cash.fire('input');
cash.type('0');
check('badge "Selisih" untuk aktual 0 vs ERP 500.000 (0 = nilai sah)', els['status_cash_masuk'].innerHTML.includes('Selisih'), true);
check('selisih untuk aktual 0 = -500.000', els['selisih_cash_masuk'].textContent, 'Rp -500.000');

// === 3. Penolakan input tidak valid ===========================================
cash.dataset.lastValid = '1.000';
cash.value = '1.000-';
cash.fire('input');
check('minus ditolak & nilai lama dipulihkan', cash.value, '1.000');
check('minus ditandai is-invalid', cash.classList.contains('is-invalid'), true);
check('pesan per-field berubah jadi error', els['msg_cash_masuk'].textContent.length > 0, true);
check('pesan per-field menyebut alasannya', /tidak boleh negatif/i.test(els['msg_cash_masuk'].textContent), true);
check('badge kembali "Belum diperiksa" saat invalid', els['status_cash_masuk'].innerHTML.includes('Belum diperiksa'), true);
check('selisih jadi "—" saat invalid', els['selisih_cash_masuk'].textContent, '—');

cash.value = 'abc';
cash.fire('input');
check('huruf ditolak', cash.value, '1.000');

// nilai valid berikutnya harus menghapus status invalid
cash.value = '';
cash.fire('input');
check('input valid berikutnya menghapus error', cash.classList.contains('is-invalid'), false);
check('pesan per-field dikosongkan lagi', els['msg_cash_masuk'].textContent, '');

// === 4. Paste (parse ketat, parity dengan server) ============================
transfer.fire('paste', { clipboardData: { getData: () => '2.750.000' } });
check('paste "2.750.000" (ribuan ganda) diterima', transfer.value, '2.750.000');

transfer.fire('paste', { clipboardData: { getData: () => '1,000,000' } });
check('paste "1,000,000" diterima', transfer.value, '1.000.000');

transfer.fire('paste', { clipboardData: { getData: () => 'Rp 750.000' } });
check('paste "Rp 750.000" diterima', transfer.value, '750.000');

transfer.fire('paste', { clipboardData: { getData: () => '1.500,25' } });
check('paste desimal DITOLAK (nilai tak berubah)', transfer.value, '750.000');
check('paste desimal = is-invalid', transfer.classList.contains('is-invalid'), true);

transfer.fire('paste', { clipboardData: { getData: () => '-100' } });
check('paste negatif DITOLAK', transfer.value, '750.000');
transfer.fire('paste', { clipboardData: { getData: () => 'Rp -100' } });
check('paste "Rp -100" DITOLAK', transfer.value, '750.000');

transfer.fire('paste', { clipboardData: { getData: () => 'abc' } });
check('paste teks non-numerik DITOLAK', transfer.value, '750.000');

transfer.fire('paste', { clipboardData: { getData: () => '1000,5' } });
check('paste "1000,5" (desimal) DITOLAK', transfer.value, '750.000');

transfer.fire('paste', { clipboardData: { getData: () => '2500000' } });
check('paste angka polos diformat otomatis', transfer.value, '2.500.000');
check('paste valid menghapus error', transfer.classList.contains('is-invalid'), false);

transfer.fire('paste', { clipboardData: { getData: () => '0' } });
// Angka 0 itu SAH, bukan "belum diisi": parseNominalRekon() di
// DashboardFinance memetakan "" -> null tapi "0" -> 0. Kalau paste "0"
// dikosongkan, hari yang sebenarnya lengkap akan tersimpan ulang jadi NULL.
check('paste "0" -> tetap "0" (0 sah, bukan kosong)', transfer.value, '0');

// === 5. Blur & nilai awal dari server ========================================
keluar.value = '3.250.000';
keluar.fire('blur');
check('blur tidak merusak nilai server', keluar.value, '3.250.000');
check('blur tidak menandai invalid', keluar.classList.contains('is-invalid'), false);

keluar.value = '';
keluar.fire('input');
check('kosong TIDAK dihitung selisih (tampil "—")', els['selisih_kas_keluar'].textContent, '\u2014');
check('kosong tetap berstatus "Belum diperiksa"', els['status_kas_keluar'].innerHTML.includes('Belum diperiksa'), true);

// --- dua unit input berdampingan (halaman daftar bulanan) -------------------
// Di daftar bulanan satu halaman memuat satu unit per tanggal, masing-masing
// dengan prefix id sendiri. Dua hal yang wajib benar:
//   1. Input hari B TIDAK boleh menulis ke selisih/pesan milik unit A.
//   2. Setelah init semua unit, mengetik di unit A masih menyasar elemen A.
//
// Risiko nyata: kalau inputs/groups/prefix dibiarkan di scope modul, unit
// terakhir yang di-init yang menang, dan setiap ketikan setelahnya menulis ke
// panel tanggal yang salah.
const mkUnit = (prefix, erpCash) => {
    const list = [{
        dataset: { group: 'cash_masuk', erp: String(erpCash) },
        value: '', classList: {
            _s: new Set(),
            add(c) { this._s.add(c); }, remove(c) { this._s.delete(c); },
            contains(c) { return this._s.has(c); },
        },
        handlers: {},
        addEventListener(ev, fn) { this.handlers[ev] = fn; },
        fire(ev, arg) { this.handlers[ev].call(this, arg || {}); },
        setAttribute() {}, removeAttribute() {},
        setSelectionRange() {},
        closest() { return { querySelector: () => ({ textContent: 'Cash Masuk' }) }; },
    }];
    els[prefix + 'selisih_cash_masuk'] = { textContent: '' };
    els[prefix + 'status_cash_masuk'] = { innerHTML: '' };
    els[prefix + 'msg_cash_masuk'] = { textContent: '' };
    return {
        getAttribute: () => prefix,
        querySelectorAll: sel => (sel === '.rupiah-rekon' ? list : []),
        _list: list,
    };
};

const hariA = mkUnit('', 1_000_000);
const hariB = mkUnit('p20260910-', 500_000);
global.__units = [hariA, hariB];
global.__boot();

hariA._list[0].value = '1.000.000';
hariA._list[0].fire('input');
check('hari A: input diketik jadi berpemisah ribuan', hariA._list[0].value, '1.000.000');
check('hari A: selisih A = 0 (Cocok)', els['selisih_cash_masuk'].textContent, 'Rp 0');
check('hari A: status A = Cocok', els['status_cash_masuk'].innerHTML.includes('Cocok'), true);
check('hari A: selisih B tidak ikut berubah', els['p20260910-selisih_cash_masuk'].textContent, '\u2014');

hariB._list[0].value = '750.000';
hariB._list[0].fire('input');
check('hari B: selisih B = 250.000 (Selisih)', els['p20260910-selisih_cash_masuk'].textContent, 'Rp 250.000');
check('hari B: status B = Selisih', els['p20260910-status_cash_masuk'].innerHTML.includes('Selisih'), true);
check('hari B: selisih A tidak berubah', els['selisih_cash_masuk'].textContent, 'Rp 0');

// Error harus mendarat di pesan milik unit yang diketik.
hariB._list[0].value = 'abc';
hariB._list[0].fire('input');
check('hari B: pesan error masuk ke msg B', els['p20260910-msg_cash_masuk'].textContent.length > 0, true);
check('hari A: pesan error A tetap kosong', els['msg_cash_masuk'].textContent, '');

console.log('\n========================================');
console.log('PASS: ' + pass + '  FAIL: ' + fail);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
