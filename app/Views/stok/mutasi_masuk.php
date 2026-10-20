<?= $this->include('stok/_mutasi_theme') ?>
<?= $this->include('stok/_mutasi_masuk_theme') ?>

<?php
$rp = static fn($n): string => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');

// Nilai dari controller dipakai utuh; fallback ini hanya untuk render view
// secara terpisah (mis. smoke test).
$isLintas    = $isLintas ?? in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);
$filter      = $filter      ?? ['search' => '', 'status' => '', 'dari' => '', 'sampai' => '', 'unit' => 0];
$currentPage = $currentPage ?? 1;
$perPage     = $perPage     ?? 25;
$total       = $total       ?? 0;
$totalPages  = $totalPages  ?? 1;
$items       = $items       ?? [];
$unit        = $unit        ?? [];

$namaUnit  = trim((string) session('NAMA_UNIT'));
$unitAktif = (int) session('ID_UNIT');

$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tgl = static function (?string $value) use ($bulan): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }

    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};

// Ditulis eksplisit, bukan `||` biasa: status '0' itu falsy, jadi menyaring
// "Belum Diterima" akan terbaca sebagai "tidak ada filter".
$adaFilter = $filter['search'] !== '' || $filter['status'] !== ''
    || $filter['dari'] !== '' || $filter['sampai'] !== '' || (int) $filter['unit'] > 0;

$statusOptions = [
    ['', 'Semua'],
    ['0', 'Belum diterima'],
    ['1', 'Sudah diterima'],
];

$dariTampil   = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$sampaiTampil = min($currentPage * $perPage, $total);
$hpDue        = $tgl(date('Y-m-d', strtotime('+3 days')));

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$detailJson = static function ($m) use ($jsonFlags): string {
    $rows = [];
    foreach (($m->detail ?? []) as $d) {
        $rows[] = [
            'nama'   => (string) ($d->nama_barang ?? ''),
            'jumlah' => (float) ($d->jumlah_terima ?? 0),
            'harga'  => (float) ($d->harga_mutasi ?? 0),
            'nilai'  => (float) ($d->nilai ?? 0),
        ];
    }
    $json = json_encode($rows, $jsonFlags);

    return $json === false ? '[]' : $json;
};

/**
 * Bangun query string yang mempertahankan filter + halaman yang sedang aktif.
 *
 * Dipakai untuk link paginasi dan untuk field `asal` pada form terima/batal.
 * Tanpa ini, setiap klik "next" menghapus filter yang sedang dipakai, dan
 * setiap terima/batal melempar user ke daftar polos.
 */
$qs = static function (array $overrides = []) use ($filter, $currentPage): string {
    // Perbandingan harus !== '' , bukan ?: . Di PHP, string '0' itu falsy,
    // jadi `?:` akan membuang status='0' — dan "Belum Diterima" justru
    // filter paling sering dipakai di halaman ini.
    $q = [
        'search' => $filter['search'] !== '' ? $filter['search'] : null,
        'status' => $filter['status'] !== '' ? $filter['status'] : null,
        'dari'   => $filter['dari'] !== '' ? $filter['dari'] : null,
        'sampai' => $filter['sampai'] !== '' ? $filter['sampai'] : null,
        'unit'   => $filter['unit'] ? (int) $filter['unit'] : null,
        'page'   => $currentPage,
    ];
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }

    return http_build_query(array_filter($q, static fn ($v) => $v !== null && $v !== ''));
};
$urlMasuk = static function (array $o = []) use ($qs): string {
    $query = $qs($o);

    return base_url('mutasi_stok/masuk') . ($query !== '' ? '?' . $query : '');
};

// Controller membaca `asal` sebagai URL query dan butuh tanda "?" di depan.
$asal = $qs() !== '' ? '?' . $qs() : '';

// Batal terima jauh lebih sempit daripada terima: hanya Admin Root. Menerima
// menambah satu dokumen keuangan, membatalkan menghapusnya, jadi yang boleh
// membatalkan tidak boleh sama longgarnya dengan yang boleh membuat.
$bisaBatal = in_array((int) session('ID_JABATAN'), [1], true);
?>

