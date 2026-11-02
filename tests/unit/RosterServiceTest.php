<?php

namespace Tests\unit;

use App\Services\Presensi\RosterService;
use CodeIgniter\Test\CIUnitTestCase;

class RosterServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->query('CREATE TABLE presensi_roster (
            id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            akun_idakun INT NOT NULL,
            tanggal DATE NOT NULL,
            shift VARCHAR(10) NOT NULL,
            keterangan TEXT NULL,
            created_by INT NULL,
            updated_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal)
        )');
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testTerapkanHanyaMengisiTanggalKosong(): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => 10, 'tanggal' => '2026-11-02', 'shift' => 'OFF',
            'created_by' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pola = [1 => 'PAGI', 2 => 'SIANG', 3 => 'PS', 4 => 'PAGI', 5 => 'SIANG', 6 => 'OFF', 7 => 'OFF'];

        $jumlah = (new RosterService())->terapkanBulan(10, '2026-11', $pola, 1);

        $this->assertSame(29, $jumlah); // November 30 hari, 1 sudah ada
        $this->assertSame('OFF', $this->db->table('presensi_roster')
            ->where('akun_idakun', 10)->where('tanggal', '2026-11-02')->get()->getRow()->shift);
        // 3 Nov 2026 = Selasa -> pola[2] = SIANG
        $this->assertSame('SIANG', $this->db->table('presensi_roster')
            ->where('akun_idakun', 10)->where('tanggal', '2026-11-03')->get()->getRow()->shift);
    }

    public function testSimpanTanggalUpsert(): void
    {
        $service = new RosterService();
        $service->simpanTanggal(10, '2026-11-05', 'PS', null, 1);
        $service->simpanTanggal(10, '2026-11-05', 'IZIN', 'sakit', 1);

        $row = $this->db->table('presensi_roster')
            ->where('akun_idakun', 10)->where('tanggal', '2026-11-05')->get()->getRow();
        $this->assertSame('IZIN', $row->shift);
        $this->assertSame('sakit', $row->keterangan);
        $this->assertSame(1, (int) $this->db->table('presensi_roster')->countAllResults());
    }

    public function testIzinWajibKeterangan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RosterService())->simpanTanggal(10, '2026-11-05', 'IZIN', '  ', 1);
    }

    public function testShiftTidakDikenalDitolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RosterService())->terapkanBulan(10, '2026-11', [1 => 'LIBUR'], 1);
    }
}
