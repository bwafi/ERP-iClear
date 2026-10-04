<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Struktur statement reference bank + residual LEGACY/UNASSIGNED
 * sesuai keputusan cut-off 30 September 2026.
 *
 * KEBIJAKAN YANG DIHORMATI
 * ------------------------
 * Cut-off resmi  : 2026-09-30  (tanggal SALDOKORAN yang disepakati)
 * Periode aktif  : 2026-10-01 → sekarang
 *
 * SALDO BANK 30 SEPETEMBER ADALAH STATEMENT REFERENCE, BUKAN TRANSAKSI.
 * Diwujudkan lewat tabel `saldo_awal_kas_bank` yang sudah ada:
 *
 *     saldo_fisik(akun) = statement(akun, 30 Sep) + net movement sejak 1 Okt
 *
 * Tidak ada baris `transaksi_kas_bank` yang dibuat di sini. Opening bank
 * TIDAK pernah menjadi mutasi ledger.
 *
 * YANG DIUBAH
 * -----------
 * 1. `saldo_awal_kas_bank`
 *    - UNIQUE(akun_kas_bank_id) -> UNIQUE(akun_kas_bank_id, tanggal)
 *      Supaya satu rekening fisik bisa punya statement reference pada
 *      beberapa tanggal cut-off (mis. 30 Sep 2026 dan 30 Sep 2027).
 *      Index unique lama harus dibuang lebih dulu; selama masih ada, satu
 *      rekening tidak bisa punya statement di dua tanggal.
 *    - kolom baru `status` (BELUM_VERIFIKASI | VERIFIED) supaya "sudah
 *      dicek Finance" bisa di-QUERY, bukan ditebak dari `keterangan`.
 *    - index biasa pada `tanggal` untuk lookup statement per tanggal.
 *
 * 2. `akun_kas_bank`
 *    - kolom generated `bank_kunci` = IF(tipe='BANK', bank_idbank, NULL)
 *      dengan UNIQUE. Ini enforce "1 rekening fisik = 1 akun BANK" tanpa
 *      merusak akun KAS: kolomnya NULL untuk KAS, dan UNIQUE mengizinkan
 *      banyak NULL di MariaDB.
 *    - MariaDB/MySQL tidak punya partial unique index, jadi bentuk IF()
 *      dipakai agar constraint hanya berlaku untuk tipe BANK.
 *    - Constraint dibangun HANYA jika data existing tidak konflik. Kalau
 *      ada bank_idbank ter-map ke >1 akun BANK, migration BERHENTI dengan
 *      pesan yang menyebut id-nya — TIDAK memilih salah satu, TIDAK
 *      menghapus data. Conflict itu keputusan manusia.
 *    - `getBankByBankIdbank()` sebelumnya memakai `first()` tanpa
 *      `orderBy`, jadi kalau sempat ada duplikat pemetaannya tidak
 *      deterministik antar-request.
 *
 * 3. Baris statement reference
 *    - Baris yang ada (saldo=0, tanggal=2026-10-01, keterangan
 *      OPENING-BELUM-VERIFIKASI) di-datekan ulang ke 2026-09-30 karena
 *      2026-10-01 bukan tanggal statement. Nilai `saldo` TIDAK disentuh.
 *    - Akun BANK aktif yang belum punya baris sama sekali (mis. akun 15 &
 *      16) dibuatkan baris dengan `saldo = 0`, `status = BELUM_VERIFIKASI`.
 *
 * YANG SENGAJA TIDAK DILAKUKAN
 * ---------------------------
 *   - TIDAK mengarang saldo statement. Semua baris hasil migration
 *     berstatus BELUM_VERIFIKASI dengan saldo 0, dan `saveSaldoAwal()`
 *     menolak nilai 0. Angka koran hanya masuk lewat Finance.
 *   - TIDAK membuat opening transaction di `transaksi_kas_bank`.
 *   - TIDAK menyentuh `transaksi_kas_bank` sama sekali.
 *   - TIDAK mengisi `alokasi_saldo_kas_bank` (entitlement != pembagian
 *     saldo; kolom nominal tetap 0 sampai Finance memutuskan).
 *   - TIDAK backfill histori 2.688 dokumen.
 *   - TIDAK mengubah histori kas/bank sebelum cut-off.
 *   - TIDAK menghapus baris data apa pun.
 */
class StrukturStatementBankDanLegacyResidual extends Migration
{
    public const STATUS_BELUM = 'BELUM_VERIFIKASI';
    public const STATUS_SUDAH = 'VERIFIED';

    public function up()
    {
        $this->strukturStatementReference();
        $this->jagaPemetaanRekeningFisik();
        $this->seedStatementReference();
    }

