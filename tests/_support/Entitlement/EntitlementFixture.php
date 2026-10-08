<?php

namespace Tests\Support\Entitlement;

/**
 * Fixture entitlement KAS-BANK untuk test Fase 1.
 *
 * Isinya MENIRU state `erp_local` SETELAH M1-M4 (batch 23) berhasil
 * diterapkan, bukan state sebelum migrasi. Test yang memverifikasi scope
 * hanya benar kalau fixture-nya sama dengan data produksi yang sudah
 * dibetulkan — kalau tidak, test bisa hijau untuk data yang sudah usang.
 *
 * PETA ENTITLEMENT SETELAH M1-M4
 * -----------------------------
 *   akun 16  CV          idbank=2  shared  unit_id=NULL  alokasi [1,2]
 *   akun 3   ALFARIZKI   idbank=5  privat  unit_id=3     alokasi [3]
 *   akun 15  SABRINA     idbank=1  privat  unit_id=4     alokasi [4]
 *   akun 1   FINANCE     idbank=3  shared  unit_id=NULL  TANPA alokasi
 *   akun 4   FARA        idbank=4  nonaktif             (tidak disentuh)
 *
 * Konsekuensi yang harusTerlihat di test:
 *   - Unit 1, 2 -> hanya CV
 *   - Unit 3     -> hanya ALFARIZKI
 *   - Unit 4     -> hanya SABRINA
 *   - Unit 5     -> tidak punya rekening bank (Genteng belum diverifikasi)
 *   - Unit 50/HO -> tidak punya rekening bank
 *
 * SEMUA alokasi memakai `nominal = 0`. Itu disengaja: nominal 0 berarti
 * "belum ada saldo yang dialokasikan", BUKAN "tanpa hak". Kalau fixture ini
 * memakai nominal besar, test akan lolos karena alasan yang salah.
 */
trait EntitlementFixture
{

    /**
     * Koneksi group `tests`.
     *
     * Sengaja memakai db_connect() dan bukan properti kelas: trait ini dipakai
     * oleh beberapa test yang punya nama properti koneksi berbeda.
     */
    public function koneksi()
    {
        return db_connect();
    }

    public function jalankan(string $sql): void
    {
        $this->koneksi()->query($sql);
    }

