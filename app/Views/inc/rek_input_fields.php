<?php

use App\Services\Finance\RekonDailyCalculator;
use App\Models\ModelFinanceRekonDaily;

/**
 * Blok input SATU HARI rekonsiliasi: tiga kelompok (ERP / Aktual / Selisih /
 * Status) plus catatan opsional.
 *
 * File ini ada karena blok yang sama dipakai DI DUA tempat di dalam satu
 * permukaan saja (sejak 2026-09-27 halaman form harian dihapus):
 *   1. panel read-only (rk-comps) di finance_rekonsiliasi.php
 *   2. form input per-hari di panel yang sama
 *
 * Kalau markup atau hitungannya diduplikasi, satu permukaan akan diam-diam
 * meleset, dan yang meleset adalah angka selisih — angka yang justru
 * diercapkan finance sebagai "benar". Jadi definisi baris, pemformat angka,
 * dan chip status semua hidup di sini, bukan di view pemanggil.
 *
 * Kontrak variabel (semuanya opsional, punya default):
 *   ex             object baris finance_rekon_daily, atau null (belum pernah diisi)
 *   erp            ['cash_masuk'=>int, 'transfer_masuk'=>int, 'kas_keluar'=>int]
 *   locked         bool    -> input jadi read-only (sudah VERIFIED)
 *   catatanRevisi  string  -> alasan revisi dari manager, atau null
 *   idPrefix       string  -> awalan untuk SEMUA id DOM
 *   showLockNote   bool    -> tampilkan catatan "sudah verified"
 *
 * PENTING: $idPrefix hanya menyentuh id DOM dan atribut `for`. NAMA field POST
 * ('actual_cash_masuk', dst) SENGAJA tidak pernah diawakan: itu kontrak server
 * (DashboardFinance::rekonSave + parseNominalRekon) dan tidak boleh berubah.
 */

$ex = $ex ?? null;
$erp = $erp ?? ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0];
$idPrefix = $idPrefix ?? '';
$showLockNote = $showLockNote ?? false;
$catatanRevisi = $catatanRevisi ?? ($ex ? $ex->catatan_revisi : null);

// Penentu akhir: kalau locked tidak diberi, tanyakan ke kalkulator dengan
// identitas pemanggil. Sifat default partial ini "boleh diubah" supaya
// pemanggil yang lupa mengoper $locked tidak ikut mengunci form.
$myId = (int) ($my_id ?? 0);
$bolehUbah = $bolehUbah ?? RekonDailyCalculator::bolehUbahAngka($ex, $myId);
$kunciAlasan = $kunciAlasan ?? RekonDailyCalculator::kunciAlasan($ex, $myId);
$locked = $locked ?? ! $bolehUbah;
$disabled = $locked ? ' disabled' : '';
$catatan = $ex ? (string) $ex->catatan : '';

$statusProses = RekonDailyCalculator::statusProses($ex);

$fmt = static fn ($v): string => number_format((int) $v, 0, ',', '.');

/**
 * Selisih bertanda, format ASLI aplikasi: "Rp -25.000" / "Rp 25.000".
 *
 * Sengaja pakai tanda hubung ASCII, bukan U+2212 (−): angka ini rutin disalin
 * finance ke Excel/Sheets, dan U+2212 akan tersalin sebagai karakter non-numerik
 * sehingga tidak bisa dijumlahkan. "Rp" juga mendahului tanda, mengikuti seluruh
 * laporan lain di app ini.
 */
$fmtSelisih = static function (?int $s): string {
    if ($s === null) {
        return '&mdash;';
    }

    return 'Rp ' . number_format((int) $s, 0, ',', '.');
};

/** Chip status komponen, diturunkan 100% dari perbandingan ERP vs Aktual. */
$chipKomponen = static function (?object $row, string $suffix) {
    $status = RekonDailyCalculator::statusKomponen($row, $suffix);
    $varian = [
        RekonDailyCalculator::KOMPONEN_COCOK => 'is-ok',
        RekonDailyCalculator::KOMPONEN_SELISIH => 'is-warn',
    ][$status] ?? '';

    return '<span class="rk-chip ' . $varian . '">'
        . RekonDailyCalculator::labelKomponen($status) . '</span>';
};

