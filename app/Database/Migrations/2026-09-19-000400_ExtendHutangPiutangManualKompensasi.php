<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Hutang Piutang — perluasan sumber input manual + kompensasi.
 *
 * Perubahan bersifat ADDITIVE (tidak destruktif, aman untuk dump production):
 *  1. `hutang_piutang.sumber_tipe` diperluas dengan:
 *       jasa_teknisi, kelebihan_transfer, retur_barang, manual
 *  2. `hutang_piutang.pihak_tipe` diperluas dengan:
 *       teknisi, lainnya
 *  3. Tabel `kompensasi_hutang_piutang` untuk alokasi piutang terhadap hutang
 *     satu pihak yang sama (audit trail relasi kedua record; record asal TIDAK
 *     dihapus).
 *
 * CATATAN integritas:
 *  - Jenis sumber di atas diinput MANUAL melalui modul ini (authoritative),
 *    bukan projection. Proyeksi otomatis dari modul existing TIDAK diaktifkan
 *    untuk retur/kelebihan transfer karena tabel existing belum menyimpan
 *    status moneter (nilai retur & apakah uang sudah dikembalikan), sehingga
 *    proyeksi otomatis berisiko menciptakan piutang palsu (double count).
 *  - `down()` hanya membuang tabel kompensasi & template jurnal yang disemai;
 *    enum TIDAK dipersempit agar tidak kehilangan data.
 */
class ExtendHutangPiutangManualKompensasi extends Migration
{
    public function up()
    {
        // ------------------------------------------------------------------
        // 1 & 2. Perluas enum (idempotent: nilai final sama bila diulang)
        // ------------------------------------------------------------------
        $this->db->query("
            ALTER TABLE `hutang_piutang`
            MODIFY COLUMN `sumber_tipe`
            ENUM('pembelian','piutang_pelanggan','kasbon','piutang_legacy',
                 'jasa_teknisi','kelebihan_transfer','retur_barang','manual')
            NOT NULL
        ");

        $this->db->query("
            ALTER TABLE `hutang_piutang`
            MODIFY COLUMN `pihak_tipe`
            ENUM('suplier','pelanggan','pegawai','teknisi','lainnya')
            NOT NULL
        ");

        // ------------------------------------------------------------------
        // 3. Tabel kompensasi (relasi hutang <-> piutang sepihak)
        // ------------------------------------------------------------------
        if (!$this->db->tableExists('kompensasi_hutang_piutang')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'tanggal' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'hutang_piutang_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'piutang_piutang_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                ],
                'jumlah' => [
                    'type'    => 'BIGINT',
                    'default' => 0,
                ],
                'pihak_tipe' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'pihak_id' => [
                    'type'       => 'INTEGER',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'keterangan' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'input_by' => [
                    'type'       => 'INTEGER',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('hutang_piutang_id', false, false, 'idx_khp_hutang');
            $this->forge->addKey('piutang_piutang_id', false, false, 'idx_khp_piutang');
            $this->forge->addKey(['pihak_tipe', 'pihak_id'], false, false, 'idx_khp_pihak');
            $this->forge->createTable('kompensasi_hutang_piutang', true);
        }

        // ------------------------------------------------------------------
        // 4. Izinkan sumber pembayaran 'kompensasi'
        // ------------------------------------------------------------------
        $this->db->query("
            ALTER TABLE `pembayaran_hutang_piutang`
            MODIFY COLUMN `sumber`
            ENUM('manual','payroll','kompensasi')
            NOT NULL DEFAULT 'manual'
        ");
    }

    public function down()
    {
        $this->forge->dropTable('kompensasi_hutang_piutang', true);
    }
}
