<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rapikan nomor nota mutasi lama yang duplikat, lalu pasang unique index.
 *
 * Baris pertama tiap grup mempertahankan nomor asli; baris berikutnya diberi
 * suffix `-R2`, `-R3`, dst. Idempotent: setelah tidak ada duplikat, `up()`
 * hanya memastikan index sudah ada.
 */
class RenumberDanUnikkanNoNotaMutasi extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('mutasi')) {
            return;
        }

        $rows = $this->db->table('mutasi')
            ->select('idmutasi, no_nota_mutasi')
            ->where('no_nota_mutasi IS NOT NULL', null, false)
            ->where('no_nota_mutasi !=', '')
            ->orderBy('idmutasi', 'ASC')
            ->get()
            ->getResult();

        $groups = [];
        $used   = [];

        foreach ($rows as $row) {
            $nota = (string) $row->no_nota_mutasi;
            $groups[$nota][] = (int) $row->idmutasi;
            $used[$nota] = true;
        }

        foreach ($groups as $nota => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            array_shift($ids);
            $seq = 2;

            foreach ($ids as $id) {
                do {
                    $candidate = $nota . '-R' . $seq++;
                } while (isset($used[$candidate]));

                $this->db->table('mutasi')
                    ->where('idmutasi', $id)
                    ->update(['no_nota_mutasi' => $candidate]);

                $used[$candidate] = true;
            }
        }

        if (! isset($this->db->getIndexData('mutasi')['uniq_mutasi_no_nota'])) {
            $table = $this->db->prefixTable('mutasi');
            $this->db->query("CREATE UNIQUE INDEX uniq_mutasi_no_nota ON `{$table}` (`no_nota_mutasi`)");
        }
    }

    public function down()
    {
        // Renumber data historis tidak dibalik otomatis.
    }
}
