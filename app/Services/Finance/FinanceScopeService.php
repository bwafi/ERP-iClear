<?php

namespace App\Services\Finance;

use App\Models\ModelUnit;
use App\Models\ModelAuth;
use Config\Database;
use Config\Finance;

/**
 * Bekas unit Dashboard Finance (replika scopeInfo SummaryKPI).
 *
 * - Role "lintas unit" (Finance/Root/Direktur/Manager): 0, 1, 2, 34
 * - Manager Keuangan (41): hanya unit sendiri (ID_UNIT dari akun)
 * - SPV (40): hanya unit SPV (tabel spv_units)
 */
class FinanceScopeService
{
    protected $modelUnit;
    protected $modelAuth;

    public function __construct()
    {
        $this->modelUnit = new ModelUnit();
        $this->modelAuth = new ModelAuth();
    }

    /**
     * ID_JABATAN global yang boleh mengisi.
     */
    public static function inputRoles(): array
    {
        return (new Finance())->financeInputRoles;
    }

    /**
     * ID_JABATAN global yang boleh melihat.
     */
    public static function viewRoles(): array
    {
        return (new Finance())->financeViewRoles;
    }

    /**
     * Profil akun yang login beserta jabatan & unitnya.
     *
     * @return array{me: object|null, myRole: int, myUnit: int, myId: int, isLintas: bool}
     */
    public function scopeInfo(): array
    {
        $me = $this->modelAuth->getById((int) session('ID_AKUN'));
        $myRole = (int) ($me->ID_JABATAN ?? 0);
        $myUnit = (int) ($me->ID_UNIT ?? 0);
        $myId = (int) ($me->ID_AKUN ?? session('ID_AKUN'));

        return [
            'me' => $me,
            'myRole' => $myRole,
            'myUnit' => $myUnit,
            'myId' => $myId,
            'isLintas' => in_array($myRole, self::inputRoles(), true),
        ];
    }

    /**
     * Daftar unit yang boleh dilihat pengguna login saat ini.
     *
     * @return array<int, object> objek unit (idunit, NAMA_UNIT)
     */
    public function resolveAllowedUnits(): array
    {
        $info = $this->scopeInfo();

        if ($info['isLintas']) {
            return $this->modelUnit->orderBy('idunit', 'ASC')->findAll();
        }

        if (in_array($info['myRole'], self::viewRoles(), true)) {
            $unit = $this->modelUnit->find($info['myUnit']);

            return $unit ? [$unit] : [];
        }

        // SPV via tabel spv_units (spv_id = ID_AKUN)
        $db = Database::connect();
        $rows = $db->table('spv_units')
            ->where('spv_id', $info['myId'])
            ->get()
            ->getResult();

        $unitIds = array_map('intval', array_column($rows, 'unit_id'));

        if (!empty($unitIds)) {
            return $this->modelUnit->whereIn('idunit', $unitIds)->orderBy('idunit', 'ASC')->findAll();
        }

        $unit = $this->modelUnit->find($info['myUnit']);

        return $unit ? [$unit] : [];
    }

    /**
     * ID unit yang boleh dilihat (cocok dengan param, atau fallback pertama).
     *
     * CATATAN: metode ini TIDAK pernah mengembalikan null untuk user dengan
     * lebih dari satu unit — selalu mengembalikan unit pertama yang diizinkan.
     * Modul Dashboard Finance / HutangPiutang bergantung pada perilaku itu.
     * Jangan dipakai sebagai "user scope" modul yang butuh mode konsolidasi
     * (null); pakai resolveSelectedUnitIdAtauKosolidasi().
     */
    public function resolveSelectedUnitId(?string $requestUnit): ?int
    {
        $allowedIds = $this->allowedUnitIds();

        if (empty($allowedIds)) {
            return null;
        }

        $req = (int) ($requestUnit ?: 0);
        if ($req && in_array($req, $allowedIds, true)) {
            return $req;
        }

        return count($allowedIds) === 1 ? $allowedIds[0] : $allowedIds[0];
    }

    /**
     * USER SCOPE dengan mode konsolidasi yang jujur.
     *
     * Return unit yang dipilih user bila valid. Bila user tidak memilih /
     * memilih tidak valid:
     *   - hanya boleh 1 unit  -> dipaksa ke unit itu (tidak ada pilihan lain),
     *   - boleh >1 unit      -> null = KONSOLIDASI.
     *
     * null berarti "semua unit dalam user scope" dan itu HANYA bermakna
     * setelah data difilter dengan ACCOUNT SCOPE (lihat KasBankScopeService).
     * Memakai null sebagai konsolidasi tanpa filter account scope akan
     * membocorkan seluruh rekening perusahaan.
     */
    public function resolveSelectedUnitIdAtauKosolidasi(?string $requestUnit): ?int
    {
        $allowedIds = $this->allowedUnitIds();

        if (empty($allowedIds)) {
            return null;
        }

        $req = (int) ($requestUnit ?: 0);
        if ($req && in_array($req, $allowedIds, true)) {
            return $req;
        }

        return count($allowedIds) === 1 ? $allowedIds[0] : null;
    }

