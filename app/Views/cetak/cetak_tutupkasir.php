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
            font-size: 9.5pt;
            color: #111;
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
        .head-name { font-size: 14pt; font-weight: bold; }
        .head-unit { margin-top: 3px; font-size: 10pt; font-weight: bold; }
        .head-addr { margin-top: 3px; font-size: 8pt; line-height: 1.5; }
        .head-doc  { font-size: 13pt; font-weight: bold; }
        .head-meta { margin-top: 4px; font-size: 9pt; line-height: 1.55; }
        .head-rule { margin-top: 12px; border-top: 1.2pt solid #111; }

        .currency {
            margin-top: 10px;
            font-size: 7.5pt;
            text-align: right;
        }

        /* Buku kas */
        .lg td {
            padding: 6px 0;
            font-size: 10.5pt;
        }
        .lg td.lbl { padding-right: 16px; }
        .lg tr.ch td {
            padding: 7px 0 5px;
            border-bottom: 1pt solid #111;
            font-size: 8pt;
            font-weight: bold;
        }
        .lg tr.gap td { padding-top: 13px; }
        .lg tr.grand td {
            padding-top: 8px;
            border-top: 1.8pt solid #111;
            font-size: 12pt;
            font-weight: bold;
        }

        /* Blok bawah: rincian di kiri, hitung laci di kanan */
        .cols { margin-top: 30px; }
        .cols td.c1 { width: 50%; padding-right: 26px; }
        .cols td.c2 { width: 50%; }

        .blk-title {
            padding-bottom: 4px;
            border-bottom: 0.8pt solid #111;
            font-size: 9.5pt;
            font-weight: bold;
        }
        .blk { margin-top: 4px; }
        .blk td {
            padding: 3.5px 0;
            font-size: 8.5pt;
        }
        .blk td.lbl { padding-right: 10px; }
        .blk tr.ch td {
            padding-bottom: 3px;
            border-bottom: 0.6pt solid #111;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .blk tr.tot td {
            padding-top: 5px;
            border-top: 0.6pt solid #111;
            font-weight: bold;
        }

        /* Hitung laci */
        .count { margin-top: 34px; }
        .count tr.head td {
            padding-bottom: 4px;
            border-bottom: 0.8pt solid #111;
            font-size: 9.5pt;
            font-weight: bold;
        }
        .count td {
            padding: 4.5px 0;
            font-size: 9.5pt;
        }
        .count td.lbl { width: 58%; }
        .count td.val { width: 42%; }
        .count tr.diff td {
            padding-top: 8px;
            border-top: 0.8pt solid #111;
            font-size: 11pt;
            font-weight: bold;
        }
        .count tr.stamp td {
            padding-top: 2px;
            font-size: 10pt;
            font-weight: bold;
            text-align: right;
        }

        /* Tanda tangan */
        .sign { margin-top: 64px; }
        .sign td.role { font-size: 9pt; }
        .sign td.col { width: 50%; padding-right: 28px; }
        .sign-inner { width: 66%; }
        .sign-inner td.space { height: 38px; }
        .sign-inner td.line {
            border-top: 0.8pt solid #111;
            padding-top: 3px;
            font-size: 10pt;
            font-weight: bold;
        }
        .sign-inner td.jabatan {
            padding-top: 1px;
            font-size: 8.5pt;
        }

        /* Kaki */
        .foot {
            margin-top: 30px;
            padding-top: 5px;
            border-top: 0.6pt solid #111;
            font-size: 7.5pt;
        }
    </style>
</head>
<body>

<table class="head">
    <tr>
        <td style="width:56%;">
            <div class="head-name">iClear</div>
            <div class="head-unit"><?= esc($namaUnit) ?></div>
            <div class="head-addr">
                <?php if ($alamatUnit !== ''): ?><?= esc($alamatUnit) ?><br><?php endif; ?>
                <?php if ($teleponUnit !== ''): ?>Telp. <?= esc($teleponUnit) ?><?php endif; ?>
            </div>
        </td>
        <td class="r">
            <div class="head-doc">Tutup Kasir #<?= (int) ($tutup->idtutupkasir ?? 0) ?></div>
            <div class="head-meta">
                <?= esc($tanggalLabel) ?> &middot; <?= esc($waktuClosing) ?><br>
                <?= esc($namaPenutup !== '' ? $namaPenutup : '-') ?><?php if ($jabatanPenutup !== ''): ?> &middot; <?= esc($jabatanPenutup) ?><?php endif; ?><br>
                Status: <?= esc($statusLabel) ?>
            </div>
        </td>
    </tr>
</table>
<div class="head-rule"></div>

<div class="currency">Nilai dalam Rupiah</div>

<table class="lg">
    <tr class="ch">
        <td class="lbl" style="width:40%;">Uraian</td>
        <td class="r n" style="width:20%;">Kas</td>
        <td class="r n" style="width:20%;">Transfer</td>
        <td class="r n" style="width:20%;">Total</td>
    </tr>
    <tr>
        <td class="lbl">Saldo awal</td>
        <td class="r n"><?= $cell($awalCash) ?></td>
        <td class="r n"><?= $cell($awalTf) ?></td>
        <td class="r n"><?= $cell($totalAwal) ?></td>
    </tr>
    <tr class="gap">
        <td class="lbl">Pendapatan</td>
        <td class="r n"><?= $cell($pendapatanCash) ?></td>
        <td class="r n"><?= $cell($pendapatanTf) ?></td>
        <td class="r n"><?= $cell($totalPendapatan) ?></td>
    </tr>
    <tr>
        <td class="lbl">Pengeluaran</td>
        <td class="r n"><?= $out($pengeluaranCash) ?></td>
        <td class="r n"><?= $out($pengeluaranTf) ?></td>
        <td class="r n"><?= $out($totalPengeluaran) ?></td>
    </tr>
    <?php if ($mutasiNeto !== 0): ?>
        <tr>
            <td class="lbl"><?= $mutasiNeto > 0 ? 'Setor ke bank (neto)' : 'Tarik dari bank (neto)' ?></td>
            <td class="r n"><?= $mutasiNeto > 0 ? '(' . $num($mutasiNeto) . ')' : $num($mutasiNeto) ?></td>
            <td class="r n">&ndash;</td>
            <td class="r n"><?= $mutasiNeto > 0 ? '(' . $num($mutasiNeto) . ')' : $num($mutasiNeto) ?></td>
        </tr>
    <?php endif; ?>
    <tr class="grand">
        <td class="lbl">Saldo akhir</td>
        <td class="r n"><?= $cell($akhirCash) ?></td>
        <td class="r n"><?= $cell($akhirTf) ?></td>
        <td class="r n"><?= $cell($totalAkhir) ?></td>
    </tr>
</table>

<table class="cols">
    <tr>
        <td class="c1">
            <div class="blk-title">Rekap transaksi</div>
            <table class="blk">
                <tr class="ch">
                    <td class="lbl" style="width:46%;">Uraian</td>
                    <td class="r n" style="width:18%;">Qty</td>
                    <td class="r n" style="width:36%;">Nilai</td>
                </tr>
                <tr>
                    <td class="lbl">Unit (HP)</td>
                    <td class="r n"><?= $cell($qty_hp ?? 0) ?></td>
                    <td class="r n"><?= $cell($hp_total ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Servis</td>
                    <td class="r n"><?= $cell($qty_service ?? 0) ?></td>
                    <td class="r n"><?= $cell($service_total ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="lbl">ACC</td>
                    <td class="r n"><?= $cell($qty_acc ?? 0) ?></td>
                    <td class="r n"><?= $cell($acc_total ?? 0) ?></td>
                </tr>
                <tr class="tot">
                    <td class="lbl">Total</td>
                    <td class="r n"><?= $cell($totalQty) ?></td>
                    <td class="r n"><?= $cell($totalNilai) ?></td>
                </tr>
            </table>
        </td>
        <td class="c2">
            <div class="blk-title">Rincian pengeluaran</div>
            <table class="blk">
                <tr class="ch">
                    <td class="lbl" style="width:46%;">Uraian</td>
                    <td class="r n" style="width:27%;">Kas</td>
                    <td class="r n" style="width:27%;">Transfer</td>
                </tr>
                <tr>
                    <td class="lbl">Operasional</td>
                    <td class="r n"><?= $cell($op_cash ?? 0) ?></td>
                    <td class="r n"><?= $cell($op_tf ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Sparepart</td>
                    <td class="r n"><?= $cell($ps_cash ?? 0) ?></td>
                    <td class="r n"><?= $cell($ps_tf ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Lain-lain</td>
                    <td class="r n"><?= $cell($lainCash) ?></td>
                    <td class="r n"><?= $cell($lainTf) ?></td>
                </tr>
                <tr class="tot">
                    <td class="lbl">Total</td>
                    <td class="r n"><?= $cell($pengeluaranCash) ?></td>
                    <td class="r n"><?= $cell($pengeluaranTf) ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="count">
    <tr class="head">
        <td class="lbl">Hitung laci</td>
        <td class="r">&nbsp;</td>
    </tr>
    <tr>
        <td class="lbl">Kas menurut sistem</td>
        <td class="r n val"><?= $cell($akhirCash) ?></td>
    </tr>
    <tr>
        <td class="lbl">Kas hasil hitung fisik</td>
        <td class="r n val"><?= $cell($cashLaci) ?></td>
    </tr>
    <tr class="diff">
        <td class="lbl">Selisih</td>
        <td class="r n val"><?= $selisih < 0 ? '(' . $num($selisih) . ')' : $num($selisih) ?></td>
    </tr>
    <tr class="stamp">
        <td colspan="2"><?= esc($verdict) ?></td>
    </tr>
</table>

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
                <tr><td class="line"><?= esc($manager_nama !== '' ? $manager_nama : '____________') ?></td></tr>
                <tr><td class="jabatan">Manager</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="foot">
    <tr>
        <td>Rincian dihitung saat cetak; arus kas mengikuti record closing.</td>
        <td class="r">Dicetak <?= esc($dicetakLabel) ?></td>
    </tr>
</table>

</body>
</html>
