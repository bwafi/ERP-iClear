<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Dropdown "Rekening Tujuan (DP)" pada step Pembayaran Service dipisahkan dari
 * daftar rekening transfer biasa: DP hanya menawarkan `dp_bank` (rekening unit
 * yang login), sedangkan baris bank pembayaran tetap memakai `bank` penuh.
 *
 * Test ini mengunci perilaku view, bukan hanya ModelBank-nya, supaya view
 * tidak diam-diam kembali menampilkan seluruh rekening.
 */
class ServiceDpBankDropdownRenderTest extends CIUnitTestCase
{
    private function bank(string $id, string $nama, string $norek, string $atasNama): object
    {
        return (object) [
            'idbank'    => $id,
            'nama_bank' => $nama,
            'norek'     => $norek,
            'atas_nama' => $atasNama,
        ];
    }

    private function render(array $vars): string
    {
        $r = service('renderer');
        foreach ($vars as $k => $v) {
            $r->setVar($k, $v);
        }

        return (string) $r->include('transaksi/table/pembayaran_table');
    }

    private function vars(array $dpBank): array
    {
        return [
            'idservice'             => 0,
            'old_service_pelanggan' => null,
            'oldsparepart'          => [],
            'pembayaran_lama'       => [],
            'bank'                  => [
                $this->bank('1', 'Bank BCA Pandaan', '0391796181', 'SABRINA'),
                $this->bank('5', 'Bank BCA Banyuwangi', '1802016667', 'ALFARIZKI'),
            ],
            'dp_bank'               => $dpBank,
        ];
    }

    private function selectDp(string $html): string
    {
        preg_match('#<select id="dp_bank_idbank".*?</select>#s', $html, $m);

        return $m[0] ?? '';
    }

    public function testDropdownDpHanyaMemuatRekeningUnit(): void
    {
        $html   = $this->render($this->vars([$this->bank('1', 'Bank BCA Pandaan', '0391796181', 'SABRINA')]));
        $select = $this->selectDp($html);

        $this->assertStringContainsString('value="1"', $select, 'rekening unit harus jadi pilihan DP');
        $this->assertStringNotContainsString('value="5"', $select, 'rekening unit lain TIDAK boleh tampil di DP');
    }

    public function testBarisBankPembayaranTetapMemakaiDaftarPenuh(): void
    {
        // JS bankRowMarkup memakai $bank (bukan $dp_bank), jadi kedua rekening
        // harus tetap ada supaya transfer pembayaran biasa tidak ikut terbatas.
        $html = $this->render($this->vars([$this->bank('1', 'Bank BCA Pandaan', '0391796181', 'SABRINA')]));

        $this->assertStringContainsString('Bank BCA Banyuwangi', $html);
        $this->assertStringContainsString('Bank BCA Pandaan', $html);
    }

    public function testUnitTanpaRekeningMenampilkanPeringatan(): void
    {
        $html   = $this->render($this->vars([]));
        $select = $this->selectDp($html);

        $this->assertNotSame('', $select, 'select rekening DP tetap dirender walau kosong');
        $this->assertStringContainsString('-- Pilih Rekening --', $select);
        $this->assertStringNotContainsString('value="1"', $select);
        $this->assertStringContainsString('Belum ada rekening untuk unit ini', $html);
    }

    /**
     * DP yang baru diisi di step Pembayaran harus dihitung sebagai uang yang
     * sudah diterima: ikut mengurangi "Sisa tagihan" dan "Kembalian", serta
     * ikut dihitung di peringatan sebelum simpan. Sebelumnya DP baru sengaja
     * tidak dihitung, sehingga merekam DP saja memicu peringatan "Jumlah
     * dibayar (Rp 0) kurang dari tagihan (...)".
     */
    public function testDpBaruDihitungSebagaiUangDiterima(): void
    {
        $html = $this->render($this->vars([$this->bank('1', 'Bank BCA Pandaan', '0391796181', 'SABRINA')]));

        $this->assertStringContainsString('function newDpValue()', $html);
        $this->assertStringContainsString('newDpValue()', $html);
        $this->assertStringContainsString(
            'total - dpTercatat - bayarTercatat - dpBaru',
            $html,
            'DP baru harus dikurangkan dari sisa tagihan'
        );

        $this->assertStringNotContainsString('kurang dari tagihan', $html, 'peringatan lama harus diganti');
        $this->assertStringContainsString('Masih ada sisa tagihan', $html);
    }

    /**
     * Saat operator hanya mencatat DP (tanpa pembayaran ke tagihan), metode
     * pembayaran utama boleh dikosongkan: kolom tunai/transfer memang tidak
     * terbuka, dan metode DP sudah menandai cara pembayarannya.
     */
    public function testMetodePembayaranTidakWajibSaatHanyaCatatDp(): void
    {
        $html = $this->render($this->vars([$this->bank('1', 'Bank BCA Pandaan', '0391796181', 'SABRINA')]));

        $this->assertStringContainsString(
            'if (!method && dpVal <= 0)',
            $html,
            'metode utama hanya wajib bila tidak ada DP yang dicatat'
        );
        $this->assertStringContainsString(
            'if (method && sisaAkhir > 0',
            $html,
            'peringatan sisa dilewati saat hanya mencatat DP'
        );
    }
}
