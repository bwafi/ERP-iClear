<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kasbon mengurangi kas laci.
 *
 * - Kolom link/metode di `hutang_piutang` agar baris kas otomatis bisa
 *   dilacak balik ke kasbonnya.
 * - Kategori kas "Kasbon" khusus (BUKAN kategori 10: "Ka*sbon*" nyangkut di
 *   filter `LIKE '%bon%'` slip gaji).
 * - Template jurnal transfer: input kasbon via bank mengkredit Kas di Bank.
 *
 * Idempotent: seed hanya bila belum ada.
 */
class KasbonKasLaci extends Migration
{
    private const TEMPLATE_BANK = [
        ['1020201000', 'Piutang Pegawai', 'debet'],
        ['1010102000', 'Kas di Bank (Cash in Bank)', 'kredit'],
    ];

    public function up()
    {
        if (! $this->db->tableExists('hutang_piutang')) {
            return;
        }

        foreach ([
            'kas_keluar_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'metode'        => ['type' => 'ENUM', 'constraint' => ['tunai', 'transfer'], 'null' => true],
            'bank_idbank'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ] as $name => $def) {
            if (! $this->db->fieldExists($name, 'hutang_piutang')) {
                $this->forge->addColumn('hutang_piutang', [$name => $def]);
            }
        }

        if ($this->db->tableExists('kategori_kas')) {
            $ada = $this->db->table('kategori_kas')
                ->where('kategori', 'Kasbon')
                ->where('jenis_kas', 'kas_keluar')
                ->countAllResults();
            if ($ada === 0) {
                $this->db->table('kategori_kas')->insert([
                    'kategori'             => 'Kasbon',
                    'kode_template_jurnal' => null,
                    'jenis_kas'            => 'kas_keluar',
                ]);
            }
        }

        if ($this->db->tableExists('template_jurnal')) {
            foreach (self::TEMPLATE_BANK as $row) {
                $exists = $this->db->table('template_jurnal')
                    ->where('kode_template', 'hp_kasbon_input_bank')
                    ->where('no_akun', $row[0])
                    ->where('debet_kredit', $row[2])
                    ->where('array_value', 0)
                    ->countAllResults();
                if ($exists === 0) {
                    $this->db->table('template_jurnal')->insert([
                        'kode_template' => 'hp_kasbon_input_bank',
                        'no_akun'       => $row[0],
                        'nama_akun'     => $row[1],
                        'debet_kredit'  => $row[2],
                        'array_value'   => 0,
                        'keterangan'    => 'Hutang Piutang',
                    ]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hutang_piutang')
            && $this->db->fieldExists('kas_keluar_id', 'hutang_piutang')
            && $this->db->table('hutang_piutang')->where('kas_keluar_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Kasbon sudah tertaut ke kas; migrasi tidak bisa di-rollback.');
        }

        if ($this->db->tableExists('template_jurnal')) {
            $this->db->table('template_jurnal')->where('kode_template', 'hp_kasbon_input_bank')->delete();
        }

        if ($this->db->tableExists('kategori_kas')) {
            $this->db->table('kategori_kas')->where('kategori', 'Kasbon')->where('jenis_kas', 'kas_keluar')->delete();
        }

        if ($this->db->tableExists('hutang_piutang')) {
            foreach (['kas_keluar_id', 'metode', 'bank_idbank'] as $field) {
                if ($this->db->fieldExists($field, 'hutang_piutang')) {
                    $this->forge->dropColumn('hutang_piutang', $field);
                }
            }
        }
    }
}
