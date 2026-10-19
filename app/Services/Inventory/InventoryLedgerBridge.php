<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class InventoryLedgerBridge
{
    private BaseConnection $db;
    private InventoryLedger $ledger;
    private StockCardLedger $stockCard;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
        $this->ledger = new InventoryLedger($db);
        $this->stockCard = new StockCardLedger($db);
    }

    public function isTracked(int $itemId, int $unitId): bool
    {
        if (! $this->db->tableExists('inventory_ledger_entries')) {
            return false;
        }
        return $this->db->table('inventory_ledger_entries')
            ->where('item_id', $itemId)
            ->where('unit_id', $unitId)
            ->countAllResults() > 0;
    }

    public function isFirstIncoming(int $itemId, int $unitId, string $sourceType, int $sourceId, int $quantity): bool
    {
        $labels = ['stok_awal' => 'Stok Awal', 'detail_pembelian' => 'Pembelian'];
        if (! $this->db->tableExists('inventory_ledger_entries')
            || ! isset($labels[$sourceType]) || $this->isTracked($itemId, $unitId)) {
            return false;
        }
        $movements = array_values(array_filter($this->stockCard->forItem($itemId, $unitId),
            static fn (array $row) => (int) $row['masuk'] !== 0 || (int) $row['keluar'] !== 0));
        return count($movements) === 1 && $movements[0]['sumber'] === $labels[$sourceType]
            && (int) $movements[0]['referensi_id'] === $sourceId
            && (int) $movements[0]['masuk'] === $quantity;
    }

    public function incoming(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity, int $unitCost): array
    {
        return $this->post($itemId, $unitId, $sourceType, $sourceId, $event, $quantity, $unitCost, 'incoming');
    }

    public function incomingAmount(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity, int $amount): array
    {
        if ($quantity <= 0 || $amount < 0) {
            throw new \InvalidArgumentException('Nilai pembelian tidak valid.');
        }
        $unitCost = PurchaseUnitCost::fromNetAmount($amount, $quantity);
        $this->db->transBegin();
        try {
            $this->lockItem($itemId);
            $existing = $this->entry($sourceType, $sourceId, $event);
            $this->assertView($itemId, $unitId, $sourceType, $sourceId, 'incoming', $quantity, $existing, $unitCost);
            if ($existing !== null) {
                $this->assertEntry($existing, $itemId, $unitId, $quantity);
                if ((int) $existing['value'] !== $amount) {
                    throw new \RuntimeException('Nilai pembelian yang sudah diposting berbeda.');
                }
                $this->finish();
                return $existing;
            }
            $result = $this->ledger->postIncomingValue($itemId, $unitId, $sourceType, $sourceId, $event, $quantity, $amount);
            $this->finish();
            return $result;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function outgoing(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity): array
    {
        return $this->post($itemId, $unitId, $sourceType, $sourceId, $event, $quantity, null, 'outgoing');
    }

    public function transfer(int $itemId, int $fromUnit, int $toUnit, string $sourceId, int $quantity): array
    {
        if ($fromUnit <= 0 || $toUnit <= 0 || $fromUnit === $toUnit || $quantity <= 0) {
            throw new \InvalidArgumentException('Transfer stok tidak valid.');
        }
        $this->db->transBegin();
        try {
            $this->lockItem($itemId);
            $outKey = $this->entry('detail_mutasi', $sourceId, 'transfer_out');
            $inKey = $this->entry('detail_mutasi', $sourceId, 'transfer_in');
            if (($outKey === null) !== ($inKey === null)) {
                throw new \RuntimeException('Transfer ledger parsial; perlu rekonsiliasi.');
            }
            $this->assertView($itemId, $fromUnit, 'detail_mutasi', $sourceId, 'outgoing', $quantity, $outKey);
            $this->assertView($itemId, $toUnit, 'detail_mutasi', $sourceId, 'incoming', $quantity, $inKey);
            if ($outKey !== null) {
                $this->assertEntry($outKey, $itemId, $fromUnit, -$quantity);
                $this->assertEntry($inKey, $itemId, $toUnit, $quantity);
                if ((int) $inKey['value'] !== -(int) $outKey['value']) {
                    throw new \RuntimeException('Nilai transfer ledger tidak seimbang.');
                }
                $this->finish();
                return ['outgoing' => $outKey, 'incoming' => $inKey];
            }
            $out = $this->ledger->postOutgoing($itemId, $fromUnit, 'detail_mutasi', $sourceId, 'transfer_out', $quantity);
            $in = $this->ledger->postIncomingValue($itemId, $toUnit, 'detail_mutasi', $sourceId, 'transfer_in', $quantity, -(int) $out['value']);
            $this->finish();
            return ['outgoing' => $out, 'incoming' => $in];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function currentCost(int $itemId, int $unitId): array
    {
        $stock = $this->viewQuantity($itemId, $unitId);
        $balance = $this->balance($itemId, $unitId);
        $entryCount = $this->db->table('inventory_ledger_entries')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->countAllResults();
        if ($entryCount > 0 && $balance !== null && (int) $balance['quantity'] === $stock) {
            return ['unit_cost' => $stock > 0
                ? (int) round((int) $balance['value'] / $stock, 0, PHP_ROUND_HALF_UP) : null, 'source' => 'ledger'];
        }
        $valuation = MovingAverageValuation::replay($this->stockCard->forItem($itemId, $unitId));
        if ($balance === null && $valuation['valid'] && $valuation['quantity'] === $stock) {
            return ['unit_cost' => $valuation['unit_cost'], 'source' => 'legacy_replay_unposted'];
        }
        return ['unit_cost' => null, 'source' => 'unreconciled'];
    }

    private function post(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event,
        int $quantity, ?int $unitCost, string $direction): array
    {
        if ($itemId <= 0 || $unitId <= 0 || $quantity <= 0 || $sourceId === '' || $event === ''
            || ($direction === 'incoming' && ($unitCost === null || $unitCost < 0))) {
            throw new \InvalidArgumentException('Mutasi stok tidak valid.');
        }
        $this->db->transBegin();
        try {
            $this->lockItem($itemId);
            $existing = $this->entry($sourceType, $sourceId, $event);
            $this->assertView($itemId, $unitId, $sourceType, $sourceId, $direction, $quantity, $existing, $unitCost);
            if ($existing !== null) {
                $this->assertEntry($existing, $itemId, $unitId, $direction === 'incoming' ? $quantity : -$quantity);
                if ($direction === 'incoming' && (int) $existing['unit_cost'] !== $unitCost) {
                    throw new \RuntimeException('Biaya mutasi yang sudah diposting berbeda.');
                }
                $this->finish();
                return $existing;
            }
            $result = $direction === 'incoming'
                ? $this->ledger->postIncoming($itemId, $unitId, $sourceType, $sourceId, $event, $quantity, $unitCost)
                : $this->ledger->postOutgoing($itemId, $unitId, $sourceType, $sourceId, $event, $quantity);
            $this->finish();
            return $result;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function assertView(int $itemId, int $unitId, string $sourceType, string $sourceId,
        string $direction, int $quantity, ?array $existing, ?int $unitCost = null): void
    {
        $labels = [
            'stok_awal' => ['incoming' => 'Stok Awal'],
            'detail_pembelian' => ['incoming' => 'Pembelian'],
            'detail_mutasi' => ['incoming' => 'Mutasi Masuk', 'outgoing' => 'Mutasi Keluar'],
            'retur_pelanggan' => ['incoming' => 'Retur Pelanggan'],
            'detail_penjualan' => ['outgoing' => 'Penjualan/Service'],
            'barang_rusak' => ['outgoing' => 'Barang Rusak'],
            'retur_suplier' => ['outgoing' => 'Retur Supplier'],
            'garansi_sparepart_keluar' => ['outgoing' => 'Pengganti Garansi'],
        ];
        $label = $labels[$sourceType][$direction] ?? null;
        if ($label === null || ! ctype_digit($sourceId)) {
            throw new \InvalidArgumentException('Sumber mutasi tidak didukung.');
        }
        $movements = array_values(array_filter($this->stockCard->forItem($itemId, $unitId),
            static fn (array $row) => (int) $row['masuk'] !== 0 || (int) $row['keluar'] !== 0));
        $matching = array_values(array_filter($movements, static fn (array $row) =>
            $row['sumber'] === $label && (string) $row['referensi_id'] === $sourceId
            && (int) $row[$direction === 'incoming' ? 'masuk' : 'keluar'] === $quantity));
        if (count($matching) !== 1) {
            throw new \RuntimeException('Gerakan sumber tidak cocok dengan kartu stok.');
        }
        $balance = $this->balance($itemId, $unitId);
        $viewQuantity = $this->viewQuantity($itemId, $unitId);
        if ($existing !== null) {
            if ($balance === null || (int) $balance['quantity'] !== $viewQuantity) {
                throw new \RuntimeException('Saldo ledger dan stok berbeda.');
            }
            return;
        }
        $delta = $direction === 'incoming' ? $quantity : -$quantity;
        $entries = $this->db->table('inventory_ledger_entries')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->countAllResults();
        if ($entries === 0) {
            self::assertUntrackedStart($movements, $matching[0], $direction, $quantity, $viewQuantity, $balance, $unitCost);
        } elseif ($balance === null || (int) $balance['quantity'] !== $viewQuantity - $delta) {
            throw new \RuntimeException('Saldo sebelum mutasi tidak cocok dengan view stok.');
        }
    }

    public static function assertUntrackedStart(array $movements, array $matchingMovement, string $direction,
        int $quantity, int $viewQuantity, ?array $balance, ?int $unitCost = null): void
    {
        if ($direction !== 'incoming' || count($movements) !== 1 || $movements[0] !== $matchingMovement
            || ! isset($matchingMovement['hpp_satuan']) || (int) $matchingMovement['hpp_satuan'] < 0
            || ($unitCost !== null && (int) $matchingMovement['hpp_satuan'] !== $unitCost)
            || $viewQuantity !== $quantity || ($balance !== null && ((int) $balance['quantity'] !== 0
                || (int) $balance['value'] !== 0))) {
            throw new \RuntimeException('Stok historis tanpa ledger; baseline manual diperlukan.');
        }
    }

    private function viewQuantity(int $itemId, int $unitId): int
    {
        $row = $this->db->table('stok_barang')->select('stok_akhir')
            ->where('idbarang', $itemId)->where('id_unit', $unitId)->get()->getRow();
        return (int) ($row->stok_akhir ?? 0);
    }

    private function balance(int $itemId, int $unitId): ?array
    {
        return $this->db->table('inventory_ledger_balances')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->get()->getRowArray();
    }

    private function entry(string $type, string $id, string $event): ?array
    {
        return $this->db->table('inventory_ledger_entries')->where('source_type', $type)
            ->where('source_id', $id)->where('source_event', $event)->get()->getRowArray();
    }

    private function assertEntry(array $entry, int $itemId, int $unitId, int $quantity): void
    {
        if ((int) $entry['item_id'] !== $itemId || (int) $entry['unit_id'] !== $unitId
            || (int) $entry['quantity'] !== $quantity) {
            throw new \RuntimeException('Kunci sumber ledger dipakai untuk mutasi berbeda.');
        }
    }

    private function lockItem(int $itemId): void
    {
        if ($itemId <= 0 || ! $this->db->query('SELECT idbarang FROM ' . $this->db->prefixTable('barang')
            . ' WHERE idbarang = ? FOR UPDATE', [$itemId])->getRow()) {
            throw new \InvalidArgumentException('Barang tidak ditemukan.');
        }
    }

    private function finish(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new \RuntimeException('Transaksi bridge ledger gagal.');
        }
    }
}
