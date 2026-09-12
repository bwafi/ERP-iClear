<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Normalisasi nama platform: "WA Admin" → "WhatsApp", "DM Instagram" → "Instagram".
 * Berlaku untuk master marketing_platform dan nilai platform di detail rekap
 * yang sudah tersimpan. Non-destruktif.
 */
class RenamePlatformNormal extends Migration
{
    protected const RENAME = [
        'WA Admin'     => 'WhatsApp',
        'DM Instagram' => 'Instagram',
    ];

    public function up()
    {
        $db = $this->db;

        foreach (self::RENAME as $old => $new) {
            $db->table('marketing_platform')->where('name', $old)->where('name !=', $new)->update(['name' => $new]);
            if ($db->tableExists('marketing_rekap_harian_detail')) {
                $db->table('marketing_rekap_harian_detail')
                    ->where('platform', $old)
                    ->where('platform !=', $new)
                    ->update(['platform' => $new]);
            }
        }
    }

    public function down()
    {
        $db = $this->db;

        foreach (self::RENAME as $old => $new) {
            $db->table('marketing_platform')->where('name', $new)->where('name !=', $old)->update(['name' => $old]);
            if ($db->tableExists('marketing_rekap_harian_detail')) {
                $db->table('marketing_rekap_harian_detail')
                    ->where('platform', $new)
                    ->where('platform !=', $old)
                    ->update(['platform' => $old]);
            }
        }
    }
}