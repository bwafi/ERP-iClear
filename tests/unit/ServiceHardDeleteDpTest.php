<?php

namespace Tests\unit;

use App\Models\ModelService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kebijakan hard delete service terkait ledger DP (`service_dp`).
 *
 * - Service DENGAN baris ledger DP: hard delete BOLEH, dan harus menghapus
 *   service + `service_dp` + jurnal DP terkait dalam SATU transaksi sehingga
 *   tidak meninggalkan baris yatim.
 * - Jurnal DP milik service LAIN tidak boleh ikut terhapus.
 * - Kegagalan di tengah proses harus rollback (tidak boleh setengah terhapus).
 * - Service TANPA baris ledger DP: hard delete tetap berfungsi seperti semula.
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

    public function testHardDeleteMenghapusServiceDanLedgerDpBesertaJurnalTerkait(): void
    {
        $this->seedService(10);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 10,
            'kode_dp'           => 'DP-SRV10',
            'jumlah'            => 50000,
            'status'            => 'aktif',
        ]);
        $dpId = (int) $this->db->insertID();

        $this->db->table('jurnal')->insert([
            'id_referensi'    => (string) $dpId,
            'tabel_referensi' => 'service_dp',
        ]);
        // Jurnal DP milik service lain (id DP 999) — TIDAK boleh ikut terhapus.
        $this->db->table('jurnal')->insert([
            'id_referensi'    => '999',
            'tabel_referensi' => 'service_dp',
        ]);
        // Jurnal pengambilan lama (tabel_referensi='service') — tetap dibersihkan.
        $this->db->table('jurnal')->insert([
            'id_referensi'    => '10',
            'tabel_referensi' => 'service',
        ]);

        $this->assertTrue($this->model()->hasDpLedger(10), 'Service seharusnya terdeteksi punya ledger DP.');
        $this->assertTrue($this->model()->hardDeleteService(10), 'Hard delete service ber-DP harus berhasil.');

        $this->assertSame(0, (int) $this->db->table('service')->where('idservice', 10)->countAllResults(), 'Service harus terhapus.');
        $this->assertSame(0, (int) $this->db->table('service_dp')->where('service_idservice', 10)->countAllResults(), 'Ledger DP harus terhapus.');
        $this->assertSame(
            0,
            (int) $this->db->table('jurnal')->where('tabel_referensi', 'service_dp')->where('id_referensi', (string) $dpId)->countAllResults(),
            'Jurnal DP milik service ini harus terhapus.'
        );
        $this->assertSame(
            1,
            (int) $this->db->table('jurnal')->where('tabel_referensi', 'service_dp')->where('id_referensi', '999')->countAllResults(),
            'Jurnal DP service lain tidak boleh ikut terhapus.'
        );
        $this->assertSame(
            0,
            (int) $this->db->table('jurnal')->where('tabel_referensi', 'service')->where('id_referensi', '10')->countAllResults(),
            'Jurnal pengambilan lama tetap dibersihkan.'
        );
    }

    public function testHardDeleteGagalRollbackTidakMeninggalkanLedgerYatim(): void
    {
        $this->seedService(10);
        $this->db->table('service_dp')->insert([
            'service_idservice' => 10,
            'kode_dp'           => 'DP-SRV10',
            'jumlah'            => 50000,
            'status'            => 'aktif',
        ]);
        $dpId = (int) $this->db->insertID();
        $this->db->table('jurnal')->insert([
            'id_referensi'    => (string) $dpId,
            'tabel_referensi' => 'service_dp',
        ]);

        // Paksa kegagalan SETELAH penghapusan service_dp: tabel ini dihapus
        // lebih dulu sehingga DELETE padanya error dan transaksi harus rollback.
        $this->db->query('DROP TABLE service_sparepart');

        $this->assertFalse($this->model()->hardDeleteService(10), 'Hard delete harus gagal karena error di tengah transaksi.');

        $this->assertSame(1, (int) $this->db->table('service')->where('idservice', 10)->countAllResults(), 'Service harus utuh setelah rollback.');
        $this->assertSame(1, (int) $this->db->table('service_dp')->where('service_idservice', 10)->countAllResults(), 'Ledger DP harus utuh setelah rollback.');
        $this->assertSame(
            1,
            (int) $this->db->table('jurnal')->where('tabel_referensi', 'service_dp')->where('id_referensi', (string) $dpId)->countAllResults(),
            'Jurnal DP harus utuh setelah rollback.'
        );
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
