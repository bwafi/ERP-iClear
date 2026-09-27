<?php
/**
 * Perilaku input rekonsiliasi. Sekarang hanya satu permukaan: panel
 * per-hari di finance_rekonsiliasi.php (daftar bulanan). Dulu juga dipakai
 * halaman form harian, yang dihapus 2026-09-27.
 *
 * Satu panel = satu prefix id, jadi banyak panel dalam satu halaman tidak
 * boleh saling menimpa state (lihat initRekonInput).
 *
 * Harus satu implementasi. Aturan mainnya: format nominal, penolakan paste
 * negatif/desimal, dan status komponen otomatis tricky enough sampai pernah
 * regresi; dua salinan pasti akan aus satu sama lain, dan yang aus biasanya
 * yang tidak ada orangnya.
 *
 * app/Scripts/rekon_js_test.js mengekstrak blok script dari file ini dan
 * menjalankannya di atas DOM minimal, jadi jangan dipindah ke file .js terpisah
 * — halaman rekonsiliasi dirender ke file HTML lokal (file://) oleh harness
 * browser, sehingga <script src=base_url(...)> tidak akan termuat.
 *
 * Cara kerja: setiap elemen ber-atribut [data-rk-input] dianggap satu unit
 * input, dan data-rk-prefix-nya dipakai sebagai awalan saat mencari elemen
 * pasangan lewat id. Di halaman form prefix-nya kosong; di daftar bulanan
 * setiap tanggal memakai prefix sendiri supaya tiga input per hari tidak
 * menabrak id milik hari lain.
 */
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // -----------------------------------------------------------------
    // Format nominal: user mengetik ANGKA SAJA, titik ribuan dipasang
    // otomatis oleh JS.
    //
    // Kenapa parse ketat (parseNominal) TIDAK dipakai saat mengetik:
    // begitu titik terpasang ("1.000"), ketikan berikutnya menghasilkan
    // "1.0005" yang gagal aturan "3 digit per kelompok" -> field tersangkut
    // dan tidak bisa diketik lagi. Itu justru penyebab input merepotkan.
    //
    // parseNominal yang ketat tetap dipakai untuk NILAI AWAL dari server
    // dan untuk PASTE (menolak negatif / desimal). Server tetap menjadi
    // sumber kebenaran (parseNominalRekon di DashboardFinance).
    // -----------------------------------------------------------------

    /** Satu live region untuk seluruh halaman, ditunda supaya mengetik
     *  "1.250.000" tidak membacakan enam angka per ketikan. */
    var live = document.getElementById('rk-live');
    var liveTimer = null;

    /** Buang semua pemisah; sisa harus digit saja. */
    function readDigits(value) {
        return String(value).replace(/[^0-9]/g, '');
    }

    /** Pasang pemisah ribuan setiap 3 digit dari kanan. */
    function groupThousands(digits) {
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** Parse ketat untuk nilai server & paste (tolak negatif/desimal/sampah). */
    function parseNominal(str) {
        var raw = String(str).replace(/\s|Rp\.?/gi, '');
        // PARITY: server mengembalikan null untuk string kosong, BUKAN 0.
        // Kalau di sini kosong jadi 0, nilai kosong akan tercampur dengan
        // angka 0 yang sah — dan karena keduanya sama-sama 0, satu-satunya
        // cara membedakan lagi adalah membuang 0, yang justru merusak data.
        // (Dulu persis itu yang terjadi: boot mengubah "0" tersimpan jadi
        // kosong.)
        if (raw === '') { return NaN; }
        // Guard desimal: digit setelah pemisah TERAKHIR harus tepat 3 (p ribuan).
        // "1.000.000" -> "000" (ok). "1.500,25" -> "25" (desimal, tolak).
        // Mengikuti parseNominalRekon() di DashboardFinance (parity).
        var last = Math.max(raw.lastIndexOf('.'), raw.lastIndexOf(','));
        if (last >= 0) {
            if (raw.slice(last + 1).length !== 3) { return NaN; }
        }
        var digits = raw.replace(/[.,]/g, '');
        if (!/^-?\d+$/.test(digits)) { return NaN; }
        var n = parseInt(digits, 10);
        return (isNaN(n) || n < 0) ? NaN : n;
    }

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

        /**
         * Status komponen 100% otomatis dari perbandingan ERP vs Aktual.
         * Tidak ada checkbox / input manual penentu status.
         *
         *   aktual kosong                 -> Belum diperiksa
         *   aktual terisi & selisih = 0   -> Cocok
         *   aktual terisi & selisih != 0  -> Selisih
         *
         * Mengikuti server: RekonDailyCalculator::statusKomponen() memakai NULL
         * sebagai penanda "belum diisi", jadi angka 0 dihitung sah.
         */
    var CHIP = {
        belum: '<span class="rk-chip">Belum diperiksa</span>',
        cocok: '<span class="rk-chip is-ok">Cocok</span>',
        selisih: '<span class="rk-chip is-warn">Selisih</span>'
    };

    /**
     * Pasang perilaku pada satu unit input.
     *
     * @param {Element} root   elemen ber-atribut data-rk-input
     * @param {string}  prefix awalan id, agar hari berbeda tidak saling tabrak
     */
    function initRekonInput(root, prefix) {
        /**
         * Pesan kesalahan per field, bukan satu baris hint yang ditimpa field
         * terakhir: tiga input menulis ke tiga tempat yang berbeda.
         */
        function setInvalid(input, message) {
            input.classList.add('is-invalid');
            input.setAttribute('title', message || '');
            var msg = document.getElementById(prefix + 'msg_' + input.dataset.group);
            if (msg) { msg.textContent = message; }
        }

        function clearInvalid(input) {
            input.classList.remove('is-invalid');
            input.removeAttribute('title');
            var msg = document.getElementById(prefix + 'msg_' + input.dataset.group);
            if (msg) { msg.textContent = ''; }
        }

        var inputs = {};
        var groups = {};

        function refreshStatus(group) {
            var input = inputs[group];
            if (!input) { return; }
            var statusEl = document.getElementById(prefix + 'status_' + group);
            var selisihEl = document.getElementById(prefix + 'selisih_' + group);
            if (!statusEl) { return; }

            var erp = parseInt(input.dataset.erp || '0', 10);
            var digits = readDigits(input.value);
            var terisi = digits !== '';
            var actual = terisi ? parseInt(digits, 10) : null;
            var valid = terisi && !isNaN(actual) && !input.classList.contains('is-invalid');
            var selisih = valid ? actual - erp : null;

            if (selisihEl) {
                // Format sama dengan $fmtSelisih di PHP: "Rp -487.655". Tanda hubung
                // ASCII, bukan U+2212, supaya angka ini tetap bisa disalin ke Excel.
                selisihEl.textContent = selisih === null ? '\u2014' : formatRupiah(selisih);
                selisihEl.className = 'rk-selisih text-end rk-num '
                    + (selisih === null ? 'is-nihil' : (selisih === 0 ? 'is-nol' : 'is-ada'));
            }

            if (!valid) {
                statusEl.innerHTML = CHIP.belum;
            } else if (selisih === 0) {
                statusEl.innerHTML = CHIP.cocok;
            } else {
                statusEl.innerHTML = CHIP.selisih;
            }

            announce(group, selisih);
        }

        function announce(group, selisih) {
            if (!live) { return; }
            window.clearTimeout(liveTimer);
            liveTimer = window.setTimeout(function () {
                var bagian = [];
                Object.keys(inputs).forEach(function (key) {
                    var el = document.getElementById(prefix + 'selisih_' + key);
                    bagian.push(groups[key] + ' ' + (el ? el.textContent.trim() : ''));
                });
                live.textContent = 'Selisih terbaru. ' + bagian.join('. ') + '.';
            }, 500);
        }



        root.querySelectorAll('.rupiah-rekon').forEach(function (input) {
            inputs[input.dataset.group] = input;
            groups[input.dataset.group] = input.closest('tr').querySelector('.rk-k').textContent.trim();
        });

        root.querySelectorAll('.rupiah-rekon').forEach(function (input) {
            var group = input.dataset.group;

            // --- ketik: hanya digit diterima, titik ribuan dipasang otomatis ---
            input.addEventListener('input', function () {
                var raw = this.value;

                // Minus, huruf, atau karakter lain: tolak, kembalikan nilai terakhir.
                if (/[^0-9.,\s]/.test(raw)) {
                    setInvalid(this, 'Nominal harus angka bulat, tidak boleh negatif.');
                    this.value = this.dataset.lastValid || '';
                    refreshStatus(group);
                    return;
                }

                clearInvalid(this);

                var digits = readDigits(raw);
                this.value = digits === '' ? '' : groupThousands(digits);
                this.dataset.lastValid = this.value;

                var end = this.value.length;
                try { this.setSelectionRange(end, end); } catch (err) { /* ignore */ }

                refreshStatus(group);
            });

            // --- paste: parse ketat, tolak negatif / desimal ---
            input.addEventListener('paste', function (e) {
                var text = '';
                if (e.clipboardData) {
                    text = e.clipboardData.getData('text');
                } else if (window.clipboardData) {
                    text = window.clipboardData.getData('Text');
                }
                if (text === '') { return; }

                var parsed = parseNominal(text);
                if (isNaN(parsed)) {
                    e.preventDefault();
                    setInvalid(this, 'Nilai yang ditempel harus angka bulat (bukan negatif / desimal).');
                    refreshStatus(group);
                    return;
                }

                e.preventDefault();
                clearInvalid(this);
                // Angka 0 yang sah tetap tampil sebagai "0", sama seperti saat
                // diketik — lihat catatan normalisasi nilai awal di bawah.
                this.value = groupThousands(String(parsed));
                this.dataset.lastValid = this.value;
                var end = this.value.length;
                try { this.setSelectionRange(end, end); } catch (err) { /* ignore */ }
                refreshStatus(group);
            });

            // --- blur: normalisasi akhir ---
            input.addEventListener('blur', function () {
                if (this.classList.contains('is-invalid')) { return; }
                var digits = readDigits(this.value);
                this.value = digits === '' ? '' : groupThousands(digits);
                this.dataset.lastValid = this.value;
                refreshStatus(group);
            });
        });

        // Normalisasi nilai awal dari server: "1.000.000" tetap utuh, dan angka 0
        // yang tersimpan tetap tampil sebagai "0".
        //
        //(parseNominalRekon() di DashboardFinance membedakan "" -> null (belum
        // diisi) dari "0" -> 0 (sah), jadi 0 tidak boleh dikosongkan di sini.
        // Nilai null sudah dirender sebagai value="" oleh view, jadi tidak ada
        // yang perlu dikompensasi — membuang 0 hanya membuat hari yang sebenarnya
        // lengkap terbaca "Belum diperiksa" lalu tersimpan ulang jadi NULL.)
        root.querySelectorAll('.rupiah-rekon').forEach(function (input) {
            var parsed = parseNominal(input.value);
            if (!isNaN(parsed)) {
                input.value = groupThousands(String(parsed));
            }
            input.dataset.lastValid = input.value;
        });

        // Samakan badge & selisih dengan nilai server saat halaman dibuka.
        Object.keys(inputs).forEach(refreshStatus);
    }

    var roots = document.querySelectorAll('[data-rk-input]');
    for (var i = 0; i < roots.length; i++) {
        initRekonInput(roots[i], roots[i].getAttribute('data-rk-prefix') || '');
    }
});
</script>
