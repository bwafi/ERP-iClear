<?php

namespace App\Services\Presensi;

/**
 * Aturan akses roster & foto presensi. Config dibaca per panggilan supaya
 * test bisa mengganti daftar role tanpa membuat ulang service.
 */
class PresensiScopeService
{
    public function bolehKelolaUnit(
        int $jabatan,
        int $unitSesi,
        int $unitTarget
    ): bool {
        $config = config("Presensi");

        if (in_array($jabatan, $config->rosterSemuaUnit, true)) {
            return true;
        }

        return in_array($jabatan, $config->rosterUnitSendiri, true) &&
            $unitTarget === $unitSesi;
    }

    public function bolehKoreksiLampau(int $jabatan): bool
    {
        return $jabatan === config("Presensi")->koreksiLampau;
    }

    public function bolehLihatFoto(
        int $jabatan,
        int $unitSesi,
        int $akunId,
        int $akunPemilik,
        int $unitPemilik
    ): bool {
        if ($akunId === $akunPemilik) {
            return true;
        }

        $config = config("Presensi");
        if (in_array($jabatan, $config->rosterSemuaUnit, true)) {
            return true;
        }

        if (in_array($jabatan, $config->rosterUnitSendiri, true)) {
            return $unitPemilik === $unitSesi;
        }

        return $jabatan === $config->jabatanSpv && $this->spvMembina($akunId, $unitPemilik);
    }

    /** Apakah unit pemilik foto/surat termasuk wilayah SPV ini. */
    private function spvMembina(int $spvId, int $unit): bool
    {
        $db = db_connect();
        if (! $db->tableExists('spv_units')) {
            return false;
        }

        return $db->table('spv_units')
            ->where('spv_id', $spvId)
            ->where('unit_id', $unit)
            ->countAllResults() > 0;
    }
}
