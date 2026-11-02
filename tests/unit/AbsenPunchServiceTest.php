<?php

namespace Tests\unit;

use App\Models\ModelPresensi;
use App\Services\Presensi\AbsenPunchService;
use App\Services\Presensi\FotoPresensiService;
use App\Services\Presensi\PresensiException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\FakeUploadedFile;

class AbsenPunchServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';
    private array $fotoSementara = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        foreach (['presensi', 'presensi_roster', 'unit'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        foreach ($this->fotoSementara as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['presensi', 'presensi_roster', 'unit'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (
                idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL,
                LATITUDE TEXT NULL, LONGTITUDE TEXT NULL, RADIUS INT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NOT NULL, tanggal DATE NOT NULL, shift VARCHAR(10) NOT NULL,
                keterangan TEXT NULL, created_by INT NULL, updated_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal))');
        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT,
                waktu_masuk DATETIME NULL, waktu_pulang DATETIME NULL,
                jam_jadwal_masuk TIME NULL, jam_jadwal_pulang TIME NULL,
                jam_toleransi TIME NULL, status_absensi INT NULL,
                lat TEXT NULL, `long` TEXT NULL, ip TEXT NULL,
                foto TEXT NULL, foto_pulang VARCHAR(255) NULL,
                idjadwal_masuk INT NULL, akun_idakun INT NOT NULL,
                unit_idunit INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL,
                jarak INT NULL, status_kehadiran INT NULL, keterangan TEXT NULL,
                sesi VARCHAR(10) NULL, sumber VARCHAR(20) NULL)');

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LATITUDE, LONGTITUDE, RADIUS)
            VALUES (1, 'Unit Satu', '-8.1', '113.7', 200)");
    }

    private function roster(int $akunId, string $tanggal, string $shift): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => $akunId,
            'tanggal'     => $tanggal,
            'shift'       => $shift,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function foto(): FakeUploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'absen');
        file_put_contents($path, 'foto');
        $this->fotoSementara[] = $path;

        return FakeUploadedFile::buat($path);
    }

    public function testStatusKehadiranBands(): void
    {
        $this->assertSame(0, AbsenPunchService::statusKehadiran('2026-11-02 08:45:59', '08:45', '08:59'));
        $this->assertSame(1, AbsenPunchService::statusKehadiran('2026-11-02 08:46:00', '08:45', '08:59'));
        $this->assertSame(1, AbsenPunchService::statusKehadiran('2026-11-02 08:59:00', '08:45', '08:59'));
        $this->assertSame(2, AbsenPunchService::statusKehadiran('2026-11-02 08:59:01', '08:45', '08:59'));
    }

    public function testLokasiDiLuarRadiusDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new AbsenPunchService())->validasiLokasi(1, -8.2, 113.7);
    }

    public function testPesanLokasiJauhMenyebutJarakAktualDanKode(): void
    {
        try {
            (new AbsenPunchService())->validasiLokasi(1, -8.11, 113.7);
            $this->fail('Lokasi di luar radius seharusnya ditolak.');
        } catch (PresensiException $e) {
            $this->assertSame('jarak', $e->kode);
            $this->assertSame(422, $e->status);
            $this->assertSame(
                'Lokasi Anda 1.112 m dari titik absen (maks 200 m).',
                $e->getMessage()
            );
        }
    }

    public function testMasukPagiMembuatSatuBaris(): void
    {
        $this->roster(10, '2026-11-02', 'PAGI');
        $id = (new AbsenPunchService(null, null, new FakeFotoService()))->masuk(
            10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00'
        );

        $row = $this->db->table('presensi')->where('idpresensi', $id)->get()->getRow();
        $this->assertSame('FULL', $row->sesi);
        $this->assertSame('mobile', $row->sumber);
        $this->assertSame('08:45:00', $row->jam_jadwal_masuk);
        $this->assertSame(1, (int) $row->status_kehadiran);
        $this->assertSame(1, (int) $row->status_absensi, 'Absen mobile langsung final tanpa approval.');
    }

    public function testMasukGandaDitolak(): void
    {
        $this->roster(10, '2026-11-02', 'PAGI');
        $service = new AbsenPunchService(null, null, new FakeFotoService());
        $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');

        $this->expectException(PresensiException::class);
        $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:51:00');
    }

    public function testSoreTanpaPagiDitolak(): void
    {
        $this->roster(10, '2026-11-02', 'PS');
        $this->expectException(PresensiException::class);
        (new AbsenPunchService(null, null, new FakeFotoService()))->masuk(
            10, 1, 'SORE', -8.1, 113.7, 5, $this->foto(), '2026-11-02 17:00:00'
        );
    }

    public function testPsEmpatPunch(): void
    {
        $this->roster(10, '2026-11-02', 'PS');
        $service = new AbsenPunchService(null, null, new FakeFotoService());

        $pagi = $service->masuk(10, 1, 'PAGI', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');
        $service->pulang(10, $pagi, -8.1, 113.7, $this->foto(), '2026-11-02 13:00:00');
        $sore = $service->masuk(10, 1, 'SORE', -8.1, 113.7, 5, $this->foto(), '2026-11-02 17:00:00');
        $service->pulang(10, $sore, -8.1, 113.7, $this->foto(), '2026-11-02 21:00:00');

        $this->assertSame(2, (int) $this->db->table('presensi')->countAllResults());
        $this->assertSame(2, (int) $this->db->table('presensi')->where('waktu_pulang IS NOT NULL', null, false)->countAllResults());
    }

    public function testPulangOrangLainDitolak(): void
    {
        $this->roster(10, '2026-11-02', 'PAGI');
        $service = new AbsenPunchService(null, null, new FakeFotoService());
        $id = $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');

        $this->expectException(PresensiException::class);
        $service->pulang(11, $id, -8.1, 113.7, $this->foto(), '2026-11-02 17:00:00');
    }

    public function testInsertGagalMenghapusFoto(): void
    {
        $this->roster(10, '2026-11-02', 'PAGI');
        $foto = new FakeFotoService();
        $service = new AbsenPunchService(new FakePresensiGagal(), null, $foto);

        try {
            $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');
            $this->fail('Seharusnya melempar exception.');
        } catch (PresensiException $e) {
            $this->assertSame(500, $e->status);
            $this->assertTrue($foto->dihapus, 'Foto harus dihapus saat insert gagal.');
        }
    }

    public function testInsertMelemparExceptionTetapMenghapusFoto(): void
    {
        $this->roster(10, '2026-11-02', 'PAGI');
        $foto = new FakeFotoService();
        $service = new AbsenPunchService(new FakePresensiLempar(), null, $foto);

        try {
            $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');
            $this->fail('Seharusnya melempar exception.');
        } catch (PresensiException $e) {
            $this->assertSame(500, $e->status);
            $this->assertTrue($foto->dihapus, 'Foto harus dihapus saat insert melempar exception.');
        }
    }
}

class FakeFotoService extends FotoPresensiService
{
    public bool $dihapus = false;

    public function simpan(UploadedFile $file, string $jenis, int $akunId, ?string $dir = null): string
    {
        return 'test/' . $jenis . '_' . $akunId . '.jpg';
    }

    public function hapus(?string $relatif, ?string $dir = null): void
    {
        $this->dihapus = true;
    }
}

class FakePresensiGagal extends ModelPresensi
{
    public function getByAkunTanggalSesi(int $akunId, string $tanggal, string $sesi)
    {
        return null;
    }

    public function insert($data = null, bool $returnID = true)
    {
        return false;
    }
}

class FakePresensiLempar extends FakePresensiGagal
{
    public function insert($data = null, bool $returnID = true)
    {
        throw new \RuntimeException('DB down');
    }
}
