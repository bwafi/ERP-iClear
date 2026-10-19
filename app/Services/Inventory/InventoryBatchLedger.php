<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class InventoryBatchLedger
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function post(int $itemId, int $unitId, string $sourceType, string $sourceId,
        string $event, int $quantity, ?int $batchId = null): array
    {
        if ($itemId <= 0 || $unitId <= 0 || $sourceType === '' || $sourceId === '' || $event === ''
            || $quantity === 0 || ($batchId !== null && $batchId <= 0)) {
            throw new \InvalidArgumentException('Mutasi batch tidak valid.');
        }
        $batchId = $batchId ?? 0;
        $this->db->transBegin();
        try {
            $this->lockItem($itemId);
            $other = $this->db->table('inventory_batch_events')->where('source_type', $sourceType)
                ->where('source_id', $sourceId)->where('source_event', $event)->get()->getRowArray();
            if ($other !== null && (int) $other['batch_id'] !== $batchId) {
                throw new \RuntimeException('Sumber mutasi batch sudah dicatat dengan batch berbeda.');
            }
            $existing = $this->event($sourceType, $sourceId, $event, $batchId);
            $viewAfter = $this->viewQuantity($itemId, $unitId);
            if ($existing !== null) {
                if ((int) $existing['item_id'] !== $itemId || (int) $existing['unit_id'] !== $unitId
                    || (int) $existing['quantity'] !== $quantity || $this->total($itemId, $unitId) !== $viewAfter) {
                    throw new \RuntimeException('Kunci atau saldo batch tidak cocok.');
                }
                $this->finish();
                return $existing;
            }
            $this->assertBatchItem($batchId, $itemId);
            $this->baseline($itemId, $unitId, $viewAfter - $quantity);
            if ($quantity < 0 && $batchId === 0) {
                $this->downgradeUnselected($itemId, $unitId, $sourceType, $sourceId, $event, -$quantity);
            }
            $row = $this->append($itemId, $unitId, $batchId, $sourceType, $sourceId, $event, $quantity);
            if ($this->total($itemId, $unitId) !== $viewAfter) {
                throw new \RuntimeException('Saldo batch tidak cocok dengan Kartu Stok.');
            }
            $this->finish();
            return $row;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function reverse(int $entryId, string $sourceType, string $sourceId, string $event): array
    {
        if ($entryId <= 0 || $sourceType === '' || $sourceId === '' || $event === '') {
            throw new \InvalidArgumentException('Pembalikan batch tidak valid.');
        }
        $this->db->transBegin();
        try {
            $original = $this->db->table('inventory_batch_events')->where('id', $entryId)->get()->getRowArray();
            if ($original === null || $original['reverses_id'] !== null) {
                throw new \InvalidArgumentException('Mutasi batch asal tidak ditemukan.');
            }
            $itemId = (int) $original['item_id'];
            $unitId = (int) $original['unit_id'];
            $batchId = (int) $original['batch_id'];
            $this->lockItem($itemId);
            $existing = $this->event($sourceType, $sourceId, $event, $batchId);
            if ($existing !== null) {
                if ((int) $existing['reverses_id'] !== $entryId) {
                    throw new \RuntimeException('Kunci pembalikan batch digunakan ulang.');
                }
                $this->finish();
                return $existing;
            }
            if ($this->db->table('inventory_batch_events')->where('reverses_id', $entryId)->countAllResults() > 0) {
                throw new \RuntimeException('Mutasi batch sudah dibalik.');
            }
            $quantity = -(int) $original['quantity'];
            $viewAfter = $this->viewQuantity($itemId, $unitId);
            if ($this->total($itemId, $unitId) !== $viewAfter - $quantity) {
                throw new \RuntimeException('Saldo sebelum pembalikan batch tidak cocok.');
            }
            $row = $this->append($itemId, $unitId, $batchId, $sourceType, $sourceId, $event, $quantity, $entryId);
            if ($this->total($itemId, $unitId) !== $viewAfter) {
                throw new \RuntimeException('Saldo setelah pembalikan batch tidak cocok.');
            }
            $this->finish();
            return $row;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    public function total(int $itemId, int $unitId): int
    {
        $row = $this->db->table('inventory_batch_balances')->selectSum('quantity', 'total')
            ->where('item_id', $itemId)->where('unit_id', $unitId)->get()->getRow();
        return (int) ($row->total ?? 0);
    }

    private function baseline(int $itemId, int $unitId, int $before): void
    {
        if ($before < 0) {
            throw new \RuntimeException('Saldo awal batch negatif.');
        }
        $count = $this->db->table('inventory_batch_balances')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->countAllResults();
        if ($count === 0) {
            if (! $this->db->table('inventory_batch_balances')->insert([
                'item_id' => $itemId, 'unit_id' => $unitId, 'batch_id' => 0, 'quantity' => $before,
            ])) {
                throw new \RuntimeException('Baseline batch tidak dapat dicatat.');
            }
            if ($before > 0) {
                $this->appendEvent($itemId, $unitId, 0, 'provenance_opening', $itemId . ':' . $unitId,
                    'unknown', $before);
            }
        } elseif ($this->total($itemId, $unitId) !== $before) {
            throw new \RuntimeException('Saldo batch lama tidak cocok dengan Kartu Stok.');
        }
    }

    private function downgradeUnselected(int $itemId, int $unitId, string $sourceType, string $sourceId,
        string $event, int $needed): void
    {
        $unknown = $this->balance($itemId, $unitId, 0);
        if ($unknown >= $needed) {
            return;
        }
        $known = $this->db->table('inventory_batch_balances')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->where('batch_id >', 0)->where('quantity >', 0)->get()->getResultArray();
        foreach ($known as $row) {
            $batchId = (int) $row['batch_id'];
            $quantity = (int) $row['quantity'];
            $this->append($itemId, $unitId, $batchId, $sourceType, $sourceId,
                $event . '_unverified_out', -$quantity);
            $this->append($itemId, $unitId, 0, $sourceType,
                $sourceId . ':' . $batchId, $event . '_unverified_in', $quantity);
        }
        if ($this->balance($itemId, $unitId, 0) < $needed) {
            throw new \RuntimeException('Stok batch tidak mencukupi.');
        }
    }

    private function append(int $itemId, int $unitId, int $batchId, string $sourceType, string $sourceId,
        string $event, int $quantity, ?int $reversesId = null): array
    {
        $before = $this->balance($itemId, $unitId, $batchId);
        if ($before + $quantity < 0) {
            throw new \RuntimeException('Jumlah melebihi saldo batch.');
        }
        if ($this->db->table('inventory_batch_balances')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->where('batch_id', $batchId)->countAllResults() === 0) {
            $this->db->table('inventory_batch_balances')->insert([
                'item_id' => $itemId, 'unit_id' => $unitId, 'batch_id' => $batchId, 'quantity' => 0,
            ]);
        }
        $this->db->table('inventory_batch_balances')->where('item_id', $itemId)
            ->where('unit_id', $unitId)->where('batch_id', $batchId)
            ->update(['quantity' => $before + $quantity]);
        return $this->appendEvent($itemId, $unitId, $batchId, $sourceType, $sourceId, $event, $quantity, $reversesId);
    }

    private function appendEvent(int $itemId, int $unitId, int $batchId, string $sourceType, string $sourceId,
        string $event, int $quantity, ?int $reversesId = null): array
    {
        $data = [
            'item_id' => $itemId, 'unit_id' => $unitId, 'batch_id' => $batchId,
            'source_type' => $sourceType, 'source_id' => $sourceId, 'source_event' => $event,
            'quantity' => $quantity, 'reverses_id' => $reversesId, 'created_at' => date('Y-m-d H:i:s'),
        ];
        if (! $this->db->table('inventory_batch_events')->insert($data)) {
            throw new \RuntimeException('Mutasi batch gagal dicatat.');
        }
        $data['id'] = (int) $this->db->insertID();
        return $data;
    }

    private function balance(int $itemId, int $unitId, int $batchId): int
    {
        $row = $this->db->table('inventory_batch_balances')->select('quantity')
            ->where('item_id', $itemId)->where('unit_id', $unitId)->where('batch_id', $batchId)->get()->getRow();
        return (int) ($row->quantity ?? 0);
    }

    private function event(string $type, string $id, string $event, int $batchId): ?array
    {
        return $this->db->table('inventory_batch_events')->where('source_type', $type)
            ->where('source_id', $id)->where('source_event', $event)
            ->where('batch_id', $batchId)->get()->getRowArray();
    }

    private function viewQuantity(int $itemId, int $unitId): int
    {
        $row = $this->db->table('stok_barang')->select('stok_akhir')
            ->where('idbarang', $itemId)->where('id_unit', $unitId)->get()->getRow();
        return (int) ($row->stok_akhir ?? 0);
    }

    private function assertBatchItem(int $batchId, int $itemId): void
    {
        if ($batchId === 0) {
            return;
        }
        $row = $this->db->table('detail_pembelian')->select('barang_idbarang')
            ->where('iddetail_pembelian', $batchId)->get()->getRow();
        if ($row === null || (int) $row->barang_idbarang !== $itemId) {
            throw new \InvalidArgumentException('Batch bukan milik barang tersebut.');
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
            throw new \RuntimeException('Transaksi batch gagal.');
        }
    }
}
