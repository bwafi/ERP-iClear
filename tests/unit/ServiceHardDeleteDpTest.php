<?php

namespace Tests\unit;

use App\Models\ModelService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kebijakan hard delete service terkait ledger DP (`service_dp`).
 *
 * - Service TANPA baris ledger DP: hard delete tetap berfungsi seperti semula.
 * - Service DENGAN baris ledger DP: hard delete DITOLAK agar baris ledger dan
 *   jurnal DP (tabel_referensi='service_dp') tidak menjadi yatim. Reversal DP
 *   belum tersedia pada tahap ini.
 *
 * DDL/DML nyata di group `tests` (MySQL `erp_test`, prefix dikosongkan).
 *
 * @internal
 */
final class ServiceHardDeleteDpTest extends CIUnitTestCase
{
    private const TABEL = [
        'detail_penjualan',
        'penjualan',
        'service_sparepart',
        'service_kerusakan',
        'pembayaran_bank',
        'proses_service',
        'jurnal',
        'service_dp',
        'service',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->hapusTabel();
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        $this->hapusTabel();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusTabel(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }
    }

    private function buatTabel(): void
    {
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            no_service VARCHAR(255) NULL,
            status_service INT DEFAULT 1,
            dp_bayar INT DEFAULT 0,
            updated_at DATETIME NULL)');

        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL,
            kode_dp VARCHAR(40) NOT NULL,
            jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            UNIQUE KEY uniq_sdp_kode (kode_dp))');

        $this->db->query('CREATE TABLE jurnal (
            idjurnal INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_referensi VARCHAR(255) NULL,
            tabel_referensi VARCHAR(255) NULL)');

        $this->db->query('CREATE TABLE service_sparepart (service_idservice INT NULL)');
        $this->db->query('CREATE TABLE service_kerusakan (service_idservice INT NULL)');
        $this->db->query('CREATE TABLE pembayaran_bank (id_referensi VARCHAR(255) NULL, tabel_referensi VARCHAR(255) NULL)');
        $this->db->query('CREATE TABLE proses_service (service_idservice INT NULL)');
        $this->db->query('CREATE TABLE penjualan (idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY, service_idservice INT NULL)');
        $this->db->query('CREATE TABLE detail_penjualan (penjualan_idpenjualan INT NULL)');
    }

    private function model(): ModelService
    {
        return new ModelService();
    }

    private function seedService(int $id): void
    {
        $this->db->table('service')->insert([
            'idservice'  => $id,
            'no_service' => 'SRV' . $id,
        ]);
    }

    public function testHardDeleteDitolakBilaServicePunyaLedgerDp(): void
    {
        $this->seedService(10);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 10,
            'kode_dp'           => 'DP-SRV10',
            'jumlah'            => 50000,
            'status'            => 'aktif',
        ]);
        $this->db->table('jurnal')->insert([
            'id_referensi'    => '1',
            'tabel_referensi' => 'service_dp',
        ]);

        $this->assertTrue($this->model()->hasDpLedger(10), 'Service seharusnya terdeteksi punya ledger DP.');
        $this->assertFalse($this->model()->hardDeleteService(10), 'Hard delete service ber-DP harus ditolak.');

        $this->assertSame(1, (int) $this->db->table('service')->where('idservice', 10)->countAllResults(), 'Service tidak boleh terhapus.');
        $this->assertSame(1, (int) $this->db->table('service_dp')->where('service_idservice', 10)->countAllResults(), 'Ledger DP tidak boleh hilang/yatim.');
        $this->assertSame(1, (int) $this->db->table('jurnal')->where('tabel_referensi', 'service_dp')->countAllResults(), 'Jurnal DP tidak boleh yatim.');
    }

    public function testHardDeleteTetapBerfungsiTanpaLedgerDp(): void
    {
        $this->seedService(20);
        $this->db->table('service_sparepart')->insert(['service_idservice' => 20]);
        $this->db->table('penjualan')->insert(['service_idservice' => 20]);

        $this->assertFalse($this->model()->hasDpLedger(20));
        $this->assertTrue($this->model()->hardDeleteService(20), 'Hard delete service tanpa DP harus tetap berfungsi.');

        $this->assertSame(0, (int) $this->db->table('service')->where('idservice', 20)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('service_sparepart')->where('service_idservice', 20)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('penjualan')->where('service_idservice', 20)->countAllResults());
    }

    public function testHardDeleteMembersihkanJurnalLamaService(): void
    {
        $this->seedService(30);
        $this->db->table('jurnal')->insert([
            'id_referensi'    => '30',
            'tabel_referensi' => 'service',
        ]);

        $this->assertTrue($this->model()->hardDeleteService(30));
        $this->assertSame(
            0,
            (int) $this->db->table('jurnal')->where('id_referensi', '30')->where('tabel_referensi', 'service')->countAllResults(),
            'Perilaku lama: jurnal tabel_referensi=service tetap dibersihkan.'
        );
    }
}
