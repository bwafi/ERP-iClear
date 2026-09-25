<style>
    /* Vertical journey rail for service intake */
    .service-rail {
        position: relative;
        display: flex;
        flex-direction: column;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .service-rail-item {
        position: relative;
    }

    .service-rail-item:not(:last-child) {
        padding-bottom: 18px;
    }

    .service-rail-item:not(:last-child)::before {
        content: "";
        position: absolute;
        left: 15px;
        top: 32px;
        bottom: 0;
        width: 2px;
        background: var(--bs-border-color);
    }

    .service-rail-link {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 8px;
        border-radius: 12px;
        text-decoration: none;
        color: var(--bs-body-color);
        cursor: pointer;
        transition: background-color .18s ease;
    }

    .service-rail-link:hover {
        background: var(--bs-secondary-bg);
    }

    .service-rail-link:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
        border-radius: 12px;
    }

    .service-rail-node {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bs-secondary-bg);
        border: 1px solid var(--bs-border-color);
        color: var(--bs-secondary-color);
        font-weight: 600;
        font-size: .875rem;
        font-variant-numeric: tabular-nums;
    }

    .service-rail-body {
        min-width: 0;
        padding-top: 3px;
    }

    .service-rail-title {
        display: block;
        font-weight: 600;
        color: var(--bs-heading-color);
        line-height: 1.3;
    }

    .service-rail-sub {
        display: block;
        font-size: .76rem;
        color: var(--bs-secondary-color);
        margin-top: 2px;
        line-height: 1.4;
        font-variant-numeric: tabular-nums;
    }

    .service-rail-item.is-active .service-rail-node {
        background: var(--bs-primary);
        border-color: var(--bs-primary);
        color: #fff;
        box-shadow: 0 0 0 5px var(--bs-primary-bg-subtle);
    }

    .service-rail-item.is-active .service-rail-title {
        color: var(--bs-primary);
    }

    .service-rail-item.is-done .service-rail-node {
        background: var(--bs-success);
        border-color: var(--bs-success);
        color: #fff;
    }

    .service-rail-item.is-done:not(:last-child)::before {
        background: var(--bs-success);
    }

    .service-rail-item.is-locked .service-rail-link {
        pointer-events: none;
        opacity: .6;
    }

    .service-rail-item.is-locked .service-rail-node {
        background: var(--bs-tertiary-bg);
        border-style: dashed;
    }

    /* Compact running ticket bar */
    .service-summary-strip {
        background: var(--bs-secondary-bg);
        border: 1px solid var(--bs-border-color);
    }

    .service-summary-metric .sv-label {
        font-size: .72rem;
        letter-spacing: .02em;
        color: var(--bs-secondary-color);
        margin-bottom: 1px;
    }

    .service-summary-metric .sv-value {
        font-weight: 600;
        color: var(--bs-heading-color);
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
        font-size: .88rem;
    }

    .service-summary-strip .divider {
        width: 1px;
        align-self: stretch;
        background: var(--bs-border-color);
    }

    /* Consistent step footer */
    .service-step-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid var(--bs-border-color);
    }

    @media (max-width: 1199.98px) {
        .service-rail {
            flex-direction: row;
            gap: 4px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .service-rail-item:not(:last-child) {
            padding-bottom: 0;
        }

        .service-rail-item:not(:last-child)::before {
            display: none;
        }

        .service-rail-item {
            flex: 1 1 0;
            min-width: 90px;
        }

        .service-rail-link {
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 6px 4px;
            gap: 6px;
        }

        .service-rail-node {
            width: 28px;
            height: 28px;
            font-size: .8rem;
        }

        .service-rail-body {
            padding-top: 0;
        }

        .service-rail-title {
            font-size: .78rem;
        }

        .service-rail-sub {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .service-rail {
            flex-wrap: wrap;
            overflow-x: visible;
        }

        .service-rail-item {
            flex: 0 0 calc(50% - 4px);
            min-width: 0;
        }

        .service-rail-title {
            font-size: .72rem;
        }
    }
</style>

<div class="card shadow-none position-relative overflow-hidden mb-4 border-0">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="d-flex align-items-center gap-3">
            <span class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                <i class="bi bi-tools fs-5"></i>
            </span>
            <div>
                <h4 class="fw-semibold mb-0">Service</h4>
                <p class="text-muted fs-3 mb-0">Input transaksi servis dalam 4 tahap</p>
            </div>
        </div>
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

<div class="row g-3" id="service-deck">

    <!-- Running ticket summary -->
    <div class="col-12 <?= empty($idservice) ? 'd-none' : '' ?>" id="service-summary-col">
        <div class="service-summary-strip rounded-2 px-4 py-3">
            <div class="d-flex flex-wrap align-items-center gap-3 gap-xl-4">
                <div class="service-summary-metric">
                    <div class="sv-label d-inline-flex align-items-center gap-1"><i class="bi bi-person-fill"></i> Pelanggan</div>
                    <div class="sv-value" id="sv-pelanggan">-</div>
                </div>
                <div class="divider d-none d-lg-block"></div>
                <div class="service-summary-metric">
                    <div class="sv-label d-inline-flex align-items-center gap-1"><i class="bi bi-phone-fill"></i> Perangkat</div>
                    <div class="sv-value" id="sv-perangkat">-</div>
                </div>
                <div class="divider d-none d-lg-block"></div>
                <div class="service-summary-metric">
                    <div class="sv-label"><i class="bi bi-clipboard-check-fill"></i> Kerusakan</div>
                    <div class="sv-value" id="sv-kerusakan">0</div>
                </div>
                <div class="divider d-none d-lg-block"></div>
                <div class="service-summary-metric">
                    <div class="sv-label"><i class="bi bi-box-fill"></i> Sparepart</div>
                    <div class="sv-value" id="sv-sparepart">0 item</div>
                </div>
                <div class="divider d-none d-lg-block"></div>
                <div class="service-summary-metric">
                    <div class="sv-label"><i class="bi bi-wallet2"></i> DP</div>
                    <div class="sv-value" id="sv-dp">-</div>
                </div>
                <div class="ms-lg-auto">
                    <div class="service-summary-metric text-lg-end">
                        <div class="sv-label"><i class="bi bi-tag-fill"></i> Total Akhir</div>
                        <div class="sv-value text-primary fs-4" id="sv-total">Rp 0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Milestone rail -->
    <div class="col-12 col-xl-3">
        <div class="card w-100 position-relative overflow-hidden h-100">
            <div class="card-body">
                <h5 class="fw-semibold mb-1 text-body">Tahap Pengerjaan</h5>
                <p class="text-muted fs-3 mb-3">Selesaikan langkah berurutan, langkah selesai bisa diklik untuk dikoreksi.</p>

                <div class="service-rail" id="service-rail" role="tablist">
                    <div class="service-rail-item is-active" data-step="pelanggan">
                        <a class="service-rail-link" id="pelanggan-tab" data-bs-toggle="tab" href="#pelanggan" role="tab"
                            aria-controls="pelanggan" aria-selected="true">
                            <span class="service-rail-node" id="node-pelanggan">1</span>
                            <span class="service-rail-body">
                                <span class="service-rail-title">Pelanggan</span>
                                <span class="service-rail-sub" id="sub-pelanggan">Data pelanggan & perangkat</span>
                            </span>
                        </a>
                    </div>
                    <div class="service-rail-item <?= empty($idservice) ? 'disabled-tab is-locked' : '' ?>" data-step="kerusakan">
                        <a class="service-rail-link" id="kerusakan-tab" data-bs-toggle="tab" href="#kerusakan" role="tab"
                            aria-controls="kerusakan" aria-selected="false">
                            <span class="service-rail-node" id="node-kerusakan">2</span>
                            <span class="service-rail-body">
                                <span class="service-rail-title">Kerusakan</span>
                                <span class="service-rail-sub" id="sub-kerusakan"><?= empty($idservice) ? 'Lengkapi data pelanggan dulu.' : 'Belum ada kerusakan dipilih.' ?></span>
                            </span>
                        </a>
                    </div>
                    <div class="service-rail-item <?= (empty($idservice) || session('ID_JABATAN') == JABATAN_KASIR) ? 'disabled-tab is-locked' : '' ?>" data-step="sparepart">
                        <a class="service-rail-link" id="sparepart-tab" data-bs-toggle="tab" href="#sparepart" role="tab"
                            aria-controls="sparepart" aria-selected="false">
                            <span class="service-rail-node" id="node-sparepart">3</span>
                            <span class="service-rail-body">
                                <span class="service-rail-title">Sparepart</span>
                                <span class="service-rail-sub" id="sub-sparepart"><?= empty($idservice) ? 'Lengkapi data pelanggan dulu.' : (session('ID_JABATAN') == JABATAN_KASIR ? 'Tidak tersedia untuk jabatan ini.' : 'Belum ada sparepart.') ?></span>
                            </span>
                        </a>
                    </div>
                    <div class="service-rail-item <?= empty($idservice) ? 'disabled-tab is-locked' : '' ?>" data-step="pembayaran">
                        <a class="service-rail-link" id="pembayaran-tab" data-bs-toggle="tab" href="#pembayaran" role="tab"
                            aria-controls="pembayaran" aria-selected="false">
                            <span class="service-rail-node" id="node-pembayaran">4</span>
                            <span class="service-rail-body">
                                <span class="service-rail-title">Pembayaran</span>
                                <span class="service-rail-sub" id="sub-pembayaran"><?= empty($idservice) ? 'Lengkapi data pelanggan dulu.' : 'Rangkuman tagihan.' ?></span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active step panel -->
    <div class="col-12 col-xl-9">
        <div class="card w-100 position-relative overflow-hidden h-100">
            <div class="card-body">
                <div class="tab-content mt-0">
                    <div class="tab-pane fade show active" id="pelanggan" role="tabpanel" aria-labelledby="pelanggan-tab">
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
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {

        /* Role lock: jabatan 36 (kasir) dilarang mengakses langkah Sparepart */
        const isRestricted = <?= session('ID_JABATAN') == JABATAN_KASIR ? 'true' : 'false' ?>;

        if (isRestricted) {
            const sparepartTab = document.getElementById('sparepart-tab');
            if (sparepartTab) {
                sparepartTab.addEventListener('show.bs.tab', function (e) {
                    e.preventDefault();
                    return false;
                });
            }
        }

        const STEPS = ['pelanggan', 'kerusakan', 'sparepart', 'pembayaran'];

        function parseRp(str) {
            if (!str) return 0;
            return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0;
        }

        function el(id) { return document.getElementById(id); }

        function refreshServiceState() {
            const ticketInput = document.querySelector('input[name="idservice"]');
            const hasTicket = !!(ticketInput && ticketInput.value);

            /* summary bar values */
            const nama = (el('nama_pelanggan') && el('nama_pelanggan').value) || '-';
            const tipe = document.querySelector('input[name="tipe_hp"]');
            const perangkat = (tipe && tipe.value.trim()) ? tipe.value.trim() : '-';
            const checked = document.querySelectorAll('.checkbox-fungsi:checked').length;
            const rows = document.querySelectorAll('#sparepart-table-body tr').length;
            const hargaAkhir = (el('harga_akhir_sparepart') && el('harga_akhir_sparepart').value) || 'Rp 0';
            const hargaFinal = (el('total_harga_pembayaran_akhir') && el('total_harga_pembayaran_akhir').value) || hargaAkhir;
            const dpRaw = document.querySelector('form[action*="pelanggan_service"] input[name="dp_bayar"], #dp_bayar');
            let dpTxt = '-';
            if (dpRaw && dpRaw.value) {
                const n = parseRp(dpRaw.value);
                dpTxt = n > 0 ? 'Rp ' + n.toLocaleString('id-ID') : '-';
            }

            if (hasTicket) {
                const sumCol = el('service-summary-col');
                if (sumCol) sumCol.classList.remove('d-none');
            }
            if (el('sv-pelanggan')) el('sv-pelanggan').textContent = nama;
            if (el('sv-perangkat')) el('sv-perangkat').textContent = perangkat;
            if (el('sv-kerusakan')) el('sv-kerusakan').textContent = checked;
            if (el('sv-sparepart')) el('sv-sparepart').textContent = rows + ' item';
            if (el('sv-dp')) el('sv-dp').textContent = dpTxt;
            const totalShown = (rows > 0 || checked > 0) ? hargaFinal : hargaAkhir;
            if (el('sv-total')) el('sv-total').textContent = totalShown;

            /* rail states */
            document.querySelectorAll('#service-rail .service-rail-item').forEach(function (item) {
                const step = item.getAttribute('data-step');
                const locked = item.classList.contains('is-locked') || item.classList.contains('disabled-tab');
                const node = el('node-' + step);
                const sub = el('sub-' + step);

                let done = false;
                if (step === 'pelanggan') done = hasTicket;
                if (step === 'kerusakan') done = checked > 0;
                if (step === 'sparepart') done = rows > 0 || (el('garansiSelect') && el('garansiSelect').value);
                if (step === 'pembayaran') done = false;

                item.classList.toggle('is-done', done && !locked);
                item.classList.toggle('is-locked', locked);

                if (node) {
                    if (done && !locked) {
                        node.innerHTML = '<i class="bi bi-check-lg"></i>';
                    } else {
                        node.textContent = String(step === 'pelanggan' ? 1 : step === 'kerusakan' ? 2 : step === 'sparepart' ? 3 : 4);
                    }
                }

                if (sub && !locked) {
                    if (step === 'pelanggan') {
                        sub.textContent = (nama && nama !== '-') ? nama : 'Data pelanggan & perangkat';
                    } else if (step === 'kerusakan') {
                        sub.textContent = done ? checked + ' kerusakan dipilih' : 'Belum ada kerusakan dipilih.';
                    } else if (step === 'sparepart') {
                        sub.textContent = done ? (rows + ' item · ' + hargaAkhir) : 'Belum ada sparepart.';
                    } else if (step === 'pembayaran') {
                        sub.textContent = done ? 'Tagihan lengkap.' : 'Rangkuman tagihan.';
                    }
                }
            });
        }

        /* reflect active step onto the rail */
        document.querySelectorAll('#service-rail .service-rail-link').forEach(function (link) {
            link.addEventListener('shown.bs.tab', function () {
                document.querySelectorAll('#service-rail .service-rail-item').forEach(function (item) {
                    item.classList.remove('is-active');
                });
                const target = link.getAttribute('href').replace('#', '');
                const item = document.querySelector('#service-rail .service-rail-item[data-step="' + target + '"]');
                if (item) item.classList.add('is-active');
                localStorage.setItem('activeTab', link.getAttribute('href'));
                refreshServiceState();
            });
        });

        window.refreshServiceState = refreshServiceState;

        document.addEventListener('input', function () {
            clearTimeout(window.__svTimer);
            window.__svTimer = setTimeout(refreshServiceState, 80);
        });
        document.addEventListener('change', function () {
            clearTimeout(window.__svTimer);
            window.__svTimer = setTimeout(refreshServiceState, 80);
        });

        /* tab restore from ?tab= + localStorage (kept behavior) */
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            localStorage.setItem('activeTab', '#' + tabParam);
        }
        const lastTab = localStorage.getItem('activeTab');
        if (lastTab && lastTab.indexOf('#') === 0) {
            const trigger = document.querySelector('#service-rail a[href="' + lastTab + '"]');
            if (trigger && !trigger.closest('.is-locked') && !trigger.closest('.disabled-tab')) {
                new bootstrap.Tab(trigger).show();
            }
        }

        refreshServiceState();
    });
</script>