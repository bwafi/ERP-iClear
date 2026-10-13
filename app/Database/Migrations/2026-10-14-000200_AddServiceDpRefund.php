<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Audit fields and balanced reversal templates for full service-DP refunds. */
class AddServiceDpRefund extends Migration
{
    private const REFUND_TEMPLATES = [
        [
            'kode_template' => 'refund_dp_service_tunai',
            'no_akun' => '2010302000',
            'nama_akun' => 'Pendapatan Diterima di Muka (Jasa Service)',
            'debet_kredit' => 'debet',
        ],
        [
            'kode_template' => 'refund_dp_service_tunai',
            'no_akun' => '1010101000',
            'nama_akun' => 'Kas Besar (Cash on Hand)',
            'debet_kredit' => 'kredit',
        ],
        [
            'kode_template' => 'refund_dp_service_bank',
            'no_akun' => '2010302000',
            'nama_akun' => 'Pendapatan Diterima di Muka (Jasa Service)',
            'debet_kredit' => 'debet',
        ],
        [
            'kode_template' => 'refund_dp_service_bank',
            'no_akun' => '1010102000',
            'nama_akun' => 'Kas di Bank',
            'debet_kredit' => 'kredit',
        ],
    ];

    public function up()
    {
        if (! $this->db->tableExists('service_dp')) {
            return;
        }

        $fields = [
            'refund_idkas_keluar' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'refund_tanggal' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'refund_metode' => [
                'type'       => 'ENUM',
                'constraint' => ['tunai', 'transfer'],
                'null'       => true,
            ],
            'refund_bank_idbank' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'refund_referensi' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'refund_alasan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'refund_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'refund_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ];

        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, 'service_dp')) {
                $this->forge->addColumn('service_dp', [$name => $definition]);
            }
        }

        if ($this->db->tableExists('template_jurnal')) {
            foreach (self::REFUND_TEMPLATES as $template) {
                $exists = $this->db->table('template_jurnal')
                    ->where('kode_template', $template['kode_template'])
                    ->where('no_akun', $template['no_akun'])
                    ->where('debet_kredit', $template['debet_kredit'])
                    ->where('array_value', 0)
                    ->countAllResults();

                if ($exists === 0) {
                    $this->db->table('template_jurnal')->insert(array_merge($template, [
                        'array_value' => 0,
                        'keterangan' => 'Refund DP service',
                    ]));
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('service_dp')
            && $this->db->fieldExists('refund_idkas_keluar', 'service_dp')
            && $this->db->table('service_dp')->where('refund_idkas_keluar IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Refund DP sudah dicatat; audit refund tidak dapat dihapus lewat rollback migrasi.');
        }

        if ($this->db->tableExists('template_jurnal')) {
            $this->db->table('template_jurnal')
                ->whereIn('kode_template', ['refund_dp_service_tunai', 'refund_dp_service_bank'])
                ->delete();
        }

        if ($this->db->tableExists('service_dp')) {
            foreach ([
                'refund_idkas_keluar',
                'refund_tanggal',
                'refund_metode',
                'refund_bank_idbank',
                'refund_referensi',
                'refund_alasan',
                'refund_by',
                'refund_at',
            ] as $field) {
                if ($this->db->fieldExists($field, 'service_dp')) {
                    $this->forge->dropColumn('service_dp', $field);
                }
            }
        }
    }
}
