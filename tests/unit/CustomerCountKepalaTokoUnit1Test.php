<?php

namespace Tests\unit;

use App\Services\Kpi\CustomerCalculator;
use App\Services\Kpi\KpiCalculationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi: realisasi KPI Total Pelanggan (CUSTOMER_COUNT).
 *
 * Kepala Toko Unit 1 merangkap Admin dan tidak mengerjakan service sendiri,
 * sehingga realisasinya diambil dari akumulasi service para teknisi unit 1
 * (CustomerCalculator::handledByUnitTeknisi). Target TETAP target milik Kepala
 * Toko (target_value unit ÷ 2), bukan penjumlahan target kedua teknisi.
 *
 * Teknisi dan Kepala Toko unit lain tidak boleh berubah: keduanya tetap dinilai
 * dari service yang mereka tangani sendiri (service_by).
 *
 * @internal
 */
final class CustomerCountKepalaTokoUnit1Test extends CIUnitTestCase
{
    private const BULAN = '09';
    private const TAHUN = '2026';

    /** Unit 1 = ICLEAR Probolinggo (dua teknisi: 43 & 64; KT: 65). */
    private const KT_UNIT1   = 65;
    private const TEK_A      = 43;
    private const TEK_B      = 64;
    /** Unit 2 (KT 60, teknisi 68) — kontrol "unit lain tidak berubah". */
    private const KT_UNIT2   = 60;
    private const TEK_UNIT2  = 68;

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
            'akun', 'kpi_components', 'kpi_weights', 'kpi_targets',
            'service', 'penjualan',
            'kpi_evaluations', 'kpi_attendance_detail', 'kpi_attendance_off',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY,
                ID_JABATAN INT NULL,
                ID_UNIT INT NULL,
                STATUS_PEGAWAI INT NULL,
                NAMA_AKUN VARCHAR(150) NULL,
                ALAMAT TEXT NULL)');

        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY,
                code VARCHAR(50) NULL,
                name VARCHAR(150) NULL,
                type VARCHAR(30) NULL,
                calculation_strategy VARCHAR(50) NULL,
                is_active INT NULL)');

        $q('CREATE TABLE kpi_weights (
                id INT PRIMARY KEY AUTO_INCREMENT,
                kpi_component_id INT NULL,
                position_id INT NULL,
                weight DECIMAL(5,2) NULL,
                weight_group VARCHAR(20) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL)');

        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                kpi_component_id INT NULL,
                unit_id INT NULL,
                position_id INT NULL,
                context VARCHAR(30) NULL,
                target_value DECIMAL(12,2) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL)');

        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT,
                service_by INT NULL,
                unit_idunit INT NULL,
                created_at DATETIME NULL)');

        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INT NULL,
                tanggal DATE NULL,
                id_pelanggan INT NULL)');

        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NULL,
                kpi_component_id INT NULL,
                evaluator_id INT NULL,
                evaluation_date DATE NULL,
                raw_score DECIMAL(5,2) NULL,
                period_year INT NULL,
                period_month INT NULL)');

        $q('CREATE TABLE kpi_attendance_detail (
                id INT PRIMARY KEY AUTO_INCREMENT,
                evaluation_id INT NULL)');

        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NULL,
                evaluation_date DATE NULL,
                period_month INT NULL,
                period_year INT NULL,
                evaluator_id INT NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('kpi_components')->insert([
            'id' => 3, 'code' => 'CUSTOMER_COUNT', 'name' => 'Total Pelanggan',
            'type' => 'automatic', 'calculation_strategy' => 'customer_count', 'is_active' => 1,
        ]);

        // Bobot 100 hanya utk CUSTOMER_COUNT → total_score = skor customer saja.
        $this->db->table('kpi_weights')->insertBatch([
            ['kpi_component_id' => 3, 'position_id' => 41, 'weight' => 100, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => null],
            ['kpi_component_id' => 3, 'position_id' => 36, 'weight' => 100, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => null],
        ]);

        // Target pelanggan UNIT (full); engine membagi ÷2 di kode.
        $this->db->table('kpi_targets')->insertBatch([
            ['kpi_component_id' => 3, 'unit_id' => 1, 'position_id' => null, 'context' => 'default', 'target_value' => 220, 'effective_from' => '2024-01-01', 'effective_to' => null],
            ['kpi_component_id' => 3, 'unit_id' => 2, 'position_id' => null, 'context' => 'default', 'target_value' => 180, 'effective_from' => '2024-01-01', 'effective_to' => null],
        ]);

        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => self::KT_UNIT1,  'ID_JABATAN' => 41, 'ID_UNIT' => 1, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'KT Unit 1', 'ALAMAT' => 'Probolinggo'],
            ['ID_AKUN' => self::TEK_A,     'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'Teknisi A', 'ALAMAT' => 'Probolinggo'],
            ['ID_AKUN' => self::TEK_B,     'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'Teknisi B', 'ALAMAT' => 'Probolinggo'],
            ['ID_AKUN' => self::KT_UNIT2,  'ID_JABATAN' => 41, 'ID_UNIT' => 2, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'KT Unit 2', 'ALAMAT' => 'Jember'],
            ['ID_AKUN' => self::TEK_UNIT2, 'ID_JABATAN' => 36, 'ID_UNIT' => 2, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'Teknisi Unit 2', 'ALAMAT' => 'Jember'],
        ]);

        // September 2026.
        $this->sebarService(self::TEK_A,     1, '2026-09-05', 60); // teknisi unit 1
        $this->sebarService(self::TEK_B,     1, '2026-09-06', 50); // teknisi unit 1 → Σ 110
        $this->sebarService(self::KT_UNIT1,  1, '2026-09-07', 7);  // KT sendiri → TIDAK dihitung utk KT unit 1
        $this->sebarService(self::KT_UNIT2,  2, '2026-09-08', 30); // KT unit 2 (own)
        $this->sebarService(self::TEK_UNIT2, 2, '2026-09-09', 40); // teknisi unit 2 → TIDAK dipakai KT unit 2

        // Juli 2026: Σ teknisi unit 1 = 230 ≥ target penuh (220) → skor tercap (100).
        $this->sebarService(self::TEK_A, 1, '2026-07-05', 130);
        $this->sebarService(self::TEK_B, 1, '2026-07-06', 100);

        // Agustus 2026 (di bawah target): Σ teknisi unit 1 = 30 < 220.
        $this->sebarService(self::TEK_A,    1, '2026-08-05', 20);
        $this->sebarService(self::TEK_B,    1, '2026-08-06', 10);

        // Penjualan ber-id_pelanggan TIDAK boleh masuk realisasi KT unit 1
        // (definisi: pelanggan SERVICE yang dikerjakan para teknisi).
        $this->db->table('penjualan')->insertBatch([
            ['unit_idunit' => 1, 'tanggal' => '2026-09-10', 'id_pelanggan' => 9001],
            ['unit_idunit' => 1, 'tanggal' => '2026-09-11', 'id_pelanggan' => 9002],
        ]);
    }

    private function sebarService(int $employeeId, int $unitId, string $createdAt, int $count): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['service_by' => $employeeId, 'unit_idunit' => $unitId, 'created_at' => $createdAt . ' 10:00:00'];
        }
        $this->db->table('service')->insertBatch($rows);
    }

    /** @return array<string,mixed> item CUSTOMER_COUNT dari hasil KPI */
    private function customerItem(int $employeeId, int $unitId, string $month): array
    {
        $res = (new KpiCalculationService())->calculateForEmployee(
            $employeeId,
            $unitId,
            $month,
            self::TAHUN,
            'default',
            sprintf('%s-%s-15', self::TAHUN, $month)
        );

        foreach ($res['items'] as $item) {
            if ($item['code'] === 'CUSTOMER_COUNT') {
                return $item;
            }
        }

        $this->fail('Item CUSTOMER_COUNT tidak ditemukan.');
    }

    // ── Calculator langsung ────────────────────────────────────────

    public function testHandledByUnitTeknisiMengakumulasiTeknisiSaja(): void
    {
        $calc = new CustomerCalculator();

        // Unit 1: 60 + 50 = 110 (KT sendiri (7) & penjualan tidak ikut).
        $this->assertSame(110, $calc->handledByUnitTeknisi(1, 9, 2026));
        // Unit 2: hanya teknisi unit 2 (40), bukan KT-nya.
        $this->assertSame(40, $calc->handledByUnitTeknisi(2, 9, 2026));
    }

    // ── Kepala Toko Unit 1: akumulasi teknisi, target tetap target KT ──

    public function testKepalaTokoUnit1MemakaiAkumulasiTeknisiDanTargetPenuh(): void
    {
        $item = $this->customerItem(self::KT_UNIT1, 1, self::BULAN);

        $this->assertSame(110.0, (float) $item['actual'], 'Realisasi = Σ service dua teknisi (110), bukan service KT sendiri (7).');
        $this->assertSame(220.0, (float) $item['target'], 'Target KT unit 1 = nilai PENUH (220), BUKAN 220÷2.');
        // Kalau target masih 110, 110 ≥ 110 → 100. Dengan target penuh, 110 < 220 → 0.
        $this->assertSame(0.0, (float) $item['achievement'], '110 < target penuh 220 → 0.');
    }

    public function testKepalaTokoUnit1CapSeratus(): void
    {
        $item = $this->customerItem(self::KT_UNIT1, 1, '07');

        $this->assertSame(230.0, (float) $item['actual']);
        $this->assertSame(220.0, (float) $item['target']);
        $this->assertSame(100.0, (float) $item['achievement'], 'Σ ≥ target penuh → capped 100.');
    }

    public function testKepalaTokoUnit1DiBawahTargetNol(): void
    {
        $item = $this->customerItem(self::KT_UNIT1, 1, '08');

        $this->assertSame(30.0, (float) $item['actual']);
        $this->assertSame(0.0, (float) $item['achievement'], 'Σ teknisi < target → 0.');
    }

    // ── Regresi: teknisi & Kepala Toko unit lain tidak berubah ─────

    public function testTeknisiTetapMemakaiServiceSendiri(): void
    {
        $item = $this->customerItem(self::TEK_A, 1, self::BULAN);

        $this->assertSame(60.0, (float) $item['actual'], 'Teknisi tetap dinilai dari service yang ia tangani sendiri.');
        $this->assertSame(110.0, (float) $item['target'], 'Target teknisi tetap 220 ÷ 2.');
        $this->assertSame(0.0, (float) $item['achievement']);
    }

    public function testKepalaTokoUnitLainTetapMemakaiServiceSendiriDanTargetSeparuh(): void
    {
        $item = $this->customerItem(self::KT_UNIT2, 2, self::BULAN);

        $this->assertSame(30.0, (float) $item['actual'], 'KT unit lain TIDAK memakai akumulasi teknisi (40).');
        $this->assertSame(90.0, (float) $item['target'], 'Target KT unit lain tetap 180 ÷ 2.');
        $this->assertSame(0.0, (float) $item['achievement']);
    }
}
