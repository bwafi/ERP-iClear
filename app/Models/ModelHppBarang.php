<?php

namespace App\Models;

use App\Services\Inventory\MovingAverageValuation;
use App\Services\Inventory\StockCardLedger;
use CodeIgniter\Model;

class ModelHppBarang extends Model
{
    protected $table = 'hpp_barang';
    protected $primaryKey = 'idbarang';
    protected $returnType = 'object';
    protected $allowedFields = ['idbarang', 'kode_barang', 'idkategori', 'harga_beli', 'hpp'];

    public function getHppBarang()
    {
        return $this->findAll();
    }

    public function insert_HppBarang($data)
    {
        return $this->insert($data);
    }

    public function getById($idbarang)
    {
        return $this->where(['idbarang' => $idbarang])->first();
    }

    public function currentUnitCost(int $itemId, int $unitId): ?int
    {
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $this->db->query('SELECT idbarang FROM ' . $this->db->prefixTable('barang')
                . ' WHERE idbarang = ? FOR UPDATE', [$itemId]);
        }
        $stock = $this->db->table('stok_barang')
            ->select('stok_akhir')
            ->where('idbarang', $itemId)
            ->where('id_unit', $unitId)
            ->get()->getRow();
        if ($this->db->tableExists('inventory_ledger_balances')) {
            $balance = $this->db->table('inventory_ledger_balances')
                ->select('quantity, value')
                ->where('item_id', $itemId)
                ->where('unit_id', $unitId)
                ->get()->getRow();
            if ($balance !== null && (int) $balance->quantity > 0
                && (int) $balance->quantity === (int) ($stock->stok_akhir ?? 0)) {
                return (int) round((int) $balance->value / (int) $balance->quantity, 0, PHP_ROUND_HALF_UP);
            }
        }
        $movements = (new StockCardLedger($this->db))->forItem($itemId, $unitId);
        $valuation = MovingAverageValuation::replay($movements);

        return $valuation['valid'] && $valuation['quantity'] === (int) ($stock->stok_akhir ?? 0)
            ? $valuation['unit_cost']
            : null;
    }
}
