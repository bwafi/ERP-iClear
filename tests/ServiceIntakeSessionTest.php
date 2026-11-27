<?php

/**
 * Feature test sesi intake service: tiket yang boleh disunting dari /service
 * hanya status 1/2 dan unit dalam cakupan user.
 */

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServiceIntakeSessionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_service',
        'db_service_kerusakan',
        'db_service_sparepart',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_barang',
        'db_fungsi',
        'db_bank',
        'db_no_akun',
        'db_akun_kas_bank',
        'db_alokasi_saldo_kas_bank',
        'db_provinces',
        'db_akun',
        'db_jabatan',
        'db_menu',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->koneksiFresh();
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        $db = $this->koneksiFresh();

        foreach (self::TABEL as $t) {
            $db->query("DROP TABLE IF EXISTS {$t}");
        }

        parent::tearDown();
    }

    private function koneksiFresh()
    {
        return \Config\Database::connect('tests', false);
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        // `stok_barang` di produksi berupa VIEW turunan banyak tabel. Di
        // lingkungan uji view itu bisa sudah tidak valid (tabel sumber di-drop
        // test lain), sedangkan BaseController::initController selalu
        // memanggil ModelStokBarang::getStokMinimum(). Ganti dengan tabel
        // ringkas agar request halaman tidak bergantung pada view tersebut.
        $q('DROP VIEW IF EXISTS db_stok_barang');

        foreach (self::TABEL as $t) {
            $q("DROP TABLE IF EXISTS {$t}");
        }

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(255) NULL,
            no_hp VARCHAR(255) NULL,
            imei VARCHAR(255) NULL,
            alamat TEXT NULL,
            keluhan VARCHAR(255) NULL,
            keterangan TEXT NULL,
            passcode VARCHAR(255) NULL,
            tipe_hp VARCHAR(255) NULL,
            type_passcode VARCHAR(255) NULL,
            email_icloud VARCHAR(255) NULL,
            password_icloud TEXT NULL,
            status_service INT NOT NULL DEFAULT 1,
            status_proses INT NOT NULL DEFAULT 1,
            total_service INT NOT NULL DEFAULT 0,
            total_diskon INT NOT NULL DEFAULT 0,
            harus_dibayar INT NOT NULL DEFAULT 0,
            bayar INT NOT NULL DEFAULT 0,
            dp_bayar INT NOT NULL DEFAULT 0,
            garansi_hari INT NOT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NOT NULL DEFAULT 1,
            unit_idunit INT NOT NULL,
            service_by INT NOT NULL,
            input_by INT NOT NULL,
            tanggal_bisa_diambil DATETIME NULL,
            tanggal_selesai DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL ON UPDATE current_timestamp(),
            estimasi_biaya VARCHAR(255) NULL,
            tanggal_claim_garansi DATETIME NULL,
            biaya_tambahan_garansi INT NOT NULL DEFAULT 0,
            total_service_garansi INT NOT NULL DEFAULT 0,
            total_diskon_garansi INT NOT NULL DEFAULT 0,
            service_by_garansi INT NULL,
            bayar_garansi INT NOT NULL DEFAULT 0,
            harus_dibayar_garansi INT NOT NULL DEFAULT 0,
            bayar_tunai INT NULL,
            bayar_bank VARCHAR(255) NULL,
            bayar_tunai_garansi INT NULL,
            prioritas INT NOT NULL DEFAULT 0,
            refund_garansi_idkas_keluar INT NULL,
            refund_garansi_tanggal DATE NULL,
            refund_garansi_metode VARCHAR(10) NULL,
            refund_garansi_idbank VARCHAR(20) NULL,
            refund_garansi_referensi VARCHAR(100) NULL,
            refund_garansi_alasan VARCHAR(255) NULL,
            refund_garansi_jumlah INT NOT NULL DEFAULT 0,
            refund_garansi_by INT NULL,
            refund_garansi_at DATETIME NULL,
            refund_garansi_omset INT NOT NULL DEFAULT 0,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_kerusakan (
            idservice_kerusakan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            fungsi_idfungsi INT NULL,
            keterangan VARCHAR(255) NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idservice_kerusakan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            barang_idbarang INT NULL,
            jumlah INT NOT NULL DEFAULT 0,
            jumlah_tambahan_garansi INT NOT NULL DEFAULT 0,
            jumlah_refund_garansi INT NOT NULL DEFAULT 0,
            unit_idunit INT NULL,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            provinsi VARCHAR(255) NULL,
            kabupaten VARCHAR(255) NULL,
            kecamatan VARCHAR(255) NULL,
            deleted INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            LOGO VARCHAR(255) NULL,
            is_operational INT NOT NULL DEFAULT 1,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_barang (
            id_unit INT NULL,
            idbarang INT NULL,
            nama_barang VARCHAR(255) NULL,
            nama_unit VARCHAR(150) NULL,
            stok_akhir INT NULL DEFAULT 0,
            stok_minimum INT NULL DEFAULT 0
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_barang (
            idbarang INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_barang VARCHAR(64) NULL,
            nama_barang VARCHAR(255) NULL,
            deleted INT NOT NULL DEFAULT 0,
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_fungsi (
            idfungsi INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_fungsi VARCHAR(255) NULL,
            deleted INT NOT NULL DEFAULT 0,
            PRIMARY KEY (idfungsi)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_bank (
            idbank INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_bank VARCHAR(150) NULL,
            atas_nama VARCHAR(150) NULL,
            norek VARCHAR(100) NULL,
            PRIMARY KEY (idbank)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_no_akun (
            no_akun VARCHAR(50) NOT NULL,
            nama_akun VARCHAR(150) NULL,
            PRIMARY KEY (no_akun)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_akun_kas_bank (
            idakun_kas_bank INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_id INT NULL,
            bank_idbank INT NULL,
            no_akun_coa VARCHAR(50) NULL,
            tipe VARCHAR(20) NULL,
            status VARCHAR(20) NULL,
            nama_akun VARCHAR(150) NULL,
            is_shared INT NOT NULL DEFAULT 0,
            is_finance_ho INT NOT NULL DEFAULT 0,
            PRIMARY KEY (idakun_kas_bank)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_alokasi_saldo_kas_bank (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            akun_kas_bank_id INT UNSIGNED NOT NULL,
            unit_id INT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_provinces (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_akun (
            ID_AKUN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ID_JABATAN INT NULL,
            ID_UNIT INT NULL DEFAULT 0,
            NAMA_AKUN VARCHAR(100) NULL,
            ROLES TEXT NULL,
            PRIMARY KEY (ID_AKUN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_menu (
            idmenu INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent INT NOT NULL DEFAULT 0,
            sub INT NOT NULL DEFAULT 0,
            categories INT NOT NULL DEFAULT 0,
            show_menu INT NOT NULL DEFAULT 0,
            urutan INT NOT NULL DEFAULT 0,
            nama_menu VARCHAR(150) NULL,
            icon VARCHAR(100) NULL,
            url VARCHAR(255) NULL,
            PRIMARY KEY (idmenu)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jabatan (
            ID_JABATAN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_JABATAN VARCHAR(150) NULL,
            ROLES_JABATAN TEXT NULL,
            PRIMARY KEY (ID_JABATAN)
        ) ENGINE=InnoDB');

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Admin Tes'), (44, 5, 1, 'Teknisi Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL), (5, 'Teknisi Tes', NULL)");
        $q("INSERT INTO db_pelanggan (id_pelanggan, nama) VALUES (10, 'Pelanggan Sepuluh'), (11, 'Pelanggan Sebelas')");
        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu'), (2, 'Unit Dua')");
        $q("INSERT INTO db_fungsi (idfungsi, nama_fungsi) VALUES (1, 'Layar')");
    }

    private function sesi(array $extra = []): void
    {
        $this->withSession(array_merge([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 1,
        ], $extra));
    }

    private function seedService(array $override = []): int
    {
        $this->db->table('service')->insert(array_merge([
            'no_service'             => 'SRV-UJI-1',
            'status_service'         => 1,
            'pelanggan_id_pelanggan' => 10,
            'unit_idunit'            => 1,
            'service_by'             => 43,
            'input_by'               => 43,
            'created_at'             => date('Y-m-d H:i:s'),
        ], $override));

        return (int) $this->db->insertID();
    }

    public function testSesiTiketSelesaiDiresetKeFormServiceBaru(): void
    {
        $id = $this->seedService(['status_service' => 4, 'no_service' => 'SRV-LAMA']);
        $this->sesi(['idservice' => $id]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('insert/pelanggan_service', $html);
        $this->assertStringNotContainsString('update/pelanggan_service', $html);
        $this->assertStringNotContainsString('service/clear_session', $html);
    }

    public function testSesiTiketBisaDiambilDiresetKeFormServiceBaru(): void
    {
        $id = $this->seedService(['status_service' => 3, 'no_service' => 'SRV-AMBIL']);
        $this->sesi(['idservice' => $id]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('insert/pelanggan_service', $html);
        $this->assertStringNotContainsString('service/clear_session', $html);
    }

    public function testSesiTiketUnitLainDiresetUntukRoleUnit(): void
    {
        $id = $this->seedService(['status_service' => 1, 'unit_idunit' => 2]);
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 44,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 5,
            'idservice'  => $id,
        ]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('insert/pelanggan_service', $html);
        $this->assertStringNotContainsString('service/clear_session', $html);
    }

    public function testSesiTiketAktifMenampilkanBannerDanTombolServiceBaru(): void
    {
        $id = $this->seedService(['status_service' => 2, 'no_service' => 'SRV-AKTIF']);
        $this->sesi(['idservice' => $id]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('update/pelanggan_service', $html);
        $this->assertStringContainsString('service-ticket-head', $html);
        $this->assertStringContainsString('service-ticket-status is-proses', $html);
        $this->assertStringContainsString('SRV-AKTIF', $html);
        $this->assertStringContainsString('Service Baru', $html);
        $this->assertStringContainsString('service/clear_session', $html);
    }

    public function testBannerTiketMenungguMemakaiPenandaStatusMenunggu(): void
    {
        $id = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-MENUNGGU']);
        $this->sesi(['idservice' => $id]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('service-ticket-status is-menunggu', $html);
        $this->assertStringContainsString('SRV-MENUNGGU', $html);
    }

    public function testEditPelangganDariIntakeDitolakSaatTiketSelesai(): void
    {
        $id = $this->seedService(['status_service' => 4, 'tipe_hp' => 'iPhone Lama']);
        $this->sesi(['idservice' => $id]);

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'return_to'           => 'service',
            'selectedidpelanggan' => 10,
            'tipe_hp'             => 'iPhone Baru',
            'imei'                => '999',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame('iPhone Lama', $row->tipe_hp);
        $this->assertStringContainsString('service baru', strtolower($this->flashGagal()));
    }

    public function testEditPelangganDariIntakeDitolakBilaPelangganBerbeda(): void
    {
        $id = $this->seedService(['status_service' => 2, 'pelanggan_id_pelanggan' => 10]);
        $this->sesi(['idservice' => $id]);

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'return_to'           => 'service',
            'selectedidpelanggan' => 11,
            'tipe_hp'             => 'iPhone Baru',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(10, (int) $row->pelanggan_id_pelanggan);
        $this->assertStringContainsString('service baru', strtolower($this->flashGagal()));
    }

    public function testEditDariIntakeTanpaPilihanPelangganTetapDisimpan(): void
    {
        $id = $this->seedService(['status_service' => 2]);
        $this->sesi(['idservice' => $id]);

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'return_to'           => 'service',
            'selectedidpelanggan' => '',
            'tipe_hp'             => 'iPhone Baru',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame('iPhone Baru', $row->tipe_hp);
    }

    public function testGantiPelangganDariHalamanDetailTersimpan(): void
    {
        $id = $this->seedService(['status_service' => 4, 'pelanggan_id_pelanggan' => 10]);
        $this->sesi();

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'selectedidpelanggan' => 11,
            'pelanggan_changed'   => '1',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(11, (int) $row->pelanggan_id_pelanggan);
    }

    public function testGantiPelangganTanpaKonfirmasiPilihTidakMengubahTiketMaupunDomisili(): void
    {
        $id = $this->seedService(['status_service' => 4, 'pelanggan_id_pelanggan' => 10]);
        $this->sesi();

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'selectedidpelanggan' => 11,
            'domisili_provinsi'   => 'Provinsi Pelanggan Lama',
        ])->assertStatus(302);

        $db = $this->koneksiFresh();
        $service = $db->table('service')->where('idservice', $id)->get()->getRow();
        $pelangganBaru = $db->table('pelanggan')->where('id_pelanggan', 11)->get()->getRow();

        $this->assertSame(10, (int) $service->pelanggan_id_pelanggan);
        $this->assertNull($pelangganBaru->provinsi);
    }

    public function testGantiPelangganDariDetailMengabaikanPelangganTerhapus(): void
    {
        $id = $this->seedService(['status_service' => 4, 'pelanggan_id_pelanggan' => 10]);
        $this->koneksiFresh()->table('pelanggan')->insert([
            'id_pelanggan' => 12,
            'nama'         => 'Pelanggan Terhapus',
            'deleted'      => 1,
        ]);
        $this->sesi();

        $this->post('update/pelanggan_service', [
            'idservice'           => $id,
            'selectedidpelanggan' => 12,
            'pelanggan_changed'   => '1',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(10, (int) $row->pelanggan_id_pelanggan);
    }

    public function testEditPelangganDariIntakeDitolakBilaIdTidakSamaDenganSesi(): void
    {
        $aktif = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-AKTIF']);
        $lain  = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-LAIN']);
        $this->sesi(['idservice' => $aktif]);

        $this->post('update/pelanggan_service', [
            'idservice'  => $lain,
            'return_to'  => 'service',
            'tipe_hp'    => 'iPhone Baru',
        ])->assertStatus(302);

        $row = $this->koneksiFresh()->table('service')->where('idservice', $lain)->get()->getRow();
        $this->assertNull($row->tipe_hp);
        $this->assertStringContainsString('service baru', strtolower($this->flashGagal()));
    }

    public function testSesiTiketUnitLainDipertahankanUntukRoleHO(): void
    {
        $id = $this->seedService(['status_service' => 1, 'unit_idunit' => 2, 'no_service' => 'SRV-HO']);
        $this->sesi(['idservice' => $id]);

        $r = $this->get('service');
        $r->assertStatus(200);
        $html = (string) $r->getBody();

        $this->assertStringContainsString('update/pelanggan_service', $html);
        $this->assertStringContainsString('SRV-HO', $html);
        $this->assertStringContainsString('service/clear_session', $html);
    }

    private function flashGagal(): string
    {
        return (string) ($_SESSION['gagal'] ?? '');
    }

    public function testKerusakanTidakDisimpanDanTidakMencetakSaatTiketSelesai(): void
    {
        $id = $this->seedService(['status_service' => 4]);
        $this->sesi(['idservice' => $id]);

        $r = $this->post('service/saveKerusakan', [
            'idservice_k' => $id,
            'fungsi'      => [1],
            'keterangan'  => [1 => 'retak'],
        ]);

        $r->assertStatus(302);
        $this->assertStringContainsString('service?tab=kerusakan', (string) $r->getRedirectUrl());
        $this->assertStringNotContainsString('cetak/invoice_service', (string) $r->getRedirectUrl());
        $this->assertSame(0, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
    }

    public function testKerusakanDitolakBilaIdTidakSamaDenganSesi(): void
    {
        $aktif = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-AKTIF']);
        $lain  = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-LAIN']);
        $this->sesi(['idservice' => $aktif]);

        $r = $this->post('service/saveKerusakan', [
            'idservice_k' => $lain,
            'fungsi'      => [1],
        ]);

        $r->assertStatus(302);
        $this->assertStringNotContainsString('cetak/invoice_service', (string) $r->getRedirectUrl());
        $this->assertSame(0, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
    }

    public function testKerusakanTiketAktifTetapTersimpanDanMencetak(): void
    {
        $id = $this->seedService(['status_service' => 1]);
        $this->sesi(['idservice' => $id]);

        $r = $this->post('service/saveKerusakan', [
            'idservice_k' => $id,
            'fungsi'      => [1],
            'keterangan'  => [1 => 'retak'],
        ]);

        $r->assertStatus(302);
        $this->assertStringContainsString('cetak/invoice_service/' . $id, (string) $r->getRedirectUrl());
        $this->assertSame(1, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
    }

    public function testSparepartDitolakSaatTiketSelesai(): void
    {
        $id = $this->seedService(['status_service' => 4, 'garansi_hari' => 7]);
        $this->sesi(['idservice' => $id]);

        $r = $this->post('service/saveSparepart', [
            'idservice_s' => $id,
            'garansi'     => '0',
        ]);

        $r->assertStatus(302);
        $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(7, (int) $row->garansi_hari);
    }

    public function testGuardInsertServiceMenawarkanServiceBaru(): void
    {
        $id = $this->seedService(['status_service' => 2, 'no_service' => 'SRV-AKTIF']);
        $this->sesi(['idservice' => $id]);

        $this->post('insert/pelanggan_service', [
            'selectedidpelanggan' => 10,
        ])->assertStatus(302);

        $this->assertStringContainsString('Service Baru', $this->flashGagal());
        $this->assertStringContainsString('service/clear_session', $this->flashGagal());
        $this->assertSame(1, (int) $this->koneksiFresh()->table('service')->countAllResults());
    }
}
