<?php

namespace App\Controllers;

use App\Models\ModelPresensiRoster;
use App\Models\ModelUnit;
use CodeIgniter\Controller;

class JadwalMobile extends Controller
{
    protected $helpers = ['url'];

    public function index()
    {
        $akunId = (int) session('ID_AKUN');
        $unitId = (int) session('ID_UNIT');

        $bulan = (string) ($this->request->getGet('bulan') ?: date('Y-m'));
        $bulanAngka = (int) substr($bulan, 5, 2);
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan) || $bulanAngka < 1 || $bulanAngka > 12) {
            $bulan = date('Y-m');
        }

        $roster = (new ModelPresensiRoster())->getRentang(
            $akunId,
            $bulan . '-01',
            date('Y-m-t', strtotime($bulan . '-01'))
        );

        $unitMap = [];
        foreach ((new ModelUnit())->getUnit() as $u) {
            $unitMap[(int) $u->idunit] = (string) $u->NAMA_UNIT;
        }

        return view('jadwal/mobile', [
            'nama'    => (string) session('NAMA'),
            'unit'    => $unitMap[$unitId] ?? (string) (session('NAMA_UNIT') ?: 'Unit'),
            'unitId'  => $unitId,
            'bulan'   => $bulan,
            'roster'  => $roster,
            'unitMap' => $unitMap,
        ]);
    }
}
