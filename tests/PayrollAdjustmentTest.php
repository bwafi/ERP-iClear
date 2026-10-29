<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Penyesuaian gaji manual (potongan SP / bonus kinerja): diinput lewat
 * koreksi register dan dipakai saat payroll ditandai dibayar.
 *
 * @internal
 */
final class PayrollAdjustmentTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const PEGAWAI = 910;
    private const UNIT = 92;
    private const PAYROLL_ID = 1;

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach ([
            'payroll_adjustment', 'finance_payroll', 'akun', 'unit',
            'jabatan', 'menu', 'spv_units', 'pelanggan', 'service',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted INT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN VARCHAR(100) NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                service_by INT NULL, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, created_at TEXT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                refund_garansi_jumlah REAL NULL,
                refund_garansi_tanggal TEXT NULL)');
        $q('CREATE TABLE finance_payroll (
                id INT PRIMARY KEY AUTO_INCREMENT, unit_id INT NULL,
                pegawai_id INT NULL, due_date DATE NULL, paid_date DATE NULL,
                status VARCHAR(20) NULL, total BIGINT NULL,
                potongan_kasbon BIGINT NULL, potongan_lain BIGINT NULL,
                bonus_lain BIGINT NULL, total_bersih BIGINT NULL,
                notes TEXT NULL, created_by INT NULL, sumber VARCHAR(20) NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE payroll_adjustment (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                unit_id INT NULL, period CHAR(7) NULL, jenis VARCHAR(20) NULL,
                jumlah DECIMAL(15,2) NULL, keterangan VARCHAR(255) NULL,
                created_by INT NULL, created_at DATETIME NULL,
                updated_at DATETIME NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insert(['idunit' => self::UNIT, 'NAMA_UNIT' => 'Unit Test']);
        $this->db->table('jabatan')->insert([
            'ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Finance', 'ROLES_JABATAN' => '[]',
        ]);
        $this->db->table('menu')->insert([
            'idmenu' => 1, 'urutan' => 1, 'nama_menu' => 'Payroll',
            'roles' => '[]', 'url' => 'payroll', 'show_menu' => 1,
        ]);
        $this->db->table('akun')->insert([
            'ID_AKUN' => self::PEGAWAI, 'ID_UNIT' => self::UNIT, 'ID_JABATAN' => 1,
            'NAMA_AKUN' => 'Mario Test', 'TERIMA_GAJI' => 1,
            'STATUS_PEGAWAI' => 1, 'deleted' => null,
        ]);
        $this->db->table('finance_payroll')->insert([
            'id' => self::PAYROLL_ID, 'unit_id' => self::UNIT,
            'pegawai_id' => self::PEGAWAI, 'due_date' => '2026-10-25',
            'status' => 'rencana', 'total' => 3000000,
        ]);
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => self::PEGAWAI,
            'ID_UNIT'    => self::UNIT,
            'ID_JABATAN' => 1,
        ]);
    }

    private function adjustment(string $jenis, float $jumlah): ?object
    {
        return $this->db->table('payroll_adjustment')
            ->where('employee_id', self::PEGAWAI)
            ->where('period', '2026-10')
            ->where('jenis', $jenis)
            ->get()->getRow();
    }

    public function testKoreksiRegisterMenyimpanPotonganDanBonus(): void
    {
        $this->sesi();

        $this->post('payroll/update-register', [
            'id'       => self::PAYROLL_ID,
            'total'    => '3000000',
            'due_date' => '2026-10-25',
            'notes'    => '',
            'potongan' => '700000',
            'potongan_keterangan' => 'SP1',
            'bonus' => '500000',
            'bonus_keterangan' => 'Bonus kinerja',
        ]);

        $potongan = $this->adjustment('potongan', 0);
        $bonus = $this->adjustment('bonus', 0);

        $this->assertNotNull($potongan, 'potongan harus tersimpan');
        $this->assertSame(700000.0, (float) $potongan->jumlah);
        $this->assertSame('SP1', $potongan->keterangan);
        $this->assertNotNull($bonus, 'bonus harus tersimpan');
        $this->assertSame(500000.0, (float) $bonus->jumlah);
        $this->assertSame('Bonus kinerja', $bonus->keterangan);
    }

    public function testBayarMenambahBonusDanMemotongPotongan(): void
    {
        $this->sesi();

        $this->db->table('payroll_adjustment')->insertBatch([
            ['employee_id' => self::PEGAWAI, 'unit_id' => self::UNIT, 'period' => '2026-10', 'jenis' => 'potongan', 'jumlah' => 700000, 'keterangan' => 'SP1'],
            ['employee_id' => self::PEGAWAI, 'unit_id' => self::UNIT, 'period' => '2026-10', 'jenis' => 'bonus', 'jumlah' => 500000, 'keterangan' => 'Bonus'],
        ]);

        $this->post('payroll/bayar', [
            'id'        => self::PAYROLL_ID,
            'paid_date' => '2026-10-25',
        ]);

        $row = $this->db->table('finance_payroll')->where('id', self::PAYROLL_ID)->get()->getRow();

        $this->assertSame('dibayar', $row->status);
        $this->assertSame(700000, (int) $row->potongan_lain);
        $this->assertSame(500000, (int) $row->bonus_lain);
        $this->assertSame(2800000, (int) $row->total_bersih);
    }

    public function testPotonganMelebihiGajiTidakMembuatTotalMinus(): void
    {
        $this->sesi();

        $this->db->table('payroll_adjustment')->insert([
            'employee_id' => self::PEGAWAI, 'unit_id' => self::UNIT,
            'period' => '2026-10', 'jenis' => 'potongan',
            'jumlah' => 5000000, 'keterangan' => 'SP berat',
        ]);

        $this->post('payroll/bayar', [
            'id'        => self::PAYROLL_ID,
            'paid_date' => '2026-10-25',
        ]);

        $row = $this->db->table('finance_payroll')->where('id', self::PAYROLL_ID)->get()->getRow();

        $this->assertSame(0, (int) $row->total_bersih);
    }
}
