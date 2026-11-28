<?php

namespace Tests;

use App\Services\Finance\PembayaranHutangKasKeluar;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

/**
 * Regresi pencatatan pembayaran hutang supplier ke kas_keluar + jurnal.
 *
 * Alur /daftar_tagihan sebelumnya hanya menyimpan pembayaran_hutang dan
 * menandai hutang lunas tanpa arus kas. Test ini mengunci perilaku baru:
 * satu baris kas_keluar per komponen pembayaran (tunai/bank) dengan kategori
 * TEMPO, plus jurnal Dr Utang Usaha / Cr Kas-Bank yang seimbang.
 */
final class PembayaranHutangKasKeluarTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->buatSkema();
        $this->sebarData();

        $_SESSION['ID_AKUN'] = 9;
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        foreach ([
            'db_jurnal',
            'db_template_jurnal',
            'db_kas_keluar',
            'db_kategori_kas',
            'db_suplier',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE db_kategori_kas (
                idkategori_kas INT PRIMARY KEY AUTO_INCREMENT,
                kategori VARCHAR(255) NULL,
                kode_template_jurnal VARCHAR(255) NULL,
                jenis_kas VARCHAR(20) NULL)');

        $q('CREATE TABLE db_kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT,
                tanggal DATE NULL,
                kategori_idkategori INT NULL,
                deskripsi TEXT NULL,
                jumlah INT NULL,
                penerima VARCHAR(255) NULL,
                idunit INT NULL,
                jenis VARCHAR(255) NULL,
                idbank VARCHAR(11) NULL,
                created_on VARCHAR(255) NULL,
                updated_on DATETIME NULL,
                no_akun VARCHAR(255) NULL)');

        $q('CREATE TABLE db_template_jurnal (
                idtemplate_jurnal INT PRIMARY KEY AUTO_INCREMENT,
                kode_template VARCHAR(255) NULL,
                no_akun VARCHAR(255) NULL,
                nama_akun VARCHAR(255) NULL,
                debet_kredit VARCHAR(20) NULL,
                array_value INT NULL,
                keterangan TEXT NULL)');

        $q('CREATE TABLE db_jurnal (
                idjurnal INT PRIMARY KEY AUTO_INCREMENT,
                tanggal DATE NULL,
                no_akun VARCHAR(255) NULL,
                nama_akun VARCHAR(255) NULL,
                debet INT NULL,
                kredit INT NULL,
                keterangan TEXT NULL,
                id_referensi INT NULL,
                tabel_referensi VARCHAR(255) NULL,
                id_unit INT NULL,
                id_akun INT NULL)');

        $q('CREATE TABLE db_suplier (
                id_suplier INT PRIMARY KEY AUTO_INCREMENT,
                nama_suplier VARCHAR(255) NULL,
                alamat TEXT NULL,
                no_hp VARCHAR(255) NULL,
                deleted INT NULL,
                unit_idunit INT NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('kategori_kas')->insert([
            'idkategori_kas' => 16,
            'kategori' => 'TEMPO',
            'jenis_kas' => 'kas_keluar',
        ]);

        $this->db->table('suplier')->insert([
            'id_suplier' => 5,
            'nama_suplier' => 'PT Supplier',
            'deleted' => 0,
            'unit_idunit' => 2,
        ]);

        $templates = [
            'pembelian_lunas_tunai' => [
                ['2010100000', 'Utang Usaha', 'debet'],
                ['1010101000', 'Kas Besar (Cash on Hand)', 'kredit'],
            ],
            'pembelian_lunas_bank_BNI-001' => [
                ['2010100000', 'Utang Usaha', 'debet'],
                ['1010102000', 'Kas di Bank (Cash in Bank)', 'kredit'],
            ],
            'pembelian_lunas_bank' => [
                ['2010100000', 'Utang Usaha', 'debet'],
                ['1010102000', 'Kas di Bank (Cash in Bank)', 'kredit'],
            ],
        ];

        foreach ($templates as $kode => $rows) {
            foreach ($rows as $row) {
                $this->db->table('template_jurnal')->insert([
                    'kode_template' => $kode,
                    'no_akun' => $row[0],
                    'nama_akun' => $row[1],
                    'debet_kredit' => $row[2],
                    'array_value' => 0,
                ]);
            }
        }
    }

    private function pembelian(): object
    {
        return (object) [
            'idpembelian' => 1,
            'unit_idunit' => 2,
            'no_nota_supplier' => 'INV-1',
            'suplier_id_suplier' => 5,
        ];
    }

    /**
     * @return array{kas_keluar_ids:int[], jurnal:int}
     */
    private function catat(int $tunai, int $bank, ?string $idbank = null): array
    {
        return (new PembayaranHutangKasKeluar())->catat(
            1,
            '2026-11-28',
            $tunai,
            $bank,
            $idbank,
            $this->pembelian()
        );
    }

    /** @return object[] */
    private function kasKeluar(): array
    {
        return $this->db->table('kas_keluar')->orderBy('idkas_keluar', 'ASC')->get()->getResult();
    }

    /** @return object[] */
    private function jurnal(): array
    {
        return $this->db->table('jurnal')->orderBy('idjurnal', 'ASC')->get()->getResult();
    }

    public function testTunaiPenuhMembuatSatuKasKeluarTempoDanJurnalSeimbang(): void
    {
        $hasil = $this->catat(100000, 0);

        $this->assertCount(1, $hasil['kas_keluar_ids']);

        $rows = $this->kasKeluar();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('2026-11-28', (string) $row->tanggal);
        $this->assertSame(16, (int) $row->kategori_idkategori);
        $this->assertSame('2010100000', $row->no_akun);
        $this->assertSame('debet', $row->jenis);
        $this->assertSame(2, (int) $row->idunit, 'unit mengikuti pembelian, bukan unit login');
        $this->assertNull($row->idbank, 'pembayaran tunai memakai idbank NULL');
        $this->assertSame(100000, (int) $row->jumlah);
        $this->assertSame('PT Supplier', $row->penerima);
        $this->assertStringContainsString('INV-1', (string) $row->deskripsi);

        $jurnal = $this->jurnal();
        $this->assertCount(2, $jurnal);
        $this->assertSame(100000, array_sum(array_map(static fn ($r) => (int) $r->debet, $jurnal)));
        $this->assertSame(100000, array_sum(array_map(static fn ($r) => (int) $r->kredit, $jurnal)));
        $this->assertSame('pembayaran_hutang', $jurnal[0]->tabel_referensi);
        $this->assertSame(1, (int) $jurnal[0]->id_referensi);
        $this->assertSame(2, (int) $jurnal[0]->id_unit);
        $this->assertSame('2010100000', $jurnal[0]->no_akun);
        $this->assertSame('1010101000', $jurnal[1]->no_akun);
    }

    public function testBankPenuhMemakaiIdbankRekening(): void
    {
        $this->catat(0, 75000, 'BNI-001');

        $rows = $this->kasKeluar();
        $this->assertCount(1, $rows);
        $this->assertSame('BNI-001', $rows[0]->idbank);
        $this->assertSame(75000, (int) $rows[0]->jumlah);

        $jurnal = $this->jurnal();
        $this->assertCount(2, $jurnal);
        $this->assertSame('1010102000', $jurnal[1]->no_akun);
        $this->assertSame(75000, (int) $jurnal[1]->kredit);
    }

    public function testCampuranTunaiDanBankMenghasilkanDuaBaris(): void
    {
        $this->catat(40000, 60000, 'BNI-001');

        $rows = $this->kasKeluar();
        $this->assertCount(2, $rows);
        $this->assertSame(100000, (int) $rows[0]->jumlah + (int) $rows[1]->jumlah);
        $this->assertNull($rows[0]->idbank);
        $this->assertSame('BNI-001', $rows[1]->idbank);

        $jurnal = $this->jurnal();
        $this->assertCount(4, $jurnal);
        $this->assertSame(100000, array_sum(array_map(static fn ($r) => (int) $r->debet, $jurnal)));
        $this->assertSame(100000, array_sum(array_map(static fn ($r) => (int) $r->kredit, $jurnal)));
    }

    public function testTemplateBankKhususHilangMemakaiTemplateBankGenerik(): void
    {
        $this->db->table('template_jurnal')
            ->where('kode_template', 'pembelian_lunas_bank_BNI-001')
            ->delete();

        $this->catat(0, 50000, 'BNI-001');

        $jurnal = $this->jurnal();
        $this->assertCount(2, $jurnal);
        $this->assertSame('1010102000', $jurnal[1]->no_akun);
    }

    public function testKategoriTempoTidakAdaMembatalkanTanpaBaris(): void
    {
        $this->db->table('kategori_kas')->where('kategori', 'TEMPO')->delete();

        try {
            $this->catat(100000, 0);
            $this->fail('Pembayaran harus ditolak saat kategori TEMPO tidak tersedia.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TEMPO', $e->getMessage());
        }

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
        $this->assertSame(0, $this->db->table('jurnal')->countAllResults());
    }

    public function testTemplateJurnalTidakAdaMembatalkanTanpaBaris(): void
    {
        $this->db->table('template_jurnal')
            ->whereIn('kode_template', ['pembelian_lunas_bank_BNI-001', 'pembelian_lunas_bank'])
            ->delete();

        try {
            $this->catat(0, 50000, 'BNI-001');
            $this->fail('Pembayaran harus ditolak saat template jurnal tidak tersedia.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('template jurnal', strtolower($e->getMessage()));
        }

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
        $this->assertSame(0, $this->db->table('jurnal')->countAllResults());
    }

    public function testUnitPembelianTidakValidMembatalkanTanpaBaris(): void
    {
        $pembelian = $this->pembelian();
        $pembelian->unit_idunit = 0;

        try {
            (new PembayaranHutangKasKeluar())->catat(1, '2026-11-28', 100000, 0, null, $pembelian);
            $this->fail('Pembayaran harus ditolak saat unit pembelian tidak valid.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unit', strtolower($e->getMessage()));
        }

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }
}
