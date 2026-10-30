<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KesehatanUangCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * KPI Kesehatan Uang per Cabang — sisi DATABASE.
 *
 * Keputusan bisnis 2026-10-30: pembilang memakai SALDO kas + bank per akhir
 * bulan, bukan uang diterima. Penyebut tetap biaya bulan itu.
 *
 * Fokus pengujian (bagian yang tidak bisa diuji murni):
 *  - periode biaya = BULAN PENUH (1 s/d akhir bulan), tidak dibatasi cut-off;
 *  - kategori `HPP`, `SDM`, `BEBAN CABANG`, `TEMPO`, dan `KASBON` pada
 *    `kas_keluar` dikeluarkan dari komponen "Kas Keluar operasional";
 *  - HPP penjualan TIDAK dipakai sebagai komponen biaya (keputusan bisnis
 *    2026-10-30); method `hpp()` dipertahankan untuk verifikasi memo/query;
 *  - payroll diambil dari register per unit.
 *
 * Formula saldo/rasio diuji tanpa database di
 * `tests/unit/KesehatanUangCalculatorTest.php`.
 *
 * @internal
 */
final class KesehatanUangCalculatorTest extends CIUnitTestCase
{
    protected $db;

    private int $bulan;
    private int $tahun;
    private string $dari;
    private string $sampai;
    private string $tglData;
    private string $tglAwalBulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Bulan evaluasi mengikuti konvensi KPI Finance, tetapi rentangnya
        // BULAN PENUH (tanggal 1 s/d akhir bulan).
        $mulai        = FinanceScopeService::periodeMulaiDate();
        $this->bulan  = (int) date('n', strtotime($mulai));
        $this->tahun  = (int) date('Y', strtotime($mulai));
        $this->dari   = date('Y-m-01', strtotime($mulai));
        $this->sampai = date('Y-m-t', strtotime($mulai));

        $this->tglAwalBulan = $this->dari;
        $this->tglData      = date('Y-m-d', strtotime($this->dari . ' +2 days'));

