<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSourceMovement;
use App\Services\Finance\KasOpeningService;
use App\Services\Finance\TutupKasirSaldoAwal;
use App\Services\Finance\TutupKasirSourceDefinition;
use App\Services\Finance\TutupKasirTransferInternal;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Saldo awal Tutup Kasir = saldo kas riil yang diteruskan.
 *
 * Yang dijaga di sini:
 *
 *   1. Saldo awal HARI PERTAMA berasal dari baseline `opening_kas`.
 *   2. Saldo awal HARI BERIKUTNYA berasal dari `tutup_kasir.akhir_cash`
 *      sebelumnya (carry-forward), BUKAN opening lagi.
 *   3. Closing diambil DETERMINISTIK (`ORDER BY tanggal DESC, id DESC LIMIT 1`),
 *      jadi closing ganda tidak membuat saldo awal berganti-ganti.
 *   4. TIDAK ada fallback apa pun kalau baseline belum ada - bukan 0, bukan
 *      Rp1.000.000, bukan `kas_masuk`, bukan saldo lama.
 *   5. `kas_masuk deskripsi='kas awal'` sama sekali tidak dibaca.
 *   6. Setor mengurangi saldo KAS, Tarik menambahnya.
 *   7. Legacy mirror `transaksi_kas_bank` dengan `transfer_ref IS NULL` tidak
 *      pernah ikut terhitung.
 *   8. Saldo > Rp1 juta diteruskan utuh - tidak dipotong, tidak dialihkan
 *      ke bank.
 *   9. `cash_laci` tetap input fisik; `selisih = cash_laci - akhir_cash`.
 *  10. Sumber Kas Masuk / Kas Keluar tidak berubah.
 *
 * Bug yang dicegah:
 *   - `tutup()` pernah membatasi laci di Rp1.000.000 dan memakai kelebihannya
 *     untuk menaikkan saldo bank tanpa record Setor. Di data lama unit 2
 *     menutup dengan 2.221.000 lalu opening besok menjadi 1.000.000.
 *   - Opening pernah diambil dari `kas_masuk` baris `deskripsi='kas awal'`
 *     memakai `getRow()` tanpa ordering, sehingga saat ada closing ganda atau
 *     hari tanpa tutup, saldo awal bisa 0 atau baris acak.
 */
class TutupKasirSaldoAwalTest extends CIUnitTestCase
{
    protected $db;
    protected TutupKasirSaldoAwal $saldoAwal;
    protected TutupKasirTransferInternal $transfer;
    protected KasOpeningService $opening;

    private const AKUN_KAS   = 2;  // KAS unit 1
    private const AKUN_KAS2  = 3;  // KAS unit 2
    private const AKUN_BANK  = 1;  // BANK bersama
    private const UNIT       = 1;
    private const UNIT2      = 2;
    private const OPENING    = 1500000;

    /** Tanggal "hari ini" pada test, dipisah supaya test tidak ikut bergeser. */
    private string $hariIni;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Fail-closed: test ini menghapus dan membuat ulang tabel, jadi hanya
        // boleh jalan terhadap `erp_finance_test`. Menunjuk DB lain = stop.
        if ($this->db->getDatabase() !== 'erp_finance_test') {
            $this->fail('Test hanya boleh jalan pada database erp_finance_test, bukan: '
                . $this->db->getDatabase());
        }

        $this->hariIni = '2026-10-10';

        // Service dibuat lebih dulu karena seed() butuh KasOpeningService.
        $this->saldoAwal = new TutupKasirSaldoAwal($this->db);
        $this->transfer  = new TutupKasirTransferInternal($this->db);
        $this->opening   = new KasOpeningService();

