<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';
use Config\Paths;
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('FCPATH', realpath(ROOTPATH . 'public') . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$config = new \Config\App();
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'hutangpiutang/cetak/1', 'localhost', 'http');
$request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $request);
$response = \CodeIgniter\Config\Services::response();
$logger = \CodeIgniter\Config\Services::logger();
$session = \CodeIgniter\Config\Services::session();
$session->set([
    'ID_AKUN' => 1, 'ID_JABATAN' => 0, 'ID_UNIT' => 1, 'NAMA_UNIT' => 'ICLEAR Probolinggo',
    'NAMED' => 'smoke', 'logged_in' => true,
]);
\CodeIgniter\Config\Services::injectMock('session', $session);

$ac = new \App\Controllers\HutangPiutang();
$ac->initController($request, $response, $logger);

if (getenv('DUMP_HTML')) {
    $row = (new \App\Services\Finance\HutangPiutangService())->getById(1);
    $unit = $row->unit_id ? (new \App\Models\ModelUnit())->find($row->unit_id) : null;
    $html = view('cetak/bukti_hutang_piutang', ['row' => $row, 'unit' => $unit, 'pembayaran' => []]);
    file_put_contents('/tmp/opencode/bukti.html', $html);
    echo "HTML bytes: " . strlen($html) . "\n";
    exit;
}

$ac->cetak(1);
