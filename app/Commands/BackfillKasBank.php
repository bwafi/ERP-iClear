<?php

namespace App\Commands;

use App\Libraries\ModeKasBank;
use App\Models\ModelMutasiStok;
use Config\Database;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Backfill ledger Kas & Bank dari transaksi existing (idempotent).
 *
 *   php74 spark kasbank:backfill
 *   php74 spark kasbank:backfill --full      # hitung ulang saldo sesudahnya
 *
 * Hanya mempromosikan transaksi existing ke transaksi_kas_bank bila akun
 * kas/bank sudah dikonfigurasi; sisanya di-skip & dilaporkan.
 */
class BackfillKasBank extends BaseCommand
{
    protected $group       = 'KasBank';
    protected $name        = 'kasbank:backfill';
    protected $description = 'Backfill ledger Kas & Bank (idempotent). Secara default HANYA memproses sumber yang dapat dibuktikan sebagai internal transfer. Sumber operasional (termasuk kas_keluar) TIDAK dibackfill secara default.';
    protected $usage       = 'kasbank:backfill [--full] [--source=]';

    protected $options = [
        '-full'   => 'Tampilkan seluruh rincian (default hanya status per sumber).',
        '-source' => 'Proses hanya sumber tertentu (comma-separated). Hindari memproses kas_keluar tanpa pertimbangan kontrak hybrid.',
    ];

    public function run(array $params)
    {
        $lib = new ModeKasBank();
        $db = Database::connect();
        $full = (bool) CLI::getOption('full');

        CLI::write('Backfill Kas & Bank dimulai…', 'yellow');

        // Seed akun KAS default per unit (idempotent) agar transaksi existing
        // tidak di-skip karena belum ada akun.
        $seed = $lib->seedAkunDefault();
        CLI::write(
            sprintf('Akun KAS default: %d dibuat, %d sudah ada.', $seed['created'], $seed['skipped']),
            $seed['created'] > 0 ? 'green' : 'light_gray'
        );

        $sources = [];
        $optSources = CLI::getOption('source');
        if ($optSources) {
            if (is_array($optSources)) {
                $sources = $optSources;
            } else {
                $sources = preg_split('/[,\s]+/', (string) $optSources);
            }
            $sources = array_filter(array_map('trim', $sources), fn($v) => $v !== '');
        }

        $shouldProcess = function (string $key) use ($sources): bool {
            if (empty($sources)) {
                return false;
            }
            foreach ($sources as $s) {
                if (strtolower($s) === strtolower($key)) {
                    return true;
                }
            }
            return false;
        };

        $summary = [
            'kas_masuk' => $shouldProcess('kas_masuk')
                ? $this->backfill($lib, $db, 'kas_masuk', 'postingKasMasuk', 'idkas_masuk')
                : ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []],
            'kas_keluar' => ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []],
            'pembayaran_hutang' => $shouldProcess('pembayaran_hutang')
                ? $this->backfill($lib, $db, 'pembayaran_hutang', 'postingCicilanHutang', 'idpembayaran_hutang')
                : ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []],
            'pembayaran_piutang' => $shouldProcess('pembayaran_piutang')
                ? $this->backfillPiutang($lib, $db)
                : ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []],
            'mutasi' => $shouldProcess('mutasi')
                ? $this->backfillMutasi($lib, $db)
                : ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []],
        ];

        if (!empty($sources)) {
            CLI::write('Source diizinkan (whitelist): ' . implode(', ', $sources), 'light_gray');
            CLI::write('Catatan: kas_keluar TIDAK dibackfill (di-hard-skip) sesuai kontrak hybrid.', 'light_gray');
        } else {
            CLI::write('Mode default (aman): tidak memproses sumber apapun. Gunakan --source=<sumber> jika diperlukan.', 'light_gray');
        }

        foreach ($summary as $sumber => $item) {
            $label = $full
                ? sprintf(
                    '%s: total=%d inserted=%d skipped=%d failed=%d',
                    str_pad($sumber, 22, ' ', STR_PAD_RIGHT),
                    $item['total'], $item['inserted'], $item['skipped'], $item['failed']
                )
                : sprintf(
                    '%s: %d inserted, %d skipped, %d failed',
                    str_pad($sumber, 22, ' ', STR_PAD_RIGHT),
                    $item['inserted'], $item['skipped'], $item['failed']
                );
            $color = $item['failed'] > 0 ? 'red' : ($item['inserted'] > 0 ? 'green' : 'light_gray');
            CLI::write($label, $color);
        }

        if ($full) {
            foreach ($summary as $sumber => $item) {
                foreach ($item['detail'] as $det) {
                    CLI::write(sprintf('  [%s] %s', strtoupper($det['status']), $det['pesan']), 'light_gray');
                }
            }
        }

        CLI::write('Selesai.', 'yellow');
        return EXIT_SUCCESS;
    }

    private function backfill(ModeKasBank $lib, $db, string $tabel, string $method, string $pk = 'id'): array
    {
        $rows = $db->table($tabel)->select($pk)->orderBy($pk, 'ASC')->get()->getResult();
        $res = ['total' => count($rows), 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []];

        foreach ($rows as $row) {
            $id = $row->{$pk};
            $result = $lib->{$method}((int) $id);
            $this->tally($res, $result, $method, $id);
        }
        return $res;
    }

    private function backfillPiutang(ModeKasBank $lib, $db): array
    {
        $rows = $db->query(
            'SELECT pp.idpembayaran_piutang AS id, COALESCE(pt.unit_idunit, 0) AS unit_id
             FROM pembayaran_piutang pp
             LEFT JOIN piutang pt ON pt.idpiutang = pp.idpiutang
             ORDER BY pp.idpembayaran_piutang ASC'
        )->getResult();
        $res = ['total' => count($rows), 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []];

        foreach ($rows as $row) {
            $result = $lib->postingBayarPiutang((int) $row->id, (int) $row->unit_id);
            $this->tally($res, $result, 'postingBayarPiutang', $row->id);
        }
        return $res;
    }

    private function backfillMutasi(ModeKasBank $lib, $db): array
    {
        $rows = $db->table('mutasi')->select('idmutasi')->orderBy('idmutasi', 'ASC')->get()->getResult();
        $res = ['total' => count($rows), 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'detail' => []];

        foreach ($rows as $row) {
            $result = $lib->buatHutangPiutangDariMutasi((int) $row->idmutasi, null);
            $this->tally($res, $result, 'buatHutangPiutangDariMutasi', $row->idmutasi);
        }
        return $res;
    }

    private function tally(array &$res, array $result, string $method, $id): void
    {
        $status = $result['status'] ?? 'failed';
        if ($status === 'inserted') {
            $res['inserted']++;
        } elseif ($status === 'skipped') {
            $res['skipped']++;
        } else {
            $res['failed']++;
        }
        $res['detail'][] = [
            'status' => $status,
            'pesan'  => $method . ' #' . $id . ': ' . ($result['reason'] ?? ('ok/' . $status)),
        ];
    }
}