<?php

namespace Tests;

use App\Services\Payroll\PayrollGenerator;
use CodeIgniter\Test\CIUnitTestCase;

class PayrollGeneratorUnitTest extends CIUnitTestCase
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
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'unit', 'akun', 'jabatan', 'kas_keluar', 'finance_payroll',
            'salary_components', 'salary_structures',
            'incentive_members', 'incentive_groups', 'incentive_rules',
            'kpi_weights', 'kpi_components', 'kpi_targets', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off',
            'penjualan', 'detail_penjualan',
        ] as $t) {
            $q('DROP TABLE IF EXISTS ' . $t);
        }

        // KpiCalculationService::branchUnits() membaca semua unit; OmsetTokoCalculator
        // menjumlah omset per unit, jadi kedua tabel ini wajib ada (boleh kosong).
        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit_idunit INT NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, KABUPATEN_UNIT TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, JENIS_PEGAWAI INT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                penerima TEXT NULL, idunit INT NULL)');

        $q('CREATE TABLE finance_payroll (
                id INT PRIMARY KEY AUTO_INCREMENT, unit_id INT NULL,
                pegawai_id INT NULL, due_date TEXT NULL, paid_date TEXT NULL,
                status TEXT NULL, total REAL NULL, potongan_kasbon REAL NULL,
                total_bersih REAL NULL, notes TEXT NULL, created_by INT NULL,
                sumber TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        $q('CREATE TABLE salary_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE salary_structures (
                id INT PRIMARY KEY AUTO_INCREMENT, position_id INT NULL,
                salary_component_id INT NULL, unit_id INT NULL, context TEXT NULL,
                base_value REAL NULL,
                calculation_type TEXT NULL, multiplier REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');

        $q('CREATE TABLE incentive_groups (id INT PRIMARY KEY, is_active INT NULL)');
        $q('CREATE TABLE incentive_members (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                employee_id INT NULL, is_active INT NULL)');
        $q('CREATE TABLE incentive_rules (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                kpi_component_id INT NULL, base_value REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');

        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE kpi_weights (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                position_id INT NULL, weight REAL NULL, weight_group TEXT NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context TEXT NULL,
                target_value REAL NULL, effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                kpi_component_id INT NULL, evaluator_id INT NULL,
                evaluation_date TEXT NULL, raw_score REAL NULL,
                period_year INT NULL, period_month INT NULL)');
        $q('CREATE TABLE kpi_attendance_detail (id INT PRIMARY KEY AUTO_INCREMENT, evaluation_id INT NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                evaluation_date TEXT NULL, period_month INT NULL,
                period_year INT NULL, evaluator_id INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, KABUPATEN_UNIT) VALUES (3, 'ICLEAR Banyuwangi', 'Banyuwangi')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN) VALUES (41, 'Kepala Toko')");
        // 501 aktif & menerima gaji; 502 nonaktif; 503 tidak menerima gaji.
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
            (501, 3, 41, 'Karyawan Aktif', 'Jalan Test', 1, 1, NULL),
            (502, 3, 41, 'Karyawan Resign', 'Jalan Test', 1, 0, NULL),
            (503, 3, 41, 'Tidak Digaji', 'Jalan Test', 0, 1, NULL)");

        // Struktur gaji sengaja beda dari hardcode legacy (1.500.000).
        $q("INSERT INTO salary_components (id, code, name, type, is_active) VALUES
            (1, 'GAJI_POKOK', 'Gaji Pokok', 'base', 1),
            (2, 'TUNJANGAN_KINERJA', 'Tunjangan Kinerja', 'allowance', 1),
            (3, 'TUNJANGAN_ABSEN', 'Tunjangan Absen', 'allowance', 1)");
        $q("INSERT INTO salary_structures (position_id, salary_component_id, unit_id, base_value, calculation_type, multiplier, effective_from, effective_to) VALUES
            (41, 1, NULL, 2000000, 'fixed', NULL, '2026-01-01', NULL),
            (41, 2, NULL, 850000, 'percent_of_kpi', NULL, '2026-01-01', NULL),
            (41, 3, NULL, 250000, 'percent_of_kpi', NULL, '2026-01-01', NULL)");

        // Lembur kategori 10; penerima = ID_AKUN (angka).
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit) VALUES
            ('2026-09-10', 10, 'Lembur closing', 100000, '501', 3)");
    }

    public function testHitungGajiUnitMemakaiMesinPayrollDanLembur(): void
    {
        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);

        $this->assertCount(1, $hasil, 'hanya karyawan aktif & menerima gaji yang dihitung');
        $this->assertSame(501, $hasil[0]['id_akun']);
        $this->assertSame(2000000.0, $hasil[0]['gaji_pokok'], 'pakai salary_structures, bukan hardcode 1.500.000');
        $this->assertSame(0.0, $hasil[0]['tunjangan_kinerja']);
        $this->assertSame(0.0, $hasil[0]['tunjangan_absen']);
        $this->assertSame(350000.0, $hasil[0]['tunjangan_penempatan'], 'alamat beda kabupaten');
        $this->assertSame(100000.0, $hasil[0]['lembur']);
        $this->assertSame(2450000.0, $hasil[0]['total'], '2.000.000 + 350.000 + 100.000');
    }

    /**
     * Tanpa salary_structures, total tetap masuk akal: penempatan + insentif +
     * lembur (bukan 0, bukan error).
     */
    public function testKaryawanTanpaStrukturGajiTetapDihitung(): void
    {
        $this->db->query("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN) VALUES (99, 'Tanpa Struktur')");
        $this->db->query("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted)
            VALUES (504, 3, 99, 'Tanpa Struktur', 'Jalan Test', 1, 1, NULL)");

        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);
        $baris = null;
        foreach ($hasil as $row) {
            if ($row['id_akun'] === 504) {
                $baris = $row;
            }
        }

        $this->assertNotNull($baris, 'karyawan tanpa struktur tidak boleh hilang');
        $this->assertSame(0.0, $baris['gaji_pokok']);
        $this->assertSame(350000.0, $baris['total'], 'hanya tunjangan penempatan');
    }

    /** Jabatan kosong bikin hitungGaji() melempar; unit ini harus melewatinya, bukan 500. */
    public function testKaryawanTanpaJabatanDilewati(): void
    {
        $this->db->query("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted)
            VALUES (505, 3, NULL, 'Tanpa Jabatan', 'Jalan Test', 1, 1, NULL)");

        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);

        $ids = array_column($hasil, 'id_akun');
        $this->assertNotContains(505, $ids, 'akun tanpa jabatan dilewati');
        $this->assertContains(501, $ids, 'karyawan lain tetap dihitung');
    }

    /** Susun Otomatis harus memperbarui baris rencana, bukan membuat baris ganda. */
    public function testGenerateMenimpaBarisRencanaYangSudahAda(): void
    {
        $this->db->query("INSERT INTO finance_payroll
            (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
            VALUES (1, 3, 501, '2026-09-30', 'rencana', 100, 'nilai lama', 'auto', '2026-09-01', '2026-09-01')");

        $hasil = (new PayrollGenerator())->generate('2026-09', [
            'unit_ids' => [3],
            'overwrite' => true,
        ]);

        $this->assertSame(0, $hasil['created'], 'tidak boleh membuat baris baru');
        $this->assertSame(1, $hasil['updated'], 'baris rencana harus ditimpa');
        $this->assertSame(0, $hasil['skipped']);

        $row = $this->db->table('finance_payroll')->where('id', 1)->get()->getRow();
        $this->assertSame(2450000, (int) $row->total, 'total dihitung ulang');
        $this->assertSame('rencana', $row->status, 'status tidak berubah');
        $this->assertStringContainsString('Gaji Pokok', (string) $row->notes);
        $this->assertSame(1, $this->db->table('finance_payroll')->countAllResults(), 'tetap satu baris');
    }

    /** Payroll yang sudah dibayar tidak boleh ditimpa. */
    public function testGenerateTidakMenimpaBarisDibayar(): void
    {
        $this->db->query("INSERT INTO finance_payroll
            (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
            VALUES (1, 3, 501, '2026-09-30', 'dibayar', 100, 'sudah dibayar', 'auto', '2026-09-01', '2026-09-01')");

        $hasil = (new PayrollGenerator())->generate('2026-09', [
            'unit_ids' => [3],
            'overwrite' => true,
        ]);

        $this->assertSame(0, $hasil['created']);
        $this->assertSame(0, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped'], 'baris dibayar dilewati');

        $row = $this->db->table('finance_payroll')->where('id', 1)->get()->getRow();
        $this->assertSame(100, (int) $row->total, 'nominal yang sudah dibayar tidak berubah');
        $this->assertSame('dibayar', $row->status);
    }

    /** Tanpa opsi overwrite, perilaku lama (lewati baris yang sudah ada) tetap berlaku. */
    public function testGenerateTanpaOverwriteTetapMelewati(): void
    {
        $this->db->query("INSERT INTO finance_payroll
            (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
            VALUES (1, 3, 501, '2026-09-30', 'rencana', 100, 'nilai lama', 'auto', '2026-09-01', '2026-09-01')");

        $hasil = (new PayrollGenerator())->generate('2026-09', ['unit_ids' => [3]]);

        $this->assertSame(0, $hasil['updated']);
        $this->assertSame(1, $hasil['skipped']);

        $row = $this->db->table('finance_payroll')->where('id', 1)->get()->getRow();
        $this->assertSame(100, (int) $row->total, 'nilai lama tetap');
    }

    /** Draft lama akun TERIMA_GAJI=0 dibersihkan; riwayat dibayar tetap. */
    public function testGenerateMenghapusDraftAkunTidakDigaji(): void
    {
        $this->db->query("INSERT INTO finance_payroll
            (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
            VALUES
            (1, 3, 503, '2026-09-30', 'rencana', 100, 'draft lama', 'auto', '2026-09-01', '2026-09-01'),
            (2, 3, 503, '2026-09-30', 'dibayar', 200, 'sudah dibayar', 'auto', '2026-09-01', '2026-09-01')");

        $hasil = (new PayrollGenerator())->generate('2026-09', [
            'unit_ids' => [3],
            'overwrite' => true,
        ]);

        $this->assertSame(1, $hasil['dihapus'], 'hanya draft rencana yang dihapus');
        $this->assertSame(
            0,
            $this->db->table('finance_payroll')->where('id', 1)->countAllResults(),
            'draft rencana akun tidak digaji hilang'
        );

        $row = $this->db->table('finance_payroll')->where('id', 2)->get()->getRow();
        $this->assertNotNull($row, 'riwayat yang sudah dibayar tidak boleh dihapus');
        $this->assertSame('dibayar', $row->status);
        $this->assertSame(200, (int) $row->total);
    }
}