    public function down()
    {
        // Baris statement TIDAK dihapus: isinya sudah jadi acuan pembukuan.
        // Yang dikembalikan hanya STRUKTURNYA.

        $this->dropIndexJikaAda('saldo_awal_kas_bank', 'idx_saldo_awal_tanggal');
        $this->dropUniqueJikaAda('saldo_awal_kas_bank', 'uniq_saldo_awal_akun_tanggal');

        // Constraint satu rekening = satu statement harus dibuang SEBELUM
        // unique per (akun, tanggal), kalau tidak up() akan mentok di tengah.
        $this->dropUniqueJikaAda('akun_kas_bank', 'uniq_akun_bank_fisik');

        if ($this->db->fieldExists('bank_kunci', 'akun_kas_bank')) {
            $this->alter('akun_kas_bank', 'DROP COLUMN `bank_kunci`');
        }

        if ($this->db->fieldExists('status', 'saldo_awal_kas_bank')) {
            $this->forge->dropColumn('saldo_awal_kas_bank', ['status']);
        }

        // Kembalikan unique lama: satu rekening hanya boleh punya SATU statement.
        //
        // SENGaja TIDAK menghapus baris statement untuk.xxx memaksa constraint
        // ini. Kalau ada rekening yang statement-nya sudah beberapa tanggal,
        // memilih tanggal mana yang "benar" adalah keputusan Finance, bukan
        // keputusan rollback. Migration berhenti dengan pesan yang menyebut
        // rekeningnya, dan tidak ada data yang hilang.
        $ganda = $this->akunStatementGanda();

        if ($ganda !== []) {
            throw new RuntimeException(
                'Rollback 2026-10-03-000400 dihentikan. Rekening berikut punya statement '
                . 'di lebih dari satu tanggal, sehingga unique lama (satu statement per rekening) '
                . 'tidak bisa dipasang: akun_kas_bank_id = ' . implode(', ', $ganda) . '. '
                . 'Tentukan manual statement mana yang benar untuk rekening tersebut, lalu jalankan '
                . 'rollback lagi. Tidak ada baris data yang dihapus oleh migration ini.'
            );
        }

        if (! $this->sudahAdaIndex('saldo_awal_kas_bank', 'akun_kas_bank_id')) {
            $this->alter('saldo_awal_kas_bank', 'ADD UNIQUE KEY `akun_kas_bank_id` (`akun_kas_bank_id`)');
        }
    }

    /**
     * Daftar akun yang punya statement di lebih dari satu tanggal.
     *
     * @return list<int>
     */
    private function akunStatementGanda(): array
    {
        // DBPrefix ditulis manual: Connection::prefix() tidak ada di CI 4.4.8.
        $tabel = $this->db->DBPrefix . 'saldo_awal_kas_bank';

        $rows = $this->db->query(
            'SELECT akun_kas_bank_id, COUNT(*) as jml FROM ' . $tabel
            . ' GROUP BY akun_kas_bank_id HAVING jml > 1'
        )->getResultArray();

        return array_map(static fn ($r) => (int) $r['akun_kas_bank_id'], $rows);
    }

    // =====================================================================
    // 1. Statement reference: boleh banyak tanggal per rekening
    // =====================================================================
    private function strukturStatementReference(): void
    {
        if (! $this->db->fieldExists('status', 'saldo_awal_kas_bank')) {
            $this->forge->addColumn('saldo_awal_kas_bank', [
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => false,
                    'default'    => self::STATUS_BELUM,
                    'comment'    => 'BELUM_VERIFIKASI | VERIFIED — statement sudah dicek Finance atau belum',
                ],
            ]);
        }

        // Index unique lama dibuang lebih dulu. Selama index (akun_kas_bank_id)
        // masih ada, satu rekening tidak bisa punya statement di dua tanggal.
        $this->dropUniqueJikaAda('saldo_awal_kas_bank', 'akun_kas_bank_id');
        $this->dropUniqueJikaAda('saldo_awal_kas_bank', 'uniq_saldo_awal_akun');

        if (! $this->sudahAdaIndex('saldo_awal_kas_bank', 'uniq_saldo_awal_akun_tanggal')) {
            $this->alter(
                'saldo_awal_kas_bank',
                'ADD UNIQUE KEY `uniq_saldo_awal_akun_tanggal` (`akun_kas_bank_id`, `tanggal`)'
            );
        }

