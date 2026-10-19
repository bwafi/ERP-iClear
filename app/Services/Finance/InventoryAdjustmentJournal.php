<?php

namespace App\Services\Finance;

use CodeIgniter\Database\BaseConnection;

final class InventoryAdjustmentJournal
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function post(array $adjustment, int $value): void
    {
        if ($value === 0) {
            return;
        }
        $unitId = (int) $adjustment['unit_id'];
        $config = $this->db->table('inventory_adjustment_accounts')->where('unit_id', $unitId)
            ->get()->getRowArray();
        if ($config === null) {
            throw new \RuntimeException('Akun selisih stok opname belum dipetakan untuk unit ini.');
        }
        $inventory = (string) $config['inventory_account'];
        $counterpart = (string) $config[$value < 0 ? 'shortage_account' : 'surplus_account'];
        if ($inventory === $counterpart || ! preg_match('/^1[0-9]{9}$/D', $inventory)
            || ! preg_match($value < 0 ? '/^(?:[56][0-9]{9}|702[0-9]{7})$/D'
                : '/^(?:4[0-9]{9}|701[0-9]{7})$/D', $counterpart)) {
            throw new \RuntimeException('Pemetaan akun selisih stok opname tidak valid.');
        }
        $accounts = $this->db->table('no_akun')->select('no_akun, nama_akun')
            ->whereIn('no_akun', [$inventory, $counterpart])->get()->getResult();
        $names = [];
        foreach ($accounts as $account) {
            $names[$account->no_akun] = $account->nama_akun;
        }
        if (! isset($names[$inventory], $names[$counterpart])) {
            throw new \RuntimeException('Akun selisih stok opname tidak ditemukan di master akun.');
        }
        $id = (int) $adjustment['id'];
        if ($this->db->table('jurnal')->where('tabel_referensi', 'inventory_stock_adjustments')
            ->where('id_referensi', $id)->countAllResults() !== 0) {
            throw new \RuntimeException('Jurnal penyesuaian sudah ada; duplikasi ditolak.');
        }
        $amount = abs($value);
        $debit = $value < 0 ? $counterpart : $inventory;
        $credit = $value < 0 ? $inventory : $counterpart;
        foreach ([[$debit, $amount, 0], [$credit, 0, $amount]] as $line) {
            if (! $this->db->table('jurnal')->insert([
                'tanggal' => substr($adjustment['created_at'], 0, 10),
                'no_akun' => $line[0], 'nama_akun' => $names[$line[0]],
                'debet' => $line[1], 'kredit' => $line[2],
                'keterangan' => 'Penyesuaian stok #' . $id . ': ' . $adjustment['reason'],
                'id_referensi' => $id, 'tabel_referensi' => 'inventory_stock_adjustments',
                'id_unit' => $unitId, 'id_akun' => (int) $adjustment['actor_id'],
            ])) {
                throw new \RuntimeException('Jurnal penyesuaian gagal disimpan.');
            }
        }
    }

    public function reverse(array $original, array $reversal, int $value): void
    {
        $rows = $this->db->table('jurnal')->where('tabel_referensi', 'inventory_stock_adjustments')
            ->where('id_referensi', (int) $original['id'])->get()->getResultArray();
        if ($value === 0 && count($rows) === 0) {
            return;
        }
        if (count($rows) !== 2 || array_sum(array_column($rows, 'debet')) !== abs($value)
            || array_sum(array_column($rows, 'kredit')) !== abs($value)) {
            throw new \RuntimeException('Jurnal penyesuaian asal tidak lengkap atau nominalnya berubah.');
        }
        foreach ($rows as $row) {
            if (! $this->db->table('jurnal')->insert([
                'tanggal' => substr($reversal['created_at'], 0, 10),
                'no_akun' => $row['no_akun'], 'nama_akun' => $row['nama_akun'],
                'debet' => $row['kredit'], 'kredit' => $row['debet'],
                'keterangan' => 'Pembalikan penyesuaian stok #' . $original['id'],
                'id_referensi' => (int) $reversal['id'],
                'tabel_referensi' => 'inventory_stock_adjustments',
                'id_unit' => (int) $reversal['unit_id'], 'id_akun' => (int) $reversal['actor_id'],
            ])) {
                throw new \RuntimeException('Jurnal pembalikan penyesuaian gagal disimpan.');
            }
        }
    }
}
