<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Schema fitur Kas & Bank + Pembayaran Antar Unit.
 *
 * - Tabel baru: akun_kas_bank (master akun per unit), saldo_awal_kas_bank,
 *   transaksi_kas_bank (ledger per unit dengan idempotency guard).
 * - Alterasi minimal & kompatibel pada registry existing:
 *   hutang_piutang (pihak_tipe + 'unit', sumber_tipe + 'mutasi_unit',
 *   kolom lawan_unit_id, UNIQUE sumber) dan pembayaran_hutang_piutang
 *   (sumber + 'antar_unit').
 *
 * Tidak mengubah struktur mutasi/detail_mutasi/kas_masuk/kas_keluar/jurnal.
 */
class CreateKasBankSchema extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('akun_kas_bank')) {
            $this->forge->addField([
                'idakun_kas_bank' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                    'comment'    => 'FK unit.idunit (cabang)',
                ],
                'tipe' => [
                    'type'       => 'ENUM',
                    'constraint' => ['KAS', 'BANK'],
                    'null'       => false,
                ],
                'nama_akun' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => false,
                ],
                'bank_idbank' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                    'comment'    => 'FK bank.idbank, wajib untuk tipe BANK',
                ],
                'no_akun_coa' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'comment'    => 'FK no_akun.no_akun (mapping akuntansi, bukan source of truth)',
                ],
                'status' => [
                    'type'       => "ENUM('aktif','nonaktif')",
                    'default'    => 'aktif',
                    'null'       => false,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('idakun_kas_bank', true);
            $this->forge->addKey(['unit_id', 'tipe']);
            $this->forge->addUniqueKey(['unit_id', 'nama_akun']);
            $this->forge->createTable('akun_kas_bank', true);
        }

        if (!$this->db->tableExists('saldo_awal_kas_bank')) {
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
                ],
                'tanggal' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'saldo' => [
                    'type'       => 'BIGINT',
                    'null'       => false,
                    'comment'    => 'harus > 0, bukan pemasukan / bukan Net Cash Flow',
                ],
                'keterangan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'input_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('akun_kas_bank_id');
            $this->forge->createTable('saldo_awal_kas_bank', true);
        }

        if (!$this->db->tableExists('transaksi_kas_bank')) {
            $this->forge->addField([
                'idtransaksi' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'tanggal' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                ],
                'akun_kas_bank_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => false,
                ],
                'jenis' => [
                    'type'       => "ENUM('PEMASUKAN','PENGELUARAN','TRANSFER_INTERNAL','PEMBAYARAN_ANTAR_UNIT')",
                    'null'       => false,
                ],
                'arah' => [
                    'type'       => "ENUM('MASUK','KELUAR')",
                    'null'       => true,
                ],
                'jumlah' => [
                    'type'       => 'BIGINT',
                    'null'       => false,
                ],
                'akun_tujuan_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'transfer_ref' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 45,
                    'null'       => true,
                ],
                'sumber_tipe' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => true,
                ],
                'sumber_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'keterangan' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'bukti' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'input_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('idtransaksi', true);
            $this->forge->addKey(['akun_kas_bank_id', 'tanggal']);
            $this->forge->addKey('akun_tujuan_id');
            $this->forge->addKey('transfer_ref');
            $this->forge->addUniqueKey(
                ['sumber_tipe', 'sumber_id', 'akun_kas_bank_id', 'arah'],
                'uniq_kasbank_sumber'
            );
            $this->forge->createTable('transaksi_kas_bank', true);
        }

        $this->alterHutangPiutang('up');
        $this->alterPembayaranHutangPiutang('up');
    }

    public function down()
    {
        $this->alterPembayaranHutangPiutang('down');
        $this->alterHutangPiutang('down');

        if ($this->db->tableExists('transaksi_kas_bank')) {
            $this->forge->dropTable('transaksi_kas_bank', true);
        }
        if ($this->db->tableExists('saldo_awal_kas_bank')) {
            $this->forge->dropTable('saldo_awal_kas_bank', true);
        }
        if ($this->db->tableExists('akun_kas_bank')) {
            $this->forge->dropTable('akun_kas_bank', true);
        }
    }

    private function alterHutangPiutang(string $direction): void
    {
        $db = $this->db;

        if ($direction === 'up') {
            $db->query("ALTER TABLE hutang_piutang
                MODIFY COLUMN pihak_tipe ENUM('suplier','pelanggan','pegawai','teknisi','lainnya','unit') NOT NULL");
            $db->query("ALTER TABLE hutang_piutang
                MODIFY COLUMN sumber_tipe ENUM('pembelian','piutang_pelanggan','kasbon','piutang_legacy','jasa_teknisi','kelebihan_transfer','retur_barang','manual','mutasi_unit') NOT NULL");

            $cols = $db->query("SHOW COLUMNS FROM hutang_piutang LIKE 'lawan_unit_id'")->getResultArray();
            if (count($cols) === 0) {
                $db->query("ALTER TABLE hutang_piutang
                    ADD COLUMN lawan_unit_id INT(11) NULL AFTER pihak_id");
            }

            $keys = $db->query("SHOW INDEX FROM hutang_piutang WHERE Key_name = 'uniq_hp_sumber'")->getResultArray();
            $hasJenis = false;
            foreach ($keys as $k) {
                if (($k['Column_name'] ?? '') === 'jenis') {
                    $hasJenis = true;
                }
            }

            // Kalau index lama hanya (sumber_tipe, sumber_id), lebarkan jadi
            // (sumber_tipe, sumber_id, jenis) agar satu mutasi bisa punya pasangan
            // piutang + hutang sekaligus.
            if (count($keys) > 0 && !$hasJenis) {
                $db->query("ALTER TABLE hutang_piutang DROP INDEX uniq_hp_sumber");
                $keys = [];
            }

            if (count($keys) === 0) {
                $db->query("ALTER TABLE hutang_piutang
                    ADD UNIQUE KEY uniq_hp_sumber (sumber_tipe, sumber_id, jenis)");
            }
        } else {
            $keys = $db->query("SHOW INDEX FROM hutang_piutang WHERE Key_name = 'uniq_hp_sumber'")->getResultArray();
            if (count($keys) > 0) {
                $db->query("ALTER TABLE hutang_piutang DROP INDEX uniq_hp_sumber");
            }

            $cols = $db->query("SHOW COLUMNS FROM hutang_piutang LIKE 'lawan_unit_id'")->getResultArray();
            if (count($cols) > 0) {
                $db->query("ALTER TABLE hutang_piutang DROP COLUMN lawan_unit_id");
            }

            $db->query("ALTER TABLE hutang_piutang
                MODIFY COLUMN pihak_tipe ENUM('suplier','pelanggan','pegawai','teknisi','lainnya') NOT NULL");
            $db->query("ALTER TABLE hutang_piutang
                MODIFY COLUMN sumber_tipe ENUM('pembelian','piutang_pelanggan','kasbon','piutang_legacy','jasa_teknisi','kelebihan_transfer','retur_barang','manual') NOT NULL");
        }
    }

    private function alterPembayaranHutangPiutang(string $direction): void
    {
        $db = $this->db;

        if ($direction === 'up') {
            $db->query("ALTER TABLE pembayaran_hutang_piutang
                MODIFY COLUMN sumber ENUM('manual','payroll','kompensasi','antar_unit') NOT NULL DEFAULT 'manual'");
        } else {
            $db->query("ALTER TABLE pembayaran_hutang_piutang
                MODIFY COLUMN sumber ENUM('manual','payroll','kompensasi') NOT NULL DEFAULT 'manual'");
        }
    }
}