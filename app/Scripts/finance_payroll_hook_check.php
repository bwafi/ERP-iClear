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
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \Config\Database::connect();
$row = $db->table('finance_payroll')->orderBy('id', 'ASC')->get()->getRow();
if (!$row) { echo "SKIP: tidak ada finance_payroll\n"; exit(0); }

$db->transBegin();
$model = new \App\Models\ModelFinancePayroll();
$potongan = 12345;
$total = (int) $row->total;
$model->update((int) $row->id, ['potongan_kasbon' => $potongan, 'total_bersih' => $total - $potongan]);
$check = $db->table('finance_payroll')->where('id', $row->id)->get()->getRow();
$ok = (int) $check->potongan_kasbon === $potongan && (int) $check->total_bersih === $total - $potongan;
$db->transRollback();

echo $ok ? "PASS model update potongan_kasbon/total_bersih\n" : "FAIL nilai tidak tersimpan\n";
exit($ok ? 0 : 1);
