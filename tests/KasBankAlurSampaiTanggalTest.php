<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSetorTarikService;
use App\Services\Finance\KasBankSourceMovement;
use App\Services\Finance\KasOpeningService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Alur KAS sampai tanggal tertentu.
 *
 *     Opening (cut-off) + Cash In - Cash Out ± Setor/Tarik = Saldo Buku
 *
 * Yang dijaga di sini:
 *
 *   1. Tanggal cut-off menghasilkan movement 0.
 *   2. Batas ATAS benar-benar membatasi: transaksi setelah tanggal yang
 *      dipilih tidak ikut terhitung.
 *   3. Arah Setor (KAS keluar) dan Tarik (KAS masuk).
 *   4. Opening adalah baseline, bukan movement — tidak masuk ke Cash In /
 *      Cash Out / Setor / Tarik, dan tidak pernah dihitung dua kali.
 *   5. Cash In / Cash Out memakai sumber & filter yang sama dengan definisi
 *      Tutup Kasir, jadi angkanya tidak pernah berbeda dari Tutup Kasir.
 *   6. Pemanggil lama (tanpa tanggal) tetap berarti "sampai sekarang".
 *
 * Bug yang dicegah: `saldoFisik($akun, $tanggal)` pernah meneruskan
 * $tanggal hanya ke opening sementara movement tetap dihitung tanpa batas
 * atas. Semua tanggal mengembalikan saldo yang sama, dan saldo itu ikut
 * berubah setiap kali ada transaksi baru — termasuk transaksi yang
 * seharusnya sudah di luar rentang yang ditanyakan.
 */
class KasBankAlurSampaiTanggalTest extends CIUnitTestCase
{
    protected $db;
    protected KasBankCutoffService $cutoff;
    protected KasBankSetorTarikService $pindah;
    protected KasOpeningService $opening;

    /** Rekening: 1 = Bank Bersama (shared), 2 = KAS Unit 1. */
    private const AKUN_KAS  = 2;
    private const AKUN_BANK = 1;
    private const UNIT      = 1;
    private const OPENING   = 1500000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->schema();
        $this->seed();

        $_SESSION['ID_AKUN']    = 43;
        $_SESSION['ID_UNIT']    = 1;
        $_SESSION['ID_JABATAN'] = 1;

