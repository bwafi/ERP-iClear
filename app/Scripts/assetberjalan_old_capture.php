<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH.'../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('TESTPATH')) define('TESTPATH', realpath(rtrim($paths->testsDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$unit = $argv[1] ?? 1;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

$request = new IncomingRequest(new App(), new URI('http://localhost/assetberjalan'), null, new UserAgent());
$request->setGlobal('get', ['unit' => (string)$unit]);

$ctrl = new \App\Controllers\TutupKasir();
$refProp = new ReflectionProperty(\App\Controllers\TutupKasir::class, 'request');
$refProp->setAccessible(true);
$refProp->setValue($ctrl, $request);

$ref = new ReflectionMethod(\App\Controllers\TutupKasir::class, 'assetberjalan');

ob_start();
$html = $ref->invoke($ctrl);
$out = ob_get_clean();
if (is_string($html)) { $out .= $html; }

// Ambil angka di dekat label tertentu
function nearValue($html, $label, $lookaheadBytes = 2000) {
    $pos = strpos($html, $label);
    if ($pos === false) return null;
    $chunk = substr($html, $pos, $lookaheadBytes);
    if (preg_match('/Rp\s*([\d.]+)/', $chunk, $m)) {
        return (float) str_replace('.', '', $m[1]);
    }
    return null;
}

file_put_contents(WRITEPATH . 'tmp/assetberjalan_old.html', $out);

echo "=== OLD assetberjalan() rendered ===\n";
echo "omset_bulan  : " . var_export(nearValue($out, 'Omset Bulan Ini'), true) . "\n";
echo "pengeluaran  : " . var_export(nearValue($out, 'Pengeluaran'), true) . "\n";
echo "totalGajiUnito: " . var_export(nearValue($out, 'Total Gaji'), true) . "\n";
