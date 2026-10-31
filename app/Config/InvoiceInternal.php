<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Identitas penerbit dan rekening pembayaran Invoice Internal cabang.
 *
 * Dipisah dari view supaya nomor rekening tidak tersebar di template dan
 * mudah diganti tanpa menyentuh kode cetak.
 */
class InvoiceInternal extends BaseConfig
{
    public string $namaUsaha = 'CV. ICLEAR DIGITAL SOLUSI';
    public string $alamat = 'Jl. Hos Cokroaminoto No.06, Kanigaran, Kec. Kanigaran, Kota Probolinggo, Jawa Timur 67213';
    public string $telepon = '085183270910';
    public string $bank = 'BCA';
    public string $norek = '039-377-8773';
    public string $atasNama = 'ICLEAR DIGITAL SOLUSI CV';
    public string $catatan = 'Invoice ini merupakan tagihan internal untuk keperluan operasional dan pengelolaan cabang.';
}
