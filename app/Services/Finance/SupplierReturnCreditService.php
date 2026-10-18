<?php

namespace App\Services\Finance;

use Config\Database;

final class SupplierReturnCreditService
{
    public function applyRemaining(int $creditId, int $userId): void
    {
        if ($creditId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('Kredit atau pengguna tidak valid.');
        }
        $db = Database::connect();
        $db->transBegin();
        try {
            $credit = $db->query(
                'SELECT * FROM ' . $db->prefixTable('kredit_retur_suplier') . ' WHERE id = ? FOR UPDATE',
                [$creditId]
            )->getRow();
            if (! $credit || $credit->status !== 'disetujui' || (int) $credit->sisa_kredit <= 0) {
                throw new \RuntimeException('Tidak ada sisa kredit yang dapat digunakan.');
            }
            $db->query(
                'SELECT id_suplier FROM ' . $db->prefixTable('suplier') . ' WHERE id_suplier = ? FOR UPDATE',
                [$credit->suplier_id]
            );
            $purchases = $db->query(
                'SELECT idpembelian, sisa FROM ' . $db->prefixTable('pembelian')
                . ' WHERE suplier_id_suplier = ? AND unit_idunit = ? AND sisa > 0'
                . ' ORDER BY tanggal_masuk, idpembelian FOR UPDATE',
                [$credit->suplier_id, $credit->unit_id]
            )->getResult();
            $balances = [];
            foreach ($purchases as $purchase) {
                $balances[(int) $purchase->idpembelian] = (int) $purchase->sisa;
            }
            $applied = min((int) $credit->sisa_kredit, array_sum($balances));
            if ($applied <= 0) {
                throw new \RuntimeException('Belum ada tagihan supplier yang dapat dipotong.');
            }
            $allocations = SupplierCreditAllocator::plan($balances, $applied);
            foreach ($purchases as $purchase) {
                $allocated = $allocations[(int) $purchase->idpembelian] ?? 0;
                if ($allocated === 0) {
                    continue;
                }
                $newBalance = (int) $purchase->sisa - $allocated;
                if (! $db->table('pembelian')->where('idpembelian', $purchase->idpembelian)->update([
                    'sisa' => $newBalance,
                    'status' => $newBalance === 0 ? 'Lunas' : 'Belum Lunas',
                ]) || ! $db->table('alokasi_kredit_retur_suplier')->insert([
                    'kredit_id' => $creditId,
                    'pembelian_id' => $purchase->idpembelian,
                    'nominal' => $allocated,
                ])) {
                    throw new \RuntimeException('Alokasi sisa kredit gagal.');
                }
                (new HutangPiutangService())->syncFromPembelian((int) $purchase->idpembelian);
            }
            $accounts = $db->table('no_akun')->select('no_akun, nama_akun')
                ->whereIn('no_akun', [$credit->akun_debet, $credit->akun_piutang])->get()->getResult();
            $accountNames = [];
            foreach ($accounts as $account) {
                $accountNames[$account->no_akun] = $account->nama_akun;
            }
            if (! isset($accountNames[$credit->akun_debet], $accountNames[$credit->akun_piutang])) {
                throw new \RuntimeException('Akun jurnal kredit tidak ditemukan.');
            }
            foreach ([[$credit->akun_debet, $applied, 0], [$credit->akun_piutang, 0, $applied]] as $line) {
                if (! $db->table('jurnal')->insert([
                    'tanggal' => date('Y-m-d'),
                    'no_akun' => $line[0],
                    'nama_akun' => $accountNames[$line[0]],
                    'debet' => $line[1],
                    'kredit' => $line[2],
                    'keterangan' => 'Pemakaian sisa kredit retur ' . $credit->no_retur_suplier,
                    'id_referensi' => $creditId,
                    'tabel_referensi' => 'kredit_retur_suplier',
                    'id_unit' => $credit->unit_id,
                    'id_akun' => $userId,
                ])) {
                    throw new \RuntimeException('Jurnal pemakaian kredit gagal.');
                }
            }
            if (! $db->table('kredit_retur_suplier')->where('id', $creditId)->update([
                'sisa_kredit' => (int) $credit->sisa_kredit - $applied,
            ]) || $db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Pemakaian sisa kredit gagal disimpan.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function approve(int $creditId, string $amountInput, string $creditNote, string $debitAccount, string $receivableAccount, string $creditAccount, int $userId): void
    {
        $amount = filter_var($amountInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($amount === false || $creditNote === '' || strlen($creditNote) > 100
            || $debitAccount === '' || $receivableAccount === '' || $creditAccount === ''
            || count(array_unique([$debitAccount, $receivableAccount, $creditAccount])) !== 3
            || $userId <= 0) {
            throw new \InvalidArgumentException('Nominal, nota kredit, atau akun jurnal tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $row = $db->query(
                'SELECT * FROM ' . $db->prefixTable('kredit_retur_suplier') . ' WHERE id = ? FOR UPDATE',
                [$creditId]
            )->getRow();
            if (! $row || $row->status !== 'menunggu') {
                throw new \RuntimeException('Kredit retur tidak ditemukan atau sudah disetujui.');
            }

            $supplier = $db->query(
                'SELECT id_suplier FROM ' . $db->prefixTable('suplier') . ' WHERE id_suplier = ? FOR UPDATE',
                [$row->suplier_id]
            )->getRow();
            if (! $supplier) {
                throw new \RuntimeException('Supplier tidak ditemukan.');
            }

            $returnTotal = $db->query(
                'SELECT COUNT(*) AS total FROM ' . $db->prefixTable('retur_suplier')
                . ' WHERE no_retur_suplier = ? AND unit_idunit = ?',
                [$row->no_retur_suplier, $row->unit_id]
            )->getRow();
            if ((int) ($returnTotal->total ?? 0) === 0) {
                throw new \RuntimeException('Dokumen retur tidak memiliki barang.');
            }

            $purchases = $db->query(
                'SELECT idpembelian, sisa FROM ' . $db->prefixTable('pembelian')
                . ' WHERE suplier_id_suplier = ? AND unit_idunit = ? AND sisa > 0'
                . ' ORDER BY tanggal_masuk, idpembelian FOR UPDATE',
                [$row->suplier_id, $row->unit_id]
            )->getResult();
            $balances = [];
            foreach ($purchases as $purchase) {
                $balances[(int) $purchase->idpembelian] = (int) $purchase->sisa;
            }
            $split = SupplierCreditAllocator::split($amount, array_sum($balances));
            $applied = $split['applied'];
            $allocations = $applied > 0 ? SupplierCreditAllocator::plan($balances, $applied) : [];

            foreach ($purchases as $purchase) {
                $allocated = $allocations[(int) $purchase->idpembelian] ?? 0;
                if ($allocated === 0) {
                    continue;
                }
                $newBalance = (int) $purchase->sisa - $allocated;
                if (! $db->table('pembelian')->where('idpembelian', $purchase->idpembelian)->update([
                    'sisa' => $newBalance,
                    'status' => $newBalance === 0 ? 'Lunas' : 'Belum Lunas',
                ]) || ! $db->table('alokasi_kredit_retur_suplier')->insert([
                    'kredit_id' => $creditId,
                    'pembelian_id' => $purchase->idpembelian,
                    'nominal' => $allocated,
                ])) {
                    throw new \RuntimeException('Alokasi kredit ke utang supplier gagal.');
                }
                (new HutangPiutangService())->syncFromPembelian((int) $purchase->idpembelian);
            }

            $accounts = $db->table('no_akun')
                ->select('no_akun, nama_akun')
                ->whereIn('no_akun', [$debitAccount, $receivableAccount, $creditAccount])
                ->get()->getResult();
            $accountNames = [];
            foreach ($accounts as $account) {
                $accountNames[$account->no_akun] = $account->nama_akun;
            }
            if (! isset($accountNames[$debitAccount], $accountNames[$receivableAccount], $accountNames[$creditAccount])) {
                throw new \RuntimeException('Akun jurnal belum terdaftar.');
            }

            $date = date('Y-m-d');
            $lines = [[$receivableAccount, $amount, 0], [$creditAccount, 0, $amount]];
            if ($applied > 0) {
                $lines[] = [$debitAccount, $applied, 0];
                $lines[] = [$receivableAccount, 0, $applied];
            }
            foreach ($lines as $line) {
                if (! $db->table('jurnal')->insert([
                    'tanggal' => $date,
                    'no_akun' => $line[0],
                    'nama_akun' => $accountNames[$line[0]],
                    'debet' => $line[1],
                    'kredit' => $line[2],
                    'keterangan' => 'Kredit retur supplier ' . $row->no_retur_suplier . ' / ' . $creditNote,
                    'id_referensi' => $creditId,
                    'tabel_referensi' => 'kredit_retur_suplier',
                    'id_unit' => $row->unit_id,
                    'id_akun' => $userId,
                ])) {
                    throw new \RuntimeException('Jurnal kredit retur gagal disimpan.');
                }
            }

            if (! $db->table('kredit_retur_suplier')->where('id', $creditId)->where('status', 'menunggu')->update([
                'status' => 'disetujui',
                'nominal' => $amount,
                'sisa_kredit' => $split['remaining'],
                'nomor_nota_kredit' => $creditNote,
                'akun_debet' => $debitAccount,
                'akun_piutang' => $receivableAccount,
                'akun_kredit' => $creditAccount,
                'disetujui_oleh' => $userId,
                'disetujui_pada' => date('Y-m-d H:i:s'),
            ])) {
                throw new \RuntimeException('Persetujuan kredit retur gagal disimpan.');
            }

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Transaksi kredit retur gagal disimpan.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
