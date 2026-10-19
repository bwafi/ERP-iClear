<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class InventoryLedger
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function postIncoming(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity, int $unitCost): array
    {
        return $this->post($itemId, $unitId, $sourceType, $sourceId, $event, 'incoming', $quantity, $unitCost, null);
    }

    public function postIncomingValue(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity, int $carriedValue): array
    {
        return $this->post($itemId, $unitId, $sourceType, $sourceId, $event, 'incoming_value', $quantity, null, $carriedValue);
    }

    public function postOutgoing(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event, int $quantity): array
    {
        return $this->post($itemId, $unitId, $sourceType, $sourceId, $event, 'outgoing', $quantity, null, null);
    }

    public function reverse(int $entryId, string $sourceType, string $sourceId, string $event): array
    {
        $this->validateSource($sourceType, $sourceId, $event);
        if ($entryId <= 0) {
            throw new \InvalidArgumentException('ID entri asal tidak valid.');
        }
        $this->db->transBegin();
        try {
            $original = $this->db->table('inventory_ledger_entries')->where('id', $entryId)->get()->getRowArray();
            if ($original === null || $original['reverses_id'] !== null) {
                throw new \InvalidArgumentException('Entri asal tidak ditemukan atau merupakan pembalikan.');
            }
            $this->lockBalance((int) $original['item_id'], (int) $original['unit_id']);
            $existing = $this->findSource($sourceType, $sourceId, $event);
            if ($existing !== null) {
                if ((int) $existing['reverses_id'] !== $entryId) {
                    throw new \InvalidArgumentException('Kunci sumber sudah digunakan untuk entri lain.');
                }
                $this->db->transCommit();
                return $existing;
            }
            $alreadyReversed = $this->db->table('inventory_ledger_entries')->where('reverses_id', $entryId)->countAllResults();
            if ($alreadyReversed > 0) {
                throw new \InvalidArgumentException('Entri sudah dibalik.');
            }
            $balance = $this->currentBalance((int) $original['item_id'], (int) $original['unit_id']);
            $change = InventoryLedgerMath::reverse((int) $balance['quantity'], (int) $balance['value'],
                (int) $original['quantity'], (int) $original['value']);
            $row = $this->save((int) $original['item_id'], (int) $original['unit_id'], $sourceType, $sourceId,
                $event, 'reversal', abs((int) $original['quantity']), null, $change, $entryId);
            $this->finish();
            return $row;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function post(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event,
        string $direction, int $quantity, ?int $unitCost, ?int $carriedValue): array
    {
        $this->validateSource($sourceType, $sourceId, $event);
        if ($itemId <= 0 || $unitId <= 0 || $quantity <= 0 || $direction === 'incoming' && $unitCost === null
            || $direction === 'incoming_value' && $carriedValue === null) {
            throw new \InvalidArgumentException('Mutasi stok tidak valid.');
        }
        $this->db->transBegin();
        try {
            $this->lockBalance($itemId, $unitId);
            $existing = $this->findSource($sourceType, $sourceId, $event);
            if ($existing !== null) {
                if ((int) $existing['item_id'] !== $itemId || (int) $existing['unit_id'] !== $unitId
                    || $existing['direction'] !== $direction || (int) $existing['quantity'] !== ($direction === 'outgoing' ? -$quantity : $quantity)
                    || ($direction === 'incoming' && (int) $existing['unit_cost'] !== $unitCost)
                    || ($direction === 'incoming_value' && (int) $existing['value'] !== $carriedValue)) {
                    throw new \InvalidArgumentException('Kunci sumber digunakan dengan isi berbeda.');
                }
                $this->db->transCommit();
                return $existing;
            }
            $balance = $this->currentBalance($itemId, $unitId);
            if ($direction === 'incoming') {
                $change = InventoryLedgerMath::incoming((int) $balance['quantity'], (int) $balance['value'], $quantity, $unitCost);
            } elseif ($direction === 'incoming_value') {
                $change = InventoryLedgerMath::incomingValue((int) $balance['quantity'], (int) $balance['value'], $quantity, $carriedValue);
            } else {
                $change = InventoryLedgerMath::outgoing((int) $balance['quantity'], (int) $balance['value'], $quantity);
            }
            $row = $this->save($itemId, $unitId, $sourceType, $sourceId, $event, $direction, $quantity,
                $unitCost, $change, null);
            $this->finish();
            return $row;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function lockBalance(int $itemId, int $unitId): void
    {
        $table = $this->db->prefixTable('inventory_ledger_balances');
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('INSERT OR IGNORE INTO ' . $table . ' (item_id, unit_id, quantity, value) VALUES (?, ?, 0, 0)', [$itemId, $unitId]);
            return;
        }
        $this->db->query('INSERT IGNORE INTO ' . $table . ' (item_id, unit_id, quantity, value) VALUES (?, ?, 0, 0)', [$itemId, $unitId]);
        $this->db->query('SELECT item_id FROM ' . $table . ' WHERE item_id = ? AND unit_id = ? FOR UPDATE', [$itemId, $unitId]);
    }

    private function currentBalance(int $itemId, int $unitId): array
    {
        return $this->db->table('inventory_ledger_balances')->where('item_id', $itemId)->where('unit_id', $unitId)->get()->getRowArray();
    }

    private function findSource(string $sourceType, string $sourceId, string $event): ?array
    {
        return $this->db->table('inventory_ledger_entries')->where('source_type', $sourceType)
            ->where('source_id', $sourceId)->where('source_event', $event)->get()->getRowArray();
    }

    private function save(int $itemId, int $unitId, string $sourceType, string $sourceId, string $event,
        string $direction, int $quantity, ?int $unitCost, array $change, ?int $reversesId): array
    {
        $data = ['item_id' => $itemId, 'unit_id' => $unitId, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'source_event' => $event, 'direction' => $direction,
            'quantity' => $change['quantity'], 'unit_cost' => $unitCost, 'value' => $change['value'],
            'balance_quantity' => $change['balance_quantity'], 'balance_value' => $change['balance_value'],
            'reverses_id' => $reversesId, 'created_at' => date('Y-m-d H:i:s')];
        if (! $this->db->table('inventory_ledger_entries')->insert($data)) {
            throw new \RuntimeException('Gagal menulis entri ledger.');
        }
        $data['id'] = (int) $this->db->insertID();
        if (! $this->db->table('inventory_ledger_balances')->where('item_id', $itemId)->where('unit_id', $unitId)
            ->update(['quantity' => $change['balance_quantity'], 'value' => $change['balance_value']])) {
            throw new \RuntimeException('Gagal memperbarui saldo ledger.');
        }
        return $data;
    }

    private function finish(): void
    {
        if ($this->db->transStatus() === false || ! $this->db->transCommit()) {
            throw new \RuntimeException('Transaksi ledger gagal.');
        }
    }

    private function validateSource(string $type, string $id, string $event): void
    {
        foreach ([$type, $id, $event] as $part) {
            if ($part === '' || strlen($part) > 64) {
                throw new \InvalidArgumentException('Kunci sumber harus berisi 1 sampai 64 karakter.');
            }
        }
    }
}
