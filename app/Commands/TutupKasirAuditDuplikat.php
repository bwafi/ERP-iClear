<?php

namespace App\Commands;

use App\Services\Finance\TutupKasirClosing;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Audit duplicate Tutup Kasir — READ ONLY.
 *
 *   php74 spark tutupkasir:audit-duplikat
 *   php74 spark tutupkasir:audit-duplikat --pair 2026-10-02:3
 *   php74 spark tutupkasir:audit-duplikat --legacy
 *
 * ================================================================
 * KENAPA PERINTAH INI ADA
 * ================================================================
 * `tutup_kasir` TIDAK punya unique index pada (tanggal, unit). Index itu
 * sengaja belum dipasang karena datanya sekarang sudah melanggar constraint
 * tersebut — memasang migration yang gagal di tengah jalan lebih buruk
 * daripada tidak punya index.
 *
 * Duplicate itu sendiri tidak bisa dihapus diam-diam: `tutup_kasir` adalah
 * catatan bendahara, dan baris yang dibuang mengubah angka yang sudah
 * dilaporkan. Karena itu pemeriksaan ini dibuat PERMANEN dan read-only:
 * operator bisa menjalankanya kapan saja untuk melihat apakah data sudah
 * bersih, dan referencedya (ID baris) bisa dipakai untuk memutuskan
 * perbaikan secara sadar.
 *
 * Perintah ini TIDAK PERNAH menulis apa pun. Tidak ada `--fix`, tidak ada
 * `--delete`. Itu disengaja.
 *
 * Lima pasang duplicate historis ditemukan saat hardening:
 *   (2026-10-02, unit 3), (2026-10-01, unit 5), (2026-09-20, unit 3),
 *   (2026-07-24, unit 3), (2026-07-23, unit 3)
 * ditambah satu pasang legacy tanpa `unit` sama sekali (2026-05-20).
 * Pada semua pasang, `akhir_cash` kedua baris IDENTIK — jadi dampaknya
 * bukan saldo yang berbeda, tapi `saldoAwalKas()` yang punya lebih dari satu
 * kandidat sumber. Service itu sudah memilih deterministik
 * (`tanggal DESC, id DESC`), jadi angka tidak lagi berganti-ganti — tapi
 * duplikatnya sendiri tetap harus dibereskan oleh manusia.
 */
class TutupKasirAuditDuplikat extends BaseCommand
{
    protected $group       = 'TutupKasir';
    protected $name        = 'tutupkasir:audit-duplikat';
    protected $description = 'Audit duplicate (tanggal, unit) pada tutup_kasir. READ ONLY — tidak mengubah data apa pun.';
    protected $usage       = 'tutupkasir:audit-duplikat [--pair=Y-m-d:unit] [--legacy]';

    protected $options = [
        // CLI CodeIgniter membaca `--opsi nilai`, bukan `--opsi=nilai`.
        '-pair'   => 'Tampilkan rincian SEMUA baris untuk satu pasang, format YYYY-MM-DD:unit (unit = "null" untuk yang legacy).',
        '-legacy' => 'Tampilkan rincian baris yang `unit`-nya NULL (legacy sebelum unit discrimination).',
    ];

    public function run(array $params)
    {
        $closing = new TutupKasirClosing();
        $pairs   = $closing->duplikat();

        CLI::write('Audit duplicate Tutup Kasir (READ ONLY)', 'yellow');
        CLI::newline();

        $jumlah         = count($pairs);
        $baris_di_pairs = 0;
        $nilai_berbeda  = 0;

        foreach ($pairs as $p) {
            $baris_di_pairs += $p['jumlah'];

            if ($p['saldo_berbeda']) {
                $nilai_berbeda++;
            }

            CLI::write(sprintf(
                '  %s  unit %-5s  %d baris  [%s]  akhir_cash: %s%s',
                $p['tanggal'],
                $p['unit'] === null ? '(null)' : (string) $p['unit'],
                $p['jumlah'],
                implode(', ', $p['id']),
                implode(', ', $p['akhir_cash']),
                $p['saldo_berbeda'] ? '   <-- NILAI BERBEDA, jangan chose diam-diam' : ''
            ));

            if (CLI::getOption('pair') !== null) {
                $ini = sprintf('%s:%s', $p['tanggal'], $p['unit'] === null ? 'null' : $p['unit']);

                if ($ini !== CLI::getOption('pair')) {
                    continue;
                }

                $this->rincian($p['tanggal'], $p['unit']);
            }
        }

        if ($jumlah === 0) {
            CLI::write('  Tidak ada duplicate (tanggal, unit).', 'green');
        }

        CLI::newline();

        if ($nilai_berbeda > 0) {
            CLI::write(
                sprintf(
                    '%d pasang punya `akhir_cash` BERBEDA. Ini kondisi yang paling serius: '
                    . 'saldo pembawa untuk hari berikutnya tidak tunggal, jadi angkanya '
                    . 'bergantung pada baris mana yang dibaca.',
                    $nilai_berbeda
                ),
                'light_red'
            );
        } else {
            CLI::write(
                'Semua pasang duplicate punya `akhir_cash` identik, jadi dampaknya tidak '
                . 'berubah saldo — tapi tetap perlu dibereskan sebelum unique index bisa dipasang.',
                'light_gray'
            );
        }

        if (CLI::getOption('legacy')) {
            $this->rincianLegacy();
        }

        CLI::newline();
        $tanpaUnit = $closing->closingTanpaUnit();

        if ($tanpaUnit > 0) {
            CLI::newline();
            CLI::write(
                sprintf(
                    '%d baris `tutup_kasir` lama tidak punya `unit` (NULL). Baris ini tidak '
                    . 'pernah ikut dihitung sebagai sumber saldo awal unit mana pun, jadi '
                    . 'uangnya tidak masuk ke posisi kasir unit mana pun. Perlu diputuskan: '
                    . 'di-backfill unit-nya, atau ditandai sudah usang.',
                    $tanpaUnit
                ),
                'yellow'
            );
        }

        CLI::newline();

        $siap = $closing->bisaUniqueIndex();

        if ($siap['index_sudah_ada']) {
            CLI::write('Unique index (tanggal, unit) sudah terpasang.', 'green');
        } elseif ($siap['bisa']) {
            CLI::write(
                'Data bersih — unique index (tanggal, unit) bisa dipasang.',
                'green'
            );
        } else {
            CLI::write(
                sprintf(
                    'Unique index (tanggal, unit) BELUM bisa dipasang: masih ada %d pasang duplicate. '
                    . 'Perbaiki dulu secara sadar, lalu jalankan perintah ini lagi.',
                    $siap['jumlah_duplikat']
                ),
                'light_red'
            );
        }

        return 0;
    }

