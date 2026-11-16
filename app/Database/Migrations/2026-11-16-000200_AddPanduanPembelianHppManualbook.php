<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pasang PDF panduan baru ke modal Manual Book (issue #213).
 *
 * File PDF ada di `public/manualbook/`. Penambahan dilakukan tanpa menghapus
 * nama manual lama: nilai baru ditaruh paling depan (jadi default), sisa nama
 * lama tetap tersimpan di belakang pemisah `|`. Dengan begitu halaman yang
 * sudah punya manual book tidak kehilangan akses ke manual lamanya.
 *
 * Idempoten: menjalankan ulang tidak menghasilkan duplikat.
 */
class AddPanduanPembelianHppManualbook extends Migration
{
    private const PEMISAH = '|';

    /**
     * url menu => nama file PDF yang ditambahkan.
     *
     * @var array<string, string>
     */
    private array $panduan = [
        'pembelian'  => 'Panduan Pembelian.pdf',
        'kartu_stok' => 'Panduan HPP dan Harga Jual per Unit.pdf',
        'tarif_jasa' => 'Panduan HPP dan Harga Jual per Unit.pdf',
    ];

    public function up()
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->terapkan(true);
    }

    public function down()
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->terapkan(false);
    }

    private function terapkan(bool $tambah): void
    {
        foreach ($this->panduan as $url => $berkas) {
            $rows = $this->db->table('menu')->where('url', $url)->get()->getResult();

            foreach ($rows as $row) {
                $daftar = $this->pecah($row->manualbook ?? null);
                $daftar = array_values(array_diff($daftar, [$berkas]));

                if ($tambah) {
                    array_unshift($daftar, $berkas);
                }

                $nilai = $daftar === [] ? null : implode(self::PEMISAH, $daftar);

                if ((string) ($row->manualbook ?? '') === (string) ($nilai ?? '')) {
                    continue;
                }

                $this->db->table('menu')
                    ->where('idmenu', $row->idmenu)
                    ->update(['manualbook' => $nilai]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pecah(?string $nilai): array
    {
        if ($nilai === null) {
            return [];
        }

        $hasil = [];
        foreach (preg_split('/[|' . "\r\n" . ']+/', $nilai) ?: [] as $item) {
            $item = trim($item);
            if ($item !== '' && ! in_array($item, $hasil, true)) {
                $hasil[] = $item;
            }
        }

        return $hasil;
    }
}
