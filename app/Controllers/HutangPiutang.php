<?php

namespace App\Controllers;

use App\Services\Finance\HutangPiutangService;
use App\Services\Finance\FinanceScopeService;
use App\Models\ModelUnit;

/**
 * Modul Hutang Piutang — UI terpusat.
 *
 * - Piutang  : authoritative (piutang pelanggan, kasbon) — input & bayar.
 * - Hutang   : projection dari pembelian (supplier) — bayar via modul existing.
 * - Riwayat  : gabungan pembayaran authoritative + existing (tanpa double count).
 */
class HutangPiutang extends BaseController
{
    protected $service;
    protected $scopeService;
    protected $modelUnit;

    public function __construct()
    {
        $this->service = new HutangPiutangService();
        $this->scopeService = new FinanceScopeService();
        $this->modelUnit = new ModelUnit();
    }

    private function allowedUnitIds(): array
    {
        $units = $this->scopeService->resolveAllowedUnits();
        return array_map('intval', array_column(array_map('get_object_vars', $units), 'idunit'));
    }

    private function resolveUnitId(): ?int
    {
        $req = $this->request->getGet('unit_id');

        // Tanpa pilihan unit: multi-unit = semua unit (null), single-unit = unit itu.
        if ($req === null || $req === '') {
            $allowed = $this->allowedUnitIds();
            return count($allowed) === 1 ? $allowed[0] : null;
        }

        return $this->scopeService->resolveSelectedUnitId((string) $req);
    }

    public function dashboard()
    {
        $allowed = $this->allowedUnitIds();
        $unitId = $this->resolveUnitId();
        $bulan = $this->request->getGet('bulan') ?: date('Y-m');

        // Ringkasan lintas unit yang diizinkan (atau unit terpilih).
        $scopeUnits = $unitId ? [$unitId] : $allowed;
        $ringkasan = $this->service->getRingkasan($scopeUnits);

        return view('template', [
            'title' => 'Dashboard Hutang Piutang',
            'body' => 'hutangpiutang/dashboard',
            'ringkasan' => $ringkasan,
            'recent' => $this->service->listPositions([
                'unit_id' => $unitId,
                'bulan' => $bulan,
            ]),
            'units' => $this->scopeService->resolveAllowedUnits(),
            'unit_id' => $unitId,
            'bulan' => $bulan,
            'can_input' => $this->scopeService->canInput(),
        ]);
    }

    public function piutang()
    {
        return $this->renderList('piutang');
    }

    public function hutang()
    {
        return $this->renderList('hutang');
    }

    private function renderList(string $jenis)
    {
        $filters = [
            'jenis' => $jenis,
            'status' => $this->request->getGet('status'),
            'sumber_tipe' => $this->request->getGet('sumber_tipe'),
            'unit_id' => $this->resolveUnitId(),
            'bulan' => $this->request->getGet('bulan'),
            'q' => $this->request->getGet('q'),
            'scope' => $this->request->getGet('scope'),
        ];

        return view('template', [
            'title' => $jenis === 'piutang' ? 'Daftar Piutang' : 'Daftar Hutang',
            'body' => 'hutangpiutang/index',
            'jenis' => $jenis,
            'rows' => $this->service->listPositions($filters),
            'filters' => $filters,
            'units' => $this->scopeService->resolveAllowedUnits(),
            'unit_id' => $filters['unit_id'],
            'can_input' => $this->scopeService->canInput(),
            'input_types' => HutangPiutangService::inputTypes(),
            'pelanggan' => $this->service->getPelangganOptions(),
            'pegawai' => $this->service->getPegawaiOptions(),
            'suplier' => $this->service->getSuplierOptions(),
            'teknisi' => $this->service->getTeknisiOptions(),
            'bank' => $this->service->getBankOptions(),
        ]);
    }

