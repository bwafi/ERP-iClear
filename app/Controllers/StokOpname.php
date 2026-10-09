<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Models\ModelUnit;
use App\Services\StokOpnameService;

/**
 * Stok Opname v2.
 *
 * Perubahan dari versi lama:
 *   - Tidak lagi memaksa tanggal = hari ini. Satu periode DRAFT boleh
 *     dilanjutkan lintas hari; tanggal yang dibuka selalu periode DRAFT milik
 *     operator itu sendiri.
 *   - Aksi mutasi tidak lagi selalu terkunci ke unit milik operator. Operator
 *     biasa tetap terkunci ke `session('ID_UNIT')` dan `unit` dari POST
 *     diabaikan, jadi tidak ada jalur untuk mengopname unit lain. Admin Root
 *     boleh memilih unit, karena `unit` jadi input dari request nilainya
 *     divalidasi terhadap daftar unit yang ada.
 *   - loadTable() yang dulu terbuka publik sudah dihapus.
 */
class StokOpname extends BaseController
{
    /**
     * Role yang HANYA boleh melihat — tidak boleh menjalankan mutasi apa pun.
     *
     * Admin Root (1) sengaja TIDAK ada di sini: ia tetap boleh memantau semua
     * unit sekaligus menjalankan opname bila perlu (mis. menutup unit yang
     * operatornya tidak bisa melakukan hitung fisik). Role lain di daftar ini
     * tetap murni read-only.
     */
    private const VIEW_ONLY_ROLES = [0, 2, 34, 40];

    /** Role yang boleh memilih unit di layar (termasuk Admin Root). */
    private const CROSS_UNIT_ROLES = [0, 1, 2, 34];

    /** Role yang boleh memakai fitur pemantauan, mis. filter selisih. */
    private const MONITOR_ROLES = [0, 1, 2, 34, 40];

    /** Role yang boleh melakukan reopen stok opname (koreksi hasil final). */
    private const REOPEN_ROLES = [1];

    /** Target KPI stok opname per bulan. */
    private const KPI_TARGET = 4;

