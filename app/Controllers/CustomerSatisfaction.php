<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Services\Kpi\CustomerSatisfactionService;

/**
 * Customer Satisfaction KPI — input harian review Google Maps.
 *
 * - Admin root / Direktur (1, 2): input semua unit.
 * - Manager (34): melihat semua unit, read-only.
 * - Kepala Toko (41): input review unit sendiri.
 */
class CustomerSatisfaction extends BaseController
{
    protected $authModel;
    protected $service;

    public function __construct()
    {
        $this->authModel = new ModelAuth();
        $this->service   = new CustomerSatisfactionService();
    }

    public function index()
    {
        $me       = $this->authModel->getById((int)session()->get('ID_AKUN'));
        $myRole   = (int)($me->ID_JABATAN ?? 0);
        $myUnit   = (int)($me->ID_UNIT ?? 0);
        $myId     = (int)($me->ID_AKUN ?? 0);

        // Hanya Admin root (1), Direktur (2), Manager (34), dan Kepala Toko (41).
        $viewRoles = [1, 2, 34, 41];
        if (!in_array($myRole, $viewRoles, true)) {
            return redirect()->to('/')->with('error', 'Anda tidak berhak mengakses fitur Customer Satisfaction.');
        }

        // Filter: hanya Bulan & Tahun (tanggal dihapus dari filter; tanggal input di bawah).
        $bulan    = (int)($this->request->getGet('bulan') ?: date('m'));
        $tahun    = (int)($this->request->getGet('tahun') ?: date('Y'));
        $startMonth = sprintf('%04d-%02d-01', $tahun, $bulan);
        $endMonth   = date('Y-m-t', strtotime($startMonth));

        // Tanggal input default = hari ini (atau hari pertama bulan jika hari ini di luar bulan).
        $defaultDate = sprintf('%04d-%02d-%02d', $tahun, $bulan, min((int)date('d', time()), (int)date('t', strtotime($startMonth))));
        $tanggalInput = $this->request->getGet('tanggal') ?? $defaultDate;
        if ($tanggalInput < $startMonth || $tanggalInput > $endMonth) {
            $tanggalInput = $defaultDate;
        }

        // Scope unit & hak input.
        [$units, $canInput, $selectedUnit] = $this->scopeFor($myRole, $myUnit, $myId);

        $requestedUnit = (int)($this->request->getGet('id_unit') ?: 0);
        if ($requestedUnit && in_array($requestedUnit, array_map('intval', $units), true)) {
            $selectedUnit = $requestedUnit;
        } elseif (!in_array($selectedUnit, array_map('intval', $units), true)) {
            $selectedUnit = $units ? (int)$units[0] : 0;
        }

        $unitList = \Config\Database::connect()->table('unit')->orderBy('idunit', 'ASC')->get()->getResultArray();
        $unitNames = [];
        foreach ($unitList as $u) {
            $unitNames[(int)$u['idunit']] = $u['NAMA_UNIT'];
        }

        $totalCustomer = $selectedUnit ? $this->service->totalCustomers($tanggalInput, $selectedUnit) : 0;
        $existing      = $selectedUnit ? $this->service->model()->findByTanggalUnit($tanggalInput, $selectedUnit) : null;
        $review        = $existing ? (int)$existing->jumlah_review : 0;
        $persentase    = $this->service->dailyPercentage($review, $totalCustomer);

        $aggregation = $this->service->monthlyAggregation(array_map('intval', $units), (string)$bulan, (string)$tahun);

        // Nilai kartu = BULANAN per unit terpilih (bukan harian).
        $monthly = $aggregation['units'][$selectedUnit] ?? ['review' => 0, 'customer' => 0, 'persen' => 0];

        // Daftar input harian per unit & bulan (semua data, urut terbaru).
        $records = $this->service->model()
            ->where('id_unit', $selectedUnit)
            ->where('tanggal >=', $startMonth)
            ->where('tanggal <=', $endMonth)
            ->orderBy('tanggal', 'DESC')
            ->findAll();

        $recordData = [];
        foreach ($records as $rec) {
            $rc = $selectedUnit ? $this->service->totalCustomers($rec->tanggal, $selectedUnit) : 0;
            $recordData[] = [
                'tanggal'  => $rec->tanggal,
                'customer' => $rc,
                'review'   => (int)$rec->jumlah_review,
                'persen'   => $this->service->dailyPercentage((int)$rec->jumlah_review, $rc),
            ];
        }

        $unitNamesScope = [];
        foreach (array_map('intval', $units) as $uid) {
            $unitNamesScope[$uid] = $unitNames[$uid] ?? ('Unit ' . $uid);
        }

        return view('template', [
            'myRole'        => $myRole,
            'myUnit'        => $myUnit,
            'canInput'      => $canInput,
            'units'         => array_map('intval', $units),
            'unitNames'     => $unitNamesScope,
            'selectedUnit'  => $selectedUnit,
            'tanggalInput'  => $tanggalInput,
            'bulan'         => str_pad((string)$bulan, 2, '0', STR_PAD_LEFT),
            'tahun'         => (string)$tahun,
            'totalCustomer' => $totalCustomer,
            'review'        => $review,
            'persentase'    => $persentase,
            'monthly'       => $monthly,
            'aggregation'   => $aggregation,
            'records'       => $recordData,
            'body'          => 'penilaian/customer_satisfaction',
        ]);
    }

