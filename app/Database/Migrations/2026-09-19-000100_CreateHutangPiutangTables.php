<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Hutang Piutang — registry terpusat + pembayaran.
 *
 * Prinsip anti-duplikasi:
 *  - `hutang_piutang` adalah layer terpusat untuk membaca/mengelola posisi
 *    hutang & piutang. Baris dengan is_projection = 1 TIDAK menyimpan saldo
 *    otoritatif; saldonya dibaca dari tabel sumber (pembelian / piutang).
 *  - Jenis baru (piutang_pelanggan, kasbon) bersifat authoritative:
 *    total/sisa/status dihitung oleh HutangPiutangService.
 *  - Pembayaran pembelian existing tetap di `pembayaran_hutang`; modul ini
 *    hanya membuat baris registry (projection) sebagai penunjuk.
 *
 * Sumber & tabel pembayaran:
 *  | sumber_tipe        | authoritative | pembayaran                | projection |
 *  |--------------------|---------------|---------------------------|------------|
 *  | pembelian          | pembelian     | pembayaran_hutang         | 1          |
 *  | piutang_legacy     | piutang       | pembayaran_piutang        | 1          |
 *  | piutang_pelanggan  | tabel ini     | pembayaran_hutang_piutang | 0          |
 *  | kasbon             | tabel ini     | pembayaran_hutang_piutang | 0          |
 *
 * Idempotent: cek keberadaan tabel/kolom/komponen sebelum membuat; backfill
 * projection memakai INSERT IGNORE (UNIQUE sumber_tipe + sumber_id).
 */
