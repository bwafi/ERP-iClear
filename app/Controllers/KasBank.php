<?php

namespace App\Controllers;

use App\Models\ModelAkunKasBank;
use App\Models\ModelAuth;
use App\Models\ModelBank;
use App\Models\ModelHutangPiutang;
use App\Models\ModelNoAkun;
use App\Models\ModelPembayaranHutangPiutang;
use App\Models\ModelSaldoAwalKasBank;
use App\Models\ModelTransaksiKasBank;
use App\Libraries\ModeKasBank;
use App\Services\Finance\FinanceScopeService;

/**
 * Kas & Bank + Pembayaran Antar Unit.
 *
 * Authorization reuse FinanceScopeService (Config\Finance::financeInputRoles):
 * - role input (0,1,2,34 = lintas unit) boleh memilih unit & mengisi;
 * - lainnya terikat ke unit sendiri (baca saja).
 */
class KasBank extends BaseController
{
    protected $AkunModel;
    protected $TransaksiModel;
    protected $SaldoAwalModel;
    protected $HPModel;
    protected $PembayaranModel;
    protected $BankModel;
    protected $NoAkunModel;
    protected $AuthModel;
    protected $scopeService;
    protected $KasBankLib;

    public function __construct()
    {
        $this->AkunModel = new ModelAkunKasBank();
        $this->TransaksiModel = new ModelTransaksiKasBank();
        $this->SaldoAwalModel = new ModelSaldoAwalKasBank();
        $this->HPModel = new ModelHutangPiutang();
        $this->PembayaranModel = new ModelPembayaranHutangPiutang();
        $this->BankModel = new ModelBank();
        $this->NoAkunModel = new ModelNoAkun();
        $this->AuthModel = new ModelAuth();
        $this->scopeService = new FinanceScopeService();
        $this->KasBankLib = new ModeKasBank();
    }

    private function canInput(): bool
    {
        return $this->scopeService->canInput();
    }

    private function unitTerpilih(): ?int
    {
        $unit = $this->request->getGet('unit_id') ?? $this->request->getPost('unit_id');
        return $this->scopeService->resolveSelectedUnitId($unit !== null ? (string) $unit : null);
    }

    private function pageData(): array
    {
        return [
            'akun'           => $this->AuthModel->getById(session('ID_AKUN')),
            'unit'           => $this->scopeService->resolveAllowedUnits(),
            'bisa_pilih_unit' => $this->canInput(),
        ];
    }

    private function gagal(string $pesan)
    {
        session()->setFlashdata('gagal', $pesan);
        return redirect()->back();
    }

