<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Tutup Kasir</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Tutup Kasir</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
        <h5 class="mb-0 fw-semibold">Laporan Hari Ini (<?=date('Y-m-d'); ?>)</h5>
    </div>

<div class="card-body px-4 pt-4 pb-2">
    <form action="<?= base_url('tutupkasir/tutup') ?>" method="post">
        <?php
        // ==============================================================
        // ANGKA DI HALAMAN INI = HASIL HITUNG SERVER
        // ==============================================================
        //
        // Semua angka diambil dari `TutupKasirClosing::hitung()` yang dipanggil
        // `TutupKasir::index()` — service yang SAMA dengan yang dipakai saat
        // menyimpan. Jadi yang tampil di layar ini pasti sama dengan yang
        // masuk ke `tutup_kasir`.
        //
        // Hidden field di bawah hanya untuk tampilan/audit. `tutup()` TIDAK
        // membacanya: `akhir_cash`, `awal_cash`, `pendapatan_*`, dan
        // `pengeluaran_*` dihitung ulang di server. Satu-satunya angka dari
        // request adalah `cash_laci` (hasil hitung uang fisik oleh manusia).
        //
        // @see \App\Services\Finance\TutupKasirClosing
        $awalKas    = $saldo_awal_kas ?? ['ada' => false, 'nilai' => null, 'sumber' => '', 'pesan' => ''];
        $awalTf     = $saldo_awal_tf ?? ['ada' => false, 'nilai' => null, 'sumber' => '', 'pesan' => ''];
        $setor      = (int) ($setor ?? 0);
        $tarik      = (int) ($tarik ?? 0);
        $belumAda   = ! $awalKas['ada'];
        $tfBelumAda = ! $awalTf['ada'];

        $awalCashNum = $belumAda ? 0 : (int) $awalKas['nilai'];
        $awalTfNum   = $tfBelumAda ? 0 : (int) $awalTf['nilai'];

        // SALDO SEHARUSNYA (sistem) — sistem yang menghitung, bukan user.
        //   akhir_cash = awal + kas masuk - kas keluar - setor + tarik
        // Saldo ini hanya dipakai untuk tampilan + live preview selisih.
        $akhirCashSistem = $awalCashNum + ($cash ?? 0) - ($pengeluarancash ?? 0) - $setor + $tarik;
        $akhirTfSistem   = $awalTfNum + ($transfer ?? 0) - ($pengeluarantf ?? 0);

        // Tanpa sumber saldo yang sah, `$akhirCashSistem` di atas hanya
        // hasil hitung dari nol PALSU. Jadi nilainya dikosongkan, bukan 0,
        // supaya tidak pernah tersimpan sebagai saldo kas.
        if ($belumAda) {
            $akhirCashSistem = null;
        }
        if ($tfBelumAda) {
            $akhirTfSistem = null;
        }

        // Server menolak penyimpanan kalau salah satu sumber saldo belum sah.
        // Tombol dimatikan karena itu, tapi guard sesungguhnya ada di server.
        $tutupBisaDisimpan = (bool) ($tutup_bisa_disimpan ?? false);
        $sudahDitutup      = (bool) ($sudah_ditutup ?? false);
        $tutupAlasan       = (string) ($tutup_alasan ?? '');
        ?>

        <?php if ($belumAda || $tfBelumAda) : ?>
            <div class="alert alert-warning border-0" role="alert">
                <h6 class="fw-semibold mb-1"><iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon> Saldo awal belum ditetapkan</h6>
                <div class="mb-1">Angka nol di bawah <strong>bukan</strong> berarti laci kosong. Itu berarti belum ada sumber saldo yang sah, jadi sistem tidak mengarang angka pengganti.</div>
                <?php if ($belumAda) : ?>
                    <div class="small"><?= esc($awalKas['pesan']) ?></div>
                <?php endif; ?>
                <?php if ($tfBelumAda) : ?>
                    <div class="small"><?= esc($awalTf['pesan']) ?></div>
                <?php endif; ?>
                <div class="small mt-2">Tetapkan baseline di menu <a href="<?= base_url('kas_bank/akun') ?>" class="fw-semibold">Kas &amp; Bank &rarr; Opening KAS</a>, atau pastikan tutup kasir sebelumnya sudah tercatat.</div>
            </div>
        <?php endif; ?>

        <?php if ($sudahDitutup) : ?>
            <div class="alert alert-danger border-0" role="alert">
                <h6 class="fw-semibold mb-1"><iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon> Sudah tutup kasir hari ini</h6>
                <div class="mb-1">Unit ini sudah punya closing untuk tanggal hari ini, jadi tutup kasir tidak bisa dilakukan lagi.</div>
            </div>
        <?php endif; ?>

        <?php if (! $tutupBisaDisimpan && ! $sudahDitutup) : ?>
            <div class="alert alert-danger border-0" role="alert">
                <h6 class="fw-semibold mb-1"><iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon> Tutup kasir ditolak</h6>
                <div class="small mb-1"><?= esc($tutupAlasan) ?></div>
            </div>
        <?php endif; ?>

        <?php
        // Token CSRF. Route POST `tutupkasir/tutup` memakai filter `csrf`
        // (lihat app/Config/Routes.php), jadi form wajib mengirim token.
        //
        // `csrf_field()` dipakai, bukan `csrf_token_name()` — fungsi itu CI 3
        // dan tidak ada di CodeIgniter 4. `csrf_field()` juga ikut memakai
        // nama field dari Security config, jadi kalau nama token diubah di
        // config, form ini tidak ikut rusak.
        ?>
        <?= csrf_field() ?>

        <input type="hidden" name="awal_cash"
        value="<?= $belumAda ? '' : $awalCashNum ?>">

        <input type="hidden" name="awal_transfer"
        value="<?= $tfBelumAda ? '' : $awalTfNum ?>">

        <input type="hidden" name="akhir_cash"
        value="<?= $akhirCashSistem === null ? '' : (int) $akhirCashSistem ?>">

        <input type="hidden" name="akhir_transfer"
        value="<?= $akhirTfSistem === null ? '' : (int) $akhirTfSistem ?>">

        <input type="hidden" name="pendapatan_cash"
        value="<?= $cash ?? 0 ?>">

        <input type="hidden" name="pendapatan_transfer"
        value="<?= $transfer ?? 0 ?>">

        <input type="hidden" name="pengeluaran_cash"
        value="<?= $pengeluarancash ?? 0 ?>">

        <input type="hidden" name="pengeluaran_transfer"
        value="<?= $pengeluarantf ?? 0 ?>">

        <div class="row g-4">
            <div class="col-md-6 col-lg-12">
                <div class="alert alert-primary border-0">
                    <h6 class="fw-semibold mb-1">Saldo Awal</h6>
                    <h4 class="mb-0 fw-bold">
                        <?php if ($belumAda && $tfBelumAda) : ?>
                            <span class="text-warning">Belum ditetapkan</span>
                        <?php else : ?>
                            Rp <?= number_format($awalCashNum + $awalTfNum, 0, ',', '.') ?>
                        <?php endif; ?>
                    </h4>
                    <?php if (! $belumAda) : ?>
                        <div class="small mt-1">
                            Kas <strong>Rp <?= number_format($awalCashNum, 0, ',', '.') ?></strong>
                            <?php if (! $tfBelumAda) : ?>
                                &middot; Transfer <strong>Rp <?= number_format($awalTfNum, 0, ',', '.') ?></strong>
                            <?php endif; ?>
                            <div class="text-muted"><?= esc($awalKas['pesan']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- Cash -->
            <div class="col-md-6 col-lg-4">
                <div class="card bg-warning-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pendapatan Cash</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format($cash ?? 0, 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:money-bag-bold" width="42"
                                class="text-warning"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transfer -->
            <div class="col-md-6 col-lg-4">
                <div class="card bg-success-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pendapatan Transfer</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format($transfer ?? 0, 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:card-transfer-bold" width="42"
                                class="text-success"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card bg-primary-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Pendapatan Cash dan Bank</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format($total_pendapatan ?? 0, 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:wallet-money-bold" width="42"
                                class="text-primary"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card bg-danger-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pengeluaran Cash</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format($pengeluarancash ?? 0, 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:bill-list-bold" width="42"
                                class="text-danger"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Pengeluaran -->
            <div class="col-md-6 col-lg-4">
                <div class="card bg-danger-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Pengeluaran Transfer</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format($pengeluarantf ?? 0, 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:bill-list-bold" width="42"
                                class="text-danger"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card bg-danger-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Pengeluaran Cash dan Bank</h6>
                                <h4 class="fw-bold mb-0">
                                    Rp <?= number_format(($pengeluarancash ?? 0) + ($pengeluarantf ?? 0), 0, ',', '.') ?>
                                </h4>
                            </div>
                            <iconify-icon icon="solar:bill-list-bold" width="42"
                                class="text-danger"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php if (($transfer_internal['ada'] ?? false)) : ?>
        <div class="row g-4">
            <div class="col-12">
                <h6 class="fw-semibold mb-2">Perpindahan Kas &harr; Bank Hari Ini</h6>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card bg-info-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Setor (Kas &rarr; Bank)</h6>
                                <h4 class="fw-bold mb-0">Rp <?= number_format($setor, 0, ',', '.') ?></h4>
                                <div class="small text-muted">Mengurangi saldo laci</div>
                            </div>
                            <iconify-icon icon="solar:bank-card-outline-bold" width="42"
                                class="text-info"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card bg-info-subtle border-0 shadow-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Tarik (Bank &rarr; Kas)</h6>
                                <h4 class="fw-bold mb-0">Rp <?= number_format($tarik, 0, ',', '.') ?></h4>
                                <div class="small text-muted">Menambah saldo laci</div>
                            </div>
                            <iconify-icon icon="solar:wallet-money-bold" width="42"
                                class="text-info"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SALDO SEHARUSNYA (SISTEM) — SELALU ditampilkan.
             Dulu kartu ini DI DALAM `if ($transfer_internal['ada'])`, jadi
             hari tanpa Setor/Tarik kartu ini hilang sama sekali — padahal
             saldo seharusnya adalah informasi utama halaman ini. Sekarang
             kartu berdiri sendiri; Setor/Tarik hanya komponen tambahan.

             Nilai `$akhirCashSistem` sudah dihitung server lewat
             TutupKasirClosing::hitung(), sama persis dengan angka yang akan
             disimpan. Hidden field HANYA untuk tampilan/audit — server
             mengabaikannya dan menghitung ulang. -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card bg-light border-0 shadow-none h-100">
                    <div class="card-body">
                        <h6 class="mb-1">Saldo Seharusnya (Sistem)</h6>
                        <?php if ($akhirCashSistem === null) : ?>
                            <h4 class="fw-bold mb-0"><span class="text-warning">Belum tersedia</span></h4>
                            <div class="small text-muted">
                                Saldo awal belum ditetapkan, jadi sistem tidak menghitung angka apa pun.
                            </div>
                        <?php else : ?>
                            <h4 class="fw-bold mb-0">Rp <?= number_format($akhirCashSistem, 0, ',', '.') ?></h4>
                            <div class="small text-muted">
                                Saldo awal + kas masuk &minus; kas keluar &minus; setor + tarik.
                                Angka ini sistem yang menghitung, bukan yang diinput form.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="mt-4">

                    <label class="form-label fw-semibold">
                        Total Uang di Laci
                    </label>

                    <div class="input-group">
                        <span class="input-group-text">Rp</span>

                        <input 
                            type="number"
                            name="cash_laci"
                            id="uang_laci"
                            class="form-control"
                            placeholder="Masukkan total uang"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            required
                            <?= $belumAda ? 'disabled' : '' ?>
                        >
                    </div>

                    <small class="text-muted">
                        Isi sesuai uang fisik yang ada di kas/laci. Wajib diisi; isi <strong>0</strong> kalau
                        laci memang kosong. Angka ini boleh berbeda dari saldo sistem — selisihnya
                        justru yang dicari.
                    </small>

                    <div class="alert alert-light border mt-3 mb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-semibold">Selisih</span>
                                <div class="small text-muted">Uang di laci &minus; saldo seharusnya</div>
                            </div>
                            <div class="fw-bold fs-5" id="selisihText">Rp 0</div>
                        </div>
                    </div>

                </div>

        <!-- Peringatan closing terlambat (hanya muncul setelah 23:00 WIB) -->
        <div class="alert alert-warning border-0 d-none" id="peringatanTerlambat" role="alert">
            <div class="d-flex align-items-start">
                <iconify-icon icon="solar:danger-triangle-bold" width="20" class="flex-shrink-0 me-2"></iconify-icon>
                <div>
                    <div class="fw-semibold">Closing terlambat</div>
                    <div class="small">
                        Sekarang sudah lewat <strong>23:00 WIB</strong>. Tutup Kasir tetap
                        <strong>dibuka</strong> supaya kasir bisa tetap menutup buku hari ini,
                        tapi closing ini tercatat di luar jam closing normal.
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Tutup Kasir -->
        <div class="text-end mt-4">
            <button type="submit" id="btnTutupKasir" class="btn btn-danger px-4"
                <?= (! $tutupBisaDisimpan || $sudahDitutup) ? 'disabled' : '' ?>>
                <iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon>
                Tutup Kasir
            </button>

            <?php if (!empty($tutupkasir)) : ?>
                <a href="<?= base_url('/cetak-tutup-kasir/' . $tutupkasir->idtutupkasir) ?>"
                    target="_blank"
                    class="btn btn-primary px-4">
                    <iconify-icon icon="solar:printer-bold" width="20"></iconify-icon>
                    Print
                </a>
            <?php endif; ?>
        </div>
        <?php if (isset($error)) : ?>
            <div class="alert alert-danger">
                <?= esc($error) ?>
            </div>
        <?php endif; ?>
        </form>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const uangLaci = document.getElementById("uang_laci");
    const selisihText = document.getElementById("selisihText");

    // Saldo SEHARUSNYA, sama persis dengan nilai hidden `akhir_cash`:
    //   awal + kas masuk - kas keluar - setor + tarik
    // Angka ini dikirim server; JS hanya memformat untuk tampilan.
    const saldoCash = <?= $akhirCashSistem === null ? 'null' : (int) $akhirCashSistem ?>;

    // Tanpa guard ini, halaman ini melempar TypeError setiap kali admin
    // mengetik (element #selisihText pernah tidak ada di DOM).
    if (!uangLaci || !selisihText) {
        return;
    }

    const rupiah = new Intl.NumberFormat('id-ID');

    function hitungSelisih() {

        // Tanpa baseline yang sah tidak ada angka "sistem" yang bisa dibandingkan.
        if (saldoCash === null) {
            selisihText.innerHTML = '<small>Belum ada saldo awal</small>';
            return;
        }

        const fisik = parseInt(uangLaci.value, 10) || 0;
        const selisih = fisik - saldoCash;

        if (selisih < 0) {
            selisihText.innerHTML =
                '- Rp ' + rupiah.format(Math.abs(selisih)) +
                ' <small>(Kurang)</small>';
        } else if (selisih > 0) {
            selisihText.innerHTML =
                '+ Rp ' + rupiah.format(selisih) +
                ' <small>(Lebih)</small>';
        } else {
            selisihText.innerHTML = 'Rp 0';
        }
    }

    uangLaci.addEventListener('input', hitungSelisih);
    hitungSelisih();
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const btn = document.getElementById("btnTutupKasir");
    const form = btn.closest("form");

    // Server MENOLAK tutup kasir kalau sumber saldo belum sah, jadi jam buka
    // di bawah tidak boleh mengaktifkan kembali tombol ini.
    const tutupBisaDisimpan = <?= $tutupBisaDisimpan ? 'true' : 'false' ?>;
    const sudahDitutup = <?= $sudahDitutup ? 'true' : 'false' ?>;

    // ==============================================================
    // WAKTU: WIB (Asia/Jakarta), BUKAN UTC DAN BUKAN ZONA BROWSER
    // ==============================================================
    //
    // Versi lama memakai dua sumber waktu yang tidak sinkron:
    //   `new Date().getHours()`  -> waktu lokal PERAMBAN, bisa saja bukan WIB
    //   `new Date().toISOString()` -> UTC
    // Kalau peramban ada di zona lain, jam 20:45 dihitung di zona yang
    // salah. Sekarang offset WIB dikirim server sebagai konstanta, jadi
    // browser di timezone mana pun menghitung jam yang sama dengan server.
    //
    // Nilai ini dihitung PHP dengan DateTimeZone('Asia/Jakarta'), jadi
    // "WIB" di sini bukan asumsi.
    const OFFSET_WIB_MENIT = <?= $wibOffsetMenit ?? 420 ?>;

    // Jam dan tanggal "dinding" WIB.
    //
    // Caranya: geser epoch sekarang sebesar offset WIB, lalu baca komponen
    // UTC-nya. Setelah digeser, komponen `getUTC*()` itu PERSIS jam dinding
    // WIB — jadi hasilnya benar untuk browser di timezone mana pun, tanpa
    // `Date.toISOString()` (yang selalu UTC dan mudah salah dibaca).
    function waktuWib() {
        const d = new Date(Date.now() + OFFSET_WIB_MENIT * 60000);

        const bulan = String(d.getUTCMonth() + 1).padStart(2, '0');
        const hari  = String(d.getUTCDate()).padStart(2, '0');

        return {
            jam: d.getUTCHours(),
            menit: d.getUTCMinutes(),
            tanggal: d.getUTCFullYear() + '-' + bulan + '-' + hari
        };
    }

    function cekWaktu() {

        const peringatan = document.getElementById('peringatanTerlambat');

        function sembunyikanPeringatan() {
            if (peringatan) {
                peringatan.classList.add('d-none');
            }
        }

        if (sudahDitutup) {
            btn.disabled = true;
            btn.innerHTML = `
                <iconify-icon icon="solar:check-circle-bold" width="20"></iconify-icon>
                Sudah Tutup Kasir
            `;
            sembunyikanPeringatan();
            return;
        }

        if (!tutupBisaDisimpan) {
            btn.disabled = true;
            btn.innerHTML = `
                <iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon>
                Saldo Awal Belum Ditetapkan
            `;
            sembunyikanPeringatan();
            return;
        }

        const now = waktuWib();

        const totalMenit = now.jam * 60 + now.menit;

        // ==============================================================
        // BUSINESS WINDOW TUTUP KASIR (WIB)
        // ==============================================================
        //   <  20:45  tombol mati  — belum boleh closing
        //   20:45 - 22:59  tombol aktif — window closing normal
        //   >= 23:00  tombol TETAP aktif + tampil peringatan "terlambat"
        //
        // Batas bisnis yang benar adalah 23:00, bukan 21:15. Setelah 23:00
        // sengaja TIDAK di-hard-lock: versi lama membiarkan closing sampai
        // tengah malam, dan mengunci tombol setelah 23:00 akan mengubah
        // perilaku produksi itu — kasir yang biasa closing jam 23:30 akan
        // tiba-tiba tidak bisa menutup buku.
        //
        // Catatan histori: kode lama menulis
        // `totalMenit >= mulai && totalMenit <= akhir` dengan
        // `akhir = 24*60 + 15` (1455 menit). Karena `totalMenit` maksimal
        // 1439, syarat `<= akhir` selalu benar — jadi kondisi aslinya
        // praktis hanya "setelah 20:45", tanpa batas atas yang nyata.
        // Batas 23:00 di sini dipakai sebagai penanda closing terlambat,
        // bukan sebagai pengunci.
        const MULAI_NORMAL = 20 * 60 + 45;  // 20:45 WIB
        const BATAS_TERLAMBAT = 23 * 60;     // 23:00 WIB

        if (totalMenit < MULAI_NORMAL) {

            btn.disabled = true;

            btn.innerHTML = `
                <iconify-icon icon="solar:clock-circle-bold" width="20"></iconify-icon>
                Belum Waktunya
            `;

            sembunyikanPeringatan();

        } else {

            btn.disabled = false;

            const terlambat = totalMenit >= BATAS_TERLAMBAT;

            btn.innerHTML = terlambat
                ? `
                    <iconify-icon icon="solar:danger-triangle-bold" width="20"></iconify-icon>
                    Tutup Kasir (Terlambat)
                `
                : `
                    <iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon>
                    Tutup Kasir
                `;

            if (peringatan) {
                peringatan.classList.toggle('d-none', !terlambat);
            }
        }
    }

    // saat form submit
    form.addEventListener('submit', function () {

        // Cegah double submit selama POST berjalan. Tidak ada penandaan
        // tanggal di localStorage: versi lama menulis flag di sini SEBELUM
        // server menjawab, jadi submit yang gagal (selisih laci, CSRF
        // kedaluwarsa, unit tidak cocok) tetap menyisakan flag dan membuat
        // tombol mati "Sudah Tutup Kasir" sampai akhir hari, padahal DB
        // tidak punya closing. Status tutup hanya dari `sudahDitutup`
        // (query DB di server). Kalau POST gagal, controller redirect
        // kembali dan `sudahDitutup` = false, jadi tombol hidup lagi.
        btn.disabled = true;

        btn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            Memproses...
        `;
    });

    // jalankan pertama kali
    cekWaktu();

    // cek tiap 10 detik
    setInterval(cekWaktu, 10000);

});
</script>
