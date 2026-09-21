<?php

namespace App\Controllers;

use App\Models\ModelAkunKasBank;
use App\Models\ModelAlokasiSaldoKasBank;
use App\Models\ModelAuth;
use App\Models\ModelBank;
use App\Models\ModelDetailMutasi;
use App\Models\ModelHutangPiutang;
use App\Models\ModelNoAkun;
use App\Models\ModelPembayaranHutangPiutang;
use App\Models\ModelSaldoAwalKasBank;
use App\Models\ModelTransaksiKasBank;
use App\Models\ModelUnit;
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
    protected $AlokasiModel;
    protected $TransaksiModel;
    protected $SaldoAwalModel;
    protected $HPModel;
    protected $PembayaranModel;
    protected $DetailMutasiModel;
    protected $BankModel;
    protected $NoAkunModel;
    protected $AuthModel;
    protected $UnitModel;
    protected $scopeService;
    protected $KasBankLib;

    public function __construct()
    {
        $this->AkunModel = new ModelAkunKasBank();
        $this->AlokasiModel = new ModelAlokasiSaldoKasBank();
        $this->TransaksiModel = new ModelTransaksiKasBank();
        $this->SaldoAwalModel = new ModelSaldoAwalKasBank();
        $this->HPModel = new ModelHutangPiutang();
        $this->PembayaranModel = new ModelPembayaranHutangPiutang();
        $this->DetailMutasiModel = new ModelDetailMutasi();
        $this->BankModel = new ModelBank();
        $this->NoAkunModel = new ModelNoAkun();
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->scopeService = new FinanceScopeService();
        $this->KasBankLib = new ModeKasBank();
    }

    private function canInput(): bool
    {
        return $this->scopeService->canInput();
    }

    /**
     * Siapa yang boleh MENCATAT transaksi kas &amp; bank. Selain lintas unit
     * (root/direktur/manager/admin center), admin cabang juga boleh mengisi
     * Transfer Internal dan Pembayaran Antar Unit — tapi hanya memakai
     * rekening unitnya sendiri (dibatasi guard akun Terbatas).
     */
    private function bisaTransaksi(): bool
    {
        return in_array((int) session('ID_JABATAN'), [0, 1, 2, 34, 35, 40, 41, 47], true);
    }

    private function unitTerpilih(): ?int
    {
        $unit = $this->request->getGet('unit_id') ?? $this->request->getPost('unit_id');
        return $this->scopeService->resolveSelectedUnitId($unit !== null ? (string) $unit : null);
    }

    /**
     * Apakah pengguna lintas unit (bisa pilih unit lain). Admin Center/Root/
     * Direktur/Manager = lintas; admin cabang/SPV = terikat unit sendiri.
     */
    private function lintas(): bool
    {
        return $this->scopeService->scopeInfo()['isLintas'];
    }

    /**
     * Daftar rekening fisik menurut hak akses:
     * - lintas unit: KAS unit pilihan + SEMUA rekening BANK fisik;
     * - admin cabang (non-lintas): hanya rekening unitnya (KAS + BANK milik
     *   unit + rekening bersama yang dialokasikan ke unit tsb).
     */
    private function akunListUntuk(?int $unit): array
    {
        if ($this->lintas()) {
            return $unit ? $this->AkunModel->getAllWithUnit($unit) : $this->AkunModel->getAllWithUnit();
        }

        return $this->AkunModel->getAllWithUnitTerbatas($unit ?: (int) session('ID_UNIT'));
    }

    /**
     * Daftar rekening aktif untuk form transaksi (sesuai hak akses unit).
     */
    private function akunAktifUntuk(?int $unit): array
    {
        $unitId = $unit ?: (int) session('ID_UNIT');

        if ($this->lintas()) {
            return $this->AkunModel->getAktifUntukUnit($unitId);
        }

        return $this->AkunModel->getAktifUntukUnitTerbatas($unitId);
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
     * Token anti double-submit: dibuat saat halaman form dirender, disimpan di
     * session, dipakai & dihapus saat form benar-benar dikirim. Submit kedua
     * (double-click) mendapat token yang sudah terpakai -> ditolak.
     */
    private function buatSubmitToken(): string
    {
        $token = bin2hex(random_bytes(16));
        session()->set('kb_submit_' . $token, time());
        return $token;
    }

    /**
     * Klaim (konsumsi) token submit. Return false bila token tidak ada / sudah
     * dipakai -> submit ganda harus ditolak.
     */
    private function klaimSubmitToken(string $token): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $key = 'kb_submit_' . $token;
        if (session()->get($key) === null) {
            return false;
        }
        session()->remove($key);
        return true;
    }

    /**
     * Dashboard Kas & Bank.
     *
     * - Tiap akun = rekening KAS/BANK FISIK. Saldo akun adalah saldo fisik
     *   (gabungan semua unit). Rekening bersama tampil apa adanya, tidak
     *   di-klaim eksklusif milik satu unit.
     * - Saat unit dipilih: total Kas/Bank dihitung per unit (alokasi saldo
     *   awal unit + transaksi unit), difilter via unit_id.
     * - Peringatan bila total alokasi saldo awal unit melebihi saldo fisik.
     */
    public function index()
    {
        $unitTerpilih = $this->unitTerpilih();
        $tanggalAwal  = $this->request->getGet('tanggal_awal') ?: date('Y-m-01');
        $tanggalAkhir = $this->request->getGet('tanggal_akhir') ?: date('Y-m-d');
        $akunId       = (int)$this->request->getGet('akun_id');

        $akun = $this->akunListUntuk($unitTerpilih > 0 ? $unitTerpilih : null);

        $saldoFisikPerAkun = [];
        $saldoUnitPerAkun  = [];
        $alokasiTotal      = [];
        $warningAlokasi    = [];
        $totalKas = 0;
        $totalBank = 0;
        $totalSemua = 0;
        $totalFisikKas = 0;
        $totalFisikBank = 0;
        $totalFisikSemua = 0;

        foreach ($akun as $a) {
            $aid = (int)$a->idakun_kas_bank;
            $fisik = $this->TransaksiModel->getSaldoFisikAkun($aid);
            $saldoFisikPerAkun[$aid] = $fisik;
            $alokasiTotal[$aid] = $this->TransaksiModel->getTotalAlokasiUnit($aid);
            if ($alokasiTotal[$aid] > $fisik) {
                $warningAlokasi[] = $aid;
            }

            $totalFisikSemua += $fisik;
            if ($a->tipe === 'KAS') {
                $totalFisikKas += $fisik;
            } else {
                $totalFisikBank += $fisik;
            }

            if ($unitTerpilih > 0) {
                $unitSaldo = $this->TransaksiModel->getSaldoUnitAkun($aid, $unitTerpilih);
                $saldoUnitPerAkun[$aid] = $unitSaldo;
                $totalSemua += $unitSaldo;
                if ($a->tipe === 'KAS') {
                    $totalKas += $unitSaldo;
                } else {
                    $totalBank += $unitSaldo;
                }
            } else {
                $totalSemua += $fisik;
                if ($a->tipe === 'KAS') {
                    $totalKas += $fisik;
                } else {
                    $totalBank += $fisik;
                }
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

        // Finance cut-off: Net Cash Flow & ringkasan pemasukan/pengeluaran yang
        // ditampilkan adalah arus kas OPERASIONAL pada/setelah cut-off. Baris
        // "kas awal" (penanda saldo dari backfill sistem lama) bukan transaksi;
        // transaksi sebelum cut-off adalah legacy dan tidak dihitung ulang.
        $cutoff = FinanceScopeService::cutoffDate();
        $builder->where('transaksi_kas_bank.tanggal >=', $cutoff);
        $builder->groupStart()
            ->where('transaksi_kas_bank.keterangan !=', 'kas awal')
            ->groupStart()
                ->where('transaksi_kas_bank.keterangan IS NOT NULL')
                ->where('transaksi_kas_bank.keterangan !=', '')
            ->groupEnd()
        ->groupEnd();

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
            'unit_terpilih'        => $unitTerpilih,
            'akun_kas_bank'        => $akun,
            'saldo_fisik_per_akun' => $saldoFisikPerAkun,
            'saldo_unit_per_akun'  => $saldoUnitPerAkun,
            'alokasi_total'        => $alokasiTotal,
            'warning_alokasi'      => $warningAlokasi,
            'total_kas'            => $totalKas,
            'total_bank'           => $totalBank,
            'total_semua'          => $totalSemua,
            'total_fisik_kas'      => $totalFisikKas,
            'total_fisik_bank'     => $totalFisikBank,
            'total_fisik_semua'    => $totalFisikSemua,
            'ringkasan'            => $ringkasan,
            'net_cash_flow'        => $netCashFlow,
            'filter'               => [
                'tanggal_awal'  => $tanggalAwal,
                'tanggal_akhir' => $tanggalAkhir,
                'akun_id'       => $akunId,
            ],
            'body'                 => 'kas_bank/dashboard',
        ]);

        return view('template', $data);
    }

    /**
     * Master akun kas/bank + saldo awal + alokasi saldo awal per unit.
     */
    public function akun()
    {
        $unitTerpilih = $this->unitTerpilih();

        $akun = $this->akunListUntuk($unitTerpilih ?: null);

        $fisikSaldo = [];
        foreach ($akun as $a) {
            $fisikSaldo[(int)$a->idakun_kas_bank] = $this->TransaksiModel->getSaldoFisikAkun((int)$a->idakun_kas_bank);
        }

        $data = array_merge($this->pageData(), [
            'unit_terpilih'     => $unitTerpilih,
            'akun_kas_bank'     => $akun,
            'saldo_fisik_akun'  => $fisikSaldo,
            'bank'              => $this->BankModel->getBank(),
            'no_akun'           => $this->NoAkunModel->getAkun(),
            'saldo_awal'        => $this->SaldoAwalModel->findAll(),
            'alokasi'           => $this->AlokasiModel->indexByAkun(),
            'unit_list'         => $this->scopeService->resolveAllowedUnits(),
            'body'              => 'kas_bank/akun',
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
        $shared = $this->request->getPost('is_shared') === '1';

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

        // KAS adalah fisik per unit -> wajib ada unit. BANK adalah rekening
        // fisik (bisa lintas unit / shared -> unit boleh kosong).
        if ($tipe === 'KAS' && $unitId <= 0) {
            return $this->gagal('Akun KAS wajib diisi unit-nya');
        }
        if ($tipe === 'BANK' && $unitId <= 0) {
            $shared = true;
        }
        if ($tipe === 'KAS') {
            $shared = false;
        }

        $noAkunCoa = trim((string)$this->request->getPost('no_akun_coa'));
        $noAkunCoa = $noAkunCoa === '' ? null : $noAkunCoa;
        $status    = $this->request->getPost('status') === 'nonaktif' ? 'nonaktif' : 'aktif';

        if ($tipe === 'BANK') {
            // 1 rekening fisik = 1 akun BANK.
            $duplikat = $this->AkunModel->where('tipe', 'BANK')->where('bank_idbank', $bankId);
            if ($id > 0) {
                $duplikat->where('idakun_kas_bank !=', $id);
            }
            if ($duplikat->first()) {
                return $this->gagal('Rekening bank ini sudah terdaftar sebagai akun fisik (satu rekening = satu akun).');
            }
        } else {
            $duplikat = $this->AkunModel->where('unit_id', $unitId)->where('nama_akun', $nama);
            if ($id > 0) {
                $duplikat->where('idakun_kas_bank !=', $id);
            }
            if ($duplikat->first()) {
                return $this->gagal('Nama akun sudah dipakai di unit ini');
            }
        }

        $data = [
            'unit_id'     => $unitId > 0 ? $unitId : null,
            'tipe'        => $tipe,
            'nama_akun'   => $nama,
            'bank_idbank' => $bankId,
            'no_akun_coa' => $noAkunCoa,
            'status'      => $status,
            'is_shared'   => $shared ? 1 : 0,
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

    /**
     * Simpan alokasi saldo awal per unit pada satu rekening fisik.
     * Tidak mengubah saldo fisik; total alokasi tidak boleh melebihi
     * saldo fisik rekening.
     */
    public function saveAlokasiSaldo()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak mengatur alokasi saldo.');
        }
        $akunId = (int)$this->request->getPost('akun_kas_bank_id');
        $unitId = (int)$this->request->getPost('unit_id');
        $nominal = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('nominal'));
        $ket = trim((string)$this->request->getPost('keterangan'));

        if ($akunId <= 0 || $unitId <= 0) {
            return $this->gagal('Rekening dan unit wajib dipilih');
        }
        if ($nominal <= 0) {
            return $this->gagal('Nominal alokasi harus lebih dari 0');
        }

        $akun = $this->AkunModel->find($akunId);
        if (!$akun || $akun->tipe !== 'BANK' || $akun->status !== 'aktif') {
            return $this->gagal('Alokasi khusus untuk rekening BANK aktif');
        }

        $saldoFisik = $this->TransaksiModel->getSaldoFisikAkun($akunId);
        $sekarang = $this->AlokasiModel->sumByAkun($akunId);

        $existing = $this->AlokasiModel->getByAkunUnit($akunId, $unitId);
        $sebelum  = $existing ? (int)$existing->nominal : 0;

        if ($sekarang - $sebelum + $nominal > $saldoFisik) {
            return $this->gagal('Total alokasi unit melebihi saldo fisik rekening (' .
                number_format($saldoFisik) . ').');
        }

        $data = [
            'akun_kas_bank_id' => $akunId,
            'unit_id'          => $unitId,
            'nominal'          => $nominal,
            'keterangan'       => $ket,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->AlokasiModel->update($existing->id, $data);
        } else {
            $data['input_by']   = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->AlokasiModel->insert($data);
        }

        session()->setFlashdata('sukses', 'Alokasi saldo awal unit berhasil disimpan');
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
     * Halaman transfer internal. Akun yang bisa dipilih = yang bisa dipakai
     * unit terpilih (KAS unit + semua rekening BANK fisik).
     */
    public function transfer()
    {
        $unitTerpilih = $this->unitTerpilih();

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            'akun_kas_bank' => $this->akunAktifUntuk($unitTerpilih),
            'can_transaksi' => $this->bisaTransaksi(),
            'transaksi'     => $this->TransaksiModel
                ->where('jenis', ModeKasBank::JENIS_TRANSFER)
                ->orderBy('idtransaksi', 'DESC')
                ->limit(200)
                ->findAll(),
            'submit_token'  => $this->buatSubmitToken(),
            'body'          => 'kas_bank/transfer',
        ]);

        return view('template', $data);
    }

    public function saveTransfer()
    {
        if (!$this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan transfer internal.');
        }
        $asalId    = (int)$this->request->getPost('akun_asal_id');
        $tujuanId  = (int)$this->request->getPost('akun_tujuan_id');
        $jumlah    = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $tanggal   = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $ket       = trim((string)$this->request->getPost('keterangan'));
        $token     = (string)$this->request->getPost('submit_token');

        // Unit transaksi di-stamp dari form (default akun sesi).
        $unitId = (int)$this->request->getPost('unit_id');
        if ($unitId <= 0) {
            $unitId = (int)session()->get('ID_UNIT');
        }
        if ($unitId <= 0) {
            $unitId = $this->unitTerpilih();
        }

        if (!$this->klaimSubmitToken($token)) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }
        if ($asalId <= 0 || $tujuanId <= 0) {
            return $this->gagal('Pilih akun asal dan tujuan');
        }
        if ($asalId === $tujuanId) {
            return $this->gagal('Asal dan tujuan tidak boleh rekening fisik yang sama. ' .
                'Jika dua unit berbagi rekening yang sama itu BUKAN transfer internal.');
        }
        if ($jumlah <= 0) {
            return $this->gagal('Jumlah harus lebih dari 0');
        }

        $asal   = $this->AkunModel->find($asalId);
        $tujuan = $this->AkunModel->find($tujuanId);
        if (!$asal || $asal->status !== 'aktif' || !$tujuan || $tujuan->status !== 'aktif') {
            return $this->gagal('Akun asal / tujuan tidak valid atau tidak aktif');
        }

        // Admin cabang: hanya boleh memindahkan antar rekening unitnya sendiri.
        if (!$this->lintas()) {
            $boleh = $this->AkunModel->getAktifUntukUnitTerbatas((int) session('ID_UNIT'));
            $bolehIds = array_map('intval', array_column($boleh, 'idakun_kas_bank'));
            if (!in_array($asalId, $bolehIds, true) || !in_array($tujuanId, $bolehIds, true)) {
                return $this->gagal('Transfer hanya boleh antar rekening unit Anda');
            }
        }

        // Unit per leg: KAS terikat unit pemilik rekening; BANK (rekening
        // fisik) di-stamp unit transaksi dari form (default sesi/unit terpilih).
        $unitKeluar = $asal->tipe === 'KAS' ? (int)$asal->unit_id : $unitId;
        $unitMasuk  = $tujuan->tipe === 'KAS' ? (int)$tujuan->unit_id : $unitId;

        $transferRef = 'TRF-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $bukti       = $this->uploadBukti();

        $db = \Config\Database::connect();
        try {
            $db->transStart();

            $duplikat = $this->TransaksiModel->where('transfer_ref', $transferRef)->first();
            if ($duplikat) {
                $db->transRollback();
                return $this->gagal('Kode transfer sudah terpakai, coba lagi');
            }

            $this->TransaksiModel->insert([
                'tanggal'          => $tanggal,
                'unit_id'          => $unitKeluar,
                'akun_kas_bank_id' => $asalId,
                'jenis'            => ModeKasBank::JENIS_TRANSFER,
                'arah'             => ModeKasBank::ARAH_KELUAR,
                'jumlah'           => $jumlah,
                'akun_tujuan_id'   => $tujuanId,
                'transfer_ref'     => $transferRef,
                'submission_key'   => $token,
                'keterangan'       => $ket,
                'bukti'            => $bukti,
                'input_by'         => (int)session()->get('ID_AKUN'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $this->TransaksiModel->insert([
                'tanggal'          => $tanggal,
                'unit_id'          => $unitMasuk,
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
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan transfer: ' . $e->getMessage());
            return $this->gagal('Gagal menyimpan transfer. Silakan coba lagi.');
        }

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

        // Detail barang mutasi untuk H/P yang bersumber dari mutasi stok
        // (bukti H/P = list barang yang dikirim).
        $detailMutasiMap = [];
        foreach (array_merge($hp, $piutang) as $row) {
            if ($row->sumber_tipe !== 'mutasi_unit' || (int) $row->sumber_id <= 0) {
                continue;
            }
            $mid = (int) $row->sumber_id;
            if (isset($detailMutasiMap[$mid])) {
                continue;
            }
            $detail = $this->DetailMutasiModel->getFullDetailMutasiByMutasiId($mid);
            if (empty($detail)) {
                continue;
            }
            $total = 0;
            foreach ($detail as $d) {
                $d->nilai = $this->KasBankLib->nilaiDetailMutasi((array) $d);
                $total += (int) $d->nilai;
            }
            $detailMutasiMap[$mid] = [
                'no_nota' => $detail[0]->no_nota_mutasi ?? '',
                'tanggal' => $detail[0]->mutasi_tanggal_kirim ?? '',
                'total'   => $total,
                'items'   => $detail,
            ];
        }

        // Reversal atribusi: pembayaran antar unit yang MEMAKAI rekening fisik
        // yang sama (tidak membuat gerakan kas/bank).
        $atribusi = $this->PembayaranModel
            ->where('sumber', 'antar_unit')
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->orderBy('id', 'DESC')
            ->limit(100)
            ->findAll();

        $data = array_merge($this->pageData(), [
            'unit_terpilih'    => $unitTerpilih,
            'akun_kas_bank'    => $this->akunAktifUntuk($unitTerpilih),
            'can_transaksi'    => $this->bisaTransaksi(),
            'akun_penerima'    => $this->akunListUntuk($unitTerpilih),
            'hp_hutang'        => $hp,
            'hp_piutang'       => $piutang,
            'detail_mutasi_map'=> $detailMutasiMap,
            'pembayaran'       => $this->TransaksiModel
                ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
                ->orderBy('idtransaksi', 'DESC')
                ->limit(200)
                ->findAll(),
            'histori'          => $this->PembayaranModel->findAll(),
            'histori_atribusi' => $atribusi,
            'submit_token'     => $this->buatSubmitToken(),
            'body'             => 'kas_bank/antar_unit',
        ]);

        return view('template', $data);
    }

    /**
     * Cek akun fisik bisa dipakai satu unit: KAS harus milik unit tsb;
     * BANK (rekening fisik) bebas dipakai lintas unit.
     */
    private function cekAkunUntukUnit($akun, int $unitId): bool
    {
        if ($akun->tipe === 'KAS') {
            return (int)$akun->unit_id === $unitId;
        }
        return true; // rekening BANK fisik
    }

    /**
     * Simpan pembayaran antar unit (satu DB transaction, atomic).
     *
     * Dua skenario rekening:
     * a) Pengirim & penerima pakai rekening FISIK berbeda -> 2 leg kas/bank.
     * b) Pengirim & penerima pakai rekening FISIK yang SAMA -> TIDAK membuat
     *    gerakan kas/bank; hanya menyelesaikan H/P + catatan pembayaran
     *    bertipe 'antar_unit_atribusi' (atribusi internal, ledger mencerminkan
     *    kenyataan: uang tidak berpindah rekening).
     */
    public function saveAntarUnit()
    {
        if (!$this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak mencatat pembayaran antar unit.');
        }
        $tanggal     = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $hpId        = (int)$this->request->getPost('hutang_piutang_id');
        $akunKirimId = (int)$this->request->getPost('akun_pengirim_id');
        $akunTerimaId = (int)$this->request->getPost('akun_penerima_id');
        $jumlah      = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $ket         = trim((string)$this->request->getPost('keterangan'));
        $token       = (string)$this->request->getPost('submit_token');

        if (!$this->klaimSubmitToken($token)) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }
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

        // Kelayakan akun fisik per unit (KAS unit tsb / BANK bebas).
        if (!$this->cekAkunUntukUnit($akunKirim, (int)$hp->unit_id)) {
            return $this->gagal('Akun pengirim harus KAS milik unit yang punya hutang atau rekening BANK fisik');
        }
        if (!$this->cekAkunUntukUnit($akunTerima, (int)$piutang->unit_id)) {
            return $this->gagal('Akun penerima harus KAS milik unit yang berpiutang atau rekening BANK fisik');
        }

        // Admin cabang: hanya boleh memakai rekening unitnya sendiri.
        if (!$this->lintas()) {
            $boleh = $this->AkunModel->getAktifUntukUnitTerbatas((int) session('ID_UNIT'));
            $bolehIds = array_map('intval', array_column($boleh, 'idakun_kas_bank'));
            if (!in_array($akunKirimId, $bolehIds, true) || !in_array($akunTerimaId, $bolehIds, true)) {
                return $this->gagal('Pembayaran hanya boleh pakai rekening unit Anda');
            }
        }

        $bukti = $this->uploadBukti();

        $transferRef = 'BUT-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $db = \Config\Database::connect();
        try {
            $db->transStart();
            $idKeluar = null;

            if ($akunKirimId === $akunTerimaId) {
                // ---------- SKENARIO (b): rekening fisik SAMA ----------
                // Tidak ada gerakan kas/bank; cukup selesaikan H/P. Tandai
                // pembayaran dengan referensi_tipe 'antar_unit_atribusi' dan
                // referensi_id = id hutang, agar bisa di-reversal tanpa
                // menyentuh saldo (yang memang tidak berubah).
                $bayarData = [
                    'tanggal_bayar'   => $tanggal,
                    'jumlah_bayar'    => $jumlah,
                    'bayar_tunai'     => $akunKirim->tipe === 'KAS' ? $jumlah : 0,
                    'bayar_bank'      => $akunKirim->tipe === 'BANK' ? $jumlah : 0,
                    'bank_idbank'     => $akunKirim->tipe === 'BANK' ? $akunKirim->bank_idbank : null,
                    'sumber'          => 'antar_unit',
                    'referensi_tipe'  => 'antar_unit_atribusi',
                    'referensi_id'    => (int)$hp->id,
                    'keterangan'      => 'Penyelesaian H/P antar unit (rekening fisik sama — tanpa gerakan kas) ' .
                        $transferRef . ($ket ? ' — ' . $ket : ''),
                    'input_by'        => (int)session()->get('ID_AKUN'),
                    'created_at'      => date('Y-m-d H:i:s'),
                ];

                $bayarData['hutang_piutang_id'] = (int)$hp->id;
                $this->PembayaranModel->insert($bayarData);
                $bayarData['hutang_piutang_id'] = (int)$piutang->id;
                $this->PembayaranModel->insert($bayarData);
            } else {
                // ---------- SKENARIO (a): rekening fisik BEDA ----------
                // Akun pengirim KAS -> bayar tunai; BANK -> bayar bank.
                $bayarTunai = $akunKirim->tipe === 'KAS' ? $jumlah : 0;
                $bayarBank  = $akunKirim->tipe === 'BANK' ? $jumlah : 0;
                $bankId     = $akunKirim->tipe === 'BANK' ? $akunKirim->bank_idbank : null;

                // Leg 1: KELUAR dari akun pengirim.
                // submission_key = token form (UNIQUE) -> anti double-submit.
                $this->TransaksiModel->insert([
                    'tanggal'          => $tanggal,
                    'unit_id'          => (int)$hp->unit_id,
                    'akun_kas_bank_id' => $akunKirimId,
                    'jenis'            => ModeKasBank::JENIS_ANTAR_UNIT,
                    'arah'             => ModeKasBank::ARAH_KELUAR,
                    'jumlah'           => $jumlah,
                    'akun_tujuan_id'   => $akunTerimaId,
                    'transfer_ref'     => $transferRef,
                    'submission_key'   => $token,
                    'keterangan'       => $ket,
                    'bukti'            => $bukti,
                    'input_by'         => (int)session()->get('ID_AKUN'),
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
                $idKeluar = (int)$this->TransaksiModel->insertID();

                // Leg 2: MASUK ke akun penerima.
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

                // Catat pembayaran di registry existing (hutang + piutang pasangan).
                $bayarData = [
                    'tanggal_bayar'  => $tanggal,
                    'jumlah_bayar'   => $jumlah,
                    'bayar_tunai'    => $bayarTunai,
                    'bayar_bank'     => $bayarBank,
                    'bank_idbank'    => $bankId,
                    'sumber'         => 'antar_unit',
                    'referensi_tipe' => 'transaksi_kas_bank',
                    'referensi_id'   => $idKeluar,
                    'keterangan'     => 'Pembayaran antar unit ' . $transferRef . ($ket ? ' — ' . $ket : ''),
                    'input_by'       => (int)session()->get('ID_AKUN'),
                    'created_at'     => date('Y-m-d H:i:s'),
                ];

                $bayarData['hutang_piutang_id'] = (int)$hp->id;
                $this->PembayaranModel->insert($bayarData);
                $bayarData['hutang_piutang_id'] = (int)$piutang->id;
                $this->PembayaranModel->insert($bayarData);
            }

            // Kurangi sisa hutang & piutang pasangan secara atomik.
            $rH = $this->KasBankLib->terapkanPembayaranHP((int)$hp->id, $jumlah);
            $rP = $this->KasBankLib->terapkanPembayaranHP((int)$piutang->id, $jumlah);

            if ($rH['status'] !== 'ok' || $rP['status'] !== 'ok') {
                $db->transRollback();
                return $this->gagal('Gagal memperbarui sisa hutang/piutang');
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan pembayaran antar unit: ' . $e->getMessage());
            return $this->gagal('Gagal menyimpan pembayaran antar unit. Silakan coba lagi.');
        }

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan pembayaran antar unit');
        }

        session()->setFlashdata(
            'sukses',
            $akunKirimId === $akunTerimaId
                ? 'Pembayaran antar unit berhasil disimpan (rekening fisik sama — H/P diselesaikan tanpa gerakan kas)'
                : 'Pembayaran antar unit berhasil disimpan'
        );
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Reversal pembayaran antar unit ATRIBUSI (rekening fisik sama, tanpa
     * gerakan kas/bank): kembalikan sisa H/P dan hapus catatan pembayaran.
     */
    public function reversalAtribusi(int $idHp)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan pembayaran antar unit.');
        }

        $daftarBayar = $this->PembayaranModel
            ->where('sumber', 'antar_unit')
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->where('referensi_id', $idHp)
            ->findAll();
        if (!$daftarBayar) {
            return $this->gagal('Catatan pembayaran atribusi tidak ditemukan');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($daftarBayar as $bayar) {
            $this->KasBankLib->restorePembayaranHP((int)$bayar->hutang_piutang_id, (int)$bayar->jumlah_bayar);
            $this->PembayaranModel->delete($bayar->id);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal pembayaran antar unit atribusi');
        }

        session()->setFlashdata('sukses', 'Pembayaran antar unit atribusi berhasil dibatalkan (sisa H/P dikembalikan)');
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