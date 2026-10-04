<?php

namespace Tests;

use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * M1-M4 harus fail-closed DAN idempoten.
 *
 * Test ini menyemai state SEBELUM migration, menjalankan tiap migration satu
 * kali, lalu snapshot, lalu menjalankan semuanya lagi dan membandingkan.
 * Kalau disemai dengan state akhir, migration kedua akan berjalan di atas data
 * yang sudah benar dan test ini tidak menguji apa pun.
 *
 * CATATAN ISOLASI: test ini menjalankan DML sungguhan, jadi HANYA aman di
 * group `tests`. Jangan pernah jalankan terhadap erp_local.
 */
class MigrationIdempotencyTest extends CIUnitTestCase
{
    use EntitlementFixture;

    /**
     * composer.json memakai exclude-from-classmap untuk folder Migrations,
     * jadi kelas migration tidak bisa di-autoload dan wajib require manual.
     */
    private const MIGRASI = [
        '2026-10-03-000501_KoreksiEntitlementCv'      => 'KoreksiEntitlementCv',
        '2026-10-03-000502_KoreksiEntitlementSabrina' => 'KoreksiEntitlementSabrina',
        '2026-10-03-000503_BuatEntitlementAlfarizki'  => 'BuatEntitlementAlfarizki',
        '2026-10-03-000504_KunciEntitlementFinance'   => 'KunciEntitlementFinance',
    ];

    private EntitlementPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedEntitlementPraMigrasi();
        $this->policy = new EntitlementPolicyService();

