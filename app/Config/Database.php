<?php

namespace Config;

use CodeIgniter\Database\Config;
use App\Database\Connection;
use RuntimeException;
/**
 * Database Configuration
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations
     * and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to
     * use if no other is specified.
     */
    public string $defaultGroup = 'default';

    public $DBDebug = true;

    /**
     * The default database connection.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'connectionClass' => Connection::class,
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => '',
        'password'     => '',
        'database'     => '',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => 'utf8_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
    ];

    /**
     * This database connection is used when
     * running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'connectionClass' => Connection::class,
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => 'utf8_general_ci',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
    ];

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';

            $this->tolakTestYangTunjukProduksi();
        }
    }

    /**
     * Fail-closed: group `tests` tidak boleh menunjuk database yang sama
     * dengan group `default`.
     *
     * Kenapa perlu. CI4 memaksa defaultGroup = 'tests' saat ENVIRONMENT
     * 'testing', jadi test SUDAH aman secara default. Tapi group `tests`
     * boleh dioverride lewat env (`database.tests.database=...`), dan
     * override itulah yang pernah membuat test berjalan di atas database
     * produksi. Menyalin satu baris env itu cukup untuk menghapus data asli.
     *
     * Karena itu dicek di sini, di satu tempat, berlaku ke semua pemanggil
     * dan tidak bisa dilewati dengan sengaja atau tidak sengaja. Kalau group
     * `tests` memang butuh database tetap, buat database terpisah dengan
     * nama yang jelas (mis. `erp_xxx_test`) dan override group `tests` ke
     * situ -- bukan ke `erp_local`.
     *
     * @throws RuntimeException
     */
    private function tolakTestYangTunjukProduksi(): void
    {
        $tests   = trim((string) ($this->tests['database'] ?? ''), " '\"");
        $produksi = trim((string) ($this->default['database'] ?? ''), " '\"");

        if ($tests === '' || $produksi === '') {
            // Belum terkonfigurasi; biarkan driver yang melapor dengan jelas.
            return;
        }

        if (strtolower($tests) === strtolower($produksi)) {
            throw new RuntimeException(
                'Group database "tests" menunjuk ke database yang sama dengan produksi ("' . $produksi . '"). '
                . 'Test DDL/DML akan merusak data asli. Buat database test terpisah dan override '
                . 'group "tests" ke sana.'
            );
        }
    }
}
