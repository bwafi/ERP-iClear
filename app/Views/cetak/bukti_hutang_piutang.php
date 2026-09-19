<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Bukti Hutang Piutang - <?= esc($row->kode) ?></title>
    <style>
        /* Pengaturan Print & Kertas */
        @page {
            margin: 25px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .bg-gray {
                background-color: #f4f5f7 !important;
            }

            .badge {
                border: 1px solid #333 !important;
            }
        }

        /* Tipografi Dasar */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.5;
            margin: 0;
            padding: 10px 20px;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* --- KOP SURAT --- */
        table.kop-surat {
            width: 100%;
            border-bottom: 3px double #222;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .kop-logo {
            width: 60px;
            text-align: left;
            vertical-align: middle;
        }

        .kop-logo img {
            height: 40px;
            width: auto;
            display: block;
        }

        .kop-info {
            text-align: right;
            vertical-align: middle;
        }

        .kop-info h1 {
            margin: 0 0 3px 0;
            font-size: 18px;
            letter-spacing: 0.5px;
            color: #111;
            text-transform: uppercase;
        }

        .kop-info p {
            margin: 0;
            font-size: 11px;
            color: #555;
        }

        /* --- JUDUL & NO DOKUMEN --- */
        table.doc-header {
            width: 100%;
            margin-bottom: 20px;
        }

        .doc-title h2 {
            margin: 0;
            font-size: 16px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .doc-title span {
            font-size: 12px;
            color: #666;
            font-weight: normal;
        }

        .doc-no-box {
            text-align: right;
        }

        .doc-no-box span {
            display: inline-block;
            background: #f4f5f7;
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        /* --- TABEL INFORMASI --- */
        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        table.info td {
            padding: 7px 10px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        table.info td.label {
            width: 18%;
            font-weight: bold;
            color: #444;
            font-size: 11px;
        }

        table.info td.value {
            width: 32%;
            font-size: 12px;
        }

        /* --- TABEL DATA / BOX --- */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            color: #333;
            border-bottom: 2px solid #222;
            display: inline-block;
            padding-bottom: 2px;
        }

        table.box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        table.box th,
        table.box td {
            border: 1px solid #222;
            padding: 8px 10px;
        }

        table.box th {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }

        table.box td {
            font-size: 12px;
        }

        /* Utilities */
        .text-center {
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .text-bold {
            font-weight: bold;
        }

        .bg-gray {
            background-color: #f4f5f7;
        }

        .badge {
            padding: 4px 12px;
            background: #fff;
            border: 1px solid #222;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- TANDA TANGAN --- */
        .sign-place {
            text-align: right;
            font-size: 12px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        table.sign {
            width: 100%;
            margin-top: 10px;
            page-break-inside: avoid;
        }

        table.sign td {
            text-align: center;
            vertical-align: bottom;
            width: 50%;
        }

        table.sign .sign-space {
            height: 90px;
        }

        table.sign .name {
            font-weight: bold;
            text-decoration: underline;
            font-size: 12px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        table.sign .role {
            color: #444;
            font-size: 11px;
        }

        /* --- FOOTER --- */
        .doc-footer {
            margin-top: 40px;
            padding-top: 8px;
            border-top: 1px dotted #aaa;
            font-size: 10px;
            color: #777;
            text-align: center;
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    <?php
    $rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
    $labelSumber = [
        'pembelian' => 'Hutang Supplier',
        'piutang_pelanggan' => 'Piutang Pelanggan',
        'kasbon' => 'Kasbon Pegawai',
        'piutang_legacy' => 'Piutang Pegawai',
    ];
    $status = \App\Services\Finance\HutangPiutangService::labelStatus($row->status);

    $detail = $detail ?? [];
    $dSumber = $detail['sumber_label'] ?? ($labelSumber[$row->sumber_tipe] ?? $row->sumber_tipe);
    $dPihakLabel = $detail['pihak_label'] ?? 'Nama Pihak';
    $dPihakNama = $detail['pihak_nama'] ?? $row->nama_pihak;
    $dRefLabel = $detail['referensi_label'] ?? null;
    $dRef = $detail['referensi'] ?? null;
    $dDetail = $detail['detail'] ?? ($row->uraian ?: null);
    $dItems = $detail['items'] ?? [];
    $dMeta = $detail['meta'] ?? [];
    ?>

    <div class="container">

        <!-- KOP SURAT PROFESIONAL -->
        <?php
        $alamatUnit = trim(implode(', ', array_filter([
            $unit->JALAN_UNIT ?? null,
            $unit->KABUPATEN_UNIT ?? null,
        ])), ', ');
        ?>
        <table class="kop-surat" cellpadding="0" cellspacing="0">
            <tr>
                <td class="kop-logo">
                    <img src="https://iclear.my.id/assets/img/logo.png" alt="Logo iClear" style="height: 40px; width: auto;">
                </td>
                <td class="kop-info">
                    <h1>CV. ICLEAR DIGITAL SOLUTION</h1>
                    <p><?= esc($alamatUnit ?: '-') ?></p>
                    <p>Phone: 0851 8327 0910</p>
                </td>
            </tr>
        </table>

        <!-- JUDUL DAN NOMOR DOKUMEN -->
        <table class="doc-header" cellpadding="0" cellspacing="0">
            <tr>
                <td class="doc-title">
                    <h2>BUKTI HUTANG PIUTANG</h2>
                    <span>Jenis: <?= esc($dSumber) ?></span>
                </td>
                <td class="doc-no-box">
                    <span>No. <?= esc($row->kode) ?></span>
                </td>
            </tr>
        </table>

        <!-- DETAIL TRANSAKSI (identitas dari sumber transaksi) -->
        <table class="info">
            <tr>
                <td class="label bg-gray">Jenis Transaksi</td>
                <td class="value text-bold"><?= esc($dSumber) ?></td>

                <td class="label bg-gray"><?= esc($dPihakLabel) ?></td>
                <td class="value text-bold"><?= esc($dPihakNama) ?></td>
            </tr>
            <tr>
                <td class="label bg-gray">Tanggal Transaksi</td>
                <td class="value"><?= $row->tanggal ? date('d-m-Y', strtotime($row->tanggal)) : '-' ?></td>

                <td class="label bg-gray">Jatuh Tempo</td>
                <td class="value"><?= $row->jatuh_tempo ? date('d-m-Y', strtotime($row->jatuh_tempo)) : '-' ?></td>
            </tr>
            <tr>
                <td class="label bg-gray">No. Dokumen</td>
                <td class="value"><?= esc($row->kode) ?></td>

                <td class="label bg-gray"><?= esc($dRefLabel ?: 'No. Referensi / Transaksi') ?></td>
                <td class="value"><?= esc($dRef ?: '-') ?></td>
            </tr>
            <?php foreach ($dMeta as $m) : ?>
                <tr>
                    <td class="label bg-gray"><?= esc($m['label']) ?></td>
                    <td class="value" colspan="3"><?= esc($m['value']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td class="label bg-gray">Detail Transaksi / Keperluan</td>
                <td class="value" colspan="3"><?= esc($dDetail ?: '-') ?></td>
            </tr>
            <tr>
                <td class="label bg-gray">Keterangan</td>
                <td class="value" colspan="3"><?= esc($row->keterangan ?: '-') ?></td>
            </tr>
            <tr>
                <td class="label bg-gray">Status</td>
                <td class="value" colspan="3"><span class="badge"><?= esc($status) ?></span></td>
            </tr>
        </table>

        <!-- DETAIL BARANG / JASA DARI TRANSAKSI SUMBER -->
        <?php if (!empty($dItems)) : ?>
            <div class="section-title">Detail Barang / Jasa</div>
            <table class="box">
                <thead>
                    <tr class="bg-gray">
                        <th>Nama Barang / Jasa</th>
                        <th width="14%" class="text-center">Qty</th>
                        <th width="20%" class="text-end">Harga</th>
                        <th width="24%" class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dItems as $it) : ?>
                        <tr>
                            <td><?= esc($it['nama']) ?></td>
                            <td class="text-center"><?= (int) $it['qty'] ?><?= !empty($it['satuan']) ? ' ' . esc($it['satuan']) : '' ?></td>
                            <td class="text-end"><?= $rp($it['harga']) ?></td>
                            <td class="text-end text-bold"><?= $rp($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- RINGKASAN NOMINAL -->
        <div class="section-title">Ringkasan Saldo</div>
        <table class="box">
            <tr class="bg-gray">
                <th width="33%">Total Tagihan</th>
                <th width="33%">Total Dibayar</th>
                <th width="34%">Sisa Saldo Akhir</th>
            </tr>
            <tr>
                <td class="text-end text-bold"><?= $rp($row->total) ?></td>
                <td class="text-end"><?= $rp($row->total_dibayar) ?></td>
                <td class="text-end text-bold" style="font-size: 13px;"><?= $rp($row->sisa) ?></td>
            </tr>
        </table>

        <!-- RIWAYAT PEMBAYARAN -->
        <?php if (!empty($pembayaran)) : ?>
            <div class="section-title">Rincian Pembayaran</div>
            <table class="box">
                <thead>
                    <tr class="bg-gray">
                        <th width="12%">Tanggal</th>
                        <th width="18%" class="text-end">Tunai</th>
                        <th width="18%" class="text-end">Bank</th>
                        <th width="20%" class="text-end">Jumlah Bayar</th>
                        <th width="32%">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pembayaran as $p) : ?>
                        <tr>
                            <td class="text-center"><?= $p->tanggal_bayar ? date('d-m-Y', strtotime($p->tanggal_bayar)) : '-' ?></td>
                            <td class="text-end"><?= $rp($p->bayar_tunai) ?></td>
                            <td class="text-end"><?= $rp($p->bayar_bank) ?></td>
                            <td class="text-end text-bold"><?= $rp($p->jumlah_bayar) ?></td>
                            <td><?= esc($p->keterangan ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- TEMPAT & TANGGAL TTD -->
        <div class="sign-place">
            <?= !empty($unit->KABUPATEN_UNIT) ? esc($unit->KABUPATEN_UNIT) . ', ' : '' ?><?= date('d F Y') ?>
        </div>

        <!-- TANDA TANGAN -->
        <table class="sign">
            <tr>
                <td>
                    <div class="role">Dibuat Oleh,</div>
                    <div class="role"><b>FINANCE ICLEAR</b></div>
                    <div class="sign-space"></div>
                    <div class="name">( ________________________ )</div>
                </td>
                <td>
                    <div class="role">Mengetahui / Disetujui Oleh,</div>
                    <div class="role"><b>MANAGER</b></div>
                    <div class="sign-space"></div>
                    <div class="name">( ________________________ )</div>
                </td>
            </tr>
        </table>

        <!-- FOOTER DOKUMEN -->
        <div class="doc-footer">
            Dokumen ini dicetak otomatis dari Sistem ERP iClear pada <?= date('d/m/Y H:i') ?> WIB. <br>
            Dokumen ini sah dan valid meski tanpa cap/stempel basah selama tervalidasi di dalam sistem.
        </div>

    </div>

</body>

</html>
