<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\RawSql;

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
        'is_finance_ho',
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
     *   - rekening non-Finance, non-shared -> hanya akun.unit_id
     *   - rekening non-Finance, shared      -> unit yang punya baris di
     *     alokasi_saldo_kas_bank. Baris alokasi ADA = punya hak, berapa pun
     *     nominalnya. nominal 0 berarti "belum ada saldo teralokasikan",
     *     BUKAN "tidak punya hak", dan BUKAN "saldo fisik rekening 0".
     *   - rekening FINANCE_HO               -> bukan rekening operasional unit,
     *     jadi tidak punya "unit pemilik". Lihat catatan panjang di bawah.
     *
     * REKENING FINANCE/HO — BUKAN BYPASS UNIVERSAL
     * ------------------------------------------
     * Sebelumnya `is_finance_ho = 1` membuat rekening kas Direksi otomatis
     * terlihat oleh SETIAP user yang punya minimal satu unit. Itu berarti user
     * Head Office (unit 50) — yang memang TIDAK punya rekening operasional
     * sendiri — tetap bisa melihat dan-mentarget seluruh kas Direksi hanya
     * karena flag itu. Fase 1 menutupnya.
     *
     * Aturan baru: sebuah unit hanya boleh menyentuh rekening Finance/HO kalau
     * unit itu MEMILIKI rekening operasional sendiri. Kondisi "memiliki
     * rekening operasional" DITURUNKAN DARI DATA, bukan daftar unit yang
     * ditulis manual:
     *     ada akun non-Finance dengan unit_id = unit tersebut, ATAU
     *     ada alokasi alokasi_saldo_kas_bank untuk unit tersebut.
     *
     * Akibatnya, tanpa daftar unit hardcoded:
     *   - Unit 1..4 (punya rekening bank)  -> boleh, seperti sebelumnya.
     *   - Unit 50 / Head Office (KAS saja)  -> TIDAK otomatis dapat akses.
     *   - Unit 5 (rekening banknya belum dibuat & diverifikasi) -> TIDAK.
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
                    // Finance/HO: bukan milik unit dan tidak butuh alokasi.
                    // TETAPI tidak lagi terbuka untuk semua unit — hanya untuk
                    // unit yang benar-benar punya rekening operasional
                    // sendiri. Head Office tidak punya rekening operasional,
                    // jadi user unit 50 tidak otomatis melihat kas Direksi.
                    ->where('akun_kas_bank.is_finance_ho', 1)
                    ->where(new RawSql($this->sqlUnitPunyaRekeningOperasional($in)), null)
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
                        // Finance/HO boleh jadi tujuan DARI unit yang punya
                        // rekening operasional — sama seperti di atas, dan
                        // dengan batas yang sama.
                        ->where('akun_kas_bank.is_finance_ho', 1)
                        ->where(new RawSql($this->sqlUnitPunyaRekeningOperasional((string) (int) $unitTerpilih)), null)
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
     * SQL EXISTS: apakah unit-unit ini punya rekening OPERASIONAL sendiri?
     *
     * "Operasional" = akun kas-bank tipe BANK, BUKAN Finance/HO, yang AKTIF,
     * dan unit tersebut adalah pemiliknya (unit_id) atau punya baris alokasi.
     *
     * Syarat "tipe BANK" itu penting, bukan teknis. Account KAS TIDAK dihitung:
     * punya laci kas tidak berarti berhak memindahkan uang lewat rekening bank
     * Direksi. Tanpa syarat ini, Head Office dan Unit 5 tetap lolos hanya
     * karena punya akun KAS — persis hal yang harus ditutup.
     *
     * Inilah yang membedakan unit cabang dari Head Office tanpa daftar unit
     * hardcoded: HO hanya punya akun KAS (nonaktif), tanpa rekening bank dan
     * tanpa alokasi ke rekening bank mana pun, sehingga tidak lolos.
     *
     * @param string $unitIn daftar unit_id yang sudah di-escape int, mis. "1,2,3"
     */
