<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Izin/sakit/cuti memakai `akun` sebagai pemilik pengajuan.
 *
 * `pegawai_uuid` lama tidak punya tabel pasangan, jadi dibuat nullable dan
 * tidak dipakai lagi. Jenis perizinan awal diisi agar form pengajuan punya
 * pilihan.
 */
class AddAkunIdakunToIzin extends Migration
{
    private const JENIS = ['IZIN', 'SAKIT', 'CUTI'];

    public function up()
    {
        // Forge/DDL tidak membersihkan cache skema; reset supaya idempoten.
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];

        if (! $this->db->tableExists('izin')) {
            return;
        }

        if (! $this->db->fieldExists('akun_idakun', 'izin')) {
            $this->forge->addColumn('izin', [
                'akun_idakun' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'idizin'],
            ]);
            $this->db->dataCache['field_names'] = [];
        }

        $this->forge->modifyColumn('izin', [
            'pegawai_uuid' => ['name' => 'pegawai_uuid', 'type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);

        $indexes = $this->db->getIndexData('izin');
        if (! isset($indexes['idx_izin_akun'])) {
            $this->forge->addKey('akun_idakun', false, false, 'idx_izin_akun');
            $this->forge->processIndexes('izin');
        }

        if (! $this->db->tableExists('jenis_perizinan')) {
            return;
        }

        foreach (self::JENIS as $jenis) {
            $ada = $this->db->table('jenis_perizinan')->where('jenis_izin', $jenis)->countAllResults();
            if ($ada === 0) {
                $this->db->table('jenis_perizinan')->insert(['jenis_izin' => $jenis]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('izin')) {
            return;
        }

        $indexes = $this->db->getIndexData('izin');
        if (isset($indexes['idx_izin_akun'])) {
            $this->forge->dropKey('izin', 'idx_izin_akun', false);
        }

        if ($this->db->fieldExists('akun_idakun', 'izin')) {
            $this->forge->dropColumn('izin', 'akun_idakun');
        }

        $this->db->dataCache['field_names'] = [];
    }
}
