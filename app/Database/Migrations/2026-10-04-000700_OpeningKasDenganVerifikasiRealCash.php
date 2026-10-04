<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * OPENING KAS — baseline yang DIIMPUT Finance, diverifikasi terhadap cash count.
 *
 * KEPUTUSAN YANG DIHORMATI
 * -----------------------
 * Cutoff opening KAS = cutoff opening Bank = 2026-10-05 (akhir 5 Okt).
 * Movement dihitung sejak 2026-10-06. Rumus:
 *
 *     saldo_buku_kas(akun) = opening_kas(akun, 5 Okt) + net movement sejak 6 Okt
 *
 * Kenapa tabel BARU dan bukan `saldo_awal_kas_bank`:
 *   `saldo_awal_kas_bank` adalah STATEMENT REFERENCE — koran bank. KAS tidak
 *   punya koran bank dan tidak boleh dibuatkan yang palsu. Memakai tabel itu
 *   untuk KAS akan membuat laci kas seolah-olah punya rekening koran, dan
 *   menghilangkan bedanya "angka koran" vs "angka hasil hitung laci".
 *
 * Kenapa `tutup_kasir.akhir_cash` TIDAK jadi sumber opening:
 *   `akhir_cash` adalah hitungan FISIK laci saat tutup kasir — itu REAL/ACTUAL,
 *   bukan keputusan Finance. Memakainya sebagai sumber opening otomatis berarti
 *   sistem mengarang baseline tanpa ada yang menginput dan menanggung selisihnya.
 *   Di tabel ini `akhir_cash` hanya dibaca sebagai nilai REAL untuk rekonsiliasi:
 *  opening tetap angka yang dikejar Finance, dan selisih = real - opening
 *   dihitung serta ditampilkan apa adanya.
 *
 * YANG DIJAMIN
 * ------------
 *   - Tidak ada baris `transaksi_kas_bank` yang dibuat. Opening KAS tidak
 *     pernah menjadi movement, jadi tidak mungkin dobel count.
 *   - Tidak ada satu pun baris yang di-seed. Tabel sengaja KOSONG: opening KAS
 *     hanya sah kalau Finance yang mengisinya. Migration tidak menebak saldo.
 *   - `tutup_kasir` hanya DIBACA (oleh service), tidak diubah.
 *   - Tidak ada backfill histori, tidak ada penghapusan data.
 *
 * STATUS
 * ------
 *   BELUM_VERIFIKASI : opening sudah diinput, belum dicocokkan dengan real cash
 *   TERVERIFIKASI    : opening == real cash pada cutoff (selisih 0)
 *   TIDAK_COCOK      : sudah dicocokkan, selisih != 0 — REKONSILIASI, bukan baseline
 *
 * `TIDAK_COCOK` sengaja TIDAK sama dengan `VERIFIED`: saldo yang Opening pakai
 * hanyalah opening. Kalau opening ikut dirampas supaya cocok, angka baseline
 * berubah diam-diam dan movement lama jadi salah baca.
 */
class OpeningKasDenganVerifikasiRealCash extends Migration
{
    public const STATUS_BELUM  = 'BELUM_VERIFIKASI';
    public const STATUS_SUDAH  = 'TERVERIFIKASI';
    public const STATUS_GAGAL  = 'TIDAK_COCOK';

    public function up()
    {
        if (! $this->db->tableExists('opening_kas')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'akun_kas_bank_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                    'comment'    => 'akun_kas_bank.idakun_kas_bank — hanya rekening tipe KAS',
                ],
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                    'comment'    => 'unit pemilik laci — disalin dari akun, disimpan supaya rekonsiliasi tidak bergantung pada join',
                ],
                'tanggal' => [
                    'type'       => 'DATE',
                    'null'       => false,
                    'comment'    => 'Tanggal cut-off opening — HARUS sama dengan Finance.cutoffDate',
                ],
                'opening' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'default'    => 0,
                    'null'       => false,
                    'comment'    => 'BASELINE — saldo riil yang ditetapkan Finance pada cutoff',
                ],
                'real_cash' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'null'       => true,
                    'comment'    => 'REAL/ACTUAL dari tutup_kasir.akhir_cash saat verifikasi (snapshot, bukan sumber opening)',
                ],
                'selisih' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'null'       => true,
                    'comment'    => 'real_cash - opening; 0 = cocok',
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => false,
                    'default'    => self::STATUS_BELUM,
                    'comment'    => 'BELUM_VERIFIKASI | TERVERIFIKASI | TIDAK_COCOK',
                ],
                'keterangan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'input_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'comment'    => 'User yang menetapkan opening',
                ],
                'verifikasi_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'comment'    => 'User yang mencocokkan opening dengan real cash',
                ],
                'verifikasi_at' => [
                    'type'     => 'DATETIME',
                    'null'     => true,
                    'comment'  => 'Waktu pencocokan terakhir',
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);