private function sqlUnitPunyaRekeningOperasional(string $unitIn): string
    {
        $akunTbl = $this->db->prefixTable('akun_kas_bank');
        $alokasi = $this->db->prefixTable('alokasi_saldo_kas_bank');

        // Catatan penghitung kurung: string ini dirakit dari beberapa potongan,
        // jadi jumlah ')' di ujung mudah kelewat. Pemeriksaan kedalaman kurung
        // di bawah sengaja dipertahankan sebagai penjaga: kalau satu ')' hilang,
        // MariaDB melaporkannya sebagai syntax error yang jauh dari penyebabnya
        // (menunjuk ke ORDER BY), bukan ke subquery ini.
        $sql = 'EXISTS (SELECT 1 FROM ' . $akunTbl . ' op '
            . 'WHERE op.is_finance_ho = 0 AND op.tipe = \'BANK\' AND op.status = \'aktif\' '
            . 'AND ((op.is_shared = 0 AND op.unit_id IN (' . $unitIn . ')) '
            . 'OR (op.is_shared = 1 AND EXISTS (SELECT 1 FROM ' . $alokasi . ' oa '
            . 'WHERE oa.akun_kas_bank_id = op.idakun_kas_bank AND oa.unit_id IN (' . $unitIn . ')))))';

        $tdepth = 0;
        for ($i = 0, $n = strlen($sql); $i < $n; $i++) {
            if ($sql[$i] === '(') {
                $tdepth++;
            } elseif ($sql[$i] === ')') {
                $tdepth--;
            }
        }

        if ($tdepth !== 0) {
            throw new \RuntimeException(
                'sqlUnitPunyaRekeningOperasional(): kurung tidak seimbang (depth=' . $tdepth . ').'
            );
        }

        return $sql;
    }

    /**
     * Rekening BANK fisik untuk satu idbank (maksimal satu baris: 1 rekening
     * fisik = 1 akun). Dipakai resolveAkun. Hanya mengembalikan baris AKTIF;
     * bank tanpa rekening aktif harus diperbaiki di master, bukan dialihkan.
     */
    /**
     * Satu rekening bank fisik <-> satu akun kas-bank aktif.
     *
     * Setelah migration 2026-10-03-000400 hasil `first()` di sini determinis:
     * ada UNIQUE generated column `bank_kunci` untuk setiap akun tipe BANK, jadi
     * satu `bank_idbank` tidak mungkin terpetakan ke dua akun. `orderBy`
     * dipertahankan sebagai dokumentasi urutan resolve — kalau constraint-nya
     * somehow hilang, hasilnya jadi "paling dulu" alih-alih acak.
     *
     * PENTING: idbank adalah VARCHAR dan bisa mengandung spasi/huruf besar
     * (mis. 'BNI-001', ' 15 '), jadi TIDAK pernah di-cast ke int sebelum
     * dibandingkan. Cast ke int membuat 'BNI-001' jadi 0 dan '15 '/'15'
     * dianggap sama.
     */
    public function getBankByBankIdbank(string $bankId)
    {
        return $this->where('tipe', 'BANK')
            ->where('bank_idbank', $bankId)
            ->where('status', 'aktif')
            ->orderBy('idakun_kas_bank', 'ASC')
            ->first();
    }

    /**
     * Sama seperti getBankByBankIdbank, tapi cocok juga dengan akun nonaktif.
     * Dipakai validasi write path supaya pesan errornya bisa membedakan
     * "rekening tidak dikenal" dari "rekening ada tapi nonaktif".
     */
    public function getAkunByBankIdbank(string $bankId)
    {
        return $this->where('tipe', 'BANK')
            ->where('bank_idbank', $bankId)
            ->orderBy('idakun_kas_bank', 'ASC')
            ->first();
    }
}
