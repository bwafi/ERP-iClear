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
            ->orderBy('idmutasi', 'ASC')
            ->get()
            ->getResult();

        $original = [];
        $final    = [];
        $groups   = [];
        $used     = [];

        foreach ($rows as $row) {
            $id  = (int) $row->idmutasi;
            $raw = $row->no_nota_mutasi;
            $original[$id] = $raw;

            // Index unik MySQL menganggap string kosong sebagai satu nilai;
            // NULL boleh berulang.
            if ($raw === null || trim((string) $raw) === '') {
                $final[$id] = null;
                continue;
            }

            $nota = (string) $raw;
            $key  = $this->key($nota);

            if (! isset($groups[$key])) {
                $groups[$key] = ['nota' => rtrim($nota), 'ids' => []];
                $used[$key]  = true;
                $final[$id]  = $nota;
            } else {
                $groups[$key]['ids'][] = $id;
            }
        }

        foreach ($groups as $group) {
            $seq = 2;

            foreach ($group['ids'] as $id) {
                do {
                    $candidate = $group['nota'] . '-R' . $seq++;
                    $candKey   = $this->key($candidate);
                } while (isset($used[$candKey]));

                if (strlen($candidate) > 45) {
                    throw new \RuntimeException("Nomor nota mutasi terlalu panjang untuk diberi suffix: {$candidate}");
                }

                $used[$candKey] = true;
                $final[$id]     = $candidate;
            }
        }

        // Preflight sebelum menulis: unique index MySQL memakai collation,
        // jadi bandingkan versi lowercase + tanpa spasi akhir.
        $seen = [];
        foreach ($final as $value) {
            if ($value === null) {
                continue;
            }
            $key = $this->key($value);
            if (isset($seen[$key])) {
                throw new \RuntimeException("Nomor nota mutasi masih duplikat: {$value}");
            }
            $seen[$key] = true;
        }

        foreach ($final as $id => $value) {
            if ($original[$id] === $value) {
                continue;
            }
            $this->db->table('mutasi')
                ->where('idmutasi', $id)
                ->update(['no_nota_mutasi' => $value]);
        }

        if (! isset($this->db->getIndexData('mutasi')['uniq_mutasi_no_nota'])) {
            $table = $this->db->prefixTable('mutasi');
            $this->db->query("CREATE UNIQUE INDEX uniq_mutasi_no_nota ON `{$table}` (`no_nota_mutasi`)");
        }
    }

    private function key(string $nota): string
    {
        return strtolower(rtrim($nota));
    }

    public function down()
    {
        // Renumber data historis tidak dibalik otomatis.
    }
}
