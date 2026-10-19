<?php

namespace App\Services\Service;

use Config\Database;
use RuntimeException;

/**
 * Shared hard-delete logic for Sparepart Keluar.
 *
 * The caller owns the transaction so this operation can be composed with a
 * DP refund without committing the stock return independently.
 */
final class SparepartKeluarHardDeleteService
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Delete one outgoing sparepart detail, matching the Sparepart Keluar
     * admin action. If it was the last line, its invoice and journal are
     * deleted; otherwise the invoice total is recalculated.
     *
     * @return array{ok:bool,penjualan_id:?int,invoice_deleted:bool}
     */
    public function hapusDetail(int $detailId, ?int $serviceId = null): array
    {
        if ($detailId <= 0) {
            return ['ok' => false, 'penjualan_id' => null, 'invoice_deleted' => false];
        }

        $detail = $this->db->table('detail_penjualan dp')
            ->select('dp.iddetail_penjualan, dp.penjualan_idpenjualan, dp.barang_idbarang, dp.unit_idunit, p.service_idservice')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan', 'left')
            ->where('dp.iddetail_penjualan', $detailId)
            ->get()
            ->getRow();

        if ($detail === null) {
            return ['ok' => false, 'penjualan_id' => null, 'invoice_deleted' => false];
        }

        if ($serviceId !== null && (int) ($detail->service_idservice ?? 0) !== $serviceId) {
            return ['ok' => false, 'penjualan_id' => null, 'invoice_deleted' => false];
        }

        $invoice = $this->db->table('penjualan')->select('bayar')
            ->where('idpenjualan', (int) $detail->penjualan_idpenjualan)->get()->getRow();
        if ($invoice === null || (int) $invoice->bayar > 0) {
            throw new RuntimeException('Invoice sparepart yang sudah dibayar tidak dapat dihapus.');
        }

        $invoiceId = (int) $detail->penjualan_idpenjualan;
        if ($invoiceId <= 0) {
            throw new RuntimeException('Invoice detail sparepart tidak valid.');
        }

        if (! $this->db->table('detail_penjualan')
            ->where('iddetail_penjualan', $detailId)
            ->delete()) {
            throw new RuntimeException('Gagal menghapus detail sparepart keluar.');
        }

        if ($this->db->tableExists('inventory_ledger_entries')) {
            $entry = $this->db->table('inventory_ledger_entries')
                ->where('source_type', 'detail_penjualan')
                ->where('source_id', (string) $detailId)
                ->where('source_event', 'sale')
                ->get()->getRow();
            if ($entry !== null) {
                (new \App\Services\Inventory\InventoryLedger($this->db))
                    ->reverse((int) $entry->id, 'detail_penjualan', (string) $detailId, 'cancel');
                $balance = $this->db->table('inventory_ledger_balances')
                    ->select('quantity')
                    ->where('item_id', (int) $detail->barang_idbarang)
                    ->where('unit_id', (int) $detail->unit_idunit)
                    ->get()->getRow();
                $stock = $this->db->table('stok_barang')
                    ->select('stok_akhir')
                    ->where('idbarang', (int) $detail->barang_idbarang)
                    ->where('id_unit', (int) $detail->unit_idunit)
                    ->get()->getRow();
                if ((int) ($balance->quantity ?? 0) !== (int) ($stock->stok_akhir ?? 0)) {
                    throw new RuntimeException('Pembatalan sparepart menghasilkan selisih stok ledger.');
                }
            }
        }

        $remaining = (int) $this->db->table('detail_penjualan')
            ->where('penjualan_idpenjualan', $invoiceId)
            ->countAllResults();

        if ($remaining === 0) {
            if (! $this->db->table('jurnal')
                ->where('tabel_referensi', 'penjualan')
                ->where('id_referensi', (string) $invoiceId)
                ->delete()) {
                throw new RuntimeException('Gagal menghapus jurnal invoice sparepart.');
            }

            if (! $this->db->table('penjualan')->where('idpenjualan', $invoiceId)->delete()) {
                throw new RuntimeException('Gagal menghapus invoice sparepart.');
            }

            return ['ok' => true, 'penjualan_id' => $invoiceId, 'invoice_deleted' => true];
        }

        $recalcQuery = $this->db->table('detail_penjualan')
            ->selectSum('sub_total', 'total_sub')
            ->where('penjualan_idpenjualan', $invoiceId)
            ->get();
        if ($recalcQuery === false) {
            throw new RuntimeException('Gagal menghitung ulang total invoice sparepart.');
        }

        $recalc = $recalcQuery->getRow();
        $newTotal = (int) ($recalc->total_sub ?? 0);

        if (! $this->db->table('penjualan')
            ->where('idpenjualan', $invoiceId)
            ->update([
                'total_penjualan' => $newTotal,
                'harus_dibayar'   => $newTotal,
            ])) {
            throw new RuntimeException('Gagal memperbarui total invoice sparepart.');
        }

        return ['ok' => true, 'penjualan_id' => $invoiceId, 'invoice_deleted' => false];
    }
}