    /**
     * @return int[] id unit yang boleh diakses user login
     */
    public function allowedUnitIds(): array
    {
        $ids = array_map('intval', array_column(
            array_map('get_object_vars', $this->resolveAllowedUnits()),
            'idunit'
        ));

        return array_values(array_filter($ids, static fn ($id) => $id > 0));
    }

    /**
     * Apakah pengguna boleh mengisi form input Dashboard Finance.
     */
    public function canInput(): bool
    {
        return in_array($this->scopeInfo()['myRole'], self::inputRoles(), true);
    }

    // =====================================================================
    // Finance Cut-off / Scope data legacy-opening-active
    // =====================================================================

    public const SCOPE_ACTIVE = 'active';
    public const SCOPE_OPENING = 'opening';
    public const SCOPE_LEGACY = 'legacy';

    /**
     * Tanggal DASAR / statement cut-off (YYYY-MM-DD).
     *
     * Hanya untuk ditampilkan dan untuk menandai baris statement reference
     * (saldo_awal_kas_bank) serta baseline kas per unit. BUKAN batas bawah
     * ledger — untuk itu pakai periodeMulaiDate().
     */
    public static function cutoffDate(): string
    {
        return (string) (new Finance())->cutoffDate;
    }

    /**
     * Hari pertama periode operasional baru (YYYY-MM-DD) = batas bawah ledger.
     *
     * Semua query saldo/mutasi Kas & Bank WAJIB memfilter
     * `tanggal >= periodeMulaiDate()`. Memakai cutoffDate() di sana akan
     * menghitung transaksi pada tanggal baseline statement sebagai mutasi
     * baru, sehingga saldo fisik terhitung dua kali.
     */
    public static function periodeMulaiDate(): string
    {
        return (string) (new Finance())->periodeMulaiDate;
    }

    /**
     * Cut-off KHUSUS modul Kas & Bank (YYYY-MM-DD).
     *
     * Dipakai jalur KasBank saja: opening KAS/bank, statement, guard Setor/Tarik,
     * filter movement, dan UI input opening. Modul LAIN (TutupKasir, Hutang-
     * Piutang, KPI) tetap memakai cutoffDate() — jangan menyatukan keduanya.
     */
    public static function kasBankCutoffDate(): string
    {
        return (string) (new Finance())->kasBankCutoffDate;
    }

    /**
     * Batas bawah ledger KHUSUS Kas & Bank (YYYY-MM-DD) = kasBankCutoffDate + 1.
     *
     * Semua query movement/saldo Kas & Bank WAJIB memfilter
     * `tanggal >= kasBankPeriodeMulaiDate()`. Memakai kasBankCutoffDate() di
     * sana akan menghitung transaksi tanggal baseline sebagai mutasi baru.
     */
    public static function kasBankPeriodeMulaiDate(): string
    {
        return (string) (new Finance())->kasBankPeriodeMulaiDate;
    }

    /**
     * Apakah tanggal transaksi termasuk LEGACY menurut periode Kas & Bank.
     *
     * Legacy = sebelum kasBankPeriodeMulaiDate(). Transaksi 1–7 Okt legacy
     * ketika cut-off KasBank 7 Okt.
     */
    public static function isKasBankLegacyTransaction($tanggal): bool
    {
        $t = self::tanggalStr($tanggal);

        return $t !== '' && $t < self::kasBankPeriodeMulaiDate();
    }

    /**
     * Apakah tanggal transaksi termasuk AKTIF menurut periode Kas & Bank.
     */
    public static function isActiveKasBankTransaction($tanggal): bool
    {
        $t = self::tanggalStr($tanggal);

        return $t !== '' && $t >= self::kasBankPeriodeMulaiDate();
    }

    /**
     * Normalisasi tanggal apapun (datetime/date/null) ke "YYYY-MM-DD".
     */
    public static function tanggalStr($tanggal): string
    {
        if (empty($tanggal)) {
            return '';
        }
        if ($tanggal instanceof \DateTimeInterface) {
            return $tanggal->format('Y-m-d');
        }
        $s = (string) $tanggal;
        $t = strtotime($s);

        return $t !== false ? date('Y-m-d', $t) : substr($s, 0, 10);
    }

    /**
     * Apakah tanggal transaksi termasuk LEGACY.
     *
     * Legacy = sebelum periode operasional baru. Perhatikan batasnya adalah
     * periodeMulaiDate(), BUKAN cutoffDate(): transaksi bertanggal 30 Sep
     * (tanggal baseline statement) masih legacy.
     */
    public static function isLegacyTransaction($tanggal): bool
    {
        $t = self::tanggalStr($tanggal);

        return $t !== '' && $t < self::periodeMulaiDate();
    }

    /**
     * Apakah tanggal transaksi termasuk AKTIF (periode operasional baru).
     */
    public static function isActiveTransaction($tanggal): bool
    {
        $t = self::tanggalStr($tanggal);

        return $t !== '' && $t >= self::periodeMulaiDate();
    }
}