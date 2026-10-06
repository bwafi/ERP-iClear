<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

/**
 * Cutover stok opname ke sistem v2.
 *
 * Sistem lama memaksa tanggal = hari ini, jadi setiap DRAFT yang tidak
 * diselesaikan pada hari yang sama menggantung selamanya. Akibatnya operator
 * sudah mengisi penuh (mis. unit 1: 365/365, 298/298, 132/132) tapi tidak
 * pernah masuk hitungan KPI.
 *
 * Command ini menutup periode DRAFT tersebut menjadi FINAL supaya credit
 * September tidak hilang. Aturan ketat v2 (100% barang berstok terisi)
 * TIDAK berlaku untuk cutover — ini data historis, bukan opname baru.
 *
 * Pemakaian:
 *   php spark stokopname:cutover                       # dry-run, tampilkan rencana
 *   php spark stokopname:cutover --force
 *   php spark stokopname:cutover --force --until=2026-09-30
 *   php spark stokopname:cutover --force --only-complete
 */
class StokOpnameCutover extends BaseCommand
{
    private $db;

    protected $group       = 'stokopname';
    protected $name        = 'stokopname:cutover';
    protected $description = 'Finalisasi periode stok opname DRAFT yang menggantung dari sistem lama.';
    protected $usage       = 'stokopname:cutover [--force] [--until=YYYY-MM-DD] [--only-complete]';
    protected $arguments   = [];
    protected $options     = [
        '--force'         => 'Jalankan finalisasi. Tanpa flag ini hanya dry-run.',
        '--until'         => 'Batas tanggal periode yang diproses (default: hari ini).',
        '--only-complete' => 'Hanya finalisasi periode dengan terisi_barang = total_barang.',
    ];

    public function run(array $params): int
    {
        $this->db = \Config\Database::connect();

        $raw = $_SERVER['argv'] ?? [];
        $force = in_array('--force', $raw, true);
        $only  = in_array('--only-complete', $raw, true);

        $until = date('Y-m-d');
        foreach ($raw as $i => $arg) {
            if ($arg === '--until' && isset($raw[$i + 1])) {
                $until = $raw[$i + 1];
            } elseif (str_starts_with((string)$arg, '--until=')) {
                $until = substr((string)$arg, 8);
            }
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $this->err('Format --until harus YYYY-MM-DD.');
            return 1;
        }

        $builder = $this->db->table('stok_opname_periode')
            ->where('status', 'DRAFT')
            ->where('tanggal <=', $until)
            ->orderBy('tanggal', 'ASC');
        if ($only) {
            $builder->where('terisi_barang >= total_barang', null, false)->where('total_barang >', 0);
        }
        $periods = $builder->get()->getResultArray();

        if ($periods === []) {
            $this->out('Tidak ada periode DRAFT yang perlu diproses.');
            return 0;
        }

        $this->out(str_repeat('=', 100));
        $this->out('Rencana cutover stok opname — mode ' . ($force ? 'JALAN' : 'DRY-RUN'));
        $this->out('Batas tanggal: ' . $until . ' | filter: ' . ($only ? 'hanya periode lengkap' : 'semua DRAFT'));
        $this->out(str_repeat('=', 100));
        $this->out(sprintf('%-4s %-12s %-5s %-7s %-8s %-9s %s', 'id', 'tanggal', 'unit', 'total', 'terisi', 'kelengkapan', 'catatan'));
        $this->out(str_repeat('-', 100));

        $ok = 0;
        $gagal = 0;
        foreach ($periods as $p) {
            $total   = (int)$p['total_barang'];
            $terisi  = (int)$p['terisi_barang'];
            $lengkap = $total > 0 && $terisi >= $total;
            $catatan = $total === 0
                ? 'TIDAK ADA draft — akan jadi periode FINAL kosong'
                : ($lengkap ? 'lengkap' : $total . '-' . $terisi . ' = ' . ($total - $terisi) . ' item kosong');

            $this->out(sprintf(
                '%-4d %-12s %-5d %-7d %-8d %-9s %s',
                (int)$p['id'],
                $p['tanggal'],
                (int)$p['unit_idunit'],
                $total,
                $terisi,
                $lengkap ? 'LENGKAP' : 'PARTAIL',
                $catatan
            ));

            if (! $force) {
                $ok++;
                continue;
            }

            $this->finalizePeriod($p) ? $ok++ : $gagal++;
        }

        $this->out(str_repeat('-', 100));
        $this->out('Total periode: ' . count($periods) . ' | diproses: ' . $ok . ' | gagal: ' . $gagal);

        if (! $force) {
            $this->out('');
            $this->out('DRY-RUN. Tidak ada data yang diubah. Jalankan ulang dengan --force untuk menerapkan.');
            return 0;
        }

        $this->out('');
        $this->out('Cutover selesai. ' . $this->kpiRingkas($until));

        return $gagal > 0 ? 1 : 0;
    }

