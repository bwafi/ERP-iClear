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
            <input type="hidden" name="awal_cash"
            value="<?= $kas_awalcash ?? 0 ?>">

            <input type="hidden" name="awal_transfer"
            value="<?= $kas_awaltf ?? 0 ?>">

            <input type="hidden" name="akhir_cash"
            value="<?= ($kas_awalcash ?? 0) + ($cash ?? 0) - ($pengeluarancash ?? 0) ?>">

            <input type="hidden" name="akhir_transfer"
            value="<?= ($kas_awaltf ?? 0) + ($transfer ?? 0) - ($pengeluarantf ?? 0) ?>">

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
                        Rp <?= number_format($kas_awalcash + $kas_awaltf?? 0, 0, ',', '.') ?>
                    </h4>
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
                        >
                    </div>

                    <small class="text-muted">
                        Isi sesuai uang fisik yang ada di kas/laci
                    </small>

                </div>

        <!-- Tombol Tutup Kasir -->
        <div class="text-end mt-4">
            <button type="submit" id="btnTutupKasir" class="btn btn-danger px-4">
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
        <?php if(isset($error)) : ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        </form>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const uangLaci = document.getElementById("uang_laci");
    const selisihText = document.getElementById("selisihText");

    const saldoCash =
        <?= ($kas_awalcash ?? 0) + ($cash ?? 0) - ($pengeluarancash ?? 0) ?>;

    function hitungSelisih() {

        let fisik = parseInt(uangLaci.value) || 0;

        let selisih = fisik - saldoCash;

        let format = new Intl.NumberFormat('id-ID').format(selisih);

        if (selisih < 0) {
            selisihText.innerHTML =
                '- Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(selisih)) +
                ' <small>(Kurang)</small>';
        } else if (selisih > 0) {
            selisihText.innerHTML =
                '+ Rp ' + format +
                ' <small>(Lebih)</small>';
        } else {
            selisihText.innerHTML = 'Rp 0';
        }
    }

    uangLaci.addEventListener('input', hitungSelisih);
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const btn = document.getElementById("btnTutupKasir");
    const form = btn.closest("form");

    function cekWaktu() {

        const now = new Date();

        const jam = now.getHours();
        const menit = now.getMinutes();

        const totalMenit = jam * 60 + menit;

        // Jam buka tombol
        const mulai = 20 * 60 + 45; // 20:45
        const akhir = 24 * 60 + 15; // 21:15

        // tanggal hari ini
        const today = now.toISOString().split('T')[0];

        // cek apakah sudah pernah tutup kasir hari ini
        const sudahKlik = localStorage.getItem('tutupKasirTanggal');

        // jika sudah pernah klik hari ini
        if (sudahKlik === today) {

            btn.disabled = true;

            btn.innerHTML = `
                <iconify-icon icon="solar:check-circle-bold" width="20"></iconify-icon>
                Sudah Tutup Kasir
            `;

            return;
        }

        // cek jam
        if (totalMenit >= mulai && totalMenit <= akhir) {

            btn.disabled = false;

            btn.innerHTML = `
                <iconify-icon icon="solar:lock-keyhole-bold" width="20"></iconify-icon>
                Tutup Kasir
            `;

        } else {

            btn.disabled = true;

            btn.innerHTML = `
                <iconify-icon icon="solar:clock-circle-bold" width="20"></iconify-icon>
                Belum Waktunya
            `;
        }
    }

    // saat form submit
    form.addEventListener('submit', function () {

        const today = new Date().toISOString().split('T')[0];

        // simpan ke localStorage
        localStorage.setItem('tutupKasirTanggal', today);

        // disable tombol setelah submit berjalan
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