class CreateHutangPiutangTables extends Migration
{
    public function up()
    {
        // ------------------------------------------------------------------
        // 1. Registry terpusat
        // ------------------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'kode' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['hutang', 'piutang'],
            ],
            'sumber_tipe' => [
                'type'       => 'ENUM',
                'constraint' => ['pembelian', 'piutang_pelanggan', 'kasbon', 'piutang_legacy'],
            ],
            'sumber_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'is_projection' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'pihak_tipe' => [
                'type'       => 'ENUM',
                'constraint' => ['suplier', 'pelanggan', 'pegawai'],
            ],
            'pihak_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'nama_pihak' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'tanggal' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'jatuh_tempo' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'uraian' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'total' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'total_dibayar' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'sisa' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['belum_lunas', 'sebagian', 'lunas'],
                'default'    => 'belum_lunas',
            ],
            'keterangan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'unit_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'input_by' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'deleted' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
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
        $this->forge->addKey('kode', false, true, 'uniq_hp_kode');
        $this->forge->addKey(['sumber_tipe', 'sumber_id'], false, true, 'uniq_hp_sumber');
        $this->forge->addKey(['jenis', 'status'], false, false, 'idx_hp_jenis_status');
        $this->forge->addKey(['pihak_tipe', 'pihak_id'], false, false, 'idx_hp_pihak');
        $this->forge->addKey('unit_id', false, false, 'idx_hp_unit');
        $this->forge->createTable('hutang_piutang', true);

        // ------------------------------------------------------------------
        // 2. Pembayaran untuk jenis baru (piutang_pelanggan, kasbon)
        // ------------------------------------------------------------------
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'hutang_piutang_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'tanggal_bayar' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'jumlah_bayar' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'bayar_tunai' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'bayar_bank' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'bank_idbank' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'sumber' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'payroll'],
                'default'    => 'manual',
            ],
            'referensi_tipe' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'referensi_id' => [
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
        $this->forge->addKey('hutang_piutang_id', false, false, 'idx_php_hp');
        $this->forge->addKey(['sumber', 'referensi_tipe', 'referensi_id'], false, true, 'uniq_php_referensi');
        $this->forge->createTable('pembayaran_hutang_piutang', true);

        // ------------------------------------------------------------------
        // 3. Kolom potongan kasbon pada register payroll
        // ------------------------------------------------------------------
        if (!$this->db->fieldExists('potongan_kasbon', 'finance_payroll')) {
            $this->forge->addColumn('finance_payroll', [
                'potongan_kasbon' => [
                    'type'    => 'BIGINT',
                    'default' => 0,
                    'after'   => 'total',
                ],
                'total_bersih' => [
                    'type'    => 'BIGINT',
                    'default' => 0,
                    'after'   => 'potongan_kasbon',
                ],
            ]);
        }

        // ------------------------------------------------------------------
        // 4. Komponen gaji KASBON (deduction) untuk slip gaji
        // ------------------------------------------------------------------
        $exists = $this->db->table('salary_components')->where('code', 'KASBON')->countAllResults();
        if ($exists === 0) {
            $this->db->table('salary_components')->insert([
                'code'          => 'KASBON',
                'name'          => 'Kasbon',
                'type'          => 'deduction',
                'description'   => 'Potongan kasbon pegawai (settlement dari Payroll).',
                'default_value' => 0,
                'is_active'     => 1,
                'is_configurable' => 1,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        // ------------------------------------------------------------------
        // 5. Backfill projection data existing (idempotent)
        // ------------------------------------------------------------------
        $this->db->query("
            INSERT IGNORE INTO hutang_piutang
                (kode, jenis, sumber_tipe, sumber_id, is_projection, pihak_tipe, pihak_id,
                 nama_pihak, tanggal, jatuh_tempo, uraian, total, total_dibayar, sisa,
                 status, unit_id, deleted, created_at, updated_at)
            SELECT
                CONCAT('HUT-PB', p.idpembelian),
                'hutang', 'pembelian', p.idpembelian, 1, 'suplier', p.suplier_id_suplier,
                COALESCE(NULLIF(s.nama_suplier, ''), '-'),
                p.tanggal_masuk, p.jatuh_tempo, p.no_nota_supplier,
                COALESCE(p.total_bayar, 0), COALESCE(p.bayar, 0), COALESCE(p.sisa, 0),
                CASE
                    WHEN COALESCE(p.sisa, 0) <= 0 THEN 'lunas'
                    WHEN COALESCE(p.bayar, 0) > 0 THEN 'sebagian'
                    ELSE 'belum_lunas'
                END,
                p.unit_idunit, 0, NOW(), NOW()
            FROM pembelian p
            LEFT JOIN suplier s ON s.id_suplier = p.suplier_id_suplier
        ");

        $this->db->query("
            INSERT IGNORE INTO hutang_piutang
                (kode, jenis, sumber_tipe, sumber_id, is_projection, pihak_tipe, pihak_id,
                 nama_pihak, tanggal, jatuh_tempo, uraian, total, total_dibayar, sisa,
                 status, unit_id, deleted, created_at, updated_at)
            SELECT
                CONCAT('PUT-PG', pt.idpiutang),
                'piutang', 'piutang_legacy', pt.idpiutang, 1, 'pegawai', pt.pegawai_idpegawai,
                COALESCE(NULLIF(a.NAMA_AKUN, ''), '-'),
                pt.tanggal, pt.jatuh_tempo, pt.kode_piutang,
                COALESCE(pt.jumlah_hutang, 0),
                COALESCE(pt.jumlah_hutang, 0) - COALESCE(pt.sisa_hutang, 0),
                COALESCE(pt.sisa_hutang, 0),
                CASE
                    WHEN COALESCE(pt.sisa_hutang, 0) <= 0 THEN 'lunas'
                    WHEN (COALESCE(pt.jumlah_hutang, 0) - COALESCE(pt.sisa_hutang, 0)) > 0 THEN 'sebagian'
                    ELSE 'belum_lunas'
                END,
                pt.unit_idunit, 0, NOW(), NOW()
            FROM piutang pt
            LEFT JOIN akun a ON a.ID_AKUN = pt.pegawai_idpegawai
        ");
    }

    public function down()
    {
        $this->db->table('salary_components')->where('code', 'KASBON')->delete();

        if ($this->db->fieldExists('potongan_kasbon', 'finance_payroll')) {
            $this->forge->dropColumn('finance_payroll', ['potongan_kasbon', 'total_bersih']);
        }

        $this->forge->dropTable('pembayaran_hutang_piutang', true);
        $this->forge->dropTable('hutang_piutang', true);
    }
}
