<?php

namespace App\Commands;

use App\Services\Finance\InventoryBaselineReport;
use CodeIgniter\CLI\BaseCommand;
use Config\Database;

final class FinanceInventoryBaseline extends BaseCommand
{
    protected $group = 'Finance';
    protected $name = 'finance:inventory-baseline';
    protected $description = 'Snapshot baca-saja stok, akun persediaan, dan selisih Neraca saat ini.';
    protected $usage = 'finance:inventory-baseline <akun-persediaan-10-digit> [unit-id]';

    public function run(array $params): int
    {
        $account = (string) ($params[0] ?? '');
        $unit = $params[1] ?? null;
        if (count($params) > 2 || preg_match('/^1[0-9]{9}$/D', $account) !== 1
            || ($unit !== null && (filter_var($unit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false))) {
            fwrite(STDERR, "Penggunaan: {$this->usage}\n");
            return 2;
        }

        $db = Database::connect();
        $accountExists = $db->table('no_akun')->where('no_akun', $account)->countAllResults();
        if ($accountExists !== 1) {
            fwrite(STDERR, "Akun persediaan tidak ditemukan atau ambigu.\n");
            return 2;
        }

        $stockQuery = $db->table('stok_barang stok')
            ->select('stok.idbarang, stok.id_unit, stok.stok_akhir, hpp.hpp')
            ->join('barang barang', 'barang.idbarang = stok.idbarang')
            ->join('hpp_barang hpp', 'hpp.idbarang = stok.idbarang', 'left')
            ->where('barang.jenis_item', 'fisik')
            ->orderBy('stok.id_unit')->orderBy('stok.idbarang');
        $unclassifiedQuery = $db->table('stok_barang stok')
            ->select('COUNT(*) AS jumlah', false)
            ->join('barang barang', 'barang.idbarang = stok.idbarang')
            ->groupStart()->where('barang.jenis_item IS NULL', null, false)
                ->orWhereNotIn('barang.jenis_item', ['fisik', 'jasa'])->groupEnd();
        $journalQuery = $db->table('jurnal')
            ->select('no_akun, COALESCE(SUM(debet), 0) AS debet, COALESCE(SUM(kredit), 0) AS kredit', false)
            ->groupBy('no_akun')->orderBy('no_akun');
        if ($unit !== null) {
            $stockQuery->where('stok.id_unit', (int) $unit);
            $unclassifiedQuery->where('stok.id_unit', (int) $unit);
            $journalQuery->where('id_unit', (int) $unit);
        }

        $startedAt = date('c');
        $stock = $stockQuery->get()->getResultArray();
        $accounts = $journalQuery->get()->getResultArray();
        $report = InventoryBaselineReport::summarize($stock, $accounts, $account);
        $report['inventory_account'] = $account;
        $report['unit_id'] = $unit === null ? null : (int) $unit;
        $report['started_at'] = $startedAt;
        $report['finished_at'] = date('c');
        $report['scope'] = 'current_stock_and_cumulative_journal';
        $report['unclassified_stock_rows_excluded'] = (int) ($unclassifiedQuery->get()->getRow()->jumlah ?? 0);
        $report['journal_accounts'] = $accounts;

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        return 0;
    }
}