<div class="mutasi-stok mutasi-masuk">

    <header class="ms-head">
        <div>
            <nav class="ms-crumb" aria-label="Breadcrumb">
                <a href="<?= base_url('mutasi_stok') ?>">Mutasi Stok</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Konfirmasi Terima</span>
            </nav>
            <h1>Konfirmasi Terima</h1>
            <p>Periksa mutasi yang masuk ke unit Anda, lalu konfirmasi penerimaannya.</p>
        </div>

        <div class="ms-context">
            <iconify-icon icon="solar:buildings-2-line-duotone" aria-hidden="true"></iconify-icon>
            <span>
                <small>Unit aktif</small>
                <strong><?= esc($namaUnit !== '' ? $namaUnit : 'Unit #' . $unitAktif) ?></strong>
            </span>
        </div>
    </header>

    <?php if (session('sukses')) : ?>
        <div class="mm-flash mm-flash--ok" role="status">
            <iconify-icon icon="solar:check-circle-bold" aria-hidden="true"></iconify-icon>
            <span><?= esc(session('sukses')) ?></span>
        </div>
    <?php endif; ?>
    <?php if (session('gagal')) : ?>
        <div class="mm-flash mm-flash--danger" role="alert">
            <iconify-icon icon="solar:danger-triangle-bold" aria-hidden="true"></iconify-icon>
            <span><?= esc(session('gagal')) ?></span>
        </div>
    <?php endif; ?>

    <section class="ms-panel mm-filter" aria-label="Filter mutasi masuk">
        <form method="get" action="<?= base_url('mutasi_stok/masuk') ?>">
            <div class="mm-filter__top">
                <div class="mm-field mm-field--search">
                    <label class="ms-label" for="mm-search">Cari</label>
                    <div class="mm-input-icon">
                        <iconify-icon icon="solar:magnifer-linear" aria-hidden="true"></iconify-icon>
                        <input type="search" id="mm-search" name="search" class="form-control ms-control"
                            value="<?= esc($filter['search']) ?>"
                            placeholder="No. nota mutasi..." autocomplete="off">
                    </div>
                </div>

                <?php if ($isLintas) : ?>
                    <div class="mm-field mm-field--unit">
                        <label class="ms-label" for="mm-unit">Unit</label>
                        <select id="mm-unit" name="unit" class="form-select ms-control">
                            <option value="">Semua unit</option>
                            <?php foreach ($unit as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>" <?= (int) $filter['unit'] === (int) $u->idunit ? 'selected' : '' ?>>
                                    <?= esc($u->NAMA_UNIT) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mm-field mm-field--dates">
                    <span class="ms-label">Tanggal kirim</span>
                    <div class="mm-daterange">
                        <input type="date" name="dari" class="form-control ms-control"
                            value="<?= esc($filter['dari']) ?>" aria-label="Tanggal kirim dari">
                        <span class="mm-daterange__sep" aria-hidden="true">–</span>
                        <input type="date" name="sampai" class="form-control ms-control"
                            value="<?= esc($filter['sampai']) ?>" aria-label="Tanggal kirim sampai">
                    </div>
                </div>

                <div class="mm-field mm-field--actions">
                    <button type="submit" class="ms-btn ms-btn--primary">
                        <iconify-icon icon="solar:filter-bold" aria-hidden="true"></iconify-icon>
                        Terapkan
                    </button>
                    <?php if ($adaFilter) : ?>
                        <a href="<?= base_url('mutasi_stok/masuk') ?>" class="ms-btn ms-btn--ghost">
                            <iconify-icon icon="solar:refresh-linear" aria-hidden="true"></iconify-icon>
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mm-filter__bottom">
                <div class="mm-chips" role="group" aria-label="Filter status">
                    <?php foreach ($statusOptions as [$value, $label]) :
                        $id = 'mm-status-' . ($value === '' ? 'semua' : ($value === '0' ? 'belum' : 'sudah'));
                    ?>
                        <input class="mm-chip-input" type="radio" name="status" id="<?= $id ?>"
                            value="<?= esc($value, 'attr') ?>" <?= (string) $filter['status'] === $value ? 'checked' : '' ?>>
                        <label class="mm-chip" for="<?= $id ?>"><?= esc($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <p class="mm-filter__hint">Status langsung diterapkan.</p>
            </div>
        </form>
    </section>

    <section class="ms-panel" aria-labelledby="mm-results-title">
        <div class="ms-panel__head">
            <div class="ms-panel__title">
                <span class="ms-icon" aria-hidden="true">
                    <iconify-icon icon="solar:inbox-line-bold"></iconify-icon>
                </span>
                <div>
                    <h2 id="mm-results-title">Daftar mutasi masuk</h2>
                    <p>
                        <?php if ($adaFilter) : ?>
                            Hasil filter mutasi masuk.
                        <?php elseif ($isLintas) : ?>
                            Seluruh mutasi masuk, terbaru lebih dulu.
                        <?php else : ?>
                            Mutasi yang ditujukan ke unit Anda, terbaru lebih dulu.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <span class="mm-count"><strong><?= number_format($total, 0, ',', '.') ?></strong> mutasi</span>
        </div>

        <?php if (empty($items)) : ?>
            <div class="ms-empty">
                <span class="ms-empty__icon" aria-hidden="true">
                    <iconify-icon icon="solar:inbox-line-bold"></iconify-icon>
                </span>
                <?php if ($adaFilter) : ?>
                    <h3>Tidak ada mutasi yang cocok</h3>
                    <p>Coba longgarkan filter atau reset untuk melihat semua mutasi masuk.</p>
                    <a href="<?= base_url('mutasi_stok/masuk') ?>" class="ms-btn ms-btn--ghost">
                        <iconify-icon icon="solar:refresh-linear" aria-hidden="true"></iconify-icon>
                        Reset filter
                    </a>
                <?php else : ?>
                    <h3>Belum ada mutasi masuk</h3>
                    <p>Mutasi yang dikirim ke unit Anda akan muncul di sini setelah dikirim dari unit lain.</p>
                    <a href="<?= base_url('mutasi_stok') ?>" class="ms-btn ms-btn--ghost">
                        <iconify-icon icon="solar:routing-2-line-duotone" aria-hidden="true"></iconify-icon>
                        Buat mutasi
                    </a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="ms-table-wrap">
                <table class="ms-table mm-table">
                    <thead>
                        <tr>
                            <th scope="col">No. Nota</th>
                            <th scope="col">Tgl Kirim</th>
                            <th scope="col">Rute</th>
                            <th scope="col" class="ms-money">Nilai</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $m) : ?>
                            <tr class="mm-main">
                                <td data-label="Nota">
                                    <span class="mm-note"><?= esc($m->no_nota_mutasi) ?></span>
                                    <span class="mm-note-id">#<?= (int) $m->idmutasi ?></span>
                                </td>
                                <td data-label="Tanggal">
                                    <time class="mm-date" datetime="<?= esc($m->tanggal_kirim) ?>"><?= esc($tgl($m->tanggal_kirim)) ?></time>
                                </td>
                                <td data-label="Rute">
                                    <span class="mm-route">
                                        <span class="visually-hidden">Dari </span>
                                        <span class="mm-route__from"><?= esc($m->nama_unit_kirim) ?></span>
                                        <iconify-icon class="mm-route__arrow" icon="solar:arrow-right-linear" aria-hidden="true"></iconify-icon>
                                        <span class="visually-hidden"> ke </span>
                                        <span class="mm-route__to"><?= esc($m->nama_unit_terima) ?></span>
                                    </span>
                                </td>
                                <td class="ms-money" data-label="Nilai">
                                    <span class="mm-value"><?= $rp($m->total) ?></span>
                                </td>
                                <td data-label="Status">
                                    <?php if ($m->status === '1') : ?>
                                        <span class="mm-status mm-status--done">Diterima</span>
                                        <span class="mm-status-date"><?= esc($tgl($m->tanggal_terima)) ?></span>
                                    <?php else : ?>
                                        <span class="mm-status mm-status--pending">Belum diterima</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Aksi">
                                    <div class="mm-actions">
                                        <?php if ($m->status === '1' && $bisaBatal) : ?>
                                            <button type="button" class="ms-btn ms-btn--danger-ghost btn-batal-terima"
                                                data-bs-toggle="modal" data-bs-target="#modalBatalTerima"
                                                data-id="<?= (int) $m->idmutasi ?>"
                                                data-nota="<?= esc($m->no_nota_mutasi, 'attr') ?>"
                                                data-terima="<?= esc($m->nama_unit_terima, 'attr') ?>"
                                                data-kirim="<?= esc($m->nama_unit_kirim, 'attr') ?>"
                                                data-total="<?= (int) $m->total ?>"
                                                data-tanggal-terima="<?= esc($m->tanggal_terima ? $tgl($m->tanggal_terima) : '', 'attr') ?>">
                                                <iconify-icon icon="solar:undo-left-bold" aria-hidden="true"></iconify-icon>
                                                Batal terima
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($m->status !== '1') : ?>
                                            <button type="button" class="ms-btn ms-btn--primary btn-terima"
                                                data-bs-toggle="modal" data-bs-target="#modalTerimaMutasi"
                                                data-id="<?= (int) $m->idmutasi ?>"
                                                data-nota="<?= esc($m->no_nota_mutasi, 'attr') ?>"
                                                data-kirim="<?= esc($m->nama_unit_kirim, 'attr') ?>"
                                                data-terima="<?= esc($m->nama_unit_terima, 'attr') ?>"
                                                data-tanggal="<?= esc($tgl($m->tanggal_kirim), 'attr') ?>"
                                                data-total="<?= (int) $m->total ?>"
                                                data-detail="<?= esc($detailJson($m), 'attr') ?>">
                                                <iconify-icon icon="solar:check-circle-bold" aria-hidden="true"></iconify-icon>
                                                Terima
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="ms-btn ms-btn--ghost mm-detail-toggle"
                                            data-bs-toggle="collapse" data-bs-target="#detail-<?= (int) $m->idmutasi ?>"
                                            aria-expanded="false" aria-controls="detail-<?= (int) $m->idmutasi ?>">
                                            <iconify-icon icon="solar:eye-linear" aria-hidden="true"></iconify-icon>
                                            Detail
                                            <iconify-icon class="mm-caret" icon="solar:alt-arrow-down-linear" aria-hidden="true"></iconify-icon>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse mm-detail-row" id="detail-<?= (int) $m->idmutasi ?>">
                                <td colspan="6">
                                    <div class="mm-detail">
                                        <div class="mm-detail__head">
                                            <span>Rincian barang</span>
                                            <span><?= count($m->detail ?? []) ?> item</span>
                                        </div>
                                        <div class="mm-detail__scroll">
                                            <table class="mm-lines" aria-label="Rincian barang <?= esc($m->no_nota_mutasi, 'attr') ?>">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Barang</th>
                                                        <th scope="col" class="mm-money">Jml</th>
                                                        <th scope="col" class="mm-money">Harga</th>
                                                        <th scope="col" class="mm-money">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($m->detail)) : ?>
                                                        <tr>
                                                            <td colspan="4" class="mm-lines-empty">Tidak ada detail barang.</td>
                                                        </tr>
                                                    <?php else : ?>
                                                        <?php foreach ($m->detail as $d) : ?>
                                                            <tr>
                                                                <td><?= esc($d->nama_barang) ?></td>
                                                                <td class="mm-money"><?= (int) $d->jumlah_terima ?></td>
                                                                <td class="mm-money"><?= $rp($d->harga_mutasi) ?></td>
                                                                <td class="mm-money"><?= $rp($d->nilai ?? 0) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mm-detail__total">
                                            <span>Total</span>
                                            <strong><?= $rp($m->total) ?></strong>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mm-foot">
                <span class="mm-foot__text">
                    Menampilkan <strong><?= $dariTampil ?>–<?= $sampaiTampil ?></strong>
                    dari <strong><?= number_format($total, 0, ',', '.') ?></strong> mutasi
                </span>

                <nav aria-label="Navigasi halaman">
                    <ul class="mm-pagination">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $urlMasuk(['page' => $currentPage - 1]) ?>"
                                tabindex="-1" aria-label="Halaman sebelumnya">
                                <iconify-icon icon="solar:alt-arrow-left-linear" aria-hidden="true"></iconify-icon>
                            </a>
                        </li>
                        <?php
                        $window   = 2;
                        $start    = max(1, $currentPage - $window);
                        $end      = min($totalPages, $currentPage + $window);
                        $showHead = $start > 1;
                        $showTail = $end < $totalPages;
                        ?>
                        <?php if ($showHead) : ?>
                            <li class="page-item"><a class="page-link" href="<?= $urlMasuk(['page' => 1]) ?>">1</a></li>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++) : ?>
                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $urlMasuk(['page' => $i]) ?>" <?= $i === $currentPage ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($showTail) : ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                            <li class="page-item"><a class="page-link" href="<?= $urlMasuk(['page' => $totalPages]) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $urlMasuk(['page' => $currentPage + 1]) ?>"
                                aria-label="Halaman berikutnya">
                                <iconify-icon icon="solar:alt-arrow-right-linear" aria-hidden="true"></iconify-icon>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </section>

    <div class="modal fade" id="modalTerimaMutasi" tabindex="-1"
        aria-labelledby="modalTerimaLabel" aria-describedby="modalTerimaDesc" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form id="formTerimaMutasi" method="post" class="modal-content ms-modal">
                <input type="hidden" name="asal" value="<?= esc($asal, 'attr') ?>">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title" id="modalTerimaLabel">Terima mutasi</h2>
                        <p id="modalTerimaDesc">Periksa rincian sebelum mengonfirmasi penerimaan.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body mm-modal-body">
                    <div class="mm-receipt">
                        <div class="mm-receipt__cell">
                            <span class="mm-kicker">Dokumen</span>
                            <strong id="cm-nota">-</strong>
                            <span class="mm-receipt__meta">Dikirim <span id="cm-tanggal">-</span></span>
                        </div>
                        <div class="mm-receipt__cell">
                            <span class="mm-kicker">Rute</span>
                            <div class="mm-receipt__route">
                                <span>
                                    <small>Dari</small>
                                    <strong id="cm-kirim">-</strong>
                                </span>
                                <iconify-icon icon="solar:arrow-right-linear" aria-hidden="true"></iconify-icon>
                                <span>
                                    <small>Ke</small>
                                    <strong id="cm-terima">-</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mm-lines-wrap">
                        <table class="ms-table mm-lines-table">
                            <thead>
                                <tr>
                                    <th scope="col">Barang</th>
                                    <th scope="col" class="ms-money">Jml terima</th>
                                    <th scope="col" class="ms-money">Harga</th>
                                    <th scope="col" class="ms-money">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="cm-detail"></tbody>
                        </table>
                    </div>

                    <div class="mm-total-row">
                        <span>Total nilai mutasi</span>
                        <strong id="cm-total">-</strong>
                    </div>

                    <div class="mm-callout mm-callout--info">
                        <iconify-icon icon="solar:info-circle-bold" aria-hidden="true"></iconify-icon>
                        <p>
                            Dengan menekan <strong>Terima &amp; buat H/P</strong>, mutasi ditandai
                            <strong>Diterima</strong> dan sistem otomatis membuat
                            <strong>Hutang/Piutang antar unit</strong> (pengirim berpiutang, penerima berhutang)
                            dengan <strong>jatuh tempo <?= esc($hpDue) ?></strong> (+3 hari dari hari ini).
                            Verifikasi harga &amp; jumlah di atas sebelum mengonfirmasi.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ms-btn ms-btn--ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="ms-btn ms-btn--primary">
                        <iconify-icon icon="solar:check-circle-bold" aria-hidden="true"></iconify-icon>
                        Terima &amp; buat H/P
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($bisaBatal) : ?>
        <div class="modal fade" id="modalBatalTerima" tabindex="-1"
            aria-labelledby="modalBatalLabel" aria-describedby="modalBatalDesc" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form id="formBatalTerima" method="post" class="modal-content ms-modal" novalidate>
                    <input type="hidden" name="asal" value="<?= esc($asal, 'attr') ?>">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="modalBatalLabel">Batalkan penerimaan</h2>
                            <p id="modalBatalDesc">Mutasi kembali ke status belum diterima dan H/P dibatalkan.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body mm-modal-body">
                        <div class="mm-receipt mm-receipt--stack">
                            <div class="mm-receipt__cell">
                                <span class="mm-kicker">Dokumen</span>
                                <strong id="bcb-nota">-</strong>
                                <span class="mm-receipt__meta">Diterima pada <span id="bcb-tanggal">-</span></span>
                            </div>
                            <div class="mm-receipt__cell">
                                <span class="mm-kicker">Rute</span>
                                <div class="mm-receipt__route">
                                    <span>
                                        <small>Dari</small>
                                        <strong id="bcb-kirim">-</strong>
                                    </span>
                                    <iconify-icon icon="solar:arrow-right-linear" aria-hidden="true"></iconify-icon>
                                    <span>
                                        <small>Ke</small>
                                        <strong id="bcb-terima">-</strong>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mm-total-row mm-total-row--danger">
                            <span>Nilai dokumen dibatalkan</span>
                            <strong id="bcb-total">-</strong>
                        </div>

                        <div class="mm-callout mm-callout--danger">
                            <iconify-icon icon="solar:danger-triangle-bold" aria-hidden="true"></iconify-icon>
                            <p>
                                Menekan <strong>Batalkan penerimaan</strong> akan mengembalikan mutasi ke status
                                <strong>Belum diterima</strong> dan membatalkan <strong>Hutang/Piutang antar unit</strong>
                                yang dibuat saat penerimaan (nilai <span class="fw-semibold" id="bcb-total-inline">-</span>).
                                Dokumennya tidak dihapus fisik, tapi ditandai batal dan alasannya disimpan.
                            </p>
                        </div>

                        <div class="mm-reason">
                            <label for="alasan_batal" class="ms-label">
                                Alasan pembatalan <span aria-hidden="true">*</span>
                            </label>
                            <textarea class="form-control ms-control" id="alasan_batal" name="alasan_batal" rows="3"
                                maxlength="255" required
                                placeholder="Contoh: salah klik tombol Terima pada nota ini."></textarea>
                            <div class="ms-help" id="alasanBatalHelp">
                                Wajib diisi. Alasan ini tersimpan pada mutasi dan pada dokumen H/P yang dibatalkan.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ms-btn ms-btn--ghost" data-bs-dismiss="modal">Kembali</button>
                        <button type="submit" class="ms-btn ms-btn--danger">
                            <iconify-icon icon="solar:undo-left-bold" aria-hidden="true"></iconify-icon>
                            Batalkan penerimaan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var rp = function (n) {
        n = Number(n || 0);
        return 'Rp ' + n.toLocaleString('id-ID');
    };
    var escapeHtml = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    };
    var parseDetail = function (raw) {
        try {
            return JSON.parse(raw || '[]');
        } catch (e) {
            return [];
        }
    };
    var setText = function (id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = value == null || value === '' ? '-' : value;
        }
    };

    document.querySelectorAll('.btn-terima').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            var detail = parseDetail(btn.dataset.detail);

            setText('cm-nota', btn.dataset.nota);
            setText('cm-tanggal', btn.dataset.tanggal);
            setText('cm-kirim', btn.dataset.kirim);
            setText('cm-terima', btn.dataset.terima);
            setText('cm-total', rp(btn.dataset.total));

            var tbody = document.getElementById('cm-detail');
            tbody.innerHTML = '';
            if (!detail.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="mm-lines-empty">Tidak ada detail barang.</td></tr>';
            } else {
                detail.forEach(function (d) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + escapeHtml(d.nama) + '</td>' +
                        '<td class="ms-money">' + Number(d.jumlah || 0) + '</td>' +
                        '<td class="ms-money">' + rp(d.harga) + '</td>' +
                        '<td class="ms-money">' + rp(d.nilai) + '</td>';
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('formTerimaMutasi').setAttribute('action',
                <?= json_encode(base_url('mutasi_stok/terima/')) ?> + id);
        });
    });

    // Batal terima. Alasan wajib, dan divalidasi di sini supaya pesan
    // muncul di tempat yang diklik orang, bukan toasted dari server
    // setelah halaman sudah pindah.
    var formBatal = document.getElementById('formBatalTerima');
    if (formBatal) {
        var alasan = document.getElementById('alasan_batal');

        document.querySelectorAll('.btn-batal-terima').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var total = rp(btn.dataset.total);

                setText('bcb-nota', btn.dataset.nota);
                setText('bcb-kirim', btn.dataset.kirim);
                setText('bcb-terima', btn.dataset.terima);
                setText('bcb-tanggal', btn.dataset.tanggalTerima);
                setText('bcb-total', total);
                setText('bcb-total-inline', total);

                // Dibuka berulang tidak boleh menyisakan alasan lama:
                // membatalkan mutasi berbeda butuh alasan yang berbeda.
                alasan.value = '';
                alasan.setCustomValidity('');

                formBatal.setAttribute('action',
                    <?= json_encode(base_url('mutasi_stok/batal-terima/')) ?> + btn.dataset.id);
            });
        });

        alasan.addEventListener('input', function () {
            alasan.setCustomValidity('');
        });

        formBatal.addEventListener('submit', function (e) {
            if (alasan.value.trim() !== '') { return; }

            e.preventDefault();
            alasan.setCustomValidity('Alasan pembatalan wajib diisi.');
            alasan.reportValidity();
            alasan.focus();
        });
    }

    // Chip status langsung menyaring; kolom lain menunggu tombol Terapkan.
    document.querySelectorAll('.mm-chip-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.form) { return; }
            if (typeof input.form.requestSubmit === 'function') {
                input.form.requestSubmit();
            } else {
                input.form.submit();
            }
        });
    });
})();
</script>
