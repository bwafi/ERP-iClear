<?php

namespace App\Services\Auth;

use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;

/**
 * Menjaga session('ID_UNIT') tetap sama dengan akun.ID_UNIT tanpa login ulang.
 *
 * Sinkronisasi hanya dilakukan pada GET non-AJAX. POST/PUT/DELETE harus tetap
 * memakai unit yang dipakai saat form dirender, dan AJAX GET di tengah
 * pengisian form tidak boleh memindahkan unit.
 */
final class SessionUnitSyncService
{
    /** @var \CodeIgniter\Database\BaseConnection|null */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    /**
     * @return array{old:int,new:int,old_name:string,new_name:string,ticket_reset:bool}|null
     */
    public function sync(string $method, bool $isAjax): ?array
    {
        if (strtoupper($method) !== 'GET' || $isAjax) {
            return null;
        }

        $idAkun = (int) session('ID_AKUN');
        if ($idAkun <= 0) {
            return null;
        }

        try {
            $db   = $this->db ?? Database::connect();
            $akun = $db->table('akun')
                ->select('ID_UNIT')
                ->where('ID_AKUN', $idAkun)
                ->get()
                ->getRow();
        } catch (DatabaseException $e) {
            log_message('warning', 'Sinkronisasi unit sesi dilewati: {pesan}', ['pesan' => $e->getMessage()]);

            return null;
        }

        if ($akun === null || $akun->ID_UNIT === null) {
            return null;
        }

        $unitLama = (int) session('ID_UNIT');
        $unitBaru = $this->unitDariAkun($akun->ID_UNIT);

        if ($unitBaru === $unitLama) {
            return null;
        }

        $namaLama  = (string) (session('NAMA_UNIT') ?: ('Unit ' . $unitLama));
        $namaBaru  = $this->namaUnit($db, $unitBaru);
        $tiketAktif = session()->has('idservice');

        session()->set([
            'ID_UNIT'   => $unitBaru,
            'NAMA_UNIT' => $namaBaru,
        ]);
        session()->remove('idservice');

        log_message('info', 'Sinkronisasi unit sesi: akun {akun} unit {lama} -> {baru}', [
            'akun'  => $idAkun,
            'lama'  => $unitLama,
            'baru'  => $unitBaru,
        ]);

        return [
            'old'          => $unitLama,
            'new'          => $unitBaru,
            'old_name'     => $namaLama,
            'new_name'     => $namaBaru,
            'ticket_reset' => $tiketAktif,
        ];
    }

    private function unitDariAkun($nilai): int
    {
        if (is_numeric($nilai)) {
            return (int) $nilai;
        }

        $decoded = json_decode((string) $nilai, true);
        if (is_array($decoded)) {
            $decoded = $decoded[0] ?? 0;
        }

        return (int) $decoded;
    }

    private function namaUnit($db, int $unitId): string
    {
        try {
            $unit = $db->table('unit')
                ->select('NAMA_UNIT')
                ->where('idunit', $unitId)
                ->get()
                ->getRow();
        } catch (DatabaseException $e) {
            $unit = null;
        }

        $nama = $unit->NAMA_UNIT ?? '';

        return $nama !== '' ? (string) $nama : 'Unit ' . $unitId;
    }
}
