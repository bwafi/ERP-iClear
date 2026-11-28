<?php

namespace App\Services\Finance;

use App\Exceptions\PembayaranHutangKasKeluarException;
use App\Models\ModelJurnal;
use App\Models\ModelKasKeluar;
use App\Models\ModelSuplier;
use Config\Database;

/**
 * Mencatat pembayaran hutang supplier sebagai arus kas keluar + jurnal.
 *
 * Latar belakang: `pembayaran_hutang` tidak punya kolom `transfer_ref`, jadi
 * `ModeKasBank::postingCicilanHutang()` selalu `skipped` dan pembayaran hutang
 * tidak pernah mengurangi kas/bank. Sesuai kontrak hybrid, arus kas operasional
 * dibaca dari tabel sumber `kas_keluar`, bukan ledger. Karena itu pembayaran
 * hutang dicatat di sini sebagai `kas_keluar` per komponen pembayaran.
 *
 * Satu baris per komponen (tunai `idbank NULL`, bank `idbank` rekening) supaya
 * Tutup Kasir dan saldo Kas & Bank memisahkan kas dan transfer dengan benar.
 * Setiap baris mendapat jurnal `Dr Utang Usaha / Cr Kas-Bank` yang seimbang.
 *
 * Semua validasi dilakukan SEBELUM baris pertama ditulis; kegagalan melempar
 * PembayaranHutangKasKeluarException sehingga pemanggil (transaksi `insert_cicilan`) rollback.
 */
class PembayaranHutangKasKeluar
{
    private const KATEGORI_TEMPO = 'TEMPO';
    private const AKUN_UTANG_USAHA = '2010100000';
    private const TEMPLATE_TUNAI = 'pembelian_lunas_tunai';
    private const TEMPLATE_BANK = 'pembelian_lunas_bank';

    private ModelKasKeluar $kasKeluar;
    private ModelJurnal $jurnal;
    private ModelSuplier $suplier;
    private $db;

    public function __construct(
        ?ModelKasKeluar $kasKeluar = null,
        ?ModelJurnal $jurnal = null,
        ?ModelSuplier $suplier = null
    ) {
        $this->kasKeluar = $kasKeluar ?? new ModelKasKeluar();
        $this->jurnal    = $jurnal ?? new ModelJurnal();
        $this->suplier   = $suplier ?? new ModelSuplier();
        $this->db        = Database::connect();
    }

    /**
     * @param object $pembelian Baris pembelian (unit_idunit, no_nota_supplier, suplier_id_suplier).
     * @return array{kas_keluar_ids:int[], jurnal:int}
     *
     * @throws PembayaranHutangKasKeluarException
     */
    public function catat(
        int $idPembayaran,
        string $tanggal,
        int $tunai,
        int $bank,
        ?string $idbank,
        object $pembelian
    ): array {
        $unitId = (int) ($pembelian->unit_idunit ?? 0);
        if ($unitId <= 0) {
            throw new PembayaranHutangKasKeluarException(
                'Unit pembelian tidak valid, pembayaran tidak bisa dicatat ke kas keluar.'
            );
        }

        $komponen = $this->komponen($tunai, $bank, $idbank);
        $kategoriId = $this->kategoriTempoId();

        // Validasi template jurnal sebelum menulis apa pun.
        foreach ($komponen as $k) {
            $validasi = $this->jurnal->validasiTemplate($k['template'], [$k['jumlah']]);
            if (! $validasi['ok']) {
                throw new PembayaranHutangKasKeluarException(
                    'Jurnal pembayaran hutang tidak dapat disimpan: ' . $validasi['alasan']
                );
            }
        }

        $penerima = $this->namaSupplier($pembelian->suplier_id_suplier ?? null);
        $noNota = trim((string) ($pembelian->no_nota_supplier ?? ''));
        $deskripsi = 'Pelunasan tempo'
            . ($noNota !== '' ? ' ' . $noNota : '')
            . ' - ' . $penerima;

        $ids = [];

        foreach ($komponen as $k) {
            $tersimpan = $this->kasKeluar->insert_KasKeluar([
                'tanggal'             => $tanggal,
                'kategori_idkategori' => $kategoriId,
                'deskripsi'           => $deskripsi,
                'jumlah'              => $k['jumlah'],
                'penerima'            => $penerima,
                'idunit'              => $unitId,
                'jenis'               => 'debet',
                'idbank'              => $k['idbank'],
                'created_on'          => date('Y-m-d H:i:s'),
                'no_akun'             => self::AKUN_UTANG_USAHA,
            ]);

            if (! $tersimpan) {
                throw new PembayaranHutangKasKeluarException('Kas keluar pembayaran hutang gagal disimpan.');
            }

            $ids[] = (int) $this->kasKeluar->insertID();

            $jurnalTersimpan = $this->jurnal->insertJurnal(
                $tanggal,
                $k['template'],
                [$k['jumlah']],
                $deskripsi,
                $idPembayaran,
                'pembayaran_hutang',
                $unitId
            );

            if (! $jurnalTersimpan) {
                throw new PembayaranHutangKasKeluarException('Jurnal pembayaran hutang gagal disimpan.');
            }
        }

        return ['kas_keluar_ids' => $ids, 'jurnal' => count($komponen) * 2];
    }

    /**
     * @return array<int, array{jumlah:int, idbank:?string, template:string}>
     */
    private function komponen(int $tunai, int $bank, ?string $idbank): array
    {
        $komponen = [];

        if ($tunai > 0) {
            $komponen[] = [
                'jumlah'   => $tunai,
                'idbank'   => null,
                'template' => self::TEMPLATE_TUNAI,
            ];
        }

        if ($bank > 0) {
            $idbank = trim((string) $idbank);
            if ($idbank === '') {
                throw new PembayaranHutangKasKeluarException('Rekening bank wajib dipilih untuk pembayaran lewat bank.');
            }

            $komponen[] = [
                'jumlah'   => $bank,
                'idbank'   => $idbank,
                'template' => $this->templateBank($idbank),
            ];
        }

        if ($komponen === []) {
            throw new PembayaranHutangKasKeluarException('Tidak ada komponen pembayaran tunai/bank yang bisa dicatat.');
        }

        return $komponen;
    }

    /**
     * Template bank spesifik rekening dipakai bila ada; rekening baru memakai
     * template bank generik (pola yang sama dengan Pembelian).
     */
    private function templateBank(string $idbank): string
    {
        $spesifik = self::TEMPLATE_BANK . '_' . $idbank;

        return $this->jurnal->templateRows($spesifik) === []
            ? self::TEMPLATE_BANK
            : $spesifik;
    }

    private function kategoriTempoId(): int
    {
        $row = $this->db->table('kategori_kas')
            ->where('kategori', self::KATEGORI_TEMPO)
            ->where('jenis_kas', 'kas_keluar')
            ->get()
            ->getRow();

        $id = (int) ($row->idkategori_kas ?? 0);
        if ($id <= 0) {
            throw new PembayaranHutangKasKeluarException(
                'Kategori kas TEMPO belum tersedia. Tambahkan kategori TEMPO '
                . 'untuk kas keluar sebelum mencatat pembayaran hutang.'
            );
        }

        return $id;
    }

    private function namaSupplier($idSuplier): string
    {
        $idSuplier = trim((string) $idSuplier);
        if ($idSuplier === '') {
            return '-';
        }

        $suplier = $this->suplier->getById($idSuplier);
        $nama = trim((string) ($suplier->nama_suplier ?? ''));

        return $nama !== '' ? $nama : '-';
    }
}
