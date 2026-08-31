<?php
/**
 * Standalone migration + seed untuk tabel KPI/Incentive baru.
 * Spark migrate gagal di PHP 8.4 (CI4 Time compatibility).
 * Pakai raw SQL MariaDB-compatible.
 *
 * Usage: php app/Scripts/kpi_migrate_seed.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();

define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('TESTPATH')) define('TESTPATH', realpath(rtrim($paths->testsDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \Config\Database::connect();
$existing = $db->listTables();

$drops = [];
foreach (['salary_structures','salary_components','incentive_rules','incentive_members','incentive_groups','kpi_evaluations','kpi_targets','kpi_weights','kpi_components'] as $t) {
    if (in_array($t, $existing, true)) { $db->query("DROP TABLE IF EXISTS `$t`"); $drops[] = $t; }
}
if ($drops) echo "Dropped (recreate): " . implode(', ', $drops) . "\n";

$statements = [];
$statements['kpi_components'] = "CREATE TABLE `kpi_components` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `type` ENUM('automatic','manual') NOT NULL,
  `category` VARCHAR(100) NULL,
  `unit_of_measure` VARCHAR(50) NULL,
  `calculation_strategy` VARCHAR(100) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kpi_components_code` (`code`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['kpi_weights'] = "CREATE TABLE `kpi_weights` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_component_id` INT UNSIGNED NOT NULL,
  `position_id` INT NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL,
  `weight_group` ENUM('kpi','absen','behavior','operational','other') NOT NULL DEFAULT 'kpi',
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pos_group_period` (`position_id`,`weight_group`,`effective_from`,`effective_to`),
  KEY `idx_eff_to` (`effective_to`),
  CONSTRAINT `fk_kw_component` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['kpi_targets'] = "CREATE TABLE `kpi_targets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kpi_component_id` INT UNSIGNED NOT NULL,
  `unit_id` INT NULL,
  `position_id` INT NULL,
  `context` ENUM('gaji','penilaian_kinerja','slip_gaji','default') NOT NULL DEFAULT 'default',
  `target_value` DECIMAL(15,2) NULL,
  `batas_awal` DECIMAL(15,2) NULL,
  `batas_kedua` DECIMAL(15,2) NULL,
  `batas_ketiga` DECIMAL(15,2) NULL,
  `batas_keempat` DECIMAL(15,2) NULL,
  `period_type` ENUM('monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
  `period_month` INT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kpi_unit_period` (`kpi_component_id`,`unit_id`,`effective_from`),
  KEY `idx_eff_to` (`effective_to`),
  CONSTRAINT `fk_kt_component` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_kt_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit`(`idunit`) ON DELETE CASCADE,
  CONSTRAINT `fk_kt_position` FOREIGN KEY (`position_id`) REFERENCES `jabatan`(`ID_JABATAN`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['kpi_evaluations'] = "CREATE TABLE `kpi_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `kpi_component_id` INT UNSIGNED NOT NULL,
  `evaluator_id` INT NOT NULL,
  `evaluation_date` DATE NOT NULL,
  `raw_score` DECIMAL(5,2) NOT NULL,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `normalized_score` DECIMAL(5,2) NOT NULL,
  `weighted_score` DECIMAL(5,2) NOT NULL,
  `notes` TEXT NULL,
  `period_year` INT NOT NULL,
  `period_month` INT NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`),
  KEY `idx_period` (`period_year`,`period_month`),
  KEY `idx_evaluator` (`evaluator_id`),
  KEY `idx_evaluation_date` (`evaluation_date`),
  CONSTRAINT `fk_ke_component` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['incentive_groups'] = "CREATE TABLE `incentive_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `incentive_groups_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['incentive_members'] = "CREATE TABLE `incentive_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incentive_group_id` INT UNSIGNED NOT NULL,
  `employee_id` INT NOT NULL,
  `unit_id` INT NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_unit_period` (`incentive_group_id`,`unit_id`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_im_group` FOREIGN KEY (`incentive_group_id`) REFERENCES `incentive_groups`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_im_employee` FOREIGN KEY (`employee_id`) REFERENCES `akun`(`ID_AKUN`) ON DELETE RESTRICT,
  CONSTRAINT `fk_im_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit`(`idunit`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['incentive_rules'] = "CREATE TABLE `incentive_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incentive_group_id` INT UNSIGNED NOT NULL,
  `kpi_component_id` INT UNSIGNED NOT NULL,
  `incentive_name` VARCHAR(255) NULL,
  `calculation_type` ENUM('percentage','tier','flat') NOT NULL DEFAULT 'percentage',
  `base_value` DECIMAL(15,2) NULL,
  `minimum_achievement` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `division_method` VARCHAR(100) NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_kpi` (`incentive_group_id`,`kpi_component_id`),
  KEY `idx_eff_to` (`effective_to`),
  CONSTRAINT `fk_ir_group` FOREIGN KEY (`incentive_group_id`) REFERENCES `incentive_groups`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ir_component` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['salary_components'] = "CREATE TABLE `salary_components` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('base','allowance','deduction','incentive') NOT NULL,
  `description` TEXT NULL,
  `default_value` DECIMAL(15,2) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_configurable` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_components_code` (`code`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$statements['salary_structures'] = "CREATE TABLE `salary_structures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `position_id` INT NOT NULL,
  `salary_component_id` INT UNSIGNED NOT NULL,
  `unit_id` INT NULL,
  `context` ENUM('gaji','penilaian_kinerja','slip_gaji','default') NOT NULL DEFAULT 'default',
  `base_value` DECIMAL(15,2) NULL,
  `calculation_type` ENUM('fixed','percent_of_base','percent_of_kpi') NOT NULL DEFAULT 'fixed',
  `multiplier` DECIMAL(5,2) NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_comp_period` (`position_id`,`salary_component_id`,`unit_id`,`context`,`effective_from`),
  KEY `idx_pos_period` (`position_id`,`effective_from`),
  CONSTRAINT `fk_ss_component` FOREIGN KEY (`salary_component_id`) REFERENCES `salary_components`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

foreach ($statements as $name => $sql) {
    if (!in_array($name, $existing, true) || in_array($name, $drops, true)) {
        $db->query($sql);
        echo "Created: $name\n";
    } else {
        echo "Exists (skip): $name\n";
    }
}

// ===== SEED =====
$dbConfig = new \Config\Database();
$dbConn = \Config\Database::connect();

require_once APPPATH . 'Database/Seeds/KPIConfigurationSeeder.php';
$seeder = new \App\Database\Seeds\KPIConfigurationSeeder($dbConfig, $dbConn);
$seeder->run();
echo "KPIConfigurationSeeder OK\n";

require_once APPPATH . 'Database/Seeds/IncentiveGroupSeeder.php';
$incSeeder = new \App\Database\Seeds\IncentiveGroupSeeder($dbConfig, $dbConn);
$incSeeder->run();
echo "IncentiveGroupSeeder OK\n";

// validasi bobot per jabatan (grup 'kpi' & 'absen')
$weights = $db->query("
    SELECT w.position_id, w.weight_group, SUM(w.weight) AS total
    FROM kpi_weights w
    WHERE w.effective_to IS NULL
    GROUP BY w.position_id, w.weight_group
    ORDER BY w.position_id, w.weight_group
")->getResultArray();
echo "\nValidasi bobot (harus 100 per grup per jabatan):\n";
foreach ($weights as $r) {
    $total = (float) $r['total'];
    $flag = abs($total - 100) < 0.01 ? 'OK' : 'INVALID';
    echo "  jab {$r['position_id']} [{$r['weight_group']}] = {$total} $flag\n";
}