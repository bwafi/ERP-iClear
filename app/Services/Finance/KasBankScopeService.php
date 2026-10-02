<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Models\ModelAlokasiSaldoKasBank;

/**
 * Lapis kedua scope Kas & Bank: ACCOUNT SCOPE (hak atas rekening fisik).
 *
 * PISAH dari FinanceScopeService yang hanya menangani USER SCOPE
 * (resolveAllowedUnits = unit mana yang boleh diakses user).
 *
 * TIGA JENIS REKENING
 * -------------------
 * 1. FINANCE_HO (akun_kas_bank.is_finance_ho = 1), mis. IRA/BCA.
 *    Bukan milik unit mana pun, TIDAK butuh alokasi_saldo_kas_bank.
 *    Aksesnya BERARAH:
 *      - destination : unit mana pun yang memang boleh bertransaksi
 *                      ("Unit 1 -> IRA" dan "Unit 2 -> IRA" sama-sama valid);
 *      - source      : HANYA role financeHoSourceRoles (ROOT / Finance).
 *    "Boleh transfer KE IRA" TIDAK berarti boleh memakai IRA sebagai sumber.
 *
 * 2. UNIT (is_shared = 0, unit_id = X): hanya unit X, dua arah.
 *
 * 3. SHARED (is_shared = 1): unit yang berhak dari alokasi_saldo_kas_bank.
 *    Unit lain TIDAK otomatis punya hak walaupun ada di resolveAllowedUnits().
 *    Akses efektif = irisan user scope & account scope.
 *
 * PENTING: FINANCE_HO dan SHARED sama-sama berbentuk `unit_id NULL,
 * is_shared = 1`, jadi keduanya WAJIB dibedakan lewat flag eksplisit
 * is_finance_ho. Menebaknya dari "punya alokasi atau tidak" adalah lubang
 * keamanan: rekening shared yang alokasinya belum dikonfigurasi akan otomatis
 * berubah jadi rekening Finance/HO.
 *
 * Konsekuensi: "Semua Cabang (Konsolidasi)" berarti seluruh rekening yang
 * account scope-nya BERIRISAN dengan user scope — bukan seluruh rekening yang
 * ada di database.
 */
class KasBankScopeService
{
    public const KIND_FINANCE_HO = 'FINANCE_HO';
    public const KIND_SHARED    = 'SHARED';
    public const KIND_UNIT      = 'UNIT';

    protected FinanceScopeService $userScope;
    protected ModelAkunKasBank $akun;
    protected ModelAlokasiSaldoKasBank $alokasi;

    /** Cache unitIds entitled per akun (satu request = satu instance). */
    protected array $entitledCache = [];

    /** Cache userUnitIds. */
    protected ?array $userUnitIdsCache = null;

    public function __construct(?FinanceScopeService $userScope = null)
    {
        $this->userScope = $userScope ?? new FinanceScopeService();
        $this->akun      = new ModelAkunKasBank();
        $this->alokasi  = new ModelAlokasiSaldoKasBank();
    }

    // =====================================================================
    // LAPIS 1 — USER SCOPE (delegasi, bukan miliknya)
    // =====================================================================

    /**
     * Unit yang boleh diakses user login (user scope).
     * @return int[]
     */
    public function userUnitIds(): array
    {
        if ($this->userUnitIdsCache !== null) {
            return $this->userUnitIdsCache;
        }

        $ids = array_map('intval', array_column(
            array_map('get_object_vars', $this->userScope->resolveAllowedUnits()),
            'idunit'
        ));

        return $this->userUnitIdsCache = array_values(array_filter($ids, static fn ($id) => $id > 0));
    }

    /**
     * Apakah user boleh bertransaksi pada unit tertentu (user scope).
     */
    public function userBolehUnit(int $unitId): bool
    {
        return $unitId > 0 && in_array($unitId, $this->userUnitIds(), true);
    }

    /**
     * Role yang boleh MENARIK dana dari rekening Finance/HO.
     *
     * @return int[]
     */
    public static function financeHoSourceRoles(): array
    {
        return (new \Config\Finance())->financeHoSourceRoles;
    }

    /**
     * Apakah role tertentu boleh memakai rekening Finance/HO sebagai SUMBER
     * (menarik dana). Ini permission REKENING, bukan user scope.
     */
    public static function roleBolehFinanceHoSource(int $role): bool
    {
        return in_array($role, self::financeHoSourceRoles(), true);
    }

    // =====================================================================
    // LAPIS 2 — ACCOUNT SCOPE (milik kelas ini)
    // =====================================================================

