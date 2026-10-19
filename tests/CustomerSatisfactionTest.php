<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class CustomerSatisfactionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSchema();
    }

    private function siapkanSchema(): void
    {
        $db = Database::connect();

        // Tabel khusus fitur ini boleh dibuat ulang; tabel bersama jangan
        // di-drop karena test lain di suite memakai schema yang lebih lengkap.
        $db->query('DROP TABLE IF EXISTS db_customer_satisfaction');

        foreach ([
            'CREATE TABLE IF NOT EXISTS db_stok_barang (
                idbarang INT NULL, id_unit INT NULL, stok_akhir REAL NULL, stok_minimum REAL NULL)',
            'CREATE TABLE IF NOT EXISTS db_service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, pelanggan_id_pelanggan INT NULL,
                unit_idunit INT NULL, status_service INT NULL, created_at TEXT NULL,
                tanggal_bisa_diambil TEXT NULL, tanggal_selesai TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_pelanggan (
                id_pelanggan INT PRIMARY KEY, nama TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_unit (
                idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_jabatan (
                ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_spv_units (
                spv_id INT NULL, unit_id INT NULL)',
            'CREATE TABLE db_customer_satisfaction (
                id INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL, id_unit INT NULL,
                jumlah_review INT NULL, created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                tanggal TEXT NULL, id_pelanggan INT NULL, kode_invoice TEXT NULL)',
            'CREATE TABLE IF NOT EXISTS db_menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)',
        ] as $sql) {
            $db->query($sql);
        }

        // Kolom yang dipakai layout/scope tapi tidak selalu ada di schema
        // minimal test lain.
        foreach ([
            ['unit', 'LOGO', 'TEXT NULL'],
            ['akun', 'ROLES', 'TEXT NULL'],
            ['jabatan', 'ROLES_JABATAN', 'TEXT NULL'],
        ] as [$tabel, $kolom, $definisi]) {
            // SHOW COLUMNS langsung, bukan fieldExists(): cache skema koneksi
            // tidak ikut ter-refresh setelah ALTER di setUp sebelumnya.
            $ada = $db->query(
                'SHOW COLUMNS FROM ' . $db->prefixTable($tabel) . ' LIKE ' . $db->escape($kolom)
            )->getRow();
            if (! $ada) {
                $db->query('ALTER TABLE ' . $db->prefixTable($tabel) . ' ADD COLUMN ' . $kolom . ' ' . $definisi);
            }
        }

        $db->query("INSERT INTO db_unit (idunit, NAMA_UNIT, LOGO) VALUES
            (1, 'Unit 1', ''), (2, 'Unit 2', ''), (4, 'Unit 4', '')
            ON DUPLICATE KEY UPDATE NAMA_UNIT = VALUES(NAMA_UNIT), LOGO = VALUES(LOGO)");
        $db->query("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (40, 'SPV', '[]'), (41, 'Kepala Toko', '[]')
            ON DUPLICATE KEY UPDATE NAMA_JABATAN = VALUES(NAMA_JABATAN), ROLES_JABATAN = VALUES(ROLES_JABATAN)");
        $db->query("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 41, 'Kepala Toko', '[]'), (56, 4, 40, 'SPV', '[]')
            ON DUPLICATE KEY UPDATE ID_UNIT = VALUES(ID_UNIT), ID_JABATAN = VALUES(ID_JABATAN),
                NAMA_AKUN = VALUES(NAMA_AKUN), ROLES = VALUES(ROLES)");
    }

    public function testKepalaTokoSeesInputForm(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->get('penilaian/customer_satisfaction');
        $result->assertStatus(200);

        $body = (string)$result->response()->getBody();
        $this->assertStringContainsString('Input Review Google Maps', $body);
        $this->assertStringContainsString('jumlah_review', $body);
        $this->assertStringContainsString('Rekap Bulanan', $body);
    }

    public function testSpvViewOnlyReadOnly(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 56,
            'ID_UNIT'   => 4,
            'ID_JABATAN' => 40,
        ]);

        $result = $this->get('penilaian/customer_satisfaction');
        $result->assertStatus(200);

        $body = (string)$result->response()->getBody();
        $this->assertStringNotContainsString('Input Review Google Maps', $body);
        $this->assertStringContainsString('Rekap Bulanan', $body);
    }

    public function testKepalaTokoCannotInputUnitLain(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->post('penilaian/customer_satisfaction/save', [
            'tanggal'       => '2026-09-01',
            'id_unit'       => 2,
            'jumlah_review' => 1,
        ]);
        $result->assertSessionHas('error');
    }

    public function testKepalaTokoSaveValid(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->post('penilaian/customer_satisfaction/save', [
            'tanggal'       => '2026-09-11',
            'id_unit'       => 1,
            'jumlah_review' => 0,
        ]);
        $result->assertSessionHas('message');
    }
}