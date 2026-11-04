<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class CrmServiceSearchTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const SECRET = 'test-bridge-secret';
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
        $this->sebarData();
        putenv('CRM_BRIDGE_SECRET=' . self::SECRET);
        $_ENV['CRM_BRIDGE_SECRET'] = self::SECRET;
        $_SERVER['CRM_BRIDGE_SECRET'] = self::SECRET;
    }

    protected function tearDown(): void
    {
        putenv('CRM_BRIDGE_SECRET');
        unset($_ENV['CRM_BRIDGE_SECRET'], $_SERVER['CRM_BRIDGE_SECRET']);
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);
        foreach ([
            'service', 'pelanggan', 'unit', 'stok_barang', 'jabatan', 'akun',
            'service_kerusakan', 'service_sparepart',
        ] as $table) {
            $q('DROP TABLE IF EXISTS ' . $table);
        }
        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, no_hp TEXT NULL)');
        $q('CREATE TABLE stok_barang (id_unit INT NULL, stok_akhir INT NULL, stok_minimum INT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL)');
        $q('CREATE TABLE service_kerusakan (service_idservice INT NULL)');
        $q('CREATE TABLE service_sparepart (service_idservice INT NULL)');
        $q('CREATE TABLE service (
            idservice INT PRIMARY KEY AUTO_INCREMENT,
            no_service TEXT NULL, no_hp TEXT NULL, imei TEXT NULL,
            keluhan TEXT NULL, keterangan TEXT NULL, tipe_hp TEXT NULL,
            status_service INT NULL, status_proses INT NULL,
            harus_dibayar INT NULL, bayar INT NULL, garansi_hari INT NULL,
            tanggal_bisa_diambil TEXT NULL, tanggal_selesai TEXT NULL,
            pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
            created_at TEXT NULL
        )');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insert(['idunit' => 1, 'NAMA_UNIT' => 'ICLEAR Probolinggo']);
        $this->db->table('pelanggan')->insert([
            'id_pelanggan' => 1, 'nama' => 'BUDI SANTOSO', 'no_hp' => '628111000111',
        ]);
        $this->db->table('service')->insert([
            'idservice' => 1, 'no_service' => 'SRV120261102001', 'no_hp' => '0811-1000-111',
            'imei' => '356789012345678', 'keluhan' => 'LCD pecah', 'tipe_hp' => 'IPHONE 13',
            'status_service' => 1, 'harus_dibayar' => 1500000, 'bayar' => 500000,
            'pelanggan_id_pelanggan' => 1, 'unit_idunit' => 1, 'created_at' => '2026-11-02 10:00:00',
        ]);
        for ($i = 2; $i <= 25; $i++) {
            $this->db->table('service')->insert([
                'idservice' => $i, 'no_service' => 'SRV' . $i, 'no_hp' => '08120000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'tipe_hp' => 'IPHONE 11', 'status_service' => 4,
                'pelanggan_id_pelanggan' => 1, 'unit_idunit' => 1, 'created_at' => '2026-10-01 10:00:00',
            ]);
        }
    }

    private function signedGet(string $query, ?int $time = null, string $secret = self::SECRET)
    {
        $time = (string) ($time ?? time());
        $signature = hash_hmac('sha256', $query . "\n" . $time, $secret);

        return $this->withHeaders([
            'X-CRM-Time' => $time,
            'X-CRM-Signature' => $signature,
        ])->get('api/crm/service-search?q=' . urlencode($query));
    }

    public function test_rejects_missing_or_wrong_signature(): void
    {
        $this->signedGet('SRV1', null, 'wrong-secret')->assertStatus(403);
        $this->get('api/crm/service-search?q=SRV1')->assertStatus(403);
    }

    public function test_rejects_expired_timestamp(): void
    {
        $this->signedGet('SRV1', time() - 301)->assertStatus(403);
    }

    public function test_searches_by_service_number_name_phone_device_and_imei(): void
    {
        foreach (['SRV120261102001', 'BUDI', '08111000111', 'IPHONE 13', '356789012345678'] as $query) {
            $response = $this->signedGet($query);
            $response->assertStatus(200);
            $json = json_decode($response->getJSON(), true);
            $this->assertTrue($json['read_only']);
            $this->assertSame('SRV120261102001', $json['items'][0]['no_service']);
        }
    }

    public function test_limits_results_and_hides_sensitive_fields(): void
    {
        $response = $this->signedGet('SRV');
        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);

        $this->assertCount(20, $json['items']);
        $this->assertArrayNotHasKey('passcode', $json['items'][0]);
        $this->assertArrayNotHasKey('email_icloud', $json['items'][0]);
        $this->assertArrayNotHasKey('password_icloud', $json['items'][0]);
    }

    public function test_service_summary_includes_fields_needed_for_auto_link(): void
    {
        $phone = '628111000111';
        $time = (string) time();
        $month = date('Y-m');
        $signature = hash_hmac('sha256', $phone . "\n" . $month . "\n" . $time, self::SECRET);

        $response = $this->withHeaders(['X-CRM-Time' => $time, 'X-CRM-Signature' => $signature])
            ->get('api/crm/service-summary?phone=' . $phone . '&month=' . $month);
        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);
        $this->assertSame('BUDI SANTOSO', $json['items'][0]['customer_name']);
        $this->assertSame('356789012345678', $json['items'][0]['imei']);
    }
}
