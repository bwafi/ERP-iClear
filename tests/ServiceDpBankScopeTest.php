<?php

namespace Tests;

use App\Models\ModelBank;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Entitlement\EntitlementFixture;

/**
 * Dropdown "Rekening Tujuan (DP)" pada transaksi Service hanya boleh
 * menampilkan rekening milik unit yang sedang login.
 *
 * Aturan yang dikunci:
 *   - rekening privat  -> hanya unit pemiliknya
 *   - rekening shared  -> hanya unit yang punya baris alokasi
 *   - rekening Finance/HO (kas Direksi) & rekening unit lain TIDAK muncul
 *   - unit tanpa rekening operasional -> daftar kosong, bukan "semua rekening"
 *
 * Sumber scope tetap ModelAkunKasBank::getDalamScopeUnit(); ModelBank hanya
 * menjembatani hasilnya ke daftar baris `bank` yang dipakai view.
 */
class ServiceDpBankScopeTest extends CIUnitTestCase
{
    use EntitlementFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->loginRoot();
    }

    /**
     * @return list<string> idbank yang boleh dipakai unit, terurut.
     */
    private function idbankUntukUnit(int $unit): array
    {
        $rows = (new ModelBank())->getBankDalamScopeUnit($unit);
        $ids  = array_map(static fn ($bank) => (string) $bank->idbank, $rows);
        sort($ids);

        return $ids;
    }

    public function testUnitMelihatRekeningSendiriDanRekeningBersamaYangDialokasikan(): void
    {
        // Unit 1 & 2 memakai bersama rekening CV (idbank 2).
        $this->assertSame(['2'], $this->idbankUntukUnit(1));
        $this->assertSame(['2'], $this->idbankUntukUnit(2));

        // Unit 3 -> ALFARIZKI (5), Unit 4 -> SABRINA (1).
        $this->assertSame(['5'], $this->idbankUntukUnit(3));
        $this->assertSame(['1'], $this->idbankUntukUnit(4));
    }

    public function testRekeningUnitLainTidakIkutTampil(): void
    {
        // Unit 4 (Pandaan) TIDAK boleh melihat rekening Unit 3 maupun bank lain.
        $this->assertNotContains('5', $this->idbankUntukUnit(4));
        $this->assertNotContains('2', $this->idbankUntukUnit(4));

        // Unit 3 TIDAK boleh melihat rekening Unit 1/2.
        $this->assertNotContains('2', $this->idbankUntukUnit(3));
        $this->assertNotContains('1', $this->idbankUntukUnit(3));
    }

    public function testRekeningFinanceTidakDitampilkan(): void
    {
        foreach ([1, 2, 3, 4] as $unit) {
            $this->assertNotContains(
                '3',
                $this->idbankUntukUnit($unit),
                'Rekening Finance/HO (idbank 3) tidak boleh jadi rekening tujuan DP unit ' . $unit
            );
        }
    }

    public function testUnitTanpaRekeningOperasionalMendapatDaftarKosong(): void
    {
        // Genteng (5) & Head Office (50) tidak punya rekening bank sendiri.
        $this->assertSame([], $this->idbankUntukUnit(5));
        $this->assertSame([], $this->idbankUntukUnit(50));
    }

    public function testUnitTidakValidMendapatDaftarKosong(): void
    {
        $this->assertSame([], $this->idbankUntukUnit(0));
        $this->assertSame([], (new ModelBank())->getBankDalamScopeUnit(-3));
    }
}
