<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Konsep REKENING FISIK !== UNIT untuk Kas & Bank.
 *
 * Sesuai keputusan bisnis: satu rekening bank fisik (mis. BCA bersama Jember +
 * Probolinggo) boleh dipakai banyak unit. Transaksi tetap wajib unit_id, tapi
 * akun_kas_bank adalah rekening/kas fisik, bukan milik eksklusif satu unit.
 *
 * Perubahan (backward compatible, tidak mengubah/menghapus data transaksi):
 * 1. akun_kas_bank.unit_id  -> NULLABLE (rekening fisik bisa tanpa unit).
 * 2. akun_kas_bank.is_shared -> penanda rekening fisik lintas unit.
 * 3. Index bank_idbank untuk resolve rekening fisik.
 * 4. Tabel baru alokasi_saldo_kas_bank: alokasi saldo awal per unit (UPAYA
 *    laporan/Kesehatan Keuangan), audit-able, TIDAK mengubah saldo fisik.
 * 5. Index (unit_id, akun_kas_bank_id) untuk laporan per unit.
 * 6. Sidebar: submenu Transfer Internal + daftarkan id menu yang relevan ke
 *    ROLES_JABATAN.
 */
class KonsepRekeningFisikKasBank extends Migration
{
    public function up()
    {
        $db = $this->db;

        if ($db->tableExists('akun_kas_bank')) {
            // unit_id nullable: rekening fisik boleh tanpa unit pemilik tunggal.
            $cols = $db->query("SHOW COLUMNS FROM akun_kas_bank LIKE 'unit_id'")->getResultArray();
            if (count($cols) > 0) {
                $db->query('ALTER TABLE akun_kas_bank MODIFY COLUMN unit_id INT(11) NULL COMMENT \'unit pemilik awal; NULL jika rekening fisik lintas unit\'');
            }

            $cols = $db->query("SHOW COLUMNS FROM akun_kas_bank LIKE 'is_shared'")->getResultArray();
            if (count($cols) === 0) {
                $db->query("ALTER TABLE akun_kas_bank
                    ADD COLUMN is_shared TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'rekening fisik dipakai lintas unit'
                    AFTER status");
            }

            $keys = $db->query("SHOW INDEX FROM akun_kas_bank WHERE Key_name = 'idx_akun_kas_bank_bank'")->getResultArray();
            if (count($keys) === 0) {
                $db->query('ALTER TABLE akun_kas_bank ADD INDEX idx_akun_kas_bank_bank (bank_idbank)');
            }
        }

        if (!$db->tableExists('alokasi_saldo_kas_bank')) {
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
                    'comment'    => 'FK akun_kas_bank (rekening fisik)',
                ],
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                    'comment'    => 'FK unit; alokasi saldo awal per unit',
                ],
                'nominal' => [
                    'type'       => 'BIGINT',
                    'null'       => false,
                    'comment'    => 'alokasi saldo awal untuk unit ini (tidak mengubah saldo fisik)',
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
            $this->forge->addUniqueKey(['akun_kas_bank_id', 'unit_id'], 'uniq_alokasi_unit');
            $this->forge->addKey('unit_id');
            $this->forge->createTable('alokasi_saldo_kas_bank', true);
        }

        if ($db->tableExists('transaksi_kas_bank')) {
            $keys = $db->query("SHOW INDEX FROM transaksi_kas_bank WHERE Key_name = 'idx_tkb_unit_akun'")->getResultArray();
            if (count($keys) === 0) {
                $db->query('ALTER TABLE transaksi_kas_bank ADD INDEX idx_tkb_unit_akun (unit_id, akun_kas_bank_id)');
            }
        }

        $this->sidebarUp($db);
    }

    public function down()
    {
        $db = $this->db;

        $this->sidebarDown($db);

        if ($db->tableExists('transaksi_kas_bank')) {
            $keys = $db->query("SHOW INDEX FROM transaksi_kas_bank WHERE Key_name = 'idx_tkb_unit_akun'")->getResultArray();
            if (count($keys) > 0) {
                $db->query('ALTER TABLE transaksi_kas_bank DROP INDEX idx_tkb_unit_akun');
            }
        }

        if ($db->tableExists('alokasi_saldo_kas_bank')) {
            $this->forge->dropTable('alokasi_saldo_kas_bank', true);
        }

        if ($db->tableExists('akun_kas_bank')) {
            $keys = $db->query("SHOW INDEX FROM akun_kas_bank WHERE Key_name = 'idx_akun_kas_bank_bank'")->getResultArray();
            if (count($keys) > 0) {
                $db->query('ALTER TABLE akun_kas_bank DROP INDEX idx_akun_kas_bank_bank');
            }

            $cols = $db->query("SHOW COLUMNS FROM akun_kas_bank LIKE 'is_shared'")->getResultArray();
            if (count($cols) > 0) {
                $db->query('ALTER TABLE akun_kas_bank DROP COLUMN is_shared');
            }

            $cols = $db->query("SHOW COLUMNS FROM akun_kas_bank LIKE 'unit_id'")->getResultArray();
            if (count($cols) > 0) {
                $db->query('ALTER TABLE akun_kas_bank MODIFY COLUMN unit_id INT(11) NOT NULL COMMENT \'FK unit.idunit (cabang)\'');
            }
        }
    }

    /**
     * Sidebar Kas & Bank: pastikan submenu Transfer Internal ada dan id menu
     * yang relevan terdaftar pada jabatan yang sudah punya akses Kas & Bank.
     */
    private function sidebarUp($db): void
    {
        $row = $db->table('menu')->where('idmenu', 10124)->get()->getRow();
        if (!$row) {
            $db->table('menu')->insert([
                'urutan'     => 10124,
                'nama_menu'  => 'Transfer Internal',
                'roles'      => 'transfer_internal',
                'url'        => 'kas_bank/transfer',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10120,
                'utama'      => 0,
                'categories' => 0,
                'icon'       => null,
                'manualbook' => null,
            ]);
        }

        // Pastikan id menu 10121..10124 (Kas & Bank) ada di ROLES_JABATAN yang
        // sudah memiliki 10120 (kategori Kas & Bank).
        $jabatan = $db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult();
        foreach ($jabatan as $j) {
            if (trim((string)$j->ROLES_JABATAN) === '') {
                continue;
            }
            $roles = json_decode((string)$j->ROLES_JABATAN, true);
            if (!is_array($roles)) {
                continue;
            }
            if (!in_array('10120', $roles, true)) {
                continue;
            }
            $tambah = false;
            foreach (['10121', '10122', '10123', '10124'] as $idMenu) {
                if (!in_array($idMenu, $roles, true)) {
                    $roles[] = $idMenu;
                    $tambah = true;
                }
            }
            if ($tambah) {
                $db->table('jabatan')
                    ->where('ID_JABATAN', $j->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
            }
        }
    }

    private function sidebarDown($db): void
    {
        // Hapus hanya menu Transfer Internal (canonical id 10124). Katakan
        // tidak: id 10121..10123 adalah grant milik seed SeedKasBankMenus
        // (000200), bukan bagian dari migration ini, jadi tidak disentuh.
        $row = $db->table('menu')->where('idmenu', 10124)->get()->getRow();
        if ($row) {
            $db->table('menu')->where('idmenu', 10124)->delete();
        }
    }
}