<?php

namespace App\Controllers;

use App\Services\Service\ServiceWorkflow;
use App\Services\Service\ServiceWorkflowAccess;
use Config\Database;
use RuntimeException;

class ServiceWorkflowController extends BaseController
{
    public function technicianQueue()
    {
        if (! (new ServiceWorkflowAccess())->canTechnician()) {
            return $this->forbidden();
        }
        return $this->renderQueue('technician');
    }

    public function newTechnicianService()
    {
        if (! (new ServiceWorkflowAccess())->canTechnician()) {
            return $this->forbidden();
        }
        session()->remove('idservice');
        return redirect()->to(base_url('service?workflow=technician'));
    }

    public function cashierQueue()
    {
        if (! (new ServiceWorkflowAccess())->canCashier()) {
            return $this->forbidden();
        }
        return $this->renderQueue('cashier');
    }

    public function technicianEditor(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canTechnician() || ! $this->serviceInBranch($serviceId)) {
            return $this->forbidden();
        }
        $workflow = Database::connect()->table('service_workflow')->where('service_idservice', $serviceId)->get()->getRow();
        if (! $workflow || ! in_array($workflow->queue_status, [ServiceWorkflow::DRAFT, ServiceWorkflow::RETURNED], true)
            || (int) $workflow->technician_idakun !== (int) session('ID_AKUN')) {
            return $this->forbidden();
        }
        return $this->renderEditor($serviceId, 'technician', $workflow);
    }

    public function cashierEditor(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canCashier() || ! $this->serviceInBranch($serviceId)) {
            return $this->forbidden();
        }
        $workflow = Database::connect()->table('service_workflow')->where('service_idservice', $serviceId)->get()->getRow();
        if (! $workflow || $workflow->queue_status !== ServiceWorkflow::PROCESSING
            || (int) $workflow->cashier_idakun !== (int) session('ID_AKUN')) {
            return redirect()->to(base_url('antrean-kasir'))->with('gagal', 'Ambil antrean ini terlebih dahulu.');
        }
        return $this->renderEditor($serviceId, 'cashier', $workflow);
    }

    public function badge()
    {
        $access = new ServiceWorkflowAccess();
        if (! $access->canTechnician() && ! $access->canCashier()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses ditolak.']);
        }
        $db = Database::connect();
        $query = $db->table('service_workflow')->join('service', 'service.idservice = service_workflow.service_idservice')
            ->where('service.unit_idunit', (int) session('ID_UNIT'));
        if ($access->canTechnician()) {
            $countTech = (clone $query)->whereIn('queue_status', [ServiceWorkflow::DRAFT, ServiceWorkflow::RETURNED])
                ->where('technician_idakun', (int) session('ID_AKUN'))->countAllResults();
        } else {
            $countTech = 0;
        }
        if ($access->canCashier()) {
            $countCashier = (clone $query)->where('queue_status', ServiceWorkflow::QUEUED)->countAllResults();
        } else {
            $countCashier = 0;
        }
        return $this->response->setJSON(['technician' => $countTech, 'cashier' => $countCashier]);
    }

    public function queueData()
    {
        $access = new ServiceWorkflowAccess();
        $mode = (string) $this->request->getGet('mode');
        if ($mode === 'cashier' && ! $access->canCashier()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses kasir ditolak.']);
        }
        if ($mode !== 'cashier' && ! $access->canTechnician()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses teknisi ditolak.']);
        }
        $db = Database::connect();
        $builder = $db->table('service_workflow w')
            ->select('s.idservice, s.no_service, s.tipe_hp, s.keluhan, s.unit_idunit, s.created_at, p.nama AS nama_pelanggan, p.no_hp, tech.NAMA_AKUN AS nama_teknisi, cashier.NAMA_AKUN AS nama_kasir, w.queue_status, w.recommendation, w.submitted_at, w.return_reason, w.cashier_idakun')
            ->join('service s', 's.idservice = w.service_idservice')
            ->join('pelanggan p', 'p.id_pelanggan = s.pelanggan_id_pelanggan', 'left')
            ->join('akun tech', 'tech.ID_AKUN = w.technician_idakun', 'left')
            ->join('akun cashier', 'cashier.ID_AKUN = w.cashier_idakun', 'left')
            ->where('s.unit_idunit', (int) session('ID_UNIT'));
        if ($mode === 'cashier') {
            $builder->whereIn('w.queue_status', [ServiceWorkflow::QUEUED, ServiceWorkflow::PROCESSING]);
        } else {
            $builder->whereIn('w.queue_status', [ServiceWorkflow::DRAFT, ServiceWorkflow::RETURNED])
                ->where('w.technician_idakun', (int) session('ID_AKUN'));
        }
        $rows = $builder->orderBy('COALESCE(w.submitted_at, w.created_at)', 'DESC', false)->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['is_owned'] = $mode === 'cashier' && (int) $row['cashier_idakun'] === (int) session('ID_AKUN');
        }
        unset($row);
        return $this->response->setJSON(['data' => $rows]);
    }

    public function sendToCashier(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canTechnician()) {
            return $this->forbidden();
        }
        if (! $this->serviceInBranch($serviceId)) {
            return $this->notFound();
        }
        $workflow = Database::connect()->table('service_workflow')->where('service_idservice', $serviceId)->get()->getRow();
        if (! $workflow || (int) $workflow->technician_idakun !== (int) session('ID_AKUN')
            || ! in_array($workflow->queue_status, [ServiceWorkflow::DRAFT, ServiceWorkflow::RETURNED], true)) {
            return $this->forbidden();
        }
        try {
            (new ServiceWorkflow(Database::connect()))->sendToCashier(
                $serviceId,
                (int) session('ID_AKUN'),
                (string) $this->request->getPost('recommendation')
            );
            return redirect()->to(base_url('pekerjaan-teknisi'))->with('sukses', 'Service sudah masuk Antrean Kasir.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('gagal', $e->getMessage());
        }
    }

    public function claimCashier(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canCashier()) {
            return $this->forbidden();
        }
        if (! $this->serviceInBranch($serviceId)) {
            return $this->notFound();
        }
        try {
            (new ServiceWorkflow(Database::connect()))->claimByCashier($serviceId, (int) session('ID_AKUN'));
            return redirect()->to(base_url('antrean-kasir/' . $serviceId . '?tab=sparepart'));
        } catch (\Throwable $e) {
            return redirect()->to(base_url('antrean-kasir'))->with('gagal', $e->getMessage());
        }
    }

    public function openCashier(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canCashier() || ! $this->serviceInBranch($serviceId)) {
            return $this->forbidden();
        }
        try {
            (new ServiceWorkflow(Database::connect()))->claimByCashier($serviceId, (int) session('ID_AKUN'));
            return redirect()->to(base_url('antrean-kasir/' . $serviceId . '?tab=sparepart'));
        } catch (\Throwable $e) {
            return redirect()->to(base_url('antrean-kasir'))->with('gagal', $e->getMessage());
        }
    }

    public function returnToTechnician(int $serviceId)
    {
        if (! (new ServiceWorkflowAccess())->canCashier()) {
            return $this->forbidden();
        }
        if (! $this->serviceInBranch($serviceId)) {
            return $this->notFound();
        }
        try {
            (new ServiceWorkflow(Database::connect()))->returnToTechnician(
                $serviceId,
                (int) session('ID_AKUN'),
                (string) $this->request->getPost('reason')
            );
            return redirect()->to(base_url('antrean-kasir'))->with('sukses', 'Service dikembalikan kepada teknisi untuk koreksi.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('gagal', $e->getMessage());
        }
    }

    private function renderQueue(string $mode)
    {
        $db = Database::connect();
        $builder = $db->table('service_workflow w')
            ->select('w.*, s.idservice, s.no_service, s.tipe_hp, s.keluhan, s.keterangan, s.unit_idunit, s.created_at, p.nama AS nama_pelanggan, p.no_hp, tech.NAMA_AKUN AS nama_teknisi, cashier.NAMA_AKUN AS nama_kasir')
            ->join('service s', 's.idservice = w.service_idservice')
            ->join('pelanggan p', 'p.id_pelanggan = s.pelanggan_id_pelanggan', 'left')
            ->join('akun tech', 'tech.ID_AKUN = w.technician_idakun', 'left')
            ->join('akun cashier', 'cashier.ID_AKUN = w.cashier_idakun', 'left')
            ->where('s.unit_idunit', (int) session('ID_UNIT'));
        if ($mode === 'technician') {
            $builder->whereIn('w.queue_status', [ServiceWorkflow::DRAFT, ServiceWorkflow::RETURNED])
                ->where('w.technician_idakun', (int) session('ID_AKUN'));
        } else {
            $builder->whereIn('w.queue_status', [ServiceWorkflow::QUEUED, ServiceWorkflow::PROCESSING]);
        }
        $items = $builder->orderBy('COALESCE(w.submitted_at, w.created_at)', 'DESC', false)->get()->getResult();
        return view('template', [
            'body' => 'service/workflow_queue',
            'mode' => $mode,
            'items' => $items,
            'current_actor_id' => (int) session('ID_AKUN'),
        ]);
    }

    private function renderEditor(int $serviceId, string $mode, object $workflow)
    {
        $db = Database::connect();
        $akunModel = new \App\Models\ModelAuth();
        $serviceModel = new \App\Models\ModelService();
        $kerusakanModel = new \App\Models\ModelKerusakan();
        $sparepartModel = new \App\Models\ModelServiceSparepart();
        $bankModel = new \App\Models\ModelBank();
        $unitModel = new \App\Models\ModelUnit();
        $regionModel = new \App\Models\ModelRegion();
        $pelangganModel = new \App\Models\ModelPelanggan();
        $stokModel = new \App\Models\ModelStokBarang();
        $oldService = $serviceModel->getByIdWithPelanggan($serviceId);
        if (! $oldService || (int) ($oldService->unit_idunit ?? 0) !== (int) session('ID_UNIT')) {
            return $this->notFound();
        }
        $technician = $db->table('akun')->select('NAMA_AKUN')->where('ID_AKUN', (int) $workflow->technician_idakun)->get()->getRow();
        $workflow->technician_name = $technician->NAMA_AKUN ?? '—';
        $diagnosisItems = $db->table('service_kerusakan sk')
            ->select('f.nama_fungsi, sk.keterangan')
            ->join('fungsi f', 'f.idfungsi = sk.fungsi_idfungsi', 'left')
            ->where('sk.service_idservice', $serviceId)
            ->get()->getResult();
        return view('template', [
            'akun' => $akunModel->getById(session('ID_AKUN')),
            'teknisi' => $akunModel->getdataakun(),
            'bank' => $bankModel->getBank(),
            'dp_bank' => $bankModel->getBankDalamScopeUnit((int) session('ID_UNIT')),
            'fungsi' => $kerusakanModel->getKerusakan(),
            'idservice' => $serviceId,
            'old_service_pelanggan' => $oldService,
            'oldkerusakan' => (new \App\Models\ModelServiceKerusakan())->getSerModelServiceKerusakanByServiceId($serviceId),
            'oldsparepart' => $sparepartModel->getSerModelServiceSparepartByServiceId($serviceId),
            'unit' => $unitModel->getUnit(),
            'pelanggan' => $pelangganModel->getPelanggan(),
            'sparepart' => $stokModel->getSparepart(),
            'provinsi' => $regionModel->getProvinces(),
            'workflow_mode' => $mode,
            'workflow' => $workflow,
            'workflow_diagnosis' => $diagnosisItems,
            'body' => 'transaksi/service',
        ]);
    }

    private function serviceInBranch(int $serviceId): bool
    {
        return Database::connect()->table('service')->where('idservice', $serviceId)
            ->where('unit_idunit', (int) session('ID_UNIT'))->countAllResults() === 1;
    }

    private function forbidden()
    {
        return $this->response->setStatusCode(403)->setBody('Akses ditolak berdasarkan hak akses menu service.');
    }
}
