<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus status PUBLISHED dari workflow content — setelah QC PASS (APPROVED)
 * langsung COMPLETED. Data lama ber-status PUBLISHED dimigrasikan ke COMPLETED
 * (tanggal selesai dipakai published_at bila completed_at kosong).
 */
class RemovePublishedContentStatus extends Migration
{
    public function up()
    {
        \Config\Database::connect()->query(
            "UPDATE contents
             SET status = 'COMPLETED',
                 completed_at = COALESCE(completed_at, published_at),
                 published_at = COALESCE(published_at, updated_at)
             WHERE status = 'PUBLISHED'"
        );

        $this->forge->modifyColumn('contents', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['DRAFT', 'PRODUCTION', 'QC', 'APPROVED', 'COMPLETED', 'REVISION'],
                'default'    => 'DRAFT',
                'null'       => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('contents', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['DRAFT', 'PRODUCTION', 'QC', 'APPROVED', 'PUBLISHED', 'COMPLETED', 'REVISION'],
                'default'    => 'DRAFT',
                'null'       => false,
            ],
        ]);
    }
}