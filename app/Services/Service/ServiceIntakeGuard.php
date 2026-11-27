<?php

namespace App\Services\Service;

/**
 * Aturan tiket service yang boleh disunting dari halaman intake `/service`.
 *
 * Halaman intake memakai `session('idservice')` untuk menentukan mode lanjut.
 * Tanpa guard ini, sesi yang menunjuk tiket selesai membuat form intake menjadi
 * form edit tiket lama (dan mencetak nota lama) tanpa membuat tiket baru.
 */
final class ServiceIntakeGuard
{
    /** Status yang masih boleh disunting dari halaman intake. */
    public const EDITABLE_STATUSES = [1, 2];

    /** Jabatan yang boleh menyentuh tiket lintas unit (HO). */
    public const HO_ROLES = [0, 1, 34];

    /** @var ServiceUnitScopeService|null */
    private $scope;

    /** @var array{role?: int, unit?: int}|null */
    private $context;

    /**
     * @param array{role?: int, unit?: int}|null $context
     */
    public function __construct(?array $context = null, ?ServiceUnitScopeService $scope = null)
    {
        $this->context = $context;
        $this->scope = $scope;
    }

    /**
     * @param mixed $status
     */
    public function isEditableStatus($status): bool
    {
        return in_array((int) $status, self::EDITABLE_STATUSES, true);
    }

    /**
     * @param object|array|null $service
     * @param int[]             $allowedUnitIds array kosong berarti semua unit
     *
     * @return array{ok: bool, reason: string}
     */
    public function checkTicket($service, array $allowedUnitIds): array
    {
        if (empty($service)) {
            return ['ok' => false, 'reason' => 'missing'];
        }

        $status = is_array($service)
            ? ($service['status_service'] ?? null)
            : ($service->status_service ?? null);

        if (! $this->isEditableStatus($status)) {
            return ['ok' => false, 'reason' => 'closed'];
        }

        $unit = (int) (is_array($service)
            ? ($service['unit_idunit'] ?? 0)
            : ($service->unit_idunit ?? 0));

        if ($allowedUnitIds !== [] && ! in_array($unit, $allowedUnitIds, true)) {
            return ['ok' => false, 'reason' => 'unit'];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Unit yang boleh disentuh user untuk intake.
     *
     * @return int[] array kosong berarti semua unit (HO); [-1] berarti tidak ada unit sah
     */
    public function allowedUnitIds(): array
    {
        $role = (int) ($this->context['role'] ?? session('ID_JABATAN'));

        if (in_array($role, self::HO_ROLES, true)) {
            return [];
        }

        if ($role === ServiceUnitScopeService::SPV_ROLE) {
            $ids = $this->scope()->spvUnitIds();

            return $ids !== [] ? $ids : [-1];
        }

        $unit = (int) ($this->context['unit'] ?? session('ID_UNIT'));

        return $unit > 0 ? [$unit] : [-1];
    }

    private function scope(): ServiceUnitScopeService
    {
        if ($this->scope === null) {
            $this->scope = new ServiceUnitScopeService();
        }

        return $this->scope;
    }
}
