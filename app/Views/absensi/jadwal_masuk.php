<?php
/**
 * Roster Absensi — lembar kalender bulanan.
 *
 * Header, toolbar, dan lembar memakai bahasa visual yang sama dengan
 * /kas_bank (.kbx): judul di kanvas, garis rambut, tanpa bayangan kartu.
 * Semua selector di-scope .jad supaya tidak bocor ke halaman absensi lain.
 */

$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanLabel = ($namaBulan[(int) substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$namaHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$hariPendek = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

$daftarShift = ['PAGI', 'SIANG', 'PS', 'OFF', 'IZIN'];
$labelShift  = ['PAGI' => 'Pagi', 'SIANG' => 'Siang', 'PS' => 'PS', 'OFF' => 'Off', 'IZIN' => 'Izin'];
$slugShift   = ['PAGI' => 'pagi', 'SIANG' => 'siang', 'PS' => 'ps', 'OFF' => 'off', 'IZIN' => 'izin'];

$jumlahHari = (int) date('t', strtotime($bulan . '-01'));
$offsetAwal = (int) date('N', strtotime($bulan . '-01')) - 1;
$offsetAkhir = (7 - (($offsetAwal + $jumlahHari) % 7)) % 7;
$hariIni    = date('Y-m-d');

$hitungan = array_fill_keys($daftarShift, 0);
foreach ($roster as $baris) {
    if (isset($hitungan[$baris->shift])) {
        $hitungan[$baris->shift]++;
    }
}
$terisi = count($roster);

$pegawaiAktif = null;
foreach ($pegawai as $p) {
    if ((int) $p->ID_AKUN === (int) $akunId) {
        $pegawaiAktif = $p;
        break;
    }
}
$namaPegawai = $pegawaiAktif->NAMA_AKUN ?? '';
$namaUnit    = $pegawaiAktif->NAMA_UNIT ?? '';
?>

<div class="jad">
    <header class="jad-head">
        <div class="jad-head-copy">
            <h1 class="jad-title">Roster Absensi</h1>
            <p class="jad-sub">Jadwal shift per pegawai per tanggal. Perubahan disimpan sekaligus di akhir bulan.</p>
        </div>
        <a class="jad-btn jad-btn--ghost" href="<?= base_url('absen') ?>">
            <iconify-icon icon="bi:phone" aria-hidden="true"></iconify-icon>
            Buka Absen Mobile
        </a>
    </header>

    <form method="get" action="<?= base_url('jadwal_masuk') ?>" class="jad-toolbar">
        <div class="jad-field">
            <label class="jad-label" for="jad-bulan">Bulan</label>
            <input id="jad-bulan" type="month" class="jad-control" name="bulan" value="<?= esc($bulan) ?>">
        </div>
        <?php if ($bolehSemuaUnit): ?>
            <div class="jad-field">
                <label class="jad-label" for="jad-unit">Unit</label>
                <select id="jad-unit" class="jad-control" name="unit">
                    <option value="">Semua unit</option>
                    <?php foreach ($unit as $u): ?>
                        <option value="<?= (int) $u->idunit ?>"
                            <?= (int) ($_GET['unit'] ?? 0) === (int) $u->idunit ? 'selected' : '' ?>>
                            <?= esc($u->NAMA_UNIT) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="jad-field jad-field--akun">
            <label class="jad-label" for="jad-akun">Pegawai</label>
            <select id="jad-akun" class="jad-control" name="akun">
                <?php foreach ($pegawai as $p): ?>
                    <option value="<?= (int) $p->ID_AKUN ?>" <?= (int) $p->ID_AKUN === (int) $akunId ? 'selected' : '' ?>>
                        <?= esc($p->NAMA_AKUN) ?><?= isset($p->NAMA_UNIT) ? ' — ' . esc($p->NAMA_UNIT) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="jad-btn jad-btn--primary">Tampilkan</button>
    </form>

    <?php if (session()->getFlashdata('sukses')): ?>
        <div class="jad-flash jad-flash--ok" role="status"><?= esc(session()->getFlashdata('sukses')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('gagal')): ?>
        <div class="jad-flash jad-flash--bad" role="alert"><?= esc(session()->getFlashdata('gagal')) ?></div>
    <?php endif; ?>

    <?php if (empty($pegawai)): ?>
        <div class="jad-sheet jad-empty">
            <iconify-icon icon="bi:calendar-x" aria-hidden="true"></iconify-icon>
            <p>Belum ada pegawai aktif<?= $bolehSemuaUnit ? ' di unit ini' : '' ?>.</p>
        </div>
    <?php else: ?>
        <details class="jad-pattern">
            <summary class="jad-pattern-summary">
                <span>Terapkan pola mingguan</span>
                <span class="jad-pattern-hint">Isi tanggal yang belum punya roster. Tanggal yang sudah ada tidak ditimpa.</span>
            </summary>
            <form method="post" action="<?= base_url('jadwal_masuk/terapkan') ?>" class="jad-pattern-body">
                <?= csrf_field() ?>
                <input type="hidden" name="akun_idakun" value="<?= (int) $akunId ?>">
                <input type="hidden" name="bulan" value="<?= esc($bulan) ?>">
                <div class="jad-pattern-grid">
                    <?php $shiftPola = ['PAGI', 'SIANG', 'PS', 'OFF']; ?>
                    <?php foreach ($namaHari as $nomor => $label): ?>
                        <div class="jad-field">
                            <label class="jad-label" for="jad-pola-<?= $nomor ?>"><?= $label ?></label>
                            <select id="jad-pola-<?= $nomor ?>" class="jad-control" name="pola[<?= $nomor ?>]">
                                <?php foreach ($shiftPola as $s): ?>
                                    <option value="<?= $s ?>"><?= $labelShift[$s] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="jad-pattern-foot">
                    <button type="submit" class="jad-btn jad-btn--primary">Terapkan ke bulan</button>
                </div>
            </form>
        </details>

        <section class="jad-sheet" aria-labelledby="jad-sheet-title">
            <header class="jad-sheet-head">
                <div class="jad-sheet-identity">
                    <h2 class="jad-sheet-title" id="jad-sheet-title"><?= esc($namaPegawai) ?></h2>
                    <p class="jad-sheet-meta">
                        <?= $namaUnit !== '' ? esc($namaUnit) . ' · ' : '' ?><?= esc($bulanLabel) ?>
                        · <?= (int) $terisi ?>/<?= (int) $jumlahHari ?> hari terisi
                    </p>
                </div>
                <ul class="jad-counts" aria-label="Jumlah per shift">
                    <?php foreach ($daftarShift as $s): ?>
                        <li class="jad-count is-<?= $slugShift[$s] ?><?= $hitungan[$s] === 0 ? ' is-zero' : '' ?>">
                            <span class="jad-dot" aria-hidden="true"></span>
                            <span class="jad-count-label"><?= $labelShift[$s] ?></span>
                            <b><?= (int) $hitungan[$s] ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </header>

            <form method="post" action="<?= base_url('jadwal_masuk/simpan') ?>" class="jad-form">
                <?= csrf_field() ?>
                <input type="hidden" name="akun_idakun" value="<?= (int) $akunId ?>">
                <input type="hidden" name="bulan" value="<?= esc($bulan) ?>">

                <div class="jad-grid">
                    <?php foreach ($hariPendek as $label): ?>
                        <div class="jad-weekday"><?= $label ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 0; $i < $offsetAwal; $i++): ?>
                        <div class="jad-blank" aria-hidden="true"></div>
                    <?php endfor; ?>

                    <?php for ($tgl = 1; $tgl <= $jumlahHari; $tgl++): ?>
                        <?php
                        $tanggal  = sprintf('%s-%02d', $bulan, $tgl);
                        $baris    = $roster[$tanggal] ?? null;
                        $shift    = $baris->shift ?? '';
                        $ket      = $baris->keterangan ?? '';
                        $nomorHari = (int) date('N', strtotime($tanggal));
                        $akhirPekan = $nomorHari >= 6;
                        $lampau   = $tanggal < $hariIni && ! $bolehKoreksiLampau;
                        $kelas    = 'jad-day'
                            . ($akhirPekan ? ' is-weekend' : '')
                            . ($tanggal === $hariIni ? ' is-today' : '')
                            . ($lampau ? ' is-locked' : '')
                            . (! $baris && ! $lampau ? ' is-unset' : '');
                        ?>
                        <div class="<?= $kelas ?>"<?= isset($slugShift[$shift]) ? ' data-shift="' . $slugShift[$shift] . '"' : '' ?><?= $tanggal === $hariIni ? ' aria-current="date"' : '' ?>>
                            <div class="jad-day-head">
                                <span class="jad-day-num"><?= $tgl ?></span>
                                <span class="jad-day-dow"><?= $hariPendek[$nomorHari] ?></span>
                                <span class="jad-dot" aria-hidden="true"></span>
                            </div>

                            <?php if ($lampau): ?>
                                <span class="jad-chip"><?= esc($labelShift[$shift] ?? '—') ?></span>
                                <?php if (trim((string) $ket) !== ''): ?>
                                    <span class="jad-chip-note"><?= esc($ket) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="jad-day-body">
                                    <select class="jad-select" name="shift[<?= $tanggal ?>]"
                                        aria-label="Shift <?= $tgl ?> <?= esc($bulanLabel) ?><?= $baris ? '' : ', belum diatur' ?>">
                                        <?php foreach ($daftarShift as $s): ?>
                                            <option value="<?= $s ?>" <?= $s === $shift ? 'selected' : '' ?>><?= $labelShift[$s] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" class="jad-note" name="keterangan[<?= $tanggal ?>]"
                                        value="<?= esc($ket) ?>" placeholder="Keterangan"
                                        aria-label="Keterangan <?= $tgl ?> <?= esc($bulanLabel) ?>"
                                        <?= $shift === 'IZIN' ? 'required' : '' ?>>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <?php for ($i = 0; $i < $offsetAkhir; $i++): ?>
                        <div class="jad-blank" aria-hidden="true"></div>
                    <?php endfor; ?>
                </div>

                <div class="jad-save">
                    <p class="jad-save-note">
                        <?php if ($bolehKoreksiLampau): ?>
                            Tanggal lampau ikut tersimpan saat Anda menekan simpan.
                        <?php else: ?>
                            Tanggal lampau terkunci. Hanya Admin Root yang bisa mengubahnya.
                        <?php endif; ?>
                    </p>
                    <button type="submit" class="jad-btn jad-btn--primary">Simpan Roster</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>

<style>
    .jad {
        --jad-ink: var(--bs-emphasis-color, #111c2d);
        --jad-muted: #5b6672;
        --jad-line: var(--bs-border-color, #e6ecf1);
        --jad-line-strong: #7d8a99;
        --jad-sheet: var(--bs-body-bg, #fff);
        --jad-soft: var(--bs-tertiary-bg, #f4f7f9);
        --jad-pagi: #0074ba;
        --jad-siang: #b45309;
        --jad-ps: #0f766e;
        --jad-off: #5b6672;
        --jad-izin: #c2353f;
        --jad-radius: 0.375rem;
        margin: 0 0 2rem;
        color: var(--jad-ink);
        font-size: 0.875rem;
    }

    [data-bs-theme="dark"] .jad {
        --jad-muted: #8fa0b8;
        --jad-line: #2a3543;
        --jad-line-strong: #6f8098;
        --jad-soft: #1b2530;
        --jad-pagi: #66b6e9;
        --jad-siang: #fbbf24;
        --jad-ps: #2dd4bf;
        --jad-off: #8fa0b8;
        --jad-izin: #fb977d;
    }

    .jad-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1.5rem;
        margin-bottom: 1.25rem;
    }

    .jad-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.25;
        letter-spacing: -0.025em;
        color: var(--jad-ink);
    }

    .jad-sub {
        margin: 0.25rem 0 0;
        max-width: 72ch;
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--jad-muted);
    }

    /* ===== Toolbar filter ===== */
    .jad-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem 1rem;
        padding-bottom: 1rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--jad-line);
    }

    .jad-field {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .jad-label {
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1.3;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--jad-muted);
    }

    .jad-control {
        height: 2.25rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.875rem;
        color: var(--jad-ink);
        background: var(--jad-sheet);
        border: 1px solid var(--jad-line-strong);
        border-radius: 0.25rem;
        box-shadow: none;
    }

    .jad-control:focus {
        border-color: var(--bs-primary, #0074ba);
        outline: 2px solid rgba(var(--bs-primary-rgb, 0, 116, 186), 0.25);
        outline-offset: 0;
        box-shadow: none;
    }

    .jad-field--akun .jad-control { min-width: 15rem; max-width: 22rem; }

    .jad-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        height: 2.25rem;
        padding: 0 0.875rem;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1;
        border: 1px solid transparent;
        border-radius: 0.25rem;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .jad-btn iconify-icon { font-size: 1rem; }

    .jad-btn--primary {
        color: #fff;
        background: var(--bs-primary, #0074ba);
        border-color: var(--bs-primary, #0074ba);
    }

    .jad-btn--primary:hover,
    .jad-btn--primary:focus {
        color: #fff;
        background: #0069a8;
        border-color: #0069a8;
    }

    .jad-btn--ghost {
        color: var(--jad-ink);
        background: var(--jad-sheet);
        border-color: var(--jad-line-strong);
    }

    .jad-btn--ghost:hover,
    .jad-btn--ghost:focus {
        color: var(--jad-ink);
        border-color: var(--jad-ink);
    }

    .jad-btn:focus-visible,
    .jad-control:focus-visible,
    .jad-select:focus-visible,
    .jad-note:focus-visible {
        outline: 2px solid var(--bs-primary, #0074ba);
        outline-offset: 2px;
    }

    /* ===== Flash ===== */
    .jad-flash {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding: 0.625rem 0.875rem;
        border: 1px solid;
        border-radius: var(--jad-radius);
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .jad-flash--ok {
        color: var(--bs-success-text-emphasis, #0a3622);
        background: var(--bs-success-bg-subtle, #d1e7dd);
        border-color: var(--bs-success-border-subtle, #a3cfbb);
    }

    .jad-flash--bad {
        color: var(--bs-danger-text-emphasis, #58151c);
        background: var(--bs-danger-bg-subtle, #f8d7da);
        border-color: var(--bs-danger-border-subtle, #f1aeb5);
    }

    /* ===== Pola mingguan (disclosure) ===== */
    .jad-pattern {
        margin-bottom: 1rem;
        background: var(--jad-sheet);
        border: 1px solid var(--jad-line);
        border-radius: var(--jad-radius);
    }

    .jad-pattern-summary {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem 0.75rem;
        padding: 0.75rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--jad-ink);
        cursor: pointer;
        list-style: none;
    }

    .jad-pattern-summary::-webkit-details-marker { display: none; }

    .jad-pattern-summary::after {
        content: "+";
        margin-left: auto;
        font-size: 1rem;
        font-weight: 500;
        color: var(--jad-muted);
    }

    .jad-pattern[open] .jad-pattern-summary::after { content: "−"; }

    .jad-pattern-hint {
        font-size: 0.75rem;
        font-weight: 400;
        color: var(--jad-muted);
    }

    .jad-pattern-body {
        padding: 0 1.25rem 1rem;
        border-top: 1px solid var(--jad-line);
    }

    .jad-pattern-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.75rem;
        padding-top: 0.875rem;
    }

    .jad-pattern-grid .jad-control { width: 100%; }

    .jad-pattern-foot {
        display: flex;
        justify-content: flex-end;
        padding-top: 0.875rem;
    }

    /* ===== Lembar roster ===== */
    .jad-sheet {
        background: var(--jad-sheet);
        border: 1px solid var(--jad-line);
        border-radius: var(--jad-radius);
        overflow: hidden;
    }

    .jad-sheet-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.5rem 1.5rem;
        padding: 1rem 1.25rem 0.875rem;
    }

    .jad-sheet-title {
        margin: 0;
        font-size: 1.0625rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--jad-ink);
    }

    .jad-sheet-meta {
        margin: 0.125rem 0 0;
        font-size: 0.75rem;
        color: var(--jad-muted);
    }

    .jad-counts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 1rem;
        margin: 0;
        padding: 0;
        list-style: none;
        font-size: 0.75rem;
    }

    .jad-count {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        color: var(--jad-muted);
    }

    .jad-count b {
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--jad-ink);
    }

    .jad-count.is-zero b { color: var(--jad-muted); font-weight: 500; }

    .jad-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }

    .jad-count.is-pagi .jad-dot,
    .jad-day[data-shift="pagi"] .jad-dot { color: var(--jad-pagi); }
    .jad-count.is-siang .jad-dot,
    .jad-day[data-shift="siang"] .jad-dot { color: var(--jad-siang); }
    .jad-count.is-ps .jad-dot,
    .jad-day[data-shift="ps"] .jad-dot { color: var(--jad-ps); }
    .jad-count.is-off .jad-dot,
    .jad-day[data-shift="off"] .jad-dot { color: var(--jad-off); }
    .jad-count.is-izin .jad-dot,
    .jad-day[data-shift="izin"] .jad-dot { color: var(--jad-izin); }

    .jad-day[data-shift="pagi"] { --jad-accent: var(--jad-pagi); }
    .jad-day[data-shift="siang"] { --jad-accent: var(--jad-siang); }
    .jad-day[data-shift="ps"] { --jad-accent: var(--jad-ps); }
    .jad-day[data-shift="off"] { --jad-accent: var(--jad-off); }
    .jad-day[data-shift="izin"] { --jad-accent: var(--jad-izin); }

    .jad-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 1px;
        background: var(--jad-line);
        border-top: 1px solid var(--jad-line);
    }

    .jad-weekday {
        padding: 0.4375rem 0.625rem;
        background: var(--jad-soft);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--jad-muted);
    }

    .jad-blank { background: var(--jad-sheet); }

    .jad-day {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-height: 7rem;
        padding: 0.5rem 0.625rem 0.625rem;
        background: var(--jad-sheet);
    }

    .jad-day.is-weekend { background: var(--jad-soft); }

    .jad-day.is-today { box-shadow: inset 0 0 0 2px var(--bs-primary, #0074ba); }

    .jad-day-head {
        display: flex;
        align-items: baseline;
        gap: 0.375rem;
        font-size: 0.75rem;
        color: var(--jad-muted);
    }

    .jad-day-num {
        font-size: 0.8125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--jad-ink);
    }

    .jad-day-head .jad-dot { margin-left: auto; }

    .jad-day:not([data-shift]) .jad-day-head .jad-dot { visibility: hidden; }

    .jad-day-body {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .jad-select {
        width: 100%;
        height: 1.875rem;
        padding: 0.125rem 0.375rem;
        font-size: 0.8125rem;
        color: var(--jad-ink);
        background: var(--jad-sheet);
        border: 1px solid var(--jad-line-strong);
        border-left: 3px solid var(--jad-accent, var(--jad-line-strong));
        border-radius: 0.25rem;
    }

    .jad-note {
        width: 100%;
        padding: 0.125rem 0;
        font-size: 0.75rem;
        color: var(--jad-ink);
        background: transparent;
        border: 0;
        border-bottom: 1px solid var(--jad-line);
        border-radius: 0;
    }

    .jad-note::placeholder { color: var(--jad-muted); opacity: 0.55; }

    /* Tanggal yang belum punya baris roster tampil netral, bukan seperti sudah diisi. */
    .jad-day.is-unset .jad-select {
        color: var(--jad-muted);
        border-style: dashed;
        border-left-style: solid;
    }
    .jad-note:focus { border-bottom-color: var(--bs-primary, #0074ba); }

    .jad-chip {
        display: inline-flex;
        align-self: flex-start;
        padding: 0.0625rem 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--jad-accent, var(--jad-muted));
        border: 1px solid currentColor;
        border-radius: 0.25rem;
    }

    .jad-chip-note {
        font-size: 0.75rem;
        line-height: 1.35;
        color: var(--jad-muted);
    }

    .jad-save {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem 1rem;
        padding: 0.75rem 1.25rem;
        border-top: 1px solid var(--jad-line);
        background: var(--jad-soft);
    }

    .jad-save-note {
        margin: 0;
        font-size: 0.75rem;
        color: var(--jad-muted);
    }

    .jad-empty {
        display: grid;
        justify-items: center;
        gap: 0.375rem;
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--jad-muted);
    }

    .jad-empty iconify-icon { font-size: 1.5rem; opacity: 0.6; }
    .jad-empty p { margin: 0; }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .jad-pattern-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .jad-day { min-height: 6.5rem; }
    }

    @media (max-width: 767.98px) {
        .jad-title { font-size: 1.25rem; }

        .jad-field--akun { flex: 1 1 100%; }
        .jad-field--akun .jad-control { min-width: 0; max-width: none; width: 100%; }
        .jad-toolbar > .jad-btn { flex: 1 1 100%; }
        .jad-toolbar .jad-field { flex: 1 1 45%; }
        .jad-toolbar .jad-field .jad-control { width: 100%; }

        .jad-head,
        .jad-toolbar,
        .jad-sheet-head,
        .jad-pattern-summary,
        .jad-pattern-body,
        .jad-save { padding-left: 1rem; padding-right: 1rem; }

        .jad-pattern-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .jad-pattern-foot { justify-content: stretch; }
        .jad-pattern-foot .jad-btn { width: 100%; }

        /* Grid kalender jadi daftar baris: tanggal di kiri, kontrol di kanan. */
        .jad-grid {
            display: block;
            background: transparent;
            border-top: 0;
        }

        .jad-weekday,
        .jad-blank { display: none; }

        .jad-day {
            display: grid;
            grid-template-columns: 3.25rem minmax(0, 1fr);
            align-items: start;
            column-gap: 0.75rem;
            min-height: 0;
            padding: 0.625rem 1rem;
            border-top: 1px solid var(--jad-line);
        }

        .jad-day.is-today { box-shadow: inset 3px 0 0 var(--bs-primary, #0074ba); }

        .jad-day-head { gap: 0.25rem; }
        .jad-day-head .jad-dot { display: none; }
        .jad-day-body { grid-column: 2; }
        .jad-chip,
        .jad-chip-note { grid-column: 2; }

        .jad-save { flex-direction: column; align-items: stretch; }
        .jad-save .jad-btn { width: 100%; }
    }
</style>

<script>
    (() => {
        const grid = document.querySelector('.jad-grid');
        if (!grid) return;

        // Aksen warna dan status wajib keterangan mengikuti shift yang dipilih.
        grid.addEventListener('change', (event) => {
            const select = event.target.closest('.jad-select');
            if (!select) return;

            const sel = select.closest('.jad-day');
            sel.dataset.shift = select.value.toLowerCase();
            sel.classList.remove('is-unset');

            const catatan = sel.querySelector('.jad-note');
            if (catatan) catatan.required = select.value === 'IZIN';
        });
    })();
</script>
