<?php

namespace Tests;

use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSetorTarikService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Rekening BERSAMA: saldo fisik != hak unit.
 *
 * Akun CV (Fase1::AKUN_CV) dipakai Unit 1 dan Unit 2. Saldo fisiknya satu,
 * tapi yang boleh기구/mengambil cuma saldo milik masing-masing unit. Kalau
 * satu movements dipakai untuk menaikkan SEMUA unit di rekening itu, maka
 * rekening bersama jadi ruang txn lintas unit tersembunyi — dan guard
 * "Tarik milik 1 unit, tidak boleh lintas unit" kehilangan artinya.
 *
 * Test ini mengunci INVARIAN, bukan symptom:
 *   - setiap Setor hanya menambah posisi unit yang menyetor;
 *   - saldo fisik bertambah sesuai movement, tidak dikali jumlah unit;
 *   - menarik melebihi posisi unit sendiri DITOLAK walau saldo fisik cukup.
 */
class SharedAccountSeparationTest extends CIUnitTestCase
{
    use EntitlementFixture;

    private KasBankSetorTarikService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->buatSchemaOpeningKas();
        $this->seedEntitlementCanonical();
        // CV masuk daftar rekening wajib statement, jadi harus disahkan
        // Finance lebih dulu — sama seperti di produksi.
        $this->seedStatementCanonical('VERIFIED');
        $this->loginRoot();
        $this->service = new KasBankSetorTarikService();
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_KASIR;
    }

    /**
     * Opening KAS hidup di tabel sendiri dan WAJIB berstatus TERVERIFIKASI
     * pada tanggal cut-off. Tanpa itu, Setor dari laci selalu ditolak —
     * memang itu aturan yang benar, tapi test perlu tabelnya supaya bisa
     * menguji aturan yang lain (pemisahan posisi unit).
     */
    private function buatSchemaOpeningKas(): void
    {
        $this->koneksi()->query('DROP TABLE IF EXISTS db_opening_kas');
        $this->koneksi()->query('CREATE TABLE db_opening_kas (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
            opening BIGINT NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'BELUM_VERIFIKASI\',
            keterangan TEXT NULL, input_by INT NULL, verifikasi_by INT NULL,
            verifikasi_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
    }

    /** Opening laci terverifikasi di tanggal cut-off. */
    private function seedOpeningKas(int $akunKas, int $unit, int $nominal): void
    {
        $cutoff = \App\Services\Finance\FinanceScopeService::kasBankCutoffDate();
        $this->koneksi()->query(
            'INSERT INTO db_opening_kas (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih, status, keterangan) VALUES ('
            . $akunKas . ', ' . $unit . ', \'' . $cutoff . '\', ' . $nominal . ', ' . $nominal . ', 0, '
            . '\'TERVERIFIKASI\', \'opening laci test\')'
        );
    }

    /** Hari pertama periode KasBank — tanggal semua mutasi di test ini. */
    private static function tanggalPeriode(): string
    {
        return \App\Services\Finance\FinanceScopeService::kasBankPeriodeMulaiDate();
    }

    private function laci(int $akunKas, int $unit, int $nominal = 10_000_000): void
    {
        // Opening KAS = baseline laci; statement bank tetap diperlukan untuk
        // rekening tujuannya.
        $this->seedOpeningKas($akunKas, $unit, $nominal);
        $this->seedSaldoKas($akunKas, $nominal);
    }

    private function setor(int $unit, int $kas, int $bank, int $nominal, string $key): array
    {
        return $this->service->setorTunai($unit, $kas, $bank, $nominal, self::tanggalPeriode(), $key);
    }

    private function posisi(int $akun, int $unit): int
    {
        return (new KasBankCutoffService())->posisiUnit($akun, $unit, self::tanggalPeriode());
    }

    private function fisik(int $akun): int
    {
        return (new KasBankCutoffService())->saldoFisik($akun, self::tanggalPeriode());
    }

    /**
     * (A) Setor Unit 1 ke rekening bersama hanya menaikkan posisi Unit 1.
     */
    public function testSetorUnit1HanyaMenaikkanPosisiUnit1(): void
    {
        $this->laci(Fase1::AKUN_KAS_1, 1);

        $hasil = $this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 4_000_000, 'shr-a');
        $this->assertTrue($hasil['ok'], 'Setor harus berhasil: ' . $hasil['alasan']);

        $this->assertSame(
            4_000_000,
            $this->posisi(Fase1::AKUN_CV, 1),
            'Setor Unit 1 harus menjadi posisi Unit 1 di rekening bersama'
        );
        $this->assertSame(
            0,
            $this->posisi(Fase1::AKUN_CV, 2),
            'Setor Unit 1 TIDAK boleh menambah posisi Unit 2'
        );
    }

    /**
     * (B) Setor Unit 2 ke rekening yang sama hanya menaikkan posisi Unit 2.
     */
    public function testSetorUnit2HanyaMenaikkanPosisiUnit2(): void
    {
        $this->laci(6, 2); // laci Unit 2

        $hasil = $this->setor(2, 6, Fase1::AKUN_CV, 3_000_000, 'shr-b');
        $this->assertTrue($hasil['ok'], 'Setor harus berhasil: ' . $hasil['alasan']);

        $this->assertSame(
            3_000_000,
            $this->posisi(Fase1::AKUN_CV, 2),
            'Setor Unit 2 harus menjadi posisi Unit 2 di rekening bersama'
        );
        $this->assertSame(
            0,
            $this->posisi(Fase1::AKUN_CV, 1),
            'Setor Unit 2 TIDAK boleh menambah posisi Unit 1'
        );
    }

    /**
     * Dua unit menyetor ke rekening yang sama: posisi terpisah, dan saldo
     * fisik = jumlah KEDUA setoran (bukan salah satu, bukan dikali dua).
     */
    public function testSetorDuaUnitTidakSalingMencampurDanFisikIkutJumlah(): void
    {
        $this->laci(Fase1::AKUN_KAS_1, 1);
        $this->laci(6, 2);

        $this->assertTrue($this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 4_000_000, 'shr-c1')['ok']);
        $this->assertTrue($this->setor(2, 6, Fase1::AKUN_CV, 3_000_000, 'shr-c2')['ok']);

        $this->assertSame(4_000_000, $this->posisi(Fase1::AKUN_CV, 1));
        $this->assertSame(3_000_000, $this->posisi(Fase1::AKUN_CV, 2));
        $this->assertSame(
            7_000_000,
            $this->fisik(Fase1::AKUN_CV),
            'Saldo fisik rekening bersama = total setoran, bukan posisi per unit'
        );
    }

    /**
     * (D) Unit 2 tidak boleh menarik saldo Unit 1.
     *
     * Ini guard yang membuat Pindah Saldo tidak bisa jadi jalur bypass
     * Setor/Tarik: `canUseAsSource()` hanya membuktikan AKSES, sedangkan
     * `cekTarikUnit()` yang membuktikan POSISI.
     */
    public function testUnitTidakBolehTarikSaldoUnitLainDiRekeningBersama(): void
    {
        $this->laci(Fase1::AKUN_KAS_1, 1);
        $this->assertTrue($this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 4_000_000, 'shr-d1')['ok']);

        // Saldo fisik 4 juta, posisi Unit 2 = 0.
        $this->assertSame(4_000_000, $this->fisik(Fase1::AKUN_CV));

        $cek = (new KasBankCutoffService())->cekTarikUnit(Fase1::AKUN_CV, 2, 1_000_000);
        $this->assertFalse(
            $cek['ok'],
            'Unit 2 tidak boleh menarik saldo Unit 1 meski saldo fisik rekening cukup'
        );
        $this->assertStringContainsStringIgnoringCase('posisi', $cek['alasan']);

        // Unit 1 sendiri boleh menarik saldonya sendiri.
        $cekSendiri = (new KasBankCutoffService())->cekTarikUnit(Fase1::AKUN_CV, 1, 4_000_000);
        $this->assertTrue($cekSendiri['ok'], 'Unit 1 boleh menarik saldonya sendiri: ' . $cekSendiri['alasan']);
    }

    /**
     * Gerbang yang sama, diuji lewat jalur TARIK sungguhan supaya guard
     * tidak bisa "lolos" hanya karena dipanggil di tempat lain.
     */
    public function testTarikMelebihiPosisiUnitDitolak(): void
    {
        $this->laci(Fase1::AKUN_KAS_1, 1);
        $this->laci(6, 2);
        $this->assertTrue($this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 4_000_000, 'shr-e1')['ok']);

        $hasil = $this->service->tarikTunai(2, 6, Fase1::AKUN_CV, 1_000_000, self::tanggalPeriode(), 'shr-e2');

        $this->assertFalse($hasil['ok'], 'Tarik melebihi posisi unit harus ditolak');
        $this->assertStringContainsStringIgnoringCase('posisi', $hasil['alasan']);
    }

    /**
     * Subtype `sumber_tipe` adalah diskriminator resmi. Tanpa ini, Setor dan
     * Tarik tidak bisa dibedakan dari Pindah Saldo walau `jenis`-nya sama.
     */
    public function testSetorMencatatSubtypeSendiri(): void
    {
        $this->laci(Fase1::AKUN_KAS_1, 1);
        $this->assertTrue($this->setor(1, Fase1::AKUN_KAS_1, Fase1::AKUN_CV, 1_000_000, 'shr-f')['ok']);

        $baris = $this->koneksi()->table('transaksi_kas_bank')
            ->select('arah, sumber_tipe')
            ->orderBy('arah', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $baris);
        $this->assertSame(KasBankSetorTarikService::SUMBER_TIPE_SETOR, $baris[0]['sumber_tipe']);
        $this->assertSame(KasBankSetorTarikService::SUMBER_TIPE_SETOR, $baris[1]['sumber_tipe']);
    }
}
