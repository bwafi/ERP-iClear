<?php
/**
 * Bukti Tutup Kasir (PDF, mPDF).
 *
 * Angka uang diambil dari baris `tutup_kasir` — record closing yang sudah
 * disimpan — bukan dihitung ulang dari transaksi. Rincian transaksi dan
 * pengeluaran hanya pelengkap; nilainya bisa berbeda dari arus kas karena
 * pembayaran/DP dan perubahan data setelah closing.
 */
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$num = static function ($angka): string {
    return number_format(abs((int) $angka), 0, ',', '.');
};

// Nol ditulis sebagai garis, seperti lembar kas kertas.
$cell = static function ($angka) use ($num): string {
    return (int) $angka === 0 ? '&ndash;' : $num($angka);
};

// Kas keluar ditulis dalam tanda kurung (konvensi akuntansi).
$out = static function ($angka) use ($num): string {
    return (int) $angka === 0 ? '&ndash;' : '(' . $num($angka) . ')';
};

$rp = static function ($angka) use ($num): string {
    return 'Rp ' . $num($angka);
};

$tanggalTs = strtotime((string) ($tutup->tanggal ?? ''));
$tanggalLabel = $tanggalTs
    ? date('d', $tanggalTs) . ' ' . ($namaBulan[(int) date('n', $tanggalTs)] ?? '-') . ' ' . date('Y', $tanggalTs)
    : (string) ($tutup->tanggal ?? '-');

$createdTs = ! empty($tutup->created_at) ? strtotime((string) $tutup->created_at . ' +7 hours') : false;
$waktuClosing = $createdTs ? date('H:i', $createdTs) . ' WIB' : '-';

$dicetakTs = strtotime('+7 hours');
$dicetakLabel = date('d', $dicetakTs) . ' ' . ($namaBulan[(int) date('n', $dicetakTs)] ?? '-') . ' ' . date('Y', $dicetakTs)
    . ' ' . date('H:i', $dicetakTs) . ' WIB';

$awalCash       = (int) ($tutup->awal_cash ?? 0);
$awalTf         = (int) ($tutup->awal_transfer ?? 0);
$pendapatanCash = (int) ($tutup->pendapatan_cash ?? 0);
$pendapatanTf   = (int) ($tutup->pendapatan_transfer ?? 0);
$pengeluaranCash = (int) ($tutup->pengeluaran_cash ?? 0);
$pengeluaranTf  = (int) ($tutup->pengeluaran_transfer ?? 0);
$akhirCash      = (int) ($tutup->akhir_cash ?? 0);
$akhirTf        = (int) ($tutup->akhir_transfer ?? 0);
$cashLaci       = (int) ($tutup->cash_laci ?? 0);

$totalAwal        = $awalCash + $awalTf;
$totalPendapatan  = $pendapatanCash + $pendapatanTf;
$totalPengeluaran = $pengeluaranCash + $pengeluaranTf;
$totalAkhir       = $akhirCash + $akhirTf;
$selisih          = $cashLaci - $akhirCash;

// akhir = awal + pendapatan - pengeluaran - setor + tarik,
// jadi neto setor/tarik dibaca langsung dari record.
$mutasiNeto = $awalCash + $pendapatanCash - $pengeluaranCash - $akhirCash;

$lainCash = $pengeluaranCash - (int) ($op_cash ?? 0) - (int) ($ps_cash ?? 0);
$lainTf   = $pengeluaranTf - (int) ($op_tf ?? 0) - (int) ($ps_tf ?? 0);

$totalQty   = (int) ($qty_hp ?? 0) + (int) ($qty_service ?? 0) + (int) ($qty_acc ?? 0);
$totalNilai = (int) ($hp_total ?? 0) + (int) ($service_total ?? 0) + (int) ($acc_total ?? 0);

