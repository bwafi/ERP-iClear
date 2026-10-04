<?php

namespace Tests;

use App\Models\ModelAkunKasBank;
use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * `nominal = 0` pada alokasi_saldo_kas_bank berarti "unit punya HAK memakai
 * rekening, tapi belum ada saldo teralokasikan" -- BUKAN "tanpa hak".
 *
 * Ini distinción yang mudah salah dan mahal kalau salah: kalau dihitung
 * `nominal > 0`, semua rekening unit baru akan hilang dari dropdown dan
 * unit akan terkunci diam-diam.
 */
class ScopeNominalZeroTest extends CIUnitTestCase
{
    use EntitlementFixture;

    private EntitlementPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginRoot();
        $this->policy = new EntitlementPolicyService();
    }

    public function testSeluruhAlokasiPakaiNominalNol(): void
    {
        $alokasi = $this->koneksi()->table('alokasi_saldo_kas_bank')->get()->getResultArray();

        $this->assertCount(4, $alokasi, 'Empat entitlement hasil M1-M3');

        foreach ($alokasi as $a) {
            $this->assertEquals(
                0,
                (int) $a['nominal'],
                'Fixture harus memakai nominal 0 supaya test menguji "hak tanpa saldo"'
            );
        }
    }

    /** Hanya baris alokasi yang ADA yang berarti hak, berapa pun nominalnya. */
    public function testNominalNolTetapMemberiHak(): void
    {
        foreach ([Fase1::AKUN_CV, Fase1::AKUN_ALFARIZKI, Fase1::AKUN_SABRINA] as $akun) {
            $units = $this->policy->unitEntitledDb($akun);
            $this->assertNotSame([], $units, 'Akun ' . $akun . ' punya entitlement');

            foreach ($units as $unit) {
                $this->assertTrue(
                    $this->policy->adakahEntitlement($akun, $unit),
                    'Nominal 0 tetap berarti hak: akun ' . $akun . ' unit ' . $unit
                );
            }
        }
    }

    public function testTidakAdaAlokasiTetapTidakBeriHak(): void
    {
        $this->assertFalse(
            $this->policy->adakahEntitlement(Fase1::AKUN_FINANCE, 1),
            'Rekening Finance tidak punya alokasi unit'
        );
        $this->assertFalse(
            $this->policy->adakahEntitlement(Fase1::AKUN_CV, Fase1::UNIT_GENTENG),
            'Unit 5 tidak punya baris alokasi di rekening CV'
        );
        $this->assertFalse(
            $this->policy->adakahEntitlement(Fase1::AKUN_CV, null),
            'Unit null tidak pernah punya hak'
        );
        $this->assertFalse($this->policy->adakahEntitlement(Fase1::AKUN_CV, 0));
    }

    /** Entitlement tidak boleh ikut hilang bersama saldo. */
    public function testRekeningTetapTerlihatMeskipunSaldoAwalKosong(): void
    {
        $this->assertSame(
            0,
            $this->koneksi()->table('saldo_awal_kas_bank')->countAllResults(),
            'Tidak ada statement: saldo real memang belum diisi Finance'
        );

        foreach (Fase1::UNIT_OPERASIONAL as $unit) {
            $terlihat = $this->bankTerlihat([$unit], $unit);

            $this->assertNotSame(
                [],
                $terlihat,
                'Unit ' . $unit . ' tetap harus melihat rekeningnya walau saldo kosong'
            );
        }

        $this->assertContains(Fase1::AKUN_CV, $this->bankTerlihat([1], 1));
        $this->assertContains(Fase1::AKUN_ALFARIZKI, $this->bankTerlihat([3], 3));
        $this->assertContains(Fase1::AKUN_SABRINA, $this->bankTerlihat([4], 4));
    }

    /** Golongan non-shared tetap butuh baris alokasi juga. */
    public function testRekeningMilikUnitJugaPunyaBarisAlokasi(): void
    {
        foreach ([Fase1::AKUN_ALFARIZKI, Fase1::AKUN_SABRINA] as $akun) {
            $this->assertNotSame(
                [],
                $this->policy->unitEntitledDb($akun),
                'Rekening milik unit harus punya baris alokasi eksplisit, bukan mengandalkan unit_id saja'
            );
        }

        $model = new ModelAkunKasBank();
        $this->assertSame(0, (int) $model->find(Fase1::AKUN_SABRINA)->is_shared);
    }
}