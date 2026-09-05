<style>
    .disabled-tab {
    pointer-events: none;
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Transaksi</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <!-- Nav tabs -->
        <ul class="nav nav-pills nav-fill" role="tablist">
            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center active" id="pelanggan-tab" data-bs-toggle="tab" href="#pelanggan"
                    role="tab" aria-controls="pelanggan" aria-selected="true">
                    <i class="bi bi-pencil-square fs-5"></i>
                    <span class="ms-2 mt-1">1. Pelanggan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center <?= empty($idservice) ? 'disabled-tab' : '' ?>" id="kerusakan-tab" data-bs-toggle="tab" href="#kerusakan"
                    role="tab" aria-controls="kerusakan" aria-selected="false">
                    <i class="bi bi-clipboard-check fs-5"></i>
                    <span class="ms-2 mt-1">2. Kerusakan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center <?= (empty($idservice) || session('ID_JABATAN') == JABATAN_KASIR) ? 'disabled-tab' : '' ?>"
                    id="sparepart-tab"
                    data-bs-toggle="tab"
                    href="#sparepart"
                    role="tab"
                    aria-controls="sparepart"
                    aria-selected="false">
                    <i class="bi bi-clipboard-check fs-5"></i>
                    <span class="ms-2 mt-1">3. Sparepart</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex flex-column align-items-center <?= empty($idservice) ? 'disabled-tab' : '' ?>" id="pembayaran-tab" data-bs-toggle="tab" href="#pembayaran"
                    role="tab" aria-controls="pembayaran" aria-selected="false">
                    <i class="bi bi-clipboard-check fs-5"></i>
                    <span class="ms-2 mt-1">4. Pembayaran</span>
                </a>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content mt-3">
            <div class=" tab-pane fade show active" id="pelanggan" role="tabpanel" aria-labelledby="pelanggan-tab">
                <?= view('transaksi/table/pelanggan_table') ?>
            </div>
            <div class="tab-pane fade" id="kerusakan" role="tabpanel" aria-labelledby="kerusakan-tab">
                <?= view('transaksi/table/kerusakan_table') ?>
            </div>
            <div class="tab-pane fade" id="sparepart" role="tabpanel" aria-labelledby="sparepart-tab">
                <?= view('transaksi/table/sparepart_table') ?>
            </div>
            <div class="tab-pane fade" id="pembayaran" role="tabpanel" aria-labelledby="pembayaran-tab">
                <?= view('transaksi/table/pembayaran_table') ?>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const isRestricted = <?= session('ID_JABATAN') == 36 ? 'true' : 'false' ?>;

    if (isRestricted) {
        const sparepartTab = document.querySelector('#sparepart-tab');

        // Blok klik & keyboard
        sparepartTab.addEventListener('show.bs.tab', function (e) {
            e.preventDefault();
            return false;
        });
    }

});
</script>

<script>
    // Cek apakah ada parameter ?tab=xxx di URL
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get("tab");

        if (tabParam) {
            // Set ke localStorage agar digunakan saat load
            localStorage.setItem("activeTab", "#" + tabParam);
        }

        // Ambil tab terakhir dari localStorage
        const lastTab = localStorage.getItem("activeTab");
        if (lastTab) {
            const triggerTab = document.querySelector(`a[href="${lastTab}"]`);
            if (triggerTab) {
                new bootstrap.Tab(triggerTab).show();
            }
        }

        // Update localStorage saat tab diklik
        document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem("activeTab", e.target.getAttribute("href"));
            });
        });
    });
</script>