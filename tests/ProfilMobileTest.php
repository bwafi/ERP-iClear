<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Halaman profil mobile dan navbar bawah tiga tab.
 *
 * @internal
 */
final class ProfilMobileTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'     => true,
            'ID_AKUN'       => 10,
            'ID_JABATAN'    => 36,
            'ID_UNIT'       => 1,
            'NAMA'          => 'Budi Santoso',
            'NAMA_JABATAN'  => 'Staf',
            'NAMA_UNIT'     => 'Unit Satu',
            'NOID'          => 'N-001',
            'EMAIL'         => 'budi@example.test',
        ]);
    }

    public function testProfilButuhLogin(): void
    {
        $this->get('profil')->assertRedirectTo(base_url('/login'));
    }

    public function testProfilMenampilkanIdentitasSesi(): void
    {
        $this->sesi();

        $result = $this->get('profil');
        $result->assertStatus(200);
        $result->assertSee('Budi Santoso');
        $result->assertSee('Staf');
        $result->assertSee('Unit Satu');
        $result->assertSee('N-001');
        $result->assertSee('budi@example.test');
        $result->assertSee('Keluar');
        $this->assertStringContainsString('id="pwa-install"', (string) $result->response()->getBody());
    }

    public function testNavbarTigaTabDanProfilAktif(): void
    {
        $this->sesi();

        $body = (string) $this->get('profil')->getBody();

        $this->assertSame(3, substr_count($body, 'class="mob-nav__item'));
        $this->assertSame(1, substr_count($body, 'aria-current="page"'));
        $this->assertStringContainsString('href="' . base_url('absen') . '"', $body);
        $this->assertStringContainsString('href="' . base_url('pengajuan_izin') . '"', $body);
        $this->assertStringContainsString('href="' . base_url('profil') . '"', $body);
    }

    public function testNavbarMenandaiTabSesuaiHalaman(): void
    {
        helper('url');

        foreach (['absen', 'izin', 'profil'] as $aktif) {
            $html = view('inc/_mobile_nav', ['active' => $aktif]);

            $this->assertSame(1, substr_count($html, 'aria-current="page"'));
            $this->assertStringContainsString('href="' . base_url($aktif === 'izin' ? 'pengajuan_izin' : $aktif) . '"', $html);
        }
    }
}