    protected $AuthModel;
    protected $UnitModel;
    protected $PeriodeModel;
    protected $svc;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->PeriodeModel = new \App\Models\ModelStokOpnamePeriode();
        $this->svc = new StokOpnameService();
    }

    public function index()
    {
        $myJabatan = (int) session('ID_JABATAN');
        $myUnit = (int) session('ID_UNIT');
        $isCrossUnit = in_array($myJabatan, self::CROSS_UNIT_ROLES, true);
        $canMutate = ! in_array($myJabatan, self::VIEW_ONLY_ROLES, true);

        $unit = $isCrossUnit ? $this->requestedUnit($myUnit) : $myUnit;
        if ($unit <= 0) {
            $unitList = $this->UnitModel->getUnit();
            $unit = ! empty($unitList) ? (int) ($unitList[0]->idunit ?? 1) : 1;
        }

        // Untuk operator: buka DRAFT yang masih menggantung, bukan selalu hari ini.
        // Inilah yang membuat DRAFT bisa dilanjutkan ke hari berikutnya.
        $draftTerbuka = $canMutate ? $this->svc->draftTerbuka($unit) : null;

        $tanggal = $this->cleanDate($this->request->getGet('tanggal'));
        if ($tanggal === null) {
            $tanggal = $draftTerbuka !== null ? (string) $draftTerbuka->tanggal : date('Y-m-d');
        }

        $periode = $this->svc->periode($unit, $tanggal);
        $audit   = $this->auditTrail($unit, $tanggal);

        // Ambil data stokopname untuk modal filter selisih
        $stokopname = (new \App\Models\ModelStokOpname())->getStokOpnameAll();
        $stokopname_grouped = [];
        foreach ($stokopname as $row) {
            if((int)$row->unit_idunit === (int)$unit) {
                $stokopname_grouped[$row->tanggal][] = $row;
            }
        }

        return view('template', [
            'akun'             => $this->AuthModel->getById(session('ID_AKUN')),
            'unitList'         => $this->UnitModel->getUnit(),
            'unit'             => $unit,
            'myUnit'           => $myUnit,
            'tanggal'          => $tanggal,
            'canPickUnit'      => $isCrossUnit,
            'canFilterSelisih' => in_array($myJabatan, self::MONITOR_ROLES, true),
            'canMutate'        => $canMutate,
            'canReopen'        => in_array($myJabatan, self::REOPEN_ROLES, true),
            'periode'          => $periode,
            'namaUnit'         => ($this->UnitModel->where('idunit', $unit)->first()->NAMA_UNIT ?? '-'),
            'items'            => $this->svc->periodeItems($unit, $tanggal),
            'historis'         => $this->PeriodeModel->getByUnit($unit, 20),
            'draftTerbuka'     => $draftTerbuka,
            'kpiBulanIni'      => $this->kpiBulan($unit),
            'auditTrail'       => $audit,
            'stokopname_grouped' => $stokopname_grouped,
            'akunNama'         => $this->akunNamaMap(array_merge(
                [(int) ($periode->mulai_by ?? 0), (int) ($periode->finalisasi_by ?? 0)],
                array_column($audit, 'actor_id')
            )),
            'body'             => 'stok/stok_opname',
        ]);
    }

    /**
     * Peta ID_AKUN => NAMA_AKUN untuk menampilkan nama (bukan "user #N")
     * pada chip pembekuan, baris finalisasi, dan jejak audit.
     */
    private function akunNamaMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $rows = $this->db()
            ->table('akun')
            ->select('ID_AKUN, NAMA_AKUN')
            ->whereIn('ID_AKUN', $ids)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['ID_AKUN']] = (string) $r['NAMA_AKUN'];
        }

        return $map;
    }

    /**
     * Mulai stok opname: buat periode DRAFT berisi barang berstok unit ini.
     */
    public function mulai()
    {
        if ($this->isViewOnly()) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Mode lihat: role ini tidak dapat memulai stok opname.');
        }

        $unit = $this->mutationUnit();
        if ($unit <= 0) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Unit tidak valid. Pilih unit dari daftar.');
        }

        // Mulai selalu untuk hari ini; ini bukan aksi melanjutkan. Kalau ada
        // DRAFT yang menggantung, createPeriode() yang menolaknya dengan pesan
        // yang jelas, bukan controller.
        $tanggal = $this->cleanDate($this->request->getPost('tanggal')) ?? date('Y-m-d');
        $url = base_url("stok_opname?unit={$unit}&tanggal={$tanggal}");

        $r = $this->svc->createPeriode($unit, $tanggal, (int) session('ID_AKUN'));
        if ($r['success']) {
            return redirect()->to($url)->with('sukses',
                'Stok opname dimulai sebagai DRAFT (' . $r['jumlah'] . ' barang berstok). '
                . 'Isi jumlah real secara bertahap, boleh dilanjutkan di hari berikutnya.');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    /**
     * Simpan DRAFT. Nilai kosong berarti dikosongkan (belum terisi), bukan error,
     * jadi operator bisa mengoreksi salah input tanpa jalur terpisah.
     *
     * aksi = 'finalisasi' menyimpan dulu lalu langsung mencoba finalisasi.
     */
    public function simpan()
    {
        if ($this->isViewOnly()) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Mode lihat: role ini tidak dapat menyimpan draft stok opname.');
        }

        $unit = $this->mutationUnit();
        if ($unit <= 0) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Unit tidak valid. Pilih unit dari daftar.');
        }

        $tanggal = $this->resolveMutationDate($unit);
        $aksi = (string) ($this->request->getPost('aksi') ?: 'simpan');
        $url = base_url("stok_opname?unit={$unit}&tanggal={$tanggal}");
        $userId = (int) session('ID_AKUN');

        $rows = (array) ($this->request->getPost('items') ?: []);
        $realRows = [];
        foreach ($rows as $bid => $v) {
            if (is_array($v) && array_key_exists('jumlah_real', $v)) {
                $realRows[(int) $bid] = $v['jumlah_real'];
            }
        }

        $r = $this->svc->saveDraft($unit, $tanggal, $realRows, $userId);
        if (! $r['success']) {
            return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
        }

        if ($aksi === 'finalisasi') {
            $rf = $this->svc->finalize($unit, $tanggal, $userId);
            if (! $rf['success']) {
                return redirect()->to($url)->with('gagal', implode(' ', $rf['errors']) . ' Draft tetap tersimpan.');
            }
            return redirect()->to($url)->with('sukses',
                'Stok opname berhasil disimpan & difinalisasi (FINAL). Tercatat di riwayat & KPI.');
        }

        $suffix = (int) ($r['summary']['terisi'] ?? 0) . '/' . (int) ($r['summary']['total'] ?? 0) . ' terisi';
        if (count($r['errors']) > 0) {
            return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
        }
        return redirect()->to($url)->with('sukses',
            'Draft stok opname tersimpan (' . (int) $r['saved'] . ' barang diperbarui, ' . $suffix . ').');
    }

    /**
     * Finalisasi: hanya boleh bila semua barang berstok sudah terisi.
     */
    public function finalisasi()
    {
        if ($this->isViewOnly()) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Mode lihat: role ini tidak dapat memfinalisasi stok opname.');
        }

        $unit = $this->mutationUnit();
        if ($unit <= 0) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Unit tidak valid. Pilih unit dari daftar.');
        }

        $tanggal = $this->resolveMutationDate($unit);
        $url = base_url("stok_opname?unit={$unit}&tanggal={$tanggal}");

        $r = $this->svc->finalize($unit, $tanggal, (int) session('ID_AKUN'));
        if ($r['success']) {
            return redirect()->to($url)->with('sukses',
                'Stok opname berhasil difinalisasi. Data tercatat di riwayat & KPI.');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    /**
     * Reopen: koreksi hasil final -> kembali DRAFT. Wajib menyertakan alasan.
     *
     * Baris final lama tidak dihapus, hanya ditandai tidak aktif, jadi nilai
     * sebelumnya tetap bisa ditelusuri.
     */
    public function reopen()
    {
        if ($this->isViewOnly()) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Mode lihat: role ini tidak dapat membuka kembali (koreksi) stok opname.');
        }
        if (! in_array((int) session('ID_JABATAN'), self::REOPEN_ROLES, true)) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Hanya Admin Root yang dapat membuka kembali (reopen) stok opname.');
        }

        $unit = $this->mutationUnit();
        if ($unit <= 0) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Unit tidak valid. Pilih unit dari daftar.');
        }

        $tanggal = $this->resolveMutationDate($unit);
        $url = base_url("stok_opname?unit={$unit}&tanggal={$tanggal}");

        $r = $this->svc->reopen($unit, $tanggal, (int) session('ID_AKUN'), (string) $this->request->getPost('alasan'));
        if ($r['success']) {
            return redirect()->to($url)->with('sukses',
                'Stok opname dibuka kembali (DRAFT). Nilai final sebelumnya ditandai tidak aktif dan tetap tersimpan di riwayat.');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

    /**
     * Role mode-lihat: tidak boleh menjalankan mutasi apa pun.
     */
    private function isViewOnly(): bool
    {
        return in_array((int) session('ID_JABATAN'), self::VIEW_ONLY_ROLES, true);
    }

    /**
     * Unit untuk aksi mutasi.
     *
     * Operator biasa: unit milik sendiri dari session. Nilai `unit` di POST
     * diabaikan sepenuhnya, jadi tidak ada jalur untuk mengopname unit lain.
     *
     * Admin Root: boleh memilih unit, karena ia lintas unit. Tapi karena `unit`
     * sekarang jadi input dari request, nilainya WAJIB divalidasi terhadap
     * daftar unit yang benar-benar ada — kalau tidak, `unit=999999` atau
     * `unit=-1` akan lolos ke service.
     *
     * @return int 0 = ditolak
     */
    private function mutationUnit(): int
    {
        if (! in_array((int) session('ID_JABATAN'), self::CROSS_UNIT_ROLES, true)) {
            return (int) session('ID_UNIT');
        }

        $posted = (int) $this->request->getPost('unit');
        return $posted > 0 && $this->isKnownUnit($posted) ? $posted : 0;
    }

    /**
     * Unit dari query string untuk halaman index, divalidasi sama seperti di
     * mutationUnit(). Kalau tidak valid, pakai fallback milik pemanggil.
     */
    private function requestedUnit(int $fallback): int
    {
        $requested = (int) $this->request->getGet('unit');

        if ($requested > 0 && $this->isKnownUnit($requested)) {
            return $requested;
        }

        return $fallback > 0 ? $fallback : 0;
    }

    private function isKnownUnit(int $unitId): bool
    {
        foreach ((array) $this->UnitModel->getUnit() as $u) {
            if ((int) ($u->idunit ?? 0) === $unitId) {
                return true;
            }
        }

        return false;
    }

    private function cleanDate($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * Tanggal untuk aksi mutasi (simpan / finalisasi / reopen).
     *
     * Kalau `tanggal` tidak terkirim — form kehilangan field, atau permintaan
     * dibuat langsung — jangan buru-buru memakai tanggal hari ini. Kalau unit
     * ini punya DRAFT yang menggantung, yang kita lanjutkan DRAFT itu; inilah
     * perilaku yang membuat opname bisa dilanjutkan lintas hari. Kalau memang
     * tidak ada DRAFT, baru jatuh ke hari ini.
     *
     * Without this, resuming a draft would silently target today's date, which
     * has no period, and the operator gets a confusing "not found" error.
     */
    private function resolveMutationDate(int $unit): string
    {
        $posted = $this->cleanDate($this->request->getPost('tanggal'));
        if ($posted !== null) {
            return $posted;
        }

        $draft = $this->svc->draftTerbuka($unit);
        return $draft !== null ? (string) $draft->tanggal : date('Y-m-d');
    }

    /**
     * Progres KPI stok opname bulan berjalan untuk satu unit.
     *
     * Syaratnya sama persis dengan StokOpnameCalculator: periode FINAL, seluruh
     * barang berstok terisi, dan tidak kosong. Kalau di sini longgar, angka di
     * layar akan beda dengan angka yang dipakai hitungan tunjangan.
     */
    private function kpiBulan(int $unit): array
    {
        $bulan = date('Y-m');

        $final = $this->db()
            ->table('stok_opname_periode')
            ->where('unit_idunit', $unit)
            ->where('status', 'FINAL')
            ->where('terisi_barang = total_barang', null, false)
            ->where('total_barang >', 0)
            ->where('tanggal >=', $bulan . '-01')
            ->where('tanggal <=', $bulan . '-31')
            ->countAllResults();

        return [
            'bulan'  => $bulan,
            'final'  => (int) $final,
            'target' => self::KPI_TARGET,
            'pct'    => self::KPI_TARGET > 0 ? min(100, round(($final / self::KPI_TARGET) * 100)) : 0,
        ];
    }

    /**
     * Jejak audit satu periode, untuk ditampilkan di halaman.
     */
    private function auditTrail(int $unit, string $tanggal): array
    {
        $periode = $this->svc->periode($unit, $tanggal);
        if ($periode === null) {
            return [];
        }

        return $this->db()
            ->table('stok_opname_audit')
            ->where('periode_id', (int) $periode->id)
            ->orderBy('id', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();
    }

    private function db()
    {
        return \Config\Database::connect();
    }

    /**
     * Batal draft: ubah periode DRAFT menjadi BATAL.
     */
    public function batal()
    {
        if ($this->isViewOnly()) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Mode lihat: role ini tidak dapat membatalkan stok opname.');
        }
        if ((int) session('ID_JABATAN') !== 1) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Hanya Admin Root yang dapat membatalkan draft stok opname.');
        }

        $unit = $this->mutationUnit();
        if ($unit <= 0) {
            return redirect()->to(base_url('stok_opname'))
                ->with('gagal', 'Unit tidak valid. Pilih unit dari daftar.');
        }

        $tanggal = $this->resolveMutationDate($unit);
        $url = base_url("stok_opname?unit={$unit}&tanggal={$tanggal}");

        $alasan = (string) $this->request->getPost('alasan');

        $r = $this->svc->batalDraft($unit, $tanggal, (int) session('ID_AKUN'), $alasan);
        if ($r['success']) {
            return redirect()->to($url)->with('sukses',
                'Draft stok opname dibatalkan (BATAL).');
        }
        return redirect()->to($url)->with('gagal', implode(' ', $r['errors']));
    }

}