    public function save()
    {
        $me      = $this->authModel->getById((int)session()->get('ID_AKUN'));
        $myRole  = (int)($me->ID_JABATAN ?? 0);
        $myUnit  = (int)($me->ID_UNIT ?? 0);
        $myId    = (int)($me->ID_AKUN ?? 0);

        $tanggal      = trim((string)$this->request->getPost('tanggal'));
        $idUnit       = (int)$this->request->getPost('id_unit');
        $jumlahReview = (int)$this->request->getPost('jumlah_review');

        // Otorisasi input: Admin root / Direktur & Kepala Toko; Manager read-only.
        if (!in_array($myRole, [1, 2, 41], true)) {
            return redirect()->to('/penilaian/customer_satisfaction')
                ->with('error', 'Anda tidak berwenang menginput Customer Satisfaction.');
        }
        if ($myRole === 41 && $idUnit !== $myUnit) {
            return redirect()->to('/penilaian/customer_satisfaction')
                ->with('error', 'Kepala Toko hanya boleh menginput unit sendiri.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $tanggal, $m)) {
                $tanggal = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            } else {
                return redirect()->to('/penilaian/customer_satisfaction')
                    ->with('error', 'Format tanggal tidak valid (gunakan dd-mm-yyyy).');
            }
        }
        [$dY, $dM, $dD] = array_map('intval', explode('-', $tanggal));
        if (!checkdate($dM, $dD, $dY)) {
            return redirect()->to('/penilaian/customer_satisfaction')
                ->with('error', 'Tanggal tidak valid.');
        }
        if ($tanggal > date('Y-m-d')) {
            return redirect()->to('/penilaian/customer_satisfaction')
                ->with('error', 'Tidak boleh menginput tanggal di masa depan.');
        }

        $result = $this->service->save($tanggal, $idUnit, $jumlahReview, $myId);

        if (!$result['success']) {
            return redirect()->to('/penilaian/customer_satisfaction?bulan=' . $dM . '&tahun=' . $dY . '&id_unit=' . $idUnit . '&tanggal=' . $tanggal)
                ->with('error', implode(' ', $result['errors']));
        }

        return redirect()->to('/penilaian/customer_satisfaction?bulan=' . $dM . '&tahun=' . $dY . '&id_unit=' . $idUnit . '&tanggal=' . $tanggal)
            ->with('message', 'Data Customer Satisfaction tersimpan.');
    }

    /**
     * @return array [int[] $units, bool $canInput, int $selectedUnit]
     */
    private function scopeFor(int $myRole, int $myUnit, int $myId): array
    {
        if (in_array($myRole, [1, 2], true)) {
            $all = $this->service->allUnits();
            return [$all, true, $all ? (int)$all[0] : 0];
        }
        if ($myRole === 34) {
            // Manager: melihat semua unit, read-only.
            $all = $this->service->allUnits();
            return [$all, false, $all ? (int)$all[0] : 0];
        }
        if ($myRole === 41) {
            return [[$myUnit], true, $myUnit];
        }
        if ($myRole === 40) {
            $units = $this->service->scopeUnits($myId, $myUnit);
            return [$units, false, (int)($units[0] ?? 0)];
        }
        return [[$myUnit], false, $myUnit];
    }
}