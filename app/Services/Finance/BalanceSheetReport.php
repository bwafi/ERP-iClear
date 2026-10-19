<?php

namespace App\Services\Finance;

use CodeIgniter\Database\BaseConnection;

final class BalanceSheetReport
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function summarizeAsOf(string $asOfDate, $unitId = null): array
    {
        $yearStart = date('Y-01-01', strtotime($asOfDate));
        $query = function () use ($unitId) {
            $builder = $this->db->table('jurnal');
            if ($unitId !== null && $unitId !== '') {
                $builder->where('id_unit', $unitId);
            }
            return $builder;
        };
        $select = 'no_akun, COALESCE(SUM(debet), 0) AS debet, COALESCE(SUM(kredit), 0) AS kredit';

        $balanceQuery = $query()->select($select, false)->where('tanggal <=', $asOfDate)
            ->groupStart()->like('no_akun', '1', 'after')->orLike('no_akun', '2', 'after')
            ->orLike('no_akun', '3', 'after')->groupEnd()->groupBy('no_akun');
        $profitQuery = $query()->select($select, false)->where('tanggal >=', $yearStart)
            ->where('tanggal <=', $asOfDate)
            ->groupStart()->like('no_akun', '4', 'after')->orLike('no_akun', '5', 'after')
            ->orLike('no_akun', '6', 'after')->orLike('no_akun', '701', 'after')
            ->orLike('no_akun', '702', 'after')->groupEnd()->groupBy('no_akun');
        $allQuery = $query()->select($select, false)->where('tanggal <=', $asOfDate)->groupBy('no_akun');

        return BalanceSheetReconciliation::summarizeAsOf(
            $balanceQuery->get()->getResultArray(),
            $profitQuery->get()->getResultArray(),
            $allQuery->get()->getResultArray()
        );
    }

    public function balanceAccountsAsOf(string $asOfDate, $unitId = null): array
    {
        $journalQuery = $this->db->table('jurnal')
            ->select('no_akun, SUM(debet) AS debet, SUM(kredit) AS kredit')
            ->where('tanggal <=', $asOfDate)->groupBy('no_akun');
        if ($unitId !== null && $unitId !== '') {
            $journalQuery->where('id_unit', $unitId);
        }
        $journalBalances = [];
        foreach ($journalQuery->get()->getResultArray() as $row) {
            $journalBalances[(string) $row['no_akun']] = $row;
        }

        $accounts = $this->db->table('no_akun')->select('no_akun, nama_akun')
            ->where('CHAR_LENGTH(no_akun)', 10)->orderBy('no_akun')->get()->getResultArray();
        $rows = [];
        foreach ($accounts as $account) {
            $code = (string) $account['no_akun'];
            if (! preg_match('/^[0-9]{10}$/D', $code) || ! in_array($code[0], ['1', '2', '3'], true)) {
                continue;
            }
            $balance = $journalBalances[$code] ?? ['debet' => 0, 'kredit' => 0];
            $debit = (int) $balance['debet'];
            $credit = (int) $balance['kredit'];
            $rows[] = [
                'no_akun' => $code,
                'nama_akun' => (string) $account['nama_akun'],
                'kelompok' => ['1' => 'Aset', '2' => 'Kewajiban', '3' => 'Ekuitas'][$code[0]],
                'debet' => $debit,
                'kredit' => $credit,
                'saldo' => $code[0] === '1' ? $debit - $credit : $credit - $debit,
            ];
        }
        return $rows;
    }

    public function sourceExceptionsAsOf(string $asOfDate, $unitId = null): array
    {
        $query = $this->db->table('jurnal')
            ->select('id_unit, tabel_referensi, id_referensi, COUNT(*) AS jumlah_baris, SUM(debet) AS debet, SUM(kredit) AS kredit', false)
            ->where('tanggal <=', $asOfDate);
        if ($unitId !== null && $unitId !== '') {
            $query->where('id_unit', $unitId);
        }
        $query->groupBy(['id_unit', 'tabel_referensi', 'id_referensi']);
        return BalanceSheetReconciliation::sourceExceptions($query->get()->getResultArray());
    }

    public function invalidAccountsAsOf(string $asOfDate, $unitId = null): array
    {
        $query = $this->db->table('jurnal j')
            ->select('j.no_akun, MAX(coa.no_akun) AS akun_master, COUNT(*) AS jumlah_baris, SUM(j.debet) AS debet, SUM(j.kredit) AS kredit', false)
            ->join('no_akun coa', 'coa.no_akun = j.no_akun', 'left')
            ->where('j.tanggal <=', $asOfDate)->groupBy('j.no_akun')->orderBy('j.no_akun');
        if ($unitId !== null && $unitId !== '') {
            $query->where('j.id_unit', $unitId);
        }
        return BalanceSheetReconciliation::invalidAccounts($query->get()->getResultArray());
    }

    public function inventoryReconciliation(string $asOfDate, $unitId = null): array
    {
        if ($asOfDate !== date('Y-m-d')) {
            return ['available' => false, 'reason' => 'Ledger menyimpan waktu pencatatan, bukan tanggal efektif untuk rekonstruksi saldo historis.'];
        }
        foreach (['inventory_ledger_balances', 'stok_barang', 'barang'] as $table) {
            if (! $this->db->tableExists($this->db->getPrefix() . $table, false)) {
                return ['available' => false, 'reason' => 'Ledger moving average belum tersedia untuk semua sumber.'];
            }
        }

        $stockQuery = $this->db->table('stok_barang stok')
            ->select('stok.id_unit, COUNT(DISTINCT stok.idbarang) AS jumlah_item, '
                . 'SUM(stok.stok_akhir) AS jumlah_stok_sumber, '
                . 'SUM(COALESCE(ledger.quantity, 0)) AS jumlah_stok_ledger, '
                . 'SUM(COALESCE(ledger.value, 0)) AS nilai_ledger, '
                . 'SUM(CASE WHEN ledger.item_id IS NULL AND stok.stok_akhir <> 0 THEN 1 ELSE 0 END) AS item_tanpa_saldo_ledger, '
                . 'SUM(stok.stok_akhir - COALESCE(ledger.quantity, 0)) AS selisih_jumlah', false)
            ->join('barang item', 'item.idbarang = stok.idbarang', 'inner')
            ->join('inventory_ledger_balances ledger', 'ledger.item_id = stok.idbarang AND ledger.unit_id = stok.id_unit', 'left')
            ->where('item.jenis_item', 'fisik')->groupBy('stok.id_unit');
        if ($unitId !== null && $unitId !== '') {
            $stockQuery->where('stok.id_unit', $unitId);
        }
        $stockRows = $stockQuery->get()->getResultArray();

        $journalQuery = $this->db->table('jurnal j')
            ->select('j.id_unit, COALESCE(SUM(j.debet - j.kredit), 0) AS saldo_buku, '
                . 'GROUP_CONCAT(DISTINCT j.no_akun ORDER BY j.no_akun SEPARATOR ",") AS akun_persediaan', false)
            ->join('no_akun coa', 'coa.no_akun = j.no_akun', 'inner')
            ->where('coa.jenis_akun', 'Persediaan')->where('j.tanggal <=', $asOfDate)->groupBy('j.id_unit');
        if ($unitId !== null && $unitId !== '') {
            $journalQuery->where('j.id_unit', $unitId);
        }
        $journalRows = [];
        foreach ($journalQuery->get()->getResultArray() as $row) {
            $journalRows[(int) $row['id_unit']] = $row;
        }

        $byUnit = [];
        foreach ($stockRows as $row) {
            $id = (int) $row['id_unit'];
            $journal = $journalRows[$id] ?? ['saldo_buku' => 0, 'akun_persediaan' => ''];
            $ledgerValue = (int) $row['nilai_ledger'];
            $bookValue = (int) $journal['saldo_buku'];
            $byUnit[$id] = [
                'unit_id' => $id,
                'jumlah_item' => (int) $row['jumlah_item'],
                'jumlah_stok_sumber' => (int) $row['jumlah_stok_sumber'],
                'jumlah_stok_ledger' => (int) $row['jumlah_stok_ledger'],
                'selisih_jumlah' => (int) $row['selisih_jumlah'],
                'nilai_ledger' => $ledgerValue,
                'nilai_buku' => $bookValue,
                'selisih_nilai' => $ledgerValue - $bookValue,
                'item_tanpa_saldo_ledger' => (int) $row['item_tanpa_saldo_ledger'],
                'akun_persediaan' => array_values(array_filter(explode(',', (string) $journal['akun_persediaan']))),
            ];
        }
        foreach ($journalRows as $id => $journal) {
            if (isset($byUnit[$id])) {
                continue;
            }
            $bookValue = (int) $journal['saldo_buku'];
            $byUnit[$id] = [
                'unit_id' => $id, 'jumlah_item' => 0, 'jumlah_stok_sumber' => 0,
                'jumlah_stok_ledger' => 0, 'selisih_jumlah' => 0, 'nilai_ledger' => 0,
                'nilai_buku' => $bookValue, 'selisih_nilai' => -$bookValue,
                'item_tanpa_saldo_ledger' => 0,
                'akun_persediaan' => array_values(array_filter(explode(',', (string) $journal['akun_persediaan']))),
            ];
        }
        $total = ['jumlah_item' => 0, 'jumlah_stok_sumber' => 0, 'jumlah_stok_ledger' => 0,
            'selisih_jumlah' => 0, 'nilai_ledger' => 0, 'nilai_buku' => 0, 'selisih_nilai' => 0,
            'item_tanpa_saldo_ledger' => 0, 'akun_persediaan' => []];
        foreach ($byUnit as $row) {
            foreach (['jumlah_item', 'jumlah_stok_sumber', 'jumlah_stok_ledger', 'selisih_jumlah',
                'nilai_ledger', 'nilai_buku', 'selisih_nilai', 'item_tanpa_saldo_ledger'] as $field) {
                $total[$field] += $row[$field];
            }
            $total['akun_persediaan'] = array_merge($total['akun_persediaan'], $row['akun_persediaan']);
        }
        $total['akun_persediaan'] = array_values(array_unique($total['akun_persediaan']));
        return ['available' => true, 'basis' => 'saldo ledger terkini; hanya item jenis_item=fisik',
            'total' => $total, 'per_unit' => array_values($byUnit)];
    }
}
