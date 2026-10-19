<?php

namespace App\Services\Finance;

final class InventoryBaselineReport
{
    public static function summarize(array $stock, array $accounts, string $inventoryAccount): array
    {
        $stockValue = 0;
        $missingCost = [];
        $negativeStock = [];
        foreach ($stock as $item) {
            $item = (array) $item;
            $quantity = (int) $item['stok_akhir'];
            $key = ['barang_id' => (int) $item['idbarang'], 'unit_id' => (int) $item['id_unit']];
            if ($quantity > 0 && $item['hpp'] === null) {
                $missingCost[] = $key;
            }
            if ($quantity < 0) {
                $negativeStock[] = $key;
            }
            $stockValue += $quantity * (int) ($item['hpp'] ?? 0);
        }

        $journalInventory = 0;
        foreach ($accounts as $account) {
            $account = (array) $account;
            if ((string) $account['no_akun'] === $inventoryAccount) {
                $journalInventory += (int) $account['debet'] - (int) $account['kredit'];
            }
        }

        $balance = BalanceSheetReconciliation::summarize($accounts);
        return [
            'stock_rows' => count($stock),
            'stock_value_at_current_master_cost' => $stockValue,
            'inventory_journal_balance' => $journalInventory,
            'stock_vs_journal_difference' => $stockValue - $journalInventory,
            'missing_cost' => $missingCost,
            'negative_stock' => $negativeStock,
            'balance_sheet' => $balance,
        ];
    }
}