$alamatUnit = implode(', ', array_filter([
    (string) ($unit_row['JALAN_UNIT'] ?? ''),
    (string) ($unit_row['KELURAHAN_UNIT'] ?? ''),
    (string) ($unit_row['KECAMATAN_UNIT'] ?? ''),
    (string) ($unit_row['KABUPATEN_UNIT'] ?? ''),
    (string) ($unit_row['PROVINSI_UNIT'] ?? ''),
]));

$namaUnit        = (string) ($unit_row['NAMA_UNIT'] ?? ('Unit ' . (int) ($tutup->unit ?? 0)));
$teleponUnit     = (string) ($unit_row['NOTELP'] ?? '');
$namaPenutup     = trim((string) ($penutup['NAMA_AKUN'] ?? ''));
$jabatanPenutup  = trim((string) ($penutup['NAMA_JABATAN'] ?? ''));
$statusLabel     = ucfirst((string) ($tutup->status ?? '-'));

$verdict = $selisih === 0 ? 'Cocok' : ($selisih > 0 ? 'Lebih' : 'Kurang');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tutup Kasir</title>
    <style>
        body {
            font-family: 'dejavusanscondensed', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #102a43;
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            vertical-align: top;
            padding: 0;
        }
        .r { text-align: right; }
        .n { white-space: nowrap; }

        /* Kepala dokumen */
        .brand-name {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: .01em;
            color: #0f2a43;
        }
        .brand-unit {
            margin-top: 2px;
            font-size: 10pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .brand-meta {
            margin-top: 2px;
            font-size: 7.5pt;
            line-height: 1.5;
            color: #5b6672;
        }
        .doc { text-align: right; }
        .doc-title {
            font-size: 22pt;
            font-weight: bold;
            line-height: 1.05;
            color: #0f2a43;
        }
        .doc-meta {
            margin-top: 4px;
            font-size: 8.5pt;
            line-height: 1.55;
            color: #5b6672;
        }
        .doc-meta strong { color: #102a43; font-weight: bold; }
        .masthead-rule {
            margin-top: 10px;
            border-top: 2pt solid #0f2a43;
        }

        /* Identitas closing */
        .meta { border-bottom: 1px solid #cfd8e3; }
        .meta td {
            padding: 7px 10px 8px 0;
        }
        .meta td.sep {
            border-left: 1px solid #e3e8ee;
            padding-left: 10px;
        }
        .meta .k {
            font-size: 6.8pt;
            font-weight: bold;
            letter-spacing: .1em;
            color: #5b6672;
        }
        .meta .v {
            margin-top: 2px;
            font-size: 8.5pt;
            color: #102a43;
        }

        /* Rekonsiliasi laci */
        .recon {
            margin-top: 14px;
            table-layout: fixed;
            border-top: 1pt solid #0f2a43;
            border-bottom: 1pt solid #0f2a43;
        }
        .recon td {
            padding: 12px 0 12px;
        }
        .recon td.sep {
            padding-left: 12px;
            border-left: 1px solid #cfd8e3;
        }
        .recon .k {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .08em;
            color: #5b6672;
        }
        .recon .v {
            margin-top: 4px;
            font-size: 18pt;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
            color: #0f2a43;
        }
        .recon .note {
            margin-top: 3px;
            font-size: 7.5pt;
            color: #5b6672;
        }
        .stamp-frame { width: 52mm; }
        .stamp {
            border: 1pt solid #0f2a43;
            padding: 9px 8px 10px;
            text-align: center;
            vertical-align: middle;
        }
        .stamp-k {
            font-size: 6.8pt;
            letter-spacing: .1em;
            color: #5b6672;
        }
        .stamp-word {
            margin-top: 3px;
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.1;
        }
        .stamp-amount {
            margin-top: 1px;
            font-size: 9pt;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
        }

        /* Judul bagian */
        .sec { margin-top: 28px; }
        .sec-head {
            padding-bottom: 4px;
            border-bottom: 1pt solid #0f2a43;
        }
        .sec-title {
            font-size: 10pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .sec-note {
            padding-top: 2px;
            text-align: right;
            font-size: 7.5pt;
            color: #5b6672;
        }

        /* Buku kas */
        .ledger th {
            padding: 6px 4px;
            border-bottom: 1px solid #cfd8e3;
            font-size: 6.8pt;
            font-weight: bold;
            letter-spacing: .08em;
            text-align: left;
            color: #5b6672;
        }
        .ledger th.r { text-align: right; }
        .ledger tbody th,
        .ledger tbody td {
            padding: 8px 4px;
            border-bottom: 1px solid #e3e8ee;
            font-size: 9pt;
            vertical-align: bottom;
        }
        .ledger tbody th {
            text-align: left;
            font-weight: normal;
            color: #102a43;
        }
        .ledger tbody tr.gap th,
        .ledger tbody tr.gap td { padding-top: 13px; }
        .ledger tbody tr.lead th,
        .ledger tbody tr.lead td { font-weight: bold; }
        .ledger tbody tr.internal th { font-size: 8.5pt; color: #5b6672; }
        .ledger tbody tr.internal td { font-size: 8.5pt; }
        .ledger tbody tr.grand th,
        .ledger tbody tr.grand td {
            padding-top: 14px;
            padding-bottom: 4px;
            border-top: 1.5pt solid #0f2a43;
            border-bottom: 1px solid #ffffff;
            font-size: 11pt;
            font-weight: bold;
            color: #0f2a43;
        }

        /* Rincian pendukung */
        .detail-wrap td.c1 { width: 49%; padding-right: 9mm; }
        .detail-wrap td.c2 { width: 51%; }
        .detail-title {
            padding-bottom: 4px;
            border-bottom: 1pt solid #0f2a43;
            font-size: 8.5pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .detail th {
            padding: 5px 3px;
            border-bottom: 1px solid #cfd8e3;
            font-size: 6.8pt;
            font-weight: bold;
            letter-spacing: .06em;
            text-align: left;
            color: #5b6672;
        }
        .detail th.r { text-align: right; }
        .detail td {
            padding: 6px 3px;
            border-bottom: 1px solid #e3e8ee;
            font-size: 8.4pt;
            vertical-align: bottom;
        }
        .detail tr.tot td {
            border-top: 1pt solid #0f2a43;
            border-bottom: none;
            padding-top: 6px;
            font-weight: bold;
        }

        /* Catatan */
        .notes {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e3e8ee;
            font-size: 7.5pt;
            line-height: 1.55;
            color: #5b6672;
        }

        /* Tanda tangan */
        .sign-rule {
            margin-top: 24mm;
            border-top: 1px solid #cfd8e3;
        }
        .sign { margin-top: 14px; }
        .sign td.role {
            font-size: 7.5pt;
            color: #5b6672;
        }
        .sign td.col { width: 50%; padding-right: 14mm; }
        .sign-inner { width: 72%; }
        .sign-inner td.space { height: 34px; }
        .sign-inner td.line {
            border-top: 1px solid #7b8794;
            padding-top: 4px;
            font-size: 9pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .sign-inner td.jabatan {
            padding-top: 1px;
            font-size: 8pt;
            color: #5b6672;
        }

        /* Kaki */
        .foot {
            margin-top: 18px;
            padding-top: 6px;
            border-top: 1px solid #e3e8ee;
            font-size: 7pt;
            color: #5b6672;
        }
    </style>
</head>
<body>

<table class="masthead">
    <tr>
        <td style="width:56%;">
            <div class="brand-name">iClear</div>
            <div class="brand-unit"><?= esc($namaUnit) ?></div>
            <div class="brand-meta">
                <?php if ($alamatUnit !== ''): ?><?= esc($alamatUnit) ?><br><?php endif; ?>
                <?php if ($teleponUnit !== ''): ?>Telp. <?= esc($teleponUnit) ?><?php endif; ?>
            </div>
        </td>
        <td class="doc">
            <div class="doc-title">Tutup Kasir</div>
            <div class="doc-meta">
                <strong><?= esc($tanggalLabel) ?></strong> &middot; <?= esc($waktuClosing) ?><br>
                Status: <?= esc($statusLabel) ?> &middot; No. <?= (int) ($tutup->idtutupkasir ?? 0) ?>
            </div>
        </td>
    </tr>
</table>
<div class="masthead-rule"></div>

<table class="meta">
    <tr>
        <td style="width:38%;">
            <div class="k">KASIR</div>
            <div class="v"><?= esc($namaPenutup !== '' ? $namaPenutup : '-') ?></div>
        </td>
        <td class="sep" style="width:24%;">
            <div class="k">JABATAN</div>
            <div class="v"><?= esc($jabatanPenutup !== '' ? $jabatanPenutup : '-') ?></div>
        </td>
        <td class="sep" style="width:38%;">
            <div class="k">UNIT</div>
            <div class="v"><?= esc($namaUnit) ?> (#<?= (int) ($tutup->unit ?? 0) ?>)</div>
        </td>
    </tr>
</table>

<table class="recon">
    <tr>
        <td style="width:35%; padding-right:12px;">
            <div class="k">Kas menurut sistem</div>
            <div class="v"><?= $rp($akhirCash) ?></div>
            <div class="note">Saldo akhir kas</div>
        </td>
        <td class="sep" style="width:35%;">
            <div class="k">Kas hasil hitung fisik</div>
            <div class="v"><?= $rp($cashLaci) ?></div>
            <div class="note">Uang di laci</div>
        </td>
        <td class="sep" style="width:30%; padding-right:0;">
            <table class="stamp-frame" align="right">
                <tr>
                    <td class="stamp">
                        <div class="stamp-k">SELISIH</div>
                        <div class="stamp-word" style="color:<?= $selisih === 0 ? '#0f7a46' : '#b42318' ?>"><?= esc($verdict) ?></div>
                        <div class="stamp-amount" style="color:<?= $selisih === 0 ? '#0f2a43' : '#b42318' ?>"><?= $selisih < 0 ? '(' . $num($selisih) . ')' : $num($selisih) ?></div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="sec">
    <table class="sec-head">
        <tr>
            <td class="sec-title">Buku kas</td>
            <td class="sec-note">Nilai dalam Rupiah</td>
        </tr>
    </table>
    <table class="ledger">
        <thead>
            <tr>
                <th style="width:40%;">URAIAN</th>
                <th class="r n" style="width:20%;">KAS</th>
                <th class="r n" style="width:20%;">TRANSFER</th>
                <th class="r n" style="width:20%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr class="lead">
                <th>Saldo awal</th>
                <td class="r n"><?= $cell($awalCash) ?></td>
                <td class="r n"><?= $cell($awalTf) ?></td>
                <td class="r n"><?= $cell($totalAwal) ?></td>
            </tr>
            <tr class="gap">
                <th>Pendapatan</th>
                <td class="r n"><?= $cell($pendapatanCash) ?></td>
                <td class="r n"><?= $cell($pendapatanTf) ?></td>
                <td class="r n"><?= $cell($totalPendapatan) ?></td>
            </tr>
            <tr>
                <th>Pengeluaran</th>
                <td class="r n"><?= $out($pengeluaranCash) ?></td>
                <td class="r n"><?= $out($pengeluaranTf) ?></td>
                <td class="r n"><?= $out($totalPengeluaran) ?></td>
            </tr>
            <?php if ($mutasiNeto !== 0): ?>
                <tr class="internal">
                    <th><?= $mutasiNeto > 0 ? 'Setor ke bank (neto)' : 'Tarik dari bank (neto)' ?></th>
                    <td class="r n"><?= $mutasiNeto > 0 ? '(' . $num($mutasiNeto) . ')' : $num($mutasiNeto) ?></td>
                    <td class="r n">&ndash;</td>
                    <td class="r n"><?= $mutasiNeto > 0 ? '(' . $num($mutasiNeto) . ')' : $num($mutasiNeto) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="grand">
                <th>Saldo akhir</th>
                <td class="r n"><?= $cell($akhirCash) ?></td>
                <td class="r n"><?= $cell($akhirTf) ?></td>
                <td class="r n"><?= $cell($totalAkhir) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="sec">
    <table class="detail-wrap">
        <tr>
            <td class="c1">
                <div class="detail-title">Rekap transaksi</div>
                <table class="detail">
                    <tr>
                        <th style="width:46%;">URAIAN</th>
                        <th class="r n" style="width:18%;">QTY</th>
                        <th class="r n" style="width:36%;">NILAI</th>
                    </tr>
                    <tr>
                        <td>Unit (HP)</td>
                        <td class="r n"><?= $cell($qty_hp ?? 0) ?></td>
                        <td class="r n"><?= $cell($hp_total ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td>Servis</td>
                        <td class="r n"><?= $cell($qty_service ?? 0) ?></td>
                        <td class="r n"><?= $cell($service_total ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td>ACC</td>
                        <td class="r n"><?= $cell($qty_acc ?? 0) ?></td>
                        <td class="r n"><?= $cell($acc_total ?? 0) ?></td>
                    </tr>
                    <tr class="tot">
                        <td>Total</td>
                        <td class="r n"><?= $cell($totalQty) ?></td>
                        <td class="r n"><?= $cell($totalNilai) ?></td>
                    </tr>
                </table>
            </td>
            <td class="c2">
                <div class="detail-title">Rincian pengeluaran</div>
                <table class="detail">
                    <tr>
                        <th style="width:46%;">URAIAN</th>
                        <th class="r n" style="width:27%;">KAS</th>
                        <th class="r n" style="width:27%;">TRANSFER</th>
                    </tr>
                    <tr>
                        <td>Operasional</td>
                        <td class="r n"><?= $cell($op_cash ?? 0) ?></td>
                        <td class="r n"><?= $cell($op_tf ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td>Sparepart</td>
                        <td class="r n"><?= $cell($ps_cash ?? 0) ?></td>
                        <td class="r n"><?= $cell($ps_tf ?? 0) ?></td>
                    </tr>
                    <tr>
                        <td>Lain-lain</td>
                        <td class="r n"><?= $cell($lainCash) ?></td>
                        <td class="r n"><?= $cell($lainTf) ?></td>
                    </tr>
                    <tr class="tot">
                        <td>Total</td>
                        <td class="r n"><?= $cell($pengeluaranCash) ?></td>
                        <td class="r n"><?= $cell($pengeluaranTf) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="notes">
    Selisih = kas hasil hitung fisik &minus; kas menurut sistem.<br>
    Saldo akhir = saldo awal + pendapatan &minus; pengeluaran &minus; setor + tarik.
</div>

<div class="sign-rule"></div>
<table class="sign">
    <tr>
        <td class="col">
            <div class="role">Dibuat oleh,</div>
            <table class="sign-inner">
                <tr><td class="space">&nbsp;</td></tr>
                <tr><td class="line"><?= esc($namaPenutup !== '' ? $namaPenutup : '____________') ?></td></tr>
                <tr><td class="jabatan"><?= esc($jabatanPenutup !== '' ? $jabatanPenutup : 'Kasir') ?></td></tr>
            </table>
        </td>
        <td class="col">
            <div class="role">Mengetahui,</div>
            <table class="sign-inner">
                <tr><td class="space">&nbsp;</td></tr>
                <tr><td class="line"><?= esc(($spv_nama ?? '') !== '' ? $spv_nama : '____________') ?></td></tr>
                <tr><td class="jabatan">SPV</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="foot">
    <tr>
        <td>Rincian transaksi dihitung saat cetak; arus kas mengikuti record closing.</td>
        <td class="r n">Dicetak <?= esc($dicetakLabel) ?></td>
    </tr>
</table>

</body>
</html>
