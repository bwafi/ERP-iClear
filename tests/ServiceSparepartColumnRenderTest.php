<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Modal "Pilih Sparepart" (/service dan /detail/riwayat_service?tab=sparepart)
 * menampilkan kode, stok akhir, dan suplier sparepart supaya teknisi bisa
 * memilih dengan yakin. Tampilan memakai daftar pilihan (bukan tabel padat),
 * sedangkan tabel sparepart yang terpilih tetap menampilkan stok akhir.
 */
class ServiceSparepartColumnRenderTest extends CIUnitTestCase
{
    private function render(string $view = 'transaksi/table/sparepart_table'): string
    {
        $r = service('renderer');
        $r->setVar('idservice', 0);
        $r->setVar('oldsparepart', []);

        return (string) $r->include($view);
    }

    public function testModalMerenderKodeStokDanSuplier(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="sparepartModalLabel"', $html, 'judul modal harus punya id aksesibilitas');
        $this->assertStringContainsString('service-sp-item', $html, 'pilihan sparepart dirender sebagai item daftar');
        $this->assertStringContainsString('sparepart-check', $html, 'checkbox pilih sparepart tetap ada');
        $this->assertStringContainsString('data-kode=', $html, 'kode sparepart diteruskan dari modal');
        $this->assertStringContainsString('data-stok=', $html, 'stok akhir diteruskan dari modal');
        $this->assertStringContainsString('item.kode_barang', $html, 'modal harus merender kode_barang');
        $this->assertStringContainsString('item.nama_suplier', $html, 'modal harus merender nama_suplier');
    }

    public function testModalMenampilkanSuplierDanStokDiKeduaHalaman(): void
    {
        foreach (['transaksi/table/sparepart_table', 'riwayat/table/sparepart_table'] as $view) {
            $html = $this->render($view);

            $this->assertStringContainsString('id="sparepartModalLabel"', $html, "judul modal harus punya id aksesibilitas ({$view})");
            $this->assertStringContainsString('service-sp-code', $html, "kode sparepart dirender di item ({$view})");
            $this->assertStringContainsString('item.nama_suplier', $html, "modal harus merender nama_suplier ({$view})");
            $this->assertStringContainsString(
                'service-sp-stock',
                $html,
                "stok akhir dirender sebagai penanda di item ({$view})"
            );
        }
    }

    public function testTabelTerpilihMenampilkanStokAkhirDanKode(): void
    {
        $html = $this->render();

        $this->assertStringContainsString(
            '<th>Stok Akhir</th>',
            $html,
            'kolom Stok Akhir harus ada di tabel sparepart terpilih'
        );
        $this->assertStringContainsString(
            'readonly></td>',
            $html,
            'baris terpilih harus punya kolom stok akhir read-only'
        );
        $this->assertStringContainsString('${kode ?', $html, 'kode sparepart ditampilkan pada baris terpilih');
    }
}
