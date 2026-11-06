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
        return in_array($unit, $this->spvUnitIds($spvId), true);
    }

    /**
     * Unit yang boleh dilihat di riwayat absen. Role lintas unit melihat
     * semua; SPV hanya wilayah `spv_units`; role lain unit sendiri.
     *
     * @return array{lintas: bool, ids: list<int>}
     */
    public function unitTerlihat(int $jabatan, int $akunId, int $unitSesi): array
    {
        $config = config("Presensi");

        if (in_array($jabatan, $config->riwayatSemuaUnit, true)) {
            return ['lintas' => true, 'ids' => []];
        }

        if ($jabatan === $config->jabatanSpv) {
            $ids = $this->spvUnitIds($akunId);
            if ($ids !== []) {
                return ['lintas' => false, 'ids' => $ids];
            }
        }

        return ['lintas' => false, 'ids' => [$unitSesi]];
    }

    /** @return list<int> */
    private function spvUnitIds(int $spvId): array
    {
        $db = db_connect();
        if (! $db->tableExists('spv_units')) {
            return [];
        }

        $rows = $db->table('spv_units')->select('unit_id')->where('spv_id', $spvId)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'unit_id'));
    }
}
