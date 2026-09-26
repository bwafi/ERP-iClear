/**
 * Regression test client-side untuk form Rekonsiliasi Harian.
 *
 * Menjalankan <script> yang benar-benar ada di
 * app/Views/dashboard/finance_rekon_form.php di atas DOM minimal, lalu
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

const VIEW = path.join(__dirname, '..', 'Views', 'dashboard', 'finance_rekon_form.php');

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
    'rekon-format-hint': {
        textContent: '',
        classList: makeClassList(),
        dataset: { default: 'Ketik angka saja, titik ribuan otomatis. Kosong berarti Rp 0.' },
    },
};

global.document = {
    addEventListener(ev, fn) { if (ev === 'DOMContentLoaded') { global.__boot = fn; } },
    querySelectorAll(sel) {
        if (sel === '.rupiah-rekon') { return inputs; }
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
check('hint kembali normal', els['rekon-format-hint'].classList.contains('text-danger'), false);

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
check('hint berubah jadi error', els['rekon-format-hint'].classList.contains('text-danger'), true);
check('badge kembali "Belum diperiksa" saat invalid', els['status_cash_masuk'].innerHTML.includes('Belum diperiksa'), true);
check('selisih jadi "—" saat invalid', els['selisih_cash_masuk'].textContent, '—');

cash.value = 'abc';
cash.fire('input');
check('huruf ditolak', cash.value, '1.000');

// nilai valid berikutnya harus menghapus status invalid
cash.value = '';
cash.fire('input');
check('input valid berikutnya menghapus error', cash.classList.contains('is-invalid'), false);
check('hint kembali ke teks default', els['rekon-format-hint'].textContent, els['rekon-format-hint'].dataset.default);

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
check('paste "0" -> kosong (konvensi Rp 0)', transfer.value, '');

// === 5. Blur & nilai awal dari server ========================================
keluar.value = '3.250.000';
keluar.fire('blur');
check('blur tidak merusak nilai server', keluar.value, '3.250.000');
check('blur tidak menandai invalid', keluar.classList.contains('is-invalid'), false);

keluar.value = '';
keluar.fire('input');
check('kosong TIDAK dihitung selisih (tampil "—")', els['selisih_kas_keluar'].textContent, '\u2014');
check('kosong tetap berstatus "Belum diperiksa"', els['status_kas_keluar'].innerHTML.includes('Belum diperiksa'), true);

console.log('\n========================================');
console.log('PASS: ' + pass + '  FAIL: ' + fail);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