        foreach (self::MIGRASI as $file => $class) {
            $path = APPPATH . 'Database/Migrations/' . $file . '.php';
            if (! is_file($path)) {
                $this->fail('Migration tidak ditemukan: ' . $path);
            }
            if (! class_exists('App\\Database\\Migrations\\' . $class, false)) {
                require_once $path;
            }
        }
    }

    private function jalankanSemua(): void
    {
        foreach (self::MIGRASI as $class) {
            $fqcn = 'App\\Database\\Migrations\\' . $class;
            (new $fqcn())->up();
        }
    }

    /**
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>}
     */
    private function snapshot(): array
    {
        $alokasi = $this->koneksi()->table('alokasi_saldo_kas_bank')
            ->orderBy('akun_kas_bank_id', 'ASC')
            ->orderBy('unit_id', 'ASC')
            ->get()->getResultArray();

        $akun = $this->koneksi()->table('akun_kas_bank')
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()->getResultArray();

        return [$alokasi, $akun];
    }

    /** Keadaan awal yang jadi alasan M1-M4 dibuat. */
    public function testStatePrasMigrasiSesuaiTemuanAudit(): void
    {
        $this->assertSame([1, 2, 4, 50], $this->policy->unitEntitledDb(Fase1::AKUN_CV), 'CV salah punya 4 unit');
        $this->assertSame([1, 3, 4], $this->policy->unitEntitledDb(Fase1::AKUN_SABRINA), 'SABRINA salah punya 3 unit');
        $this->assertSame([], $this->policy->unitEntitledDb(Fase1::AKUN_ALFARIZKI), 'ALFARIZKI tanpa alokasi');

        $sabrina = (new \App\Models\ModelAkunKasBank())->find(Fase1::AKUN_SABRINA);
        $this->assertSame(1, (int) $sabrina->is_shared, 'SABRINA masih shared');
        $this->assertNull($sabrina->unit_id, 'SABRINA belum punya unit_id');
    }

    public function testSemuaMigrationMenujuMappingResmi(): void
    {
        $this->jalankanSemua();

        $this->assertSame([1, 2], $this->policy->unitEntitledDb(Fase1::AKUN_CV), 'M1: CV hanya Unit 1 & 2');
        $this->assertSame([4], $this->policy->unitEntitledDb(Fase1::AKUN_SABRINA), 'M2: SABRINA hanya Unit 4');
        $this->assertSame([3], $this->policy->unitEntitledDb(Fase1::AKUN_ALFARIZKI), 'M3: ALFARIZKI untuk Unit 3');
        $this->assertSame([], $this->policy->unitEntitledDb(Fase1::AKUN_FINANCE), 'M4: Finance tanpa alokasi');
    }

    public function testMigrationMengubahBentukMasterSabrina(): void
    {
        $this->jalankanSemua();

        $sabrina = (new \App\Models\ModelAkunKasBank())->find(Fase1::AKUN_SABRINA);

        $this->assertSame(4, (int) $sabrina->unit_id, 'SABRINA jadi milik Unit 4');
        $this->assertSame(0, (int) $sabrina->is_shared, 'SABRINA berhenti jadi shared');
        $this->assertSame(Fase1::IDBANK_SABRINA, (string) $sabrina->bank_idbank, 'idbank tidak boleh diubah');
    }

    /** Post-condition yang sama divalidasi migration itu sendiri: data == policy. */
    public function testSemuaRekeningSelarasPolicy(): void
    {
        $this->jalankanSemua();

        foreach ([Fase1::AKUN_CV, Fase1::AKUN_ALFARIZKI, Fase1::AKUN_SABRINA] as $akun) {
            $this->assertTrue(
                $this->policy->selarasDenganPolicy($akun),
                'Akun ' . $akun . ': ' . implode('; ', $this->policy->ringkasanDrift($akun))
            );
        }
    }

    /** Yang paling penting: dua kali jalan = hasil identik, tanpa duplikat. */
    public function testDijalankanDuaKaliHasilnyaIdentik(): void
    {
        $this->jalankanSemua();
        $setelahSatu = $this->snapshot();

        $this->jalankanSemua();
        $setelahDua = $this->snapshot();

        $this->assertSame($setelahSatu, $setelahDua, 'Migration kedua wajib no-op total');
        $this->assertCount(4, $setelahDua[0], 'Alokasi akhir tetap 4 baris, tidak bertambah');
    }

    public function testTidakAdaBarisAlokasiGanda(): void
    {
        $this->jalankanSemua();
        $this->jalankanSemua();

        $duplikat = $this->koneksi()->table('alokasi_saldo_kas_bank')
            ->select('akun_kas_bank_id, unit_id, COUNT(*) AS c')
            ->groupBy('akun_kas_bank_id', 'unit_id')
            ->having('c', '>', 1)
            ->get()
            ->getResultArray();

        $this->assertSame([], $duplikat, 'Tidak boleh ada pasangan akun+unit ganda');
    }

    /** M4 fail-loud: kalau Finance punya alokasi, migration harus berhenti. */
    public function testM4MenolakEntitlementFinanceYangAda(): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_FINANCE . ', ' . Fase1::UNIT_HO . ', 0)'
        );

        $this->expectException(\RuntimeException::class);

        (new \App\Database\Migrations\KunciEntitlementFinance())->up();
    }

    /** M1 fail-loud: kalau akun CV menunjuk idbank lain, migration berhenti. */
    public function testM1MenolakIdbankYangTidakSesuai(): void
    {
        $this->koneksi()->query(
            'UPDATE db_akun_kas_bank SET bank_idbank = ' . "'9'" . ' WHERE idakun_kas_bank = ' . Fase1::AKUN_CV
        );

        $this->expectException(\RuntimeException::class);

        (new \App\Database\Migrations\KoreksiEntitlementCv())->up();
    }

    /** Migration tidak boleh mengarang saldo. */
    public function testMigrationTidakMengarangSaldo(): void
    {
        $this->jalankanSemua();

        $this->assertSame(
            0,
            (int) $this->koneksi()->table('saldo_awal_kas_bank')->countAllResults(),
            'Migration tidak boleh mengisi saldo_awal_kas_bank'
        );
        $this->assertSame(
            0,
            (int) $this->koneksi()->table('transaksi_kas_bank')->countAllResults(),
            'Migration tidak boleh membuat opening ledger transaction'
        );
    }
}