$this->forge->addKey('id', true);
            // Satu rekening = satu opening pada satu tanggal. Inilah yang
            // membuat opening "dihitung sekali": tidak ada baris kedua yang
            // bisa menambah opening untuk rekening dan tanggal yang sama.
            $this->forge->addUniqueKey(['akun_kas_bank_id', 'tanggal'], 'uniq_opening_kas_akun_tanggal');
            // CATATAN: pada Forge CI 4.4.8 parameter kedua addKey() adalah
            // $primary (bool), BUKAN nama index. Nama index harus di posisi
            // keempat, kalau tidak index ini diam-diam jadi PRIMARY KEY.
            $this->forge->addKey(['tanggal'], false, false, 'idx_opening_kas_tanggal');
            $this->forge->addKey(['unit_id', 'tanggal'], false, false, 'idx_opening_kas_unit_tanggal');

            $this->forge->createTable('opening_kas', true);
        }

        // Tutup kasir WAJIB ada sebelum kolom ini dipakai: tanpa itu tidak ada
        // sumber real cash yang sah. Diberhentikan di sini, bukan diam-diam
        // membuat kolom yang tidak bisa diisi.
        if (! $this->db->tableExists('tutup_kasir')) {
            throw new RuntimeException(
                'Migration dihentikan: tabel `tutup_kasir` tidak ada. Opening KAS diverifikasi '
                . 'terhadap hasil hitung laci di tabel itu, jadi sumber real cash harus ada lebih '
                . 'dahulu. Tidak ada tabel yang dibuat dan tidak ada data yang diubah.'
            );
        }

        $this->jagaHanyaRekeningKas();
    }

    public function down()
    {
        // PERINGATAN: rollback ini MENGHAPUS baris opening.
        //
        // `dropTable` menghapus tabel beserta isinya, jadi begitu baseline sudah
        // dipakai untuk pembukuan, `migrate:rollback` akan menghapus acuan itu
        // dan saldo KAS kembali ke movement-only. Karena itu jangan pernah
        // rollback migration ini di database yang sudah dipakai: kosongkan
        // barisnya lewat proses backfill yang disetujui dulu, baru jalankan
        // rollback kalau memang harus.
        //
        // Trigger tidak dihapus eksplisit: MySQL/MariaDB membuang trigger-nya
        // bersama tabelnya.
        if ($this->db->tableExists('opening_kas')) {
            $this->forge->dropTable('opening_kas', true);
        }
    }

    /**
     * Rekening non-KAS tidak boleh punya opening KAS.
     *
     * Dijaga lewat trigger, bukan cuma konvensi di aplikasi: kolom
     * `akun_kas_bank_id` sengaja tidak memakai foreign key supaya tidak
     * bergantung pada konsistensi tipe data tabel lain — tapi aturan "KAS
     * saja" tetap ditegakkan di level database lewat trigger ini.
     *
     * Dijaga pada INSERT dan UPDATE. Kalau hanya INSERT, baris yang sudah
     * ada masih bisa dipindahkan ke rekening bank lewat UPDATE dan baseline
     * bisa rusak tanpa ketahuan.
     */
    private function jagaHanyaRekeningKas(): void
    {
        $prefix  = (string) $this->db->prefix;
        $tabel   = $this->quoteIdent($prefix . 'opening_kas');
        $akunTbl = $this->quoteIdent($prefix . 'akun_kas_bank');

        foreach (['INSERT', 'UPDATE'] as $acara) {
            $nama = 'trg_opening_kas_hanya_kas_' . strtolower($acara);

            if ($this->sudahAdaTrigger($nama)) {
                continue;
            }

            $sql = 'CREATE TRIGGER ' . $this->quoteIdent($nama)
                 . ' BEFORE ' . $acara . ' ON ' . $tabel
                 . ' FOR EACH ROW'
                 . ' BEGIN'
                 . ' IF NOT EXISTS (SELECT 1 FROM ' . $akunTbl
                 . ' WHERE idakun_kas_bank = NEW.akun_kas_bank_id AND tipe = \'KAS\') THEN'
                 . ' SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT ='
                 . ' \'opening_kas hanya untuk rekening tipe KAS; statement bank memakai saldo_awal_kas_bank\';'
                 . ' END IF;'
                 . ' END';

            $this->db->query($sql);
        }
    }

    private function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function sudahAdaTrigger(string $nama): bool
    {
        $rows = $this->db->query('SHOW TRIGGERS LIKE ' . $this->db->escape($this->db->prefix . 'opening_kas'))
            ->getResultArray();

        foreach ($rows as $r) {
            if (strtolower((string) $r['Trigger']) === strtolower($nama)) {
                return true;
            }
        }

        return false;
    }
}