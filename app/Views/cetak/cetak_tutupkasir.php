<style>
    body {
        background: #e5e5e5;
        font-family: Arial, sans-serif;
    }

    .laporan-container {
        width: 700px;
        margin: 30px auto;
        background: #fff;
        padding: 35px;
        color: #000;
        font-size: 14px;
        line-height: 1.7;
    }

    .judul {
        font-size: 22px;
        font-weight: bold;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .info p {
        margin: 2px 0;
    }

    hr {
        border: none;
        border-top: 1px solid #ccc;
        margin: 10px 0;
    }

    .section-title {
        font-weight: bold;
        margin-bottom: 10px;
    }

    ul {
        margin: 0;
        padding-left: 20px;
    }

    li {
        margin-bottom: 4px;
    }

    .bold {
        font-weight: bold;
    }

    .signature {
        margin-top: 35px;
    }

    .signature p {
        margin: 6px 0;
    }

    @media print {
        body {
            background: white;
        }

        .laporan-container {
            box-shadow: none;
            margin: 0;
            width: 100%;
        }
    }
</style>

<div class="laporan-container">
    <table width="100%">
        <tr>
            <td width="25%"><img src="https://iclear.my.id/assets/img/logo.png" style="height: 40px; display: left; margin: auto;"></td>
            <td width="75%">
                <div class="judul">Laporan Tutup Kasir Harian</div>
            </td>
        </tr>
    </table>
    <br>
    <br>
    <div class="info">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <b>Nama Toko</b> : iClear Store & Service<br>
                        <b>Tanggal</b> : <?= date('d-m-Y', strtotime($tutup->tanggal)) ?>
                    </td>
                    <td width="25%">
                    </td>
                    <td width="25%">
                        <b>Kasir</b> : <br>
                        <b>PIC</b> : 
                    </td>
                </tr>
            </table>
    </div>

    <hr>
    <table width=100%>
        <tr>
            <td with=100%>
                <div class="section-title">Saldo Awal Bank : Rp. <?= number_format(@$tutup->awal_transfer, 0, ',', '.') . ' ' ?></div>    
            </td>
        <tr>
            <td with=100%>
                <div class="section-title">Modal Awal Cash : Rp. <?= number_format(@$tutup->awal_cash, 0, ',', '.') . ' ' ?></div>
            <td>
        <tr>
    </table>
    <hr>
    <table width="100%" style="border:1px solid #000;">
        <tr>
            <td width="40%">
                <div class="section-title">Transakasi</div>
            </td>
            <td width="30%">
                <div class="section-title">QTY</div>
            </td>
            <td width="30%">
                <div class="section-title">Total</div>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <p>Pembelian Unit</p>
            </td>
            <td width="30%">
                <p><?= number_format(@$qty_hp, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$hp_total, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <p>Servis</p>
            </td>
            <td width="30%">
                <p><?= number_format(@$qty_service, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$service_total, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <p>ACC</p>
            </td>
            <td width="30%">
                <p><?= number_format(@$qty_acc, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$acc_total, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
    </table>
    <table width="100%">
        <tr>
            <td width="40%">
                <p>total</p>
            </td>
            <td width="30%">
                <p><?= number_format(@$qty_acc+$qty_hp+$qty_service, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$acc_total+$hp_total+$service_total, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
    </table>
    <hr>
    <table width="100%" style="border:1px solid #000;">
        <tr>
            <td width="40%">
                <div class="section-title">Pengeluaran</div>
            </td>
            <td width="30%">
                <div class="section-title">Cash</div>
            </td>
            <td width="30%">
                <div class="section-title">Transfer</div>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <p>Operasional</p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$op_cash, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$op_tf, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <p>Pembelian Sparepart</p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$ps_cash, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$ps_tf, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
    </table>
    <table width="100%">
        <tr>
            <td width="40%">
                <p>total</p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$op_cash+$ps_cash, 0, ',', '.') . ' ' ?></p>
            </td>
            <td width="30%">
                <p>Rp. <?= number_format(@$op_tf+$ps_tf, 0, ',', '.') . ' ' ?></p>
            </td>
        </tr>
    </table>
    <hr>
    <div class="section-title">Rekap Akhir</div>
    <ul>
        <li class="bold">Total cash masuk : Rp. <?= number_format(@$tutup->pendapatan_cash, 0, ',', '.') . ' ' ?></li>
        <li class="bold">Total tf masuk : Rp. <?= number_format(@$tutup->pendapatan_transfer, 0, ',', '.') . ' ' ?></li>
        <li class="bold">Total cash keluar : Rp. <?= number_format(@$tutup->pengeluaran_cash, 0, ',', '.') . ' ' ?></li>
        <li class="bold">Total tf keluar : Rp. <?= number_format(@$tutup->pengeluaran_transfer, 0, ',', '.') . ' ' ?></li>
    </ul>

    <hr>
    <div class="section-title">Saldo Akhir Cash : Rp. <?= number_format(@$tutup->akhir_cash, 0, ',', '.') . ' ' ?></div>
    <div class="section-title">Saldo Akhir Transfer : Rp. <?= number_format(@$tutup->akhir_transfer, 0, ',', '.') . ' ' ?></div>
    <hr>
        <p>Cash di Laci : Rp. <?= number_format(@$tutup->cash_laci, 0, ',', '.') . ' ' ?>, Selisih : Rp. <?= number_format(@$tutup->cash_laci-@$tutup->akhir_cash, 0, ',', '.') . ' ' ?></p>
    <hr>    

    <div class="section-title">Tanda Tangan</div>
    <table width="100%">
        <tr>
            <td width="25%">
                <div class="signature">
                    <p>Kasir,</p>
                    <br><br><br>
                    <p>(____________)</p> 
            </td>       
            <td width="25%">
            </td>
            <td width="25%">
            </td>
            <td width="25%">
                <p>Kepala Toko,</p>
                <br><br><br>
                <p>(____________)</p>
            </td>
    </div>
        </tr>
    </table>

</div>