    /**
     * Jenis rekening: FINANCE_HO | SHARED | UNIT.
     *
     * @param  object|array $akun baris akun_kas_bank
     */
    public function accountKind($akun): string
    {
        $akun = (object) (array) $akun;

        if ((int) ($akun->is_finance_ho ?? 0) === 1) {
            return self::KIND_FINANCE_HO;
        }

        return (int) ($akun->is_shared ?? 0) === 1
            ? self::KIND_SHARED
            : self::KIND_UNIT;
    }

    /**
     * Apakah rekening ini milik Finance/HO (bukan milik unit, tanpa alokasi)?
     */
    public function isFinanceHo($akun): bool
    {
        return $this->accountKind($akun) === self::KIND_FINANCE_HO;
    }

    /**
     * Unit yang entitled atas satu rekening fisik (account scope).
     *
     * PENTING untuk FINANCE_HO: hasilnya KOSONG dengan sengaja. Tidak ada
     * unit yang "memiliki" rekening HO, jadi tidak ada irisan unit yang bisa
     * dihitung. Jangan pakai method ini untuk Finance/HO — pakai
     * canUseAsDestination()/canUseAsSource() yang paham aturan berarah.
     *
     * @param  object|array $akun baris akun_kas_bank
     * @return int[]
     */
    public function accountAllowedUnits($akun): array
    {
        $akun = (object) (array) $akun;
        if (!isset($akun->idakun_kas_bank)) {
            return [];
        }

        $kind = $this->accountKind($akun);

        if ($kind === self::KIND_FINANCE_HO) {
            return [];
        }

        if ($kind === self::KIND_SHARED) {
            // Rekening bersama antar-unit: hak SEPENUHNYA dari tabel alokasi.
            return $this->entitledUnitIds((int) $akun->idakun_kas_bank);
        }

        // Rekening non-shared: hanya unit pemiliknya.
        $unitId = (int) ($akun->unit_id ?? 0);

        return $unitId > 0 ? [$unitId] : [];
    }

    /**
     * Unit-unit yang punya baris alokasi pada satu rekening.
     *
     * @return int[]
     */
    public function entitledUnitIds(int $akunId): array
    {
        if (isset($this->entitledCache[$akunId])) {
            return $this->entitledCache[$akunId];
        }

        $rows  = $this->alokasi->select('unit_id')
            ->where('akun_kas_bank_id', $akunId)
            ->groupBy('unit_id')
            ->findAll();
        $ids = array_values(array_unique(array_map('intval', array_column($rows, 'unit_id'))));
        sort($ids);

        return $this->entitledCache[$akunId] = array_values(array_filter($ids, static fn ($id) => $id > 0));
    }

    /**
     * Apakah unit punya hak atas rekening ini (account scope saja).
     */
    public function isUnitEntitled($akun, int $unitId): bool
    {
        if ($unitId <= 0) {
            return false;
        }

        return in_array($unitId, $this->accountAllowedUnits($akun), true);
    }

    /**
     * Guard rekening untuk satu unit — account scope, TANPA arah.
     *
     * Hanya berlaku untuk rekening UNIT & SHARED. Rekening Finance/HO tidak
     * punya unit pemilik sehingga selalu false di sini; itu bukan penolakan,
     * tapi "rekening ini memang tidak punya hak unit". Jangan dipakai sebagai
     * pengganti canUseAsSource()/canUseAsDestination().
     */
    public function cekAkunUntukUnit($akun, int $unitId): bool
    {
        return $this->isUnitEntitled($akun, $unitId);
    }

    // =====================================================================
    // AKSES BERDASARKAN ARAH — ini yang dipakai form & guard server
    // =====================================================================

    /**
     * Boleh dipakai sebagai rekening SUMBER (dana keluar)?
     *
     *   FINANCE_HO -> HANYA role financeHoSourceRoles (ROOT / Finance).
     *                 Sengaja TIDAK memakai resolveAllowedUnits(): user scope
     *                 bukan permission menarik dana dari rekening HO.
     *   UNIT       -> unit leg harus = akun.unit_id DAN dalam user scope.
     *   SHARED     -> unit leg harus punya baris alokasi DAN dalam user scope.
     *                 Unit yang cuma ada di resolveAllowedUnits() TIDAK
     *                 otomatis punya hak.
     *
     * @param object|array $akun
     */
    public function canUseAsSource($akun, ?int $unitId, int $role): bool
    {
        $akun = (object) (array) $akun;
        $kind = $this->accountKind($akun);

        if ($kind === self::KIND_FINANCE_HO) {
            // Unit tidak relevan untuk HO; yang menentukan hanya role.
            return self::roleBolehFinanceHoSource($role);
        }

        if ($unitId === null || $unitId <= 0) {
            return false;
        }

        return $this->userBolehUnit($unitId) && $this->isUnitEntitled($akun, $unitId);
    }

