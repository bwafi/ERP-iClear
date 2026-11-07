<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * PWA mobile: manifest publik, service worker, dan tag di tema bersama.
 *
 * @internal
 */
final class PwaManifestTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testManifestPublikDanValid(): void
    {
        $result = $this->get('manifest.webmanifest');

        $result->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', $result->response()->getHeaderLine('Content-Type'));

        $manifest = json_decode((string) $result->response()->getBody(), true);
        $this->assertSame('Absen iClear', $manifest['name']);
        $this->assertSame(base_url('absen'), $manifest['start_url']);
        $this->assertSame(base_url('/'), $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertCount(3, $manifest['icons']);
        $this->assertSame(base_url('pengajuan_izin'), $manifest['shortcuts'][0]['url']);
    }

    public function testServiceWorkerAdaDiRootPublik(): void
    {
        $path = FCPATH . 'sw.js';

        $this->assertFileExists($path);
        $this->assertStringContainsString("addEventListener('fetch'", (string) file_get_contents($path));
        $this->assertStringContainsString("caches.open", (string) file_get_contents($path));
    }

    public function testTemaMobileMemuatManifestDanRegistrasiSw(): void
    {
        helper('url');
        $html = view('inc/_presensi_mobile_theme');

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString(base_url('manifest.webmanifest'), $html);
        $this->assertStringContainsString(base_url('sw.js'), $html);
        $this->assertStringContainsString('serviceWorker.register', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
    }

    public function testDialogInstallPwa(): void
    {
        helper('url');
        $html = view('inc/_pwa_install');

        $this->assertStringContainsString('<dialog class="pwa-install" id="pwa-install"', $html);
        $this->assertStringContainsString('data-pwa-install', $html);
        $this->assertStringContainsString('data-pwa-dismiss', $html);
        $this->assertStringContainsString(base_url('template/assets/pwa/icon-192.png'), $html);
    }

    public function testAjakanInstallMemakaiPromptAsliDanCooldown(): void
    {
        helper('url');
        $html = view('inc/_presensi_mobile_theme');

        $this->assertStringContainsString("addEventListener('beforeinstallprompt'", $html);
        $this->assertStringContainsString('.prompt()', $html);
        $this->assertStringContainsString('(display-mode: standalone)', $html);
        $this->assertStringContainsString("addEventListener('appinstalled'", $html);
        $this->assertStringContainsString('pwa-install-seen', $html);
        $this->assertStringContainsString('CriOS', $html);
        $this->assertStringContainsString('pwa-install__steps', $html);
        $this->assertStringContainsString('Tambahkan ke Layar Utama', $html);
    }

    public function testHalamanMobileMemasangDialogInstall(): void
    {
        foreach (['absen/mobile.php', 'izin/pengajuan.php', 'profil/mobile.php'] as $view) {
            $this->assertStringContainsString(
                "view('inc/_pwa_install')",
                (string) file_get_contents(APPPATH . 'Views/' . $view),
                $view . ' tidak memasang dialog install',
            );
        }
    }
}