    private function finalizePeriod(array $p): bool
    {
        $periodeId = (int)$p['id'];
        $unit      = (int)$p['unit_idunit'];
        $tanggal   = $p['tanggal'];

        $this->db->transBegin();
        try {
            $draft = $this->db->query(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL THEN 1 ELSE 0 END),0) AS terisi,
                        COALESCE(SUM(jumlah_komp),0) AS komp,
                        COALESCE(SUM(jumlah_real),0) AS real_jml,
                        COALESCE(SUM(jumlah_selisih),0) AS selisih
                 FROM stok_opname_draft WHERE periode_id = ?',
                [$periodeId]
            )->getRow();

            $total = (int)($draft->total ?? 0);

            if ($total > 0) {
                $this->db->query('DELETE FROM stok_opname WHERE periode_id = ?', [$periodeId]);
                $this->db->query(
                    'INSERT INTO stok_opname
                        (tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                         satuan_terkecil, barang_idbarang, unit_idunit, periode_id)
                     SELECT tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                            satuan_terkecil, barang_idbarang, unit_idunit, periode_id
                     FROM stok_opname_draft WHERE periode_id = ?',
                    [$periodeId]
                );
            }

            $this->db->table('stok_opname_periode')->where('id', $periodeId)->update([
                'status'             => 'FINAL',
                'total_barang'       => $total,
                'terisi_barang'      => (int)($draft->terisi ?? 0),
                'jumlah_komp'        => (float)($draft->komp ?? 0),
                'jumlah_real'        => (float)($draft->real_jml ?? 0),
                'jumlah_selisih'     => (float)($draft->selisih ?? 0),
                'finalisasi_by'      => null,
                'tanggal_finalisasi' => date('Y-m-d H:i:s'),
                'catatan_finalisasi' => 'cutover v2 (dari DRAFT sistem lama)',
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('stok_opname_audit')->insert([
                'periode_id'    => $periodeId,
                'unit_idunit'   => $unit,
                'tanggal'       => $tanggal,
                'aksi'          => 'finalisasi',
                'actor_id'      => null,
                'jumlah_barang' => $total,
                'jumlah_terisi' => (int)($draft->terisi ?? 0),
                'catatan'       => 'cutover v2',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->err('Periode #' . $periodeId . ' (' . $tanggal . ' unit ' . $unit . ') gagal: ' . $e->getMessage());
            return false;
        }
    }

    private function kpiRingkas(string $until): string
    {
        $rows = $this->db->query(
            "SELECT MONTH(tanggal) AS bln, unit_idunit, COUNT(*) AS periode
             FROM stok_opname_periode
             WHERE status = 'FINAL' AND YEAR(tanggal) = ? AND tanggal <= ?
             GROUP BY bln, unit_idunit
             ORDER BY bln DESC, unit_idunit",
            [(int) substr($until, 0, 4), $until]
        )->getResultArray();

        $lines = [];
        foreach ($rows as $r) {
            $lines[] = sprintf(
                '  %02d unit %d = %d periode (target 4 -> %d%%)',
                (int)$r['bln'],
                (int)$r['unit_idunit'],
                (int)$r['periode'],
                (int) min(round(((int) $r['periode'] / 4) * 100), 100)
            );
        }

        return "Progres KPI per bulan:\n" . implode("\n", $lines);
    }

    private function out(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private function err(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }
}
