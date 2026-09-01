<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        @media print{

            .no-print{
                display:none;
            }

            body{
                margin:0;
            }

        }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        margin: 20px;
        color: #000;
    }

    .slip {
        width: 400px;
        margin: auto;
        border: 1px solid #000;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .header td {
        border: 1px solid #000;
        text-align: center;
        padding: 0px;
    }

    .company {
        font-size: 22px;
        font-weight: bold;
    }

    .alamat {
        font-size: 10px;
    }

    .judul {
        font-size: 16px;
        font-weight: bold;
    }

    .subjudul {
        font-size: 12px;
        font-weight: bold;
    }

    .periode {
        font-size: 12px;
    }

    .content {
        padding: 10px;
    }

    .info td {
        padding: 2px;
        vertical-align: top;
    }

    .section-title {
        font-weight: bold;
        padding-top: 10px;
        padding-bottom: 5px;
    }

    .penghasilan-potongan {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 40px;
    }

    .penghasilan,
    .potongan {
        width: calc(50% - 12.5px);
    }

    .detail td {
        padding: 2px;
    }

    .detail td:last-child {
        text-align: right;
    }

    .total {
        font-weight: bold;
    }

    .netto {
        margin-top: 10px;
        font-size: 14px;
        font-weight: bold;
    }

    .signature {
        margin-top: 40px;
        text-align: right;
    }

    .signature p {
        margin: 3px;
    }

    .note {
        margin-top: 30px;
        color: darkred;
        font-size: 11px;
        font-style: italic;
    }

    @media print {

        body {
            margin: 0;
        }

        .slip {
            border: 1px solid #000;
        }
    }
    </style>

</head>

<body>

    <div class="slip">

        <!-- Header -->
        <table class="header">
            <tr>
                <td>
                    <div class="judul">CV. ICLEAR DIGITAL SOLUSI</div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="alamat"><?=$unit['JALAN_UNIT']?></div>
                    <br/>
                    <div class="subjudul">SLIP GAJI KARYAWAN</div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="periode">
                        Periode: <?= '01 '.str_pad($bulan, 2, '0', STR_PAD_LEFT).' - '.date('t', strtotime($tahun.'-'.str_pad($bulan, 2, '0', STR_PAD_LEFT).'-01')).' '.str_pad($bulan, 2, '0', STR_PAD_LEFT).' '.$tahun?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="content">

            <!-- Data Pegawai -->
            <table class="info">
                <tr>
                    <td width="90">Nama</td>
                    <td width="10">:</td>
                    <td><?= $pegawai['NAMA_AKUN'] ?></td>
                </tr>

                <tr>
                    <td>NIP</td>
                    <td>:</td>
                    <td><?= $pegawai['NOID'] ?></td>
                </tr>

                <tr>
                    <td>Jabatan</td>
                    <td>:</td>
                    <td><?= $jabatan['NAMA_JABATAN'] ?></td>
                </tr>

                <tr>
                    <td>Status</td>
                    <td>:</td>
                    <td>Kontrak</td>
                </tr>
            </table>

            <br>

            <div class="penghasilan-potongan">

    <!-- Penghasilan -->
    <div class="penghasilan">

        <div class="section-title">PENGHASILAN</div>

        <table class="detail">

            <tr>
                <td>Gaji Pokok</td>
                <td>Rp<?= number_format($gaji_pokok,0,',','.') ?></td>
            </tr>

            <tr>
                <td>T. Kinerja</td>
                <td>Rp<?= number_format($tunjangan_kinerja,0,',','.') ?></td>
            </tr>

            <tr>
                <td>T. Absen</td>
                <td>Rp<?= number_format($tunjangan_absen,0,',','.') ?></td>
            </tr>

            <tr>
                <td>Insentif</td>
                <td>Rp<?= number_format($insentif,0,',','.') ?></td>
            </tr>

            <tr>
                <td>T. Penempatan</td>
                <td>Rp<?= number_format($tunjangan_penempatan,0,',','.') ?></td>
            </tr>

            <tr>
                <td>Lembur</td>
                <td>Rp<?= number_format($lembur ?? 0,0,',','.') ?></td>
            </tr>

            <tr class="total">
                <td>TOTAL (A)</td>
                <td>
                    Rp<?= number_format($gaji + $lembur,0,',','.') ?>
                </td>
            </tr>

        </table>

    </div>


    <!-- Potongan -->
    <div class="potongan">

            <div class="section-title">POTONGAN</div>

            <table class="detail">

                <tr>
                    <td>Bon</td>
                    <td>Rp<?= number_format($bon ?? 0,0,',','.') ?></td>
                </tr>

                <tr class="total">
                <td>TOTAL (B)</td>
                <td>
                    Rp<?= number_format($bon ?? 0,0,',','.') ?>
                </td>
            </tr>

            </table>

        </div>

    </div>

            

            <div style="clear:both"></div>

            <!-- Netto -->
            <table style="margin-top:20px;">
                <tr>
                    <td class="netto">
                        PENERIMAAN BERSIH (A-B)
                    </td>

                    <td class="netto" align="right">
                        Rp<?= number_format($gaji + $lembur - $bon,0,',','.') ?>
                    </td>
                </tr>
            </table>

            <!-- TTD -->
            <div class="signature">

                <p><?= date('d F Y') ?></p>
                <p>Supervisor</p>

                <br><br><br>

                <strong>NURUL HUDA</strong>

            </div>

        </div>

    </div>
    <div class="no-print" style="text-align:center;margin-bottom:15px;">

        <button onclick="window.print()" class="btn btn-primary">
            🖨 Print Slip Gaji
        </button>

    </div>
</body>

</html>