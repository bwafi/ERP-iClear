<?php

namespace App\Services\Finance;

use App\Models\ModelUnit;
use App\Models\ModelAuth;
use Config\Database;
use Config\Finance;

/**
 * Bekas unit Dashboard Finance (replika scopeInfo SummaryKPI).
 *
 * - Role "lintas unit" (Admin Center/Root/Direktur/Manager): 0, 1, 2, 34
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
     */
    public function resolveSelectedUnitId(?string $requestUnit): ?int
    {
        $allowed = $this->resolveAllowedUnits();
        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $allowed),
            'idunit'
        ));

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
     * Apakah pengguna boleh mengisi form input Dashboard Finance.
     */
    public function canInput(): bool
    {
        return in_array($this->scopeInfo()['myRole'], self::inputRoles(), true);
    }
}