<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class IzinApprovalTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

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
        foreach (['izin', 'jenis_perizinan', 'akun', 'unit', 'presensi', 'presensi_roster', 'stok_barang', 'service', 'pelanggan', 'menu', 'jabatan'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['izin', 'jenis_perizinan', 'akun', 'unit', 'presensi', 'presensi_roster', 'stok_barang', 'service', 'pelanggan', 'menu', 'jabatan'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (
                idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
                LATITUDE TEXT NULL, LONGTITUDE TEXT NULL, RADIUS INT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jenis_perizinan (
                idjenis_perizinan INT PRIMARY KEY AUTO_INCREMENT, jenis_izin VARCHAR(225) NULL)');
        $q('CREATE TABLE izin (
                idizin INT PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NULL, alasan TEXT NULL, status INT NULL DEFAULT 0,
                tanggal_mulai DATE NULL, uuid_atasan TEXT NULL, pegawai_uuid VARCHAR(100) NULL,
                tanggal_akhir DATE NULL, file TEXT NULL, balasan TEXT NULL,
                jenis_perizinan_idjenis_perizinan INT NOT NULL)');
        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NOT NULL,
                waktu_masuk DATETIME NULL, status_absensi INT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NOT NULL, tanggal DATE NOT NULL,
                shift VARCHAR(10) NOT NULL, keterangan TEXT NULL, created_by INT NULL,
                updated_by INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, nama_barang TEXT NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
                tanggal_bisa_diambil TEXT NULL, tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN)
            VALUES (10, 1, 36, 'Budi'), (11, 1, 36, 'Siti')");
        $q("INSERT INTO jenis_perizinan (idjenis_perizinan, jenis_izin)
            VALUES (1, 'IZIN'), (2, 'SAKIT'), (3, 'CUTI')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)
            VALUES (36, 'Staf', '[]')");
    }

    private function sesi(int $akun = 10, int $unit = 1, int $jabatan = 36): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => $akun,
            'ID_UNIT'    => $unit,
            'ID_JABATAN' => $jabatan,
            'NAMA'       => 'Tester',
        ]);
    }

    private function ajukan(int $akunId = 10, int $jenis = 2, string $alasan = 'Demam'): int
    {
        $this->db->table('izin')->insert([
            'akun_idakun'                       => $akunId,
            'jenis_perizinan_idjenis_perizinan' => $jenis,
            'tanggal_mulai'                     => '2026-11-03',
            'tanggal_akhir'                     => '2026-11-04',
            'alasan'                            => $alasan,
            'status'                            => 0,
        ]);

        return (int) $this->db->insertID();
    }

    public function testPengajuanMenyimpanAkunDariSession(): void
    {
        $this->sesi(10);
        $result = $this->post('pengajuan_izin/simpan', [
            'jenis'         => 2,
            'tanggal_mulai' => '2026-11-05',
            'tanggal_akhir' => '2026-11-06',
            'alasan'        => 'Demam tinggi',
        ]);

        $result->assertRedirectTo(base_url('pengajuan_izin'));

        $row = $this->db->table('izin')->get()->getRow();
        $this->assertSame(10, (int) $row->akun_idakun);
        $this->assertSame(2, (int) $row->jenis_perizinan_idjenis_perizinan);
        $this->assertSame(0, (int) $row->status);
        $this->assertSame('2026-11-06', $row->tanggal_akhir);
    }

    public function testPengajuanTanpaAlasanDitolak(): void
    {
        $this->sesi(10);
        $this->post('pengajuan_izin/simpan', [
            'jenis'         => 1,
            'tanggal_mulai' => '2026-11-05',
            'alasan'        => '   ',
        ]);

        $this->assertSame(0, (int) $this->db->table('izin')->countAllResults());
    }

    public function testApprovalHanyaMenampilkanIzin(): void
    {
        $this->sesi(10);
        $this->ajukan(10, 2, 'Demam');
        $this->db->table('presensi')->insert([
            'akun_idakun' => 11, 'waktu_masuk' => '2026-11-02 08:00:00', 'status_absensi' => 0,
        ]);

        $result = $this->get('approval_presensi');
        $result->assertSee('Budi');
        $result->assertSee('SAKIT');
        $result->assertDontSee('Siti');
    }

    public function testSetujuiMengubahStatusDanBalasan(): void
    {
        $id = $this->ajukan();
        $this->sesi(10);

        $this->post('approval_presensi/putuskan', [
            'idizin'  => $id,
            'status'  => 1,
            'balasan' => 'Disetujui, lekas sembuh.',
        ])->assertRedirectTo(base_url('approval_presensi'));

        $row = $this->db->table('izin')->where('idizin', $id)->get()->getRow();
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('Disetujui, lekas sembuh.', $row->balasan);
    }

    public function testSetujuiMenulisRosterIzinSesuaiJenis(): void
    {
        $id = $this->ajukan(10, 2, 'Demam');
        $this->sesi(10);

        $this->post('approval_presensi/putuskan', [
            'idizin' => $id,
            'status' => 1,
        ])->assertRedirectTo(base_url('approval_presensi'));

        $rows = $this->db->table('presensi_roster')
            ->where('akun_idakun', 10)
            ->orderBy('tanggal', 'ASC')
            ->get()
            ->getResult();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-11-03', $rows[0]->tanggal);
        $this->assertSame('SAKIT', $rows[0]->shift);
        $this->assertNull($rows[0]->keterangan);
    }

    public function testTolakSetelahSetujuiMenghapusRosterIzin(): void
    {
        $id = $this->ajukan(10, 3, 'Acara keluarga');
        $this->sesi(10);

        $this->post('approval_presensi/putuskan', ['idizin' => $id, 'status' => 1]);
        $this->post('approval_presensi/putuskan', ['idizin' => $id, 'status' => 2]);

        $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
    }

    public function testTolakMengubahStatus(): void
    {
        $id = $this->ajukan();
        $this->sesi(10);

        $this->post('approval_presensi/putuskan', ['idizin' => $id, 'status' => 2]);

        $row = $this->db->table('izin')->where('idizin', $id)->get()->getRow();
        $this->assertSame(2, (int) $row->status);
    }

    public function testStatusTidakValidTidakMengubahData(): void
    {
        $id = $this->ajukan();
        $this->sesi(10);

        $this->post('approval_presensi/putuskan', ['idizin' => $id, 'status' => 9]);

        $row = $this->db->table('izin')->where('idizin', $id)->get()->getRow();
        $this->assertSame(0, (int) $row->status);
    }

    public function testHalamanPengajuanMenampilkanRiwayatSendiri(): void
    {
        $this->ajukan(10, 3, 'Acara keluarga');
        $this->ajukan(11, 2, 'Sakit gigi');
        $this->sesi(10);

        $result = $this->get('pengajuan_izin');
        $result->assertSee('Acara keluarga');
        $result->assertDontSee('Sakit gigi');
        // Mobile-first: halaman standalone dengan viewport penuh dan tanpa tabel lebar.
        $result->assertSee('viewport-fit=cover');
        $result->assertDontSee('<table');
    }

    private function tanggal(int $offsetHari): string
    {
        $zona = new \DateTimeZone(config('Presensi')->zonaWaktu);

        return (new \DateTimeImmutable('today', $zona))
            ->modify(sprintf('%+d days', $offsetHari))
            ->format('Y-m-d');
    }

    public function testCutiKurangTujuhHariDitolak(): void
    {
        $this->sesi(10);
        $this->post('pengajuan_izin/simpan', [
            'jenis'         => 3,
            'tanggal_mulai' => $this->tanggal(6),
            'alasan'        => 'Pulang kampung',
        ]);

        $this->assertSame(0, (int) $this->db->table('izin')->countAllResults());
    }

    public function testCutiTujuhHariDiterima(): void
    {
        $this->sesi(10);
        $this->post('pengajuan_izin/simpan', [
            'jenis'         => 3,
            'tanggal_mulai' => $this->tanggal(7),
            'alasan'        => 'Pulang kampung',
        ]);

        $this->assertSame(1, (int) $this->db->table('izin')->countAllResults());
    }

    public function testSakitHariIniDiterima(): void
    {
        $this->sesi(10);
        $this->post('pengajuan_izin/simpan', [
            'jenis'         => 2,
            'tanggal_mulai' => $this->tanggal(0),
            'alasan'        => 'Demam',
        ]);

        $this->assertSame(1, (int) $this->db->table('izin')->countAllResults());
    }

    public function testIzinHariIniDiterima(): void
    {
        $this->sesi(10);
        $this->post('pengajuan_izin/simpan', [
            'jenis'         => 1,
            'tanggal_mulai' => $this->tanggal(0),
            'alasan'        => 'Urusan darurat keluarga',
        ]);

        $this->assertSame(1, (int) $this->db->table('izin')->countAllResults());
    }

    public function testSuratDokterHanyaBisaDiaksesPemilikAtauAtasan(): void
    {
        $dir  = WRITEPATH . 'uploads/izin_test_' . uniqid();
        $asli = config('Presensi')->suratDir;
        config('Presensi')->suratDir = $dir;

        try {
            mkdir($dir . '/202611', 0775, true);
            file_put_contents($dir . '/202611/surat.png', 'isi-surat');
            $id = $this->ajukan(10, 2, 'Demam');
            $this->db->table('izin')->where('idizin', $id)->update(['file' => '202611/surat.png']);

            $this->sesi(10);
            $pemilik = $this->get('pengajuan_izin/surat/' . $id);
            $pemilik->assertStatus(200);
            $this->assertStringContainsString('isi-surat', (string) $pemilik->response()->getBody());

            $this->sesi(11);
            $this->get('pengajuan_izin/surat/' . $id)->assertStatus(403);
        } finally {
            config('Presensi')->suratDir = $asli;
            if (is_dir($dir)) {
                $this->hapusDir($dir);
            }
        }
    }

    private function hapusDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->hapusDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
