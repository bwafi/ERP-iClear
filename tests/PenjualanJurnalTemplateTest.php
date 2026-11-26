<?php

namespace Tests;

use App\Models\ModelJurnal;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Template jurnal penjualan yang dipanggil Penjualan::insert_penjualan().
 *
 * Sebelum migration ini, controller memakai kode template yang tidak ada di
 * `template_jurnal`; ModelJurnal::insertJurnal() hanya log error dan return
 * false sehingga penjualan tetap commit tanpa jurnal. Test ini menjaga:
 *  - semua kode template yang dipakai controller tersedia;
 *  - jurnal gabungan pembayaran + pendapatan + PPN + diskon seimbang;
 *  - migration idempoten dan tidak menyentuh jurnal historis.
 *
 * DDL/DML nyata di group `tests` (MySQL `erp_test`, prefix dikosongkan).
 *
 * @internal
 */
final class PenjualanJurnalTemplateTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    private const MIGRASI_FILE = '2026-11-26-000100_SeedPenjualanJurnal.php';
    private const MIGRASI_KELAS = 'App\\Database\\Migrations\\SeedPenjualanJurnal';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (['template_jurnal', 'jurnal', 'bank'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->query('CREATE TABLE template_jurnal (
            idtemplate_jurnal INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            kode_template VARCHAR(255) NULL,
            no_akun VARCHAR(255) NULL,
            nama_akun VARCHAR(255) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT DEFAULT 0,
            keterangan TEXT NULL)');

        $this->db->query('CREATE TABLE jurnal (
            idjurnal INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(255) NULL,
            nama_akun VARCHAR(255) NULL,
            debet INT DEFAULT 0,
            kredit INT DEFAULT 0,
            keterangan TEXT NULL,
            id_referensi VARCHAR(255) NULL,
            tabel_referensi VARCHAR(255) NULL,
            id_unit INT NULL,
            id_akun INT NULL)');

        $this->db->query('CREATE TABLE bank (
            idbank VARCHAR(20) NOT NULL PRIMARY KEY,
            jenis_bank VARCHAR(20) NULL,
            nama_bank VARCHAR(255) NULL)');

        $this->db->table('bank')->insertBatch([
            ['idbank' => '1', 'jenis_bank' => 'bank', 'nama_bank' => 'BCA'],
            ['idbank' => '2', 'jenis_bank' => 'bank', 'nama_bank' => 'BNI'],
        ]);

        session()->set(['ID_AKUN' => 7, 'ID_UNIT' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (['template_jurnal', 'jurnal', 'bank'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function jalankanMigrasi(): void
    {
        $path = APPPATH . 'Database/Migrations/' . self::MIGRASI_FILE;
        if (! is_file($path)) {
            $this->fail('Migration belum ada: ' . $path);
        }

        if (! class_exists(self::MIGRASI_KELAS, false)) {
            require_once $path;
        }

        $kelas = self::MIGRASI_KELAS;
        (new $kelas())->up();
    }

    private function jurnal(): ModelJurnal
    {
        return new ModelJurnal();
    }

    private function tulisJurnal(string $kode, int $nilai, string $referensi, string $tabelReferensi): void
    {
        $ok = $this->jurnal()->insertJurnal(
            '2026-11-26',
            $kode,
            [$nilai],
            'uji ' . $kode,
            $referensi,
            $tabelReferensi
        );

        $this->assertTrue($ok, 'insertJurnal gagal untuk template ' . $kode);
    }

    /**
     * @return array{rows:list<array<string,mixed>>,debet:int,kredit:int}
     */
    private function ringkas(string $tabelReferensi, string $referensi): array
    {
        $rows = $this->db->table('jurnal')
            ->where('tabel_referensi', $tabelReferensi)
            ->where('id_referensi', $referensi)
            ->get()
            ->getResultArray();

        $debet = 0;
        $kredit = 0;
        foreach ($rows as $row) {
            $debet += (int) ($row['debet'] ?? 0);
            $kredit += (int) ($row['kredit'] ?? 0);
        }

        return ['rows' => $rows, 'debet' => $debet, 'kredit' => $kredit];
    }

    public function testSemuaTemplateYangDipakaiPenjualanTersedia(): void
    {
        $this->jalankanMigrasi();

        $model = $this->jurnal();
        $wajib = [
            'penjualan_lunas_tunai',
            'penjualan_lunas_bank_1',
            'penjualan_lunas_bank_2',
            'penjualan_bayar_ppn',
            'penjualan_diskon',
            'penjualan_acc_cash',
            'penjualan_hp_baru_ppn',
            'penjualan_hp_baru_cash',
            'penjualan_hp_second_cash',
        ];

        foreach ($wajib as $kode) {
            $this->assertCount(1, $model->templateRows($kode), 'Template ' . $kode . ' harus ada tepat satu baris.');
        }
    }

    public function testJurnalTunaiDenganPpnDanDiskonSeimbang(): void
    {
        $this->jalankanMigrasi();

        $kotor = 1000000;
        $diskon = 10000;
        $ppn = 108900;
        $tunai = $kotor - $diskon + $ppn;

        $this->tulisJurnal('penjualan_lunas_tunai', $tunai, 'PJL-TUNAI', 'pembayaran_bank');
        $this->tulisJurnal('penjualan_hp_baru_cash', $kotor, '99', 'penjualan');
        $this->tulisJurnal('penjualan_bayar_ppn', $ppn, '99', 'penjualan');
        $this->tulisJurnal('penjualan_diskon', $diskon, '99', 'penjualan');

        $bayar = $this->ringkas('pembayaran_bank', 'PJL-TUNAI');
        $jual = $this->ringkas('penjualan', '99');

        $this->assertSame($tunai, $bayar['debet'], 'Kas Besar didebit sebesar uang diterima.');
        $this->assertSame(0, $bayar['kredit']);
        $this->assertSame($kotor + $ppn, $jual['kredit'], 'Pendapatan + PPN dikredit.');
        $this->assertSame($diskon, $jual['debet'], 'Diskon penjualan didebit.');
        $this->assertSame(
            $bayar['debet'] + $jual['debet'],
            $bayar['kredit'] + $jual['kredit'],
            'Jurnal penjualan tunai harus seimbang.'
        );
        $this->assertSame($kotor + $ppn, $bayar['debet'] + $jual['debet']);
    }

    public function testJurnalBankDanHpSecondSeimbang(): void
    {
        $this->jalankanMigrasi();

        $this->tulisJurnal('penjualan_lunas_bank_2', 500000, 'PJL-BANK', 'pembayaran_bank');
        $this->tulisJurnal('penjualan_hp_second_cash', 500000, '100', 'penjualan');

        $bayar = $this->ringkas('pembayaran_bank', 'PJL-BANK');
        $jual = $this->ringkas('penjualan', '100');

        $this->assertSame(500000, $bayar['debet']);
        $this->assertSame(500000, $jual['kredit']);
        $this->assertSame($bayar['debet'] + $jual['debet'], $bayar['kredit'] + $jual['kredit']);
        $this->assertSame('1010102000', (string) $bayar['rows'][0]['no_akun'], 'Transfer masuk ke Kas di Bank.');
        $this->assertSame('4010103000', (string) $jual['rows'][0]['no_akun'], 'HP second memakai akun penjualan HP second.');
    }

    public function testJurnalHpBaruPpnSeimbang(): void
    {
        $this->jalankanMigrasi();

        $this->tulisJurnal('penjualan_lunas_tunai', 1110000, 'PJL-PPN', 'pembayaran_bank');
        $this->tulisJurnal('penjualan_hp_baru_ppn', 1000000, '101', 'penjualan');
        $this->tulisJurnal('penjualan_bayar_ppn', 110000, '101', 'penjualan');

        $bayar = $this->ringkas('pembayaran_bank', 'PJL-PPN');
        $jual = $this->ringkas('penjualan', '101');

        $this->assertSame(1110000, $bayar['debet']);
        $this->assertSame(1110000, $jual['kredit']);
        $this->assertSame($bayar['debet'] + $jual['debet'], $bayar['kredit'] + $jual['kredit']);
        $this->assertSame('4010101000', (string) $jual['rows'][0]['no_akun'], 'HP baru PPN memakai akun PPN.');
    }

    public function testJurnalAksesorisDanSparepartSeimbang(): void
    {
        $this->jalankanMigrasi();

        $this->tulisJurnal('penjualan_lunas_tunai', 200000, 'PJL-ACC', 'pembayaran_bank');
        $this->tulisJurnal('penjualan_acc_cash', 200000, '102', 'penjualan');

        $bayar = $this->ringkas('pembayaran_bank', 'PJL-ACC');
        $jual = $this->ringkas('penjualan', '102');

        $this->assertSame(200000, $bayar['debet']);
        $this->assertSame(200000, $jual['kredit']);
        $this->assertSame($bayar['debet'] + $jual['debet'], $bayar['kredit'] + $jual['kredit']);
        $this->assertSame('4010104000', (string) $jual['rows'][0]['no_akun'], 'Aksesoris memakai akun penjualan aksesoris.');
    }

    public function testMigrasiIdempotenDanTidakMenyentuhJurnalHistoris(): void
    {
        $this->db->table('jurnal')->insert([
            'tanggal' => '2025-07-08',
            'no_akun' => '1010101000',
            'nama_akun' => 'Kas Besar',
            'debet' => 20000,
            'kredit' => 0,
            'keterangan' => 'historis',
            'id_referensi' => '80',
            'tabel_referensi' => 'penjualan',
            'id_unit' => 1,
            'id_akun' => 1,
        ]);

        $this->jalankanMigrasi();
        $snapshotSatu = $this->snapshotTemplate();
        $this->jalankanMigrasi();
        $snapshotDua = $this->snapshotTemplate();

        $this->assertSame($snapshotSatu, $snapshotDua, 'Migration kedua harus no-op.');
        $this->assertSame(1, $this->db->table('jurnal')->where('keterangan', 'historis')->countAllResults());

        $duplikat = $this->db->table('template_jurnal')
            ->select('kode_template, no_akun, debet_kredit, COUNT(*) AS c')
            ->groupBy('kode_template, no_akun, debet_kredit')
            ->having('c >', 1)
            ->get()
            ->getResultArray();

        $this->assertSame([], $duplikat, 'Tidak boleh ada baris template ganda.');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function snapshotTemplate(): array
    {
        return $this->db->table('template_jurnal')
            ->select('kode_template, no_akun, nama_akun, debet_kredit, array_value, keterangan')
            ->orderBy('kode_template', 'ASC')
            ->orderBy('no_akun', 'ASC')
            ->get()
            ->getResultArray();
    }
}
