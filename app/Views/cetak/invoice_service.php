<?php
if (!function_exists('nota_val')) {
    function nota_val($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === null || $value === '') ? '-' : $value;
    }
}
?>
<style>
    body {
        margin: 0;
        padding: 10px;
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.2;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
    }

    table td,
    table th {
        padding: 2px 5px;
        vertical-align: top;
    }

    .left {
        text-align: left;
    }

    hr {
        border: 1px solid black;
        margin: 5px 0;
    }

    /* --- Nota: Ketentuan Service & Garansi --- */
    .nota-terms {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
        line-height: 1.4;
        color: #000;
    }

    .nota-terms td {
        padding: 0;
    }

    .nota-terms .nt-head {
        font-size: 10px;
        font-weight: bold;
        letter-spacing: .06em;
        border-bottom: 1px solid #000;
        padding-top: 26px;
        padding-bottom: 3px;
    }

    .nota-terms .nt-head.first {
        padding-top: 0;
    }

    .nota-terms .nt-num {
        width: 16px;
        padding-top: 3px;
        font-weight: bold;
        white-space: nowrap;
    }

    .nota-terms .nt-txt {
        padding-top: 3px;
        padding-right: 2px;
    }

    .nota-terms .nt-note {
        padding-top: 10px;
        font-style: italic;
    }

    .ttd td,
    .ttd th {
        padding-bottom: 15px;
    }

    table .kop:before {
        content: ': ';
    }

    .ttd td,
    .ttd th {
        padding-bottom: 4em;
    }

    .nowrap {
        white-space: nowrap;
    }

    /* Blok data service: label rata kiri, titik dua segaris, tanpa jarak berlebih */
    .hdr-data {
        width: auto;
        border-collapse: collapse;
    }

    .hdr-data td {
        padding: 1px 0;
        vertical-align: top;
    }

    .hdr-data .k {
        text-align: left;
        white-space: nowrap;
        padding-right: 3px;
    }

    .hdr-data .v {
        text-align: left;
    }
