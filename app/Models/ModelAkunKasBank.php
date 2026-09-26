<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAkunKasBank extends Model
{
    protected $table = 'akun_kas_bank';
    protected $primaryKey = 'idakun_kas_bank';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idakun_kas_bank',
        'unit_id',
        'tipe',
        'nama_akun',
        'bank_idbank',
        'no_akun_coa',
        'status',
        'is_shared',
        'created_by',
        'created_at',
        'updated_at',
    ];

    /**
     * Daftar rekening fisik (akun_kas_bank) dilihat dari sudut satu unit.
     * - KAS: fisik per unit -> hanya KAS milik unit tsb.
     * - BANK: rekening fisik -> semua BANK aktif (bisa lintas unit), sehingga
     *   rekening bersama TIDAK tampil seolah-olah milik eksklusif satu unit.
     */
    public function getAllWithUnit(?int $unitId = null)
    {
        $builder = $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek, no_akun.nama_akun as nama_akun_coa')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->join('no_akun', 'no_akun.no_akun = akun_kas_bank.no_akun_coa', 'left')
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.unit_id', 'ASC');

        if (!empty($unitId)) {
            $unitId = (int)$unitId;
            $builder->groupStart()
                ->groupStart()
                    ->where('akun_kas_bank.tipe', 'KAS')
                    ->where('akun_kas_bank.unit_id', $unitId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('akun_kas_bank.tipe', 'BANK')
                ->groupEnd()
            ->groupEnd();
        }

        return $builder->findAll();
    }

    /**
     * Akun aktif yang bisa dipakai satu unit (untuk form transaksi).
     * KAS unit tsb + semua rekening BANK fisik aktif.
     */
    public function getAktifUntukUnit(int $unitId)
    {
        return $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->where('akun_kas_bank.status', 'aktif')
            ->groupStart()
                ->groupStart()
                    ->where('akun_kas_bank.tipe', 'KAS')
                    ->where('akun_kas_bank.unit_id', (int)$unitId)
                ->groupEnd()
                ->orWhere('akun_kas_bank.tipe', 'BANK')
            ->groupEnd()
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.unit_id', 'ASC')
            ->orderBy('akun_kas_bank.nama_akun', 'ASC')
            ->findAll();
    }

    public function getAktifByUnit(int $unitId)
    {
        return $this->where('unit_id', $unitId)
            ->where('status', 'aktif')
            ->orderBy('tipe', 'ASC')
            ->findAll();
    }

    public function getAktifAll()
    {
        return $this->select('akun_kas_bank.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->where('akun_kas_bank.status', 'aktif')
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.unit_id', 'ASC')
            ->findAll();
    }

    /**
     * Rekening BANK fisik untuk satu idbank (maksimal satu baris: 1 rekening
     * fisik = 1 akun). Dipakai resolveAkun lintas unit.
     */
    public function getBankByBankIdbank(string $bankId)
    {
        return $this->where('tipe', 'BANK')
            ->where('bank_idbank', $bankId)
            ->where('status', 'aktif')
            ->first();
    }

    /**
     * Rekening yang boleh diakses SATU unit (admin cabang / user non-lintas):
     * - KAS milik unit tsb,
     * - BANK yang memang milik unit tsb (unit_id = unit),
     * - BANK rekening fisik bersama yang dialokasikan ke unit tsb
     *   (ada baris di alokasi_saldo_kas_bank untuk unit tsb).
     * Rekening fisik milik unit lain TIDAK terlihat.
     */
    public function getAllWithUnitTerbatas(int $unitId)
    {
        $unitId = (int)$unitId;
        $akunTbl  = $this->db->prefixTable('akun_kas_bank');
        $alokasi  = $this->db->prefixTable('alokasi_saldo_kas_bank');

        return $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek, no_akun.nama_akun as nama_akun_coa')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->join('no_akun', 'no_akun.no_akun = akun_kas_bank.no_akun_coa', 'left')
            ->groupStart()
                ->where('akun_kas_bank.tipe', 'KAS')
                ->where('akun_kas_bank.unit_id', $unitId)
                ->orGroupStart()
                    ->where('akun_kas_bank.tipe', 'BANK')
                    ->where('akun_kas_bank.unit_id', $unitId)
                ->groupEnd()
                ->orWhere(
                    'EXISTS (SELECT 1 FROM ' . $alokasi . ' a ' .
                    'WHERE a.akun_kas_bank_id = ' . $akunTbl . '.idakun_kas_bank ' .
                    'AND a.unit_id = ' . $unitId . ')',
                    null,
                    false
                )
            ->groupEnd()
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.unit_id', 'ASC')
            ->findAll();
    }

    /**
     * Versi aktif (untuk form transaksi) dari daftar rekening satu unit.
     */
    public function getAktifUntukUnitTerbatas(int $unitId)
    {
        $unitId = (int)$unitId;
        $akunTbl  = $this->db->prefixTable('akun_kas_bank');
        $alokasi = $this->db->prefixTable('alokasi_saldo_kas_bank');

        return $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->where('akun_kas_bank.status', 'aktif')
            ->groupStart()
                ->where('akun_kas_bank.tipe', 'KAS')
                ->where('akun_kas_bank.unit_id', $unitId)
                ->orGroupStart()
                    ->where('akun_kas_bank.tipe', 'BANK')
                    ->where('akun_kas_bank.unit_id', $unitId)
                ->groupEnd()
                ->orWhere(
                    'EXISTS (SELECT 1 FROM ' . $alokasi . ' a ' .
                    'WHERE a.akun_kas_bank_id = ' . $akunTbl . '.idakun_kas_bank ' .
                    'AND a.unit_id = ' . $unitId . ')',
                    null,
                    false
                )
            ->groupEnd()
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.nama_akun', 'ASC')
            ->findAll();
    }
}