    public function form()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak menambah transaksi.');
        }

        return view('template', [
            'title' => 'Input Hutang Piutang',
            'body' => 'hutangpiutang/form',
            'input_types' => HutangPiutangService::inputTypes(),
            'pelanggan' => $this->service->getPelangganOptions(),
            'pegawai' => $this->service->getPegawaiOptions(),
            'suplier' => $this->service->getSuplierOptions(),
            'teknisi' => $this->service->getTeknisiOptions(),
            'units' => $this->scopeService->resolveAllowedUnits(),
            'unit_id' => $this->resolveUnitId(),
        ]);
    }

    public function store()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak menambah transaksi.');
        }

        $sumberTipe = (string) $this->request->getPost('sumber_tipe');
        $types = HutangPiutangService::inputTypes();
        if (!isset($types[$sumberTipe])) {
            return redirect()->back()->with('gagal', 'Jenis transaksi tidak valid.');
        }

        $result = $this->service->createPosition([
            'jenis' => $types[$sumberTipe]['jenis'] ?: (string) $this->request->getPost('jenis'),
            'sumber_tipe' => $sumberTipe,
            'pihak_tipe' => (string) $this->request->getPost('pihak_tipe'),
            'pihak_id' => (int) $this->request->getPost('pihak_id'),
            'nama_pihak' => $this->request->getPost('nama_pihak'),
            'tanggal' => $this->request->getPost('tanggal') ?: date('Y-m-d'),
            'jatuh_tempo' => $this->request->getPost('jatuh_tempo'),
            'total' => $this->request->getPost('total'),
            'uraian' => $this->request->getPost('uraian'),
            'keterangan' => $this->request->getPost('keterangan'),
            'unit_id' => (int) ($this->request->getPost('unit_id') ?: session('ID_UNIT')),
        ], (int) session('ID_AKUN'));

        if (!$result['success']) {
            return redirect()->back()->withInput()->with('gagal', $result['message']);
        }

        return redirect()->to(base_url('hutangpiutang/detail/' . $result['id']))
            ->with('sukses', 'Transaksi ' . $result['kode'] . ' berhasil disimpan.');
    }

    public function detail($id = null)
    {
        $row = $this->service->getById((int) $id);
        if (!$row) {
            return redirect()->to(base_url('hutangpiutang/piutang'))->with('gagal', 'Transaksi tidak ditemukan.');
        }

        $unit = $row->unit_id ? $this->modelUnit->find($row->unit_id) : null;

        return view('template', [
            'title' => 'Detail ' . $row->kode,
            'body' => 'hutangpiutang/detail',
            'row' => $row,
            'unit' => $unit,
            'pembayaran' => $this->service->getPembayaran((int) $row->id),
            'kompensasi' => $this->service->getKompensasi((int) $row->id),
            'lawan_kompensasi' => $this->service->getLawanKompensasi($row),
            'bank' => $this->service->getBankOptions(),
            'can_input' => $this->scopeService->canInput(),
        ]);
    }

    public function kompensasi()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak melakukan kompensasi.');
        }

        $hutangId = (int) $this->request->getPost('hutang_piutang_id');
        $piutangId = (int) $this->request->getPost('lawan_id');
        $jumlah = (int) preg_replace('/[^\d]/', '', (string) $this->request->getPost('jumlah'));

        $result = $this->service->kompensasi(
            $hutangId,
            $piutangId,
            $jumlah,
            $this->request->getPost('keterangan'),
            (int) session('ID_AKUN')
        );

        if (!$result['success']) {
            return redirect()->back()->with('gagal', $result['message']);
        }

        return redirect()->to(base_url('hutangpiutang/detail/' . $hutangId))->with('sukses', $result['message']);
    }

    public function bayar()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mencatat pembayaran.');
        }

        $id = (int) $this->request->getPost('hutang_piutang_id');
        $tunai = (int) preg_replace('/[^\d]/', '', (string) $this->request->getPost('bayar_tunai'));
        $bank = (int) preg_replace('/[^\d]/', '', (string) $this->request->getPost('bayar_bank'));
        $jumlah = (int) preg_replace('/[^\d]/', '', (string) $this->request->getPost('jumlah_bayar'));
        if ($jumlah <= 0) {
            $jumlah = $tunai + $bank;
        }

        $result = $this->service->bayar($id, [
            'tanggal_bayar' => $this->request->getPost('tanggal_bayar') ?: date('Y-m-d'),
            'jumlah_bayar' => $jumlah,
            'bayar_tunai' => $tunai,
            'bayar_bank' => $bank,
            'bank_idbank' => $this->request->getPost('bank_idbank'),
            'keterangan' => $this->request->getPost('keterangan'),
        ], (int) session('ID_AKUN'));

        if (!$result['success']) {
            return redirect()->back()->with('gagal', $result['message']);
        }

        return redirect()->to(base_url('hutangpiutang/detail/' . $id))->with('sukses', $result['message']);
    }

    public function riwayat()
    {
        $filters = [
            'unit_id' => $this->resolveUnitId(),
            'bulan' => $this->request->getGet('bulan'),
            'jenis' => $this->request->getGet('jenis'),
        ];

        return view('template', [
            'title' => 'Riwayat Pembayaran Hutang Piutang',
            'body' => 'hutangpiutang/riwayat',
            'rows' => $this->service->getRiwayatPembayaran($filters),
            'filters' => $filters,
            'units' => $this->scopeService->resolveAllowedUnits(),
            'unit_id' => $filters['unit_id'],
        ]);
    }

    public function cetak($id = null)
    {
        $row = $this->service->getById((int) $id);
        if (!$row) {
            return redirect()->to(base_url('hutangpiutang/piutang'))->with('gagal', 'Transaksi tidak ditemukan.');
        }

        $unit = $row->unit_id ? $this->modelUnit->find($row->unit_id) : null;

        $html = view('cetak/bukti_hutang_piutang', [
            'row' => $row,
            'unit' => $unit,
            'detail' => $this->service->getDetailTransaksi($row),
            'pembayaran' => $this->service->getPembayaran((int) $row->id),
        ]);

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'curlUserAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:108.0) Gecko/20100101 Firefox/108.0',
        ]);
        $mpdf->curlAllowUnsafeSslRequests = true;

        // mPDF memunculkan notice internal (mis. "Undefined offset: -1") yang di
        // lingkungan development diubah CI4 menjadi ErrorException. Samakan dengan
        // cetak/invoice_service: matikan reporting tepat sebelum render.
        error_reporting(0);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $mpdf->WriteHTML($html);
        $mpdf->Output('bukti-' . $row->kode . '.pdf', 'I');
        exit;
    }
}
