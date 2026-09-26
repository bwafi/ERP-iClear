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
            3 => 'kategori_kas.kategori',
            4 => 'no_akun.no_akun',
            5 => 'kas_keluar.deskripsi',
            6 => 'kas_keluar.penerima',
            7 => 'kas_keluar.jumlah',
            8 => 'kas_keluar.jenis',
            9 => null, // aksi — tidak urutkan
        ];
        $orderCol = $columnMap[$orderColIdx] ?? 'kas_keluar.tanggal';

        $startDate = trim((string)$this->request->getGet('tanggal_awal'));
        $endDate   = trim((string)$this->request->getGet('tanggal_akhir'));
        $unitId    = (int)$this->request->getGet('unit_id');

        // Kueri yang isinya hanya angka dibaca sebagai pencarian ID: dicocokkan
        // persis ke primary key, bukan sebagai substring. "3" sebagai substring
        // mengembalikan ratusan baris yang tidak pernah dimaksud; "3" sebagai ID
        // selesai seketika lewat indeks. Pencarian teks tetap memakai OR LIKE
        // seperti sebelumnya, dan pengguna bisa memaksa mode teks lewat
        // search_mode=text.
        $searchMode = (string)$this->request->getGet('search_mode');
        $idExact    = ($search !== '' && $searchMode !== 'text' && ctype_digit($search));

        $total    = $this->KasKeluarModel->countAllKasKeluar();
        $filtered = $this->KasKeluarModel->countKasKeluarFiltered(
            $search,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $idExact
        );
        $sumTotal = $this->KasKeluarModel->sumKasKeluarFiltered(
            $search,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $idExact
        );
        $rows     = $this->KasKeluarModel->getKasKeluarDataTable(
            $length,
            $start,
            $search,
            $orderCol,
            $orderDir,
            $startDate ?: null,
            $endDate ?: null,
            $unitId ?: null,
            $idExact
        );

        // Resolusi ID dijawab terpisah dari isi tabel, supaya "ID ada tapi di
        // luar filter aktif" tetap bisa disampaikan alih-alih diam-diam
        // menampilkan nol baris.
        $idHit = null;
        if ($idExact) {
            $hit = $this->KasKeluarModel->findKasKeluarById((int)$search);
            if ($hit) {
                $idHit = [
                    'id'       => (int)$hit->idkas_keluar,
                    'tanggal'  => $hit->tanggal,
                    'deskripsi' => (string)($hit->deskripsi ?? ''),
                    'jumlah'   => (float)$hit->jumlah,
                    'unit'     => (string)($hit->NAMA_UNIT ?? ''),
                    'inScope'  => $this->KasKeluarModel->isKasKeluarInScope(
                        (int)$search,
                        $startDate ?: null,
                        $endDate ?: null,
                        $unitId ?: null
                    ),
                ];
            }
        }

        // Nilai dikembalikan terpisah, tanpa HTML: sel disusun di tampilan agar
        // escaping dan pemotongan teks punya satu tempat yang jelas.
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'        => (int)$r->idkas_keluar,
                'tanggal'   => (string)$r->tanggal,
                'unit'      => (string)($r->NAMA_UNIT ?? ''),
                'no_akun'   => (string)($r->no_akun ?? ''),
                'nama_akun' => trim((string)($r->nama_akun ?? '')),
                'kategori'  => (string)($r->kategori ?? ''),
                'kategori_id' => (int)($r->kategori_idkategori ?? 0),
                'idbank'    => ($r->idbank === null || $r->idbank === '') ? 0 : (int)$r->idbank,
                'deskripsi' => (string)($r->deskripsi ?? ''),
                'nama_bank' => (string)($r->nama_bank ?? ''),
                'norek'     => (string)($r->norek ?? ''),
                'penerima'  => (string)($r->penerima ?? ''),
                'jumlah'    => (float)$r->jumlah,
                'jenis'     => trim((string)($r->jenis ?? '')),
                // Kolom Aksi tidak menyimpan apa pun, tapi DataTables server-side
                // menuntut setiap kolom yang dikonfigurasi punya kunci di payload.
                'aksi'      => null,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'sumTotal'        => $sumTotal,
            'isIdQuery'       => $idExact,
            'idHit'           => $idHit,
            'data'            => $data,
        ]);
    }

    public function insert_kas_keluar()
    {
        $tanggal = $this->request->getPost('tanggal');
        $deskripsi = $this->request->getPost('deskripsi');
        $idunit = (int) $this->request->getPost('unit_idunit');

        // Unit terkunci mengikuti akun login di form. Kalau select ter-disable,
        // browser tetap mengirimnya, tapi form yang sudah pernah di-reset bisa
        // mengirim nilai kosong — jadi unit login dipakai sebagai cadangan.
        if ($idunit <= 0) {
            $idunit = (int) session('ID_UNIT');
        }
        if ($idunit <= 0) {
            session()->setFlashdata('error', 'Unit wajib diisi.');
            return redirect()->to(base_url('/kas_keluar'));
        }

        $akunData = $this->request->getPost('akun');

        // Prospek harus punya minimal satu baris akun.
        if (!is_array($akunData) || empty($akunData)) {
            session()->setFlashdata('error', 'Minimal satu baris akun wajib diisi.');
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

        // Source update + refresh posting ledger dalam SATU transaksi:
        // hapus posting lama, posting ulang (idempotent). Jika posting gagal,
        // update sumber ikut di-rollback.
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $this->KasKeluarModel->update($id, $data);
            $this->KasBankLib->hapusPosting('kas_keluar', (int)$id);
            $this->KasBankLib->postingKasKeluar((int)$id);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal update kas_keluar #' . $id . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal mengupdate kas keluar.');
            return redirect()->to(base_url('/kas_keluar'));
        }

        if ($db->transStatus() === false) {
            session()->setFlashdata('gagal', 'Gagal mengupdate kas keluar.');
            return redirect()->to(base_url('/kas_keluar'));
        }

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
