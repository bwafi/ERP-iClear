<?php
helper('terbilang');

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$periode = (string) ($periode ?? date('Y-m'));
[$tahun, $bulan] = array_map('intval', explode('-', $periode));
$periodeLabel = ($namaBulan[$bulan] ?? '-') . ' ' . $tahun;

$tanggalTs = strtotime((string) ($invoice_date ?? date('Y-m-d')));
$tanggalLabel = $tanggalTs
    ? date('d', $tanggalTs) . ' ' . ($namaBulan[(int) date('n', $tanggalTs)] ?? '-') . ' ' . date('Y', $tanggalTs)
    : (string) ($invoice_date ?? '-');

$royalti          = (int) round($royalti ?? 0);
$gajiCabangTotal  = (int) round($gaji_cabang_total ?? 0);
$hoTotal          = (int) round($ho_total ?? 0);
$total            = $royalti + $gajiCabangTotal + $hoTotal;
$profitNett       = (int) round($profit_nett ?? 0);
$tanggunganAsset  = (int) round($tanggungan_asset ?? 0);

$rp = static function ($angka): string {
    return 'Rp ' . number_format((int) $angka, 0, ',', '.');
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice Internal</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            margin: 0;
        }
        .accent {
            height: 4px;
            background: #0f2a43;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: top;
        }
        .company {
            font-size: 15pt;
            font-weight: bold;
            color: #0f2a43;
            letter-spacing: .3px;
        }
        .addr {
            color: #64748b;
            font-size: 8.5pt;
            line-height: 1.45;
        }
        .doc {
            text-align: right;
        }
        .doc-title {
            font-size: 17pt;
            font-weight: bold;
            color: #0f2a43;
            letter-spacing: .5px;
        }
        .doc-no {
            color: #64748b;
            font-size: 9pt;
            margin-top: 3px;
        }
        .rule {
            border-top: 1px solid #cbd5e1;
            margin: 14px 0;
        }
        .meta td {
            padding: 2px 0;
            vertical-align: top;
            font-size: 9.5pt;
        }
        .meta .k {
            width: 26%;
            color: #64748b;
        }
        .meta .v {
            width: 34%;
            color: #0f172a;
        }
        .section {
            margin-top: 18px;
            font-size: 9pt;
            font-weight: bold;
            color: #0f2a43;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .items {
            margin-top: 6px;
            border: 1px solid #cbd5e1;
        }
        .items th {
            background: #0f2a43;
            color: #ffffff;
            font-size: 9pt;
            text-align: left;
            padding: 7px 8px;
            border: 1px solid #0f2a43;
        }
        .items td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9.5pt;
        }
        .items .no {
            width: 5%;
            text-align: center;
        }
        .items .amt {
            width: 19%;
            text-align: right;
            white-space: nowrap;
        }
        .items .comp {
            width: 24%;
        }
        .items td.comp {
            font-weight: bold;
            color: #0f172a;
        }
        .desc {
            color: #475569;
            line-height: 1.45;
        }
        .calc {
            margin-top: 3px;
            color: #94a3b8;
            font-size: 8pt;
        }
        .sub {
            margin-top: 2px;
        }
        .sub td {
            border: none;
            padding: 1px 0;
            font-size: 9pt;
            color: #334155;
        }
        .sub .sub-amt {
            text-align: right;
            white-space: nowrap;
        }
        .items tfoot td {
            background: #f1f5f9;
            font-weight: bold;
            font-size: 10pt;
            color: #0f2a43;
            padding: 10px 8px;
            border: 1px solid #cbd5e1;
        }
        .terbilang {
            margin-top: 10px;
            padding: 9px 11px;
            background: #f8fafc;
            border-left: 3px solid #0f2a43;
            font-size: 9pt;
            color: #334155;
        }
        .pay {
            margin-top: 18px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        .pay td {
            vertical-align: top;
            font-size: 9.5pt;
        }
        .pay .pay-title {
            width: 26%;
            color: #64748b;
        }
        .note {
            margin-top: 8px;
            font-size: 8.5pt;
            color: #64748b;
            line-height: 1.5;
        }
        .sign {
            margin-top: 34px;
            text-align: center;
            page-break-inside: avoid;
        }
        .sign td {
            width: 50%;
            font-size: 9.5pt;
            color: #334155;
        }
        .sign .space {
            height: 52px;
        }
        .sign .line {
            display: inline-block;
            border-top: 1px solid #475569;
            padding-top: 3px;
            min-width: 150px;
            color: #0f172a;
            font-weight: bold;
        }
        .foot {
            margin-top: 18px;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="accent"></div>

    <table class="header">
        <tr>
            <td>
                <div class="company"><?= esc($nama_usaha ?? 'CV. ICLEAR DIGITAL SOLUSI') ?></div>
                <div class="addr"><?= esc($alamat_usaha ?? '') ?></div>
                <div class="addr">Telp. <?= esc($telp_usaha ?? '-') ?></div>
            </td>
            <td class="doc">
                <div class="doc-title">INVOICE INTERNAL</div>
                <div class="doc-no">No. <?= esc($invoice_no ?? '-') ?></div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="meta">
        <tr>
            <td class="k">Periode</td>
            <td class="v">: <?= esc($periodeLabel) ?></td>
            <td class="k">Tanggal Invoice</td>
            <td class="v">: <?= esc($tanggalLabel) ?></td>
        </tr>
        <tr>
            <td class="k">Ditagihkan kepada</td>
            <td class="v" colspan="3">
                : <strong><?= esc($unit_nama ?? '-') ?></strong>
                <?php if (!empty($unit_alamat)): ?>
                    <br><span class="addr"><?= esc($unit_alamat) ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="section">Rincian Tagihan</div>

    <table class="items">
        <thead>
            <tr>
                <th class="no">No</th>
                <th class="comp">Komponen</th>
                <th>Keterangan</th>
                <th class="amt">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="no">1</td>
                <td class="comp">Royalti Perusahaan</td>
                <td class="desc">
                    Tagihan Center 40% dari laba bersih setelah tanggungan asset.
                    <div class="calc">
                        Laba bersih <?= $rp($profitNett) ?>
                        &middot; Tanggungan asset <?= $rp($tanggunganAsset) ?>
                    </div>
                </td>
                <td class="amt"><?= $rp($royalti) ?></td>
            </tr>
            <tr>
                <td class="no">2</td>
                <td class="comp">Gaji Karyawan Cabang</td>
                <td class="desc">
                    <?php if (!empty($gaji_cabang)): ?>
                        <table class="sub">
                            <?php foreach ($gaji_cabang as $gaji): ?>
                                <tr>
                                    <td><?= esc($gaji['nama'] ?? '-') ?></td>
                                    <td class="sub-amt"><?= $rp($gaji['nominal'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        Tidak ada gaji karyawan cabang pada periode ini.
                    <?php endif; ?>
                </td>
                <td class="amt"><?= $rp($gajiCabangTotal) ?></td>
            </tr>
            <tr>
                <td class="no">3</td>
                <td class="comp">Gaji Head Office</td>
                <td class="desc">
                    <?php if (!empty($ho_employees)): ?>
                        <table class="sub">
                            <?php foreach ($ho_employees as $ho): ?>
                                <tr>
                                    <td><?= esc($ho['nama'] ?? '-') ?></td>
                                    <td class="sub-amt"><?= $rp($ho['nominal'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        Bagian gaji Head Office untuk unit ini.
                    <?php endif; ?>
                </td>
                <td class="amt"><?= $rp($hoTotal) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAL TAGIHAN</td>
                <td class="amt"><?= $rp($total) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="terbilang">
        <strong>Terbilang:</strong> <?= esc(terbilang_rupiah($total)) ?>
    </div>

    <table class="pay">
        <tr>
            <td class="pay-title">Pembayaran</td>
            <td>
                <strong><?= esc($bank['nama'] ?? '-') ?></strong>
                &middot; <?= esc($bank['norek'] ?? '-') ?><br>
                a.n. <?= esc($bank['atas_nama'] ?? '-') ?>
            </td>
        </tr>
    </table>

    <div class="note"><?= esc($catatan ?? '') ?></div>

    <table class="sign">
        <tr>
            <td>Finance<div class="space"></div><span class="line">(____________________)</span></td>
            <td>Manager<div class="space"></div><span class="line">(____________________)</span></td>
        </tr>
    </table>

    <div class="foot">Dokumen ini dibuat otomatis oleh ERP iClear.</div>
</body>
</html>
