<?php

namespace Tests\unit;

use App\Services\Presensi\PresensiScopeService;
use CodeIgniter\Test\CIUnitTestCase;

class PresensiScopeServiceTest extends CIUnitTestCase
{
    private PresensiScopeService $scope;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->scope = new PresensiScopeService();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS spv_units');
        $this->db->query('CREATE TABLE spv_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            spv_id INT NOT NULL,
            unit_id INT NOT NULL,
            UNIQUE KEY uniq_spv_unit (spv_id, unit_id))');
        $this->db->table('spv_units')->insertBatch([
            ['spv_id' => 56, 'unit_id' => 1],
            ['spv_id' => 56, 'unit_id' => 4],
        ]);
        $this->db->dataCache['table_names'] = [];
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS spv_units');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
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

    public function testSpvBolehFotoUnitBinaan(): void
    {
        // Bima (56) membina unit 1 dan 4.
        $this->assertTrue($this->scope->bolehLihatFoto(40, 50, 56, 1, 1));
        $this->assertTrue($this->scope->bolehLihatFoto(40, 50, 56, 1, 4));

        // Unit di luar spv_units tetap ditolak.
        $this->assertFalse($this->scope->bolehLihatFoto(40, 50, 56, 1, 7));

        // Foto sendiri tetap boleh, SPV lain tidak.
        $this->assertTrue($this->scope->bolehLihatFoto(40, 50, 56, 56, 50));
        $this->assertFalse($this->scope->bolehLihatFoto(40, 50, 57, 1, 1));
    }

    public function testUnitTerlihatRoleLintas(): void
    {
        foreach ([0, 1, 2, 34] as $jabatan) {
            $this->assertSame(['lintas' => true, 'ids' => []], $this->scope->unitTerlihat($jabatan, 99, 7));
        }
    }

    public function testUnitTerlihatSpvDanRoleLain(): void
    {
        // Bima (56) membina unit 1 dan 4.
        $this->assertSame(['lintas' => false, 'ids' => [1, 4]], $this->scope->unitTerlihat(40, 56, 50));

        // SPV tanpa mapping jatuh ke unit sesinya.
        $this->assertSame(['lintas' => false, 'ids' => [50]], $this->scope->unitTerlihat(40, 57, 50));

        // Role lain hanya unit sendiri.
        $this->assertSame(['lintas' => false, 'ids' => [2]], $this->scope->unitTerlihat(35, 99, 2));
    }
}
