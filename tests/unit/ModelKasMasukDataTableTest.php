<?php

namespace Tests\unit;

use App\Models\ModelKasMasuk;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ModelKasMasukDataTableTest extends CIUnitTestCase
{
    private const TABEL = ['kas_masuk', 'kategori_kas', 'bank', 'unit', 'no_akun'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $this->db->query('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->db->query('CREATE TABLE kategori_kas (idkategori_kas INT PRIMARY KEY, kategori TEXT NULL)');
        $this->db->query('CREATE TABLE no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $this->db->query('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, norek TEXT NULL)');
        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
            tanggal DATE NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
            deskripsi TEXT NULL, jumlah BIGINT DEFAULT 0, jenis VARCHAR(15) NULL,
            penerima TEXT NULL, idbank VARCHAR(20) NULL, idunit INT NULL,
            created_on TEXT NULL, updated_on TEXT NULL)');

        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit A'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit B'],
        ]);
        $this->db->table('kategori_kas')->insertBatch([
            ['idkategori_kas' => 1, 'kategori' => 'Pendapatan'],
            ['idkategori_kas' => 2, 'kategori' => 'Modal'],
        ]);
        $this->db->table('no_akun')->insertBatch([
            ['no_akun' => '4000000000', 'nama_akun' => 'Pendapatan Lain'],
        ]);
        $this->db->table('bank')->insertBatch([
            ['idbank' => 'BNI-001', 'nama_bank' => 'BNI', 'norek' => '5551'],
        ]);
        $this->db->table('kas_masuk')->insertBatch([
            ['tanggal' => '2026-11-01', 'kategori_idkategori' => 1, 'no_akun' => '4000000000', 'deskripsi' => 'Setoran tunai', 'jumlah' => 100000, 'jenis' => 'kredit', 'penerima' => 'Kasir', 'idbank' => null, 'idunit' => 1],
            ['tanggal' => '2026-11-02', 'kategori_idkategori' => 1, 'no_akun' => '4000000001', 'deskripsi' => 'Transfer BNI', 'jumlah' => 250000, 'jenis' => 'kredit', 'penerima' => 'PT Contoh', 'idbank' => 'BNI-001', 'idunit' => 1],
            ['tanggal' => '2026-11-03', 'kategori_idkategori' => 2, 'no_akun' => '4000000000', 'deskripsi' => 'Modal unit B', 'jumlah' => 400000, 'jenis' => 'kredit', 'penerima' => 'Pemilik', 'idbank' => null, 'idunit' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function model(): ModelKasMasuk
    {
        return new ModelKasMasuk();
    }

    public function testFilterTanggalUnitDanKategori(): void
    {
        $m = $this->model();

        $this->assertSame(3, $m->countAllKasMasuk());
        $this->assertSame(2, $m->countKasMasukFiltered('', '2026-11-01', '2026-11-02', 1, null));
        $this->assertSame(1, $m->countKasMasukFiltered('', null, null, 2, null));
        $this->assertSame(1, $m->countKasMasukFiltered('', null, null, null, 2));
    }

    public function testPencarianMencakupTeksDanId(): void
    {
        $m = $this->model();

        $this->assertSame(1, $m->countKasMasukFiltered('Transfer BNI'));
        $this->assertSame(2, $m->countKasMasukFiltered('Unit A'));
        $this->assertSame(1, $m->countKasMasukFiltered('BNI-001'));
        $this->assertSame(1, $m->countKasMasukFiltered('4000000001'));
        $this->assertSame(0, $m->countKasMasukFiltered('%'), 'Wildcard LIKE harus literal.');
        $this->assertSame(1, $m->countKasMasukFiltered('2'), 'Angka juga cocok ke idkas_masuk.');
    }

    public function testSumTunaiTransferDanTotal(): void
    {
        $m = $this->model();
        $sum = $m->sumKasMasukFiltered();

        $this->assertSame(750000.0, $sum['total']);
        $this->assertSame(500000.0, $sum['tunai']);
        $this->assertSame(250000.0, $sum['transfer']);
        $this->assertSame(3, $sum['n']);

        $filtered = $m->sumKasMasukFiltered('', null, null, 1);
        $this->assertSame(350000.0, $filtered['total']);
    }

    public function testBarisKasAwalTidakIkutDaftarDanRingkasan(): void
    {
        $this->db->table('kas_masuk')->insertBatch([
            ['tanggal' => '2026-11-04', 'deskripsi' => 'kas awal', 'jumlah' => 5000000, 'idunit' => 1, 'jenis' => null, 'idbank' => null],
            ['tanggal' => '2026-11-05', 'deskripsi' => '  KAS AWAL  ', 'jumlah' => 7000000, 'idunit' => 2, 'jenis' => null, 'idbank' => null],
        ]);

        $m = $this->model();

        $this->assertSame(3, $m->countAllKasMasuk(), 'Snapshot saldo awal bukan kas masuk.');
        $this->assertSame(3, $m->countKasMasukFiltered('', null, null, null));

        $sum = $m->sumKasMasukFiltered();
        $this->assertSame(750000.0, $sum['total']);
        $this->assertSame(3, $sum['n']);

        $rows = $m->getKasMasukDataTable(10, 0);
        $this->assertCount(3, $rows);
        $this->assertNotContains(
            'kas awal',
            array_map(static fn ($r) => strtolower(trim((string) $r->deskripsi)), $rows)
        );
    }

    public function testSumPendapatanRangeMengecualikanKasAwal(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => '2026-11-04', 'deskripsi' => 'kas awal', 'jumlah' => 5000000, 'idunit' => 1,
        ]);

        $m = $this->model();

        $this->assertSame(350000.0, $m->sumPendapatanRange('2026-11-01', '2026-11-30', 1));
        $this->assertSame(750000.0, $m->sumPendapatanRange('2026-11-01', '2026-11-30', null));
    }

    public function testExportTidakMemuatKasAwal(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => '2026-11-04', 'kategori_idkategori' => 1, 'no_akun' => '4000000000',
            'deskripsi' => 'kas awal', 'jumlah' => 5000000, 'idunit' => 1,
        ]);

        $m = $this->model();
        $rows = $m->getKasMasukFiltered(null, null, null);

        $this->assertCount(3, $rows);
        $this->assertNotContains(
            'kas awal',
            array_map(static fn ($r) => strtolower(trim((string) $r->deskripsi)), $rows)
        );
    }

    public function testPendapatanPerKategoriDanDetailMengecualikanKasAwal(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => '2026-11-04', 'kategori_idkategori' => 1, 'no_akun' => '4000000000',
            'deskripsi' => 'kas awal', 'jumlah' => 5000000, 'idunit' => 1,
        ]);

        $m = $this->model();

        $detail = $m->getPendapatanDetail('2026-11-01', '2026-11-30', null);
        $this->assertCount(3, $detail);
        $this->assertNotContains(
            'kas awal',
            array_map(static fn ($r) => strtolower(trim((string) $r->deskripsi)), $detail)
        );

        $perKategori = [];
        foreach ($m->sumPendapatanPerKategori('2026-11-01', '2026-11-30', null) as $row) {
            $perKategori[(string) $row->kategori] = (float) $row->total;
        }

        $this->assertSame(350000.0, $perKategori['Pendapatan'] ?? 0.0);
        $this->assertSame(400000.0, $perKategori['Modal'] ?? 0.0);
    }

    public function testUrutanDanPaginasi(): void
    {
        $m = $this->model();

        $desc = $m->getKasMasukDataTable(2, 0, '', 'kas_masuk.jumlah', 'DESC');
        $this->assertSame([400000.0, 250000.0], array_map(static fn ($r) => (float) $r->jumlah, $desc));

        $page2 = $m->getKasMasukDataTable(2, 2, '', 'kas_masuk.jumlah', 'DESC');
        $this->assertSame([100000.0], array_map(static fn ($r) => (float) $r->jumlah, $page2));

        $liar = $m->getKasMasukDataTable(3, 0, '', 'kas_masuk.jumlah; DROP TABLE kas_masuk', 'DESC');
        $this->assertSame(3, count($liar), 'Kolom urutan liar jatuh ke default dan tidak merusak query.');
    }
}
