<?php

namespace App\Services\Inventory;

use App\Services\Finance\InventoryAdjustmentJournal;
use CodeIgniter\Database\BaseConnection;

final class InventoryStockAdjustment
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function post(int $itemId, int $unitId, int $delta, string $sourceType, string $sourceId,
        string $reason, int $actorId, ?int $batchId = null, ?int $openingUnitCost = null): array
    {
        if ($itemId <= 0 || $unitId <= 0 || $delta === 0 || $sourceType === '' || $sourceId === ''
            || trim($reason) === '' || $actorId <= 0 || ($openingUnitCost !== null && $openingUnitCost < 0)) {
            throw new \InvalidArgumentException('Penyesuaian stok tidak valid.');
        }
        $this->db->transBegin();
        try {
            $this->lockItem($itemId);
            $existing = $this->db->table('inventory_stock_adjustments')
                ->where('source_type', $sourceType)->where('source_id', $sourceId)->get()->getRowArray();
            if ($existing !== null) {
                if ((int) $existing['item_id'] !== $itemId || (int) $existing['unit_id'] !== $unitId
                    || (int) $existing['delta'] !== $delta || (int) $existing['actor_id'] !== $actorId
                    || $existing['reason'] !== trim($reason)) {
                    throw new \RuntimeException('Kunci penyesuaian digunakan dengan isi berbeda.');
                }
                $event = $this->db->table('inventory_batch_events')
                    ->where('source_type', 'inventory_stock_adjustments')
                    ->where('source_id', (string) $existing['id'])
                    ->where('source_event', 'adjust')->get()->getRow();
                if ($event === null || (int) $event->batch_id !== (int) ($batchId ?? 0)) {
                    throw new \RuntimeException('Batch penyesuaian yang sudah dicatat berbeda.');
                }
                $this->finish();
                return $existing;
            }
            $item = $this->db->table('barang')->select('jenis_item')->where('idbarang', $itemId)->get()->getRow();
            if ($item === null || $item->jenis_item !== 'fisik') {
                throw new \RuntimeException('Penyesuaian hanya untuk barang bertipe fisik yang telah disetujui.');
            }
            $bridge = new InventoryLedgerBridge($this->db);
            $stock = $this->db->table('stok_barang')->select('stok_akhir')
                ->where('idbarang', $itemId)->where('id_unit', $unitId)->get()->getRow();
            $before = (int) ($stock->stok_akhir ?? 0);
            if ($delta < 0 && $before < -$delta) {
                throw new \RuntimeException('Stok tidak mencukupi untuk penyesuaian.');
            }
            $current = $bridge->currentCost($itemId, $unitId);
            if ($before > 0 && $current['source'] !== 'ledger') {
                throw new \RuntimeException('Nilai persediaan belum direkonsiliasi.');
            }
            if ($delta < 0 && ! $bridge->isTracked($itemId, $unitId)) {
                throw new \RuntimeException('Pengurangan stok tanpa ledger tidak diizinkan.');
            }
            $unitCost = $before > 0 ? $current['unit_cost'] : $openingUnitCost;
            if ($unitCost === null) {
                throw new \RuntimeException('HPP awal penyesuaian belum tersedia.');
            }
            $this->db->table('inventory_stock_adjustments')->insert([
                'item_id' => $itemId, 'unit_id' => $unitId, 'delta' => $delta,
                'unit_cost' => $unitCost, 'source_type' => $sourceType, 'source_id' => $sourceId,
                'reason' => trim($reason), 'actor_id' => $actorId, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $adjustmentId = (int) $this->db->insertID();
            if ($delta > 0) {
                if (! $bridge->isTracked($itemId, $unitId)
                    && ! $bridge->isFirstIncoming($itemId, $unitId, 'inventory_stock_adjustments', $adjustmentId, $delta)) {
                    throw new \RuntimeException('Saldo awal penyesuaian tidak dapat dibuktikan.');
                }
                $valuation = $bridge->incoming($itemId, $unitId, 'inventory_stock_adjustments',
                    (string) $adjustmentId, 'adjust', $delta, $unitCost);
            } else {
                $valuation = $bridge->outgoing($itemId, $unitId, 'inventory_stock_adjustments',
                    (string) $adjustmentId, 'adjust', -$delta);
            }
            (new InventoryBatchLedger($this->db))->post($itemId, $unitId, 'inventory_stock_adjustments',
                (string) $adjustmentId, 'adjust', $delta, $batchId);
            $adjustment = $this->db->table('inventory_stock_adjustments')->where('id', $adjustmentId)->get()->getRowArray();
            (new InventoryAdjustmentJournal($this->db))->post($adjustment, (int) $valuation['value']);
            $this->finish();
            return $adjustment;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function reverse(int $adjustmentId, string $reason, int $actorId): array
    {
        if ($adjustmentId <= 0 || trim($reason) === '' || $actorId <= 0) {
            throw new \InvalidArgumentException('Pembatalan penyesuaian tidak valid.');
        }
        $this->db->transBegin();
        try {
            $original = $this->db->table('inventory_stock_adjustments')->where('id', $adjustmentId)->get()->getRowArray();
            if ($original === null || $original['reverses_id'] !== null) {
                throw new \RuntimeException('Penyesuaian asal tidak ditemukan.');
            }
            $itemId = (int) $original['item_id'];
            $this->lockItem($itemId);
            $existing = $this->db->table('inventory_stock_adjustments')
                ->where('reverses_id', $adjustmentId)->get()->getRowArray();
            if ($existing !== null) {
                if ($existing['reason'] !== trim($reason) || (int) $existing['actor_id'] !== $actorId) {
                    throw new \RuntimeException('Pembalikan penyesuaian sudah dicatat dengan isi berbeda.');
                }
                $this->finish();
                return $existing;
            }
            $sourceId = (string) $adjustmentId;
            $this->db->table('inventory_stock_adjustments')->insert([
                'item_id' => $itemId, 'unit_id' => (int) $original['unit_id'],
                'delta' => -(int) $original['delta'], 'unit_cost' => (int) $original['unit_cost'],
                'source_type' => 'inventory_stock_adjustments', 'source_id' => $sourceId,
                'reason' => trim($reason), 'actor_id' => $actorId,
                'reverses_id' => $adjustmentId, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $reversalId = (int) $this->db->insertID();
            $valuationEntry = $this->db->table('inventory_ledger_entries')
                ->where('source_type', 'inventory_stock_adjustments')
                ->where('source_id', $sourceId)->where('source_event', 'adjust')->get()->getRow();
            $batchEntry = $this->db->table('inventory_batch_events')
                ->where('source_type', 'inventory_stock_adjustments')
                ->where('source_id', $sourceId)->where('source_event', 'adjust')->get()->getRow();
            if ($valuationEntry === null || $batchEntry === null) {
                throw new \RuntimeException('Ledger penyesuaian asal tidak lengkap.');
            }
            $valuation = (new InventoryLedger($this->db))->reverse((int) $valuationEntry->id,
                'inventory_stock_adjustments', (string) $reversalId, 'cancel');
            (new InventoryBatchLedger($this->db))->reverse((int) $batchEntry->id,
                'inventory_stock_adjustments', (string) $reversalId, 'cancel');
            $reversal = $this->db->table('inventory_stock_adjustments')->where('id', $reversalId)->get()->getRowArray();
            (new InventoryAdjustmentJournal($this->db))->reverse($original, $reversal, (int) $valuation['value']);
            $this->finish();
            return $reversal;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function lockItem(int $itemId): void
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('SELECT idbarang FROM ' . $this->db->prefixTable('barang')
                . ' WHERE idbarang = ? FOR UPDATE', [$itemId]);
        }
    }

    private function finish(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new \RuntimeException('Transaksi penyesuaian gagal.');
        }
    }
}
