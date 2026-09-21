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
        'submission_key',
        'sumber_tipe',
        'sumber_id',
        'keterangan',
        'bukti',
        'input_by',
        'created_at',
        'updated_at',
    ];

    /**
     * Saldo fisik satu rekening (saldo awal fisik + seluruh pemasukan -
     * pengeluaran, lintas unit). Rekening bersama = gabungan semua unit.
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

    /**
     * Alias getSaldoAkun: saldo fisik rekening (seluruh unit).
     */
    public function getSaldoFisikAkun(int $akunId): int
    {
        return $this->getSaldoAkun($akunId);
    }

    /**
     * Saldo alokasi per unit pada satu rekening fisik: alokasi saldo awal unit
     * (tabel alokasi_saldo_kas_bank) + pemasukan unit - pengeluaran unit.
     * TIDAK mengubah saldo fisik; hanya atribusi untuk laporan/KPI per unit.
     */
    public function getSaldoUnitAkun(int $akunId, int $unitId): int
    {
        $db = db_connect();
        $alokasi = $db->table('alokasi_saldo_kas_bank')
            ->select('COALESCE(SUM(nominal), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->get()
            ->getRow();

        $masuk = $this->select('COALESCE(SUM(jumlah), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->where('arah', 'MASUK')
            ->get()
            ->getRow();

        $keluar = $this->select('COALESCE(SUM(jumlah), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->where('arah', 'KELUAR')
            ->get()
            ->getRow();

        return (int)($alokasi->total ?? 0) + (int)($masuk->total ?? 0) - (int)($keluar->total ?? 0);
    }

    /**
     * Total alokasi saldo awal lintas unit untuk satu rekening fisik.
     */
    public function getTotalAlokasiUnit(int $akunId): int
    {
        $row = db_connect()->table('alokasi_saldo_kas_bank')
            ->select('COALESCE(SUM(nominal), 0) as total')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getRow();

        return (int)($row->total ?? 0);
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