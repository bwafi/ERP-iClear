<?php

namespace App\Services\Payroll;

use App\Models\ModelFinancePayroll;
use App\Services\Kpi\KpiCalculationService;

/**
 * Susun draft payroll gaji dari master salary_structures.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * Finance mengisi nominal gaji satu per satu lewat form "Input Payroll
 * Gaji", padahal nominal itu sebenarnya sudah bisa dihitung: master
 * salary_structures menyimpan gaji pokok dan tunjangan per jabatan/unit,
 * dan tunjangan kinerja/absen mengikuti skor KPI bulan itu. Digitasi ulang
 * tiap bulan bukan hanya lambat, tapi juga rawan salah ketik angka yang
 * tidak pernah dicek orang lain.
 *
 * ALUR YANG DIGANTI
 * -----------------
 * Finance memilih bulan, sistem menghitung tiap karyawan dari master yang
 * sudah ada, lalu membuat baris register berstatus 'rencana'. Finance tetap
 * memegang kendali penuh: nominal boleh dikoreksi, dan baris baru dianggap
 * lunas hanya setelah dia menekan "Sudah Dibayar" seperti sebelumnya.
 *
 * Yang SENGAJA tidak dilakukan:
 *   - tidak menandai lunas otomatis; status tetap 'rencana'
 *   - tidak memotong kasbon di sini. Potongan kasbon tetap dipotong saat
 *     Finance menekan "Sudah Dibayar" lewat
 *     HutangPiutangService::settleKasbonFromPayroll(), supaya tidak ada
 *     kasbon yang terpotong dua kali.
 *   - menimpa baris 'rencana' yang sudah ada bila opsi overwrite aktif,
 *     supaya hasil hitung ulang dari KPI terbaru bisa dipakai tanpa membuat
 *     baris ganda. Baris 'dibayar' tidak disentuh karena paid_date dan
 *     potongan kasbonnya sudah terpakai.
 *   - tidak membuat baris untuk karyawan yang structure gaji-nya kosong;
 *     mereka dilaporkan sebagai dilewati supaya bisa ditangani manual.
 *   - tidak membuat baris untuk akun yang tidak menerima gaji
 *     (kolom akun.TERIMA_GAJI = 0, mis. Admin Root dan Direktur). Saat
 *     overwrite, draft 'rencana' lama milik akun itu ikut dihapus supaya
 *     tidak tertinggal sebagai baris "auto" yang sudah tidak relevan;
 *     baris 'dibayar' tetap disimpan.
 *
 * CATATAN SOAL LEMBUR
 * -------------------
 * LEMBUR ikut dijumlahkan karena masuk ke slip gaji (lihat
 * PenilaianKPI::slip_gaji()) dan sudah tercatat di kas_keluar kategori 10.
 * Baris kas_keluar LEMBUR tidak ikut dibayar lewat payroll, jadi tidak
 * ada dobel bayar.
 */
class PayrollGenerator
{
    /** Kategori kas_keluar yang dipakai gaji/lembur/kasbon. */
    private const KATEGORI_SDM = 10;

    /** Kunci tunjangan pada master salary_components. */
    private const KOMPONEN_KINERJA = 'TUNJANGAN_KINERJA';
    private const KOMPONEN_ABSEN = 'TUNJANGAN_ABSEN';

    protected $db;

    protected $payrollModel;

    protected $kpiService;

    protected $salaryService;

    public function __construct()
    {
        $this->db            = \Config\Database::connect();
        $this->payrollModel  = new ModelFinancePayroll();
        $this->kpiService    = new KpiCalculationService();
        $this->salaryService = new SalaryCalculationService();
    }

