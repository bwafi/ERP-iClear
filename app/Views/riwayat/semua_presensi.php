<?php
/**
 * Riwayat Absen (admin).
 *
 * Hanya tampilan yang diubah: query, filter tanggal, dan export tetap memakai
 * data yang sama. Jam jadwal dibaca dari baris presensi lebih dulu karena absen
 * mobile tidak punya baris `jadwal_masuk`.
 */

$jam = static function ($nilai): string {
    if ($nilai === null || $nilai === '') {
        return '—';
    }
    $ts = strtotime((string) $nilai);

    return $ts === false ? '—' : date('H:i', $ts);
};

$tanggalRow = static function ($row): string {
    $nilai = $row->waktu_masuk ?: $row->created_at;
    $ts    = $nilai ? strtotime((string) $nilai) : false;

    return $ts === false ? '' : date('d-m-Y', $ts);
};

$isoRow = static function ($row): string {
    $nilai = $row->waktu_masuk ?: $row->created_at;
    $ts    = $nilai ? strtotime((string) $nilai) : false;

    return $ts === false ? '' : date('Y-m-d H:i:s', $ts);
};

$kehadiran = [
    0 => ['Tepat waktu', 'ok'],
    1 => ['Toleransi', 'warn'],
    2 => ['Telat', 'bad'],
];
?>