        $this->cutoff  = new KasBankCutoffService();
        $this->pindah  = new KasBankSetorTarikService();
        $this->opening = new KasOpeningService();
    }

    private function q(string $sql): void
    {
        $this->db->query($sql);
    }

    private function schema(): void
    {
        foreach ([
            'db_transaksi_kas_bank', 'db_alokasi_saldo_kas_bank', 'db_saldo_awal_kas_bank',
            'db_akun_kas_bank', 'db_tutup_kasir', 'db_unit', 'db_bank', 'db_akun',
            'db_opening_kas', 'db_penjualan', 'db_kas_keluar',
        ] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        $this->q('CREATE TABLE db_akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $this->q('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->q('CREATE TABLE db_bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, norek TEXT NULL, atas_nama TEXT NULL)');
        $this->q('CREATE TABLE db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0)');
        $this->q('CREATE TABLE db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL,
                sumber_tipe TEXT NULL, sumber_id INT NULL, keterangan TEXT NULL,
                bukti TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_tutup_kasir (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit INT NULL, tanggal TEXT NULL, akhir_cash REAL NULL)');
        $this->q('ALTER TABLE db_transaksi_kas_bank ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
        // Kolom dan indeks mengikuti migration
        // 2026-10-04-000700_OpeningKasDenganVerifikasiRealCash.
        $this->q('CREATE TABLE db_opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT DEFAULT 0 NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
                status VARCHAR(32) DEFAULT \'BELUM_VERIFIKASI\' NULL, keterangan VARCHAR(255) NULL,
                input_by INT NULL, verifikasi_by INT NULL, verifikasi_at TEXT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE UNIQUE INDEX uniq_opening_kas_akun_tanggal ON db_opening_kas (akun_kas_bank_id, tanggal)');

        // Tabel sumber. Kolom dan filter di sini harus tetap sama dengan
        // TutupKasirSourceDefinition: penjualan `tanggal`, service
        // `tanggal_selesai` + `status_service = 4`, kas_keluar `idbank IS NULL`
        // untuk yang tunai.
        $this->q('CREATE TABLE db_penjualan (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode_invoice TEXT NULL, unit_idunit INT NULL, tanggal TEXT NULL,
                harus_dibayar REAL NULL, bayar_tunai REAL NULL, bayar_bank REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE db_service (
                id_service INTEGER PRIMARY KEY AUTO_INCREMENT,
                no_service TEXT NULL, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
                status_service INT NULL, harus_dibayar REAL NULL, bayar_tunai REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE db_kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, idunit INT NULL, idbank TEXT NULL,
                jumlah REAL NULL, deskripsi TEXT NULL, updated_on TEXT NULL)');
    }

    private function seed(): void
    {
        $this->q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1,'Probolinggo'), (2,'Jember')");
        $this->q("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]')");
        $this->q("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BNI-001','Bank BNI','123','PT Contoh'), ('1','Bank Lama','999','PT Contoh')");
        $this->q("INSERT INTO db_akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (1, NULL, 'BANK', 'Bank Bersama',    'BNI-001', 'aktif', 1, 0),
            (2, 1,    'KAS',  'Kas Probolinggo', NULL,       'aktif', 0, 0)");

        // Statement bank terverifikasi + entitlement, supaya Setor/Tarik lolos guard.
        $cutoff = FinanceScopeService::cutoffDate();
        $this->q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (1, '{$cutoff}', 100000000, 'Koran cut-off', 'VERIFIED')");
        $this->q("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan, created_at) VALUES
            (1, 1, 60000000, 'Unit 1', '" . date('Y-m-d H:i:s') . "')");

        // Opening KAS + Tutup Kasir di tanggal cut-off.
        $this->opening->inputOpening(self::AKUN_KAS, self::OPENING, 'test-alur', 1);
        $this->q("INSERT INTO db_tutup_kasir (unit, tanggal, akhir_cash) VALUES (1, '{$cutoff}', " . self::OPENING . ')');
        $this->opening->verifikasi(self::AKUN_KAS, 1);
    }

    private function jual(string $invoice, string $tanggal, int $tunai, int $bank = 0): void
    {
        $this->db->table('penjualan')->insert([
            'kode_invoice' => $invoice, 'unit_idunit' => self::UNIT,
            'tanggal' => $tanggal . ' 09:00:00',
            'harus_dibayar' => $tunai + $bank, 'bayar_tunai' => $tunai, 'bayar_bank' => $bank,
            'keterangan' => 'test-alur',
        ]);
    }

    // =================================================================
    // 1. Tanggal cut-off
    // =================================================================

    public function testTanggalCutoffMovementNol(): void
    {
        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, FinanceScopeService::cutoffDate());

        $this->assertTrue($alur['opening_ada']);
        $this->assertSame(self::OPENING, $alur['opening']);
        $this->assertSame(0, $alur['movement'], 'Tidak ada movement yang boleh dihitung pada tanggal cut-off');
        $this->assertSame(self::OPENING, $alur['saldo_buku']);
        $this->assertSame(0, $this->cutoff->netMovement(self::AKUN_KAS, null, FinanceScopeService::cutoffDate()));
    }

    public function testTanggalSebelumCutoffMovementNol(): void
    {
        // Tanggal di bawah batas bawah periode: harus 0, bukan negatif atau error.
        $this->assertSame(0, $this->cutoff->netMovement(self::AKUN_KAS, null, '2026-09-30'));
    }

    // =================================================================
    // 2 & 3. Batas atas
    // =================================================================

    public function testEnamOktHanyaMovementEnamOkt(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->jual('INV-7', '2026-10-07', 100000);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(300000, $alur['cash_in']);
        $this->assertSame(0, $alur['cash_out']);
        $this->assertSame(0, $alur['transfer_keluar']);
        $this->assertSame(300000, $alur['movement']);
        $this->assertSame(self::OPENING + 300000, $alur['saldo_buku']);
    }

    public function testTujuhOktMengakumulasiEnamDanTujuhOkt(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->jual('INV-7', '2026-10-07', 100000);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-07');

        $this->assertSame(400000, $alur['cash_in'], 'Cash In kumulatif 6 + 7 Okt');
        $this->assertSame(400000, $alur['movement']);
        $this->assertSame(self::OPENING + 400000, $alur['saldo_buku']);
    }

    public function testTransaksiDelapanOktTidakMemengaruhiTujuhOkt(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->jual('INV-7', '2026-10-07', 100000);
        $sebelum = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-07');

        // Transaksi 8 Okt sengaja jauh lebih besar supaya kebocoran jelas.
        $this->jual('INV-8', '2026-10-08', 700000);
        $sesudah = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-07');

        $this->assertSame($sebelum['cash_in'], $sesudah['cash_in']);
        $this->assertSame($sebelum['movement'], $sesudah['movement']);
        $this->assertSame(400000, $sesudah['movement']);

        $this->assertSame(
            1100000,
            $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-08')['cash_in'],
            'Angka 8 Okt sendiri harus tetap benar'
        );

        // Guard lama: saldoFisik() yang meneruskan tanggal harus ikut
        // menghormati batas atas, bukan hanya opening.
        $this->assertSame(self::OPENING + 400000, $this->cutoff->saldoFisik(self::AKUN_KAS, '2026-10-07'));
    }

    // =================================================================
    // 4. Arah Setor / Tarik
    // =================================================================

    public function testSetorMengurangiSaldoKas(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $r = $this->pindah->setorTunai(self::UNIT, self::AKUN_KAS, self::AKUN_BANK, 200000, '2026-10-06', 'k-setor');
        $this->assertSame('inserted', $r['status'], $r['alasan']);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(200000, $alur['transfer_keluar'], 'Setor dari KAS = KAS keluar');
        $this->assertSame(0, $alur['transfer_masuk']);
        $this->assertSame(100000, $alur['movement'], '300.000 cash in - 200.000 setor');
        $this->assertSame(self::OPENING + 100000, $alur['saldo_buku']);
    }

    public function testTarikMenambahSaldoKas(): void
    {
        $r = $this->pindah->tarikTunai(self::UNIT, self::AKUN_KAS, self::AKUN_BANK, 50000, '2026-10-06', 'k-tarik');
        $this->assertSame('inserted', $r['status'], $r['alasan']);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(50000, $alur['transfer_masuk'], 'Tarik ke KAS = KAS masuk');
        $this->assertSame(0, $alur['transfer_keluar']);
        $this->assertSame(50000, $alur['movement']);
        $this->assertSame(self::OPENING + 50000, $alur['saldo_buku']);
    }

    public function testSetorDanTarikTerpisahPerTanggal(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->pindah->setorTunai(self::UNIT, self::AKUN_KAS, self::AKUN_BANK, 200000, '2026-10-07', 'k-setor-7');
        $this->pindah->tarikTunai(self::UNIT, self::AKUN_KAS, self::AKUN_BANK, 50000, '2026-10-08', 'k-tarik-8');

        $enam  = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');
        $tujuh = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-07');
        $delapan = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-08');

        $this->assertSame(0, $enam['transfer_keluar']);
        $this->assertSame(200000, $tujuh['transfer_keluar']);
        $this->assertSame(200000, $delapan['transfer_keluar']);
        $this->assertSame(50000, $delapan['transfer_masuk'], 'Tarik 8 Okt = KAS masuk 50.000');

        $this->assertSame(300000, $enam['movement']);
        $this->assertSame(100000, $tujuh['movement'], '300.000 - 200.000 setor');
        $this->assertSame(150000, $delapan['movement'], '+ 50.000 tarik');
    }

    // =================================================================
    // 5. Opening bukan movement
    // =================================================================

    public function testOpeningTidakMasukMovement(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        // Opening ada di tabel, tapi tidak muncul di satu pun komponen movement.
        $this->assertSame(1, (int) $this->db->table('opening_kas')->countAllResults());
        $this->assertSame(300000, $alur['movement']);
        $this->assertSame(self::OPENING, $alur['opening']);
        $this->assertSame(self::OPENING + $alur['movement'], $alur['saldo_buku']);

        // Opening juga bukan movement di ledger, sehingga tidak akan terhitung
        // dua kali lewat transfer internal.
        $this->assertSame(0, $this->cutoff->netMovement(self::AKUN_KAS));
    }

    // =================================================================
    // 6. Parity sumber dengan definisi Tutup Kasir
    // =================================================================

    public function testCashOutHanyaKasKeluarTunai(): void
    {
        // Kas keluar tunai (idbank NULL) mengurangi laci; yang beridbank
        // adalah mutasi ke bank dan TIDAK boleh ikut mengurangi KAS.
        $this->db->table('kas_keluar')->insert([
            'tanggal' => '2026-10-06 10:00:00', 'idunit' => self::UNIT,
            'idbank' => null, 'jumlah' => 50000, 'deskripsi' => 'test-alur',
        ]);
        $this->db->table('kas_keluar')->insert([
            'tanggal' => '2026-10-06 11:00:00', 'idunit' => self::UNIT,
            'idbank' => '1', 'jumlah' => 90000, 'deskripsi' => 'test-alur',
        ]);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(50000, $alur['cash_out'], 'Hanya kas_keluar tunai yang mengurangi KAS');
        $this->assertSame(-50000, $alur['movement']);
    }

    public function testServiceHarusStatusSelesai(): void
    {
        // Hanya service selesai (status 4) yang ikut Cash In, sama seperti
        // Tutup Kasir.
        $this->db->table('service')->insert([
            'no_service' => 'S-1', 'unit_idunit' => self::UNIT,
            'tanggal_selesai' => '2026-10-06 09:00:00', 'status_service' => 4,
            'harus_dibayar' => 400000, 'bayar_tunai' => 120000, 'keterangan' => 'test-alur',
        ]);
        $this->db->table('service')->insert([
            'no_service' => 'S-2', 'unit_idunit' => self::UNIT,
            'tanggal_selesai' => '2026-10-06 10:00:00', 'status_service' => 2,
            'harus_dibayar' => 700000, 'bayar_tunai' => 300000, 'keterangan' => 'test-alur',
        ]);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(120000, $alur['cash_in'], 'Service status != 4 tidak boleh masuk Cash In');
    }

    public function testInvoiceSvcTidakMasukCashIn(): void
    {
        // Penjualan berkode srv/after bukan penjualan biasa di Tutup Kasir.
        $this->jual('srv-2026-10-06', '2026-10-06', 900000);

        $alur = $this->cutoff->alurKasSampai(self::AKUN_KAS, '2026-10-06');

        $this->assertSame(0, $alur['cash_in']);
    }

    // =================================================================
    // 7. Pemanggil lama tidak berubah arti
    // =================================================================

    public function testTanpaBatasAtasTetapSampaiSekarang(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->jual('INV-7', '2026-10-07', 100000);

        $this->assertSame(400000, $this->cutoff->netMovement(self::AKUN_KAS));
        $this->assertSame(self::OPENING + 400000, $this->cutoff->saldoFisik(self::AKUN_KAS));
    }

    public function testRincianMovementSamaDenganNetMovement(): void
    {
        $this->jual('INV-6', '2026-10-06', 300000);
        $this->db->table('kas_keluar')->insert([
            'tanggal' => '2026-10-07 10:00:00', 'idunit' => self::UNIT,
            'idbank' => null, 'jumlah' => 70000, 'deskripsi' => 'test-alur',
        ]);
        $this->pindah->setorTunai(self::UNIT, self::AKUN_KAS, self::AKUN_BANK, 120000, '2026-10-07', 'k-setor-x');

        $mv = new KasBankSourceMovement();

        $this->assertSame(
            $mv->rincianMovement(self::AKUN_KAS)['net'],
            $this->cutoff->netMovement(self::AKUN_KAS),
            'Rincian hanya memecah angka, bukan menghitung ulang'
        );

        $rincian = $mv->rincianMovement(self::AKUN_KAS, null, null, '2026-10-06');
        $this->assertSame(300000, $rincian['cash_in']);
        $this->assertSame(0, $rincian['transfer_keluar'], 'Setor 7 Okt belum masuk rentang sampai 6 Okt');
    }
}