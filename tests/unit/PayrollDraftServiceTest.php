<?php

namespace Tests\unit;

use App\Services\Payroll\PayrollDraftService;
use CodeIgniter\Test\CIUnitTestCase;

class PayrollDraftServiceTest extends CIUnitTestCase
{
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
        foreach (['finance_payroll', 'akun', 'unit'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q('DROP TABLE IF EXISTS finance_payroll');
        $q('DROP TABLE IF EXISTS akun');
        $q('DROP TABLE IF EXISTS unit');

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY,
                NAMA_AKUN TEXT NULL,
                STATUS_PEGAWAI INT NULL,
                JENIS_PEGAWAI INT NULL,
                deleted INT NULL
            )');
        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE finance_payroll (
                id INT PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL,
                pegawai_id INT NULL,
                due_date TEXT NULL,
                status TEXT NULL,
                total REAL NULL,
                sumber TEXT NULL
            )');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'Probolinggo'), (50, 'Head Office')");
        $q("INSERT INTO akun (ID_AKUN, NAMA_AKUN, STATUS_PEGAWAI, JENIS_PEGAWAI, deleted) VALUES
            (54, 'Indah', 0, 100, NULL),
            (60, 'Karyawan Aktif', 1, 1, NULL)");
        $q("INSERT INTO finance_payroll (id, unit_id, pegawai_id, due_date, status, total, sumber) VALUES
            (17, 50, 54, '2026-11-30', 'rencana', 1500000, 'auto'),
            (27, 1, 54, '2026-11-30', 'rencana', 1500000, 'auto'),
            (50, 1, 54, '2026-10-31', 'dibayar', 2354860, 'auto'),
            (60, 1, 60, '2026-11-30', 'rencana', 2000000, 'auto')");
    }

    public function testCariHanyaDraftPegawaiNonaktif(): void
    {
        $rows = (new PayrollDraftService($this->db))->cari([
            'hanya_nonaktif' => true,
            'bulan'          => '2026-11',
        ]);

        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);

        $this->assertSame([17, 27], $ids, 'hanya draft rencana milik pegawai nonaktif');
    }

    public function testCariTidakPernahMengembalikanBarisDibayar(): void
    {
        $rows = (new PayrollDraftService($this->db))->cari([
            'pegawai_id' => 54,
            'bulan'      => '2026-10',
        ]);

        $this->assertSame([], $rows, 'baris dibayar bukan draft');
    }

    public function testBatalkanMenghapusDraftRencana(): void
    {
        $service = new PayrollDraftService($this->db);
        $service->batalkan(17);

        $this->assertSame(
            0,
            $this->db->table('finance_payroll')->where('id', 17)->countAllResults(),
            'draft rencana terhapus'
        );
    }

    public function testBatalkanMenolakBarisDibayar(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hanya draft berstatus rencana yang bisa dibatalkan.');

        (new PayrollDraftService($this->db))->batalkan(50);
    }

    public function testBatalkanMenolakIdTidakDikenal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Draft payroll tidak ditemukan.');

        (new PayrollDraftService($this->db))->batalkan(999);
    }
}
