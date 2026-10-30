<?php

namespace Tests\unit;

use App\Services\Incentive\IncentiveCalculationService;
use App\Services\Kpi\KpiCalculationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi insentif Oktober 2026:
 * - HO (unit 50): pool cabang = rate × gross profit cabang yang capai target
 *   (1% bila < 3 cabang capai, 1,5% bila ≥ 3), ditambah pool global 1,5% bila
 *   target global capai; dibagi rata karyawan HO aktif yang menerima gaji.
 * - Probolinggo (unit 1): pool 3% × gross profit bila target capai,
 *   dibagi rata staff toko (KT, teknisi, admin, CS).
 * - Periode sebelum Oktober 2026 tetap memakai incentive_members lama.
 *
 * @internal
 */
final class IncentiveHoProbolinggoTest extends CIUnitTestCase
{
    private const BULAN = '10';
    private const TAHUN = '2026';
    private const TANGGAL = '2026-10-15';

    private const HO_A = 701;
    private const HO_B = 702;
    private const HO_NONAKTIF = 703;
    private const HO_TANPA_GAJI = 704;
    private const PROB_KT = 801;
    private const PROB_TEK_A = 802;
    private const PROB_TEK_B = 803;
    private const PROB_CS = 804;
    private const PROB_DIREKTUR = 805;
    private const GENTENG_A = 901;
    private const GENTENG_B = 902;
    private const GENTENG_C = 903;
    private const GENTENG_D = 904;

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
            'unit', 'akun', 'kpi_components', 'kpi_targets', 'penjualan',
            'detail_penjualan', 'service',
            'incentive_groups', 'incentive_members', 'incentive_rules',
            'incentive_overrides',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL, is_trial INT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted INT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(150) NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context VARCHAR(30) NULL,
                target_value DECIMAL(15,2) NULL, effective_from DATE NULL,
                effective_to DATE NULL)');
        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                tanggal DATE NULL, total_penjualan DECIMAL(15,2) NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                service_by INT NULL, refund_garansi_jumlah REAL NULL,
                refund_garansi_tanggal DATE NULL)');
        $q('CREATE TABLE incentive_groups (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(255) NULL,
                description TEXT NULL, is_active INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE incentive_members (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                employee_id INT NULL, unit_id INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, is_active INT NULL)');
        $q('CREATE TABLE incentive_rules (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                kpi_component_id INT NULL, incentive_name VARCHAR(255) NULL,
                calculation_type VARCHAR(30) NULL, base_value REAL NULL,
                division_method VARCHAR(30) NULL, minimum_achievement REAL NULL,
                effective_from DATE NULL, effective_to DATE NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE incentive_overrides (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                unit_id INT NULL, period CHAR(7) NULL, rate DECIMAL(5,2) NULL,
                reason VARCHAR(255) NULL, created_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'ICLEAR Probolinggo', 'is_trial' => 0],
            ['idunit' => 2, 'NAMA_UNIT' => 'ICLEAR Jember', 'is_trial' => 0],
            ['idunit' => 5, 'NAMA_UNIT' => 'ICLEAR Genteng', 'is_trial' => 1],
            ['idunit' => 50, 'NAMA_UNIT' => 'Head Office', 'is_trial' => 0],
        ]);

        $this->db->table('kpi_components')->insert([
            'id' => 1, 'code' => 'OMSET_TOKO', 'name' => 'Omset Toko',
        ]);
        $this->db->table('kpi_targets')->insertBatch([
            ['kpi_component_id' => 1, 'unit_id' => 1, 'context' => 'default', 'target_value' => 50000000, 'effective_from' => '2024-01-01'],
            ['kpi_component_id' => 1, 'unit_id' => 2, 'context' => 'default', 'target_value' => 50000000, 'effective_from' => '2024-01-01'],
            ['kpi_component_id' => 1, 'unit_id' => 5, 'context' => 'default', 'target_value' => 35000000, 'effective_from' => '2024-01-01'],
        ]);

        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => self::HO_A, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO A', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::HO_B, 'ID_UNIT' => 50, 'ID_JABATAN' => 40, 'NAMA_AKUN' => 'HO B', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => self::HO_NONAKTIF, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO Nonaktif', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 0, 'deleted' => null],
            ['ID_AKUN' => self::HO_TANPA_GAJI, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO Tanpa Gaji', 'TERIMA_GAJI' => 0, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::PROB_KT, 'ID_UNIT' => 1, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'KT Probolinggo', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::PROB_TEK_A, 'ID_UNIT' => 1, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Teknisi A', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::PROB_TEK_B, 'ID_UNIT' => 1, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Teknisi B', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::PROB_CS, 'ID_UNIT' => 1, 'ID_JABATAN' => 42, 'NAMA_AKUN' => 'CS Probolinggo', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::PROB_DIREKTUR, 'ID_UNIT' => 1, 'ID_JABATAN' => 1, 'NAMA_AKUN' => 'Direktur', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::GENTENG_A, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Genteng A', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::GENTENG_B, 'ID_UNIT' => 5, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'Genteng B', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::GENTENG_C, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Genteng C', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::GENTENG_D, 'ID_UNIT' => 5, 'ID_JABATAN' => 35, 'NAMA_AKUN' => 'Genteng D', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
        ]);

        // Oktober 2026 (omzet = GP = sub_total - hpp):
        // unit 1 = 60jt (capai), unit 2 = 40jt (tidak capai),
        // unit 5 trial = 70jt (tidak masuk pool HO).
        $this->sebarOmset(1, '2026-10-05', 80000000, 20000000);
        $this->sebarOmset(2, '2026-10-06', 50000000, 10000000);
        $this->sebarOmset(5, '2026-10-07', 90000000, 20000000);

        // September 2026 utk jalur lama (membership).
        $this->sebarOmset(1, '2026-09-05', 12000000, 2000000);

        $this->db->table('incentive_groups')->insert([
            'id' => 1, 'code' => 'KEPALA_TOKO', 'is_active' => 1,
        ]);
        $this->db->table('incentive_members')->insert([
            'incentive_group_id' => 1, 'employee_id' => self::PROB_KT,
            'unit_id' => 1, 'effective_from' => '2024-01-01',
            'effective_to' => null, 'is_active' => 1,
        ]);
        $this->db->table('incentive_rules')->insert([
            'incentive_group_id' => 1, 'kpi_component_id' => 1,
            'base_value' => 1, 'division_method' => 'per_member',
            'minimum_achievement' => 0, 'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);
    }

    private function sebarOmset(int $unitId, string $tanggal, float $subTotal, float $hpp): void
    {
        $this->db->table('penjualan')->insert([
            'unit_idunit' => $unitId, 'tanggal' => $tanggal,
            'total_penjualan' => $subTotal,
        ]);
        $penjualanId = $this->db->insertID();

        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => $penjualanId,
            'sub_total' => $subTotal,
            'hpp_penjualan' => $hpp,
        ]);
    }

    private function insentif(int $employeeId, int $unitId, string $bulan = self::BULAN): float
    {
        return (new KpiCalculationService())->computeIncentive(
            $employeeId,
            $unitId,
            $bulan,
            self::TAHUN,
            sprintf('%s-%s-15', self::TAHUN, $bulan),
        );
    }

    public function testHoMemakaiPoolCabangCapaiTargetDibagiHeadcount(): void
    {
        // Hanya 1 cabang non-trial capai (unit 1 = 60jt) → rate 1%;
        // pool cabang = 1% × 60jt = 600rb. Global: omzet unit 1+2 = 100jt
        // ≥ Σ target toko 100jt → pool global = 1,5% × 100jt = 1,5jt.
        // Total 2,1jt; 2 karyawan HO aktif → 1,05jt.
        $this->assertSame(1050000.0, $this->insentif(self::HO_A, 50));
        $this->assertSame(1050000.0, $this->insentif(self::HO_B, 50));
    }

    public function testHoNonaktifDanTanpaGajiTidakDapat(): void
    {
        $this->assertSame(0.0, $this->insentif(self::HO_NONAKTIF, 50));
        $this->assertSame(0.0, $this->insentif(self::HO_TANPA_GAJI, 50));
    }

    public function testHoDapatNolBilaTidakAdaCabangCapaiTarget(): void
    {
        // November: tanpa penjualan → semua cabang di bawah target.
        $this->assertSame(
            0.0,
            $this->insentif(self::HO_A, 50, '11'),
        );
    }

    public function testProbolinggoMemakaiPoolTigaPersenDibagiEmpatStaffToko(): void
    {
        // Pool = 3% × 60jt = 1,8jt; 4 staff toko (KT, 2 teknisi, CS) → 450rb.
        foreach ([self::PROB_KT, self::PROB_TEK_A, self::PROB_TEK_B, self::PROB_CS] as $id) {
            $this->assertSame(450000.0, $this->insentif($id, 1), 'akun ' . $id);
        }
    }

    public function testProbolinggoJabatanDiLuarStaffTokoTidakDapat(): void
    {
        $this->assertSame(0.0, $this->insentif(self::PROB_DIREKTUR, 1));
    }

    public function testProbolinggoNolBilaTargetTidakCapai(): void
    {
        // November: tanpa penjualan → 0 < target 50jt.
        $this->assertSame(0.0, $this->insentif(self::PROB_KT, 1, '11'));
    }

    public function testSeptemberMasihMemakaiAturanMembershipLama(): void
    {
        // September unit 1 = 10jt, rule lama per_member 1% → 100rb.
        $this->assertSame(100000.0, $this->insentif(self::PROB_KT, 1, '09'));
    }

    public function testGentengDuaAnggotaTetapSatuPersenPerAnggota(): void
    {
        // Simulasikan rule lama yang belum termigrasi: 3% pool / member_count.
        $this->pakaiRuleLamaKepalaToko();
        $this->tambahAnggotaGenteng([self::GENTENG_A, self::GENTENG_B]);

        // Omset unit 5 = 70jt. 1% per anggota = 700rb (bukan 3%/2 = 1,05jt).
        $this->assertSame(700000.0, $this->insentif(self::GENTENG_A, 5));
        $this->assertSame(700000.0, $this->insentif(self::GENTENG_B, 5));
    }

    public function testGentengEmpatAnggotaTetapSatuPersenPerAnggota(): void
    {
        $this->pakaiRuleLamaKepalaToko();
        $this->tambahAnggotaGenteng([
            self::GENTENG_A, self::GENTENG_B, self::GENTENG_C, self::GENTENG_D,
        ]);

        // 3%/4 = 0,75% (525rb) tidak boleh; aturan final tetap 1% (700rb).
        foreach ([self::GENTENG_A, self::GENTENG_B, self::GENTENG_C, self::GENTENG_D] as $id) {
            $this->assertSame(700000.0, $this->insentif($id, 5), 'akun ' . $id);
        }
    }

    public function testServiceInsentifGentengDuaAnggotaSatuPersen(): void
    {
        $this->pakaiRuleLamaKepalaToko();
        $this->tambahAnggotaGenteng([self::GENTENG_A, self::GENTENG_B]);

        $hasil = (new IncentiveCalculationService())->calculateGroupIncentive(
            'KEPALA_TOKO',
            5,
            70000000,
            100,
            self::TANGGAL,
        );

        $this->assertTrue($hasil['success']);
        $this->assertSame(700000.0, $hasil['incentive_amount']);
        $this->assertSame(1400000.0, $hasil['pool_amount']);
    }

    private function pakaiRuleLamaKepalaToko(): void
    {
        $this->db->table('incentive_rules')
            ->where('incentive_group_id', 1)
            ->update([
                'base_value' => 3.0,
                'division_method' => 'member_count',
            ]);
    }

    private function tambahAnggotaGenteng(array $employeeIds): void
    {
        $rows = [];
        foreach ($employeeIds as $id) {
            $rows[] = [
                'incentive_group_id' => 1,
                'employee_id' => $id,
                'unit_id' => 5,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
                'is_active' => 1,
            ];
        }

        $this->db->table('incentive_members')->insertBatch($rows);
    }
}
