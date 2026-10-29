<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Migrasi BackfillGentengIncentiveMembers:
 * - unit 5 (Genteng) hanya berisi anggota KEPALA_TOKO yang TERIMA_GAJI = 1,
 *   yaitu teknisi; admin/KT dengan TERIMA_GAJI = 0 tidak diikutkan,
 * - baris lama yang masih menempel di unit lain dipindah ke unit 5,
 * - idempoten (dijalankan dua kali hasilnya sama).
 */
class IncentiveGentengMembershipTest extends CIUnitTestCase
{
    private const MIGRASI = '2026-10-28-000200_BackfillGentengIncentiveMembers';

    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->hapusTabel();

        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('incentive_groups') . ' (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(255) NULL,
                description TEXT NULL, is_active INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('incentive_members') . ' (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                employee_id INT NULL, unit_id INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, is_active INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
        $this->db->query('CREATE TABLE ' . $this->db->prefixTable('akun') . ' (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted INT NULL)');

        $this->db->table('incentive_groups')->insert([
            'id' => 1, 'code' => 'KEPALA_TOKO', 'name' => 'Store Team', 'is_active' => 1,
        ]);
        $this->db->table('akun')->insertBatch([
            // Dua teknisi Genteng yang menerima gaji.
            ['ID_AKUN' => 67, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Dimas', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => 71, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Nanda', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            // Kepala toko Genteng tapi TERIMA_GAJI = 0 → tidak diikutkan.
            ['ID_AKUN' => 72, 'ID_UNIT' => 5, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'IRAA', 'TERIMA_GAJI' => 0, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            // Jabatan di luar group, pegawai nonaktif, dan pegawai terhapus.
            ['ID_AKUN' => 68, 'ID_UNIT' => 5, 'ID_JABATAN' => 40, 'NAMA_AKUN' => 'SPV', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => 69, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Nonaktif', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 0, 'deleted' => null],
            ['ID_AKUN' => 70, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Terhapus', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => 1],
        ]);

        // Baris lama Dimas masih menempel di unit 3.
        $this->db->table('incentive_members')->insert([
            'incentive_group_id' => 1, 'employee_id' => 67, 'unit_id' => 3,
            'effective_from' => '2024-01-01', 'effective_to' => null, 'is_active' => 1,
        ]);

        $path = APPPATH . 'Database/Migrations/' . self::MIGRASI . '.php';
        if (! is_file($path)) {
            $this->fail('Migration tidak ditemukan: ' . $path);
        }
        if (! class_exists('App\\Database\\Migrations\\BackfillGentengIncentiveMembers', false)) {
            require_once $path;
        }
    }

    protected function tearDown(): void
    {
        $this->hapusTabel();
        parent::tearDown();
    }

    public function testHanyaTeknisiPenerimaGajiYangMenjadiAnggotaUnit5(): void
    {
        (new \App\Database\Migrations\BackfillGentengIncentiveMembers())->up();

        $anggota = $this->db->table('incentive_members')
            ->select('employee_id, unit_id, is_active')
            ->where('unit_id', 5)
            ->where('is_active', 1)
            ->orderBy('employee_id')
            ->get()->getResultArray();

        $this->assertSame([67, 71], array_map(
            static fn ($row) => (int) $row['employee_id'],
            $anggota,
        ));
        $this->assertSame(0, $this->db->table('incentive_members')->where('employee_id', 72)->countAllResults());
    }

    public function testBarisLamaDimasDipindahKeUnit5DanTidakTerduplikasi(): void
    {
        (new \App\Database\Migrations\BackfillGentengIncentiveMembers())->up();
        (new \App\Database\Migrations\BackfillGentengIncentiveMembers())->up();

        $this->assertSame(1, $this->db->table('incentive_members')->where('employee_id', 67)->countAllResults());
        $this->assertSame(5, (int) $this->db->table('incentive_members')->where('employee_id', 67)->get()->getRow()->unit_id);
        $this->assertSame(0, $this->db->table('incentive_members')->where('unit_id', 3)->countAllResults());
        $this->assertSame(2, $this->db->table('incentive_members')->where('unit_id', 5)->countAllResults());
    }

    private function hapusTabel(): void
    {
        foreach (['incentive_members', 'incentive_groups', 'akun'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable($tabel));
        }
    }
}
