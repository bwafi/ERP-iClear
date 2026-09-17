<?php

namespace Tests\SocialMedia;

use App\Models\ModelUnit;
use App\Services\SocialMedia\SocialMediaScopeService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Test Unit Genteng & seed akun (idempotensi, relasi unit, authorization).
 */
class UnitAndSeedTest extends CIUnitTestCase
{
    private ModelUnit $unitModel;

    protected function setUp(): void
    {
        parent::setUp();
        // Forcing koneksi 'default' (tanpa prefix db_) untuk memakai tabel unit asli.
        $this->unitModel = new ModelUnit(\Config\Database::connect('default'));
    }

    public function testUnitGentengDibuatDanTidakDuplicate(): void
    {
        $id = $this->unitModel->ensureGenteng();
        $this->assertGreaterThan(0, $id);

        // Jalankan lagi → tidak boleh duplicate.
        $id2 = $this->unitModel->ensureGenteng();
        $this->assertSame($id, $id2);

        $rows = $this->unitModel->select('idunit')
            ->groupStart()
            ->like('NAMA_UNIT', 'Genteng', 'both')
            ->orWhere('kode_unit', 'GNT')
            ->groupEnd()
            ->findAll();
        $this->assertCount(1, $rows);
    }

    public function testEnamAkunTersediaDanUnitBenar(): void
    {
        $db = \Config\Database::connect('default');

        $accounts = $db->table('social_media_accounts')->get()->getResultArray();
        $this->assertGreaterThanOrEqual(6, count($accounts));

        $expect = [
            'iClear Center'     => 'Probolinggo',
            'iClear Jember'     => 'Jember',
            'iClear Banyuwangi' => 'Banyuwangi',
            'iClear Pandaan'    => 'Pandaan',
            'iClear Genteng'    => 'Genteng',
        ];

        foreach ($expect as $name => $unitPart) {
            $found = false;
            foreach ($accounts as $acc) {
                if (stripos($acc['account_name'], $name) !== false) {
                    $unitName = $db->table('unit')->where('idunit', (int)$acc['unit_id'])->get()->getRow()->NAMA_UNIT;
                    $this->assertStringContainsStringIgnoringCase($unitPart, $unitName, "Unit akun {$name}");
                    $this->assertSame('facebook', $acc['platform']);
                    $found = true;
                }
            }
            $this->assertTrue($found, "Akun {$name} tidak ditemukan.");
        }

        // TikTok = Unit Center / global.
        $tiktok = $db->table('social_media_accounts')->where('platform', 'tiktok')->where('is_active', 1)->get()->getRow();
        $this->assertNotNull($tiktok, 'Akun TikTok aktif harus ada.');
        $unitCenter = $db->table('unit')->where('idunit', (int)$tiktok->unit_id)->get()->getRow();
        $this->assertNotNull($unitCenter, 'TikTok harus punya unit (Center).');
    }

    public function testAuthorizationRole(): void
    {
        // Kelola: 0 Admin, 1 Root, 2 Direktur, 34 Manager, 43 Kepala Divisi
        foreach ([0, 1, 2, 34, 43] as $role) {
            $this->assertTrue(SocialMediaScopeService::canManage($role));
            $this->assertTrue(SocialMediaScopeService::canView($role));
        }

        // Role lain tidak boleh.
        $this->assertFalse(SocialMediaScopeService::canView(48));
        $this->assertFalse(SocialMediaScopeService::canManage(48));
    }
}