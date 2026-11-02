<?php

namespace Tests\unit;

use App\Services\Presensi\PresensiScopeService;
use CodeIgniter\Test\CIUnitTestCase;

class PresensiScopeServiceTest extends CIUnitTestCase
{
    private PresensiScopeService $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scope = new PresensiScopeService();
    }

    public function testRoleLintasBolehSemuaUnit(): void
    {
        foreach ([0, 1, 34] as $jabatan) {
            $this->assertTrue($this->scope->bolehKelolaUnit($jabatan, 1, 2));
        }
    }

    public function testAdminKasirHanyaUnitSendiri(): void
    {
        foreach ([35, 47] as $jabatan) {
            $this->assertTrue($this->scope->bolehKelolaUnit($jabatan, 2, 2));
            $this->assertFalse($this->scope->bolehKelolaUnit($jabatan, 2, 1));
        }
    }

    public function testRoleLainTidakBolehKelola(): void
    {
        $this->assertFalse($this->scope->bolehKelolaUnit(36, 2, 2));
    }

    public function testHanyaAdminRootBolehKoreksiLampau(): void
    {
        $this->assertTrue($this->scope->bolehKoreksiLampau(1));
        $this->assertFalse($this->scope->bolehKoreksiLampau(0));
        $this->assertFalse($this->scope->bolehKoreksiLampau(35));
    }

    public function testFotoPemilikDanScope(): void
    {
        $this->assertTrue($this->scope->bolehLihatFoto(36, 1, 10, 10, 1));
        $this->assertTrue($this->scope->bolehLihatFoto(34, 1, 99, 10, 5));
        $this->assertTrue($this->scope->bolehLihatFoto(35, 2, 99, 10, 2));
        $this->assertFalse($this->scope->bolehLihatFoto(35, 2, 99, 10, 1));
        $this->assertFalse($this->scope->bolehLihatFoto(36, 2, 99, 10, 2));
    }
}