<div class="ra-page">
    <header class="ra-hero">
        <div>
            <nav class="ra-crumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li><span aria-current="page">Riwayat Absen</span></li>
                </ol>
            </nav>
            <h1>Riwayat Absen</h1>
            <p>Seluruh catatan kehadiran pegawai dari absen mobile dan presensi lama. Buka detail untuk melihat foto, lokasi, dan jejak absen.</p>
        </div>
        <div class="ra-hero__aside">
            <button type="submit" form="ra-filter-form" class="ra-btn ra-btn--primary">
                <iconify-icon icon="solar:export-broken" width="18" height="18" aria-hidden="true"></iconify-icon>
                Export Excel
            </button>
        </div>
    </header>

    <section class="ra-board" aria-label="Ringkasan kehadiran">
        <div class="ra-board__cell">
            <div class="ra-board__label">Total baris</div>
            <div class="ra-board__value" data-ra-count="total">—</div>
        </div>
        <div class="ra-board__cell">
            <div class="ra-board__label">Tepat waktu</div>
            <div class="ra-board__value is-ok" data-ra-count="tepat">—</div>
        </div>
        <div class="ra-board__cell">
            <div class="ra-board__label">Toleransi</div>
            <div class="ra-board__value is-warn" data-ra-count="toleransi">—</div>
        </div>
        <div class="ra-board__cell">
            <div class="ra-board__label">Telat</div>
            <div class="ra-board__value is-bad" data-ra-count="telat">—</div>
        </div>
        <div class="ra-board__cell">
            <div class="ra-board__label">Belum konfirmasi</div>
            <div class="ra-board__value" data-ra-count="belum">—</div>
        </div>
    </section>

    <form id="ra-filter-form" class="ra-card ra-filter" action="<?= base_url('export/semua_presensi') ?>" method="post" autocomplete="off">
        <div class="ra-filter__fields">
            <div class="ra-field">
                <label for="startDate">Dari tanggal</label>
                <input name="tanggal_awal" type="date" id="startDate" class="ra-control">
            </div>
            <div class="ra-field">
                <label for="endDate">Sampai tanggal</label>
                <input name="tanggal_akhir" type="date" id="endDate" class="ra-control">
            </div>
            <button type="button" id="ra-reset" class="ra-btn ra-btn--ghost">Reset</button>
        </div>
        <p class="ra-filter__hint">
            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
            Filter bawaan 15 hari terakhir. Export mengikuti tanggal yang dipilih.
        </p>
    </form>

    <section class="ra-card ra-table-card">
        <div class="ra-table-card__head">
            <div>
                <h2>Log absensi</h2>
                <p>Status kehadiran dihitung saat absen; status presensi menandai absen yang sudah final.</p>
            </div>
        </div>

        <div class="ra-table-wrap">
            <table class="ra-table" id="riwayat-absen-table">
                <thead>
                    <tr>
                        <th scope="col">Pegawai</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Jadwal</th>
                        <th scope="col">Masuk</th>
                        <th scope="col">Pulang</th>
                        <th scope="col">Kehadiran</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presensi as $row): ?>
                        <?php
                        $tgl        = $tanggalRow($row);
                        $iso        = $isoRow($row);
                        $jadwal     = $jam($row->jam_jadwal_masuk ?: $row->jmmasuk);
                        $toleransi  = $jam($row->jam_toleransi ?: $row->toleransi);
                        $masuk      = $jam($row->waktu_masuk);
                        $pulang     = $jam($row->waktu_pulang);
                        $kehadiranV = $row->status_kehadiran;
                        $statusV    = $row->status_absensi;
                        $tanggalIso = substr((string) ($row->waktu_masuk ?: $row->created_at), 0, 10);
                        $kunciSrg   = (int) $row->akun_idakun . '|' . $tanggalIso;
                        $srg        = $seragam[$kunciSrg] ?? ['score' => 0, 'seragam' => false, 'sepatu' => false, 'idcard' => false];
                        $bolehSrg   = !empty($bolehSeragam[$kunciSrg]);
                        ?>
                        <tr data-kehadiran="<?= $kehadiranV === null ? '' : (int) $kehadiranV ?>"
                            data-status="<?= $statusV === null ? '' : (int) $statusV ?>">
                            <td data-label="Pegawai">
                                <span class="ra-emp">
                                    <span class="ra-emp__name"><?= esc($row->NAMA_AKUN ?? 'Tanpa nama') ?></span>
                                    <span class="ra-emp__unit"><?= esc($row->NAMA_UNIT ?? 'Tanpa unit') ?></span>
                                </span>
                            </td>
                            <td data-label="Tanggal" data-order="<?= esc($iso, 'attr') ?>">
                                <span class="ra-time"><?= esc($tgl) ?></span>
                            </td>
                            <td data-label="Jadwal">
                                <span class="ra-time__stack">
                                    <span class="ra-time"><?= esc($jadwal) ?></span>
                                    <span class="ra-time__note">toleransi <?= esc($toleransi) ?></span>
                                </span>
                            </td>
                            <td data-label="Masuk">
                                <span class="ra-time"><?= esc($masuk) ?></span>
                            </td>
                            <td data-label="Pulang">
                                <span class="ra-time<?= $pulang === '—' ? ' is-empty' : '' ?>"><?= esc($pulang) ?></span>
                            </td>
                            <td data-label="Kehadiran">
                                <?php if (isset($kehadiran[$kehadiranV])): ?>
                                    <span class="ra-tag ra-tag--<?= $kehadiran[$kehadiranV][1] ?>"><?= esc($kehadiran[$kehadiranV][0]) ?></span>
                                <?php else: ?>
                                    <span class="ra-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php if ((int) $statusV === 0): ?>
                                    <span class="ra-tag ra-tag--warn">Belum konfirmasi</span>
                                <?php elseif ((int) $statusV === 1): ?>
                                    <span class="ra-tag ra-tag--accent">Terkonfirmasi</span>
                                <?php else: ?>
                                    <span class="ra-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi">
                                <button type="button"
                                    class="ra-action js-ra-detail"
                                    data-bs-toggle="modal"
                                    data-bs-target="#ra-modal-detail"
                                    data-nama="<?= esc($row->NAMA_AKUN ?? '', 'attr') ?>"
                                    data-unit="<?= esc($row->NAMA_UNIT ?? '', 'attr') ?>"
                                    data-tanggal="<?= esc($tgl, 'attr') ?>"
                                    data-waktumasuk="<?= esc((string) $row->waktu_masuk, 'attr') ?>"
                                    data-waktupulang="<?= esc((string) $row->waktu_pulang, 'attr') ?>"
                                    data-jadwalmasuk="<?= esc($jadwal, 'attr') ?>"
                                    data-jadwalpulang="<?= esc($jam($row->jam_jadwal_pulang), 'attr') ?>"
                                    data-toleransi="<?= esc($toleransi, 'attr') ?>"
                                    data-ip="<?= esc((string) $row->ip, 'attr') ?>"
                                    data-lat="<?= esc((string) $row->lat, 'attr') ?>"
                                    data-long="<?= esc((string) $row->long, 'attr') ?>"
                                    data-latkantor="<?= esc((string) $row->LATITUDE, 'attr') ?>"
                                    data-longkantor="<?= esc((string) $row->LONGTITUDE, 'attr') ?>"
                                    data-jarak="<?= esc((string) $row->jarak, 'attr') ?>"
                                    data-idpresensi="<?= (int) $row->idpresensi ?>"
                                    data-sumber="<?= esc((string) $row->sumber, 'attr') ?>"
                                    data-foto="<?= esc((string) $row->foto, 'attr') ?>"
                                    data-fotopulang="<?= esc((string) $row->foto_pulang, 'attr') ?>"
                                    data-kehadiran="<?= $kehadiranV === null ? '' : (int) $kehadiranV ?>"
                                    data-status="<?= $statusV === null ? '' : (int) $statusV ?>"
                                    data-akun="<?= (int) $row->akun_idakun ?>"
                                    data-tanggal-iso="<?= esc($tanggalIso, 'attr') ?>"
                                    data-seragam-boleh="<?= $bolehSrg ? '1' : '0' ?>"
                                    data-seragam-nilai="<?= esc((string) $srg['score'], 'attr') ?>"
                                    data-seragam-s="<?= $srg['seragam'] ? '1' : '0' ?>"
                                    data-seragam-p="<?= $srg['sepatu'] ? '1' : '0' ?>"
                                    data-seragam-i="<?= $srg['idcard'] ? '1' : '0' ?>"
                                    data-seragam-manual="<?= !empty($srg['manual']) ? '1' : '0' ?>">
                                    <iconify-icon icon="solar:magnifer-zoom-in-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
                                    Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Detail absensi -->
    <div class="modal fade ra-modal" id="ra-modal-detail" tabindex="-1" aria-labelledby="ra-detail-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="ra-detail__identity">
                        <span class="ra-detail__avatar" aria-hidden="true"><iconify-icon icon="solar:user-rounded-linear" width="22" height="22"></iconify-icon></span>
                        <div>
                            <h5 class="modal-title" id="ra-detail-title"><span id="ra-detail-nama">Detail absensi</span></h5>
                            <p class="ra-detail__sub"><span id="ra-detail-unit">—</span> · <span id="ra-detail-tanggal">—</span></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="ra-map" id="ra-map"></div>
                        </div>
                        <div class="col-lg-5">
                            <div class="ra-detail__tags" id="ra-detail-tags"></div>
                            <dl class="ra-meta" id="ra-detail-meta"></dl>

                            <div class="ra-seragam" id="ra-seragam">
                                <h6 class="fw-semibold mb-2">Penilaian Seragam</h6>
                                <form method="post" action="<?= base_url('riwayat_presensi/seragam') ?>" id="ra-seragam-form" class="d-none">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="employee_id" id="ra-seragam-akun">
                                    <input type="hidden" name="tanggal" id="ra-seragam-tanggal">
                                    <input type="hidden" name="timpa_manual" id="ra-seragam-timpa" value="">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="seragam" value="1" id="ra-seragam-s">
                                        <label class="form-check-label" for="ra-seragam-s">Seragam sesuai <span class="text-muted">(3)</span></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sepatu" value="1" id="ra-seragam-p">
                                        <label class="form-check-label" for="ra-seragam-p">Sepatu sesuai <span class="text-muted">(1)</span></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="idcard" value="1" id="ra-seragam-i">
                                        <label class="form-check-label" for="ra-seragam-i">ID Card sesuai <span class="text-muted">(1)</span></label>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-2">
                                        <button type="submit" class="btn btn-sm btn-primary">Simpan nilai</button>
                                        <span class="text-muted small" id="ra-seragam-hasil">0/5</span>
                                    </div>
                                </form>
                                <p class="text-muted small mb-0" id="ra-seragam-info"></p>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 d-none" id="ra-seragam-timpa-btn">
                                    Nilai ulang dari foto
                                </button>
                            </div>

                            <div class="ra-photos" id="ra-detail-photos"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Penampil foto -->
    <div class="modal fade ra-viewer" id="ra-modal-foto" tabindex="-1" aria-labelledby="ra-foto-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ra-foto-title">Foto absensi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="ra-viewer__stage">
                        <img id="ra-zoom-image" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="Foto absensi diperbesar">
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="ra-viewer__controls" role="group" aria-label="Geser foto">
                        <button type="button" class="ra-viewer__btn" onclick="raPan(0, -24)" aria-label="Geser ke atas">↑</button>
                        <button type="button" class="ra-viewer__btn" onclick="raPan(-24, 0)" aria-label="Geser ke kiri">←</button>
                        <button type="button" class="ra-viewer__btn" onclick="raPan(24, 0)" aria-label="Geser ke kanan">→</button>
                        <button type="button" class="ra-viewer__btn" onclick="raPan(0, 24)" aria-label="Geser ke bawah">↓</button>
                    </div>
                    <div class="ra-viewer__controls" role="group" aria-label="Zoom foto">
                        <button type="button" class="ra-viewer__btn" onclick="raZoom(-0.15)" aria-label="Perkecil">−</button>
                        <button type="button" class="ra-viewer__btn" onclick="raZoom(0.15)" aria-label="Perbesar">+</button>
                    </div>
                    <button type="button" class="ra-viewer__btn ra-viewer__btn--close" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .ra-page {
        --ra-surface: var(--bs-card-bg, #fff);
        --ra-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --ra-rule: var(--bs-border-color, #e6ecf1);
        --ra-muted: #5b6672;
        --ra-ink: #3d4a58;
        --ra-accent-ink: #0b5f8a;
        --ra-accent-solid: #0b5f8a;
        --ra-accent-soft: rgba(11, 95, 138, .08);
        --ra-ok-ink: #0f6b3e;
        --ra-ok-soft: rgba(15, 122, 70, .10);
        --ra-warn-ink: #8a5300;
        --ra-warn-soft: rgba(192, 118, 0, .13);
        --ra-bad-ink: #b02a37;
        --ra-bad-soft: rgba(176, 42, 55, .10);
        --ra-radius: 16px;
        --ra-radius-sm: 10px;
        --ra-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -32px rgba(16, 24, 40, .35);
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--ra-ink);
    }

    html[data-bs-theme="dark"] .ra-page {
        --ra-surface: var(--bs-card-bg, #1f2937);
        --ra-subtle: rgba(255, 255, 255, .04);
        --ra-muted: #8fa0b8;
        --ra-ink: var(--bs-body-color, #cfd8e3);
        --ra-accent-ink: #7cc4f0;
        --ra-accent-soft: rgba(124, 196, 240, .12);
        --ra-ok-ink: #6fdd9d;
        --ra-ok-soft: rgba(111, 221, 157, .12);
        --ra-warn-ink: #ffcf6e;
        --ra-warn-soft: rgba(255, 207, 110, .12);
        --ra-bad-ink: #ff9aa2;
        --ra-bad-soft: rgba(255, 154, 162, .12);
        --ra-shadow: none;
    }

    .ra-page h1,
    .ra-page h2,
    .ra-page h5 {
        color: var(--ra-ink);
    }

    /* ===== Hero ===== */
    .ra-page .ra-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 20px;
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: var(--ra-radius);
        box-shadow: var(--ra-shadow);
    }

    .ra-page .ra-crumb {
        margin-bottom: 10px;
        color: var(--ra-muted);
        font-size: .72rem;
    }

    .ra-page .ra-crumb ol {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .ra-page .ra-crumb li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .ra-page .ra-crumb li + li::before {
        content: "/";
        color: var(--ra-rule);
    }

    .ra-page .ra-crumb a {
        color: var(--ra-muted);
        text-decoration: none;
    }

    .ra-page .ra-crumb a:hover {
        color: var(--ra-accent-ink);
    }

    .ra-page .ra-hero h1 {
        margin: 0 0 6px;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -.035em;
    }

    .ra-page .ra-hero p {
        max-width: 62ch;
        margin: 0;
        color: var(--ra-muted);
        font-size: .86rem;
        line-height: 1.55;
        text-wrap: pretty;
    }

    .ra-page .ra-hero__aside {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    /* ===== Tombol & kontrol ===== */
    .ra-page .ra-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 13px;
        border: 1px solid transparent;
        border-radius: var(--ra-radius-sm);
        font-family: inherit;
        font-size: .78rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .ra-page .ra-btn--primary {
        color: #fff;
        background: var(--ra-accent-solid);
        border-color: var(--ra-accent-solid);
    }

    .ra-page .ra-btn--primary:hover,
    .ra-page .ra-btn--primary:focus {
        color: #fff;
        background: #09506f;
        border-color: #09506f;
    }

    .ra-page .ra-btn--ghost {
        color: var(--ra-ink);
        background: transparent;
        border-color: var(--ra-rule);
    }

    .ra-page .ra-btn--ghost:hover,
    .ra-page .ra-btn--ghost:focus {
        color: var(--ra-accent-ink);
        border-color: var(--ra-accent-ink);
        background: var(--ra-accent-soft);
    }

    .ra-page .ra-control {
        width: 100%;
        height: 38px;
        padding: 7px 10px;
        color: var(--ra-ink);
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: var(--ra-radius-sm);
        font-family: inherit;
        font-size: .8rem;
    }

    html[data-bs-theme="dark"] .ra-page .ra-control {
        color-scheme: dark;
    }

    .ra-page .ra-control:focus {
        outline: none;
        border-color: var(--ra-accent-ink);
        box-shadow: 0 0 0 3px var(--ra-accent-soft);
    }

    .ra-page a:focus-visible,
    .ra-page button:focus-visible {
        outline: 2px solid var(--ra-accent-ink);
        outline-offset: 2px;
    }

    /* ===== Ringkasan ===== */
    .ra-page .ra-board {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .ra-page .ra-board__cell {
        min-width: 0;
        padding: 14px 16px;
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: var(--ra-radius-sm);
    }

    .ra-page .ra-board__label {
        margin-bottom: 6px;
        color: var(--ra-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .ra-page .ra-board__value {
        color: var(--ra-ink);
        font-size: 1.5rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.05;
    }

    .ra-page .ra-board__value.is-ok {
        color: var(--ra-ok-ink);
    }

    .ra-page .ra-board__value.is-warn {
        color: var(--ra-warn-ink);
    }

    .ra-page .ra-board__value.is-bad {
        color: var(--ra-bad-ink);
    }

    /* ===== Kartu & filter ===== */
    .ra-page .ra-card {
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: var(--ra-radius);
        box-shadow: var(--ra-shadow);
    }

    .ra-page .ra-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px 16px;
        padding: 14px 18px;
    }

    .ra-page .ra-filter__fields {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .ra-page .ra-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .ra-page .ra-field label {
        color: var(--ra-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .ra-page .ra-filter__hint {
        display: flex;
        flex: 1 1 240px;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
        margin: 0;
        color: var(--ra-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .ra-page .ra-filter__hint iconify-icon {
        flex: none;
        font-size: 16px;
    }

    /* ===== Tabel ===== */
    .ra-page .ra-table-card {
        overflow: hidden;
    }

    .ra-page .ra-table-card__head {
        padding: 16px 18px 12px;
    }

    .ra-page .ra-table-card__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .ra-page .ra-table-card__head p {
        margin: 4px 0 0;
        color: var(--ra-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .ra-page .ra-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .ra-page .ra-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--ra-ink);
        font-size: .8rem;
    }

    .ra-page .ra-table > thead > tr > th {
        padding: 10px 14px;
        color: var(--ra-muted);
        background: var(--ra-subtle);
        border-top: 1px solid var(--ra-rule);
        border-bottom: 1px solid var(--ra-rule);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ra-page .ra-table > tbody > tr > td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--ra-rule);
        vertical-align: middle;
    }

    .ra-page .ra-table > tbody > tr:last-child > td {
        border-bottom: 0;
    }

    .ra-page .ra-table > tbody > tr:hover > td {
        background: var(--ra-subtle);
    }

    .ra-page .ra-emp {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ra-page .ra-emp__name {
        color: var(--ra-ink);
        font-weight: 650;
    }

    .ra-page .ra-emp__unit {
        color: var(--ra-muted);
        font-size: .69rem;
    }

    .ra-page .ra-time {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        white-space: nowrap;
    }

    .ra-page .ra-time.is-empty,
    .ra-page .ra-muted {
        color: var(--ra-muted);
        font-weight: 500;
    }

    .ra-page .ra-time__note {
        display: block;
        margin-top: 2px;
        color: var(--ra-muted);
        font-size: .68rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .ra-page .ra-time__stack {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .ra-page .ra-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .69rem;
        font-weight: 650;
        line-height: 1.25;
        white-space: nowrap;
    }

    .ra-page .ra-tag::before {
        content: "";
        flex: none;
        width: 6px;
        height: 6px;
        background: currentColor;
        border-radius: 50%;
        opacity: .9;
    }

    .ra-page .ra-tag--ok {
        color: var(--ra-ok-ink);
        background: var(--ra-ok-soft);
    }

    .ra-page .ra-tag--warn {
        color: var(--ra-warn-ink);
        background: var(--ra-warn-soft);
    }

    .ra-page .ra-tag--bad {
        color: var(--ra-bad-ink);
        background: var(--ra-bad-soft);
    }

    .ra-page .ra-tag--accent {
        color: var(--ra-accent-ink);
        background: var(--ra-accent-soft);
    }

    .ra-page .ra-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        color: var(--ra-accent-ink);
        background: transparent;
        border: 1px solid var(--ra-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .72rem;
        font-weight: 650;
        line-height: 1.2;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .ra-page .ra-action:hover,
    .ra-page .ra-action:focus {
        color: var(--ra-accent-ink);
        background: var(--ra-accent-soft);
        border-color: var(--ra-accent-ink);
    }

    /* ===== Kontrol DataTables ===== */
    .ra-page .ra-dt-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--ra-rule);
    }

    .ra-page .dataTables_length label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--ra-muted);
        font-size: .73rem;
        font-weight: 600;
    }

    .ra-page .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
        color: var(--ra-ink);
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .75rem;
    }

    .ra-page .dataTables_filter label {
        margin: 0;
    }

    .ra-page .dataTables_filter input {
        width: 230px;
        height: 34px;
        padding: 6px 10px;
        color: var(--ra-ink);
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .77rem;
    }

    .ra-page .dataTables_filter input::placeholder {
        color: var(--ra-muted);
        opacity: .85;
    }

    .ra-page .dataTables_filter input:focus {
        outline: none;
        border-color: var(--ra-accent-ink);
        box-shadow: 0 0 0 3px var(--ra-accent-soft);
    }

    .ra-page .ra-dt-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--ra-rule);
    }

    .ra-page .dataTables_info {
        padding: 0;
        color: var(--ra-muted);
        font-size: .73rem;
    }

    .ra-page .dataTables_paginate {
        padding: 0;
    }

    .ra-page .dataTables_paginate .pagination {
        gap: 4px;
        margin: 0;
    }

    .ra-page .dataTables_paginate .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        color: var(--ra-ink);
        background: transparent;
        border: 1px solid var(--ra-rule);
        border-radius: 8px;
        font-size: .73rem;
        font-weight: 600;
    }

    .ra-page .dataTables_paginate .page-link:hover {
        color: var(--ra-accent-ink);
        background: var(--ra-accent-soft);
        border-color: var(--ra-accent-ink);
    }

    .ra-page .dataTables_paginate .active .page-link {
        color: #fff;
        background: var(--ra-accent-solid);
        border-color: var(--ra-accent-solid);
    }

    .ra-page .dataTables_paginate .disabled .page-link {
        opacity: .45;
        pointer-events: none;
    }

    .ra-page .dataTables_empty {
        color: var(--ra-muted);
        text-align: center;
        padding: 28px 14px;
    }

    /* ===== Modal detail ===== */
    .ra-page .modal-content {
        color: var(--ra-ink);
        background: var(--ra-surface);
        border: 1px solid var(--ra-rule);
        border-radius: 18px;
    }

    .ra-page .modal-header {
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
        border-bottom: 1px solid var(--ra-rule);
    }

    .ra-page .modal-body {
        padding: 18px;
    }

    .ra-page .ra-detail__identity {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .ra-page .ra-detail__avatar {
        display: grid;
        flex: none;
        width: 42px;
        height: 42px;
        place-items: center;
        color: var(--ra-accent-ink);
        background: var(--ra-accent-soft);
        border-radius: 12px;
    }

    .ra-page .ra-detail__identity .modal-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .ra-page .ra-detail__sub {
        margin: 3px 0 0;
        color: var(--ra-muted);
        font-size: .75rem;
    }

    .ra-page .ra-map {
        height: 420px;
        overflow: hidden;
        background: var(--ra-subtle);
        border: 1px solid var(--ra-rule);
        border-radius: var(--ra-radius-sm);
    }

    .ra-page .ra-map__empty {
        display: grid;
        height: 100%;
        margin: 0;
        padding: 24px;
        place-items: center;
        color: var(--ra-muted);
        text-align: center;
        font-size: .82rem;
    }

    .ra-page .ra-detail__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    .ra-page .ra-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin: 0 0 14px;
    }

    .ra-page .ra-meta__item {
        min-width: 0;
        padding: 9px 11px;
        background: var(--ra-subtle);
        border-radius: 9px;
    }

    .ra-page .ra-meta__item dt {
        margin: 0 0 2px;
        color: var(--ra-muted);
        font-size: .63rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ra-page .ra-meta__item dd {
        margin: 0;
        color: var(--ra-ink);
        font-size: .78rem;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .ra-page .ra-photos {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .ra-page .ra-photo {
        margin: 0;
    }

    .ra-page .ra-photo figcaption {
        margin-bottom: 5px;
        color: var(--ra-muted);
        font-size: .63rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ra-page .ra-photo img,
    .ra-page .ra-photo__empty {
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        background: var(--ra-subtle);
        border: 1px solid var(--ra-rule);
        border-radius: 9px;
    }

    .ra-page .ra-photo img {
        object-fit: cover;
        cursor: zoom-in;
    }

    .ra-page .ra-photo__empty {
        display: grid;
        place-items: center;
        color: var(--ra-muted);
        font-size: .7rem;
    }

    /* ===== Penampil foto ===== */
    .ra-page .ra-viewer .modal-content {
        color: #f5f7fa;
        background: #0d1117;
        border-color: #232b36;
    }

    .ra-page .ra-viewer .modal-header,
    .ra-page .ra-viewer .modal-footer {
        border-color: #232b36;
    }

    .ra-page .ra-viewer .modal-title {
        font-size: .9rem;
        font-weight: 650;
        color: #f5f7fa;
    }

    .ra-page .ra-viewer__stage {
        overflow: hidden;
        text-align: center;
    }

    .ra-page .ra-viewer__stage img {
        max-width: 100%;
        max-height: 68vh;
        transition: transform .2s ease;
        transform-origin: center;
    }

    .ra-page .ra-viewer .modal-footer {
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ra-page .ra-viewer__controls {
        display: inline-flex;
        gap: 4px;
    }

    .ra-page .ra-viewer__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        color: #e6edf3;
        background: #161b22;
        border: 1px solid #30363d;
        border-radius: 8px;
        font-family: inherit;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
    }

    .ra-page .ra-viewer__btn:hover,
    .ra-page .ra-viewer__btn:focus {
        color: #fff;
        background: #21262d;
        border-color: #8b949e;
    }

    .ra-page .ra-viewer__btn--close {
        color: #0d1117;
        background: #f5f7fa;
        border-color: #f5f7fa;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .ra-page .ra-board {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ra-page .ra-filter__hint {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .ra-page .ra-hero h1 {
            font-size: 1.35rem;
        }

        .ra-page .ra-hero__aside,
        .ra-page .ra-hero__aside .ra-btn {
            width: 100%;
        }

        .ra-page .ra-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ra-page .ra-field {
            flex: 1 1 100%;
        }

        .ra-page .ra-filter__fields {
            width: 100%;
        }

        .ra-page .ra-filter__fields .ra-btn {
            width: 100%;
        }

        .ra-page .ra-dt-toolbar,
        .ra-page .ra-dt-foot {
            flex-direction: column;
            align-items: stretch;
        }

        .ra-page .dataTables_filter input {
            width: 100%;
        }

        .ra-page .ra-meta {
            grid-template-columns: 1fr;
        }

        .ra-page .ra-map {
            height: 280px;
        }
    }

    /* Tabel menjadi kartu per baris di ponsel. */
    @media (max-width: 767.98px) {
        .ra-page .ra-table-wrap {
            overflow: visible;
        }

        .ra-page .ra-table > thead {
            display: none;
        }

        .ra-page .ra-table,
        .ra-page .ra-table > tbody,
        .ra-page .ra-table > tbody > tr,
        .ra-page .ra-table > tbody > tr > td {
            display: block;
            width: 100%;
        }

        .ra-page .ra-table > tbody > tr {
            padding: 12px 16px;
            border-top: 1px solid var(--ra-rule);
        }

        .ra-page .ra-table > tbody > tr:first-child {
            border-top: 0;
        }

        .ra-page .ra-table > tbody > tr > td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
            border: 0;
            text-align: right;
        }

        .ra-page .ra-table > tbody > tr > td::before {
            content: attr(data-label);
            flex: none;
            color: var(--ra-muted);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .ra-page .ra-table > tbody > tr > td:first-child {
            padding-top: 0;
            text-align: left;
        }

        .ra-page .ra-table > tbody > tr > td:first-child::before,
        .ra-page .ra-table > tbody > tr > td:last-child::before {
            display: none;
        }

        .ra-page .ra-table > tbody > tr > td:last-child {
            justify-content: flex-end;
            padding-top: 10px;
        }

        .ra-page .ra-time__stack {
            align-items: flex-end;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ra-page *,
        .ra-page *::before,
        .ra-page *::after {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
        }
    }

    /* Tombol kustomisasi template menutupi kolom aksi di kanan bawah. */
    .customizer-btn {
        display: none !important;
    }
</style>

<script>
    (function () {
        const baseFotoLegacy = "<?= base_url('foto_presensi/') ?>";
        const baseFotoMobile = "<?= base_url('absen/foto/') ?>";
        const labelKehadiran = { '0': 'Tepat waktu', '1': 'Toleransi', '2': 'Telat' };
        const labelStatus = { '0': 'Belum konfirmasi', '1': 'Terkonfirmasi' };

        const startInput = document.getElementById('startDate');
        const endInput = document.getElementById('endDate');
        let table = null;

        const esc = function (value) {
            return String(value === null || value === undefined || value === '' ? '—' : value).replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        };

        const tanggalInput = function (date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        };

        $(function () {
            const today = new Date();
            const fifteenDaysAgo = new Date();
            fifteenDaysAgo.setDate(today.getDate() - 15);
            startInput.value = tanggalInput(fifteenDaysAgo);
            endInput.value = tanggalInput(today);

            // Tanggal dibaca dari data-order ISO di sel, bukan teks dd-mm-yyyy:
            // DataTables mengirim sel ber-data-order sebagai objek, bukan string.
            $.fn.dataTable.ext.search.push(function (settings, data, index) {
                const tr = settings.aoData[index] && settings.aoData[index].nTr;
                const iso = tr && tr.cells[1] ? tr.cells[1].getAttribute('data-order') : '';
                if (!iso) {
                    return true;
                }
                const rowDate = new Date(iso.slice(0, 10));
                const start = startInput.value ? new Date(startInput.value) : null;
                const end = endInput.value ? new Date(endInput.value) : null;
                if (start && rowDate < start) {
                    return false;
                }
                if (end && rowDate > end) {
                    return false;
                }
                return true;
            });

            table = $('#riwayat-absen-table').DataTable({
                order: [[1, 'desc']],
                pageLength: 15,
                lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, 'Semua']],
                columnDefs: [{ targets: 7, orderable: false, searchable: false }],
                dom: "<'ra-dt-toolbar'<'ra-dt-len'l><'ra-dt-search'f>>t<'ra-dt-foot'<'ra-dt-info'i><'ra-dt-page'p>>",
                language: {
                    search: '',
                    searchPlaceholder: 'Cari nama pegawai…',
                    lengthMenu: 'Tampil _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                    infoEmpty: 'Tidak ada baris',
                    infoFiltered: '(dari _MAX_ total baris)',
                    zeroRecords: 'Tidak ada absensi yang cocok dengan filter.',
                    emptyTable: 'Belum ada data absensi.',
                    paginate: { previous: '‹', next: '›' }
                }
            });

            startInput.addEventListener('change', function () { table.draw(); });
            endInput.addEventListener('change', function () { table.draw(); });

            table.table().container().querySelector('.dataTables_filter input')
                .setAttribute('aria-label', 'Cari pegawai');
            table.table().container().querySelector('.dataTables_length select')
                .setAttribute('aria-label', 'Jumlah baris per halaman');

            document.getElementById('ra-reset').addEventListener('click', function () {
                startInput.value = '';
                endInput.value = '';
                table.search('').draw();
            });

            perbaruiRingkasan();
            table.on('draw', perbaruiRingkasan);
        });

        function perbaruiRingkasan() {
            if (!table) {
                return;
            }
            const nodes = table.rows({ search: 'applied' }).nodes().toArray();
            const hitung = { total: nodes.length, tepat: 0, toleransi: 0, telat: 0, belum: 0 };

            nodes.forEach(function (node) {
                const kehadiran = node.getAttribute('data-kehadiran');
                const status = node.getAttribute('data-status');
                if (kehadiran === '0') { hitung.tepat++; }
                else if (kehadiran === '1') { hitung.toleransi++; }
                else if (kehadiran === '2') { hitung.telat++; }
                if (status === '0') { hitung.belum++; }
            });

            Object.keys(hitung).forEach(function (key) {
                const el = document.querySelector('[data-ra-count="' + key + '"]');
                if (el) {
                    el.textContent = hitung[key];
                }
            });
        }

        function urlFoto(data, pulang) {
            const path = pulang ? data.fotoPulang : data.foto;
            if (!path) {
                return '';
            }
            if (data.sumber === 'mobile') {
                return baseFotoMobile + data.idpresensi + (pulang ? '/pulang' : '');
            }
            return baseFotoLegacy + path;
        }

        function metaItem(label, value) {
            return '<div class="ra-meta__item"><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>';
        }

        function fotoItem(label, src) {
            if (!src) {
                return '<figure class="ra-photo"><figcaption>' + esc(label) + '</figcaption><div class="ra-photo__empty">Tidak ada foto</div></figure>';
            }
            return '<figure class="ra-photo"><figcaption>' + esc(label) + '</figcaption>' +
                '<img class="ra-photo__img" src="' + esc(src) + '" alt="' + esc(label) + '" loading="lazy"></figure>';
        }

        let detailAktif = null;

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-ra-detail');
            if (!btn) {
                return;
            }

            const data = {
                nama: btn.getAttribute('data-nama'),
                unit: btn.getAttribute('data-unit'),
                tanggal: btn.getAttribute('data-tanggal'),
                waktuMasuk: btn.getAttribute('data-waktumasuk'),
                waktuPulang: btn.getAttribute('data-waktupulang'),
                jadwalMasuk: btn.getAttribute('data-jadwalmasuk'),
                jadwalPulang: btn.getAttribute('data-jadwalpulang'),
                toleransi: btn.getAttribute('data-toleransi'),
                ip: btn.getAttribute('data-ip'),
                lat: btn.getAttribute('data-lat'),
                long: btn.getAttribute('data-long'),
                latKantor: btn.getAttribute('data-latkantor'),
                longKantor: btn.getAttribute('data-longkantor'),
                jarak: btn.getAttribute('data-jarak'),
                idpresensi: btn.getAttribute('data-idpresensi'),
                sumber: btn.getAttribute('data-sumber'),
                foto: btn.getAttribute('data-foto'),
                fotoPulang: btn.getAttribute('data-fotopulang'),
                kehadiran: btn.getAttribute('data-kehadiran'),
                status: btn.getAttribute('data-status'),
                akun: btn.getAttribute('data-akun'),
                tanggalIso: btn.getAttribute('data-tanggal-iso'),
                seragamBoleh: btn.getAttribute('data-seragam-boleh') === '1',
                seragamNilai: btn.getAttribute('data-seragam-nilai') || '0',
                seragamS: btn.getAttribute('data-seragam-s') === '1',
                seragamP: btn.getAttribute('data-seragam-p') === '1',
                seragamI: btn.getAttribute('data-seragam-i') === '1',
                seragamManual: btn.getAttribute('data-seragam-manual') === '1'
            };

            const masuk = data.waktuMasuk ? new Date(data.waktuMasuk) : null;
            const pulang = data.waktuPulang ? new Date(data.waktuPulang) : null;
            const formatJam = function (date) {
                if (!date) {
                    return '—';
                }
                return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
            };

            document.getElementById('ra-detail-nama').textContent = data.nama || 'Tanpa nama';
            document.getElementById('ra-detail-unit').textContent = data.unit || 'Tanpa unit';
            document.getElementById('ra-detail-tanggal').textContent = data.tanggal || '—';

            let tags = '';
            if (labelKehadiran[data.kehadiran]) {
                const warna = data.kehadiran === '0' ? 'ok' : (data.kehadiran === '1' ? 'warn' : 'bad');
                tags += '<span class="ra-tag ra-tag--' + warna + '">' + esc(labelKehadiran[data.kehadiran]) + '</span>';
            }
            if (labelStatus[data.status]) {
                tags += '<span class="ra-tag ' + (data.status === '1' ? 'ra-tag--accent' : 'ra-tag--warn') + '">' + esc(labelStatus[data.status]) + '</span>';
            }
            document.getElementById('ra-detail-tags').innerHTML = tags;

            const koordinat = (data.lat && data.long) ? data.lat + ', ' + data.long : '—';
            document.getElementById('ra-detail-meta').innerHTML = [
                metaItem('Jam masuk', formatJam(masuk)),
                metaItem('Jam pulang', formatJam(pulang)),
                metaItem('Jadwal masuk', data.jadwalMasuk),
                metaItem('Toleransi', data.toleransi),
                metaItem('Jadwal pulang', data.jadwalPulang),
                metaItem('Jarak', data.jarak === '—' ? '—' : data.jarak + ' m'),
                metaItem('IP', data.ip || '—'),
                metaItem('Koordinat', koordinat)
            ].join('');

            document.getElementById('ra-detail-photos').innerHTML = [
                fotoItem('Foto masuk', urlFoto(data, false)),
                fotoItem('Foto pulang', urlFoto(data, true))
            ].join('');

            document.querySelectorAll('#ra-detail-photos .ra-photo__img').forEach(function (img) {
                img.addEventListener('click', function () {
                    tampilkanModalGambar(img.getAttribute('src'));
                });
            });

            detailAktif = data;

            const formSeragam = document.getElementById('ra-seragam-form');
            const infoSeragam = document.getElementById('ra-seragam-info');
            const btnTimpa = document.getElementById('ra-seragam-timpa-btn');
            document.getElementById('ra-seragam-akun').value = data.akun || '';
            document.getElementById('ra-seragam-tanggal').value = data.tanggalIso || '';
            document.getElementById('ra-seragam-timpa').value = '';
            document.getElementById('ra-seragam-s').checked = data.seragamS;
            document.getElementById('ra-seragam-p').checked = data.seragamP;
            document.getElementById('ra-seragam-i').checked = data.seragamI;

            if (data.seragamBoleh) {
                if (data.seragamManual) {
                    formSeragam.classList.add('d-none');
                    btnTimpa.classList.remove('d-none');
                    infoSeragam.textContent = 'Nilai manual lama: ' + data.seragamNilai + '/5.';
                } else {
                    formSeragam.classList.remove('d-none');
                    btnTimpa.classList.add('d-none');
                    infoSeragam.textContent = '';
                    perbaruiNilaiSeragam();
                }
            } else {
                formSeragam.classList.add('d-none');
                btnTimpa.classList.add('d-none');
                infoSeragam.textContent = data.seragamNilai !== '0'
                    ? 'Nilai tersimpan: ' + data.seragamNilai + '/5. Anda tidak berwenang mengubahnya.'
                    : 'Anda tidak berwenang menilai SERAGAM pegawai ini.';
            }
        });

        document.getElementById('ra-seragam-timpa-btn').addEventListener('click', function () {
            document.getElementById('ra-seragam-form').classList.remove('d-none');
            document.getElementById('ra-seragam-timpa').value = '1';
            this.classList.add('d-none');
            document.getElementById('ra-seragam-info').textContent = 'Nilai manual lama akan diganti setelah disimpan.';
        });

        function perbaruiNilaiSeragam() {
            const nilai = (document.getElementById('ra-seragam-s').checked ? 3 : 0)
                + (document.getElementById('ra-seragam-p').checked ? 1 : 0)
                + (document.getElementById('ra-seragam-i').checked ? 1 : 0);
            document.getElementById('ra-seragam-hasil').textContent = nilai + '/5';
        }

        ['ra-seragam-s', 'ra-seragam-p', 'ra-seragam-i'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', perbaruiNilaiSeragam);
        });

        let peta = null;

        document.getElementById('ra-modal-detail').addEventListener('shown.bs.modal', function () {
            if (!detailAktif) {
                return;
            }
            const mapEl = document.getElementById('ra-map');
            const latKantor = parseFloat(detailAktif.latKantor);
            const longKantor = parseFloat(detailAktif.longKantor);
            const latPegawai = parseFloat(detailAktif.lat);
            const longPegawai = parseFloat(detailAktif.long);

            if (peta) {
                peta.remove();
                peta = null;
            }

            mapEl.innerHTML = '';
            if (!isFinite(latKantor) || !isFinite(longKantor) || !isFinite(latPegawai) || !isFinite(longPegawai)) {
                mapEl.innerHTML = '<p class="ra-map__empty">Koordinat unit atau pegawai belum diatur, peta tidak bisa ditampilkan.</p>';
                return;
            }

            peta = L.map(mapEl).setView([latKantor, longKantor], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(peta);

            L.marker([latKantor, longKantor]).addTo(peta).bindPopup('Lokasi kantor').openPopup();
            L.marker([latPegawai, longPegawai]).addTo(peta).bindPopup('Lokasi pegawai');

            const garis = L.polyline([[latKantor, longKantor], [latPegawai, longPegawai]], { color: '#0b5f8a' }).addTo(peta);
            peta.fitBounds(garis.getBounds(), { padding: [30, 30] });

            const jarak = hitungJarak(latKantor, longKantor, latPegawai, longPegawai);
            L.tooltip({ permanent: true, direction: 'top', className: 'ra-map__label' })
                .setContent(jarak.toFixed(2) + ' km')
                .setLatLng([(latKantor + latPegawai) / 2, (longKantor + longPegawai) / 2])
                .addTo(peta);
        });

        function hitungJarak(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        let zoomLevel = 1;
        let geserX = 0;
        let geserY = 0;
        let fotoTertunda = false;

        const elDetail = document.getElementById('ra-modal-detail');
        const elFoto = document.getElementById('ra-modal-foto');

        function terapkanTransformasi() {
            const img = document.getElementById('ra-zoom-image');
            img.style.transform = 'scale(' + zoomLevel + ') translate(' + geserX + 'px, ' + geserY + 'px)';
        }

        function tampilkanModalGambar(src) {
            zoomLevel = 1;
            geserX = 0;
            geserY = 0;
            const img = document.getElementById('ra-zoom-image');
            img.src = src;
            terapkanTransformasi();

            fotoTertunda = true;
            const inst = elDetail.classList.contains('show') ? bootstrap.Modal.getInstance(elDetail) : null;
            if (inst) {
                inst.hide();
            } else {
                bukaFoto();
            }
        }

        function bukaFoto() {
            fotoTertunda = false;
            bootstrap.Modal.getOrCreateInstance(elFoto).show();
        }

        // Viewer dibuka setelah detail tertutup agar dua modal tidak bertumpuk.
        elDetail.addEventListener('hidden.bs.modal', function () {
            if (fotoTertunda) {
                bukaFoto();
            }
        });

        elFoto.addEventListener('hidden.bs.modal', function () {
            if (detailAktif) {
                bootstrap.Modal.getOrCreateInstance(elDetail).show();
            }
        });

        window.raZoom = function (delta) {
            zoomLevel = Math.max(0.4, zoomLevel + delta);
            terapkanTransformasi();
        };

        window.raPan = function (x, y) {
            geserX += x;
            geserY += y;
            terapkanTransformasi();
        };
    })();
</script>

<style>
    .ra-map__label {
        padding: 3px 7px;
        color: #0d1117;
        background: #fff;
        border: 1px solid #8b949e;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 700;
    }
</style>