    /**
     * Bangun schema minimal yang dibutuhkan service scope, validator, dan
     * Setor/Penarikan. Prefix `db_` WAJIB — itu yang dipakai group `tests`.
     */
    public function buatSchemaEntitlement(): void
    {
        // MySQL dan SQLite menuliskan auto-increment berbeda. Group `tests`
        // bawaan PHPUnit = SQLite3 :memory:, sedangkan runner verifikasi ini
        // memakai MySQL. Supaya fixture yang sama bisa dipakai di keduanya,
        // DDL-nya tidak boleh mengasumsikan satu driver saja.
        $autoInc = $this->koneksi()->getPlatform() === 'SQLite3' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT';

        foreach ([
            'db_transaksi_kas_bank',
            'db_saldo_awal_kas_bank',
            'db_alokasi_saldo_kas_bank',
            'db_akun_kas_bank',
            'db_akun',
            'db_bank',
            'db_unit',
            'db_no_akun',
            'db_spv_units',
        ] as $t) {
            $this->koneksi()->query('DROP TABLE IF EXISTS ' . $t);
        }

        $this->koneksi()->query('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->koneksi()->query('CREATE TABLE db_no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $this->koneksi()->query('CREATE TABLE db_akun (ID_AKUN INTEGER PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL)');
        $this->koneksi()->query('CREATE TABLE db_spv_units (spv_id INT NULL, unit_id INT NULL)');
        $this->koneksi()->query('CREATE TABLE db_bank (
            idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL,
            atas_nama TEXT NULL, norek TEXT NULL)');

        $this->koneksi()->query('CREATE TABLE db_akun_kas_bank (
            idakun_kas_bank INTEGER PRIMARY KEY,
            unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
            bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
            is_shared TINYINT(1) DEFAULT 0,
            is_finance_ho TINYINT(1) DEFAULT 0,
            created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        $this->koneksi()->query('CREATE TABLE db_alokasi_saldo_kas_bank (
            id INTEGER PRIMARY KEY ' . $autoInc . ',
            akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
            keterangan TEXT NULL, input_by INT NULL,
            created_at TEXT NULL, updated_at TEXT NULL)');

        $this->koneksi()->query('CREATE TABLE db_saldo_awal_kas_bank (
            id INTEGER PRIMARY KEY ' . $autoInc . ',
            akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
            keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
            input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        $this->koneksi()->query('CREATE TABLE db_transaksi_kas_bank (
            idtransaksi INTEGER PRIMARY KEY ' . $autoInc . ',
            tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
            jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
            transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL UNIQUE,
            sumber_tipe TEXT NULL, sumber_id INT NULL,
            keterangan TEXT NULL, bukti TEXT NULL, input_by INT NULL,
            created_at TEXT NULL, updated_at TEXT NULL)');
    }

    /**
     * Isi data sesuai state setelah M1-M4.
     */
    public function seedEntitlementCanonical(): void
    {
        $this->koneksi()->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES
            (1, 'Probolinggo'), (2, 'Jember'), (3, 'Banyuwangi'),
            (4, 'Pandaan'), (5, 'Genteng'), (50, 'Head Office')");

        // Akun login untuk user scope. ROOT = lintas unit.
        $this->koneksi()->query('INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 50, ' . Fase1::ROLE_ROOT . ',    \'Root\'),
            (2, 1,  ' . Fase1::ROLE_KASIR . ',  \'Kasir Probolinggo\'),
            (3, 1,  ' . Fase1::ROLE_FINANCE . ', \'Finance Probolinggo\'),
            (4, 50, ' . Fase1::ROLE_KASIR . ',  \'Kasir Head Office\')');

        $this->koneksi()->query("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('1', 'Bank BCA', '0391796181', 'SABRINA'),
            ('2', 'Bank BCA', '0393778773', 'ICLEAR DIGITAL'),
            ('3', 'Bank BCA', '0391943558', 'PT_ICLEAR_FINANCE'),
            ('4', 'Bank BCA', '3251427508', 'FARA'),
            ('5', 'Bank BCA', '1802016667', 'ALFARIZKI')");

        // Rekening bank. Perhatikan: TIDAK ADA rekening untuk Unit 5 (Genteng) —
        // nomor 1802016123 belum diverifikasi Finance, jadi sengaja tidak dibuat.
        $this->koneksi()->query('INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (' . Fase1::AKUN_FINANCE . ',   NULL, \'BANK\', \'Rekening Kas Direksi (IRA)\', \'' . Fase1::IDBANK_FINANCE . '\',   \'aktif\',    1, 1),
            (' . Fase1::AKUN_ALFARIZKI . ', 3,    \'BANK\', \'Bank ALFARIZKI\',              \'' . Fase1::IDBANK_ALFARIZKI . '\', \'aktif\',    0, 0),
            (' . Fase1::AKUN_FARA . ',      NULL, \'BANK\', \'Bank FARA (nonaktif)\',         \'' . Fase1::IDBANK_FARA . '\',      \'nonaktif\', 0, 0),
            (' . Fase1::AKUN_SABRINA . ',   4,    \'BANK\', \'Bank SABRINA\',                \'' . Fase1::IDBANK_SABRINA . '\',   \'aktif\',    0, 0),
            (' . Fase1::AKUN_CV . ',        NULL, \'BANK\', \'Bank CV (ICLEAR Digital)\',     \'' . Fase1::IDBANK_CV . '\',        \'aktif\',    1, 0)');

        // KAS per unit. Unit 5 dan 50 MEMILIKI KAS tapi tidak punya rekening
        // bank — persis kondisi yang harus membuat mereka tidak otomatis
        // mendapat akses ke kas Direksi.
        $this->koneksi()->query('INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (5, 1,  \'KAS\', \'Kas Probolinggo\', NULL, \'aktif\',    0, 0),
            (6, 2,  \'KAS\', \'Kas Jember\',       NULL, \'aktif\',    0, 0),
            (7, 3,  \'KAS\', \'Kas Banyuwangi\',   NULL, \'aktif\',    0, 0),
            (8, 4,  \'KAS\', \'Kas Pandaan\',      NULL, \'aktif\',    0, 0),
            (9, 5,  \'KAS\', \'Kas Genteng\',      NULL, \'aktif\',    0, 0),
            (10, 50, \'KAS\', \'Kas Head Office\',  NULL, \'nonaktif\', 0, 0)');

        // Entitlement hasil M1-M3. SEMUA nominal 0 = punya hak, saldo belum
        // dialokasikan. Finance (akun 1) SENGAJA tidak punya baris di sini.
        $this->koneksi()->query('INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (' . Fase1::AKUN_CV . ', 1, 0, \'Hak pakai rekening CV (M1)\'),
            (' . Fase1::AKUN_CV . ', 2, 0, \'Hak pakai rekening CV (M1)\'),
            (' . Fase1::AKUN_ALFARIZKI . ', 3, 0, \'Hak pakai rekening ALFARIZKI (M3)\'),
            (' . Fase1::AKUN_SABRINA . ', 4, 0, \'Hak pakai rekening SABRINA (M2)\')');
    }

    /**
     * Isi saldo KAS fisik sebuah unit.
     *
     * Dipakai test Setor: setor menarik dari laci KAS, jadi laci harus berisi
     * dulu. Saldo datang dari statement VERIFIED (bukan dari mengarang angka
     * di kode), supaya test ini tidak mengarang kondisi bisnis.
     */
    public function seedSaldoKas(int $akunKasId, int $saldo = 10000000): void
    {
        $this->koneksi()->query(
            'INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, status, keterangan) VALUES ('
            . $akunKasId . ', \'' . \App\Services\Finance\FinanceScopeService::kasBankCutoffDate() . '\', '
            . $saldo . ', \'VERIFIED\', \'saldo KAS test\')'
        );
    }

    /**
     * State SEBELUM M1-M4, dipakai test idempotency migration.
     *
     * Bentuk ini yang ditemukan audit awal dan menjadi alasan M1-M4 dibuat:
     *   - CV (16)        alokasi 1, 2, 4, 50   -> sebenarnya cuma 1 & 2
     *   - SABRINA (15)   alokasi 1, 3, 4        -> sebenarnya cuma 4,
     *                   dan master masih shared though sebenarnya milik Unit 4
     *   - ALFARIZKI (3)  tanpa alokasi           -> padahal harusnya ada
     *   - FINANCE (1)    tanpa alokasi
     *
     * Sengaja TIDAK memakai canonical: kalau test idempotency disemai dengan
     * state akhir, migration kedua jalan di atas data yang sudah benar dan
     * test jadi tidak menguji apa pun.
     */
    public function seedEntitlementPraMigrasi(): void
    {
        $this->buatSchemaEntitlement();

        $this->koneksi()->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES
            (1, 'Probolinggo'), (2, 'Jember'), (3, 'Banyuwangi'),
            (4, 'Pandaan'), (5, 'Genteng'), (50, 'Head Office')");

        $this->koneksi()->query('INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 50, ' . Fase1::ROLE_ROOT . ', \'Root\'),
            (2, 1,  ' . Fase1::ROLE_KASIR . ', \'Kasir Probolinggo\'),
            (3, 1,  ' . Fase1::ROLE_FINANCE . ', \'Finance Probolinggo\'),
            (4, 50, ' . Fase1::ROLE_KASIR . ', \'Kasir Head Office\')');

        $this->koneksi()->query("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('1', 'Bank BCA', '0391796181', 'SABRINA'),
            ('2', 'Bank BCA', '0393778773', 'ICLEAR DIGITAL'),
            ('3', 'Bank BCA', '0391943558', 'PT_ICLEAR_FINANCE'),
            ('4', 'Bank BCA', '3251427508', 'FARA'),
            ('5', 'Bank BCA', '1802016667', 'ALFARIZKI')");

        // Master PRAMIGRASI: SABRINA masih shared / tanpa unit_id.
        $this->koneksi()->query('INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (' . Fase1::AKUN_FINANCE . ',   NULL, \'BANK\', \'Rekening Kas Direksi (IRA)\', \'' . Fase1::IDBANK_FINANCE . '\',   \'aktif\',    1, 1),
            (' . Fase1::AKUN_ALFARIZKI . ', 3,    \'BANK\', \'Bank ALFARIZKI\',              \'' . Fase1::IDBANK_ALFARIZKI . '\', \'aktif\',    0, 0),
            (' . Fase1::AKUN_FARA . ',      NULL, \'BANK\', \'Bank FARA (nonaktif)\',         \'' . Fase1::IDBANK_FARA . '\',      \'nonaktif\', 0, 0),
            (' . Fase1::AKUN_SABRINA . ',   NULL, \'BANK\', \'Bank SABRINA\',                \'' . Fase1::IDBANK_SABRINA . '\',   \'aktif\',    1, 0),
            (' . Fase1::AKUN_CV . ',        NULL, \'BANK\', \'Bank CV (ICLEAR Digital)\',     \'' . Fase1::IDBANK_CV . '\',        \'aktif\',    1, 0)');

        $this->koneksi()->query('INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (5, 1,  \'KAS\', \'Kas Probolinggo\', NULL, \'aktif\',    0, 0),
            (6, 2,  \'KAS\', \'Kas Jember\',       NULL, \'aktif\',    0, 0),
            (7, 3,  \'KAS\', \'Kas Banyuwangi\',   NULL, \'aktif\',    0, 0),
            (8, 4,  \'KAS\', \'Kas Pandaan\',      NULL, \'aktif\',    0, 0),
            (9, 5,  \'KAS\', \'Kas Genteng\',      NULL, \'aktif\',    0, 0),
            (10, 50, \'KAS\', \'Kas Head Office\',  NULL, \'nonaktif\', 0, 0)');

        // Alokasi PRAMIGRASI: CV 4 unit, SABRINA 3 unit, ALFARIZKI nol.
        $this->koneksi()->query('INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (' . Fase1::AKUN_CV . ',      1,  0, \'histori\'),
            (' . Fase1::AKUN_CV . ',      2,  0, \'histori\'),
            (' . Fase1::AKUN_CV . ',      4,  0, \'histori\'),
            (' . Fase1::AKUN_CV . ',      50, 0, \'histori\'),
            (' . Fase1::AKUN_SABRINA . ', 1,  0, \'histori\'),
            (' . Fase1::AKUN_SABRINA . ', 3,  0, \'histori\'),
            (' . Fase1::AKUN_SABRINA . ', 4,  0, \'histori\')');

        $this->loginRoot();
    }

    /**
     * Statement cutoff. Akun 16/3/15 WAJIB punya statement VERIFIED sebelum
     * mutasi baru boleh masuk (design Fase 1). Tanggal cutoff mengikuti
     * FinanceScopeService supaya tidak di-hardcode di test.
     */
    public function seedStatementCanonical(string $status = 'VERIFIED'): void
    {
        $tanggal = \App\Services\Finance\FinanceScopeService::kasBankCutoffDate();

        foreach ([Fase1::AKUN_CV, Fase1::AKUN_ALFARIZKI, Fase1::AKUN_SABRINA] as $akun) {
            $this->koneksi()->query(
                'INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, status, keterangan) VALUES '
                . '(' . $akun . ', \'' . $tanggal . '\', 0, \'' . $status . '\', \'statement test\')'
            );
        }
    }

    /** Login sebagai ROOT (lintas unit, user scope = semua unit). */
    public function loginRoot(): void
    {
        $_SESSION['ID_AKUN']    = 1;
        $_SESSION['ID_UNIT']    = 50;
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_ROOT;
    }

    /** Login sebagai kasir unit tertentu (user scope = unit itu saja). */
    public function loginUnit(int $unit, int $role = Fase1::ROLE_KASIR): void
    {
        $id = 100 + $unit;
        $this->koneksi()->query(
            'INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES ('
            . $id . ', ' . $unit . ', ' . $role . ', \'Kasir unit ' . $unit . '\')'
        );
        $_SESSION['ID_AKUN']    = $id;
        $_SESSION['ID_UNIT']    = $unit;
        $_SESSION['ID_JABATAN'] = $role;
    }

    /** @return int[] idakun_kas_bank tipe BANK yang terlihat untuk unit */
    public function bankTerlihat(array $unitIds, ?int $unitTerpilih = null): array
    {
        $model = new \App\Models\ModelAkunKasBank();
        $rows  = $model->getDalamScopeUnit($unitIds, $unitTerpilih, true, false);

        $ids = [];
        foreach ($rows as $r) {
            if ((string) $r->tipe === 'BANK') {
                $ids[] = (int) $r->idakun_kas_bank;
            }
        }
        sort($ids);

        return $ids;
    }

    /** @return int[] akun BANK yang boleh jadi TUJUAN pada unit */
    public function bankTujuanUntuk(int $unit, int $role = Fase1::ROLE_KASIR): array
    {
        $svc  = new \App\Services\Finance\KasBankScopeService();
        $model = new \App\Models\ModelAkunKasBank();
        $rows  = $model->getDalamScopeUnit([$unit], $unit, true, false);

        $ids = [];
        foreach ($rows as $r) {
            if ((string) $r->tipe === 'BANK' && $svc->canUseAsDestination($r, $unit, $role)) {
                $ids[] = (int) $r->idakun_kas_bank;
            }
        }
        sort($ids);

        return $ids;
    }
}