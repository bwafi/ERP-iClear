<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi potongan kasbon pada pembayaran payroll.
 *
 * Kasbon bisa tercatat di unit yang berbeda dari unit payroll (contoh: kasbon
 * unit 1, gaji dibayar unit 50). Potongan harus tetap terjadi dan jurnal
 * dibebankan ke unit kasbon. Klik bayar ulang tidak boleh menghapus potongan
 * yang sudah tersimpan.
 *
 * @internal
 */
final class PayrollKasbonDeductionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const PEGAWAI = 910;
    private const UNIT_PAYROLL = 92;
    private const UNIT_KASBON = 91;
    private const PAYROLL_ID = 1;
    private const KASBON_SISA = 100000;

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
            'hutang_piutang', 'pembayaran_hutang_piutang',
            'template_jurnal', 'jurnal',
            'kategori_kas', 'bank', 'kas_keluar',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL, LOGO VARCHAR(100) NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted INT NULL, ROLES TEXT NULL)');
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
        $q('CREATE TABLE hutang_piutang (
                id INT PRIMARY KEY AUTO_INCREMENT, kode TEXT NULL,
                jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                nama_pihak TEXT NULL, tanggal TEXT NULL, jatuh_tempo TEXT NULL,
                uraian TEXT NULL, total DOUBLE NULL, total_dibayar DOUBLE NULL,
                sisa DOUBLE NULL, status TEXT NULL, scope TEXT NULL,
                keterangan TEXT NULL, unit_id INT NULL, input_by INT NULL,
                deleted INT NULL DEFAULT 0, created_at TEXT NULL,
                updated_at TEXT NULL)');
        $q('CREATE TABLE pembayaran_hutang_piutang (
                id INT PRIMARY KEY AUTO_INCREMENT, hutang_piutang_id INT NULL,
                tanggal_bayar TEXT NULL, jumlah_bayar DOUBLE NULL,
                bayar_tunai DOUBLE NULL, bayar_bank DOUBLE NULL,
                bank_idbank TEXT NULL, sumber TEXT NULL,
                referensi_tipe TEXT NULL, referensi_id INT NULL,
                keterangan TEXT NULL, input_by INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE template_jurnal (
                idtemplate_jurnal INT PRIMARY KEY AUTO_INCREMENT,
                kode_template VARCHAR(255) NULL, no_akun VARCHAR(255) NULL,
                nama_akun VARCHAR(255) NULL, debet_kredit VARCHAR(10) NULL)');
        $q('CREATE TABLE jurnal (
                idjurnal INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                no_akun TEXT NULL, nama_akun TEXT NULL, debet DOUBLE NULL,
                kredit DOUBLE NULL, keterangan TEXT NULL,
                id_referensi INT NULL, tabel_referensi TEXT NULL,
                id_unit INT NULL, id_akun INT NULL)');
        $q('CREATE TABLE kategori_kas (
                idkategori_kas INT PRIMARY KEY, kategori VARCHAR(100) NULL)');
        $q('CREATE TABLE bank (
                idbank INT PRIMARY KEY, nama_bank VARCHAR(100) NULL,
                atas_nama VARCHAR(100) NULL, norek VARCHAR(50) NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT, tanggal DATE NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL,
                jumlah BIGINT NULL, idunit INT NULL, idbank INT NULL,
                penerima TEXT NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insertBatch([
            ['idunit' => self::UNIT_PAYROLL, 'NAMA_UNIT' => 'Unit Payroll', 'LOGO' => 'logo_iclear.png'],
            ['idunit' => self::UNIT_KASBON, 'NAMA_UNIT' => 'Unit Kasbon', 'LOGO' => 'logo_iclear.png'],
        ]);
        $this->db->table('jabatan')->insert([
            'ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Finance', 'ROLES_JABATAN' => '[]',
        ]);
        $this->db->table('menu')->insert([
            'idmenu' => 1, 'urutan' => 1, 'nama_menu' => 'Payroll',
            'roles' => '[]', 'url' => 'payroll', 'show_menu' => 1,
        ]);
        $this->db->table('akun')->insert([
            'ID_AKUN' => self::PEGAWAI, 'ID_UNIT' => self::UNIT_PAYROLL, 'ID_JABATAN' => 1,
            'NAMA_AKUN' => 'Mario Test', 'TERIMA_GAJI' => 1,
            'STATUS_PEGAWAI' => 1, 'deleted' => null, 'ROLES' => '[]',
        ]);
        $this->db->table('finance_payroll')->insert([
            'id' => self::PAYROLL_ID, 'unit_id' => self::UNIT_PAYROLL,
            'pegawai_id' => self::PEGAWAI, 'due_date' => '2026-10-25',
            'status' => 'rencana', 'total' => 3000000,
            'notes' => 'Otomatis dari salary_structures: Gaji Pokok Rp 3.000.000. Kasbon dipotong saat dibayar.',
        ]);
        $this->db->table('hutang_piutang')->insert([
            'kode' => 'KSB-TEST', 'jenis' => 'piutang', 'sumber_tipe' => 'kasbon',
            'pihak_tipe' => 'pegawai', 'pihak_id' => self::PEGAWAI,
            'tanggal' => '2026-10-20', 'total' => self::KASBON_SISA,
            'total_dibayar' => 0, 'sisa' => self::KASBON_SISA,
            'status' => 'belum_lunas', 'scope' => 'active',
            'unit_id' => self::UNIT_KASBON, 'deleted' => 0, 'is_projection' => 0,
        ]);
        $this->db->table('hutang_piutang')->insert([
            'kode' => 'KSB-LEGACY', 'jenis' => 'piutang', 'sumber_tipe' => 'kasbon',
            'pihak_tipe' => 'pegawai', 'pihak_id' => self::PEGAWAI,
            'tanggal' => '2026-09-01', 'total' => 50000,
            'total_dibayar' => 0, 'sisa' => 50000,
            'status' => 'belum_lunas', 'scope' => 'legacy',
            'unit_id' => self::UNIT_KASBON, 'deleted' => 0, 'is_projection' => 0,
        ]);
        $this->db->table('template_jurnal')->insertBatch([
            ['kode_template' => 'hp_kasbon_potong_payroll', 'no_akun' => '2010201000', 'nama_akun' => 'Utang Gaji dan Upah', 'debet_kredit' => 'debet'],
            ['kode_template' => 'hp_kasbon_potong_payroll', 'no_akun' => '1020201000', 'nama_akun' => 'Piutang Pegawai', 'debet_kredit' => 'kredit'],
        ]);
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => self::PEGAWAI,
            'ID_UNIT'    => self::UNIT_PAYROLL,
            'ID_JABATAN' => 1,
        ]);
    }

    private function bayar(): void
    {
        $this->post('payroll/bayar', [
            'id'        => self::PAYROLL_ID,
            'paid_date' => '2026-10-25',
        ]);
    }

    public function testKasbonBedaUnitDipotongDariGaji(): void
    {
        $this->sesi();
        $this->bayar();

        $row = $this->db->table('finance_payroll')->where('id', self::PAYROLL_ID)->get()->getRow();
        $this->assertSame(self::KASBON_SISA, (int) $row->potongan_kasbon);
        $this->assertSame(2900000, (int) $row->total_bersih);

        $kasbon = $this->db->table('hutang_piutang')->where('kode', 'KSB-TEST')->get()->getRow();
        $this->assertSame(0.0, (float) $kasbon->sisa);
        $this->assertSame('lunas', $kasbon->status);

        // Kasbon legacy tidak ikut dipotong (sama dengan getSisaKasbonPegawai).
        $legacy = $this->db->table('hutang_piutang')->where('kode', 'KSB-LEGACY')->get()->getRow();
        $this->assertSame(50000.0, (float) $legacy->sisa);
        $this->assertSame('belum_lunas', $legacy->status);

        $jurnal = $this->db->table('jurnal')->get()->getResult();
        $this->assertNotEmpty($jurnal, 'Jurnal potongan kasbon harus terposting.');
        foreach ($jurnal as $baris) {
            $this->assertSame(
                self::UNIT_KASBON,
                (int) $baris->id_unit,
                'Jurnal potongan dibebankan ke unit kasbon, bukan unit payroll.'
            );
        }
    }

    public function testHalamanPayrollMenampilkanPotonganKasbonDanTotalBersih(): void
    {
        $this->sesi();
        $html = (string) $this->get('payroll?bulan=2026-10')->getBody();

        // Total kolom langsung angka bersih; keterangan kasbon pindah ke Catatan.
        $this->assertStringContainsString('Rp 2.900.000', $html);
        $this->assertStringContainsString('Gaji Pokok Rp 3.000.000. Dikurangi kasbon Rp 100.000 saat dibayar.', $html);
        $this->assertStringNotContainsString('Bersih Rp', $html);

        // Form koreksi menerima catatan & nominal kasbon yang sama.
        $this->assertStringContainsString('data-kasbon="100000"', $html);
        $this->assertStringNotContainsString('edit_register_kasbon_hint', $html);
    }

    public function testCatatanKasbonLamaDiperbaruiBukanDitumpuk(): void
    {
        $this->db->table('finance_payroll')->where('id', self::PAYROLL_ID)->update([
            'notes' => 'Catatan Finance. Dikurangi kasbon Rp 50.000 saat dibayar.',
        ]);

        $this->sesi();
        $html = (string) $this->get('payroll?bulan=2026-10')->getBody();

        $this->assertStringContainsString('Catatan Finance. Dikurangi kasbon Rp 100.000 saat dibayar.', $html);
        $this->assertStringNotContainsString('Rp 50.000 saat dibayar', $html);
    }

    public function testModeSemuaPeriodeTetapMenampilkanPotonganKasbon(): void
    {
        $this->sesi();
        $html = (string) $this->get('payroll?bulan=')->getBody();

        $this->assertStringContainsString('Dikurangi kasbon Rp 100.000', $html);
    }

    public function testKlikBayarUlangTidakMenghapusPotonganKasbon(): void
    {
        $this->sesi();
        $this->bayar();
        $this->bayar();

        $row = $this->db->table('finance_payroll')->where('id', self::PAYROLL_ID)->get()->getRow();
        $this->assertSame(self::KASBON_SISA, (int) $row->potongan_kasbon);
        $this->assertSame(2900000, (int) $row->total_bersih);
    }
}
