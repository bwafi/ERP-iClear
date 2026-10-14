<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kolom "Kode Sparepart" dan "Stok Akhir" pada tab Sparepart /service
 * (modal pilih sparepart + tabel sparepart terpilih) disamakan dengan halaman
 * /detail/riwayat_service?tab=sparepart, supaya teknisi bisa mengenali
 * sparepart dan melihat sisa stoknya sebelum dipakai.
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

    public function testModalMenampilkanKodeSparepartDanStokAkhir(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<th>Kode Sparepart</th>', $html, 'modal harus punya kolom Kode Sparepart');
        $this->assertStringContainsString('data-kode=', $html, 'kode sparepart harus diteruskan dari modal');
        $this->assertStringContainsString('data-stok=', $html, 'stok akhir harus diteruskan dari modal');
        $this->assertStringContainsString('item.kode_barang', $html, 'modal harus merender kode_barang');
        $this->assertStringContainsString('item.stok_akhir', $html, 'modal harus merender stok_akhir');
    }

    public function testTabelTerpilihMenampilkanStokAkhirDanKode(): void
    {
        $html = $this->render();

        $this->assertSame(
            2,
            substr_count($html, '<th>Stok Akhir</th>'),
            'kolom Stok Akhir harus ada di modal dan di tabel sparepart terpilih'
        );
        $this->assertStringContainsString(
            'readonly></td>',
            $html,
            'baris terpilih harus punya kolom stok akhir read-only'
        );
        $this->assertStringContainsString('${kode ?', $html, 'kode sparepart ditampilkan pada baris terpilih');
    }

    public function testModalMenampilkanSuplierDiKeduaHalaman(): void
    {
        foreach (['transaksi/table/sparepart_table', 'riwayat/table/sparepart_table'] as $view) {
            $html = $this->render($view);

            $this->assertStringContainsString('<th>Suplier</th>', $html, "kolom Suplier harus ada di modal ({$view})");
            $this->assertStringContainsString('item.nama_suplier', $html, "modal harus merender nama_suplier ({$view})");

            $this->assertStringContainsString('colspan="9"', $html, "placeholder modal harus membentang 9 kolom ({$view})");
            $this->assertStringNotContainsString('colspan="8"', $html, "tidak boleh ada placeholder modal 8 kolom ({$view})");
        }
    }
}
