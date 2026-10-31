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

$royalti         = (int) round($royalti ?? 0);
$gajiCabangTotal = (int) round($gaji_cabang_total ?? 0);
$hoTotal         = (int) round($ho_total ?? 0);
$total           = $royalti + $gajiCabangTotal + $hoTotal;

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
            font-family: 'dejavusanscondensed', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #102a43;
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .muted {
            color: #5b6672;
        }

        /* Masthead */
        .masthead td {
            vertical-align: top;
            padding: 0;
        }
        .brand-name {
            font-family: 'dejavusanscondensed', Helvetica, Arial, sans-serif;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: .01em;
            color: #0f2a43;
        }
        .brand-meta {
            margin-top: 4px;
            font-size: 8pt;
            line-height: 1.5;
            color: #5b6672;
        }
        .doc {
            text-align: right;
        }
        .doc-kicker {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #5b6672;
        }
        .doc-title {
            margin-top: 2px;
            font-family: 'dejavusanscondensed', Helvetica, Arial, sans-serif;
            font-size: 27pt;
            line-height: .95;
            color: #0f2a43;
        }
        .doc-no {
            margin-top: 5px;
            font-size: 8.5pt;
            color: #102a43;
        }
        .masthead-rule {
            margin-top: 14px;
            border-top: 2px solid #0f2a43;
        }

        /* Billed-to and document meta */
        .lede {
            margin-top: 14px;
        }
        .lede td {
            vertical-align: top;
            padding: 0;
        }
        .lede .billed {
            width: 58%;
            padding-right: 18px;
        }
        .lede .meta {
            width: 42%;
        }
        .eyebrow {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #5b6672;
        }
        .unit-name {
            margin-top: 5px;
            font-size: 13pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .unit-addr {
            margin-top: 3px;
            font-size: 8.5pt;
            line-height: 1.5;
            color: #5b6672;
        }
        .meta table td {
            padding: 2.5px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .meta .k {
            width: 36%;
            color: #5b6672;
        }
        .meta .v {
            text-align: right;
            color: #102a43;
        }

        /* Section head */
        .section {
            margin-top: 22px;
            padding-bottom: 6px;
            border-bottom: 1px solid #cfd8e3;
        }
        .section td {
            vertical-align: bottom;
            padding: 0;
        }
        .section-title {
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #0f2a43;
        }
        .section-note {
            text-align: right;
            font-size: 8pt;
            color: #5b6672;
        }

        /* Items */
        .items {
            margin-top: 0;
            table-layout: fixed;
        }
        .items thead th {
            padding: 7px 4px;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .1em;
            text-transform: uppercase;
            text-align: left;
            color: #5b6672;
            border-bottom: 1px solid #cfd8e3;
        }
        .items tbody td {
            padding: 12px 4px;
            vertical-align: top;
            border-bottom: 1px solid #e3e8ee;
            page-break-inside: avoid;
        }
        .items .no {
            width: 6%;
            font-size: 8pt;
            color: #5b6672;
        }
        .items .comp {
            width: 25%;
            font-size: 10pt;
            font-weight: bold;
            color: #102a43;
        }
        .items .desc {
            width: 47%;
            color: #5b6672;
            line-height: 1.5;
            font-size: 8.5pt;
        }
        .items .col-desc {
            width: 47%;
        }
        .items .amt {
            width: 22%;
            text-align: right;
            white-space: nowrap;
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f2a43;
        }

        /* Lampiran rincian gaji (halaman 2) */
        .gaji-matrix {
            margin-top: 12px;
        }
        .gaji-matrix th {
            font-size: 7.5pt;
            font-weight: bold;
            color: #5b6672;
            text-align: right;
            padding: 7px 5px;
            border-bottom: 1px solid #0f2a43;
        }
        .gaji-matrix td {
            font-size: 8pt;
            text-align: right;
            padding: 6px 5px;
            border-bottom: 1px solid #e3e8ee;
            white-space: nowrap;
            color: #102a43;
        }
        .gaji-matrix .col-nama,
        .gaji-matrix .col-jumlah {
            text-align: left;
        }
        .gaji-matrix .col-jumlah {
            text-align: right;
            font-weight: bold;
        }
        .gaji-matrix td.col-nama {
            white-space: normal;
            font-weight: bold;
        }
        .gaji-matrix tfoot td {
            border-top: 2px solid #0f2a43;
            border-bottom: none;
            font-weight: bold;
            color: #0f2a43;
        }
        .gaji-matrix .nol {
            color: #b0b8c1;
        }
        .gaji-note {
            margin-top: 10px;
            font-size: 7.5pt;
            line-height: 1.55;
            color: #5b6672;
        }
        .empty-note {
            color: #5b6672;
        }

        /* Total */
        .total {
            margin-top: 4px;
            border-top: 2px solid #0f2a43;
            padding-top: 10px;
        }
        .total td {
            vertical-align: top;
            padding: 0;
        }
        .total .words {
            width: 58%;
            padding-right: 18px;
            font-size: 8.5pt;
            line-height: 1.55;
            color: #5b6672;
        }
        .total .words strong {
            color: #102a43;
        }
        .total .sum {
            width: 42%;
            text-align: right;
        }
        .total .sum-label {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #5b6672;
        }
        .total .sum-value {
            margin-top: 2px;
            font-size: 17pt;
            font-weight: bold;
            color: #0f2a43;
        }

        /* Payment */
        .pay {
            margin-top: 24px;
        }
        .pay td {
            vertical-align: top;
            padding: 0;
        }
        .pay .pay-label {
            width: 22%;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #5b6672;
            padding-top: 3px;
        }
        .pay .bank-name {
            font-size: 10pt;
            font-weight: bold;
            color: #0f2a43;
        }
        .pay .bank-account {
            margin-top: 2px;
            font-size: 10.5pt;
            color: #102a43;
        }
        .pay .bank-owner {
            margin-top: 2px;
            font-size: 8.5pt;
            color: #5b6672;
        }
        .note {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e3e8ee;
            font-size: 8pt;
            line-height: 1.55;
            color: #5b6672;
        }

        /* Signatures */
        .sign {
            width: 100%;
            margin-top: 34px;
            border-collapse: collapse;
        }
        .sign-cell {
            width: 60mm;
            vertical-align: top;
            padding: 0;
        }
        .sign-gap {
            width: 30mm;
        }
        .sign-role {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #5b6672;
        }
        .sign-line {
            width: 100%;
            border-collapse: collapse;
        }
        .sign-line-cell {
            height: 40px;
            border-bottom: 1px solid #7b8794;
            padding: 0;
            font-size: 8.5pt;
        }
        .sign-name {
            margin-top: 4px;
            font-size: 8.5pt;
            color: #102a43;
        }

        .foot {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e3e8ee;
            font-size: 7pt;
            color: #5b6672;
        }
        .foot .right {
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="masthead">
        <tr>
            <td>
                <div class="brand-name"><?= esc($nama_usaha ?? 'CV. ICLEAR DIGITAL SOLUSI') ?></div>
                <div class="brand-meta">
                    <?= esc($alamat_usaha ?? '') ?>
                    <?php if (!empty($telp_usaha)): ?>
                        <br>Telp. <?= esc($telp_usaha) ?>
                    <?php endif; ?>
                </div>
            </td>
            <td class="doc">
                <div class="doc-kicker">Tagihan Internal</div>
                <div class="doc-title">Invoice</div>
                <div class="doc-no">No. <?= esc($invoice_no ?? '-') ?></div>
            </td>
        </tr>
    </table>
    <div class="masthead-rule"></div>

    <table class="lede">
        <tr>
            <td class="billed">
                <div class="eyebrow">Ditagihkan kepada</div>
                <div class="unit-name"><?= esc($unit_nama ?? '-') ?></div>
                <?php if (!empty($unit_alamat)): ?>
                    <div class="unit-addr"><?= esc($unit_alamat) ?></div>
                <?php endif; ?>
            </td>
            <td class="meta">
                <table>
                    <tr>
                        <td class="k">Periode</td>
                        <td class="v"><?= esc($periodeLabel) ?></td>
                    </tr>
                    <tr>
                        <td class="k">Tanggal invoice</td>
                        <td class="v"><?= esc($tanggalLabel) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td class="section-title">Rincian Tagihan</td>
            <td class="section-note">3 komponen</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="no">No</th>
                <th class="comp">Komponen</th>
                <th class="col-desc">Keterangan</th>
                <th class="amt">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="no">01</td>
                <td class="comp">Royalti Perusahaan</td>
                <td class="desc"></td>
                <td class="amt"><?= $rp($royalti) ?></td>
            </tr>
            <tr>
                <td class="no">02</td>
                <td class="comp">Gaji Karyawan Cabang</td>
                <td class="desc">
                    <?php if (!empty($gaji_cabang)): ?>
                        <span class="empty-note">Rincian per karyawan di halaman berikutnya.</span>
                    <?php else: ?>
                        <span class="empty-note">Tidak ada gaji karyawan cabang pada periode ini.</span>
                    <?php endif; ?>
                </td>
                <td class="amt"><?= $rp($gajiCabangTotal) ?></td>
            </tr>
            <tr>
                <td class="no">03</td>
                <td class="comp">Gaji Head Office</td>
                <td class="desc">
                    <?php if (!empty($ho_employees)): ?>
                        <span class="empty-note">Rincian per karyawan di halaman berikutnya.</span>
                    <?php elseif (!empty($unit_trial)): ?>
                        <span class="empty-note">Unit trial &mdash; tidak menanggung gaji HO.</span>
                    <?php else: ?>
                        <span class="empty-note">Bagian gaji Head Office untuk unit ini.</span>
                    <?php endif; ?>
                </td>
                <td class="amt"><?= $rp($hoTotal) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="total">
        <tr>
            <td class="words">
                <strong>Terbilang:</strong> <?= esc(terbilang_rupiah($total)) ?>
            </td>
            <td class="sum">
                <div class="sum-label">Total Tagihan</div>
                <div class="sum-value"><?= $rp($total) ?></div>
            </td>
        </tr>
    </table>

    <table class="pay">
        <tr>
            <td class="pay-label">Pembayaran</td>
            <td>
                <div class="bank-name"><?= esc($bank['nama'] ?? '-') ?></div>
                <div class="bank-account"><?= esc($bank['norek'] ?? '-') ?></div>
                <div class="bank-owner">a.n. <?= esc($bank['atas_nama'] ?? '-') ?></div>
            </td>
        </tr>
    </table>

    <?php if (!empty($catatan)): ?>
        <div class="note"><?= esc($catatan) ?></div>
    <?php endif; ?>

    <table class="sign">
        <tr>
            <td class="sign-cell">
                <div class="sign-role">Finance</div>
                <table class="sign-line"><tr><td class="sign-line-cell">&nbsp;</td></tr></table>
                <div class="sign-name"><?= esc($finance_nama ?? '') ?></div>
            </td>
            <td class="sign-gap"></td>
            <td class="sign-cell">
                <div class="sign-role">Manager</div>
                <table class="sign-line"><tr><td class="sign-line-cell">&nbsp;</td></tr></table>
                <div class="sign-name"><?= esc($manager_nama ?? '') ?></div>
            </td>
        </tr>
    </table>

    <table class="foot">
        <tr>
            <td>Dokumen ini dibuat otomatis oleh ERP iClear.</td>
            <td class="right"><?= esc($invoice_no ?? '') ?></td>
        </tr>
    </table>

    <?php if (!empty($gaji_cabang) || !empty($ho_employees)): ?>
        <pagebreak />

        <table class="masthead">
            <tr>
                <td>
                    <div class="brand-name"><?= esc($nama_usaha ?? 'CV. ICLEAR DIGITAL SOLUSI') ?></div>
                    <div class="brand-meta">
                        <?= esc($alamat_usaha ?? '') ?>
                        <?php if (!empty($telp_usaha)): ?>
                            <br>Telp. <?= esc($telp_usaha) ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="doc">
                    <div class="doc-kicker">Lampiran Invoice</div>
                    <div class="doc-title">Rincian Gaji</div>
                    <div class="doc-no">No. <?= esc($invoice_no ?? '-') ?></div>
                </td>
            </tr>
        </table>
        <div class="masthead-rule"></div>

        <?php if (!empty($gaji_cabang)): ?>
            <table class="section">
                <tr>
                    <td class="section-title">Rincian Gaji Karyawan Cabang</td>
                    <td class="section-note"><?= esc($periodeLabel) ?> &middot; <?= esc($unit_nama ?? '-') ?></td>
                </tr>
            </table>
            <?= view('cetak/invoice_matriks', ['rows' => $gaji_cabang, 'total' => $gajiCabangTotal]) ?>
        <?php endif; ?>

        <?php if (!empty($ho_employees)): ?>
            <table class="section">
                <tr>
                    <td class="section-title">Rincian Gaji Head Office</td>
                    <td class="section-note">
                        <?php if (empty($gaji_cabang)): ?>
                            <?= esc($periodeLabel) ?> &middot; <?= esc($unit_nama ?? '-') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?= view('cetak/invoice_matriks', ['rows' => $ho_employees, 'total' => $hoTotal]) ?>
        <?php endif; ?>

        <div class="gaji-note">
            Nominal aktual (termasuk bonus/potongan) menggantikan perkiraan karyawan yang sudah dibayar; karyawan lain tetap perkiraan.
            <strong>Jumlah</strong> adalah beban gaji yang ditagihkan ke unit ini.
        </div>

        <table class="foot">
            <tr>
                <td>Lampiran <?= esc($invoice_no ?? '') ?> &middot; dibuat otomatis oleh ERP iClear.</td>
                <td class="right"><?= esc($unit_nama ?? '') ?></td>
            </tr>
        </table>
    <?php endif; ?>
</body>
</html>
