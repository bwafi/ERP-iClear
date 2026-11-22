<?php

namespace Tests\unit;

use App\Models\ModelNoAkun;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ModelNoAkunKasMasukTest extends CIUnitTestCase
{
    private const TABEL = ['no_akun'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->query('CREATE TABLE no_akun (
            no_akun VARCHAR(20) NOT NULL PRIMARY KEY,
            nama_akun TEXT NULL,
            jenis_akun TEXT NULL)');

        $rows = [
            ['1000000000', 'Aset', 'Aset'],
            ['1010101000', 'Kas Besar', 'Kas & Bank'],
            ['1020000000', 'Piutang Usaha', 'Piutang'],
            ['2000000000', 'Hutang Usaha', 'Liabilitas'],
            ['3000000000', 'Modal', 'Ekuitas'],
            ['4000000000', 'Pendapatan', 'Pendapatan'],
            ['4100000000', 'Pendapatan & Beban Pajak Penghasilan', 'Pendapatan & Beban Pajak Penghasilan'],
            ['5000000000', 'HPP', 'Harga Pokok Penjualan'],
            ['6000000000', 'Beban Operasional', 'Beban Operasional'],
            ['6010000000', 'Beban Gaji', 'Beban Gaji'],
            ['1200000000', 'Akumulasi Penyusutan Aset', 'Akumulasi Penyusutan Aset'],
            ['1300000000', 'Tanpa Jenis', null],
            ['1400000000', 'Persediaan', 'Persediaan'],
        ];
        foreach ($rows as $r) {
            $this->db->table('no_akun')->insert([
                'no_akun' => $r[0], 'nama_akun' => $r[1], 'jenis_akun' => $r[2],
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testGetAkunMasukMenyaringAkunTerlarang(): void
    {
        $akun = (new ModelNoAkun())->getAkunMasuk();
        $nomor = array_map(static fn ($r) => (string) $r->no_akun, $akun);
        sort($nomor);

        $this->assertSame([
            '1000000000', '1020000000', '1400000000', '2000000000',
            '3000000000', '4000000000', '4100000000',
        ], $nomor);
    }

    public function testBolehUntukKasMasukKonsistenDenganQuery(): void
    {
        $model = new ModelNoAkun();

        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Pendapatan']));
        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Pendapatan & Beban Pajak Penghasilan']));
        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Piutang']));

        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => null]));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => '']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Kas & Bank']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Beban Operasional']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => ' beban gaji ']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Harga Pokok Penjualan']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Akumulasi Penyusutan Aset']));
    }
}
