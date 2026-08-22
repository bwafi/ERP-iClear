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

    .syarat {
        font-size: 10px;
        line-height: 1.3;
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
</style>
<div id="printable" class="container">
    <table cellpadding="0" cellspacing="0" width="485" class="border" style="overflow-x:auto;">

        <thead>
            <tr>
                <td style="text-align: left;" colspan="2"><img src="https://iclear.my.id/assets/img/logo.png" style="height: 40px; display: left; margin: auto;"></td>
            </tr>
            <tr>
                <td style="text-align: left;" colspan="2">No.Faktur : <?= @$service->no_service ?></td>
                <td colspan="2"></td>
                <td style="font-size: 10px;" colspan="2">Nama : <?= @$human->nama_pelanggan ?></td>
            </tr>
            <tr>
                <td style="text-align: left; width:50%;" class="nowrap" colspan="2"><?= @$dataunit->JALAN_UNIT . ', ' . @$dataunit->KABUPATEN_UNIT ?></td>
                <td style="text-align: left;" colspan="2">IG : <?= @$dataunit->KELURAHAN_UNIT?></td>
                <td style="font-size: 10px;" colspan="2">No.HP : <?= @$human->no_hp ?></td>
            </tr>
            <tr>
                <td style="text-align: left;" colspan="2">0851 8327 0910</td>
                <td style="text-align: left;" colspan="2">Tiktok : iclear.service</td>
                <td style="font-size: 10px;" colspan="2">Tanggal : <?= date('d-m-Y', strtotime(@$service->created_at)) ?></td>
            </tr>
            <tr>
                <td colspan="6">
                    <hr style="border: 1px solid black;">
                </td>
            </tr>
            <tr>
                <td style="text-align:left; width:5px;" colspan="1">Tipe : <?= @$service->tipe_hp ?></td>
                <td class="left kop"></td>
                <td></td>
                <td></td>
                <td style="text-align:center;">iCloud</td>
            </tr>
            <tr>
                <td style="text-align:left; width:5px;">IMEI : <?= @$service->imei ?></td>
                <td class="left kop"></td>
                <td colspan="2"></td>
                <td style="text-align:left; width:5px; font-size: 10px;" colspan="2">Email : <?= @$service->email_icloud ?></td>
            </tr>
            <tr>
                <td style="text-align:left; width:5px;">Passcode : <?= @$service->passcode ?></td>
                <td class="left kop"></td>
                <td></td>
                <td></td>
                <td style="text-align:left; font-size: 10px;" colspan="2">Password : <?= @$service->password_icloud ?></td>
            </tr>
            <tr>
                <!-- KOLOM GAMBAR -->
                <td colspan="2" style="width:50%; text-align:left;">
                    <img src="https://iclear.my.id/assets/img/hp.png" style="height: 200px;">
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
                <td colspan="4" style="text-align:left;">
                    Ketentuan Garansi Iphone Hangus :<br>
                    1. Masa Gransi Berakhir<br>
                    2. Tidak membawa nota garansi<br>
                    3. Segel Rusak/Hilang<br>
                    4. Kesalahan pemakaian conoth : terjatuh, terkena cairan, selama dalam penggunaan, pecah/retak, salah penyimpanan, berkarat, tergores, berjamur, atau human error, lupa iCloud<br><br>
                    Yang tidak termasuk garansi<br>
                    1. Accesoris (Kabel Cas, Charger, Handfree)<br>
                    2. Data dan Update OS<br>
                    3. Fisik iPhone Second<br>
                    4. Tidak Garansi IMEI
                </td>
            </tr>
            <tr>
                <td colspan="6">
                    <hr style="border: 1px solid black;">
                </td>
            </tr>
            <tr>
                <td colspan="6" style="text-align:left;">
                    Ketentuan Garansi IPhone:<br>
                    1. Disarankan Nota dan Segel dibawah Handphone masih utuh tidak rusak<br>
                    2. Untuk Garansi Sparepart 7 hari, 1 bulan, 1 tahun, 2 tahun, dan selamanya terhitung dari tanggal Pengambilan Barang<br>
                    3. Untuk Caransi Service Mesin 7 dan 30 Hari terhitung dari tanggal Pengambilan Barang<br>
                    4. Untuk Garansi penggantian LCD berlaku yang cacat pabrik (Ghost touch, Blank, Sentuh tidak bisa) Selain Cacat Pabrik kita tidak Menerima Garansi<br><br>
                    Ketentuan Service iPhone:<br>
                    1. Kami tidak bertanggung jawab atas hilangnya data<br>
                    2. Bukan Lock icloud<br>
                    3. Tanda Terima wajib dibawa waktu pengambilan barang<br>
                    4. Perbaikan berhak kami cancel apabila: gagal service, kerusakan yang parah<br>
                    5. Garansi uang kembali selama masa garansi iPhone masih ada dan tetap ERROR yang sama<br>
                    6. Apabila Unit Service tidak diambil lebih dari 2 bulan, kehilangan bukan tanggung jawab karni<br>
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
            <td rowspan="2" colspan="3" style="text-align: center;">
                <?php if (isset($qrImageUrl)): ?>
                    <div>
                        <img src="<?= $qrImageUrl ?>" width="70" alt="QR Code">
                        <p style="font-size: 11px;">Scan untuk melihat detail service</p>
                    </div>
                <?php endif; ?>
            </td>
            <td colspan="2" style="text-align: center;">Customer</td>
        </tr>
        <tr class="ttd">
            <td colspan="1" style="text-align: center;"><?= @$human->nama_service_by ?></td>
            <td colspan="2" style="text-align: center;">____________________</td>
        </tr>
    </table>
</div>