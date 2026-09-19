<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Nota Mutasi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 20px;
        }

        h2,
        h4 {
            text-align: center;
            margin: 0;
        }

        .info {
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .info p {
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table,
        th,
        td {
            border: 1px solid #000;
        }

        th,
        td {
            padding: 6px;
            text-align: left;
        }

        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            width: 40%;
        }

        .signature p {
            margin-top: 60px;
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>NOTA MUTASI STOK</h2>
        <h4>No. Nota: <?= esc($kode_mutasi ?? '') ?></h4>

        <div class="info">
            <p><strong>Kode Mutasi:</strong> <?= esc($kode_mutasi ?? '') ?></p>
            <p><strong>Tanggal:</strong> <?= date('d-m-Y', strtotime($tanggal ?? '')) ?></p>
            <p><strong>Unit Pengirim:</strong> <?= esc($pengirim ?? '') ?></p>
            <p><strong>Unit Penerima:</strong> <?= esc($penerima ?? '') ?></p>
            <p><strong>Input By:</strong> <?= esc($namainputer ?? '') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Produk</th>
                    <th>Jumlah Kirim</th>
                    <th>Jumlah Terima</th>
                    <th>Satuan</th>
                    <th>Harga Beli</th>
                    <th>Harga Jual</th>
                    <th>Harga Mutasi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data_produk)): ?>
                    <?php $no = 1;
                    foreach ($data_produk as $produk): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= esc($produk['nama']) ?></td>
                            <td><?= number_format($produk['jumlah_kirim'], 0, ',', '.') ?></td>
                            <td><?= number_format($produk['jumlah_terima'], 0, ',', '.') ?></td>
                            <td>pcs</td>
                            <td>
                                <?= ($produk['harga_beli'] == 0)
                                    ? '-'
                                    : number_format($produk['harga_beli'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <?= ($produk['harga_jual'] == 0)
                                    ? '-'
                                    : number_format($produk['harga_jual'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <?= ($produk['harga_mutasi'] == 0)
                                    ? '-'
                                    : number_format($produk['harga_mutasi'], 0, ',', '.'); ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">Tidak ada data produk</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature">
                <span><strong>Pengirim</strong></span>
                <p>(<?= esc($pengirim ?? '') ?>)</p>
            </div>
            <div class="signature">
                <span><strong>Penerima</strong></span>
                <p>(<?= esc($penerima ?? '') ?>)</p>
            </div>
        </div>
    </div>
</body>

</html>