        $this->schema();
        $this->seed();
    }

    private function q(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            $this->fail('Query gagal: ' . $this->db->error()['message']);
        }
    }

    private function schema(): void
    {
        // Tabel-tabel ini direferensikan tabel lain di schema asli, jadi FK
        // check dimatikan sebentar. Database dikembalikan utuh setelah test
        // dari backup /tmp/opencode/erp_audit/erp_finance_test_BEFORE.sql.
        $this->q('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'transaksi_kas_bank', 'alokasi_saldo_kas_bank', 'saldo_awal_kas_bank',
            'akun_kas_bank', 'tutup_kasir', 'unit', 'bank', 'akun',
            'opening_kas', 'penjualan', 'service', 'kas_keluar', 'kas_masuk',
        ] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        $this->q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $this->q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->q('CREATE TABLE bank (
                idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL,
                norek TEXT NULL, atas_nama TEXT NULL)');
        $this->q('CREATE TABLE akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0)');
        $this->q('CREATE TABLE saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL,
                sumber_tipe TEXT NULL, sumber_id INT NULL, keterangan TEXT NULL,
                bukti TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('ALTER TABLE transaksi_kas_bank
                ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
        $this->q('CREATE TABLE alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        // Struktur mengikuti TutupKasir::tutup() yang sekarang: tidak ada lagi
        // baris carry-forward, dan `cash_laci` berisi hitungan fisik admin.
        $this->q('CREATE TABLE tutup_kasir (
                idtutupkasir INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, awal_cash REAL NULL, awal_transfer REAL NULL,
                akhir_cash REAL NULL, akhir_transfer REAL NULL,
                pendapatan_cash REAL NULL, pendapatan_transfer REAL NULL,
                pengeluaran_cash REAL NULL, pengeluaran_transfer REAL NULL,
                cash_laci REAL NULL, status TEXT NULL,
                akun_ID_AKUN INT NULL, unit INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');

        // Struktur mengikuti migration Opening KAS dengan verifikasi real cash.
        $this->q('CREATE TABLE opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT DEFAULT 0 NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
                status VARCHAR(32) DEFAULT \'BELUM_VERIFIKASI\' NULL,
                keterangan VARCHAR(255) NULL,
                input_by INT NULL, verifikasi_by INT NULL, verifikasi_at TEXT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE UNIQUE INDEX uniq_opening_kas_akun_tanggal
                ON opening_kas (akun_kas_bank_id, tanggal)');

        // Tabel legacy: TIDAK boleh dibaca sebagai sumber saldo awal.
        $this->q('CREATE TABLE kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                idunit INT NULL, idbank TEXT NULL, created_on TEXT NULL, updated_on TEXT NULL)');

        // Sumber operasional - filter WAJIB sama dengan TutupKasirSourceDefinition.
        $this->q('CREATE TABLE penjualan (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode_invoice TEXT NULL, unit_idunit INT NULL, tanggal TEXT NULL,
                harus_dibayar REAL NULL, bayar_tunai REAL NULL, bayar_bank REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE service (
                id_service INTEGER PRIMARY KEY AUTO_INCREMENT,
                no_service TEXT NULL, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
                status_service INT NULL, harus_dibayar REAL NULL, bayar_tunai REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, idunit INT NULL, idbank TEXT NULL,
                jumlah REAL NULL, deskripsi TEXT NULL, updated_on TEXT NULL)');

        $this->q('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function seed(): void
    {
        $this->q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1,'Probolinggo'), (2,'Jember')");
        $this->q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]')");
        $this->q("INSERT INTO bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BNI-001','Bank BNI','123','PT Contoh')");
        $this->q("INSERT INTO akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa,
                 status, is_shared, is_finance_ho) VALUES
            (1, NULL, 'BANK', 'Bank Bersama',    'BNI-001', NULL,         'aktif', 1, 0),
            (2, 1,    'KAS',  'Kas Probolinggo', NULL,       '1010101000', 'aktif', 0, 0),
            (3, 2,    'KAS',  'Kas Jember',      NULL,       '1010101000', 'aktif', 0, 0)");

        // Statement bank & opening KAS memakai cut-off KASBANK, bukan
        // cut-off Finance global — dua config memang terpisah.
        $cutoff = FinanceScopeService::kasBankCutoffDate();
        $this->q("INSERT INTO saldo_awal_kas_bank
                (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (1, '{$cutoff}', 100000000, 'Koran cut-off', 'VERIFIED')");
        $this->q("INSERT INTO alokasi_saldo_kas_bank
                (akun_kas_bank_id, unit_id, nominal, keterangan, created_at) VALUES
            (1, 1, 60000000, 'Unit 1', '" . date('Y-m-d H:i:s') . "')");

        // Baseline Opening KAS kedua unit - dipakai skenario "hari pertama".
        //
        // PENTING: `TutupKasirSaldoAwal` membatalkan konsep verifikasi —
        // baseline opening yang tersimpan langsung dipakai (tanpa status).
        // Kolom `status` tetap ada di skema test supaya fixture lama tetap
        // bisa berjalan apa adanya.
        $this->setOpening(self::AKUN_KAS, self::OPENING, 'TERVERIFIKASI');
        $this->setOpening(self::AKUN_KAS2, 900000, 'TERVERIFIKASI');
    }

    /**
     * Tulis baris `opening_kas`.
     *
     * Fixture murni: status kolom legacy di-set langsung supaya test meniru
     * baris produksi. Service yang diuji TIDAK membedakan status lagi.
     * Kalau closing cut-off ikut dibuat, closing itu yang menang sebagai
     * sumber saldo awal (closing diprioritaskan atas opening) dan skenario
     * "hari pertama" justru hancur.
     *
     * @param string $status BELUM_VERIFIKASI | TERVERIFIKASI | TIDAK_COCOK
     */
    private function setOpening(int $akunId, int $opening, string $status, ?int $realCash = null): void
    {
        $cutoff         = FinanceScopeService::kasBankCutoffDate();
        $terverifikasi  = $status === 'TERVERIFIKASI';
        $unit           = $this->unitDariAkun($akunId);
        $real           = $realCash ?? $opening;

        $realTxt = $terverifikasi ? (string) $real : 'NULL';
        $selTxt  = $terverifikasi ? '0' : 'NULL';

        $this->q("INSERT INTO opening_kas
                (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih,
                 status, input_by, created_at, updated_at)
            VALUES ({$akunId}, {$unit}, '{$cutoff}', {$opening}, {$realTxt}, {$selTxt},
                '{$status}', 1, '" . date('Y-m-d H:i:s') . "', '" . date('Y-m-d H:i:s') . "')
            ON DUPLICATE KEY UPDATE
                opening = {$opening}, status = '{$status}',
                real_cash = {$realTxt}, selisih = {$selTxt}");
    }

    private function unitDariAkun(int $akunId): int
    {
        return (int) ($this->db->table('akun_kas_bank')
            ->select('unit_id')
            ->where('idakun_kas_bank', $akunId)
            ->get()
            ->getRow()->unit_id ?? 0);
    }

    // =================================================================
    // helper
    // =================================================================

    private function tutup(string $tanggal, int $unit, int $awal, int $akhir, ?int $laci = null): int
    {
        $this->db->table('tutup_kasir')->insert([
            'tanggal' => $tanggal, 'unit' => $unit,
            'awal_cash' => $awal, 'akhir_cash' => $akhir,
            'akhir_transfer' => 0,
            'cash_laci' => $laci ?? $akhir,
            'status' => 'selesai', 'akun_ID_AKUN' => 43,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function jual(string $invoice, string $tanggal, int $tunai, int $bank = 0): void
    {
        $this->db->table('penjualan')->insert([
            'kode_invoice' => $invoice, 'unit_idunit' => self::UNIT,
            'tanggal' => $tanggal . ' 09:00:00',
            'harus_dibayar' => $tunai + $bank,
            'bayar_tunai' => $tunai, 'bayar_bank' => $bank,
            'keterangan' => 'test-saldo-awal',
        ]);
    }

    private function keluar(string $tanggal, int $jumlah, ?string $bank = null): void
    {
        $this->db->table('kas_keluar')->insert([
            'tanggal' => $tanggal . ' 10:00:00', 'idunit' => self::UNIT,
            'idbank' => $bank, 'jumlah' => $jumlah, 'deskripsi' => 'test-saldo-awal',
        ]);
    }

    /** Baris legacy `kas_masuk` yang dulu jadi sumber opening. */
    private function kasAwalLegacy(string $tanggal, int $unit, int $jumlah, ?string $bank = null): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => $tanggal, 'deskripsi' => 'kas awal',
            'jumlah' => $jumlah, 'idunit' => $unit, 'idbank' => $bank,
        ]);
    }

    /**
     * Baris transfer internal seperti yang ditulis KasBankSetorTarikService:
     * `jenis = TRANSFER_INTERNAL` + `transfer_ref` terisi.
     *
     * @param string $arah 'KELUAR' = setor (kas -> bank), 'MASUK' = tarik
     */
    private function transferInternal(
        string $tanggal,
        int $unit,
        int $akun,
        string $arah,
        int $jumlah,
        string $ref
    ): void {
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal' => $tanggal, 'unit_id' => $unit,
            'akun_kas_bank_id' => $akun,
            'jenis' => 'TRANSFER_INTERNAL', 'arah' => $arah,
            'jumlah' => $jumlah,
            'transfer_ref' => $ref,
            'submission_key' => 'k-' . $ref,
            'sumber_tipe' => 'SETOR_TARIK', 'sumber_id' => null,
            'keterangan' => 'test-saldo-awal',
        ]);
    }

    private function setor(string $tanggal, int $unit, int $akun, int $jumlah, string $ref): void
    {
        $this->transferInternal($tanggal, $unit, $akun, 'KELUAR', $jumlah, $ref);
    }

    private function tarik(string $tanggal, int $unit, int $akun, int $jumlah, string $ref): void
    {
        $this->transferInternal($tanggal, $unit, $akun, 'MASUK', $jumlah, $ref);
    }

    // =================================================================
    // 1 & 2. SUMBER SALDO AWAL
    // =================================================================

    public function testHariPertamaSaldoAwalDariOpeningKas(): void
    {
        // Hari pertama PERIODE BARU = periode mulai KasBank. Baseline cut-off
        // (Opening KAS tanggal cut-off) bertanggal <= hari ini, jadi sah
        // dipakai. Menutup kasir pada tanggal sebelum cut-off justru dilarang
        // memakai baseline yang masih di masa depan relatif ke closing tsb.
        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, $this->hariIni);

        $this->assertTrue($hasil['ada']);
        $this->assertSame(self::OPENING, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $hasil['sumber']);
        $this->assertSame(FinanceScopeService::kasBankCutoffDate(), $hasil['tanggal']);
    }

    public function testHariBerikutnyaSaldoAwalDariClosingSebelumnya(): void
    {
        $this->tutup('2026-10-08', self::UNIT, self::OPENING, 1700000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertTrue($hasil['ada']);
        $this->assertSame(1700000, $hasil['nilai'], 'Saldo akhir 8 Okt harus jadi saldo awal 9 Okt');
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
        $this->assertSame('2026-10-08', $hasil['tanggal']);
    }

    public function testOpeningKasTidakDipakaiLagiSetelahAdaClosing(): void
    {
        // Closing hari pertama periode baru (10 Okt). 11 Okt harus memakai
        // closing itu, bukan Opening KAS lagi.
        $this->tutup('2026-10-10', self::UNIT, self::OPENING, 999999);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-11');

        $this->assertSame(999999, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
        $this->assertNotSame(
            self::OPENING,
            $hasil['nilai'],
            'Opening periode tidak boleh menggantikan closing terakhir'
        );
    }

    public function testRantaiCarryForwardBeberapaHari(): void
    {
        // Carry-forward antar-hari DI DALAM periode baru (>= periode mulai).
        $this->tutup('2026-10-10', self::UNIT, self::OPENING, 1600000);
        $this->tutup('2026-10-11', self::UNIT, 1600000, 1750000);

        $tanggal12 = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-12');
        $tanggal13 = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-13');

        // Untuk 12 Okt, closing terakhir sebelumnya adalah 11 Okt (1.750.000).
        // Untuk 13 Okt juga 11 Okt, karena 12 Okt belum ditutup.
        $this->assertSame(1750000, $tanggal12['nilai']);
        $this->assertSame('2026-10-11', $tanggal12['tanggal']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $tanggal12['sumber']);
        $this->assertSame(1750000, $tanggal13['nilai']);
        $this->assertSame('2026-10-11', $tanggal13['tanggal']);
    }

    public function testClosingHariIniTidakDipakai(): void
    {
        $this->tutup('2026-10-09', self::UNIT, 100, 2220000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertSame(
            TutupKasirSaldoAwal::SUMBER_OPENING,
            $hasil['sumber'],
            'Closing pada tanggal yang sama bukan "sebelumnya"'
        );
    }

    // =================================================================
    // 3. DETERMINISTIK
    // =================================================================

    public function testClosingGandaDipilihSecaraDeterministik(): void
    {
        // Data lama punya closing ganda. Baris terakhir (id terbesar) yang
        // menang supaya angka tidak berubah-ubah antar request.
        $this->tutup('2026-10-08', self::UNIT, 0, 500000);
        $this->tutup('2026-10-08', self::UNIT, 0, 777000);
        $idTerakhir = $this->tutup('2026-10-08', self::UNIT, 0, 111000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertSame(111000, $hasil['nilai']);
        $this->assertSame($idTerakhir, $hasil['closing_id']);

        // Dipanggil ulang: hasilnya tetap sama.
        $ulang = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');
        $this->assertSame($hasil['nilai'], $ulang['nilai']);
        $this->assertSame($hasil['closing_id'], $ulang['closing_id']);
    }

    public function testClosingDipilihBerdasarkanTanggalTerakhir(): void
    {
        $this->tutup('2026-10-10', self::UNIT, 0, 100);
        $this->tutup('2026-10-11', self::UNIT, 0, 200);
        $this->tutup('2026-10-12', self::UNIT, 0, 300);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-13');

        $this->assertSame(300, $hasil['nilai'], 'Tanggal terbaru yang menang, bukan id terbesar');
    }

    public function testClosingDenganAkhirCashNullDiabaikan(): void
    {
        $this->tutup('2026-10-10', self::UNIT, 0, 500000);
        // Closing tanpa saldo bukan posisi kas yang bisa diteruskan.
        $this->db->table('tutup_kasir')->insert([
            'tanggal' => '2026-10-11', 'unit' => self::UNIT, 'status' => 'selesai',
        ]);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-12');

        $this->assertSame(500000, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
        $this->assertSame('2026-10-10', $hasil['tanggal']);
    }

    // =================================================================
    // 4 & 5. TIDAK ADA FALLBACK
    // =================================================================

    public function testTanpaClosingDanTanpaOpeningTidakAdaFallback(): void
    {
        $this->q('DELETE FROM opening_kas');

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-10');

        $this->assertFalse($hasil['ada'], 'Saldo awal belum boleh ada');
        $this->assertNull($hasil['nilai'], 'Nilai harus null, bukan 0');
        $this->assertNotSame(1000000, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_BELUM, $hasil['sumber']);
        $this->assertStringContainsString('belum ditetapkan', strtolower($hasil['pesan']));
    }

    public function testUnitTanpaRekeningKasTidakDiberiAngka(): void
    {
        $hasil = $this->saldoAwal->saldoAwalKas(999, '2026-10-10');

        $this->assertFalse($hasil['ada']);
        $this->assertNull($hasil['nilai']);
        $this->assertStringContainsString('rekening KAS', $hasil['pesan']);
    }

    public function testHariTanpaTutupTidakResetSaldoKeNol(): void
    {
        // Regression bug lama: unit 3 tidak menutup 3 Okt sehingga opening
        // 4 Okt jadi 0. Closing 2 Okt tetap jadi sumber walau 3 Okt kosong.
        $this->q('DELETE FROM opening_kas');
        $this->tutup('2026-10-02', self::UNIT, 0, 850000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-04');

        $this->assertTrue($hasil['ada']);
        $this->assertSame(
            850000,
            $hasil['nilai'],
            'Closing 2 Okt tetap jadi sumber walau 3 Okt tidak ditutup'
        );
    }

    public function testKasMasukKasAwalLegacyTidakDibaca(): void
    {
        // Baris legacy penanda masalah lama: nilainya 1.000.000.
        $this->kasAwalLegacy('2026-10-10', self::UNIT, 1000000);
        $this->kasAwalLegacy('2026-10-10', self::UNIT2, 1000000);

        $unit1 = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-10');
        $this->assertSame(self::OPENING, $unit1['nilai']);
        $this->assertNotSame(1000000, $unit1['nilai']);

        // Opening unit 2 dihapus -> harus "belum", bukan 1 juta.
        $this->q('DELETE FROM opening_kas WHERE akun_kas_bank_id = ' . self::AKUN_KAS2);
        $unit2 = $this->saldoAwal->saldoAwalKas(self::UNIT2, '2026-10-10');

        $this->assertFalse($unit2['ada'], 'Legacy kas_masuk tidak boleh jadi sumber opening');
        $this->assertNull($unit2['nilai']);
        $this->assertNotSame(1000000, $unit2['nilai']);
    }

    public function testKasAwalLegacyTidakMenggangguClosingYangAda(): void
    {
        $this->kasAwalLegacy('2026-10-11', self::UNIT, 1000000);
        $this->tutup('2026-10-10', self::UNIT, 0, 2340000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-11');

        $this->assertSame(2340000, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
    }

    // =================================================================
    // 2b. RESET KASBANK: closing ledger lama tidak menimpa baseline cutoff
    // =================================================================

    public function testClosingSebelumCutoffTidakJadiSumberKasPeriodeBaru(): void
    {
        // Kasus produksi: closing 8 Okt (Rp33.389.372) dipakai sebagai sumber
        // saldo awal 10 Okt walau cut-off KasBank sudah 9 Okt. Closing lama itu
        // harus gugur dan baseline cut-off (Opening KAS) yang dipakai.
        $this->tutup('2026-10-08', self::UNIT, self::OPENING, 3623500);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-10');

        $this->assertTrue($hasil['ada'], $hasil['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $hasil['sumber']);
        $this->assertSame(self::OPENING, $hasil['nilai'], 'Baseline Opening KAS cut-off yang dipakai, bukan closing 8 Okt');
        $this->assertSame(FinanceScopeService::kasBankCutoffDate(), $hasil['tanggal']);
    }

    public function testClosingPadaTanggalCutoffTidakJadiSumberPeriodeBaru(): void
    {
        // Closing tepat di tanggal cut-off pun dihitung dengan ledger lama,
        // jadi tetap tidak sah untuk periode baru.
        $this->tutup(FinanceScopeService::kasBankCutoffDate(), self::UNIT, 0, 444000);

        $hasil = $this->saldoAwal->saldoAwalKas(
            self::UNIT,
            FinanceScopeService::kasBankPeriodeMulaiDate()
        );

        $this->assertTrue($hasil['ada'], $hasil['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $hasil['sumber']);
        $this->assertSame(self::OPENING, $hasil['nilai']);
    }

    public function testClosingSebelumCutoffTidakJadiSumberTransferPeriodeBaru(): void
    {
        // Kasus produksi: closing 8 Okt mencatat akhir_transfer Rp29.765.872.
        // Untuk periode baru, baseline statement cut-off (alokasi unit)
        // yang harus menang, bukan angka transfer legacy itu.
        $this->db->table('tutup_kasir')->insert([
            'tanggal' => '2026-10-08', 'unit' => self::UNIT, 'status' => 'selesai',
            'awal_cash' => 0, 'akhir_cash' => 0,
            'akhir_transfer' => 29765872,
            'akun_ID_AKUN' => 43,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $hasil = $this->saldoAwal->saldoAwalTransfer(self::UNIT, '2026-10-10');

        $this->assertTrue($hasil['ada'], $hasil['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_ALOKASI, $hasil['sumber']);
        $this->assertSame(60000000, (int) $hasil['nilai'], 'Baseline statement cut-off (alokasi unit) yang dipakai');
    }

    public function testClosingHariPertamaPeriodeJadiSumberHariBerikutnya(): void
    {
        // Begitu ada closing DI DALAM periode baru, carry-forward normal jalan
        // lagi: closing 10 Okt menjadi sumber saldo awal 11 Okt.
        $this->tutup('2026-10-10', self::UNIT, self::OPENING, 1900000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-11');

        $this->assertTrue($hasil['ada'], $hasil['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
        $this->assertSame('2026-10-10', $hasil['tanggal']);
        $this->assertSame(1900000, $hasil['nilai']);
    }

    public function testClosingHariPertamaPeriodeJadiSumberTransferHariBerikutnya(): void
    {
        $this->db->table('tutup_kasir')->insert([
            'tanggal' => '2026-10-10', 'unit' => self::UNIT, 'status' => 'selesai',
            'awal_cash' => 0, 'akhir_cash' => 0,
            'akhir_transfer' => 12345678,
            'akun_ID_AKUN' => 43,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $hasil = $this->saldoAwal->saldoAwalTransfer(self::UNIT, '2026-10-11');

        $this->assertTrue($hasil['ada'], $hasil['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $hasil['sumber']);
        $this->assertSame('2026-10-10', $hasil['tanggal']);
        $this->assertSame(12345678, (int) $hasil['nilai']);
    }

    public function testSaldoNolSahTidakDikiraBelumDitetapkan(): void
    {
        $this->setOpening(self::AKUN_KAS, 0, 'TERVERIFIKASI');

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-10');

        $this->assertTrue($hasil['ada'], 'Laci kosong itu baseline yang sah');
        $this->assertSame(0, $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $hasil['sumber']);
    }

    // =================================================================
    // 6, 7. SETOR / TARIK
    // =================================================================

    public function testSetorMengurangiSaldoKas(): void
    {
        $this->setor($this->hariIni, self::UNIT, self::AKUN_KAS, 500000, 'st-1');

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertTrue($hasil['ada']);
        $this->assertSame(500000, $hasil['setor']);
        $this->assertSame(0, $hasil['tarik']);
    }

    public function testTarikMenambahSaldoKas(): void
    {
        $this->tarik($this->hariIni, self::UNIT, self::AKUN_KAS, 300000, 'tr-1');

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertSame(0, $hasil['setor']);
        $this->assertSame(300000, $hasil['tarik']);
    }

    public function testSetorDanTarikBersamaan(): void
    {
        $this->setor($this->hariIni, self::UNIT, self::AKUN_KAS, 500000, 'st-2');
        $this->tarik($this->hariIni, self::UNIT, self::AKUN_KAS, 200000, 'tr-2');

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertSame(500000, $hasil['setor']);
        $this->assertSame(200000, $hasil['tarik']);
    }

    public function testTanpaSetorTarikTidakAdaTransferInternal(): void
    {
        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertFalse($hasil['ada']);
        $this->assertSame(0, $hasil['setor']);
        $this->assertSame(0, $hasil['tarik']);
    }

    public function testLegacyMirrorTanpaTransferRefTidakDihitung(): void
    {
        // 12 baris legacy di DB: jenis PENGELUARAN, sumber_tipe kas_keluar,
        // transfer_ref NULL. Tidak boleh jadi Setor.
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal' => $this->hariIni, 'unit_id' => self::UNIT,
            'akun_kas_bank_id' => self::AKUN_KAS,
            'jenis' => 'PENGELUARAN', 'arah' => 'KELUAR', 'jumlah' => 750000,
            'transfer_ref' => null, 'sumber_tipe' => 'kas_keluar', 'sumber_id' => 1,
        ]);

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertFalse($hasil['ada'], 'Mirror legacy bukan Setor');
        $this->assertSame(0, $hasil['setor']);
    }

    public function testTransferInternalUnitLainTidakDihitung(): void
    {
        $this->setor($this->hariIni, self::UNIT2, self::AKUN_KAS2, 900000, 'st-3');

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertSame(0, $hasil['setor'], 'Setor unit lain bukan bagian saldo laci unit ini');
    }

    public function testTransferInternalTanggalLainTidakDihitung(): void
    {
        $this->setor('2026-10-09', self::UNIT, self::AKUN_KAS, 500000, 'st-4');

        $hasil = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);

        $this->assertSame(0, $hasil['setor'], 'Setor kemarin tidak boleh masuk hari ini');
    }

    // =================================================================
    // 8. SALDO > Rp1 JUTA
    // =================================================================

    public function testSaldoLebihDariSatuJutaDiteruskanUtuh(): void
    {
        // Regression inti: dulu dibatasi 1.000.000 dan sisanya "dialihkan" ke bank.
        $this->tutup('2026-10-08', self::UNIT, self::OPENING, 2221000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertSame(2221000, $hasil['nilai']);
        $this->assertGreaterThan(1000000, $hasil['nilai'], 'Saldo riil tidak boleh dipotong');
    }

    public function testSaldoBesarTidakMenulisBarisKasMasuk(): void
    {
        $jumlahKasMasuk = $this->db->table('kas_masuk')->countAllResults();

        $this->tutup('2026-10-08', self::UNIT, self::OPENING, 3311000);
        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertSame(3311000, $hasil['nilai']);
        $this->assertSame(
            $jumlahKasMasuk,
            $this->db->table('kas_masuk')->countAllResults(),
            'Carry-forward tidak boleh membuat transaksi kas_masuk'
        );
    }

    public function testSaldoBesarTidakMeningkatkanSaldoBank(): void
    {
        $bankAwal = (float) $this->db->table('transaksi_kas_bank')
            ->where('akun_kas_bank_id', self::AKUN_BANK)
            ->selectSum('jumlah', 's')->get()->getRow()->s;

        $this->tutup('2026-10-08', self::UNIT, self::OPENING, 5000000);
        $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $bankAkhir = (float) $this->db->table('transaksi_kas_bank')
            ->where('akun_kas_bank_id', self::AKUN_BANK)
            ->selectSum('jumlah', 's')->get()->getRow()->s;

        $this->assertEquals(
            $bankAwal,
            $bankAkhir,
            'Kelebihan kas tidak boleh muncul sebagai saldo bank tanpa Setor'
        );
    }

    // =================================================================
    // 9. FORMULA & KONSEP SALDO
    // =================================================================

    public function testFormulaSaldoAkhirMemasukkanSetorDanTarik(): void
    {
        // 1.500.000 + 800.000 - 100.000 - 500.000 + 200.000 = 1.900.000
        $awal = 1500000;
        $masuk = 800000;
        $keluar = 100000;

        $this->jual('INV-F1', $this->hariIni, $masuk);
        $this->keluar($this->hariIni, $keluar);
        $this->setor($this->hariIni, self::UNIT, self::AKUN_KAS, 500000, 'st-5');
        $this->tarik($this->hariIni, self::UNIT, self::AKUN_KAS, 200000, 'tr-5');

        $tr = $this->transfer->hariIni(self::UNIT, $this->hariIni, self::AKUN_KAS);
        $akhir = $awal + $masuk - $keluar - $tr['setor'] + $tr['tarik'];

        $this->assertSame(1900000, $akhir);
    }

    public function testSelisihAdalahFisikDikurangiSistem(): void
    {
        $sistem = 1900000;
        $fisik  = 1850000;

        $this->assertSame(-50000, $fisik - $sistem, 'Selisih negatif = uang laci kurang');
        $this->assertSame(100000, 2000000 - $sistem, 'Selisih positif = uang laci lebih');
        $this->assertSame(0, $sistem - $sistem, 'Tepat sama = selisih nol');
    }

    public function testCashLaciTetapInputFisikDanTidakDipaksaSamaDenganSistem(): void
    {
        // Sistem Rp1.900.000, admin hitung fisik Rp1.850.000 -> selisih -50.000.
        // Nilai fisik HARUS tersimpan apa adanya.
        $sistem = 1900000;
        $fisik  = 1850000;

        $this->tutup($this->hariIni, self::UNIT, 1500000, $sistem, $fisik);

        $row = $this->db->table('tutup_kasir')
            ->where('unit', self::UNIT)->where('tanggal', $this->hariIni)
            ->get()->getRow();

        $this->assertSame($sistem, (int) $row->akhir_cash, 'Saldo sistem');
        $this->assertSame($fisik, (int) $row->cash_laci, 'Fisik tetap independen');
        $this->assertSame(-50000, (int) $row->cash_laci - (int) $row->akhir_cash);
    }

    // =================================================================
    // 10, 11. SUMBER OPERASIONAL TIDAK BERUBAH
    // =================================================================

    public function testSumberKasMasukDanKasKeluarTetapSama(): void
    {
        $def = new TutupKasirSourceDefinition($this->db);

        $this->jual('INV-S1', $this->hariIni, 300000, 150000);
        $this->jual('INV-S2', $this->hariIni, 90000);
        $this->keluar($this->hariIni, 70000);
        $this->keluar($this->hariIni, 45000, 'BNI-001');

        $r = $def->ringkasan(self::UNIT, $this->hariIni);

        // cashpenjualan = SUM(bayar_tunai) dari seluruh penjualan, tanpa
        // pemisahan invoice service (dihitung dari tabel `service`).
        $this->assertSame(390000, $r['cashpenjualan'], 'Kas masuk penjualan tidak berubah');
        $this->assertSame(150000, $r['tfpenjualan'], 'Transfer penjualan tidak berubah');
        $this->assertSame(70000, $r['pengeluarancash'], 'Kas keluar tunai tidak berubah');
        $this->assertSame(45000, $r['pengeluarantf'], 'Kas keluar transfer tidak berubah');
        $this->assertSame(
            150000 + 390000,
            $r['cash'] + $r['transfer'],
            'Total kas masuk tetap penjumlahan kedua sumber'
        );
    }

    public function testSetorTidakMasukPengeluaranOperasional(): void
    {
        // Setor TIDAK boleh muncul sebagai kas_keluar supaya tidak mengotori
        // `pengeluaran` Tutup Kasir (kontrak yang sudah diaudit).
        $this->setor($this->hariIni, self::UNIT, self::AKUN_KAS, 500000, 'st-6');
        $this->keluar($this->hariIni, 100000);

        $nilai = (new TutupKasirSourceDefinition($this->db))
            ->kasKeluarCash(self::UNIT, $this->hariIni);

        $this->assertSame(100000, $nilai, 'Setor 500.000 tidak boleh ikut jadi pengeluaran');
    }

    public function testSetorTarikTidakMasukKasMasuk(): void
    {
        $sebelum = $this->db->table('kas_masuk')->countAllResults();

        $this->setor($this->hariIni, self::UNIT, self::AKUN_KAS, 500000, 'st-7');
        $this->tarik($this->hariIni, self::UNIT, self::AKUN_KAS, 250000, 'tr-7');

        $this->assertSame(
            $sebelum,
            $this->db->table('kas_masuk')->countAllResults(),
            'Setor/Tarik tidak boleh membuat baris kas_masuk'
        );
    }

    public function testKonsistenDenganDefinisiTransferFinance(): void
    {
        $this->jual('INV-P1', '2026-10-08', 400000, 250000);
        $this->keluar('2026-10-08', 120000);
        $this->setor('2026-10-08', self::UNIT, self::AKUN_KAS, 300000, 'st-8');

        $rincian = (new KasBankSourceMovement($this->db))->rincianMovement(
            self::AKUN_KAS,
            self::UNIT,
            '2026-10-08',
            '2026-10-08'
        );
        $tr = $this->transfer->hariIni(self::UNIT, '2026-10-08', self::AKUN_KAS);

        // Tutup Kasir dan Finance WAJIB dapat angka Setor yang sama karena
        // keduanya membaca definisi transfer internal yang sama.
        $this->assertSame($rincian['transfer_keluar'], $tr['setor']);
        $this->assertSame($rincian['transfer_masuk'], $tr['tarik']);
        $this->assertSame(400000, $rincian['cash_in']);
        $this->assertSame(120000, $rincian['cash_out']);
    }

    public function testOpeningAdalahBaselineBukanTransaksi(): void
    {
        // Opening tidak boleh muncul sebagai movement di hari pertama.
        $rincian = (new KasBankSourceMovement($this->db))->rincianMovement(
            self::AKUN_KAS,
            self::UNIT,
            '2026-10-08',
            '2026-10-08'
        );

        $this->assertSame(0, $rincian['net'], 'Opening baseline bukan transaksi hari itu');
    }

    // =================================================================
    // MULTI-UNIT
    // =================================================================

    public function testSaldoAwalTerpisahPerUnit(): void
    {
        $this->tutup('2026-10-08', self::UNIT, 0, 1110000);
        $this->tutup('2026-10-08', self::UNIT2, 0, 2220000);

        $unit1 = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');
        $unit2 = $this->saldoAwal->saldoAwalKas(self::UNIT2, '2026-10-09');

        $this->assertSame(1110000, $unit1['nilai']);
        $this->assertSame(2220000, $unit2['nilai']);
        $this->assertNotSame($unit1['nilai'], $unit2['nilai'], 'Tidak boleh ada saldo global');
    }

    public function testClosingUnitLainTidakMengalirKeUnitIni(): void
    {
        $this->tutup('2026-10-08', self::UNIT2, 0, 9999000);

        $hasil = $this->saldoAwal->saldoAwalKas(self::UNIT, '2026-10-09');

        $this->assertSame(
            TutupKasirSaldoAwal::SUMBER_OPENING,
            $hasil['sumber'],
            'Closing unit lain tidak boleh jadi saldo awal unit ini'
        );
        $this->assertSame(self::OPENING, $hasil['nilai']);
    }

    /**
     * 6. Saldo awal BANK yang Finance input TANPA verifikasi statement tetap
     *    terbaca oleh Tutup Kasir. Verifikasi bukan prerequisite baseline:
     *    `baselineBank()` hanya menuntut barisnya ada pada tanggal cut-off.
     */
    public function testSaldoAwalBankBelumDiverifikasiTetapTerbacaTutupKasir(): void
    {
        // Rekening BANK MILIK UNIT (bukan shared) — syarat akunBankUnit().
        $this->q("INSERT INTO akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa,
                 status, is_shared, is_finance_ho) VALUES
            (4, 1, 'BANK', 'Bank Unit 1', 'BNI-001', NULL, 'aktif', 0, 0)");

        $cutoff = FinanceScopeService::kasBankCutoffDate();
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 4,
            'tanggal'          => $cutoff,
            'saldo'            => 7500000,
            'keterangan'       => 'test-saldo-awal',
            'status'           => 'BELUM_VERIFIKASI',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->assertFalse(
            (new KasBankCutoffService())->statementVerified(4),
            'Fixture sengaja belum diverifikasi — inilah kondisi yang dulu memblokir'
        );

        $hasil = $this->saldoAwal->saldoAwalTransfer(self::UNIT, $this->hariIni);

        $this->assertTrue($hasil['ada'], 'Tutup Kasir wajib bisa membaca saldo awal yang sudah diinput');
        $this->assertSame(7500000, (int) $hasil['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $hasil['sumber']);
        $this->assertSame($cutoff, $hasil['tanggal']);
    }
}
