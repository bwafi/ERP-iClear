<?php

namespace App\Controllers;

use App\Models\ModelKasKeluar;
use App\Models\ModelAuth;
use App\Models\ModelKategoriKas;
use App\Models\ModelNoAkun;
use App\Models\ModelBank;
use App\Models\ModelKasMasuk;
use App\Models\ModelJurnal;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\ModelUnit;
use App\Models\ModelAkunKasBank;
use App\Libraries\ModeKasBank;
use App\Services\Finance\BankRekeningValidator;
use App\Services\Finance\FinanceScopeService;
use App\Exceptions\PostingLedgerException;


class Kas_Masuk extends BaseController
{
    protected $KasKeluarModel;
    protected $AuthModel;
    protected $KategoriKasModel;
    protected $NoAkunModel;
    protected $BankModel;
    protected $KasMasukModel;
    protected $JurnalModel;
    protected $UnitModel;
    protected $KasBankLib;
    protected $FinanceScope;

    public function __construct()
    {
        $this->KasKeluarModel = new ModelKasKeluar();
        $this->AuthModel = new ModelAuth();
        $this->KategoriKasModel = new ModelKategoriKas();
        $this->NoAkunModel = new ModelNoAkun();
        $this->BankModel = new ModelBank();
        $this->KasMasukModel = new ModelKasMasuk();
        $this->JurnalModel = new ModelJurnal();
        $this->UnitModel = new ModelUnit();
        $this->KasBankLib = new ModeKasBank();
        $this->FinanceScope = new FinanceScopeService();
    }

    private function bebasPilihUnit(): bool
    {
        return in_array((int) session('ID_JABATAN'), FinanceScopeService::inputRoles(), true);
    }

    /**
     * Unit untuk dropdown filter. Role lintas melihat semua unit, SPV hanya
     * unit binaannya (spv_units), role lain hanya unitnya sendiri.
     *
     * @return object[]
     */
    private function unitFilterList(): array
    {
        if ($this->bebasPilihUnit()) {
            return $this->UnitModel->getUnit();
        }

        return $this->FinanceScope->resolveAllowedUnits();
    }

    /**
     * Unit filter yang benar-benar dipakai. Request dari role terbatas tidak
     * dipercaya: unit di luar scope jatuh ke unit pertama yang diizinkan.
     */
    private function unitFilterTerpilih($requested): int
    {
        $req = (int) $requested;

        if ($this->bebasPilihUnit()) {
            return $req;
        }

        $allowed = array_map('intval', $this->FinanceScope->allowedUnitIds());
        if ($req > 0 && in_array($req, $allowed, true)) {
            return $req;
        }

        return (int) ($allowed[0] ?? session('ID_UNIT'));
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $unitList = $this->UnitModel->getUnit();

        // Dropdown "No. Rekening" hanya menawarkan rekening yang bisa dipakai
        // unit itu. Rekening Finance/HO tetap ikut untuk role yang berhak.
        $role = isset($akun->ID_JABATAN) ? (int) $akun->ID_JABATAN : null;
        $bolehFinanceHo = $role !== null && in_array(
            $role,
            array_map('intval', config(\Config\Finance::class)->financeHoSourceRoles ?? []),
            true
        );

        $financeHoBankIds = [];
        if ($bolehFinanceHo) {
            $financeHo = (new ModelAkunKasBank())
                ->where('tipe', 'BANK')
                ->where('is_finance_ho', 1)
                ->where('status', 'aktif')
                ->findAll();
            foreach ($financeHo as $a) {
                $financeHoBankIds[] = trim((string) $a->bank_idbank);
            }
        }

        // Daftar rekening dibangun untuk SEMUA unit sekaligus supaya unit yang
        // dipilih di form bisa langsung mengganti isi dropdown tanpa request
        // tambahan.
        $bankPerUnit = [];
        foreach ($unitList as $u) {
            $unitId = (int) $u->idunit;
            $daftar = [];
            foreach ($this->BankModel->getBankDalamScopeUnit($unitId) as $b) {
                $daftar[(string) $b->idbank] = $b;
            }
            foreach ($financeHoBankIds as $idbank) {
                $b = $this->BankModel->getById($idbank);
                if ($b) {
                    $daftar[(string) $b->idbank] = $b;
                }
            }
            $bankPerUnit[$unitId] = array_values($daftar);
        }

        $loginUnit = (int) ($akun->ID_UNIT ?? session('ID_UNIT'));
        $bebasPilihUnit = $this->bebasPilihUnit();
        $unitFilter = $this->unitFilterList();
        $unitFilterIds = array_map(static fn ($u) => (int) $u->idunit, $unitFilter);

        $data = [
            'akun' => $akun,
            'kategori_kas' => $this->KategoriKasModel->getKategoriKas(),
            'no_akun' =>  $this->NoAkunModel->getAkunMasuk(),
            'bank' => $bankPerUnit[$loginUnit] ?? [],
            'bank_per_unit' => $bankPerUnit,
            'unit' => $unitList,
            'unit_filter' => $unitFilter,
            'can_filter_unit' => $bebasPilihUnit || count($unitFilterIds) > 1,
            'show_all_units' => $bebasPilihUnit,
            'default_filter_unit' => $bebasPilihUnit ? 0 : (int) ($unitFilterIds[0] ?? $loginUnit),
            // Tombol Edit/Hapus hanya untuk Admin Root. Server tetap memakai
            // filter `auth` seperti sebelumnya; ini hanya kontrol tampilan.
            'can_act' => (int) session('ID_JABATAN') === 1,
            'body' => 'jurnal/kas_masuk'
        ];

        return view('template', $data);
    }