        $this->schema();
        $this->seedDasar();
    }

    protected function tearDown(): void
    {
        // Bersihkan supaya tabel uji tidak tertinggal dan menabrak test lain
        // yang meng-CREATE tanpa DROP terlebih dahulu.
        foreach ($this->tabel() as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        parent::tearDown();
    }

    /** @return array<int,string> */
    private function tabel(): array
    {
        return [
            'db_kas_keluar', 'db_kategori_kas',
            'db_detail_penjualan', 'db_penjualan', 'db_service', 'db_finance_payroll',
        ];
    }

    private function q(string $sql): void
    {
        $this->db->query($sql);
    }

    private function schema(): void
    {
        foreach ($this->tabel() as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        $this->q('CREATE TABLE db_kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                idunit INT NULL, idbank TEXT NULL, kategori_idkategori INT NULL)');
        $this->q('CREATE TABLE db_kategori_kas (
                idkategori_kas INTEGER PRIMARY KEY, kategori TEXT NULL)');
        $this->q('CREATE TABLE db_penjualan (
                idpenjualan INTEGER PRIMARY KEY, unit_idunit INT NULL, tanggal TEXT NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, kode_invoice TEXT NULL)');
        $this->q('CREATE TABLE db_detail_penjualan (
                iddetail_penjualan INTEGER PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, unit_idunit INT NULL,
                jumlah INT NULL, sub_total REAL NULL, hpp_penjualan REAL NULL)');
        $this->q('CREATE TABLE db_service (
                idservice INTEGER PRIMARY KEY, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
                status_service INT NULL, bayar_tunai REAL NULL, harus_dibayar REAL NULL)');
        $this->q('CREATE TABLE db_finance_payroll (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, unit_id INT NULL,
                due_date TEXT NULL, total REAL NULL)');
    }

    private function seedDasar(): void
    {
        $this->q("INSERT INTO db_kategori_kas (idkategori_kas, kategori) VALUES
            (1,'OPERASIONAL'), (7,'HPP'), (10,'SDM'),
            (11,'BEBAN CABANG'), (12,'TEMPO'), (19,'Kasbon')");
    }

    private function calc(): KesehatanUangCalculator
    {
        return new KesehatanUangCalculator();
    }

    // ── Biaya = bulan penuh (tidak dibatasi cut-off) ───────────────

    public function testHppDanPayrollMenghitungTanggalSatuBulan(): void
    {
        // Tanggal 1 bulan berjalan (sebelum periodeMulaiDate/cut-off) tetap
        // harus ikut karena periode biaya memakai bulan penuh.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (1, 1, '{$this->tglAwalBulan}', 0, 0, 'SLL-1')");
        $this->q("INSERT INTO db_detail_penjualan (penjualan_idpenjualan, unit_idunit, jumlah, sub_total, hpp_penjualan)
                  VALUES (1, 1, 2, 10000, 1500000)");
        $this->q("INSERT INTO db_finance_payroll (unit_id, due_date, total)
                  VALUES (1, '{$this->tglAwalBulan}', 2500000)");

        $calc = $this->calc();

        $this->assertSame(3000000, $calc->hpp(1, $this->dari, $this->sampai));
        $this->assertSame(2500000, $calc->payroll(1, $this->dari, $this->sampai));
    }

    public function testBulanSebelumLedgerKasBankTidakMengarangSaldo(): void
    {
        // Saldo baru sah sejak ledger Kas & Bank dimulai. Bulan sebelumnya
        // tidak boleh memakai statement lama sebagai saldo bulan itu.
        $tgl   = date('Y-m-d', strtotime(FinanceScopeService::kasBankPeriodeMulaiDate() . ' -3 months'));
        $bulan = (int) date('n', strtotime($tgl));
        $tahun = (int) date('Y', strtotime($tgl));

        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori)
                  VALUES ('{$tgl}', 'Listrik', 1000000, 1, NULL, 1)");

        $hasil = $this->calc()->calculate(1, $bulan, $tahun);

        $this->assertSame('saldo_belum_tersedia', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertFalse($hasil['detail']['saldo_tersedia']);
    }

    // ── Pencegahan perhitungan ganda ───────────────────────────────

    public function testKasKeluarKategoriYangDikecualikan(): void
    {
        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori) VALUES
            ('{$this->tglData}', 'Beli stok',    999000000, 1, NULL, 7),
            ('{$this->tglData}', 'Gaji & komisi', 888000000, 1, NULL, 10),
            ('{$this->tglData}', 'Beban cabang',  70000000, 1, NULL, 11),
            ('{$this->tglData}', 'Tempo supplier',66000000, 1, NULL, 12),
            ('{$this->tglData}', 'Kasbon pegawai',  400000, 1, NULL, 19),
            ('{$this->tglData}', 'Listrik',         5000000, 1, NULL, 1),
            ('{$this->tglData}', 'ATK unit lain',  77000000, 2, NULL, 1)");

        $calc = $this->calc();

        // Hanya kategori di luar HPP/SDM/BEBAN CABANG/TEMPO/KASBON unit 1.
        $this->assertSame(5000000, $calc->kasKeluarOperasional(1, $this->dari, $this->sampai));

        $dikecualikan = $calc->kasKeluarDikecualikan(1, $this->dari, $this->sampai);
        $this->assertSame(999000000, $dikecualikan['hpp']);
        $this->assertSame(888000000, $dikecualikan['sdm']);
        $this->assertSame(70000000, $dikecualikan['beban_cabang']);
        $this->assertSame(66000000, $dikecualikan['tempo']);
        $this->assertSame(400000, $dikecualikan['kasbon']);
    }

    public function testPerhitunganTidakMenghitungHppDuaKali(): void
    {
        // HPP dari penjualan = 20jt. Di kas_keluar juga ada kategori HPP 999jt;
        // yang terakhir HARUS dikecualikan dari komponen biaya.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (2, 1, '{$this->tglData}', 0, 0, 'SLL-2')");
        $this->q("INSERT INTO db_detail_penjualan (penjualan_idpenjualan, unit_idunit, jumlah, sub_total, hpp_penjualan)
                  VALUES (2, 1, 2000, 50000000, 10000)");
        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori)
                  VALUES ('{$this->tglData}', 'Beli stok', 999000000, 1, NULL, 7)");

        $calc = $this->calc();

        $this->assertSame(20000000, $calc->hpp(1, $this->dari, $this->sampai));
        $this->assertSame(0, $calc->kasKeluarOperasional(1, $this->dari, $this->sampai));
    }

    public function testHppPakaiJumlahDanTidakMencampurUnitLain(): void
    {
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice) VALUES
            (10, 1, '{$this->tglData}', 0, 0, 'SLL-10'),
            (11, 2, '{$this->tglData}', 0, 0, 'SLL-11')");
        $this->q("INSERT INTO db_detail_penjualan (penjualan_idpenjualan, unit_idunit, jumlah, sub_total, hpp_penjualan) VALUES
            (10, 1, 3,  30000, 1000),
            (11, 2, 10, 500000, 5000)");

        // Unit 1: 1.000 x 3 = 3.000. Unit 2 tidak boleh tercampur.
        $this->assertSame(3000, $this->calc()->hpp(1, $this->dari, $this->sampai));
        $this->assertSame(50000, $this->calc()->hpp(2, $this->dari, $this->sampai));
    }

    public function testPayrollDiambilDariRegisterPerUnit(): void
    {
        $this->q("INSERT INTO db_finance_payroll (unit_id, due_date, total) VALUES
            (1, '{$this->sampai}', 4500000),
            (1, '{$this->sampai}', 500000),
            (2, '{$this->sampai}', 12000000)");

        $this->assertSame(5000000, $this->calc()->payroll(1, $this->dari, $this->sampai));
        $this->assertSame(12000000, $this->calc()->payroll(2, $this->dari, $this->sampai));
    }
}
