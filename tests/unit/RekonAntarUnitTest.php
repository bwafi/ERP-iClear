<?php

namespace Tests\unit;

use App\Services\Finance\RekonDailyCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pelunasan antar unit tidak disalin ke `kas_keluar` (lihat PRD Modul 05
 * BR 19), tetapi leg KELUAR-nya adalah uang yang benar-benar keluar dari unit
 * pembayar. Rekonsiliasi Harian harus memasukkannya ke ERP Kas Keluar.
 *
 * Yang diuji: `erpValues()` per hari, `erpValuesRange()` (panel daftar), leg
 * atribusi (rekening fisik sama) tidak dihitung, leg MASUK tidak dihitung,
 * unit lain tidak tercampur, dan Setor/Tarik tetap tidak ikut.
 *
 * @internal
 */
final class RekonAntarUnitTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const UNIT_LAIN = 2;
    private const D2 = '2026-02-10'; // kas_keluar + antar unit fisik + atribusi + setor
    private const D3 = '2026-02-11'; // antar unit fisik
    private const D4 = '2026-02-12'; // hanya atribusi
    private const D5 = '2026-02-13'; // hanya leg MASUK

    private const TABEL = ['penjualan', 'kas_keluar', 'service_dp', 'service', 'transaksi_kas_bank'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';
    private RekonDailyCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->hapusTabel();
        $this->buatTabel();
        $this->seed();

        $this->calc = new RekonDailyCalculator();
    }

    protected function tearDown(): void
    {
        $this->hapusTabel();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusTabel(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
    }

    private function buatTabel(): void
    {
        // Tabel yang dibaca komponen Cash/Transfer Masuk (TutupKasirSourceDefinition).
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL,
            status_service INT DEFAULT 1,
            bayar_tunai BIGINT DEFAULT 0,
            harus_dibayar BIGINT DEFAULT 0,
            tanggal_selesai DATE NULL)');

        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL,
            refund_idkas_keluar INT NULL,
            jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            metode VARCHAR(15) NULL,
            tanggal DATE NULL)');

        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL,
            kode_invoice VARCHAR(64) NULL,
            bayar_tunai BIGINT DEFAULT 0,
            bayar_bank BIGINT DEFAULT 0,
            tanggal DATE NULL)');

        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            idunit INT NULL,
            idbank VARCHAR(20) NULL,
            jumlah BIGINT DEFAULT 0,
            tanggal DATE NULL)');

        $this->db->query('CREATE TABLE transaksi_kas_bank (
            idtransaksi INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            unit_id INT NOT NULL,
            akun_kas_bank_id INT NULL,
            jenis VARCHAR(40) NOT NULL,
            arah VARCHAR(10) NULL,
            jumlah BIGINT NOT NULL,
            akun_tujuan_id INT NULL,
            transfer_ref VARCHAR(45) NULL,
            sumber_tipe VARCHAR(40) NULL,
            sumber_id INT NULL,
            keterangan TEXT NULL)');
    }

    private function seed(): void
    {
        $this->db->table('kas_keluar')->insert([
            'idunit' => self::UNIT, 'idbank' => null, 'jumlah' => 2000, 'tanggal' => self::D2,
        ]);

        $ledger = static function (array $row): array {
            return array_merge([
                'tanggal'          => self::D2,
                'unit_id'          => self::UNIT,
                'akun_kas_bank_id' => 10,
                'jenis'            => 'PEMBAYARAN_ANTAR_UNIT',
                'arah'             => 'KELUAR',
                'jumlah'           => 0,
                'transfer_ref'     => null,
                'sumber_tipe'      => null,
                'sumber_id'        => null,
                'keterangan'       => null,
            ], $row);
        };

        $this->db->table('transaksi_kas_bank')->insertBatch([
            // Uang benar-benar keluar dari rekening unit pembayar.
            $ledger(['jumlah' => 50000, 'transfer_ref' => 'AU-1']),
            // Mutasi hak pada rekening fisik sama: tidak ada uang keluar.
            $ledger(['jumlah' => 70000, 'transfer_ref' => 'AU-2', 'sumber_tipe' => 'antar_unit_atribusi']),
            // Setor/Tarik tetap bukan kas keluar rekon.
            $ledger(['jenis' => 'TRANSFER_INTERNAL', 'jumlah' => 30000, 'transfer_ref' => 'TI-1']),
            // Unit lain tidak boleh tercampur.
            $ledger(['unit_id' => self::UNIT_LAIN, 'jumlah' => 90000, 'transfer_ref' => 'AU-3']),
            // Hari lain, untuk uji rentang.
            $ledger(['tanggal' => self::D3, 'jumlah' => 15000, 'transfer_ref' => 'AU-4']),
            // Leg MASUK tidak dihitung sebagai kas keluar.
            $ledger(['tanggal' => self::D5, 'arah' => 'MASUK', 'jumlah' => 15000, 'transfer_ref' => 'AU-5']),
            // Hanya atribusi: tetap nol.
            $ledger(['tanggal' => self::D4, 'jumlah' => 70000, 'sumber_tipe' => 'antar_unit_atribusi']),
        ]);
    }

    public function testErpKasKeluarMemuatLegKeluarAntarUnitFisik(): void
    {
        $d2 = $this->calc->erpValues(self::UNIT, self::D2);

        $this->assertSame(52000, $d2['kas_keluar'], '2.000 kas_keluar + 50.000 antar unit fisik.');
    }

    public function testLegAtribusiMasukDanSetorTarikTidakDihitung(): void
    {
        $this->assertSame(0, $this->calc->erpValues(self::UNIT, self::D4)['kas_keluar'], 'Leg atribusi bukan uang keluar.');
        $this->assertSame(0, $this->calc->erpValues(self::UNIT, self::D5)['kas_keluar'], 'Leg MASUK bukan kas keluar.');
        $this->assertSame(52000, $this->calc->erpValues(self::UNIT, self::D2)['kas_keluar'], 'Setor/Tarik tidak menambah kas keluar.');
    }

    public function testUnitLainTetapTerpisah(): void
    {
        $this->assertSame(90000, $this->calc->erpValues(self::UNIT_LAIN, self::D2)['kas_keluar']);
    }

    public function testErpValuesRangeSamaDenganPerHari(): void
    {
        $range = $this->calc->erpValuesRange(self::UNIT, self::D2, self::D5);

        foreach ([self::D2, self::D3] as $d) {
            $this->assertSame(
                $this->calc->erpValues(self::UNIT, $d),
                $range[$d] ?? [],
                "Rentang harus identik dengan per-hari pada {$d}."
            );
        }

        $this->assertSame(52000, $range[self::D2]['kas_keluar']);
        $this->assertSame(15000, $range[self::D3]['kas_keluar']);
    }
}
