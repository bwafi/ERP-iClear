<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelTransaksiKasBank extends Model
{
    protected $table = 'transaksi_kas_bank';
    protected $primaryKey = 'idtransaksi';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idtransaksi',
        'tanggal',
        'unit_id',
        'akun_kas_bank_id',
        'jenis',
        'arah',
        'jumlah',
        'akun_tujuan_id',
        'transfer_ref',
        'sumber_tipe',
        'sumber_id',
        'keterangan',
        'bukti',
        'input_by',
        'created_at',
        'updated_at',
    ];

    /**
     * Saldo ledger satu akun (saldo awal + pemasukan - pengeluaran),
     * termasuk transfer & pembayaran antar unit yang memengaruhi akun tsb.
     */
    public function getSaldoAkun(int $akunId): int
    {
        $saldoAwal = db_connect()->table('saldo_awal_kas_bank')
            ->select('COALESCE(SUM(saldo), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getRow();

        $masuk = $this->select('COALESCE(SUM(jumlah), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('arah', 'MASUK')
            ->get()
            ->getRow();

        $keluar = $this->select('COALESCE(SUM(jumlah), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('arah', 'KELUAR')
            ->get()
            ->getRow();

        return (int)($saldoAwal->total ?? 0) + (int)($masuk->total ?? 0) - (int)($keluar->total ?? 0);
    }

    public function getByTransferRef(string $transferRef)
    {
        return $this->where('transfer_ref', $transferRef)->findAll();
    }

    public function getPasanganTransfer(string $transferRef, string $arah): ?object
    {
        return $this->where('transfer_ref', $transferRef)->where('arah', $arah)->first();
    }
}