    /**
     * Rincian SEMUA baris untuk satu pasang (tanggal, unit).
     *
     * Read only: hanya SELECT. `closingPada()` sengaja tidak dipakai di sini
     * karena dia hanya mengembalikan SATU baris (yang terpilih) — padahal
     * justru baris-bandingannya yang perlu dibandingkan.
     *
     * `cash_laci` dan `akhir_cash` ditampilkan berdampingan supaya selisih
     * fisik vs sistem kelihatan tanpa perlu dihitung ulang.
     */
    private function rincian(string $tanggal, ?int $unit): void
    {
        CLI::newline();
        CLI::write(
            sprintf('  Rincian %s / unit %s', $tanggal, $unit === null ? '(null)' : (string) $unit),
            'white'
        );

        $b = Database::connect()
            ->table('tutup_kasir')
            ->where('tanggal', $tanggal)
            ->orderBy('idtutupkasir', 'ASC');

        if ($unit === null) {
            $b->where('unit', null);
        } else {
            $b->where('unit', $unit);
        }

        $baris = $b->get()->getResultArray();

        if ($baris === []) {
            CLI::write('    (tidak ada)', 'light_gray');

            return;
        }

        foreach ($baris as $r) {
            CLI::write(sprintf(
                '    id %-5d  status %-9s  awal %-14s  akhir %-14s  laci %-14s  selisih %-14s  akun %-5s  %s',
                $r['idtutupkasir'],
                $r['status'] ?? '-',
                number_format((int) $r['awal_cash'], 0, ',', '.'),
                number_format((int) $r['akhir_cash'], 0, ',', '.'),
                number_format((int) $r['cash_laci'], 0, ',', '.'),
                number_format((int) $r['cash_laci'] - (int) $r['akhir_cash'], 0, ',', '.'),
                $r['akun_ID_AKUN'] ?? '-',
                $r['created_at'] ?? '-'
            ), 'light_gray');
        }
    }

    /**
     * Rincian semua baris legacy tanpa `unit`.
     *
     * Baris-baris ini TIDAK ikut jadi sumber saldo awal unit mana pun, jadi
     * uang di dalamnya tidak muncul di posisi kasir siapa pun. Membutakannya
     * di sini supaya keputusan backfill-vs-pensiun diambil dengan melihat
     * angkanya, bukan asal status `NULL`.
     */
    private function rincianLegacy(): void
    {
        $baris = Database::connect()
            ->table('tutup_kasir')
            ->where('unit', null)
            ->orderBy('tanggal', 'ASC')
            ->orderBy('idtutupkasir', 'ASC')
            ->get()
            ->getResultArray();

        CLI::newline();
        CLI::write(sprintf('  Baris legacy tanpa unit: %d', count($baris)), 'white');

        foreach ($baris as $r) {
            CLI::write(sprintf(
                '    id %-5d  %s  akhir %-14s  laci %-14s  akun %-5s  %s',
                $r['idtutupkasir'],
                $r['tanggal'],
                number_format((int) $r['akhir_cash'], 0, ',', '.'),
                number_format((int) $r['cash_laci'], 0, ',', '.'),
                $r['akun_ID_AKUN'] ?? '-',
                $r['created_at'] ?? '-'
            ), 'light_gray');
        }
    }
}
