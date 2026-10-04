# Running Application Tests

This is the quick-start to CodeIgniter testing. Its intent is to describe what
it takes to set up your application and get it ready to run unit tests.
It is not intended to be a full description of the test features that you can
use to test your application. Those details can be found in the documentation.

## Resources

* [CodeIgniter 4 User Guide on Testing](https://codeigniter.com/user_guide/testing/index.html)
* [PHPUnit docs](https://phpunit.de/documentation.html)
* [Any tutorials on Unit testing in CI4?](https://forum.codeigniter.com/showthread.php?tid=81830)

## Requirements

It is recommended to use the latest version of PHPUnit. At the time of this
writing, we are running version 9.x. Support for this has been built into the
**composer.json** file that ships with CodeIgniter and can easily be installed
via [Composer](https://getcomposer.org/) if you don't already have it installed globally.

```console
> composer install
```

If running under macOS or Linux, you can create a symbolic link to make running tests a touch nicer.

```console
> ln -s ./vendor/bin/phpunit ./phpunit
```

You also need to install [XDebug](https://xdebug.org/docs/install) in order
for code coverage to be calculated successfully. After installing `XDebug`, you must add `xdebug.mode=coverage` in the **php.ini** file to enable code coverage.

## Setting Up

A number of the tests use a running database.
In order to set up the database edit the details for the `tests` group in
**app/Config/Database.php** or **.env**.
Make sure that you provide a database engine that is currently running on your machine.
More details on a test database setup are in the
[Testing Your Database](https://codeigniter.com/user_guide/testing/database.html) section of the documentation.

## Running the tests

The entire test suite can be run by simply typing one command-line command from the main directory.

```console
> ./phpunit
```

If you are using Windows, use the following command.

```console
> vendor\bin\phpunit
```

You can limit tests to those within a single test directory by specifying the
directory name after phpunit.

```console
> ./phpunit app/Models
```

## Generating Code Coverage

To generate coverage information, including HTML reports you can view in your browser,
you can use the following command:

```console
> ./phpunit --colors --coverage-text=tests/coverage.txt --coverage-html=tests/coverage/ -d memory_limit=1024m
```

This runs all of the tests again collecting information about how many lines,
functions, and files are tested. It also reports the percentage of the code that is covered by tests.
It is collected in two formats: a simple text file that provides an overview as well
as a comprehensive collection of HTML files that show the status of every line of code in the project.

The text file can be found at **tests/coverage.txt**.
The HTML files can be viewed by opening **tests/coverage/index.html** in your favorite browser.

## PHPUnit XML Configuration

The repository has a ``phpunit.xml.dist`` file in the project root that's used for
PHPUnit configuration. This is used to provide a default configuration if you
do not have your own configuration file in the project root.

The normal practice would be to copy ``phpunit.xml.dist`` to ``phpunit.xml``
(which is git ignored), and to tailor it as you see fit.
For instance, you might wish to exclude database tests, or automatically generate
HTML code coverage reports.

## Test Cases

Every test needs a *test case*, or class that your tests extend. CodeIgniter 4
provides one class that you may use directly:
* `CodeIgniter\Test\CIUnitTestCase`

Most of the time you will want to write your own test cases that extend `CIUnitTestCase`
to hold functions and services common to your test suites.

## Aturan Isolasi Database Test (Fase 1)

Bagian ini menggantikan bagian "Setting Up" bawaan CodeIgniter untuk repo ini.
Aturan di sini mengikat; pelanggaran akan menggagalkan test.

### 1. Test TIDAK BOLEH menyentuh `erp_local`

Group `tests` di `app/Config/Database.php` secara default sudah aman:
`:memory:` dengan prefix `db_`, dan `Config\Database::__construct()` memaksa
`defaultGroup = 'tests'` selama `ENVIRONMENT === 'testing'`.

Yang berbahaya adalah **override env**. `phpunit.xml.dist` punya blok
`database.tests.*` yang sengaja dikomentari supaya tidak aktif diam-diam.
Kalau itu diaktifkan, isinya harus database KHUSUS, misalnya:

```console
> database.tests.database=erp_fase1_test
> database.tests.DBDriver=MySQLi
> database.tests.DBPrefix=db_
```

`Config\Database` sekarang menolak keras (`RuntimeException`) kalau group
`tests` resolved ke database yang sama dengan group `default`/`erp_local`.
Jadi menunjuk ke produksi akan berhenti sebelum DDL pertama jalan, bukan
setelah tabel terlanjur dihapus.

### 2. Namanya harus jelas dan berakhiran `_test`

`erp_fase1_test` dipakai test Fase 1. Database itu boleh dihapus dan dibuat
ulang sesuka hati; isinya tidak pernah berarti apa-apa.

### 3. Kalau PHPUnit gagal karena ekstensi, itu bukan hasil test

Kalau `phpunit` gagal dengan "requires the dom, json, libxml, mbstring,
tokenizer, xml, xmlwriter extensions", itu masalah interpreter, bukan test.
Jangan menjalankan test lewat PHP lain hanya karena ekstensi ada di
sana — perilaku laporan bisa berbeda, dan hasilnya tidak bisa dipakai sebagai
bukti.

## Creating Tests

Writing tests is an art, and there are many resources available to help learn how.
Review the links above and always pay attention to your code coverage.

### Database Tests

Tests can include migrating, seeding, and testing against a mock or live database.
Be sure to modify the test case (or create your own) to point to your seed and migrations
and include any additional steps to be run before tests in the `setUp()` method.
See [Testing Your Database](https://codeigniter.com/user_guide/testing/database.html)
for details.
