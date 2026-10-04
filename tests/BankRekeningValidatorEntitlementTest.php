<?php

namespace Tests;

use App\Models\ModelBank;
use App\Services\Finance\BankRekeningValidator;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Validator rekening bank yang dipakai endpoint POST (Kas_Masuk, Kas_Keluar).
 *
 * Yang diuji di sini adalah otorisasi, bukan saldo. Kalau `idbank` diubah
 * di browser, request harus ditolak karena tidak cocok dengan unit -- bukan
 * karena angkanya salah.
 */
class BankRekeningValidatorEntitlementTest extends CIUnitTestCase
{
    use EntitlementFixture;

    private BankRekeningValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginRoot();
        $this->validator = new BankRekeningValidator();
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_KASIR;
    }

    /**
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    private function valid(string $idbank, int $unit, ?int $role = Fase1::ROLE_KASIR): array
    {
        return $this->validator->validateUntukUnit($idbank, $unit, $role, false);
    }

    public function testRekeningCvHanyaUntukUnitSatuDua(): void
    {
        $this->assertTrue($this->valid(Fase1::IDBANK_CV, 1)['ok'], 'CV sah untuk Unit 1');
        $this->assertTrue($this->valid(Fase1::IDBANK_CV, 2)['ok'], 'CV sah untuk Unit 2');
        $this->assertFalse($this->valid(Fase1::IDBANK_CV, 3)['ok'], 'CV bukan hak Unit 3');
        $this->assertFalse($this->valid(Fase1::IDBANK_CV, 4)['ok'], 'CV bukan hak Unit 4');
        $this->assertFalse($this->valid(Fase1::IDBANK_CV, 5)['ok'], 'CV bukan hak Unit 5');
    }

    public function testRekeningAlfarizkiHanyaUntukUnitTiga(): void
    {
        $this->assertTrue($this->valid(Fase1::IDBANK_ALFARIZKI, 3)['ok']);
        $this->assertFalse($this->valid(Fase1::IDBANK_ALFARIZKI, 1)['ok']);
        $this->assertFalse($this->valid(Fase1::IDBANK_ALFARIZKI, 4)['ok']);
        $this->assertFalse($this->valid(Fase1::IDBANK_ALFARIZKI, 5)['ok']);
    }

    public function testRekeningSabrinaHanyaUntukUnitEmpat(): void
    {
        $this->assertTrue($this->valid(Fase1::IDBANK_SABRINA, 4)['ok']);
        $this->assertFalse($this->valid(Fase1::IDBANK_SABRINA, 1)['ok']);
        $this->assertFalse($this->valid(Fase1::IDBANK_SABRINA, 3)['ok']);
    }

    public function testRekeningNonaktifDitolak(): void
    {
        $hasil = $this->valid(Fase1::IDBANK_FARA, 1);

        $this->assertFalse($hasil['ok'], 'FARA berstatus nonaktif tidak boleh dipakai');
        $this->assertNotSame('', $hasil['alasan'], 'Penolakan wajib punya alasan yang terbaca');
    }

    public function testIdbankTakDikenalDanKosongDitolak(): void
    {
        $this->assertFalse($this->valid('999', 1)['ok'], 'idbank di luar master ditolak');
        $this->assertFalse($this->valid('', 1)['ok'], 'idbank kosong ditolak');
        $this->assertFalse($this->valid('0', 1)['ok'], 'idbank "0" diperlakukan sebagai kosong');
    }

    /** Spasi dari input form tidak boleh membuat rekening sah jadi tidak sah. */
    public function testIdbankDenganSpasiTetapDiterima(): void
    {
        $this->assertTrue(
            $this->valid(' ' . Fase1::IDBANK_CV . ' ', 1)['ok'],
            'idbank dibaca sebagai string lalu di-trim, bukan di-cast ke integer'
        );
    }

    public function testRekeningFinanceDigateRole(): void
    {
        $this->assertFalse(
            $this->valid(Fase1::IDBANK_FINANCE, 1, Fase1::ROLE_KASIR)['ok'],
            'Kasir tidak boleh memakai rekening kas Direksi'
        );
        $this->assertTrue($this->valid(Fase1::IDBANK_FINANCE, 1, Fase1::ROLE_FINANCE)['ok']);
        $this->assertTrue($this->valid(Fase1::IDBANK_FINANCE, 1, Fase1::ROLE_ROOT)['ok']);
    }

    /**
     * Session kosong berarti "tidak diketahui", bukan ROOT.
     *
     * Ini dicegah karena `session('ID_JABATAN')` yang null akan jadi 0, dan 0
     * adalah ROOT -- konteks tanpa session akan dapat hak penuh.
     */
    public function testRoleKosongTidakOtomatisJadiRoot(): void
    {
        $hasil = $this->validator->validate('', false, 1, null);

        $this->assertFalse($hasil['ok'], 'Role null harus ditolak, bukan diperlakukan ROOT');
    }

    /** `bolehKosong` dipakai pemanggil yang memang boleh tanpa rekening. */
    public function testRekeningKosongBolehBilaPeneleponMengizinkan(): void
    {
        $hasil = $this->validator->validate('', true, 1, Fase1::ROLE_KASIR);

        $this->assertTrue($hasil['ok']);
        $this->assertNull($hasil['idbank']);
    }

    // =================================================================
    // FAIL-CLOSED DI WRITE PATH
    // =================================================================

    /**
     * Alokasi liar di DB harus berhenti DI BOUNDARY, bukan lolos ke mutasi.
     *
     * `adakahEntitlement()` akan bilang "boleh" karena membaca DB. Boundary
     * write path tidak boleh memakai jawaban itu mentah-mentah.
     */
    public function testAlokasiLiarDitolakDiWritePath(): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_CV . ', ' . Fase1::UNIT_HO . ', 0)'
        );

        $hasil = $this->valid(Fase1::IDBANK_CV, Fase1::UNIT_HO);

        $this->assertFalse($hasil['ok'], 'Unit HO tidak punya alokasi di mapping resmi');
        $this->assertStringContainsString('mapping resmi', $hasil['alasan']);
    }

    /** Rekening Direksi yang punya alokasi unit melanggar M4 -> ditolak. */
    public function testRekeningFinanceDenganAlokasiDitolak(): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES ('
            . Fase1::AKUN_FINANCE . ', 1, 1000)'
        );

        $hasil = $this->valid(Fase1::IDBANK_FINANCE, 1, Fase1::ROLE_FINANCE);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Direksi', $hasil['alasan']);
    }

    /**
     * Constructor harus memakai ModelBank yang DISUNTIKKAN.
     *
     * Parameter yang diabaikan di constructor tidak bisa diam-diam: kalau
     * dipanggil `new BankRekeningValidator($bank)` tapi `$bank` dibuang, test
     * ini yang gagal -- bukan bug yang baru ketahuan saat test sudah hijau semua.
     */
    public function testModelBankYangDisuntikkanDipakai(): void
    {
        $spy = new class () extends ModelBank {
            /** @var list<string> */
            public array $dipanggil = [];

            public function getById($idbank)
            {
                $this->dipanggil[] = (string) $idbank;

                return (object) [
                    'idbank'    => (string) $idbank,
                    'nama_bank' => 'Bank dari Dependency Injection',
                    'norek'     => '999',
                    'atas_nama' => 'Spy',
                ];
            }
        };

        $hasil = (new BankRekeningValidator($spy))->validate(Fase1::IDBANK_CV, false);

        $this->assertTrue($hasil['ok'], 'Rekening canonical harus tetap valid');
        $this->assertSame(
            [Fase1::IDBANK_CV],
            $spy->dipanggil,
            'Validator WAJIB memakai ModelBank yang disuntikkan, bukan membuat sendiri'
        );
        $this->assertSame(
            'Bank dari Dependency Injection',
            $hasil['bank']->nama_bank,
            'Baris bank yang dikembalikan harus berasal dari model yang disuntikkan'
        );
    }

    /** Tanpa injeksi, defaultnya tetap ModelBank biasa dan tidak error. */
    public function testTanpaInjeksiTetapBekerja(): void
    {
        $hasil = $this->valid(Fase1::IDBANK_CV, 1);

        $this->assertTrue($hasil['ok']);
        $this->assertSame('Bank BCA', $hasil['bank']->nama_bank);
        $this->assertNotSame(
            'Bank dari Dependency Injection',
            $hasil['bank']->nama_bank,
            'Tanpa injeksi, model harus benar-benar yang bawaan'
        );
    }
}