</style>
<div id="printable" class="container">
    <table cellpadding="0" cellspacing="0" width="485" class="border" style="overflow-x:auto;">

        <thead>
            <tr>
                <td style="text-align: left;" colspan="2"><img src="<?= base_url('template/assets/images/logo.png') ?>" style="height: 40px; display: left; margin: auto;"></td>
            </tr>
            <tr>
                <td style="text-align: left; width:50%;" class="nowrap" colspan="3"><?= @$dataunit->JALAN_UNIT . ', ' . @$dataunit->KABUPATEN_UNIT ?></td>
                <td style="text-align: left;" colspan="3">IG : <?= @$dataunit->KELURAHAN_UNIT?></td>
            </tr>
            <tr>
                <td style="text-align: left;" colspan="3"><?= @$dataunit->NOTELP_KOMPLAIN ?></td>
                <td style="text-align: left;" colspan="3">Tiktok : iclear.service</td>
            </tr>
            <tr>
                <td style="text-align: left;" colspan="3"></td>
                <td style="text-align: left;" colspan="3">Website : iclear.my.id</td>
            </tr>
            <tr>
                <td colspan="6">
                    <hr style="border: 1px solid black;">
                </td>
            </tr>
            <tr>
                <td style="text-align:left; vertical-align: top;" colspan="4">
                    <table class="hdr-data">
                        <tr>
                            <td class="k">No.Faktur</td>
                            <td class="v">: <?= nota_val(@$service->no_service) ?></td>
                        </tr>
                        <tr>
                            <td class="k">Tanggal</td>
                            <td class="v">: <?= @$service->created_at ? date('d-m-Y', strtotime($service->created_at)) : '-' ?></td>
                        </tr>
                        <tr>
                            <td class="k">Nama</td>
                            <td class="v">: <?= nota_val(@$human->nama_pelanggan) ?></td>
                        </tr>
                        <tr>
                            <td class="k">No.HP</td>
                            <td class="v">: <?= nota_val(@$human->no_hp) ?></td>
                        </tr>
                        <tr>
                            <td class="k">Tipe</td>
                            <td class="v">: <?= nota_val(@$service->tipe_hp) ?></td>
                        </tr>
                        <tr>
                            <td class="k">IMEI</td>
                            <td class="v">: <?= nota_val(@$service->imei) ?></td>
                        </tr>
                        <tr>
                            <td class="k">Passcode</td>
                            <td class="v">: <?= nota_val(@$service->passcode) ?></td>
                        </tr>
                        <tr>
                            <td class="k">Email</td>
                            <td class="v">: <?= nota_val(@$service->email_icloud) ?></td>
                        </tr>
                        <tr>
                            <td class="k">Password</td>
                            <td class="v">: <?= nota_val(@$service->password_icloud) ?></td>
                        </tr>
                    </table>
                </td>
                <td style="text-align:center; vertical-align: middle;" colspan="2">
                    <?php if (!empty($qrImageUrl)): ?>
                        <img src="<?= $qrImageUrl ?>" width="90" alt="QR Code">
                        <div style="font-size: 9px; margin-top: 3px;">Scan untuk melihat<br>detail service</div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <!-- KOLOM GAMBAR -->
                <td colspan="2" style="width:50%; text-align:left;">
                    <img src="<?= base_url('template/assets/images/hp.png') ?>" style="height: 200px;">
                </td>

                <?php
                $fungsiRusak = [];
                $adaKerusakanLain = false;
                
                foreach ($kerusakan as $krs) {
                    $nama = strtolower(trim($krs->nama_fungsi));
                    $fungsiRusak[] = $nama;
                
                    if ($nama == 'kerusakan lain') {
                        $adaKerusakanLain = true;
                    }
                }
                ?>

                <?php
                function ceklis($label, $data, $adaKerusakanLain){

                    // jika ada "Kerusakan Lain" → semua jadi kotak kosong
                    if ($adaKerusakanLain) {
                        return '☐';
                    }
                
                    foreach ($data as $item){
                        if (strpos($item, strtolower($label)) !== false) {
                            return '✖'; // rusak
                        }
                    }
                
                    return '✔'; // normal
                }
                ?>

                <!-- KOLOM FUNGSI -->
                <td colspan="2" style="width:25%; vertical-align: top; font-size:11px;">
                    <b>Fungsi :</b><br>
                    <?= ceklis('Signal', $fungsiRusak, $adaKerusakanLain) ?> Signal<br>
                    <?= ceklis('Wifi', $fungsiRusak, $adaKerusakanLain) ?> Wifi<br>
                    <?= ceklis('Bluetooth', $fungsiRusak, $adaKerusakanLain) ?> Bluetooth<br>
                    <?= ceklis('Volume Up', $fungsiRusak, $adaKerusakanLain) ?> Volume Up<br>
                    <?= ceklis('Volume Down', $fungsiRusak, $adaKerusakanLain) ?> Volume Down<br>
                    <?= ceklis('Tombol Home', $fungsiRusak, $adaKerusakanLain) ?> Tombol Home<br>
                    <?= ceklis('Kamera Depan + Mic', $fungsiRusak, $adaKerusakanLain) ?> Kamera Depan + Mic<br>
                    <?= ceklis('Kamera Belakang + Mic', $fungsiRusak, $adaKerusakanLain) ?> Kamera Belakang + Mic<br>
                    <?= ceklis('Flash Belakang', $fungsiRusak, $adaKerusakanLain) ?> Flash Belakang<br>
                    <?= ceklis('Finger Print', $fungsiRusak, $adaKerusakanLain) ?> Finger Print<br>
                    <?= ceklis('Getar', $fungsiRusak, $adaKerusakanLain) ?> Getar<br>
                    <?= ceklis('Silent', $fungsiRusak, $adaKerusakanLain) ?> Silent<br>
                    <?= ceklis('Touch / 3D', $fungsiRusak, $adaKerusakanLain) ?> Touch / 3D<br>
                </td>
                <td colspan="2" style="width:25%; vertical-align: top; font-size:11px;">
                    <br>
                    <?= ceklis('Speaker Phone', $fungsiRusak, $adaKerusakanLain) ?> Speaker Phone<br>
                    <?= ceklis('Speaker Big', $fungsiRusak, $adaKerusakanLain) ?> Speaker Big<br>
                    <?= ceklis('Brightness', $fungsiRusak, $adaKerusakanLain) ?> Brightness<br>
                    <?= ceklis('Mic Phone', $fungsiRusak, $adaKerusakanLain) ?> Mic Phone<br>
                    <?= ceklis('Tombol Power', $fungsiRusak, $adaKerusakanLain) ?> Tombol Power<br>
                    <?= ceklis('Face ID', $fungsiRusak, $adaKerusakanLain) ?> Face ID<br>
                    <?= ceklis('Truetone', $fungsiRusak, $adaKerusakanLain) ?> Truetone<br>
                    <?= ceklis('Proximity Sensor', $fungsiRusak, $adaKerusakanLain) ?> Proximity Sensor<br>
                    <?= ceklis('No Charging', $fungsiRusak, $adaKerusakanLain) ?> No Charger<br><br>
                        
                        <?php if ($adaKerusakanLain): ?>
                
                            <?php foreach ($kerusakan as $krs): ?>
                                <b style="text-decoration: underline;">Keterangan : <br> <?= $krs->keterangan ?></b><br>
                            <?php endforeach; ?>
                
                        <?php else: ?>
                
                            <b style="text-decoration: underline;">Keterangan :<br>  <?= @$service->keterangan ?></b>
                
                        <?php endif; ?>
                
                </td>
            </tr>
            <tr>
                <td colspan="6">
                    <hr style="border: 1px solid black;">
                </td>
            </tr>
            <tr>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="6" style="text-align:left;">
                    <table class="nota-terms" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="nt-head first" colspan="2">KETENTUAN SERVICE</td>
                        </tr>
                        <tr>
                            <td class="nt-num">1.</td>
                            <td class="nt-txt">Reparasi berhak kami tolak/cancel apabila: Gagal repair, rusak parah (cairan parah, putus jalur, berkarat, IC Besar), bagian mesin sudah tidak lengkap.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">2.</td>
                            <td class="nt-txt">Untuk kondisi handphone (mode DFU, matot, kena cairan, jatuh, putus jalur, IC mesin lemah, bekas service mesin, eror base band signal dll) tidak memungkinkan untuk pengecekan fungsi secara keseluruhan. Sehingga bisa menyebabkan fungsi handphone bisa menurun.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">3.</td>
                            <td class="nt-txt">Garansi uang kembali selama garansi jika iPhone tetap eror yang SAMA, atau karena CANCEL.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">4.</td>
                            <td class="nt-txt">Kami tidak bertanggung jawab atas data di dalam handphone.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">5.</td>
                            <td class="nt-txt">Barang yang sudah selesai dikerjakan akan dikonfirmasi sebelumnya, dan apabila barang tidak diambil lebih dari 1 bulan, jika terjadi kehilangan diluar tanggung jawab kami.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">6.</td>
                            <td class="nt-txt">Tanda terima wajib dibawa pada waktu pengambilan barang.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">7.</td>
                            <td class="nt-txt">Sparepart lama wajib dibawa customer / user.</td>
                        </tr>
                        <tr>
                            <td class="nt-head" colspan="2">KETENTUAN GARANSI</td>
                        </tr>
                        <tr>
                            <td class="nt-num">1.</td>
                            <td class="nt-txt">Garansi berlaku selama masa garansi, segel dan nota masih utuh.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">2.</td>
                            <td class="nt-txt">Garansi sparepart sesuai yang tertera di struk atau nota garansi, dihitung dari tanggal pengambilan barang.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">3.</td>
                            <td class="nt-txt">Garansi Mesin sesuai yang tertera di struk atau nota garansi, dihitung dari tanggal pengambilan barang.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">4.</td>
                            <td class="nt-txt">Garansi Replace LCD berlaku untuk cacat pabrik, dengan kondisi fisik LCD seperti baru (Touch Loncat, Blank, Touch No Respon). Garansi LCD tidak termasuk jika Garis Vertikal, Fisik Pecah, Retak, Dot, Black Dot, Flicker, Tergores, Air, Dead Pixel, dll.</td>
                        </tr>
                        <tr>
                            <td class="nt-num">5.</td>
                            <td class="nt-txt">Garansi Replace Baterai untuk kerusakan drop / boros, cacat pabrik. Garansi baterai tidak termasuk jika ada cacat fisik, bekas congkel, tusukan, goresan, sobekan di flexible.</td>
                        </tr>
                        <tr>
                            <td class="nt-note" colspan="2">
                                *Note : Klaim garansi hanya berlaku 1 (satu) kali.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="6">
                    <hr style="border: 1px solid black;">
                </td>
            </tr>
        </tbody>
        <tr class="ttd">
            <td colspan="1" style="text-align: center;">Teknisi</td>
            <td colspan="3"></td>
            <td colspan="2" style="text-align: center;">Customer</td>
        </tr>
        <tr class="ttd">
            <td colspan="1" style="text-align: center;"><?= nota_val(@$human->nama_service_by) ?></td>
            <td colspan="3"></td>
            <td colspan="2" style="text-align: center;">____________________</td>
        </tr>
    </table>
</div>