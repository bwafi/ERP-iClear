<?php

namespace App\Controllers;

use App\Models\ModelKasKeluar;
use App\Models\ModelAuth;
use App\Models\ModelKategoriKas;
use App\Models\ModelNoAkun;
use App\Models\ModelBank;
use App\Models\ModelJurnal;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\ModelUnit;
use App\Libraries\ModeKasBank;

class Kas_Keluar extends BaseController
{
    protected $KasKeluarModel;
    protected $AuthModel;
    protected $KategoriKasModel;
    protected $NoAkunModel;
    protected $BankModel;
    protected $JurnalModel;
    protected $UnitModel;
    protected $KasBankLib;

    public function __construct()
    {
        $this->KasKeluarModel = new ModelKasKeluar();
        $this->AuthModel = new ModelAuth();
        $this->KategoriKasModel = new ModelKategoriKas();
        $this->NoAkunModel = new ModelNoAkun();
        $this->BankModel = new ModelBank();
        $this->JurnalModel = new ModelJurnal();
        $this->UnitModel = new ModelUnit();
        $this->KasBankLib = new ModeKasBank();
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));

        $data = [
            'akun' => $akun,
            'kategori_kas' => $this->KategoriKasModel->getKategoriKas(),
            'no_akun' =>  $this->NoAkunModel->getAkun(),
            'bank' => $this->BankModel->getBank(),
            'unit' => $this->UnitModel->getUnit(),
            'body' => 'jurnal/kas_keluar'
        ];

        return view('template', $data);
    }

    /** DataTables server-side untuk daftar kas keluar. */
    public function datatable()
    {
        $draw   = (int)$this->request->getGet('draw');
        $start  = (int)$this->request->getGet('start');
        $length = (int)$this->request->getGet('length');

        $search = $this->request->getGet('search');
        $search = is_array($search) ? trim((string)($search['value'] ?? '')) : trim((string)$search);

        $order = $this->request->getGet('order');
        $orderColIdx = isset($order[0]['column']) ? (int)$order[0]['column'] : 0;
        $orderDir    = isset($order[0]['dir']) ? strtoupper($order[0]['dir']) : 'DESC';

        $columnMap = [
            0 => 'kas_keluar.idkas_keluar',
            1 => 'kas_keluar.tanggal',
            2 => 'unit.NAMA_UNIT',
            3 => 'no_akun.no_akun',
            4 => 'kategori_kas.kategori',
            5 => 'kas_keluar.deskripsi',
            6 => 'bank.nama_bank',
            7 => 'kas_keluar.penerima',
            8 => 'bank.norek',
            9 => 'kas_keluar.jumlah',
            10 => 'kas_keluar.jenis',
            11 => null, // aksi — tidak urutkan
        ];
        $orderCol = $columnMap[$orderColIdx] ?? 'kas_keluar.idkas_keluar';

        $startDate = trim((string)$this->request->getGet('tanggal_awal'));
        $endDate   = trim((string)$this->request->getGet('tanggal_akhir'));
        $unitId    = (int)$this->request->getGet('unit_id');

        $total    = $this->KasKeluarModel->countAllKasKeluar();
        $filtered = $this->KasKeluarModel->countKasKeluarFiltered($search, $startDate ?: null, $endDate ?: null, $unitId ?: null);
        $rows     = $this->KasKeluarModel->getKasKeluarDataTable(
            $length,
            $start,
            $search,
            $orderCol,
            $orderDir,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null
        );

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'         => (int)$r->idkas_keluar,
                'tanggal'    => date('d-m-Y', strtotime($r->tanggal)),
                'unit'       => $r->NAMA_UNIT,
                'no_akun'    => esc($r->no_akun ?? '', 'attr') . ($r->nama_akun ? ' <small class="text-muted">' . esc($r->nama_akun) . '</small>' : ''),
                'kategori'   => esc($r->kategori ?? ''),
                'deskripsi'  => esc($r->deskripsi ?? ''),
                'bank'       => esc($r->nama_bank ?? '-'),
                'penerima'   => esc($r->penerima ?? '-'),
                'norek'      => esc($r->norek ?? '-'),
                'jumlah'     => (float)$r->jumlah,
                'jenis'      => ucfirst(esc($r->jenis ?? '-')),
                'aksi'       => '<div class="d-flex justify-content-center gap-1">'
                    . '<button type="button" class="btn btn-sm btn-outline-primary edit-button" title="Edit" '
                    . 'data-id="' . (int)$r->idkas_keluar . '" '
                    . 'data-tanggal="' . esc($r->tanggal, 'attr') . '" '
                    . 'data-kategori="' . (int)($r->kategori_idkategori ?? 0) . '" '
                    . 'data-deskripsi="' . esc($r->deskripsi ?? '', 'attr') . '" '
                    . 'data-jumlah="' . (float)$r->jumlah . '" '
                    . 'data-jenis="' . esc($r->jenis ?? '', 'attr') . '" '
                    . 'data-idbank="' . (int)($r->idbank ?? 0) . '" '
                    . 'data-penerima="' . esc($r->penerima ?? '', 'attr') . '" '
                    . 'data-bs-toggle="modal" data-bs-target="#edit-kas-modal">'
                    . '<i class="bi bi-pencil-square"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-outline-danger delete-button" title="Hapus" '
                    . 'data-id="' . (int)$r->idkas_keluar . '" '
                    . 'data-bs-toggle="modal" data-bs-target="#delete-kas-modal">'
                    . '<i class="bi bi-trash"></i></button></div>',
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    public function insert_kas_keluar()
    {
        $tanggal = $this->request->getPost('tanggal');
        $deskripsi = $this->request->getPost('deskripsi');
        $idunit = $this->request->getPost('unit_idunit');

        $akunData = $this->request->getPost('akun');

        // Prospek harus punya minimal satu baris posisi akun.
        if (!is_array($akunData) || empty($akunData)) {
            session()->setFlashdata('error', 'Minimal satu posisi akun wajib diisi.');
            return redirect()->to(base_url('/kas_keluar'));
        }

        foreach ($akunData as $data) {
            $noAkun = $data['no_akun'] ?? '';
            $jenisAkun = $data['jenis_akun'] ?? '';
            $noRekening = isset($data['no_rekening']) ? $data['no_rekening'] : null;
            if (empty($noRekening)) {
                $noRekening = null;
            }
            $jumlah = (float)($data['jumlah'] ?? 0);
            $penerima = (string)($data['penerima'] ?? '');
            $jenis = (string)($data['posisi_drk'] ?? 'debet'); // debet / kredit
            $kategori_idkategori = (int)($data['kategori_idkategori'] ?? 0);

            // Simpan data kas keluar
            $dataKasKeluar = [
                'tanggal' => $tanggal,
                'no_akun' => $noAkun,
                'kategori_idkategori' => $kategori_idkategori,
                'deskripsi' => $deskripsi,
                'jumlah' => $jumlah,
                'jenis' => $jenis,
                'penerima' => $penerima,
                'idbank' => $noRekening,
                'idunit' => $idunit,
                'created_on' => date('Y-m-d H:i:s')
            ];

            $this->KasKeluarModel->insert_KasKeluar($dataKasKeluar);

            // Ambil ID kas keluar yang baru saja diinsert
            $insertId = $this->KasKeluarModel->insertID();

            // Posting ringkas ke ledger kas/bank (best effort, idempotent).
            if ($insertId) {
                try {
                    $this->KasBankLib->postingKasKeluar((int)$insertId);
                } catch (\Throwable $e) {
                    log_message('error', 'KasBank: gagal posting kas_keluar #' . $insertId . ': ' . $e->getMessage());
                }
            }

            // Ambil data akun untuk jurnal
            $data_akunjurnal = $this->NoAkunModel->getByNoAkun($noAkun);
            $nama_akun = $data_akunjurnal ? trim((string)$data_akunjurnal->nama_akun) : $noAkun;

            // Tentukan nilai debet dan kredit berdasarkan posisi_drk
            $debet = ($jenis === 'debet') ? $jumlah : 0;
            $kredit = ($jenis === 'kredit') ? $jumlah : 0;

            // Data jurnal
            $datajurnal = [
                'tanggal' => $tanggal,
                'no_akun' => $noAkun,
                'nama_akun' => $nama_akun,
                'debet' => $debet,
                'kredit' => $kredit,
                'keterangan' => $deskripsi,
                'id_referensi' => $insertId,
                'tabel_referensi' => 'kas_keluar',
                'id_unit' => session('ID_UNIT'),
                'id_akun' => session('ID_AKUN')
            ];

            $this->JurnalModel->insert_biasah($datajurnal);
        }

        session()->setFlashdata('sukses', 'Data kas keluar berhasil disimpan.');
        return redirect()->to(base_url('/kas_keluar'));
    }



    public function update_kas_keluar()
    {
        $id = $this->request->getPost('idkas_keluar');
        $tanggal = $this->request->getPost('tanggal');
        $deskripsi = $this->request->getPost('deskripsi');
        $kategori_idkategori = $this->request->getPost('kategori_idkategori');
        $jumlah = $this->request->getPost('jumlah');
        $penerima = $this->request->getPost('penerima'); //idbank

        $databank = $this->BankModel->getById($penerima);
        $atasnama = $databank->atas_nama;
        $posisi_drk = $this->request->getPost('posisi_drk');



        $data = [
            'tanggal' =>  $tanggal,
            'kategori_idkategori' =>  $kategori_idkategori,
            'deskripsi' => $deskripsi,
            'jumlah' =>   $jumlah,
            'jenis' => $posisi_drk,
            'penerima' => $atasnama,
            'idbank' => $penerima,
            'updated_on' => date('Y-m-d H:i:s')
        ];

        $this->KasKeluarModel->update($id, $data);

        // Restore posting ledger kas/bank: hapus posting lama, posting ulang (idempotent).
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $this->KasBankLib->hapusPosting('kas_keluar', (int)$id);
            $this->KasBankLib->postingKasKeluar((int)$id);
        } catch (\Throwable $e) {
            log_message('error', 'KasBank: gagal restore posting kas_keluar #' . $id . ': ' . $e->getMessage());
        }
        $db->transComplete();

        session()->setFlashdata('sukses', 'Data kas keluar berhasil diupdate.');
        return redirect()->to(base_url('/kas_keluar'));
    }

    public function delete_kas_keluar()
    {
        $id = $this->request->getPost('idkas_keluar');

        $db = \Config\Database::connect();
        $db->transStart();
        $this->KasBankLib->hapusPosting('kas_keluar', (int)$id);
        $this->KasKeluarModel->delete($id);
        $db->transComplete();

        session()->setFlashdata('sukses', 'Data kas keluar berhasil dihapus.');
        return redirect()->to(base_url('/kas_keluar'));
    }

    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $unit = $this->request->getPost('nama_unit');
        $unitId = (int)$this->request->getPost('unit_id');
        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');


        $kasKeluarData = $this->KasKeluarModel->getKasKeluarFiltered($tanggal_awal, $tanggal_akhir, $unit, $unitId ?: null);


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
        foreach ($kasKeluarData as $item) {
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
