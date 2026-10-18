<?php

namespace App\Controllers;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\SupplierReturnCreditService;
use Config\Database;

class KreditReturSuplier extends BaseController
{
    public function index()
    {
        $scope = new FinanceScopeService();
        $unitIds = array_map(
            static fn ($unit) => (int) $unit->idunit,
            $scope->resolveAllowedUnits()
        );
        $db = Database::connect();
        $credits = [];
        if ($unitIds !== []) {
            $credits = $db->table('kredit_retur_suplier kredit')
                ->select('kredit.*, suplier.nama_suplier')
                ->join('suplier', 'suplier.id_suplier = kredit.suplier_id')
                ->whereIn('kredit.unit_id', $unitIds)
                ->orderBy('kredit.id', 'DESC')
                ->get()->getResult();
        }

        return view('template', [
            'body' => 'retur/kredit_suplier',
            'credits' => $credits,
            'accounts' => $db->table('no_akun')->orderBy('no_akun')->get()->getResult(),
            'canApprove' => $scope->canInput(),
        ]);
    }

    public function approve()
    {
        $scope = new FinanceScopeService();
        if (! $scope->canInput()) {
            return redirect()->back()->with('gagal', 'Tidak berhak menyetujui kredit supplier.');
        }

        $creditId = (int) $this->request->getPost('credit_id');
        $db = Database::connect();
        $credit = $db->table('kredit_retur_suplier')->where('id', $creditId)->get()->getRow();
        $unitIds = array_map(
            static fn ($unit) => (int) $unit->idunit,
            $scope->resolveAllowedUnits()
        );
        if (! $credit || ! in_array((int) $credit->unit_id, $unitIds, true)) {
            return redirect()->back()->with('gagal', 'Kredit retur tidak tersedia untuk unit ini.');
        }

        try {
            (new SupplierReturnCreditService())->approve(
                $creditId,
                trim((string) $this->request->getPost('nominal')),
                trim((string) $this->request->getPost('nomor_nota_kredit')),
                trim((string) $this->request->getPost('akun_debet')),
                trim((string) $this->request->getPost('akun_kredit')),
                (int) session('ID_AKUN')
            );
            return redirect()->to(base_url('kredit_retur_suplier'))->with('sukses', 'Kredit supplier disetujui dan jurnal dicatat.');
        } catch (\Throwable $error) {
            log_message('error', 'Persetujuan kredit retur gagal: ' . $error->getMessage());
            return redirect()->back()->with('gagal', 'Persetujuan gagal. Periksa nominal, sisa utang supplier, dan akun jurnal.');
        }
    }
}
