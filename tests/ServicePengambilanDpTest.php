<?php

/**
 * Feature test pengambilan service (`service/sudah_diambil`) terkait DP.
 *
 * Menguji Tahap 2.1:
 *  - jurnal pengambilan SEIMBANG (pendapatan sekali, PDM dilepas sekali);
 *  - sumber saldo DP: ledger baru (`service_dp`), historis (`service.dp_bayar`),
 *    atau tanpa DP;
 *  - DP intake TIDAK dihitung ulang sebagai kas (tidak dobel);
 *  - idempotensi: service berstatus 4 tidak dijurnal ulang;
 *  - kegagalan jurnal -> rollback (status tetap 3).
 *
 * Mengikuti pola ServiceCancelDpTest: tabel prefix `db_` (group 'tests'),
 * verifikasi lewat koneksi fresh karena AuthFilter menutup koneksi shared
 * setelah tiap request. Test berjalan di database terisolasi (erp_test).
 */

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServicePengambilanDpTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_service',
        'db_service_dp',
        'db_service_kerusakan',
        'db_service_sparepart',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_akun',
        'db_jabatan',
        'db_jurnal',
        'db_template_jurnal',
        'db_pembayaran_bank',
        'db_bank',
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

        foreach (self::TABEL as $t) {
            $q("DROP TABLE IF EXISTS {$t}");
        }

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            status_service INT NOT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            total_service INT NULL DEFAULT 0,
            harus_dibayar INT NULL DEFAULT 0,
            bayar_tunai INT NULL DEFAULT 0,
            dp_bayar INT NULL DEFAULT 0,
            created_at DATETIME NULL,
            tanggal_selesai DATETIME NULL,
            tanggal_bisa_diambil DATE NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            kode_dp VARCHAR(64) NULL,
            tanggal DATE NULL,
            jumlah DOUBLE NOT NULL DEFAULT 0,
            metode VARCHAR(20) NULL,
            bank_idbank VARCHAR(20) NULL,
            referensi VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT "aktif",
            keterangan TEXT NULL,
            unit_id INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_kerusakan (
            idservice_kerusakan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            PRIMARY KEY (idservice_kerusakan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_barang (
            id_unit INT NULL,
            stok_akhir INT NULL DEFAULT 0,
            stok_minimum INT NULL DEFAULT 0
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_akun (
            ID_AKUN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ID_JABATAN INT NULL,
            ID_UNIT INT NULL DEFAULT 0,
            NAMA_AKUN VARCHAR(100) NULL,
            PRIMARY KEY (ID_AKUN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jabatan (
            ID_JABATAN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_JABATAN VARCHAR(150) NULL,
            ROLES_JABATAN TEXT NULL,
            PRIMARY KEY (ID_JABATAN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jurnal (
            idjurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tanggal DATE NULL,
            no_akun VARCHAR(64) NULL,
            nama_akun VARCHAR(150) NULL,
            debet INT NULL DEFAULT 0,
            kredit INT NULL DEFAULT 0,
            keterangan TEXT NULL,
            id_referensi VARCHAR(64) NULL,
            tabel_referensi VARCHAR(64) NULL,
            id_unit INT NULL,
            id_akun INT NULL,
            PRIMARY KEY (idjurnal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_template_jurnal (
            idtemplate_jurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_template VARCHAR(64) NULL,
            no_akun VARCHAR(64) NULL,
            nama_akun VARCHAR(150) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            PRIMARY KEY (idtemplate_jurnal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pembayaran_bank (
            idpembayaran_bank INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_pembayaran VARCHAR(64) NULL,
            jumlah INT NULL DEFAULT 0,
            bank_idbank VARCHAR(20) NULL,
            tabel_referensi VARCHAR(64) NULL,
            id_referensi VARCHAR(64) NULL,
            PRIMARY KEY (idpembayaran_bank)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_bank (
            idbank VARCHAR(20) NOT NULL,
            jenis_bank VARCHAR(20) NULL,
            nama_bank VARCHAR(150) NULL,
            PRIMARY KEY (idbank)
        ) ENGINE=InnoDB');

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");

        // Template jurnal pelunasan jasa service: Dr Kas, Dr PDM, Cr Pendapatan.
        $q("INSERT INTO db_template_jurnal (kode_template, no_akun, nama_akun, debet_kredit, array_value) VALUES
            ('pembayaran_service_tunai', '1010101000', 'Kas Besar', 'debet', 0),
            ('pembayaran_service_tunai', '2010302000', 'Pendapatan Diterima di Muka', 'debet', 1),
            ('pembayaran_service_tunai', '4010201000', 'Pendapatan Jasa', 'kredit', 2),
            ('pembayaran_service_bank', '1010102000', 'Kas di Bank', 'debet', 0),
            ('pembayaran_service_bank', '2010302000', 'Pendapatan Diterima di Muka', 'debet', 1),
            ('pembayaran_service_bank', '4010201000', 'Pendapatan Jasa', 'kredit', 2)");
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 1,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function seedService(array $overrides = []): int
    {
        $this->db->table('service')->insert(array_merge([
            'no_service'      => 'SRV-' . uniqid(),
            'status_service'  => 3,
            'created_at'      => '2026-01-01 08:00:00',
            'tanggal_bisa_diambil' => '2026-01-02',
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function seedDp(int $idservice, int $jumlah, string $metode = 'tunai', string $status = 'aktif'): void
    {
        $this->db->table('service_dp')->insert([
            'service_idservice' => $idservice,
            'kode_dp'           => 'DP' . uniqid(),
            'jumlah'            => $jumlah,
            'metode'            => $metode,
            'status'            => $status,
        ]);
    }

    private function seedBankPaid(int $idservice, int $jumlah): void
    {
        $this->db->table('pembayaran_bank')->insert([
            'kode_pembayaran' => 'PB' . uniqid(),
            'jumlah'          => $jumlah,
            'bank_idbank'     => '2',
            'tabel_referensi' => 'service_baru',
            'id_referensi'    => $idservice,
        ]);
    }

    private function jurnalService(int $idservice): array
    {
        $db = $this->koneksiFresh();

        return $db->table('jurnal')
            ->where('tabel_referensi', 'service')
            ->where('id_referensi', (string) $idservice)
            ->orderBy('idjurnal', 'ASC')
            ->get()->getResultArray();
    }

    private function jumlahDenganAkun(array $jurnal, string $akun, string $kolom): int
    {
        $total = 0;
        foreach ($jurnal as $row) {
            if ((string) $row['no_akun'] === $akun) {
                $total += (int) $row[$kolom];
            }
        }

        return $total;
    }

    public function testPengambilanTanpaDpSeimbang(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 100000]);

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);
        $debet  = $this->jumlahDenganAkun($jurnal, '1010101000', 'debet');

        $this->assertSame(100000, $debet, 'Kas Besar didebet sebesar bayar tunai.');
        $this->assertSame(0, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'Tanpa DP tidak ada pelepasan PDM.');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'), 'Pendapatan diakui sekali.');

        $db = $this->koneksiFresh();
        $this->assertSame(4, (int) $db->table('service')->where('idservice', $id)->get()->getRow()->status_service);
    }

    public function testPengambilanDpLedgerTidakMenambahKasGanda(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 70000]);
        $this->seedDp($id, 30000, 'tunai');

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);

        $this->assertSame(70000, $this->jumlahDenganAkun($jurnal, '1010101000', 'debet'), 'DP intake TIDAK boleh dihitung lagi sebagai kas.');
        $this->assertSame(30000, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'PDM dilepas tepat sebesar DP terpakai.');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'), 'Pendapatan total = tunai + DP.');
    }

    public function testPengambilanDpHistorisTanpaLedger(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 75000, 'dp_bayar' => 25000]);
        // Tidak ada baris service_dp -> sumber saldo = kolom historis dp_bayar.

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);

        $this->assertSame(75000, $this->jumlahDenganAkun($jurnal, '1010101000', 'debet'));
        $this->assertSame(25000, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'DP historis dari service.dp_bayar tetap dilepas.');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
    }

    public function testPengambilanDpLedgerNonAktifTidakFallbackKeKolomHistoris(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 150000, 'bayar_tunai' => 100000, 'dp_bayar' => 50000]);
        // Ledger ada tetapi tidak aktif (DP dibatalkan) -> saldo 0, JANGAN pakai dp_bayar.
        $this->seedDp($id, 50000, 'tunai', 'dibatalkan');

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);

        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '1010101000', 'debet'));
        $this->assertSame(0, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'Ledger non-aktif tidak boleh di-resolve ke kolom historis.');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
    }

    public function testPengambilanBankMenulisJurnalBank(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 0]);
        $this->seedBankPaid($id, 100000);

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);

        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '1010102000', 'debet'), 'Pembayaran bank didebet ke Kas di Bank.');
        $this->assertSame(0, $this->jumlahDenganAkun($jurnal, '1010101000', 'debet'), 'Tidak ada kas tunai.');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
    }

    public function testPengambilanTunaiDanBankDenganDpPendapatanSekali(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 30000]);
        $this->seedBankPaid($id, 50000);
        $this->seedDp($id, 10000, 'tunai');
        $this->seedDp($id, 10000, 'transfer');

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $jurnal = $this->jurnalService($id);

        $this->assertSame(30000, $this->jumlahDenganAkun($jurnal, '1010101000', 'debet'));
        $this->assertSame(50000, $this->jumlahDenganAkun($jurnal, '1010102000', 'debet'));
        $this->assertSame(20000, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'PDM dilepas sekali (10k tunai + 10k transfer).');
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'), 'Pendapatan total = 30k + 50k + 20k, bukan dobel.');
    }

    public function testPengambilanKeduaTidakMenggandakanJurnal(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 100000]);

        $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);
        $jurnalSetelahPertama = $this->jurnalService($id);
        $this->assertCount(3, $jurnalSetelahPertama, 'Dr Kas, Dr PDM(0), Cr Pendapatan.');

        // Request kedua: status sudah 4 -> ditolak, tidak menambah jurnal.
        $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);

        $this->assertCount(3, $this->jurnalService($id), 'Pengambilan kedua tidak boleh menggandakan jurnal.');
    }

    public function testTemplateJurnalHilangMembatalkanStatus(): void
    {
        $this->sesi();
        $id = $this->seedService(['total_service' => 100000, 'bayar_tunai' => 100000]);

        // Hapus template tunai -> jurnal tidak terbentuk -> harus rollback.
        $this->db->table('template_jurnal')->where('kode_template', 'pembayaran_service_tunai')->delete();

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $status = (int) $db->table('service')->where('idservice', $id)->get()->getRow()->status_service;

        $this->assertSame(3, $status, 'Rollback harus mengembalikan status ke 3.');
        $this->assertCount(0, $this->jurnalService($id), 'Tidak ada jurnal parsial yang tersisa.');
    }

    public function testStatusSudahEmpatTidakDiubah(): void
    {
        $this->sesi();
        $id = $this->seedService(['status_service' => 4, 'total_service' => 100000, 'bayar_tunai' => 100000]);

        $r = $this->post('service/sudah_diambil', ['idservice' => $id]);
        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $this->assertSame(4, (int) $db->table('service')->where('idservice', $id)->get()->getRow()->status_service);
        $this->assertCount(0, $this->jurnalService($id), 'Service berstatus 4 tidak dijurnal ulang.');
    }

    /**
     * Jalur duplikasi nyata: service diambil (jurnal #1), lalu "Dibatalkan"
     * (status 90) dan "Aktifkan Kembali" (status 2) tanpa membalik jurnal,
     * lalu diambil lagi. Status bukan lagi 4 sehingga guard status saja tidak
     * menahan, dan jurnal pengambilan sempat tergandakan. Idempotensi berbasis
     * jurnal harus menahannya dan tetap menetapkan status akhir 4.
     */
    public function testPengambilanUlangSetelahAktifKembaliTidakMenggandakanJurnal(): void
    {
        $this->sesi();
        $id = $this->seedService(['status_service' => 2, 'total_service' => 100000, 'bayar_tunai' => 100000]);

        $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);
        $this->assertCount(3, $this->jurnalService($id), 'Pengambilan pertama menulis 3 baris jurnal.');

        // Simulasi dibatalkan (status 90) lalu diaktifkan kembali (status 2),
        // tanpa menyentuh jurnal — persis perilaku controller saat ini.
        $this->db->table('service')->where('idservice', $id)->update(['status_service' => 2]);

        $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);

        $this->assertCount(3, $this->jurnalService($id), 'Pengambilan ulang tidak boleh menambah jurnal.');
        $db = $this->koneksiFresh();
        $this->assertSame(4, (int) $db->table('service')->where('idservice', $id)->get()->getRow()->status_service, 'Status akhir tetap 4.');
    }
}
