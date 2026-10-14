<?php

namespace App\Services\Service;

use Config\Database;
use RuntimeException;

/**
 * Removes the previous outgoing-sparepart invoice for a service before its
 * current sparepart selection is written as a replacement.
 *
 * The caller owns the transaction. Legacy invoices are adopted only when one
 * unique generated SRV invoice exactly matches the service's saved spareparts;
 * ambiguous/unrelated sales are never deleted by guesswork.
 */
final class ServiceSparepartPenjualanSyncService
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Delete linked old invoices, adopting one uniquely matching legacy invoice
     * when possible so it can be removed through the shared hard-delete path.
     *
     * @param array<int,object|array<string,mixed>> $sparepartLama
     */
    public function hapusTransaksiLama(int $serviceId, array $sparepartLama, int $unitId): void
    {
        if ($serviceId <= 0) {
            throw new RuntimeException('ID service tidak valid untuk sinkronisasi sparepart.');
        }
        if (! $this->db->fieldExists('service_idservice', 'penjualan')) {
            throw new RuntimeException('Kolom penghubung invoice-service belum tersedia. Jalankan migrasi terbaru.');
        }

        $linkedRows = $this->db->table('penjualan')
            ->select('idpenjualan')
            ->where('service_idservice', $serviceId)
            ->get()
            ->getResultArray();
        $invoiceIds = array_map('intval', array_column($linkedRows, 'idpenjualan'));

        if ($invoiceIds === []) {
            $legacyId = $this->cariInvoiceLegacyUnik($serviceId, $sparepartLama, $unitId);
            if ($legacyId !== null) {
                if (! $this->db->table('penjualan')
                    ->where('idpenjualan', $legacyId)
                    ->where('service_idservice IS NULL', null, false)
                    ->update(['service_idservice' => $serviceId])) {
                    throw new RuntimeException('Gagal menautkan invoice lama ke service.');
                }
                $invoiceIds[] = $legacyId;
            }
        }

        $hardDelete = new SparepartKeluarHardDeleteService($this->db);
        foreach (array_unique($invoiceIds) as $invoiceId) {
            $details = $this->db->table('detail_penjualan')
                ->select('iddetail_penjualan')
                ->where('penjualan_idpenjualan', $invoiceId)
                ->get()
                ->getResultArray();

            if ($details === []) {
                if (! $this->db->table('jurnal')
                    ->where('tabel_referensi', 'penjualan')
                    ->where('id_referensi', (string) $invoiceId)
                    ->delete()) {
                    throw new RuntimeException('Gagal menghapus jurnal invoice service kosong.');
                }
                if (! $this->db->table('penjualan')
                    ->where('idpenjualan', $invoiceId)
                    ->where('service_idservice', $serviceId)
                    ->delete()) {
                    throw new RuntimeException('Gagal menghapus invoice service kosong.');
                }
                continue;
            }

            foreach ($details as $detail) {
                $result = $hardDelete->hapusDetail((int) $detail['iddetail_penjualan'], $serviceId);
                if (! $result['ok']) {
                    throw new RuntimeException('Gagal menghapus sparepart keluar lama milik service.');
                }
            }
        }
    }

    /**
     * Only claim legacy invoices whose generated code, unit, unpaid state, total
     * and complete detail composition all match the service's saved spareparts.
     */
    private function cariInvoiceLegacyUnik(int $serviceId, array $sparepartLama, int $unitId): ?int
    {
        if ($sparepartLama === [] || $unitId <= 0) {
            return null;
        }

        $expected = $this->normalisasiDetailService($sparepartLama);
        if ($expected === []) {
            return null;
        }
        $expectedTotal = array_sum(array_column($expected, 'sub_total'));

        $candidates = $this->db->table('penjualan')
            ->select('idpenjualan, kode_invoice, total_penjualan, unit_idunit, bayar, keterangan')
            ->where('service_idservice IS NULL', null, false)
            ->where('unit_idunit', $unitId)
            ->where('bayar', 0)
            ->where('keterangan', 'Belum Lunas')
            ->like('kode_invoice', 'SRV', 'after')
            ->get()
            ->getResult();

        $matchingIds = [];
        foreach ($candidates as $candidate) {
            if (! preg_match('/^SRV\d{14}$/D', (string) ($candidate->kode_invoice ?? ''))
                || (int) ($candidate->total_penjualan ?? 0) !== $expectedTotal) {
                continue;
            }

            $details = $this->db->table('detail_penjualan')
                ->select('barang_idbarang, jumlah, harga_penjualan, sub_total')
                ->where('penjualan_idpenjualan', (int) $candidate->idpenjualan)
                ->get()
                ->getResultArray();

            if ($this->normalisasiDetailPenjualan($details) === $expected) {
                $matchingIds[] = (int) $candidate->idpenjualan;
            }
        }

        if (count($matchingIds) > 1) {
            throw new RuntimeException(
                'Ditemukan beberapa invoice lama yang cocok dengan sparepart service ini. '
                . 'Tidak ada transaksi yang dihapus untuk menghindari salah hapus; minta bantuan supervisor untuk rekonsiliasi.'
            );
        }

        return $matchingIds[0] ?? null;
    }

    /** @return array<int,array{barang_idbarang:int,jumlah:int,harga_penjualan:int,sub_total:int}> */
    private function normalisasiDetailService(array $rows): array
    {
        $details = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $barangId = (int) ($row['barang_idbarang'] ?? 0);
            if ($barangId <= 0) {
                continue;
            }
            $details[] = [
                'barang_idbarang' => $barangId,
                'jumlah'          => (int) ($row['jumlah'] ?? 0),
                'harga_penjualan' => (int) ($row['harga_penjualan'] ?? 0),
                'sub_total'       => (int) ($row['sub_total'] ?? 0),
            ];
        }

        usort($details, static fn ($a, $b) => $a['barang_idbarang'] <=> $b['barang_idbarang']);
        return $details;
    }

    /** @return array<int,array{barang_idbarang:int,jumlah:int,harga_penjualan:int,sub_total:int}> */
    private function normalisasiDetailPenjualan(array $rows): array
    {
        $details = [];
        foreach ($rows as $row) {
            $barangId = (int) ($row['barang_idbarang'] ?? 0);
            if ($barangId <= 0) {
                continue;
            }
            $details[] = [
                'barang_idbarang' => $barangId,
                'jumlah'          => (int) ($row['jumlah'] ?? 0),
                'harga_penjualan' => (int) ($row['harga_penjualan'] ?? 0),
                'sub_total'       => (int) ($row['sub_total'] ?? 0),
            ];
        }

        usort($details, static fn ($a, $b) => $a['barang_idbarang'] <=> $b['barang_idbarang']);
        return $details;
    }
}