    /**
     * Dashboard Kas & Bank.
     */
    public function index()
    {
        $unitTerpilih = $this->unitTerpilih();
        $tanggalAwal  = $this->request->getGet('tanggal_awal') ?: date('Y-m-01');
        $tanggalAkhir = $this->request->getGet('tanggal_akhir') ?: date('Y-m-d');
        $akunId       = (int)$this->request->getGet('akun_id');

        $akun = $this->AkunModel->getAllWithUnit($unitTerpilih > 0 ? $unitTerpilih : null);

        $saldoPerAkun = [];
        $totalKas = 0;
        $totalBank = 0;
        $totalSemua = 0;

        foreach ($akun as $a) {
            $saldo = $this->TransaksiModel->getSaldoAkun((int)$a->idakun_kas_bank);
            $saldoPerAkun[$a->idakun_kas_bank] = $saldo;
            $totalSemua += $saldo;
            if ($a->tipe === 'KAS') {
                $totalKas += $saldo;
            } else {
                $totalBank += $saldo;
            }
        }

        $builder = db_connect()->table('transaksi_kas_bank')
            ->select('transaksi_kas_bank.jenis, SUM(transaksi_kas_bank.jumlah) as total')
            ->groupBy('transaksi_kas_bank.jenis');

        if ($unitTerpilih > 0) {
            $builder->where('transaksi_kas_bank.unit_id', $unitTerpilih);
        }
        if ($tanggalAwal) {
            $builder->where('transaksi_kas_bank.tanggal >=', $tanggalAwal);
        }
        if ($tanggalAkhir) {
            $builder->where('transaksi_kas_bank.tanggal <=', $tanggalAkhir);
        }
        if ($akunId > 0) {
            $builder->where('transaksi_kas_bank.akun_kas_bank_id', $akunId);
        }

        $ringkasan = [];
        $netCashFlow = 0;
        foreach ($builder->get()->getResult() as $row) {
            $ringkasan[$row->jenis] = (int)$row->total;
            if ($row->jenis === ModeKasBank::JENIS_PEMASUKAN) {
                $netCashFlow += (int)$row->total;
            } elseif ($row->jenis === ModeKasBank::JENIS_PENGELUARAN) {
                $netCashFlow -= (int)$row->total;
            }
        }

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            'akun_kas_bank' => $akun,
            'saldo_per_akun' => $saldoPerAkun,
            'total_kas'     => $totalKas,
            'total_bank'    => $totalBank,
            'total_semua'   => $totalSemua,
            'ringkasan'     => $ringkasan,
            'net_cash_flow' => $netCashFlow,
            'filter'        => [
                'tanggal_awal'  => $tanggalAwal,
                'tanggal_akhir' => $tanggalAkhir,
                'akun_id'       => $akunId,
            ],
            'body'          => 'kas_bank/dashboard',
        ]);

        return view('template', $data);
    }

    /**
     * Master akun kas/bank + saldo awal.
     */
    public function akun()
    {
        $unitTerpilih = $this->unitTerpilih();

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            'akun_kas_bank' => $this->AkunModel->getAllWithUnit($unitTerpilih ?: null),
            'bank'          => $this->BankModel->getBank(),
            'no_akun'       => $this->NoAkunModel->getAkun(),
            'saldo_awal'    => $this->SaldoAwalModel->findAll(),
            'body'          => 'kas_bank/akun',
        ]);

        return view('template', $data);
    }

    public function saveAkun()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak menambah/mengubah akun kas & bank.');
        }
        $id     = (int)$this->request->getPost('idakun_kas_bank');
        $unitId = (int)$this->request->getPost('unit_id');
        $tipe   = $this->request->getPost('tipe');
        $nama   = trim($this->request->getPost('nama_akun'));

        if ($unitId <= 0) {
            return $this->gagal('Unit wajib diisi');
        }
        if (!in_array($tipe, ['KAS', 'BANK'], true)) {
            return $this->gagal('Tipe akun tidak valid');
        }
        if ($nama === '') {
            return $this->gagal('Nama akun wajib diisi');
        }

        $bankId = $tipe === 'BANK' ? trim((string)$this->request->getPost('bank_idbank')) : null;
        if ($tipe === 'BANK' && empty($bankId)) {
            return $this->gagal('Akun BANK wajib memilih master bank');
        }

        $noAkunCoa = trim((string)$this->request->getPost('no_akun_coa'));
        $noAkunCoa = $noAkunCoa === '' ? null : $noAkunCoa;
        $status    = $this->request->getPost('status') === 'nonaktif' ? 'nonaktif' : 'aktif';

        $duplikat = $this->AkunModel->where('unit_id', $unitId)->where('nama_akun', $nama);
        if ($id > 0) {
            $duplikat->where('idakun_kas_bank !=', $id);
        }
        if ($duplikat->first()) {
            return $this->gagal('Nama akun sudah dipakai di unit ini');
        }

        $data = [
            'unit_id'     => $unitId,
            'tipe'        => $tipe,
            'nama_akun'   => $nama,
            'bank_idbank' => $bankId,
            'no_akun_coa' => $noAkunCoa,
            'status'      => $status,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->AkunModel->update($id, $data);
            session()->setFlashdata('sukses', 'Akun berhasil diperbarui');
        } else {
            $data['created_by'] = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->AkunModel->insert($data);
            session()->setFlashdata('sukses', 'Akun berhasil ditambahkan');
        }

        return redirect()->to(base_url('kas_bank/akun'));
    }

    public function saveSaldoAwal()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak menyimpan saldo awal.');
        }
        $akunId  = (int)$this->request->getPost('akun_kas_bank_id');
        $saldo   = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('saldo'));
        $tanggal = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $ket     = trim((string)$this->request->getPost('keterangan'));

        if ($akunId <= 0) {
            return $this->gagal('Akun wajib dipilih');
        }
        if ($saldo <= 0) {
            return $this->gagal('Saldo awal harus lebih dari 0');
        }

        $akun = $this->AkunModel->find($akunId);
        if (!$akun || $akun->status !== 'aktif') {
            return $this->gagal('Akun tidak ditemukan / tidak aktif');
        }

        $existing = $this->SaldoAwalModel->getByAkun($akunId);
        $data = [
            'akun_kas_bank_id' => $akunId,
            'tanggal'          => $tanggal,
            'saldo'            => $saldo,
            'keterangan'       => $ket,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->SaldoAwalModel->update($existing->id, $data);
            session()->setFlashdata('sukses', 'Saldo awal berhasil diperbarui');
        } else {
            $data['input_by']   = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->SaldoAwalModel->insert($data);
            session()->setFlashdata('sukses', 'Saldo awal berhasil disimpan');
        }

        return redirect()->to(base_url('kas_bank/akun'));
    }

    /**
     * Halaman transfer internal.
     */
    public function transfer()
    {
        $unitTerpilih = $this->unitTerpilih();

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            'akun_kas_bank' => $this->AkunModel->getAktifAll(),
            'transaksi'     => $this->TransaksiModel
                ->where('jenis', ModeKasBank::JENIS_TRANSFER)
                ->orderBy('idtransaksi', 'DESC')
                ->limit(200)
                ->findAll(),
            'body'          => 'kas_bank/transfer',
        ]);

        return view('template', $data);
    }

    public function saveTransfer()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak melakukan transfer internal.');
        }
        $asalId    = (int)$this->request->getPost('akun_asal_id');
        $tujuanId  = (int)$this->request->getPost('akun_tujuan_id');
        $jumlah    = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $tanggal   = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $ket       = trim((string)$this->request->getPost('keterangan'));

        if ($asalId <= 0 || $tujuanId <= 0) {
            return $this->gagal('Pilih akun asal dan tujuan');
        }
        if ($asalId === $tujuanId) {
            return $this->gagal('Akun asal dan tujuan tidak boleh sama');
        }
        if ($jumlah <= 0) {
            return $this->gagal('Jumlah harus lebih dari 0');
        }

        $asal   = $this->AkunModel->find($asalId);
        $tujuan = $this->AkunModel->find($tujuanId);
        if (!$asal || $asal->status !== 'aktif' || !$tujuan || $tujuan->status !== 'aktif') {
            return $this->gagal('Akun asal / tujuan tidak valid atau tidak aktif');
        }

        $transferRef = 'TRF-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $db = \Config\Database::connect();
        $db->transStart();

        $duplikat = $this->TransaksiModel->where('transfer_ref', $transferRef)->first();
        if ($duplikat) {
            $db->transRollback();
            return $this->gagal('Kode transfer sudah terpakai, coba lagi');
        }

        $this->TransaksiModel->insert([
            'tanggal'          => $tanggal,
            'unit_id'          => (int)$asal->unit_id,
            'akun_kas_bank_id' => $asalId,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_KELUAR,
            'jumlah'           => $jumlah,
            'akun_tujuan_id'   => $tujuanId,
            'transfer_ref'     => $transferRef,
            'keterangan'       => $ket,
            'input_by'         => (int)session()->get('ID_AKUN'),
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->TransaksiModel->insert([
            'tanggal'          => $tanggal,
            'unit_id'          => (int)$tujuan->unit_id,
            'akun_kas_bank_id' => $tujuanId,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_MASUK,
            'jumlah'           => $jumlah,
            'transfer_ref'     => $transferRef,
            'keterangan'       => $ket,
            'input_by'         => (int)session()->get('ID_AKUN'),
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan transfer');
        }

        session()->setFlashdata('sukses', 'Transfer internal berhasil (tidak memengaruhi Net Cash Flow)');
        return redirect()->to(base_url('kas_bank/transfer'));
    }

    public function reversalTransfer(int $id)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan transfer.');
        }
        $row = $this->TransaksiModel->find($id);
        if (!$row || $row->jenis !== ModeKasBank::JENIS_TRANSFER) {
            return $this->gagal('Transaksi transfer tidak ditemukan');
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $this->TransaksiModel->where('transfer_ref', $row->transfer_ref)->delete();
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal transfer');
        }

        session()->setFlashdata('sukses', 'Transfer berhasil dibatalkan');
        return redirect()->to(base_url('kas_bank/transfer'));
    }

    /**
     * Halaman pembayaran antar unit + daftar hutang/piutang antar unit.
     */
    public function antar_unit()
    {
        $unitTerpilih = $this->unitTerpilih();

        $hp = $this->HPModel->getHutangPiutangUnit($unitTerpilih > 0 ? $unitTerpilih : null, 'hutang');
        $piutang = $this->HPModel->getHutangPiutangUnit($unitTerpilih > 0 ? $unitTerpilih : null, 'piutang');

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            'akun_kas_bank' => $this->AkunModel->getAktifAll(),
            'hp_hutang'     => $hp,
            'hp_piutang'    => $piutang,
            'pembayaran'    => $this->TransaksiModel
                ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
                ->orderBy('idtransaksi', 'DESC')
                ->limit(200)
                ->findAll(),
            'histori'       => $this->PembayaranModel->findAll(),
            'body'          => 'kas_bank/antar_unit',
        ]);

        return view('template', $data);
    }

    /**
     * Simpan pembayaran antar unit (satu DB transaction, atomic).
     */
    public function saveAntarUnit()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak mencatat pembayaran antar unit.');
        }
        $tanggal     = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $hpId        = (int)$this->request->getPost('hutang_piutang_id');
        $akunKirimId = (int)$this->request->getPost('akun_pengirim_id');
        $akunTerimaId = (int)$this->request->getPost('akun_penerima_id');
        $jumlah      = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $ket         = trim((string)$this->request->getPost('keterangan'));

        if ($hpId <= 0 || $akunKirimId <= 0 || $akunTerimaId <= 0) {
            return $this->gagal('Data belum lengkap');
        }
        if ($jumlah <= 0) {
            return $this->gagal('Jumlah harus lebih dari 0');
        }

        $hp = $this->HPModel->find($hpId);
        if (!$hp || $hp->jenis !== 'hutang' || $hp->pihak_tipe !== 'unit' || $hp->deleted) {
            return $this->gagal('Hutang antar unit tidak ditemukan');
        }

        if ((int)$hp->sisa < $jumlah) {
            return $this->gagal('Jumlah melebihi sisa hutang');
        }

        $akunKirim  = $this->AkunModel->find($akunKirimId);
        $akunTerima = $this->AkunModel->find($akunTerimaId);
        if (!$akunKirim || $akunKirim->status !== 'aktif' || !$akunTerima || $akunTerima->status !== 'aktif') {
            return $this->gagal('Akun pengirim / penerima tidak valid');
        }

        // Pasangan piutang
        $piutang = $this->HPModel->getPasangan($hp);
        if (!$piutang) {
            return $this->gagal('Pasangan piutang tidak ditemukan');
        }

        // Unit pengirim harus = unit pemilik hutang; unit penerima = unit pemilik piutang
        if ((int)$akunKirim->unit_id !== (int)$hp->unit_id) {
            return $this->gagal('Akun pengirim harus milik unit yang punya hutang');
        }
        if ((int)$akunTerima->unit_id !== (int)$piutang->unit_id) {
            return $this->gagal('Akun penerima harus milik unit yang berpiutang');
        }

        $bukti = $this->uploadBukti();

        $transferRef = 'BUT-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));

        // Akun pengirim KAS -> bayar tunai; BANK -> bayar bank + bank_idbank.
        $bayarTunai = $akunKirim->tipe === 'KAS' ? $jumlah : 0;
        $bayarBank  = $akunKirim->tipe === 'BANK' ? $jumlah : 0;
        $bankId     = $akunKirim->tipe === 'BANK' ? $akunKirim->bank_idbank : null;

        $db = \Config\Database::connect();
        $db->transStart();

        // Leg 1: KELUAR dari akun pengirim (sumber_id = idtransaksi leg1)
        $this->TransaksiModel->insert([
            'tanggal'          => $tanggal,
            'unit_id'          => (int)$hp->unit_id,
            'akun_kas_bank_id' => $akunKirimId,
            'jenis'            => ModeKasBank::JENIS_ANTAR_UNIT,
            'arah'             => ModeKasBank::ARAH_KELUAR,
            'jumlah'           => $jumlah,
            'akun_tujuan_id'   => $akunTerimaId,
            'transfer_ref'     => $transferRef,
            'keterangan'       => $ket,
            'bukti'            => $bukti,
            'input_by'         => (int)session()->get('ID_AKUN'),
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
        $idKeluar = (int)$this->TransaksiModel->insertID();

        // Leg 2: MASUK ke akun penerima
        $this->TransaksiModel->insert([
            'tanggal'          => $tanggal,
            'unit_id'          => (int)$piutang->unit_id,
            'akun_kas_bank_id' => $akunTerimaId,
            'jenis'            => ModeKasBank::JENIS_ANTAR_UNIT,
            'arah'             => ModeKasBank::ARAH_MASUK,
            'jumlah'           => $jumlah,
            'akun_tujuan_id'   => $akunKirimId,
            'transfer_ref'     => $transferRef,
            'sumber_tipe'      => 'antar_unit',
            'sumber_id'        => $idKeluar,
            'keterangan'       => $ket,
            'bukti'            => $bukti,
            'input_by'         => (int)session()->get('ID_AKUN'),
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Catat pembayaran di registry existing (hutang + piutang pasangan)
        $bayarData = [
            'tanggal_bayar' => $tanggal,
            'jumlah_bayar'  => $jumlah,
            'bayar_tunai'   => $bayarTunai,
            'bayar_bank'    => $bayarBank,
            'bank_idbank'   => $bankId,
            'sumber'        => 'antar_unit',
            'referensi_tipe' => 'transaksi_kas_bank',
            'referensi_id'  => $idKeluar,
            'keterangan'    => 'Pembayaran antar unit ' . $transferRef . ($ket ? ' — ' . $ket : ''),
            'input_by'      => (int)session()->get('ID_AKUN'),
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $bayarData['hutang_piutang_id'] = (int)$hp->id;
        $this->PembayaranModel->insert($bayarData);

        $bayarData['hutang_piutang_id'] = (int)$piutang->id;
        $this->PembayaranModel->insert($bayarData);

        // Kurangi sisa hutang & piutang pasangan secara atomik
        $rH = $this->KasBankLib->terapkanPembayaranHP((int)$hp->id, $jumlah);
        $rP = $this->KasBankLib->terapkanPembayaranHP((int)$piutang->id, $jumlah);

        if ($rH['status'] !== 'ok' || $rP['status'] !== 'ok') {
            $db->transRollback();
            return $this->gagal('Gagal memperbarui sisa hutang/piutang');
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan pembayaran antar unit');
        }

        session()->setFlashdata('sukses', 'Pembayaran antar unit berhasil disimpan');
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Reversal pembayaran antar unit: kembalikan kas/bank + sisa H/P secara atomik.
     */
    public function reversalAntarUnit(int $id)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan pembayaran antar unit.');
        }
        $row = $this->TransaksiModel->find($id);
        if (!$row || $row->jenis !== ModeKasBank::JENIS_ANTAR_UNIT || $row->arah !== ModeKasBank::ARAH_KELUAR) {
            return $this->gagal('Transaksi pembayaran antar unit tidak ditemukan');
        }

        $transferRef = $row->transfer_ref;
        $idKeluar    = (int)$row->idtransaksi;

        $db = \Config\Database::connect();
        $db->transStart();

        $daftarBayar = $this->PembayaranModel->getByReferensi('transaksi_kas_bank', $idKeluar);
        foreach ($daftarBayar as $bayar) {
            $this->KasBankLib->restorePembayaranHP((int)$bayar->hutang_piutang_id, (int)$bayar->jumlah_bayar);
            $this->PembayaranModel->delete($bayar->id);
        }

        $this->TransaksiModel->where('transfer_ref', $transferRef)->delete();

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal pembayaran antar unit');
        }

        session()->setFlashdata('sukses', 'Pembayaran antar unit berhasil dibatalkan (kas/bank & sisa H/P dikembalikan)');
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Upload bukti transfer ke public/uploads/bukti_antar_unit/.
     * Return nama file (null jika tidak ada upload).
     */
    private function uploadBukti(): ?string
    {
        $file = $this->request->getFile('bukti');
        if ($file === null) {
            return null;
        }

        if (!$file->isValid()) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'];
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $dir = ROOTPATH . 'public/uploads/bukti_antar_unit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nama = 'BUT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4))) . '.' . $ext;
        $file->move($dir, $nama);

        return 'uploads/bukti_antar_unit/' . $nama;
    }
}