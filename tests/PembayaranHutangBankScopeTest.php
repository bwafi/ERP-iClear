<?php

namespace Tests;

use App\Controllers\PembayaranHutang;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Scope rekening pada pembayaran hutang supplier.
 *
 * Role lintas unit (Finance/Root/Direktur/Manager) tetap melihat semua
 * rekening; role unit hanya melihat rekening unitnya dan wajib divalidasi
 * ulang di server saat submit.
 */
class PembayaranHutangBankScopeTest extends CIUnitTestCase
{
    private function controller(): PembayaranHutang
    {
        return new class extends PembayaranHutang {
            public function __construct()
            {
                $this->BankModel = new class {
                    public array $calls = [];

                    public function getBank(): array
                    {
                        $this->calls[] = 'all';

                        return [(object) ['idbank' => 'ALL']];
                    }

                    public function getBankDalamScopeUnit(int $unitId): array
                    {
                        $this->calls[] = 'unit:' . $unitId;

                        return [(object) ['idbank' => 'UNIT' . $unitId]];
                    }
                };
            }

            public function pilihBank(int $role, int $unitId): array
            {
                return $this->bankUntukPembayaran($role, $unitId);
            }

            public function perluValidasi(int $role): bool
            {
                return $this->wajibValidasiBankUnit($role);
            }

            public function bankModel()
            {
                return $this->BankModel;
            }
        };
    }

    public function testRoleLintasUnitMelihatSemuaBank(): void
    {
        foreach ([0, 1, 2, 34] as $role) {
            $controller = $this->controller();
            $banks = $controller->pilihBank($role, 3);

            $this->assertSame('ALL', $banks[0]->idbank, 'Role ' . $role . ' harus melihat semua bank');
            $this->assertSame(['all'], $controller->bankModel()->calls);
            $this->assertFalse($controller->perluValidasi($role));
        }
    }

    public function testRoleUnitHanyaMelihatBankUnitSendiriDanWajibValidasi(): void
    {
        foreach ([35, 41] as $role) {
            $controller = $this->controller();
            $banks = $controller->pilihBank($role, 3);

            $this->assertSame('UNIT3', $banks[0]->idbank, 'Role ' . $role . ' hanya boleh melihat bank unitnya');
            $this->assertSame(['unit:3'], $controller->bankModel()->calls);
            $this->assertTrue($controller->perluValidasi($role));
        }
    }
}