    /**
     * Boleh dipakai sebagai rekening TUJUAN (dana masuk)?
     *
     *   FINANCE_HO -> unit mana pun yang memang boleh bertransaksi. TIDAK ada
     *                 syarat role: "Unit 1 -> IRA" dan "Unit 2 -> IRA" sama-
     *                 sama valid. Permission yang dibutuhkan adalah permission
     *                 transaksi dari rekening ASAL-nya, bukan dari IRA.
     *   UNIT       -> unit leg harus = akun.unit_id DAN dalam user scope.
     *   SHARED     -> unit leg harus punya baris alokasi DAN dalam user scope.
     *
     * @param object|array $akun
     */
    public function canUseAsDestination($akun, ?int $unitId, int $role): bool
    {
        $akun = (object) (array) $akun;
        $kind = $this->accountKind($akun);

        if ($kind === self::KIND_FINANCE_HO) {
            // HO menerima dana dari unit mana pun yang boleh bertransaksi.
            return $unitId === null || $unitId <= 0 || $this->userBolehUnit($unitId);
        }

        if ($unitId === null || $unitId <= 0) {
            return false;
        }

        return $this->userBolehUnit($unitId) && $this->isUnitEntitled($akun, $unitId);
    }

    /**
     * Akses efektif dua arah (source DAN destination) — dipakai untuk menyaring
     * daftar rekening form supaya yang tampil hanya rekening yang benar-benar
     * bisa dipakai pada unit tersebut.
     *
     * @param object|array $akun
     */
    public function bolehPakaiRekening($akun, ?int $unitId = null, ?int $role = null): bool
    {
        $role ??= (int) session('ID_JABATAN');

        return $this->canUseAsSource($akun, $unitId, $role)
            && $this->canUseAsDestination($akun, $unitId, $role);
    }


    /**
     * Unit hasil irisan untuk satu rekening (untuk ditampilkan/di debugging).
     *
     * Untuk FINANCE_HO hasilnya KOSONG: rekening HO tidak dimiliki unit mana
     * pun. Jangan dipakai sebagai penentu akses Finance/HO.
     *
     * @return int[]
     */
    public function effectiveUnits($akun): array
    {
        return array_values(array_intersect(
            $this->userUnitIds(),
            $this->accountAllowedUnits($akun)
        ));
    }

    // =====================================================================
    // LISTING REKENING — SELALU hasil irisan kedua scope
    // =====================================================================

    /**
     * Rekening yang boleh dilihat dipakai user.
     *
     * @param int[]  $userUnitIds                user scope
     * @param int|null $unitTerpilih             null = konsolidasi
     * @param bool   $aktifOnly                  form transaksi hanya akun aktif
     * @param bool   $includeUnallocatedShared erekening shared yang belum punya
     *                                             alokasi sama sekali — hanya untuk
     *                                             halaman master, supaya admin
     *                                             bisainker configuring alokasi
     *                                             (jika tidak, rekening tsb mustahil
     *                                             diperbaiki dari UI).
     */
    public function akunTerlihat(
        array $userUnitIds,
        ?int $unitTerpilih = null,
        bool $aktifOnly = false,
        bool $includeUnallocatedShared = false
    ): array {
        return $this->akun->getDalamScopeUnit(
            $userUnitIds,
            $unitTerpilih,
            $aktifOnly,
            $includeUnallocatedShared
        );
    }

    /**
     * Id rekening yang boleh dilihat dipakai user — untuk memfilter
     * transaksi_kas_bank (supaya daftar transaksi & ringkasan tidak
     * membocorkan rekening di luar account scope).
     *
     * @return int[]
     */
    public function akunIdsTerlihat(
        array $userUnitIds,
        ?int $unitTerpilih = null,
        bool $aktifOnly = false,
        bool $includeUnallocatedShared = false
    ): array {
        $rows = $this->akunTerlihat($userUnitIds, $unitTerpilih, $aktifOnly, $includeUnallocatedShared);

        return array_map('intval', array_column($rows, 'idakun_kas_bank'));
    }

    /**
     * Peta akunId => unitIds entitled, untuk ditampilkan di view.
     *
     * @param  object[] $akun
     * @return array<int, int[]>
     */
    public function petaAccountScope(array $akun): array
    {
        $peta = [];
        foreach ($akun as $a) {
            $peta[(int) $a->idakun_kas_bank] = $this->accountAllowedUnits($a);
        }

        return $peta;
    }

    /**
     * Reset cache (dipakai test / setelah menulis alokasi).
     */
    public function flushCache(): void
    {
        $this->entitledCache   = [];
        $this->userUnitIdsCache = null;
    }
}
