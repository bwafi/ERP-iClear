<?php

namespace Tests\unit;

use App\Services\Service\ServiceIntakeGuard;
use CodeIgniter\Test\CIUnitTestCase;

final class ServiceIntakeGuardTest extends CIUnitTestCase
{
    public function testHanyaStatusSatuDanDuaYangBolehDisuntingDariIntake(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 5, 'unit' => 1]);

        $this->assertTrue($guard->isEditableStatus(1));
        $this->assertTrue($guard->isEditableStatus(2));
        $this->assertTrue($guard->isEditableStatus('2'));
        $this->assertFalse($guard->isEditableStatus(3));
        $this->assertFalse($guard->isEditableStatus(4));
        $this->assertFalse($guard->isEditableStatus(90));
        $this->assertFalse($guard->isEditableStatus(91));
        $this->assertFalse($guard->isEditableStatus(null));
    }

    public function testCheckTicketMenolakTiketHilangTutupDanUnitLain(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 5, 'unit' => 1]);

        $this->assertSame(['ok' => false, 'reason' => 'missing'], $guard->checkTicket(null, [1]));
        $this->assertSame(
            ['ok' => false, 'reason' => 'closed'],
            $guard->checkTicket((object) ['status_service' => 4, 'unit_idunit' => 1], [1])
        );
        $this->assertSame(
            ['ok' => false, 'reason' => 'unit'],
            $guard->checkTicket((object) ['status_service' => 1, 'unit_idunit' => 2], [1])
        );
        $this->assertSame(
            ['ok' => true, 'reason' => ''],
            $guard->checkTicket((object) ['status_service' => 2, 'unit_idunit' => 1], [1])
        );
    }

    public function testCheckTicketMenerimaArrayDanSemuaUnit(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 1, 'unit' => 1]);

        $this->assertSame(
            ['ok' => true, 'reason' => ''],
            $guard->checkTicket(['status_service' => 1, 'unit_idunit' => 99], [])
        );
    }

    public function testAllowedUnitIdsMengikutiRole(): void
    {
        $this->assertSame([], (new ServiceIntakeGuard(['role' => 1, 'unit' => 1]))->allowedUnitIds());
        $this->assertSame([], (new ServiceIntakeGuard(['role' => 34, 'unit' => 1]))->allowedUnitIds());
        $this->assertSame([3], (new ServiceIntakeGuard(['role' => 5, 'unit' => 3]))->allowedUnitIds());
        $this->assertSame([-1], (new ServiceIntakeGuard(['role' => 5, 'unit' => 0]))->allowedUnitIds());
    }

    public function testRoleNolAdalahHoDanSpvTanpaUnitTidakLolosKeSemuaUnit(): void
    {
        $this->assertSame([], (new ServiceIntakeGuard(['role' => 0, 'unit' => 1]))->allowedUnitIds());

        $_SESSION['ID_AKUN'] = 0;
        $_SESSION['ID_UNIT'] = 0;

        $this->assertSame([-1], (new ServiceIntakeGuard(['role' => 40, 'unit' => 0]))->allowedUnitIds());
    }
}
