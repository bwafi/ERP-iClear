<?php

namespace Tests;

use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Visibilitas rekening per unit, termasuk mode konsolidasi.
 *
 * Mode konsolidasi/unit "Semua" hanya boleh menjumlahkan unit yang ADA dalam
 * user scope. Kalau union diambil dari semua unit di database, user Unit 1
 * akan melihat rekening Unit 3 lewat tampilan konsolidasi.
 */
class ScopeConsolidasiTest extends CIUnitTestCase
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

    /** Tiap unit hanya melihat rekening yang benar-benar menjadi haknya. */
    public function testVisibilitasPerUnit(): void
    {
        $this->assertSame([Fase1::AKUN_FINANCE, Fase1::AKUN_CV], $this->bankTerlihat([1], 1), 'Unit 1');
        $this->assertSame([Fase1::AKUN_FINANCE, Fase1::AKUN_CV], $this->bankTerlihat([2], 2), 'Unit 2');
        $this->assertSame([Fase1::AKUN_ALFARIZKI, Fase1::AKUN_FINANCE], $this->bankTerlihat([3], 3), 'Unit 3');
        $this->assertSame([Fase1::AKUN_FINANCE, Fase1::AKUN_SABRINA], $this->bankTerlihat([4], 4), 'Unit 4');
        $this->assertSame([], $this->bankTerlihat([5], 5), 'Unit 5');
        $this->assertSame([], $this->bankTerlihat([50], 50), 'Unit 50');
    }

    public function testKonsolidasiAdalahUnionTanpaDuplikat(): void
    {
        $hasil = $this->bankTerlihat(Fase1::UNIT_OPERASIONAL);

        $this->assertSame(
            [Fase1::AKUN_FINANCE, Fase1::AKUN_ALFARIZKI, Fase1::AKUN_SABRINA, Fase1::AKUN_CV],
            $hasil
        );
        $this->assertSame(
            count($hasil),
            count(array_unique($hasil)),
            'Rekening shared hanya boleh muncul sekali di konsolidasi'
        );
    }

    /** Unit tanpa rekening tidak boleh menambah apa pun ke konsolidasi. */
    public function testKonsolidasiDenganUnitTanpaRekening(): void
    {
        $semua = array_merge(Fase1::UNIT_OPERASIONAL, Fase1::UNIT_TANPA_REKENING);

        $this->assertSame(
            $this->bankTerlihat(Fase1::UNIT_OPERASIONAL),
            $this->bankTerlihat($semua),
            'Menambah unit 5 dan 50 tidak boleh mengubah hasil konsolidasi'
        );
    }

    /** Konsolidasi harus dibatasi user scope, bukan seluruh database. */
    public function testKonsolidasiUserUnitTidakBocorKeUnitLain(): void
    {
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginUnit(1);

        $this->assertSame([1], $this->scope->userUnitIds(), 'Kasir Unit 1 hanya punya satu unit');

        $terlihat = $this->bankTerlihat($this->scope->userUnitIds());
        $this->assertSame(
            [Fase1::AKUN_FINANCE, Fase1::AKUN_CV],
            $terlihat,
            'Konsolidasi user Unit 1 tidak boleh memunculkan rekening Unit 3/4'
        );
    }

    public function testUserBolehUnitMembatasiUnitLain(): void
    {
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginUnit(1);
        $scope = new KasBankScopeService();

        $this->assertTrue($scope->userBolehUnit(1));
        $this->assertFalse($scope->userBolehUnit(3), 'Unit di luar cakupan harus ditolak');
        $this->assertFalse($scope->userBolehUnit(4));
        $this->assertFalse($scope->userBolehUnit(50));
        $this->assertFalse($scope->userBolehUnit(99));
        $this->assertFalse($scope->userBolehUnit(0));
        $this->assertFalse($scope->userBolehUnit(-1));
    }

    /** ROOT adalah lintas unit, jadi memang melihat semua unit. */
    public function testRootMelihatSeluruhUnit(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 50], $this->scope->userUnitIds());
    }

    /** Rekening KAS ikut terlihat sebagai kas unit, bukan dianggap rekening bank. */
    public function testKasUnitTerlihatTapiBukanRekeningTujuanValid(): void
    {
        $terlihat = $this->bankTerlihat([1], 1);

        $this->assertNotContains(Fase1::AKUN_KAS_1, $terlihat, 'Helper hanya menghitung tipe BANK');
        $this->assertContains(Fase1::AKUN_CV, $this->bankTujuanUntuk(1));
    }

    /** Perubahan alokasi langsung tercermin, tanpa cache basi. */
    public function testPerubahanEntitlementLangsungTerlihat(): void
    {
        $before = $this->bankTerlihat([3], 3);

        $this->koneksi()->query(
            'UPDATE db_akun_kas_bank SET status = \'nonaktif\' WHERE idakun_kas_bank = ' . Fase1::AKUN_ALFARIZKI
        );

        $this->assertContains(Fase1::AKUN_ALFARIZKI, $before);
        $this->assertNotContains(
            Fase1::AKUN_ALFARIZKI,
            $this->bankTerlihat([3], 3),
            'Rekening yang dinonaktifkan harus hilang dari dropdown'
        );
    }
}