        if (! $this->sudahAdaIndex('saldo_awal_kas_bank', 'idx_saldo_awal_tanggal')) {
            $this->alter('saldo_awal_kas_bank', 'ADD INDEX `idx_saldo_awal_tanggal` (`tanggal`)');
        }
    }

    // =====================================================================
    // 2. Satu rekening fisik = satu akun BANK
    // =====================================================================
    private function jagaPemetaanRekeningFisik(): void
    {
        if ($this->db->fieldExists('bank_kunci', 'akun_kas_bank')) {
            return;
        }

        $konflik = $this->db->table('akun_kas_bank')
            ->select('bank_idbank, COUNT(*) as jml, GROUP_CONCAT(idakun_kas_bank ORDER BY idakun_kas_bank) as akun')
            ->where('tipe', 'BANK')
            ->where('bank_idbank IS NOT NULL', null, false)
            ->groupBy('bank_idbank')
            ->having('jml', '>', 1)
            ->get()
            ->getResultArray();

        if ($konflik !== []) {
            $detail = [];
            foreach ($konflik as $k) {
                $detail[] = sprintf('bank_idbank=%s -> akun [%s]', $k['bank_idbank'], $k['akun']);
            }

            throw new \RuntimeException(
                'Migration dihentikan: ada rekening fisik yang ter-map ke lebih dari satu akun'
                . '_kas_bank tipe BANK. Constraint UNIQUE sengaja tidak dipasang sebelum'
                . ' ini diputuskan manual (tidak ada data yang dihapus atau dipilih'
                . ' otomatis). Konflik: ' . implode('; ', $detail)
            );
        }

        $this->alter(
            'akun_kas_bank',
            'ADD COLUMN `bank_kunci` VARCHAR(20) GENERATED ALWAYS AS '
            . "(CASE WHEN `tipe` = 'BANK' THEN `bank_idbank` ELSE NULL END) STORED"
        );

        $this->alter('akun_kas_bank', 'ADD UNIQUE KEY `uniq_akun_bank_fisik` (`bank_kunci`)');
    }

    // =====================================================================
    // 3. Baris statement reference di tanggal cut-off
    // =====================================================================
    private function seedStatementReference(): void
    {
        $cutoff = (string) (new \Config\Finance())->cutoffDate;
        $db     = $this->db;
        $now    = date('Y-m-d H:i:s');

        // (a) Baris yang sudah ada: datekan ke tanggal cut-off. Nilai saldo
        //     TIDAK diubah — 0 tetap 0 sampai Finance memberi angka koran.
        $rows = $db->table('saldo_awal_kas_bank')
            ->select('id, akun_kas_bank_id, tanggal')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if ($row['tanggal'] === $cutoff) {
                continue;
            }

            $bentrok = (int) $db->table('saldo_awal_kas_bank')
                ->where('akun_kas_bank_id', (int) $row['akun_kas_bank_id'])
                ->where('tanggal', $cutoff)
                ->where('id !=', (int) $row['id'])
                ->countAllResults();

            if ($bentrok > 0) {
                // Sudah ada statement di tanggal cut-off untuk rekening ini;
                // baris lama dibiarkan apa adanya (tidak dihapus).
                continue;
            }

            $db->table('saldo_awal_kas_bank')
                ->where('id', (int) $row['id'])
                ->update(['tanggal' => $cutoff, 'updated_at' => $now]);
        }

        // (b) Akun BANK aktif yang belum punya statement reference sama sekali.
        $akunIds = $db->table('akun_kas_bank')
            ->select('idakun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('status', 'aktif')
            ->get()
            ->getResultArray();

        foreach ($akunIds as $a) {
            $akunId = (int) $a['idakun_kas_bank'];

            $sudah = (int) $db->table('saldo_awal_kas_bank')
                ->where('akun_kas_bank_id', $akunId)
                ->countAllResults();

            if ($sudah > 0) {
                continue;
            }

            $db->table('saldo_awal_kas_bank')->insert([
                'akun_kas_bank_id' => $akunId,
                'tanggal'          => $cutoff,
                // 0 = belum diisi, BUKAN "saldo rekening ini benar-benar nol".
                'saldo'            => 0,
                'status'           => self::STATUS_BELUM,
                'keterangan'       => 'STATEMENT 30 SEP — BELUM DIVERIFIKASI, menunggu rekening koran Finance',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    // =====================================================================

    private function alter(string $table, string $sql): void
    {
        $this->db->query('ALTER TABLE `' . $this->prefix() . $table . '` ' . $sql);
    }

    /**
     * Nama index yang sudah ada di tabel.
     *
     * @return string[]
     */
    private function daftarIndex(string $table): array
    {
        $out = [];

        foreach ($this->db->query('SHOW INDEX FROM `' . $this->prefix() . $table . '`')->getResultArray() as $r) {
            $out[(string) $r['Key_name']] = true;
        }

        return array_keys($out);
    }

    private function sudahAdaIndex(string $table, string $key): bool
    {
        return in_array($key, $this->daftarIndex($table), true);
    }

    private function dropIndexJikaAda(string $table, string $key): void
    {
        if ($this->sudahAdaIndex($table, $key)) {
            $this->alter($table, 'DROP INDEX `' . $key . '`');
        }
    }

    private function dropUniqueJikaAda(string $table, string $key): void
    {
        $found = false;

        foreach ($this->db->query('SHOW INDEX FROM `' . $this->prefix() . $table . '`')->getResultArray() as $r) {
            if ((string) $r['Key_name'] === $key && (int) $r['Non_unique'] === 0) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->alter($table, 'DROP INDEX `' . $key . '`');
        }
    }

    private function prefix(): string
    {
        return (string) $this->db->prefix;
    }
}
