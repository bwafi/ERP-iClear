<?php

namespace Tests;

use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSetorTarikService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Guard statement pada mutasi Setor/Tarik.
 *
 * Tanpa guard ini, saldo rekening operasional dibaca dari histori transaksi
 * legacy yang sudah terbukti salah. Setelah M1-M4, saldo itu harus datang
 * dari statement VERIFIED -- kalau tidak, guard justru mengunci angka
 * yang salah.
 */
class SetorTarikStatementGuardTest extends CIUnitTestCase
{
    use EntitlementFixture;

    private KasBankSetorTarikService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginRoot();
        $this->service = new KasBankSetorTarikService();
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_KASIR;
    }

    private function setor(int $unit, int $kas, int $bank, int $nominal, string $key): array
    {
        return $this->service->setorTunai($unit, $kas, $bank, $nominal, '2026-10-03', $key);
    }

    private function tarik(int $unit, int $kas, int $bank, int $nominal, string $key): array
    {
        return $this->service->tarikTunai($unit, $kas, $bank, $nominal, '2026-10-03', $key);
    }

    private function jumlahTransaksi(): int
    {
        return (int) $this->koneksi()->table('transaksi_kas_bank')->countAllResults();
    }

    public function testSetorDitolakSaatStatementBelumDiverifikasi(): void
    {
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);
        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'stmt-1');

        $this->assertFalse($hasil['ok'], 'Rekening wajib statement tidak boleh dipakai tanpa statement VERIFIED');
        $this->assertStringContainsStringIgnoringCase('statement', $hasil['alasan']);
        $this->assertSame(0, $this->jumlahTransaksi(), 'Transaksi yang ditolak tidak boleh meninggalkan baris');
    }

    public function testTarikDitolakSaatStatementBelumDiverifikasi(): void
    {
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);
        $hasil = $this->tarik(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'stmt-2');

        $this->assertFalse($hasil['ok']);
        $this->assertSame(0, $this->jumlahTransaksi());
    }

    public function testSetorBerjalanSetelahStatementDiverifikasi(): void
    {
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'stmt-3');

        $this->assertTrue($hasil['ok'], 'Guard harus لكنه: ' . $hasil['alasan']);
    }

    /**
     * Setor adalah mutasi dua leg: KAS KELUAR + BANK MASUK dalam satu
     * transfer_ref. Kalau hanya satu leg yang tertulis, saldo KAS dan saldo
     * bank akan berbeda discontinue.
     */
    public function testSetorMencatatDuaLegDalamSatuTransferRef(): void
    {
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'stmt-4');

        $legs = $this->koneksi()->table('transaksi_kas_bank')
            ->orderBy('idtransaksi', 'ASC')->get()->getResultArray();

        $this->assertCount(2, $legs, 'Harus ada dua leg');
        $this->assertSame((string) Fase1::AKUN_KAS_1, $legs[0]['akun_kas_bank_id']);
        $this->assertSame('KELUAR', $legs[0]['arah'], 'KAS keluar');
        $this->assertSame((string) Fase1::AKUN_CV, $legs[1]['akun_kas_bank_id']);
        $this->assertSame('MASUK', $legs[1]['arah'], 'BANK masuk');
        $this->assertSame($legs[0]['transfer_ref'], $legs[1]['transfer_ref']);
        $this->assertSame($hasil['transfer_ref'], $legs[0]['transfer_ref']);
    }

    /**
     * Anti double-submit bersifat idempoten, bukan error.
     *
     * Klik kedua dianggap "sudah berhasil" supaya UI tidak menampilkan
     * kegagalan setelah mutasi sebenarnya sudah tercatat.
     */
    public function testSubmissionKeyGandaTidakMenggandakanTransaksi(): void
    {
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $pertama = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'ganda-1');
        $kedua  = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'ganda-1');

        $this->assertSame('inserted', $pertama['status']);
        $this->assertSame('skipped', $kedua['status']);
        $this->assertTrue($kedua['ok'], 'Kirim ulang dianggap berhasil, bukan gagal');
        $this->assertSame($pertama['transfer_ref'], $kedua['transfer_ref']);
        $this->assertSame(2, $this->jumlahTransaksi(), 'Harus tetap dua leg');

        $this->assertSame(
            9900000,
            (new KasBankCutoffService())->saldoFisik(Fase1::AKUN_KAS_1),
            'Saldo KAS hanya berkurang sekali'
        );
    }

    /** Statement BELUM_VERIFIKASI tetap tidak cukup, walau barisnya ada. */
    public function testStatementBelumDiverifikasiTidakCukup(): void
    {
        $this->seedStatementCanonical('BELUM_VERIFIKASI');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $this->assertFalse($this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'stmt-5')['ok']);
        $this->assertSame(0, $this->jumlahTransaksi());
    }

    /** Rekening Finance exempt statement, tapi WAJIB role Finance/Direksi. */
    public function testRekeningFinanceExemptStatementTapiRoleGated(): void
    {
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $hasilKasir = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_FINANCE, 100000, 'fin-1');
        $this->assertFalse($hasilKasir['ok'], 'Kasir tidak boleh.');
        $this->assertStringContainsStringIgnoringCase('role', $hasilKasir['alasan']);
        $this->assertStringNotContainsStringIgnoringCase('statement', $hasilKasir['alasan']);

        $_SESSION['ID_JABATAN'] = Fase1::ROLE_FINANCE;
        $hasilFinance = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_FINANCE, 100000, 'fin-2');

        $this->assertTrue(
            $hasilFinance['ok'],
            'Rekening Finance tidak punya statement, jadi role yang gated: ' . $hasilFinance['alasan']
        );
    }

    /** Rekening unit lain tidak bisa dipakai, walau statement-nya VERIFIED. */
    public function testRekeningUnitLainDitolak(): void
    {
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_FINANCE;

        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_SABRINA, 100000, 'x-1');

        $this->assertFalse($hasil['ok'], 'SABRINA milik Unit 4 tidak boleh dipakai Unit 1');
        $this->assertSame(0, $this->jumlahTransaksi());
    }

    public function testPenolakanMemuatAlasanYangTerbaca(): void
    {
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);
        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'alasan-1');

        $this->assertNotSame('', trim((string) $hasil['alasan']), 'Penolakan wajib dijelaskan ke user');
        $this->assertSame('failed', $hasil['status']);
    }

    // =================================================================
    // USER SCOPE DI DALAM SERVICE
    // =================================================================
    //
    // Semua panggilan di bawah LANGSUNG ke service: tidak lewat controller,
    // tidak lewat form, tidak lewat route. Kalau user-scope hanya dijaga di
    // controller, pemanggil lain (CLI, cron, integrasi) bisa memaksa unit_id
    // milik orang lain dan test-test ini akan lolos.

    /** Kontrol positif: kasir Unit 1 menyetor ke laci & rekening unit 1. */
    public function testSetorKeUnitSendiriTetapBoleh(): void
    {
        $this->loginUnit(1, Fase1::ROLE_KASIR);
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(Fase1::AKUN_KAS_1);

        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 100000, 'sc-ok');

        $this->assertTrue($hasil['ok'], 'Kasir unit 1 harus bisa menyetor ke rekening CV: ' . $hasil['alasan']);
        $this->assertSame(2, $this->jumlahTransaksi(), 'Setor itu dua leg: KAS keluar, BANK masuk');
    }

    /** Kasir Unit 1 tidak boleh memaksa mutasi pada laci Unit 2. */
    public function testSetorKeUnitLainDitolakMastersService(): void
    {
        $this->loginUnit(1, Fase1::ROLE_KASIR);
        $this->seedStatementCanonical('VERIFIED');

        // KAS 6 milik Unit 2, jadi gate "rekening kas harus milik unit" LOLOS
        // dan guard yang diuji benar-benar yang menolak.
        $hasil = $this->service->setorTunai(2, 6, Fase1::AKUN_CV, 100000, '2026-10-03', 'sc-2');

        $this->assertFalse($hasil['ok'], 'Unit di luar jangkauan tidak boleh bisa diakses dari service');
        $this->assertStringContainsStringIgnoringCase('jangkauan', $hasil['alasan']);
        $this->assertSame(0, $this->jumlahTransaksi(), 'Penolakan tidak boleh meninggalkan baris');
    }

    /** Penarikan punya aturan yang sama: unit asing tetap ditolak. */
    public function testTarikKeUnitLainDitolakMastersService(): void
    {
        $this->loginUnit(1, Fase1::ROLE_KASIR);
        $this->seedStatementCanonical('VERIFIED');

        $hasil = $this->service->tarikTunai(2, 6, Fase1::AKUN_CV, 100000, '2026-10-03', 'sc-3');

        $this->assertFalse($hasil['ok'], 'Penarikan ke unit asing harus ditolak');
        $this->assertStringContainsStringIgnoringCase('jangkauan', $hasil['alasan']);
        $this->assertSame(0, $this->jumlahTransaksi());
    }

    /** ROOT memang lintas unit, jadi scope yang luas itu bukan kebocoran. */
    public function testRootTetahBisaCrossUnit(): void
    {
        $this->loginRoot();
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_ROOT;
        $this->seedStatementCanonical('VERIFIED');
        $this->seedSaldoKas(6);

        $hasil = $this->service->setorTunai(2, 6, Fase1::AKUN_CV, 100000, '2026-10-03', 'sc-4');

        $this->assertTrue($hasil['ok'], 'ROOT boleh lintas unit: ' . $hasil['alasan']);
        $this->assertSame(2, $this->jumlahTransaksi());
    }
}