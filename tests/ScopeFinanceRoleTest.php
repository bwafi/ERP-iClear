<?php

namespace Tests;

use App\Services\Finance\KasBankScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Rekening kas Direksi (is_finance_ho = 1) HANYA boleh dipakai role
 * Finance/Direksi sebagai sumber dana, dan sebagai tujuan HANYA oleh unit
 * yang memang punya rekening operasional.
 *
 * Yang paling mudah rusak di sini adalah assumption "is_finance_ho = 1 =
 * terbuka untuk semua unit". Test ini mengunci sebaliknya.
 */
class ScopeFinanceRoleTest extends CIUnitTestCase
{
    use EntitlementFixture;

    private KasBankScopeService $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginRoot();
        $this->scope = new KasBankScopeService();
    }

    private function finance()
    {
        return (new \App\Models\ModelAkunKasBank())->find(Fase1::AKUN_FINANCE);
    }

    public function testFinanceSebagaiSumberHanyaUntukRoleFinance(): void
    {
        $finance = $this->finance();

        $this->assertTrue($this->scope->canUseAsSource($finance, 1, Fase1::ROLE_ROOT), 'ROOT boleh menarik dana Direksi');
        $this->assertTrue($this->scope->canUseAsSource($finance, 1, Fase1::ROLE_FINANCE));
        $this->assertFalse(
            $this->scope->canUseAsSource($finance, 1, Fase1::ROLE_KASIR),
            'Kasir tidak boleh menarik dana dari kas Direksi'
        );
    }

    /**
     * Unit TIDAK boleh diabaikan di `canUseAsSource()` untuk Finance/HO.
     *
     * Rekening Direksi memang tidak punya unit asal (unit_id NULL), tapi
     * unit_id pada form tetap menentukan unit mana yang menanggung mutasi.
     * Kalau hanya role yang dicek, `unit_id` acak -- null, 0, atau 99 --
     * akan lolos dan menghasilkan mutasi dari unit yang tidak pernah
     * diverifikasi. Aturannya sekarang simetris dengan `canUseAsDestination()`:
     * role finance WAJIB, unit valid WAJIB, dan unit itu harus dalam jangkauan user.
     */
    public function testFinanceSumberMenolakUnitNolKosongAtauDiLuarJangkauan(): void
    {
        $finance = $this->finance();

        // Role benar, unit asal-usul -> tidak boleh lolos hanya karena role.
        $this->assertFalse(
            $this->scope->canUseAsSource($finance, null, Fase1::ROLE_ROOT),
            'Unit null tidak boleh dipakai menarik dana Direksi'
        );
        $this->assertFalse(
            $this->scope->canUseAsSource($finance, 0, Fase1::ROLE_ROOT),
            'Unit 0 bukan unit valid'
        );
        $this->assertFalse(
            $this->scope->canUseAsSource($finance, 99, Fase1::ROLE_ROOT),
            'Unit 99 di luar jangkauan harus ditolak meski role Finance'
        );

        // User scope yang sebenarnya (unit 1) tetap boleh.
        $this->assertTrue(
            $this->scope->canUseAsSource($finance, 1, Fase1::ROLE_ROOT),
            'Unit yang memang dipegang user tetap boleh menarik dana Direksi'
        );
    }

    /** Finance Boleh jadi tujuan di unit yang punya rekening operasional. */
    public function testFinanceSebagaiTujuanHanyaDiUnitYangPunyaRekening(): void
    {
        $finance = $this->finance();

        $this->assertTrue($this->scope->canUseAsDestination($finance, 1, Fase1::ROLE_KASIR));
        $this->assertTrue($this->scope->canUseAsDestination($finance, 4, Fase1::ROLE_KASIR));

        $this->assertFalse(
            $this->scope->canUseAsDestination($finance, Fase1::UNIT_GENTENG, Fase1::ROLE_KASIR),
            'Unit 5 tidak punya rekening operasional, jadi tidak dapat kas Direksi'
        );
        $this->assertFalse(
            $this->scope->canUseAsDestination($finance, Fase1::UNIT_HO, Fase1::ROLE_KASIR),
            'Unit 50 bukan pemilik kas Direksi'
        );
    }

    public function testFinanceTampakPadaUnitOperasionalUmum(): void
    {
        foreach (Fase1::UNIT_OPERASIONAL as $unit) {
            $this->assertContains(
                Fase1::AKUN_FINANCE,
                $this->bankTerlihat([$unit], $unit),
                'Unit ' . $unit . ' boleh melihat rekening Finance'
            );
        }

        foreach (Fase1::UNIT_TANPA_REKENING as $unit) {
            $this->assertNotContains(
                Fase1::AKUN_FINANCE,
                $this->bankTerlihat([$unit], $unit),
                'Unit ' . $unit . ' tidak boleh melihat rekening Finance'
            );
        }
    }

    /**
     * Regression: role Kasir/Manager boleh LUAR ke kas Direksi pada unit 1-4.
     *
     * Ini perilaku yang sudah dipakai form transfer, jadi validator scope
     * tidak boleh mengetatkannya tanpa keputusan bisnis baru.
     */
    public function testKasirTetapBolehMasukKeRekeningFinance(): void
    {
        $this->assertSame([Fase1::AKUN_FINANCE, Fase1::AKUN_CV], $this->bankTujuanUntuk(1, Fase1::ROLE_KASIR));
        $this->assertSame([Fase1::AKUN_ALFARIZKI, Fase1::AKUN_FINANCE], $this->bankTujuanUntuk(3, Fase1::ROLE_KASIR));
        $this->assertSame([Fase1::AKUN_FINANCE, Fase1::AKUN_SABRINA], $this->bankTujuanUntuk(4, Fase1::ROLE_KASIR));
    }

    public function testRekeningNonaktifTidakPernahJadiTujuan(): void
    {
        foreach (Fase1::UNIT_OPERASIONAL as $unit) {
            $this->assertNotContains(
                Fase1::AKUN_FARA,
                $this->bankTujuanUntuk($unit, Fase1::ROLE_KASIR),
                'Rekening nonaktif tidak boleh muncul sebagai tujuan di unit ' . $unit
            );
        }
    }

    public function testUnitTanpaRekeningPunyaTujuanKosong(): void
    {
        foreach (Fase1::UNIT_TANPA_REKENING as $unit) {
            $this->assertSame(
                [],
                $this->bankTujuanUntuk($unit, Fase1::ROLE_KASIR),
                'Unit ' . $unit . ' tidak punya rekening tujuan'
            );
        }
    }
}