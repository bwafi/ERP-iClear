<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUnitToAsset extends Migration
{
    public function up()
    {
        // Aset operasional toko perlu diasosiasikan ke unit/toko
        // agar Kontrol Aset bisa dihitung per toko.
        $fields = [
            'unit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'default'    => null,
                'after'      => 'idasset',
            ],
        ];
        $this->forge->addColumn('asset', $fields);

        $this->forge->addKey('unit', false, false, 'asset_unit');
        $this->forge->processIndexes('asset');
    }

    public function down()
    {
        $this->forge->dropColumn('asset', 'unit');
    }
}