    /**
     * Buat baris payroll 'rencana' untuk seluruh karyawan pada satu bulan.
     *
     * @param string $bulan   'YYYY-MM'
     * @param array  $options due_date (default akhir bulan), unit_ids (filter),
     *                        overwrite (timpa baris 'rencana' yang sudah ada)
     *
     * @return array ringkasan: created, skipped, errors, rows, total_nominal
     */
    public function generate(string $bulan, array $options = []): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
        }

        $tahun = (int) substr($bulan, 0, 4);
        $bln   = (int) substr($bulan, 5, 2);
        $from  = $bulan . '-01';
        $to    = date('Y-m-t', strtotime($from));

        // Hari gajian tidak seragam di data nyata (tanggal 1, 24, 25, 28, 30,
        // 31 sama-sama dipakai), jadi due_date bisa Finance tentukan sendiri
        // dan default-nya jatuh di akhir bulan.
        $dueDate = (string) ($options['due_date'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = $to;
        }

        $unitIds = array_values(array_filter(array_map('intval', (array) ($options['unit_ids'] ?? []))));
        $overwrite = (bool) ($options['overwrite'] ?? false);
        $sudah  = $this->daftarSudahAda($from, $to);
        $lembur = $this->daftarLembur($from, $to);

        $hasil = [
            'bulan'           => $bulan,
            'due_date'        => $dueDate,
            'created'         => 0,
            'updated'         => 0,
            'skipped'         => 0,
            'dikecualikan'    => 0,
            'dihapus'         => 0,
            'total_nominal'   => 0,
            'updated_nominal' => 0,
            'errors'          => [],
            'rows'            => [],
        ];

        foreach ($this->daftarKaryawan($unitIds) as $karyawan) {
            $id = (int) $karyawan['ID_AKUN'];

            // Akun yang tidak menerima gaji (TERIMA_GAJI = 0, mis. Admin Root &
            // Direktur) dilewati lebih dulu, sebelum cek "sudah ada", supaya
            // alasannya tetap "dikecualikan" dan tidak tercampur dengan baris
            // yang sudah pernah dibuat.
            if ((int) ($karyawan['TERIMA_GAJI'] ?? 1) === 0) {
                $hasil['dikecualikan']++;

                // Draft lama dari sebelum akun ditandai tidak digaji tidak
                // boleh tertinggal; hanya 'rencana' yang dihapus, riwayat
                // 'dibayar' tetap utuh.
                if ($overwrite) {
                    $this->db->table('finance_payroll')
                        ->where('pegawai_id', $id)
                        ->where('due_date >=', $from)
                        ->where('due_date <=', $to)
                        ->where('status', 'rencana')
                        ->delete();
                    $hasil['dihapus'] += $this->db->affectedRows();
                }

                $hasil['rows'][] = [
                    'nama'   => (string) $karyawan['NAMA_AKUN'],
                    'unit'   => (int) $karyawan['ID_UNIT'],
                    'total'  => null,
                    'status' => 'excluded',
                    'alasan' => 'tidak menerima gaji',
                ];
                continue;
            }

            $existing = $sudah[$id] ?? null;

            if ($existing !== null && ! $overwrite) {
                $hasil['skipped']++;
                $hasil['rows'][] = [
                    'nama'  => (string) $karyawan['NAMA_AKUN'],
                    'unit'  => (int) $karyawan['ID_UNIT'],
                    'total' => null,
                    'status' => 'skip',
                    'alasan' => 'sudah ada baris payroll bulan ini',
                ];
                continue;
            }

            // Uang yang sudah dibayar tidak boleh dihitung ulang diam-diam:
            // paid_date dan potongan kasbon sudah terpakai.
            if ($existing !== null && ($existing['status'] ?? '') !== 'rencana') {
                $hasil['skipped']++;
                $hasil['rows'][] = [
                    'nama'  => (string) $karyawan['NAMA_AKUN'],
                    'unit'  => (int) $karyawan['ID_UNIT'],
                    'total' => null,
                    'status' => 'skip',
                    'alasan' => 'sudah dibayar',
                ];
                continue;
            }

            // Hanya baris payroll yang menunjuk ID_AKUN. Baris /kas_keluar
            // mengisi `penerima` dengan teks nama, jadi tidak ikut dihitung.
            $lemburOrg = $lembur[$id] ?? 0.0;

            try {
                $gaji = $this->hitungGaji($id, $karyawan, $bln, (string) $tahun, $lemburOrg);
            } catch (\Throwable $e) {
                log_message('error', 'PayrollGenerator: hitung gaji #' . $id . ' gagal: ' . $e->getMessage());

                $hasil['errors'][] = [
                    'nama'  => (string) $karyawan['NAMA_AKUN'],
                    'pesan' => $e->getMessage(),
                ];
                $hasil['rows'][] = [
                    'nama'   => (string) $karyawan['NAMA_AKUN'],
                    'unit'   => (int) $karyawan['ID_UNIT'],
                    'total'  => null,
                    'status' => 'error',
                    'alasan' => $e->getMessage(),
                ];
                continue;
            }

            $total = (int) round((float) $gaji['total_gaji']);
            $notes = $this->susunCatatan($gaji, $lemburOrg);

            // Model::insert()/update() mengembalikan false tanpa melempar
            // error kalau kueri ditolak, jadi hasilnya wajib diperiksa.
            // Kegagalan yang diam-diam di sini akan terlihat sebagai
            // "sudah ada baris" pada bulan depan, padahal karyawannya belum
            // pernah dapat payroll.
            if ($existing !== null) {
                $tersimpan = $this->payrollModel->update($existing['id'], [
                    'due_date' => $dueDate,
                    'total'    => $total,
                    'notes'    => $notes,
                ]);
                $status = 'updated';
                $aksi   = 'memperbarui';
            } else {
                $tersimpan = $this->payrollModel->insert([
                    'unit_id'    => (int) $karyawan['ID_UNIT'],
                    'pegawai_id' => $id,
                    'due_date'   => $dueDate,
                    'paid_date'  => null,
                    'status'     => 'rencana',
                    'total'      => $total,
                    'notes'      => $notes,
                    'sumber'     => 'auto',
                    'created_by' => (int) session('ID_AKUN'),
                ]);
                $status = 'created';
                $aksi   = 'menyimpan';
            }

            if (! $tersimpan) {
                $pesan = $this->db->error()['message'] ?: 'gagal ' . $aksi . ' ke database';

                log_message('error', 'PayrollGenerator: ' . $aksi . ' payroll #' . $id . ' gagal: ' . $pesan);

                $hasil['errors'][] = [
                    'nama'  => (string) $karyawan['NAMA_AKUN'],
                    'pesan' => 'gagal ' . $aksi . ': ' . $pesan,
                ];
                $hasil['rows'][] = [
                    'nama'   => (string) $karyawan['NAMA_AKUN'],
                    'unit'   => (int) $karyawan['ID_UNIT'],
                    'total'  => null,
                    'status' => 'error',
                    'alasan' => $pesan,
                ];
                continue;
            }

            if ($status === 'updated') {
                $hasil['updated']++;
                $hasil['updated_nominal'] += $total;
            } else {
                $hasil['created']++;
                $hasil['total_nominal'] += $total;
            }

            $hasil['rows'][] = [
                'nama'   => (string) $karyawan['NAMA_AKUN'],
                'unit'   => (int) $karyawan['ID_UNIT'],
                'total'  => $total,
                'status' => $status,
                'alasan' => '',
            ];
        }

        if ($hasil['created'] > 0 || $hasil['updated'] > 0) {
            HoSalaryDistributionService::invalidateCache($bulan);
        }

        return $hasil;
    }

    /**
     * Gaji per karyawan satu unit untuk satu bulan, TANPA menulis payroll.
     * Dipakai laporan /asset_berjalan supaya angkanya sama dengan payroll.
     *
     * @return array<int, array{
     *   id_akun:int, nama:string, jabatan_id:int,
     *   gaji_pokok:float, tunjangan_kinerja:float, tunjangan_absen:float,
     *   tunjangan_penempatan:float, insentif:float, lembur:float, total:float
     * }>
     */
    public function hitungGajiUnit(string $bulan, int $unitId): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
        }

        $tahun = (int) substr($bulan, 0, 4);
        $bln   = (int) substr($bulan, 5, 2);
        $from  = $bulan . '-01';
        $to    = date('Y-m-t', strtotime($from));
        $lembur = $this->daftarLembur($from, $to);

        $hasil = [];

        foreach ($this->daftarKaryawan([$unitId]) as $karyawan) {
            if ((int) ($karyawan['TERIMA_GAJI'] ?? 1) === 0) {
                continue;
            }

            $id = (int) $karyawan['ID_AKUN'];

            try {
                $gaji = $this->hitungGaji($id, $karyawan, $bln, (string) $tahun, $lembur[$id] ?? 0.0);
            } catch (\Throwable $e) {
                log_message('error', 'PayrollGenerator: hitung gaji #' . $id . ' gagal: ' . $e->getMessage());
                continue;
            }

            // amount per component_code; kode yang tidak ada dianggap 0.
            $komponen = array_column($gaji['components'], 'amount', 'component_code');

            $hasil[] = [
                'id_akun'              => $id,
                'nama'                 => (string) $karyawan['NAMA_AKUN'],
                'jabatan_id'           => (int) ($karyawan['ID_JABATAN'] ?? 0),
                'gaji_pokok'           => (float) ($komponen['GAJI_POKOK'] ?? 0),
                'tunjangan_kinerja'    => (float) ($komponen[self::KOMPONEN_KINERJA] ?? 0),
                'tunjangan_absen'      => (float) ($komponen[self::KOMPONEN_ABSEN] ?? 0),
                'tunjangan_penempatan' => (float) $gaji['placement_allowance'],
                'insentif'             => (float) $gaji['incentive'],
                'lembur'               => (float) $gaji['lembur'],
                'total'                => (float) $gaji['total_gaji'],
            ];
        }

        return $hasil;
    }

    /**
     * Hitung gaji satu karyawan untuk satu bulan.
     *
     * Wiring-nya sama persis dengan slip gaji (PenilaianKPI::slip_gaji()):
     * skor KPI mengisi tunjangan kinerja & absen, tunjangan penempatan
     * mengikuti query akun, dan insentif ikut dari KPI. Kasbon sengaja 0.0
     * karena dipotong saat pembayaran, bukan saat penyusunan.
     */
    protected function hitungGaji(int $id, array $karyawan, int $bln, string $tahun, float $lembur): array
    {
        $periodeDate = $tahun . '-' . str_pad((string) $bln, 2, '0', STR_PAD_LEFT) . '-15';

        $kpi = $this->kpiService->calculateForSalary(
            $id,
            (string) $bln,
            $tahun,
            'gaji',
            $periodeDate,
            $periodeDate
        );

        // Jabatan & unit diambil dari data karyawan, bukan dari return KPI,
        // supaya employee tanpa KPI sama sekali tetap punya struktur gaji.
        // Penting: ID_JABATAN 0 itu jabatan sah (Finance), jadi yang diperiksa
        // adalah kolom kosongnya, bukan angkanya.
        $idJabatan = trim((string) ($karyawan['ID_JABATAN'] ?? ''));
        $idUnit    = trim((string) ($karyawan['ID_UNIT'] ?? ''));

        if ($idJabatan === '') {
            throw new \RuntimeException('Jabatan karyawan belum diisi.');
        }

        if ($idUnit === '' || (int) $idUnit <= 0) {
            throw new \RuntimeException('Unit karyawan belum diisi.');
        }

        $positionId = (int) $idJabatan;
        $unitId     = (int) $idUnit;

        $akunKpi = $kpi['akun'] ?? null;
        $placement = $akunKpi !== null ? (float) ($akunKpi->tunjangan_penempatan ?? 0) : 0.0;

        return $this->salaryService->calculateSalary(
            $id,
            $positionId,
            $unitId,
            'gaji',
            [
                self::KOMPONEN_KINERJA => $kpi['skor_total'] ?? null,
                self::KOMPONEN_ABSEN   => $kpi['skor_total2'] ?? null,
            ],
            $placement,
            (float) ($kpi['insentif'] ?? 0),
            $lembur,
            0.0,
            $periodeDate
        );
    }

    /** Catatan yang menjelaskan asal angkanya, supaya Finance bisa mengcek. */
    protected function susunCatatan(array $gaji, float $lembur): string
    {
        $bagian = [];

        foreach ($gaji['components'] as $component) {
            $amount = (float) ($component['amount'] ?? 0);
            if ($amount == 0.0) {
                continue;
            }
            $bagian[] = $component['component_name'] . ' ' . $this->rupiah($amount);
        }

        $placement = (float) ($gaji['placement_allowance'] ?? 0);
        if ($placement != 0.0) {
            $bagian[] = 'Penempatan ' . $this->rupiah($placement);
        }

        $insentif = (float) ($gaji['incentive'] ?? 0);
        if ($insentif != 0.0) {
            $bagian[] = 'Insentif ' . $this->rupiah($insentif);
        }

        if ($lembur != 0.0) {
            $bagian[] = 'Lembur ' . $this->rupiah($lembur);
        }

        return 'Otomatis dari salary_structures: ' . implode(' + ', $bagian)
            . '. Kasbon dipotong saat dibayar.';
    }

    /** Karyawan aktif, opsional dibatasi unit tertentu. */
    protected function daftarKaryawan(array $unitIds): array
    {
        $builder = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, ID_UNIT, ID_JABATAN, TERIMA_GAJI')
            ->where('STATUS_PEGAWAI', '1')
            ->groupStart()
            ->where('deleted', null)
            ->orWhere('deleted', '')
            ->groupEnd();

        if ($unitIds !== []) {
            $builder->whereIn('ID_UNIT', $unitIds);
        }

        return $builder->orderBy('ID_UNIT', 'ASC')
            ->orderBy('NAMA_AKUN', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Karyawan yang sudah punya baris payroll di rentang bulan ini.
     *
     * Yang dicari per karyawan, bukan per unit: satu karyawan bisa punya
     * lebih dari satu pembayaran dalam sebulan (mis. gaji pokok dibayar
     * terpisah dari tunjangan), jadi seluruh barisnya dihitung sebagai
     * "sudah ada".
     */
    protected function daftarSudahAda(string $from, string $to): array
    {
        $ada = [];

        // Satu karyawan bisa punya lebih dari satu baris dalam sebulan (mis.
        // gaji pokok dibayar terpisah dari tunjangan). Yang ditimpa hanya
        // baris pertama; baris lain dibiarkan.
        $rows = $this->db->table('finance_payroll')
            ->select('id, pegawai_id, status, sumber, due_date, total')
            ->where('due_date >=', $from)
            ->where('due_date <=', $to)
            ->where('pegawai_id IS NOT', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $id = (int) $row['pegawai_id'];
            if (! isset($ada[$id])) {
                $ada[$id] = $row;
            }
        }

        return $ada;
    }

    /**
     * Total lembur per karyawan dari kas_keluar bulan ini.
     *
     * `kas_keluar.penerima` tidak seragam: baris dari /payroll mengisi
     * akun.ID_AKUN, sedangkan baris dari /kas_keluar mengisi teks nama
     * (mis. "ICLEAR"). Karena itu hanya isian angka yang dipakai sebagai
     * ID_AKUN; sisanya bukan karyawan dan diabaikan.
     */
    protected function daftarLembur(string $from, string $to): array
    {
        $hasil = [];

        $rows = $this->db->table('kas_keluar')
            ->select('penerima, SUM(jumlah) AS total')
            ->where('kategori_idkategori', self::KATEGORI_SDM)
            ->where('tanggal >=', $from)
            ->where('tanggal <=', $to)
            ->like('deskripsi', 'lembur')
            ->groupBy('penerima')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $id = trim((string) ($row['penerima'] ?? ''));
            if ($id === '' || ! ctype_digit($id)) {
                continue;
            }
            $hasil[(int) $id] = ($hasil[(int) $id] ?? 0.0) + (float) $row['total'];
        }

        return $hasil;
    }

    protected function rupiah(float $nilai): string
    {
        return 'Rp ' . number_format($nilai, 0, ',', '.');
    }
}