// Satu definisi baris untuk ketiga kelompok. Nilai aktual & selisih diambil dari
// record tersimpan; NULL berarti "belum diisi" dan TIDAK boleh disamakan dengan
// 0 — angka 0 adalah nilai yang sah dan dihitung lengkap.
$baris = [];
foreach ([
    ['key' => 'cash_masuk', 'nama' => 'Cash Masuk'],
    ['key' => 'transfer_masuk', 'nama' => 'Transfer Masuk'],
    ['key' => 'kas_keluar', 'nama' => 'Kas Keluar'],
] as $def) {
    $key = $def['key'];
    $baris[] = [
        'key'  => $key,
        'nama' => $def['nama'],
        'col'  => 'erp_' . $key,
        'akt'  => 'actual_' . $key,
        'sel'  => 'selisih_' . $key,
        'v'    => ($ex && $ex->{'actual_' . $key} !== null) ? (int) $ex->{'actual_' . $key} : '',
        's'    => $ex ? (int) $ex->{'selisih_' . $key} : null,
    ];
}
?>
<div class="rk-hint" id="<?= esc($idPrefix) ?>rekon-format-hint">
    Ketik angka saja, titik ribuan dipasang otomatis. Kosong berarti <b>Rp 0</b>, dan angka <b>0</b> yang diketik sendiri tetap dihitung sah.
</div>

<div class="rk-moneywrap table-responsive">
    <table class="table rk-money">
        <thead>
            <tr>
                <th scope="col">Kelompok</th>
                <th scope="col" class="text-end">ERP</th>
                <th scope="col" class="text-end">Aktual</th>
                <th scope="col" class="text-end">Selisih</th>
                <th scope="col" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($baris as $b): ?>
                <?php
                $erpNilai = (int) ($erp[$b['key']] ?? 0);
                $v = $b['v'];
                $s = $b['s'];
                $kelasSelisih = $s === null ? 'is-nihil' : ((int) $s === 0 ? 'is-nol' : 'is-ada');
                ?>
                <tr>
                    <td class="rk-k" data-label="Kelompok"><?= esc($b['nama']) ?></td>
                    <td class="rk-erp text-end" data-label="ERP"><?= esc($fmt($erpNilai)) ?></td>
                    <td class="rk-input-cell" data-label="Aktual">
                        <input type="text"
                               class="form-control rupiah-rekon text-end"
                               name="<?= esc($b['akt']) ?>"
                               inputmode="numeric" autocomplete="off" spellcheck="false"
                               value="<?= $v !== '' ? $fmt($v) : '' ?>"
                               data-group="<?= esc($b['key']) ?>"
                               data-erp="<?= $erpNilai ?>"
                               data-selishtext="<?= esc($idPrefix . $b['sel']) ?>"
                               data-status="<?= esc($idPrefix . 'status_' . $b['key']) ?>"
                               aria-describedby="<?= esc($idPrefix . 'msg_' . $b['key']) ?>"
                               placeholder="0"<?= $disabled ?>>
                        <div class="rk-msg" id="<?= esc($idPrefix . 'msg_' . $b['key']) ?>" role="status"></div>
                    </td>
                    <td class="rk-selisih text-end rk-num <?= $kelasSelisih ?>"
                        data-label="Selisih" id="<?= esc($idPrefix . $b['sel']) ?>">
                        <?= $fmtSelisih($s) ?>
                    </td>
                    <td class="text-center" data-label="Status" id="<?= esc($idPrefix . 'status_' . $b['key']) ?>">
                        <?= $chipKomponen($ex, $b['key']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="rk-notes">
    <label for="<?= esc($idPrefix) ?>catatan">Catatan (opsional)</label>
    <textarea class="form-control" id="<?= esc($idPrefix) ?>catatan" name="catatan" rows="2"
              placeholder="Rekening sumber, sisa kas, atau hal yang perlu diingat esok hari."
              <?= $disabled ?>><?= esc($catatan) ?></textarea>
</div>

<?php if ($showLockNote && $locked && $ex): ?>
    <div class="rk-note-line <?= $ex->verified_at ? 'is-ok' : 'is-warn' ?>">
        <i class="ti <?= $ex->verified_at ? 'ti-lock' : 'ti-clock' ?>" aria-hidden="true"></i>
        <span>
            <?php if ($ex->verified_at): ?>
                Data sudah <b>VERIFIED</b> oleh manager<?= $ex->verified_by ? ' (#' . (int) $ex->verified_by . ')' : '' ?>
                pada <?= esc((string) $ex->verified_at) ?> dan tidak dapat diubah.
            <?php else: ?>
                <b><?= esc((string) $kunciAlasan) ?></b>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<?php if ($statusProses === ModelFinanceRekonDaily::STATUS_NEED_REVISION && $catatanRevisi): ?>
    <div class="rk-note-line">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span>
            <b>Catatan manager (perlu revisi):</b> <?= esc((string) $catatanRevisi) ?>
        </span>
    </div>
<?php endif; ?>
