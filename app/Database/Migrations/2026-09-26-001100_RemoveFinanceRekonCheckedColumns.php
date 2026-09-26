<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hilangkan konsep "Sudah Diperiksa" (checked_*) dari Rekonsiliasi Harian.
 *
 * Latar belakang: status hasil rekonsiliasi kini 100% otomatis dari
 * perbandingan ERP vs Aktual. Tidak ada lagi input manual yang menentukan
 * COCOK / SELISIH, sehingga kolom checked_* menjadi kode mati.
 *
 * Definisi "lengkap" yang baru:
 *   actual_cash_masuk     IS NOT NULL
 *   actual_transfer_masuk IS NOT NULL
 *   actual_kas_keluar     IS NOT NULL
 *
 * Konversi data (penting, tidak menghapus apa pun):
 * Pada skema lama kolom actual_* tidak bisa membedakan "belum diisi" dari
 * "diisi 0", karena form yang kosong disimpan sebagai 0. Sebelum kolom
 * checked_* dihapus, nilai tersebut dikembalikan menjadi NULL supaya tidak
 * salah dihitung sebagai hari lengkap:
 *   checked_* = 0 DAN actual_* = 0  ->  actual_* = NULL  (belum pernah diisi)
 *   checked_* = 1 DAN actual_* = 0  ->  tetap 0           (benar-benar diisi 0)
 *   actual_* > 0                    ->  tidak tersentuh
 *
 * Kolom ERP, catatan, input_by, dan seluruh data approval (status_proses,
 * submitted_by/at, verified_by/at, catatan_revisi) tidak pernah disentuh.
 * Kolom selisih_* hanya ikut di-NULL-kan bila actual_* di-NULL-kan, karena
 * selisih = actual - erp tidak terdefinisi saat actual kosong.
 *
 * Migration ini idempoten: aman untuk DB yang tabelnya sudah tanpa checked_*
 * (fresh install yang menjalankan 000900 versi terbaru).
 *
 * @method array<int, string> getFieldNames(string $table)
 */
class RemoveFinanceRekonCheckedColumns extends Migration
{
    /** Kolom checked_* yang dihapus. */
    private array $checkedColumns = [
        'checked_cash_masuk',
        'checked_transfer_masuk',
        'checked_kas_keluar',
    ];

    /**
     * Pasangan kolom untuk konversi NULL: checked -> actual -> selisih.
     */
    private array $pairs = [
        ['checked_cash_masuk', 'actual_cash_masuk', 'selisih_cash_masuk'],
        ['checked_transfer_masuk', 'actual_transfer_masuk', 'selisih_transfer_masuk'],
        ['checked_kas_keluar', 'actual_kas_keluar', 'selisih_kas_keluar'],
    ];

    private ?array $fieldCache = null;

    /**
     * @return array<int, string> nama kolom tabel (lowercase)
     */
    private function fieldNames(): array
    {
        if ($this->fieldCache === null) {
            $names = [];

            // getFieldNames() ada di BaseConnection (objek runtime-nya MySQLi\Connection)
            // tapi tidak dideklarasikan di ConnectionInterface, jadi dipanggil
            // lewat callable supaya aman bila driver tidak menyediakannya.
            $reader = [$this->db, 'getFieldNames'];
            if (is_callable($reader)) {
                foreach ($reader('finance_rekon_daily') as $name) {
                    $names[] = strtolower($name);
                }
            }

            $this->fieldCache = $names;
        }

        return $this->fieldCache;
    }

    public function up()
    {
        $existing = $this->fieldNames();

        if ($existing === []) {
            // Tabel belum ada (mis. DB baru yang migration-nya di-skip).
            return;
        }

        // 1) Kembalikan "belum diisi" menjadi NULL SEBELUM kolom checked_* dihapus.
        $hasChecked = array_intersect($this->checkedColumns, $existing) !== [];

        if ($hasChecked) {
            foreach ($this->pairs as [$checked, $actual, $selisih]) {
                if (! in_array($actual, $existing, true)) {
                    continue;
                }

                // actual = 0 sementara belum pernah diperiksa -> NULL.
                $this->db->table('finance_rekon_daily')
                    ->where($checked, 0)
                    ->where($actual, 0)
                    ->update([$actual => null]);

                // Selisih ikut NULL karena tidak terdefinisi saat actual kosong.
                if (in_array($selisih, $existing, true)) {
                    $this->db->table('finance_rekon_daily')
                        ->where($actual, null)
                        ->update([$selisih => null]);
                }
            }
        }

        // 2) Drop kolom checked_* yang masih ada.
        $toDrop = array_values(array_intersect($this->checkedColumns, $existing));
        if ($toDrop !== []) {
            $this->forge->dropColumn('finance_rekon_daily', $toDrop, true);
        }
    }

    public function down()
    {
        $existing = $this->fieldNames();

        if ($existing === []) {
            return;
        }

        // 1) Kembalikan kolom checked_* (default 0 = belum diperiksa).
        $toAdd = array_values(array_diff($this->checkedColumns, $existing));
        if ($toAdd !== []) {
            $this->forge->addColumn('finance_rekon_daily', array_fill_keys($toAdd, [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ]), true);

            $existing = array_merge($existing, $toAdd);
        }

        // 2) Rekonstruksi nilai checked_* dari status aktual saat ini:
        //    actual terisi -> 1, actual NULL -> 0.
        foreach ($this->pairs as [$checked, $actual, $selisih]) {
            if (! in_array($actual, $existing, true) || ! in_array($checked, $existing, true)) {
                continue;
            }

            $this->db->table('finance_rekon_daily')
                ->where($actual, null)
                ->update([$checked => 0]);

            $this->db->table('finance_rekon_daily')
                ->where($actual, '!=', null)
                ->update([$checked => 1]);
        }
    }
}