    /** DataTables server-side untuk daftar kas masuk. */
    public function datatable()
    {
        $draw   = (int) $this->request->getGet('draw');
        $start  = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');
        if ($start < 0) {
            $start = 0;
        }
        if ($length < 1) {
            $length = 25;
        }

        $search = $this->request->getGet('search');
        $search = is_array($search) ? trim((string) ($search['value'] ?? '')) : trim((string) $search);

        $order = $this->request->getGet('order');
        $orderColIdx = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDir    = isset($order[0]['dir']) ? strtoupper($order[0]['dir']) : 'DESC';

        $columnMap = [
            0 => 'kas_masuk.tanggal',
            1 => 'unit.NAMA_UNIT',
            2 => 'kas_masuk.no_akun',
            3 => 'kategori_kas.kategori',
            4 => 'kas_masuk.deskripsi',
            5 => 'bank.nama_bank',
            6 => 'kas_masuk.penerima',
            7 => 'bank.norek',
            8 => 'kas_masuk.jumlah',
            9 => null, // aksi — tidak urutkan
        ];
        $orderCol = $columnMap[$orderColIdx] ?? 'kas_masuk.tanggal';

        $startDate = trim((string) $this->request->getGet('tanggal_awal'));
        $endDate   = trim((string) $this->request->getGet('tanggal_akhir'));
        $unitId    = $this->unitFilterTerpilih($this->request->getGet('unit_id'));

        $kategoriId = (int) $this->request->getGet('kategori_id');
        if ($kategoriId <= 0) {
            $kategoriId = null;
        }

        // recordsTotal adalah "semua baris yang boleh dilihat user", bukan
        // seluruh perusahaan: role terbatas tidak boleh membaca hitungan unit
        // lain lewat angka ini.
        $total = $this->bebasPilihUnit()
            ? $this->KasMasukModel->countAllKasMasuk()
            : $this->KasMasukModel->countKasMasukFiltered('', null, null, $unitId ?: null);

        $filtered = $this->KasMasukModel->countKasMasukFiltered(
            $search,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $kategoriId
        );
        $summary = $this->KasMasukModel->sumKasMasukFiltered(
            $search,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $kategoriId
        );
        $rows = $this->KasMasukModel->getKasMasukDataTable(
            $length,
            $start,
            $search,
            $orderCol,
            $orderDir,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $kategoriId
        );

        // Nilai dikembalikan terpisah, tanpa HTML: sel disusun di tampilan agar
        // escaping punya satu tempat yang jelas.
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'         => (int) $r->idkas_masuk,
                'tanggal'    => (string) $r->tanggal,
                'unit'       => (string) ($r->NAMA_UNIT ?? ''),
                'no_akun'    => (string) ($r->no_akun ?? ''),
                'nama_akun'  => trim((string) ($r->nama_akun ?? '')),
                'kategori'   => (string) ($r->kategori ?? ''),
                'kategori_id' => (int) ($r->kategori_idkategori ?? 0),
                // idbank VARCHAR: jangan cast ke int, 'BNI-001' akan jadi 0.
                'idbank'     => (string) ($r->idbank ?? ''),
                'deskripsi'  => (string) ($r->deskripsi ?? ''),
                'nama_bank'  => (string) ($r->nama_bank ?? ''),
                'norek'      => (string) ($r->norek ?? ''),
                'penerima'   => (string) ($r->penerima ?? ''),
                'jumlah'     => (float) $r->jumlah,
                'jenis'      => trim((string) ($r->jenis ?? '')),
                'idunit'     => (int) ($r->idunit ?? 0),
                'aksi'       => null,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'sumTotal'        => $summary['total'],
            'sumTunai'        => $summary['tunai'],
            'sumTransfer'     => $summary['transfer'],
            'countFiltered'   => $summary['n'],
            'data'            => $data,
        ]);
    }

    public function insert_kas_masuk()
    {
        $tanggal = $this->request->getPost('tanggal');
        $deskripsi = $this->request->getPost('deskripsi');
        $idunit = $this->request->getPost('unit_idunit');


        $akunData = $this->request->getPost('akun');

        if (! is_array($akunData) || $akunData === []) {
            session()->setFlashdata('gagal', 'Minimal satu baris akun harus diisi.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        // Validasi SEMUA baris sebelum transaksi: satu baris terlarang
        // membatalkan seluruh batch tanpa menulis apa pun.
        foreach ($akunData as $baris) {
            $noAkun = trim((string) ($baris['no_akun'] ?? ''));
            $akun   = $noAkun === '' ? null : $this->NoAkunModel->getByNoAkun($noAkun);

            if ($akun === null) {
                session()->setFlashdata('gagal', 'Akun "' . $noAkun . '" tidak terdaftar.');
                return redirect()->to(base_url('/kas_masuk'));
            }
            if (! $this->NoAkunModel->bolehUntukKasMasuk($akun)) {
                session()->setFlashdata(
                    'gagal',
                    'Akun "' . $noAkun . '" (' . ($akun->jenis_akun ?? 'tanpa jenis') . ') tidak boleh dipakai untuk Kas Masuk.'
                );
                return redirect()->to(base_url('/kas_masuk'));
            }
        }

        // Simpan source + posting ledger + jurnal dalam SATU transaksi.
        //
        // KENAPA transStart DI SINI: alur lama tidak punya batas transaksi.
        // kas_masuk tersimpan, posting diabaikan (status 'skipped' tidak
        // dilempar), user tetap dapat flash "berhasil disimpan" padahal
        // transaksi_kas_bank kosong. Resolve yang gagal sekarang melempar
        // PostingLedgerException dan membatalkan SELURUH batch.
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            foreach ($akunData as $data) {
                $noAkun = $data['no_akun'];
                $noRekening = isset($data['no_rekening']) ? $data['no_rekening'] : null;
                if (empty($noRekening)) {
                    $noRekening = null;
                }
                $jumlah = preg_replace('/[^0-9]/', '', $data['jumlah']);
                $penerima = $data['penerima'];
                // COA yang dipilih adalah akun lawan; sisi kas/bank didebit
                // implisit, jadi baris jurnal selalu kredit.
                $jenis = 'kredit';
                $kategori_idkategori = $data['kategori_idkategori'];

                // Validasi rekening SEBELUM sumber disimpan.
                //
                // Dulu idbank diteruskan apa adanya, lalu kalau tidak
                // terpetakan ModeKasBank::resolveAkunDetail() hanya log warning
                // dan skip. Sumber tetap "berhasil" tapi uangnya tidak pernah
                // masuk ledger. Sekarang format salah ketahuan di tempat yang
                // tepat, dengan pesan yang bisa ditindaklanjuti user.
                //
                // Perhatikan: TIDAK ada cast ke int. idbank itu VARCHAR, jadi
                // 'BNI-001' bukan 0 dan ' 15 ' bukan 15.
                //
                // $idunit ikut diteruskan supaya rekening yang tidak dialokasikan
                // ke unit ini ditolak. Tanpa itu setiap unit bisa menulis ke
                // rekening unit lain — sumber seluruh polusi histori.
                $cekRek = (new BankRekeningValidator())->validateUntukUnit(
                    $noRekening,
                    (int) $idunit
                );
                if (! $cekRek['ok']) {
                    throw new PostingLedgerException(
                        [
                            'status' => 'failed',
                            'reason' => $cekRek['alasan'],
                        ],
                        'Validasi rekening kas masuk'
                    );
                }
                $noRekening = $cekRek['idbank'];

                // Simpan data kas masuk
                $dataKasMasuk = [
                    'tanggal' => $tanggal,
                    'kategori_idkategori' => $kategori_idkategori,
                    'no_akun' => $noAkun,
                    'deskripsi' => $deskripsi,
                    'jumlah' => $jumlah,
                    'jenis' => $jenis,
                    'penerima' => $penerima,
                    'idbank' => $noRekening,
                    'idunit' => $idunit,
                    'created_on' => date('Y-m-d H:i:s')
                ];

                $this->KasMasukModel->insert_KasMasuk($dataKasMasuk);

                // Ambil ID kas masuk terakhir
                $insertId = $this->KasMasukModel->insertID();

                // Posting ringkas ke ledger kas/bank (idempotent).
                if ($insertId) {
                    $posting = $this->KasBankLib->postingKasMasuk((int)$insertId);

                    // Lempar HANYA kalau ledger tidak terisi. Idempotent skip
                    // ('sudah terposting') tetap lolos.
                    PostingLedgerException::wajibBerhasil($posting, 'Kas masuk');
                }

                // Ambil nama akun
                $data_akunjurnal = $this->NoAkunModel->getByNoAkun($noAkun);
                $nama_akun = $data_akunjurnal->nama_akun;

                // Tentukan debet & kredit
                $debet = ($jenis === 'debet') ? $jumlah : 0;
                $kredit = ($jenis === 'kredit') ? $jumlah : 0;

                // Simpan jurnal
                $datajurnal = [
                    'tanggal' => $tanggal,
                    'no_akun' => $noAkun,
                    'nama_akun' => $nama_akun,
                    'debet' => $debet,
                    'kredit' => $kredit,
                    'keterangan' => $deskripsi,
                    'id_referensi' => $insertId,
                    'tabel_referensi' => 'kas_masuk',
                    'id_unit' => session('ID_UNIT'),
                    'id_akun' => session('ID_AKUN')
                ];

                $this->JurnalModel->insert_biasah($datajurnal);
            }

            $db->transComplete();
        } catch (PostingLedgerException $e) {
            $db->transRollback();
            log_message('error', 'KasBank: kas masuk dibatalkan karena posting ledger gagal: ' . $e->getAlasan());
            session()->setFlashdata('gagal', $e->getMessage());

            return redirect()->to(base_url('/kas_masuk'));
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan kas masuk: ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menyimpan data kas masuk.');

            return redirect()->to(base_url('/kas_masuk'));
        }

        if ($db->transStatus() === false) {
            session()->setFlashdata('gagal', 'Gagal menyimpan data kas masuk.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        session()->setFlashdata('sukses', 'Data kas masuk berhasil disimpan.');
        return redirect()->to(base_url('/kas_masuk'));
    }



    public function update_kas_masuk()
    {
        $id = $this->request->getPost('idkas_masuk');
        $tanggal = $this->request->getPost('tanggal');
        $deskripsi = $this->request->getPost('deskripsi');
        $kategori_idkategori = $this->request->getPost('kategori_idkategori');
        $jumlah = $this->request->getPost('jumlah');
        $penerima = $this->request->getPost('penerima'); //idbank
        $idunit   = $this->request->getPost('idunit');

        // Sama seperti create: validasi rekening dulu, sekalian cek hak unit.
        //
        // Versi lama langsung $this->BankModel->getById($penerima) lalu
        // membaca ->atas_nama. Kalau idbank tidak terdaftar, getById()
        // mengembalikan null dan pembacaan property itu fatal error 500 —
        // pesan errornya tidak pernah sampai ke user.
        $cekRek = (new BankRekeningValidator())->validateUntukUnit(
            $penerima,
            (int) $idunit
        );
        if (! $cekRek['ok']) {
            session()->setFlashdata('gagal', $cekRek['alasan']);

            return redirect()->to(base_url('/kas_masuk'));
        }

        $penerima  = $cekRek['idbank'];
        $atasnama  = $cekRek['bank']->atas_nama ?? null;

        // Nominal dari form bisa berformat "75.000"; simpan digitnya saja.
        $jumlah = preg_replace('/[^0-9]/', '', (string) $jumlah);



        $data = [
            'tanggal' =>  $tanggal,
            'kategori_idkategori' =>  $kategori_idkategori,
            'deskripsi' => $deskripsi,
            'jumlah' =>   $jumlah,
            'jenis' => 'kredit',
            'penerima' => $atasnama,
            'idbank' => $penerima,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        // Source update + refresh posting ledger dalam SATU transaksi.
        //
        // ATOMIK: bila posting ulang gagal, `hapusPosting` yang barusan
        // dijalankan HARUS ikut rollback. Kalau tidak, sumber ter-update tapi
        // ledger kosong permanen. hapusPosting + postingKasMasuk keduanya
        // memakai koneksi default, jadi satu transStart sudah cukup.
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $this->KasMasukModel->update($id, $data);

            // Baris jurnal referensi `kas_masuk` ikut berubah dalam transaksi
            // yang sama. Tanpa ini nominal jurnal tetap angka lama sementara
            // sumber sudah berubah.
            $this->JurnalModel->builder()
                ->where('tabel_referensi', 'kas_masuk')
                ->where('id_referensi', (string) $id)
                ->update([
                    'tanggal'    => $tanggal,
                    'debet'      => 0,
                    'kredit'     => $jumlah,
                    'keterangan' => $deskripsi,
                ]);

            $this->KasBankLib->hapusPosting('kas_masuk', (int)$id);

            $posting = $this->KasBankLib->postingKasMasuk((int)$id);
            PostingLedgerException::wajibBerhasil($posting, 'Kas masuk');

            $db->transComplete();
        } catch (PostingLedgerException $e) {
            $db->transRollback();
            log_message('error', 'KasBank: update kas_masuk #' . $id . ' dibatalkan, posting ledger gagal: ' . $e->getAlasan());
            session()->setFlashdata('gagal', $e->getMessage());
            return redirect()->to(base_url('/kas_masuk'));
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal update kas_masuk #' . $id . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal mengupdate kas masuk.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        if ($db->transStatus() === false) {
            session()->setFlashdata('gagal', 'Gagal mengupdate kas masuk.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        session()->setFlashdata('sukses', 'Data kas Masuk berhasil diupdate.');
        return redirect()->to(base_url('/kas_masuk'));
    }

    public function delete_kas_masuk()
    {
        $id = $this->request->getPost('idkas_masuk');

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            // Hapus jurnal referensi lebih dulu, lalu posting ledger, lalu
            // sumbernya — semuanya atomik. Jurnal modul lain tidak tersentuh.
            $this->JurnalModel->hapusByReferensi('kas_masuk', (int) $id);
            $this->KasBankLib->hapusPosting('kas_masuk', (int) $id);
            $this->KasMasukModel->delete($id);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal hapus kas_masuk #' . $id . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menghapus data kas masuk.');

            return redirect()->to(base_url('/kas_masuk'));
        }

        if ($db->transStatus() === false) {
            session()->setFlashdata('gagal', 'Gagal menghapus data kas masuk.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        session()->setFlashdata('sukses', 'Data kas masuk berhasil dihapus.');
        return redirect()->to(base_url('/kas_masuk'));
    }




    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $unit = $this->request->getPost('nama_unit');
        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');


        $kasMasukData = $this->KasMasukModel->getKasMasukFiltered($tanggal_awal, $tanggal_akhir, $unit);


        $headers = [
            'A1' => 'Tanggal',
            'B1' => 'Kategori',
            'C1' => 'Deskripsi',
            'D1' => 'Jumlah',
            'E1' => 'Penerima',
            'F1' => 'Nama Unit',
            'G1' => 'Nama Bank',
            'H1' => 'No Rekening',
            'I1' => 'Jenis',
            'J1' => 'No Akun'
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }


        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');


        $row = 2;
        foreach ($kasMasukData as $item) {
            $sheet->setCellValue('A' . $row, $item->tanggal);
            $sheet->setCellValue('B' . $row, $item->kategori);
            $sheet->setCellValue('C' . $row, $item->deskripsi);
            $sheet->setCellValue('D' . $row, $item->jumlah);
            $sheet->setCellValue('E' . $row, $item->penerima);
            $sheet->setCellValue('F' . $row, $item->NAMA_UNIT);
            $sheet->setCellValue('G' . $row, $item->nama_bank);
            $sheet->setCellValue('H' . $row, $item->norek);
            $sheet->setCellValue('I' . $row, $item->jenis);
            $sheet->setCellValue('J' . $row, $item->no_akun);
            $row++;
        }


        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }


        $sheet->getStyle('A1:J' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);


        $sheet->freezePane('A2');

        $sheet->getStyle('D2:D' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

        $filename = 'Kas_Masuk_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
