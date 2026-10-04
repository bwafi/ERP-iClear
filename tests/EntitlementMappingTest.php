<?php

namespace Tests;

use App\Services\Finance\EntitlementPolicyService;
use App\Models\ModelAkunKasBank;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Mapping resmi rekening per unit SESUDAH M1-M4.
 *
 * Test ini mengunci keputusan mapping yang sudah diterapkan ke `erp_local`
 * (batch 23). Kalau mapping berubah, test ini harus gagal -- bukan ikut
 * menyesuaikan diri. Itu sebabnya fixture memakai state POST-migrasi:
 * menyemai test ini dengan data lama akan membuat test hijau untuk data
 * yang sudah usang.
 */
class EntitlementMappingTest extends CIUnitTestCase
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

    public function testMappingResmiSesuaiKeputusan(): void
    {
        $p = $this->policy;

        $this->assertSame([1, 2], $p->unitResmi(Fase1::AKUN_CV), 'CV resmi untuk Unit 1 & 2');
        $this->assertSame([3], $p->unitResmi(Fase1::AKUN_ALFARIZKI), 'ALFARIZKI resmi untuk Unit 3');
        $this->assertSame([4], $p->unitResmi(Fase1::AKUN_SABRINA), 'SABRINA resmi untuk Unit 4');
        $this->assertSame([], $p->unitResmi(Fase1::AKUN_FINANCE), 'Finance tidak punya unit alokasi');

        // Finance tercatat di policy dengan daftar KOSONG -- bukan dengan unit.
        $this->assertSame(
            [],
            $p->mappingResmi()[Fase1::AKUN_FINANCE] ?? null,
            'Rekening Finance tercatat di policy tanpa unit'
        );
    }

    public function testDatabaseSelarasDenganPolicy(): void
    {
        foreach (Fase1::WAJIB_STATEMENT as $akun) {
            $this->assertSame(
                $this->policy->unitResmi($akun),
                $this->policy->unitEntitledDb($akun),
                'Entitlement DB akun ' . $akun . ' harus sama dengan policy'
            );
            $this->assertTrue(
                $this->policy->selarasDenganPolicy($akun),
                'Rekening ' . $akun . ' harus selaras policy: ' . implode('; ', $this->policy->ringkasanDrift($akun))
            );
            $this->assertSame([], $this->policy->ringkasanDrift($akun));
        }
    }

    public function testFinanceTidakPunyaAlokasiDanExemptStatement(): void
    {
        $this->assertSame([], $this->policy->unitEntitledDb(Fase1::AKUN_FINANCE));
        $this->assertFalse(
            $this->policy->wajibStatementVerifikasi(Fase1::AKUN_FINANCE),
            'Rekening Finance exempt dari guard statement operasional'
        );
        $this->assertTrue($this->policy->adalahRekeningFinance(Fase1::AKUN_FINANCE));
        $this->assertFalse($this->policy->adalahRekeningFinance(Fase1::AKUN_CV));
    }

    public function testHanyaTigaRekeningYangWajibStatement(): void
    {
        foreach (Fase1::WAJIB_STATEMENT as $akun) {
            $this->assertTrue(
                $this->policy->wajibStatementVerifikasi($akun),
                'Rekening ' . $akun . ' wajib statement VERIFIED'
            );
        }

        $this->assertFalse($this->policy->wajibStatementVerifikasi(Fase1::AKUN_FARA), 'FARA nonaktif');
        $this->assertFalse($this->policy->wajibStatementVerifikasi(Fase1::AKUN_KAS_1), 'KAS bukan rekening bank');
    }

    public function testBentukMasterSesuaiMapping(): void
    {
        $model = new ModelAkunKasBank();

        $cv = $model->find(Fase1::AKUN_CV);
        $this->assertSame(1, (int) $cv->is_shared, 'CV = shared');
        $this->assertNull($cv->unit_id, 'CV shared tidak punya unit_id pemilik');
        $this->assertSame(Fase1::IDBANK_CV, (string) $cv->bank_idbank);

        $sabrina = $model->find(Fase1::AKUN_SABRINA);
        $this->assertSame(0, (int) $sabrina->is_shared, 'SABRINA = non-shared');
        $this->assertSame(4, (int) $sabrina->unit_id, 'SABRINA dimiliki Unit 4');
        $this->assertSame(Fase1::IDBANK_SABRINA, (string) $sabrina->bank_idbank);

        $alfa = $model->find(Fase1::AKUN_ALFARIZKI);
        $this->assertSame(0, (int) $alfa->is_shared, 'ALFARIZKI = non-shared');
        $this->assertSame(3, (int) $alfa->unit_id, 'ALFARIZKI dimiliki Unit 3');
        $this->assertSame(Fase1::IDBANK_ALFARIZKI, (string) $alfa->bank_idbank);

        $harapan = $this->policy->masterHarapan(Fase1::AKUN_SABRINA);
        $this->assertSame(Fase1::IDBANK_SABRINA, (string) $harapan['bank_idbank']);
        $this->assertSame(4, (int) $harapan['unit_id']);
        $this->assertFalse((bool) $harapan['is_shared']);
    }

    /** idbank wajib tetap string; kalau di-(int)-cast, format bisa berubah. */
    public function testIdbankTetapString(): void
    {
        foreach ((new ModelAkunKasBank())->findAll() as $a) {
            if ((string) $a->tipe !== 'BANK') {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^\d+$/',
                (string) $a->bank_idbank,
                'idbank rekening ' . $a->nama_akun . ' harus terbaca sebagai string angka'
            );
        }
    }

    public function testUnitGentengTidakPunyaRekeningBank(): void
    {
        foreach ((new ModelAkunKasBank())->findAll() as $a) {
            if ((string) $a->tipe !== 'BANK') {
                continue;
            }

            $this->assertNotSame(
                Fase1::UNIT_GENTENG,
                (int) $a->unit_id,
                'Rekening ' . $a->nama_akun . ' tidak boleh milik Unit 5 -- nomor Genteng belum diverifikasi'
            );
        }

        foreach (Fase1::UNIT_TANPA_REKENING as $unit) {
            $this->assertSame(
                [],
                $this->bankTerlihat([$unit], $unit),
                'Unit ' . $unit . ' tidak boleh melihat rekening bank apapun'
            );
        }
    }

    /**
     * TEMUAN AUDIT: runtime membaca entitlement dari DB, bukan dari policy.
     *
     * `EntitlementPolicyService::adakahEntitlement()` memang dirancang begitu
     * -- database adalah sumber runtime. Konsekuensinya baris alokasi yang
     * ditulis pihak lain LANGSUNG menjadi hak akses, tanpa perlu policy.
     * Yang menahan cuma deteksi drift, dan itu dipanggil audit/migration,
     * bukan tiap request. Test ini mengunci perilaku itu supaya tidak berubah
     * diam-diam; kalau nanti dieraskan jadi policy-first, test ini yang gagal.
     */
    public function testAlokasiLiarDiDbTerlihatSebagaiHakTapiDriftKetahuan(): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_CV . ', ' . Fase1::UNIT_HO . ', 0)'
        );

        $this->assertTrue(
            $this->policy->adakahEntitlement(Fase1::AKUN_CV, Fase1::UNIT_HO),
            'DOCUMENTASI: runtime membaca DB, jadi alokasi liar langsung granting'
        );

        $this->assertFalse(
            $this->policy->selarasDenganPolicy(Fase1::AKUN_CV),
            'Tapi drift tetap harus terdeteksi oleh audit'
        );
        $this->assertNotSame([], $this->policy->ringkasanDrift(Fase1::AKUN_CV));
    }

    // =================================================================
    // FAIL-CLOSED DI SECURITY BOUNDARY
    // =================================================================
    //
    // Test di atas mengunci pemisahan tugas: `adakahEntitlement()` membaca DB
    // dan tidak policies. Itu tetap benar dan tidak diubah.
    //
    // Tapi "DB boleh granting" bukan berarti "boundary boleh meloloskan".
    // Test di bawah mengunci lapis kedua: di titik yang menjalankan mutasi,
    // alokasi yang tidak disahkan policy harus DITOLAK, bukan diterima diam-diam.

    /** Data(DB) dan policy agreeing -> operasi sensitif LOLOS. */
    public function testGuardSensifMeneruskanHakYangSelarasDenganPolicy(): void
    {
        $p = $this->policy;

        foreach (Fase1::UNIT_OPERASIONAL as $unit) {
            $akun = $unit === 3 ? Fase1::AKUN_ALFARIZKI : ($unit === 4 ? Fase1::AKUN_SABRINA : Fase1::AKUN_CV);

            $cek = $p->guardEntitlementSensif($akun, $unit);

            $this->assertTrue($cek['ok'], sprintf('Unit %d memang berhak atas akun %d', $unit, $akun));
            $this->assertSame('', $cek['alasan']);
            $this->assertSame([], $cek['drift'], 'Tidak ada drift kalau data dan policy sama');
        }
    }

    /** Alokasi liar di DB -> `adakahEntitlement()` tetap TRUE, guard DITOLAK. */
    public function testGuardSensifMenolakAlokasiLiar(): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_CV . ', ' . Fase1::UNIT_HO . ', 0)'
        );

        // Runtime source tetap DB: hak itu ADA.
        $this->assertTrue($this->policy->adakahEntitlement(Fase1::AKUN_CV, Fase1::UNIT_HO));

        // Tapi boundary menolak, dan menyuruh Finance memperbaikinya.
        $cek = $this->policy->guardEntitlementSensif(Fase1::AKUN_CV, Fase1::UNIT_HO);

        $this->assertFalse($cek['ok'], 'Alokasi liar tidak boleh dieksekusi di boundary');
        $this->assertStringContainsString('mapping resmi', $cek['alasan']);
        $this->assertNotSame([], $cek['drift'], 'Drift harus dilaporkan bersama penolakan');
    }

    /** Unit tanpa alokasi -> ditolak tanpa perlu membandingkan policy. */
    public function testGuardSensifMenolakUnitYangTidakPunyaAlokasi(): void
    {
        $cek = $this->policy->guardEntitlementSensif(Fase1::AKUN_CV, Fase1::UNIT_GENTENG);

        $this->assertFalse($cek['ok']);
        $this->assertStringContainsString('tidak dialokasikan', $cek['alasan']);
    }

    /**
     * Rekening Finance/HO tidak punya unit asal, jadi entitas yang berwenang
     * adalah ROLE -- bukan baris alokasi.
     *
     * Kalau guard ini ikut menerapkan aturan "wajib ada di alokasi DB",
     * setiap rekening Direksi akan selalu ditolak karena memang tidak pernah
     * punya baris alokasi. Role check-nya ada di BankRekeningValidator;
     * di sini hanya invarian datanya (M4) yang dijaga.
     */
    public function testGuardSensifTreatRekeningFinanceSecaraKhusus(): void
    {
        $this->assertTrue(
            $this->policy->guardEntitlementSensif(Fase1::AKUN_FINANCE, 1)['ok'],
            'Rekening Direksi tidak punya unit asal, jadi tidak boleh diminta punya alokasi'
        );
        $this->assertTrue(
            $this->policy->guardEntitlementSensif(Fase1::AKUN_FINANCE, Fase1::UNIT_GENTENG)['ok'],
            'Untuk rekening Direksi, unit mana pun tidak relevan di lapis entitlement'
        );
    }

    /** Rekening Finance/HO tidak boleh punya alokasi sama sekali (aturan M4). */
    public function testGuardSensifMenolakRekeningFinanceYangPunyaAlokasi(): void
    {
        $this->assertTrue(
            $this->policy->guardEntitlementSensif(Fase1::AKUN_FINANCE, 1)['ok'],
            'Rekening Finance yang bersih tetap boleh lewat'
        );

        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_FINANCE . ', 1, 1000)'
        );

        $cek = $this->policy->guardEntitlementSensif(Fase1::AKUN_FINANCE, 1);

        $this->assertFalse($cek['ok'], 'Alokasi pada rekening Direksi melanggar M4');
        $this->assertStringContainsString('M4', $cek['alasan']);
    }

    /** Konteks unit tidak diketahui -> tolak, jangan tebak. */
    public function testGuardSensifMenolakKonteksUnitTidakValid(): void
    {
        foreach ([null, 0, -1] as $unit) {
            $this->assertFalse(
                $this->policy->guardEntitlementSensif(Fase1::AKUN_CV, $unit)['ok'],
                'Unit ' . var_export($unit, true) . ' tidak boleh dianggap entitlement'
            );
        }
    }
}