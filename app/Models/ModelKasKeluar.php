<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKasKeluar extends Model
{
    protected $table = 'kas_keluar';
    protected $primaryKey = 'idkas_keluar';
    protected $returnType = 'object';
    protected $allowedFields = ['idkas_keluar', 'tanggal', 'kategori_idkategori', 'deskripsi', 'jumlah', 'penerima', 'idunit', 'idbank', 'created_on', 'updated_on', 'jenis', 'no_akun'];

    public function getKasKeluar()
    {
        return $this->select('kas_keluar.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori')
            ->join('bank', 'bank.idbank = kas_keluar.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_keluar.idunit', 'left')
            ->findAll();
    }



    public function insert_KasKeluar($data)
    {
        return $this->insert($data);
    }

    public function getById($idkas_keluar)
    {
        return $this->where(['idkas_keluar' => $idkas_keluar])->first();
    }


public function getKasKeluarFiltered($tanggal_awal = null, $tanggal_akhir = null, $nama_unit = null, $unitId = null)
    {
        $builder = $this->baseKasKeluarQuery();

        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $builder->where('kas_keluar.tanggal >=', $tanggal_awal)
                ->where('kas_keluar.tanggal <=', $tanggal_akhir);
        } elseif (!empty($tanggal_awal)) {
            $builder->where('kas_keluar.tanggal >=', $tanggal_awal);
        } elseif (!empty($tanggal_akhir)) {
            $builder->where('kas_keluar.tanggal <=', $tanggal_akhir);
        }
        if (!empty($unitId)) {
            $builder->where('kas_keluar.idunit', (int)$unitId);
        } elseif (!empty($nama_unit)) {
            $builder->where('unit.NAMA_UNIT', $nama_unit);
        }

        return $builder->get()->getResult();
    }

    /**
     * Query dasar kas keluar + join kategori/bank/unit/COA.
     *
     * Kolom `kas_keluar.penerima` dipakai dua arti sekaligus, tergantung
     * halaman mana yang membuat barisnya:
     *
     *   - dari /kas_keluar -> teks atas_nama pemilik rekening pilihan
     *   - dari /payroll   -> akun.ID_AKUN karyawan (kasbon/lembur)
     *
     * Karena itu `akun` di-join dan diambil sebagai `penerima_akun`, supaya
     * pemanggil bisa menampilkan nama karyawan untuk baris payroll. Join-nya
     * mengikuti yang sudah dipakai Payroll::index(). Isian non-angka tidak
     * akan cocok apa pun dan menghasilkan NULL, jadi teks aslinya tetap bisa
     * dipakai sebagai cadangan.
     */
    private function baseKasKeluarQuery()
    {
        return $this->select('kas_keluar.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT, no_akun.nama_akun, akun.NAMA_AKUN as penerima_akun')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left')
            ->join('bank', 'bank.idbank = kas_keluar.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_keluar.idunit', 'left')
            ->join('no_akun', 'no_akun.no_akun = kas_keluar.no_akun', 'left')
            ->join('akun', 'akun.ID_AKUN = kas_keluar.penerima', 'left');
    }

    /**
     * Terapkan filter umum (search/tanggal/unit) pada builder kas keluar.
     *
     * $idExact memilih pencocokan persis primary key. Kueri yang isinya hanya angka
     * memakai mode ini: mencocokkan "3" sebagai substring mengembalikan ratusan
     * baris yang tidak pernah dimaksud, sementara `idkas_keluar = 3` memakai
     * indeks dan selesai seketika.
     */
    private function applyKasKeluarFilter($builder, string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?string $kategoriMode = null, ?int $kategoriId = null)
    {
        if ($kategoriId !== null && $kategoriId > 0) {
            $builder->where('kas_keluar.kategori_idkategori', $kategoriId);
        }
        // Kategori 6% = Biaya Terkontrol, sumbernya sama dengan /asset_berjalan.
        if ($kategoriMode === 'enam' || $kategoriMode === 'luar') {
            $terkontrol = array_map('intval', config(\Config\Finance::class)->kategoriBiayaTerkontrol ?? []);
            if ($terkontrol === []) {
                if ($kategoriMode === 'enam') {
                    $builder->where('1 = 0', null, false);
                }
            } elseif ($kategoriMode === 'enam') {
                $builder->whereIn('kas_keluar.kategori_idkategori', $terkontrol);
            } else {
                $builder->whereNotIn('kas_keluar.kategori_idkategori', $terkontrol);
            }
        }
        if ($search !== '') {
            if ($idExact) {
                $builder->where('kas_keluar.idkas_keluar', (int)$search);
            } else {
                $builder->groupStart()
                    ->orLike('no_akun.no_akun', $search)
                    ->orLike('no_akun.nama_akun', $search)
                    ->orLike('kategori_kas.kategori', $search)
                    ->orLike('kas_keluar.deskripsi', $search)
                    ->orLike('bank.nama_bank', $search)
                    ->orLike('bank.norek', $search)
                    ->orLike('unit.NAMA_UNIT', $search)
                    ->orLike('kas_keluar.penerima', $search)
                    // Baris payroll menyimpan ID_AKUN di `penerima`, jadi
                    // nama karyawannya harus ikut bisa dicari.
                    ->orLike('akun.NAMA_AKUN', $search)
                    ->groupEnd();
            }
        }
        if (!empty($startDate)) {
            $builder->where('kas_keluar.tanggal >=', $startDate);
        }
        if (!empty($endDate)) {
            $builder->where('kas_keluar.tanggal <=', $endDate);
        }
        if ($unitId !== null && $unitId > 0) {
            $builder->where('kas_keluar.idunit', $unitId);
        }
    }

    // =================================================================
    // DAFTAR GABUNGAN (kas_keluar + pelunasan antar unit)
    //
    // Pelunasan hutang antar unit tidak disalin ke `kas_keluar` — uangnya
    // sudah tercatat sekali di `transaksi_kas_bank` (leg KELUAR + MASUK).
    // Menyalinnya ke `kas_keluar` akan membuat posting keluar dua kali.
    // Karena itu daftar di /kas_keluar membaca KEDUA sumber sekaligus:
    // baris antar unit tampil read-only (is_readonly = 1) dan tidak bisa
    // diedit/dihapus dari halaman ini.
    //
    // Leg yang ditampilkan hanya:
    //   - arah KELUAR (sisi unit pembayar, uang keluar dari rekeningnya)
    //   - rekening fisik BERBEDA (sumber_tipe bukan 'antar_unit_atribusi'),
    //     karena pelunasan pada rekening yang sama hanya menggeser hak unit:
    //     tidak ada uang yang keluar dan tidak boleh menambah total kas keluar.
    // =================================================================

    private function unionSumber(): string
    {
        return "(
            SELECT k.idkas_keluar AS idkas_keluar, k.tanggal AS tanggal,
                   k.kategori_idkategori AS kategori_idkategori, k.deskripsi AS deskripsi,
                   k.jumlah AS jumlah, k.penerima AS penerima, k.idunit AS idunit,
                   k.idbank AS idbank, k.jenis AS jenis, k.no_akun AS no_akun,
                   0 AS is_readonly, '' AS ref_kode
            FROM kas_keluar k
            UNION ALL
            SELECT NULL, t.tanggal, NULL,
                   -- Arah selalu tampil: unit pembayar -> unit berpiutang.
                   -- Unit berpiutang diambil dari leg MASUK ber-transfer_ref
                   -- sama (satu baris per ref, jadi tidak menggandakan hasil).
                   CONCAT('Pelunasan antar unit ',
                          COALESCE(ua.NAMA_UNIT, CONCAT('unit ', t.unit_id)), ' → ',
                          COALESCE(ut.NAMA_UNIT, 'unit lawan'),
                          IF(TRIM(COALESCE(t.keterangan, '')) = '', '',
                             CONCAT(' — ', TRIM(t.keterangan)))),
                   t.jumlah, NULL, t.unit_id,
                   a.bank_idbank, 'antar_unit', NULL, 1, COALESCE(t.transfer_ref, '')
            FROM transaksi_kas_bank t
            LEFT JOIN akun_kas_bank a ON a.idakun_kas_bank = t.akun_kas_bank_id
            LEFT JOIN unit ua ON ua.idunit = t.unit_id
            LEFT JOIN (
                SELECT transfer_ref, MIN(unit_id) AS unit_id
                FROM transaksi_kas_bank
                WHERE arah = 'MASUK' AND jenis = 'PEMBAYARAN_ANTAR_UNIT'
                  AND transfer_ref IS NOT NULL
                GROUP BY transfer_ref
            ) tm ON tm.transfer_ref = t.transfer_ref
            LEFT JOIN unit ut ON ut.idunit = tm.unit_id
            WHERE t.jenis = 'PEMBAYARAN_ANTAR_UNIT' AND t.arah = 'KELUAR'
              AND (t.sumber_tipe IS NULL OR t.sumber_tipe <> 'antar_unit_atribusi')
        ) u";
    }

    private function unionFrom(): string
    {
        return ' FROM ' . $this->unionSumber()
            . ' LEFT JOIN kategori_kas ON kategori_kas.idkategori_kas = u.kategori_idkategori'
            . ' LEFT JOIN bank ON bank.idbank = u.idbank'
            . ' LEFT JOIN unit ON unit.idunit = u.idunit'
            . ' LEFT JOIN no_akun ON no_akun.no_akun = u.no_akun'
            . ' LEFT JOIN akun ON akun.ID_AKUN = u.penerima';
    }

    /**
     * WHERE + binding untuk daftar gabungan.
     *
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function unionWhere(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?string $kategoriMode = null, ?int $kategoriId = null): array
    {
        $where = [];
        $binds = [];

        if ($kategoriId !== null && $kategoriId > 0) {
            $where[] = 'u.kategori_idkategori = ?';
            $binds[] = $kategoriId;
        }

        if ($kategoriMode === 'enam' || $kategoriMode === 'luar') {
            $terkontrol = array_map('intval', config(\Config\Finance::class)->kategoriBiayaTerkontrol ?? []);
            if ($terkontrol === []) {
                if ($kategoriMode === 'enam') {
                    $where[] = '1 = 0';
                }
            } else {
                $where[] = 'u.kategori_idkategori ' . ($kategoriMode === 'enam' ? 'IN' : 'NOT IN')
                    . ' (' . implode(',', $terkontrol) . ')';
            }
        }

        if ($search !== '') {
            if ($idExact) {
                // Baris antar unit tidak punya idkas_keluar; mencocokkan id
                // angka ke idtransaksi bisa menunjuk transaksi yang salah.
                $where[] = 'u.is_readonly = 0 AND u.idkas_keluar = ?';
                $binds[] = (int) $search;
            } else {
                $like = '%' . $this->db->escapeLikeString($search) . '%';
                // escapeLikeString() memakai '!' sebagai escape char, jadi
                // klausa LIKE wajib menyebutkannya; tanpa itu '%' yang sudah
                // di-escape dibaca sebagai wildcard biasa.
                $where[] = "(no_akun.no_akun LIKE ? ESCAPE '!' OR no_akun.nama_akun LIKE ? ESCAPE '!'"
                    . " OR kategori_kas.kategori LIKE ? ESCAPE '!' OR u.deskripsi LIKE ? ESCAPE '!'"
                    . " OR bank.nama_bank LIKE ? ESCAPE '!' OR bank.norek LIKE ? ESCAPE '!'"
                    . " OR unit.NAMA_UNIT LIKE ? ESCAPE '!' OR u.penerima LIKE ? ESCAPE '!'"
                    . " OR akun.NAMA_AKUN LIKE ? ESCAPE '!')";
                $binds = array_merge($binds, array_fill(0, 9, $like));
            }
        }

        if (!empty($startDate)) {
            $where[] = 'u.tanggal >= ?';
            $binds[] = $startDate;
        }
        if (!empty($endDate)) {
            $where[] = 'u.tanggal <= ?';
            $binds[] = $endDate;
        }
        if ($unitId !== null && $unitId > 0) {
            $where[] = 'u.idunit = ?';
            $binds[] = $unitId;
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $binds];
    }

    /** Jumlah total baris (tanpa filter) untuk DataTables. */
    public function countAllKasKeluar(): int
    {
        $row = $this->db->query('SELECT COUNT(*) AS total FROM ' . $this->unionSumber())->getRow();

        return (int) ($row->total ?? 0);
    }

    /** Jumlah baris setelah filter untuk DataTables. */
    public function countKasKeluarFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?string $kategoriMode = null, ?int $kategoriId = null): int
    {
        [$where, $binds] = $this->unionWhere($search, $startDate, $endDate, $unitId, $idExact, $kategoriMode, $kategoriId);
        $row = $this->db->query('SELECT COUNT(*) AS total' . $this->unionFrom() . $where, $binds)->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * Total Rupiah dari SELURUH baris terfilter, bukan hanya halaman aktif.
     * Dipakai supaya angka "Total" di kaki tabel tidak berbeda dengan yang
     * dibaca pengguna dari hasil filter.
     */
    public function sumKasKeluarFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?string $kategoriMode = null, ?int $kategoriId = null): float
    {
        [$where, $binds] = $this->unionWhere($search, $startDate, $endDate, $unitId, $idExact, $kategoriMode, $kategoriId);
        $row = $this->db->query(
            'SELECT COALESCE(SUM(u.jumlah), 0) AS total' . $this->unionFrom() . $where,
            $binds
        )->getRow();

        return (float)($row->total ?? 0);
    }

    /**
     * Pecahan 6% vs di luar 6% untuk filter yang aktif (pencarian, tanggal,
     * unit, kategori spesifik). Mode kategori 6%/luar sengaja TIDAK ikut:
     * angkanya dipakai sebagai pembanding, jadi harus tetap terlihat saat
     * salah satu sisi difilter.
     *
     * @return array{total: float, enam: float, luar: float, n_enam: int, n_luar: int}
     */
    public function summaryKasKeluarFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?int $kategoriId = null): array
    {
        [$where, $binds] = $this->unionWhere($search, $startDate, $endDate, $unitId, $idExact, null, $kategoriId);

        $terkontrol = array_map('intval', config(\Config\Finance::class)->kategoriBiayaTerkontrol ?? []);
        $in = $terkontrol === [] ? 'NULL' : implode(',', $terkontrol);

        $row = $this->db->query(
            'SELECT COALESCE(SUM(u.jumlah), 0) AS total,
                    COALESCE(SUM(CASE WHEN u.kategori_idkategori IN (' . $in . ') THEN u.jumlah ELSE 0 END), 0) AS enam,
                    COALESCE(SUM(CASE WHEN u.kategori_idkategori IN (' . $in . ') THEN 1 ELSE 0 END), 0) AS n_enam,
                    COUNT(*) AS n_total'
            . $this->unionFrom() . $where,
            $binds
        )->getRow();

        $total = (float)($row->total ?? 0);
        $enam = (float)($row->enam ?? 0);
        $nEnam = (int)($row->n_enam ?? 0);
        $nTotal = (int)($row->n_total ?? 0);

        return [
            'total' => $total,
            'enam' => $enam,
            'luar' => $total - $enam,
            'n_enam' => $nEnam,
            'n_luar' => $nTotal - $nEnam,
        ];
    }

    /** Satu baris berdasarkan ID kas keluar,abaikan filter periode/unit. */
    public function findKasKeluarById(int $id): ?object
    {
        return $this->baseKasKeluarQuery()
            ->where('kas_keluar.idkas_keluar', $id)
            ->first();
    }

    /**
     * Apakah ID ini masih lolos periode/unit yang sedang aktif?
     * Dipakai untuk memberi tahu "ID ada, tapi di luar filter" alih-alih
     * diam-diam mengembalikan nol baris.
     */
    public function isKasKeluarInScope(int $id, ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?string $kategoriMode = null, ?int $kategoriId = null): bool
    {
        $builder = $this->baseKasKeluarQuery();
        $this->applyKasKeluarFilter($builder, (string)$id, $startDate, $endDate, $unitId, true, $kategoriMode, $kategoriId);

        return $builder->countAllResults() > 0;
    }

    /** Baris data untuk DataTables server-side. */
    public function getKasKeluarDataTable(int $length, int $start, string $search = '', ?string $orderCol = null, string $orderDir = 'DESC', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false, ?string $kategoriMode = null, ?int $kategoriId = null): array
    {
        $safeOrderCols = [
            'kas_keluar.tanggal',
            'unit.NAMA_UNIT',
            'no_akun.no_akun',
            'kategori_kas.kategori',
            'kas_keluar.deskripsi',
            'bank.nama_bank',
            'kas_keluar.penerima',
            'bank.norek',
            'kas_keluar.jumlah',
            'kas_keluar.jenis',
        ];
        $orderCol = in_array($orderCol, $safeOrderCols, true) ? $orderCol : 'kas_keluar.tanggal';
        // Kolom kas_keluar.* kini dibaca dari alias gabungan `u`.
        $orderCol = (string) preg_replace('/^kas_keluar\./', 'u.', $orderCol);
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        [$where, $binds] = $this->unionWhere($search, $startDate, $endDate, $unitId, $idExact, $kategoriMode, $kategoriId);

        // $length/$start sudah int; jangan di-bind supaya LIMIT tidak dikirim
        // sebagai string.
        $sql = 'SELECT u.*, kategori_kas.kategori, bank.nama_bank, bank.norek,
                       unit.NAMA_UNIT, no_akun.nama_akun, akun.NAMA_AKUN AS penerima_akun'
            . $this->unionFrom() . $where
            . ' ORDER BY ' . $orderCol . ' ' . $orderDir
            . ', u.tanggal DESC, u.is_readonly ASC, u.idkas_keluar DESC'
            . ' LIMIT ' . max(0, $length) . ' OFFSET ' . max(0, $start);

        return $this->db->query($sql, $binds)->getResult();
    }
}
