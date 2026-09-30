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
     * Rekening fisik yang berada di dalam SCOPE GANDUNG:
     * irisan antara user scope (unit mana yang boleh diakses user) dan
     * account scope (unit mana yang punya hak atas rekening).
     *
     * ACCOUNT SCOPE (aturan bisnis):
     *   is_shared = 0 -> hanya akun.unit_id
     *   is_shared = 1 -> unit yang punya baris di alokasi_saldo_kas_bank
     *
     * $unitTerpilih null = konsolidasi, yaitu seluruh rekening yang account
     * scope-nya beririsan dengan user scope (BUKAN seluruh rekening).
     *
     * Rekening non-shared milik unit lain TIDAK ikut, dan rekening shared yang
     * hanya dialokasikan ke unit lain juga TIDAK ikut — meskipun user punya
     * akses ke unit-unit tersebut.
     *
     * @param int[]  $userUnitIds               user scope
     * @param int|null $unitTerpilih            null = konsolidasi
     * @param bool   $aktifOnly                 form transaksi: hanya akun aktif
     * @param bool   $includeUnallocatedShared  sertakan rekening shared yang
     *                                            belum punya alokasi sama sekali
     *                                            (halaman master, supaya
     *                                            alokasi bisa dikonfigurasi)
     */
    public function getDalamScopeUnit(
        array $userUnitIds,
        ?int $unitTerpilih = null,
        bool $aktifOnly = false,
        bool $includeUnallocatedShared = false
    ) {
        $userUnitIds = array_values(array_filter(array_map('intval', $userUnitIds), static fn ($id) => $id > 0));

        $akunTbl = $this->db->prefixTable('akun_kas_bank');
        $alokasi = $this->db->prefixTable('alokasi_saldo_kas_bank');

        $builder = $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek, no_akun.nama_akun as nama_akun_coa')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->join('no_akun', 'no_akun.no_akun = akun_kas_bank.no_akun_coa', 'left');

        if ($aktifOnly) {
            $builder->where('akun_kas_bank.status', 'aktif');
        }

        if (empty($userUnitIds)) {
            // User tanpa satu pun unit: tidak ada rekening yang boleh tampil,
            // kecuali mode master untuk melihat rekening shared yang belum
            // dialokasikan (tetap perlu unit agar bisa diisi alokasinya).
            if (! $includeUnallocatedShared) {
                return [];
            }

            $builder->where('1 = 0');
        } else {
            $in = implode(',', $userUnitIds);

            // ---- ACCOUNT SCOPE ∩ USER SCOPE ----
            $builder->groupStart()
                ->groupStart()
                    // non-shared: unit pemilik rekening ada di user scope
                    ->where('akun_kas_bank.is_shared', 0)
                    ->where('akun_kas_bank.is_finance_ho', 0)
                    ->whereIn('akun_kas_bank.unit_id', $userUnitIds)
                ->groupEnd()
                ->orGroupStart()
                    // shared: ada alokasi ke unit dalam user scope
                    ->where('akun_kas_bank.is_shared', 1)
                    ->where('akun_kas_bank.is_finance_ho', 0)
                    ->where(
                        'EXISTS (SELECT 1 FROM ' . $alokasi . ' a ' .
                        'WHERE a.akun_kas_bank_id = ' . $akunTbl . '.idakun_kas_bank ' .
                        'AND a.unit_id IN (' . $in . '))',
                        null,
                        false
                    )
                ->groupEnd()
                ->orGroupStart()
                    // Finance/HO: bukan milik unit, TIDAK butuh alokasi, dan
                    // boleh jadi tujuan dari unit mana pun. Karena itu begitu
                    // user punya minimal satu unit dalam user scope, rekening
                    // HO selalu terlihat.
                    ->where('akun_kas_bank.is_finance_ho', 1)
                ->groupEnd();

            if ($includeUnallocatedShared) {
                // shared tanpa alokasi apa pun -> tampilkan agar bisa dikonfigurasi
                $builder->orGroupStart()
                    ->where('akun_kas_bank.is_shared', 1)
                    ->where('akun_kas_bank.is_finance_ho', 0)
                    ->where(
                        'NOT EXISTS (SELECT 1 FROM ' . $alokasi . ' a ' .
                        'WHERE a.akun_kas_bank_id = ' . $akunTbl . '.idakun_kas_bank)',
                        null,
                        false
                    )
                ->groupEnd();
            }

            $builder->groupEnd();

            // ---- FILTER UNIT TERPILIH (bukan konsolidasi) ----
            if ($unitTerpilih !== null && $unitTerpilih > 0) {
                $builder->groupStart()
                    ->groupStart()
                        ->where('akun_kas_bank.is_shared', 0)
                        ->where('akun_kas_bank.is_finance_ho', 0)
                        ->where('akun_kas_bank.unit_id', $unitTerpilih)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('akun_kas_bank.is_shared', 1)
                        ->where('akun_kas_bank.is_finance_ho', 0)
                        ->where(
                            'EXISTS (SELECT 1 FROM ' . $alokasi . ' a ' .
                            'WHERE a.akun_kas_bank_id = ' . $akunTbl . '.idakun_kas_bank ' .
                            'AND a.unit_id = ' . (int) $unitTerpilih . ')',
                            null,
                            false
                        )
                    ->groupEnd()
                    ->orGroupStart()
                        // Finance/HO tetap relevan untuk unit mana pun yang
                        // dipilih: transfer ke HO sah dari unit tsb.
                        ->where('akun_kas_bank.is_finance_ho', 1)
                    ->groupEnd()
                ->groupEnd();
            }
        }

        return $builder
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->orderBy('akun_kas_bank.unit_id', 'ASC')
            ->orderBy('akun_kas_bank.nama_akun', 'ASC')
            ->findAll();
    }

    /**
     * Rekening BANK fisik untuk satu idbank (maksimal satu baris: 1 rekening
     * fisik = 1 akun). Dipakai resolveAkun. Hanya mengembalikan baris AKTIF;
     * bank tanpa rekening aktif harus diperbaiki di master, bukan dialihkan.
     */
    public function getBankByBankIdbank(string $bankId)
    {
        return $this->where('tipe', 'BANK')
            ->where('bank_idbank', $bankId)
            ->where('status', 'aktif')
            ->first();
    }
}
