<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KesehatanUangCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * KPI Kesehatan Uang per Cabang — sisi DATABASE.
 *
 * Keputusan bisnis 2026-10-15: rasio memakai ARUS KAS bulan itu
 * (uang masuk / uang keluar), BUKAN lagi saldo rekening.
 *
 * Fokus pengujian (bagian yang tidak bisa diuji murni):
 *  - periode = BULAN PENUH (1 s/d akhir bulan), tidak dibatasi cut-off;
 *  - uang masuk = penjualan + service (tunai + transfer), definisi sama
 *    dengan KPI Cash Flow / Tutup Kasir;
 *  - pencegahan perhitungan ganda: kategori `HPP` dan `SDM` pada `kas_keluar`
 *    dikeluarkan dari komponen "Kas Keluar operasional";
 *  - HPP dihitung dari harga pokok penjualan (`hpp_penjualan x jumlah`) dan
 *    tidak mencampur unit lain;
 *  - uang keluar nol tidak pernah menghasilkan skor otomatis.
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
            (1,'OPERASIONAL'), (7,'HPP'), (10,'SDM')");
    }

    private function calc(): KesehatanUangCalculator
    {
        return new KesehatanUangCalculator();
    }

    // ── Uang masuk: penjualan + service (tunai + transfer) ─────────

    public function testUangMasukMenjumlahkanPenjualanTunaiTransferDanService(): void
    {
        // Penjualan normal: tunai 5jt, transfer 3jt.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (1, 1, '{$this->tglData}', 5000000, 3000000, 'SLL-1')");
        // Penjualan ber-kode 'srv' harus dikecualikan.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (2, 1, '{$this->tglData}', 9000000, 9000000, 'srv-1')");
        // Service selesai (status 4): tunai 2jt, residual transfer (4jt-2jt).
        $this->q("INSERT INTO db_service (idservice, unit_idunit, tanggal_selesai, status_service, bayar_tunai, harus_dibayar)
                  VALUES (1, 1, '{$this->tglData}', 4, 2000000, 4000000)");
        // Service belum selesai (status 2) harus dikecualikan.
        $this->q("INSERT INTO db_service (idservice, unit_idunit, tanggal_selesai, status_service, bayar_tunai, harus_dibayar)
                  VALUES (2, 1, '{$this->tglData}', 2, 7000000, 7000000)");
        // Service unit lain tidak boleh ikut.
        $this->q("INSERT INTO db_service (idservice, unit_idunit, tanggal_selesai, status_service, bayar_tunai, harus_dibayar)
                  VALUES (3, 2, '{$this->tglData}', 4, 0, 8000000)");

        $masuk = $this->calc()->uangMasuk(1, $this->dari, $this->sampai);

        // Tunai: 5jt (penjualan) + 2jt (service) = 7jt.
        $this->assertSame(7000000, $masuk['tunai']);
        // Transfer: 3jt (penjualan) + 2jt (service) = 5jt.
        $this->assertSame(5000000, $masuk['transfer']);
        $this->assertSame(12000000, $masuk['total']);
    }

    // ── Formula penuh dari data DB (tepat 120%) ────────────────────

    public function testPerhitunganPenuhTepatSeratusDuaPuluhPersen(): void
    {
        // Masuk 60jt (tunai 20jt + transfer 40jt).
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (1, 1, '{$this->tglData}', 20000000, 40000000, 'SLL-1')");
        // HPP 20jt (10.000 x 2.000 unit), operasional 20jt, payroll 10jt = 50jt.
        $this->q("INSERT INTO db_detail_penjualan (penjualan_idpenjualan, unit_idunit, jumlah, sub_total, hpp_penjualan)
                  VALUES (1, 1, 2000, 50000000, 10000)");
        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori)
                  VALUES ('{$this->tglData}', 'Listrik', 20000000, 1, NULL, 1)");
        $this->q("INSERT INTO db_finance_payroll (unit_id, due_date, total)
                  VALUES (1, '{$this->sampai}', 10000000)");

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(60000000, $hasil['detail']['total_masuk']);
        $this->assertSame(20000000, $hasil['detail']['hpp']);
        $this->assertSame(20000000, $hasil['detail']['kas_keluar']);
        $this->assertSame(10000000, $hasil['detail']['payroll']);
        $this->assertSame(50000000, $hasil['detail']['total_keluar']);
        $this->assertSame(120.0, $hasil['detail']['rasio']);
        $this->assertSame(100.0, $hasil['score']);
    }

    // ── Periode bulan penuh (tidak dibatasi cut-off) ───────────────

    public function testPeriodeBulanPenuhTermasukTanggalSebelumCutoff(): void
    {
        // Tanggal 1 bulan berjalan (sebelum periodeMulaiDate/cut-off) tetap
        // harus ikut karena KPI ini memakai bulan penuh.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (1, 1, '{$this->tglAwalBulan}', 1000000, 0, 'SLL-1')");

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame($this->dari, $hasil['detail']['periode_mulai']);
        $this->assertSame($this->sampai, $hasil['detail']['periode_akhir']);
        $this->assertSame(1000000, $hasil['detail']['total_masuk']);
    }

    // ── Pencegahan perhitungan ganda ───────────────────────────────

    public function testKasKeluarKategoriHppDanSdmDikecualikan(): void
    {
        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori) VALUES
            ('{$this->tglData}', 'Beli stok',   999000000, 1, NULL, 7),
            ('{$this->tglData}', 'Gaji & komisi',888000000, 1, NULL, 10),
            ('{$this->tglData}', 'Listrik',         5000000, 1, NULL, 1),
            ('{$this->tglData}', 'ATK unit lain',  77000000, 2, NULL, 1)");

        $calc = $this->calc();

        // Hanya kategori non-HPP/non-SDM milik unit 1 yang dihitung.
        $this->assertSame(5000000, $calc->kasKeluarOperasional(1, $this->dari, $this->sampai));

        $dikecualikan = $calc->kasKeluarDikecualikan(1, $this->dari, $this->sampai);
        $this->assertSame(999000000, $dikecualikan['hpp']);
        $this->assertSame(888000000, $dikecualikan['sdm']);
    }

    public function testPerhitunganTidakMenghitungHppDuaKali(): void
    {
        // HPP dari penjualan = 20jt. Di kas_keluar juga ada kategori HPP 999jt;
        // yang terakhir HARUS dikecualikan dari uang keluar.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (2, 1, '{$this->tglData}', 0, 0, 'SLL-2')");
        $this->q("INSERT INTO db_detail_penjualan (penjualan_idpenjualan, unit_idunit, jumlah, sub_total, hpp_penjualan)
                  VALUES (2, 1, 2000, 50000000, 10000)");
        $this->q("INSERT INTO db_kas_keluar (tanggal, deskripsi, jumlah, idunit, idbank, kategori_idkategori)
                  VALUES ('{$this->tglData}', 'Beli stok', 999000000, 1, NULL, 7)");

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(20000000, $hasil['detail']['hpp']);
        $this->assertSame(0, $hasil['detail']['kas_keluar']);
        $this->assertSame(20000000, $hasil['detail']['total_keluar']);
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

    // ── Uang keluar nol ────────────────────────────────────────────

    public function testUangKeluarNolTidakMenghasilkanSkorOtomatis(): void
    {
        // Ada uang masuk 10jt tetapi tidak ada HPP/operasional/payroll.
        $this->q("INSERT INTO db_penjualan (idpenjualan, unit_idunit, tanggal, bayar_tunai, bayar_bank, kode_invoice)
                  VALUES (1, 1, '{$this->tglData}', 10000000, 0, 'SLL-1')");

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('kebutuhan_nol', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['rasio']);
        $this->assertSame(0, $hasil['detail']['total_keluar']);
    }
}
