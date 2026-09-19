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
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'hutangpiutang', 'localhost', 'http');
$request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $request);

$db = \Config\Database::connect();
$row = $db->table('hutang_piutang')->orderBy('id', 'ASC')->get()->getRow();
$unit = $row && $row->unit_id ? (new \App\Models\ModelUnit())->find($row->unit_id) : null;
$pembayaran = [];

$html = view('cetak/bukti_hutang_piutang', ['row' => $row, 'unit' => $unit, 'pembayaran' => $pembayaran]);
$hasLogo = strpos($html, 'iclear.my.id/assets/img/logo.png') !== false;
$hasAddr = $unit && $unit->JALAN_UNIT ? (strpos($html, $unit->JALAN_UNIT) !== false) : true;
echo ($hasLogo ? "PASS" : "FAIL") . " logo pada HTML\n";
echo ($hasAddr ? "PASS" : "FAIL") . " alamat pada HTML\n";

require_once ROOTPATH . 'vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
$mpdf->curlAllowUnsafeSslRequests = true;
$mpdf->WriteHTML($html);
$out = sys_get_temp_dir() . '/bukti_hp_check.pdf';
$mpdf->Output($out, 'F');
$size = is_file($out) ? filesize($out) : 0;
echo ($size > 1000 ? "PASS" : "FAIL") . " PDF terbentuk (" . $size . " bytes)\n";
@unlink($out);
