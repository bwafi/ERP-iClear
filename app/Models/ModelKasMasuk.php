<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKasMasuk extends Model
{
    protected $table = 'kas_masuk';
    protected $primaryKey = 'idkas_masuk';
    protected $returnType = 'object';
    protected $allowedFields = ['idkas_masuk', 'tanggal', 'kategori_idkategori', 'deskripsi', 'jumlah', 'penerima', 'idunit', 'idbank', 'created_on', 'updated_on', 'jenis', 'no_akun'];

    /**
     * Baris `kas awal` adalah snapshot saldo awal dari Tutup Kasir lama,
     * bukan kas masuk. Aturan ini sama dengan
     * `TutupKasirSourceDefinition::KAS_AWAL_SQL`; ditaruh di sini supaya
     * daftar, export, dan laporan memakai definisi yang identik.
     */
    public const SQL_BUKAN_KAS_AWAL = "(kas_masuk.deskripsi IS NULL OR LOWER(TRIM(kas_masuk.deskripsi)) <> 'kas awal')";

    public function getKasMasuk()
    {
        return $this->select('kas_masuk.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori')
            ->join('bank', 'bank.idbank = kas_masuk.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left')
            ->findAll();
    }



    public function insert_KasMasuk($data)
    {
        return $this->insert($data);
    }


    public function getKasMasukFiltered($tanggal_awal = null, $tanggal_akhir = null, $nama_unit = null)
    {
        $builder = $this->select('kas_masuk.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori')
            ->join('bank', 'bank.idbank = kas_masuk.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left');

        // Filter tanggal
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $builder->where('kas_masuk.tanggal >=', $tanggal_awal)
                ->where('kas_masuk.tanggal <=', $tanggal_akhir);
        } elseif (!empty($tanggal_awal)) {
            $builder->where('kas_masuk.tanggal >=', $tanggal_awal);
        } elseif (!empty($tanggal_akhir)) {
            $builder->where('kas_masuk.tanggal <=', $tanggal_akhir);
        }

        // Filter unit
        if (!empty($nama_unit)) {
            $builder->where('unit.NAMA_UNIT', $nama_unit);
        }

        return $builder->findAll();
    }


    public function getById($idkas_masuk)
    {
        return $this->where(['idkas_masuk' => $idkas_masuk])->first();
    }

    // =================================================================
    // DAFTAR SERVER-SIDE (/kas_masuk/datatables)
    //
    // Semua angka ringkasan (total/tunai/transfer) dihitung dari SELURUH
    // hasil filter, bukan halaman aktif, supaya kaki tabel tidak berbeda
    // dengan yang dibaca pengguna.
    // =================================================================

    private function baseKasMasukQuery()
    {
        return $this->db->table('kas_masuk')
            ->select('kas_masuk.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT, no_akun.nama_akun')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori', 'left')
            ->join('bank', 'bank.idbank = kas_masuk.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left')
            ->join('no_akun', 'no_akun.no_akun = kas_masuk.no_akun', 'left');
    }

    /**
     * Terapkan filter umum pada builder kas masuk.
     *
     * `like()` CI4 meng-escape wildcard `%`/`_` dari pengguna, jadi pencarian
     * diperlakukan literal. Pencarian yang seluruhnya angka juga mencocokkan
     * primary key secara persis.
     */
    private function applyKasMasukFilter($builder, string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null)
    {
        if ($search !== '') {
            // CI4 4.4.8 `like()` meng-escape quote, bukan wildcard. Escape
            // `%`/`_`/escape-char sendiri agar pencarian diperlakukan literal;
            // `like()` tetap membungkus nilai dengan quote dan menambah
            // klausa ESCAPE sesuai driver.
            $escapeChar = $this->db->likeEscapeChar;
            $like = str_replace(
                [$escapeChar, '%', '_'],
                [$escapeChar . $escapeChar, $escapeChar . '%', $escapeChar . '_'],
                $search
            );

            $builder->groupStart()
                ->orLike('kas_masuk.deskripsi', $like)
                ->orLike('kas_masuk.no_akun', $like)
                ->orLike('no_akun.nama_akun', $like)
                ->orLike('kategori_kas.kategori', $like)
                ->orLike('bank.nama_bank', $like)
                ->orLike('bank.norek', $like)
                ->orLike('kas_masuk.idbank', $like)
                ->orLike('unit.NAMA_UNIT', $like)
                ->orLike('kas_masuk.penerima', $like);
            if (ctype_digit($search)) {
                $builder->orWhere('kas_masuk.idkas_masuk', (int) $search);
            }
            $builder->groupEnd();
        }

        if (!empty($startDate)) {
            $builder->where('kas_masuk.tanggal >=', $startDate);
        }
        if (!empty($endDate)) {
            $builder->where('kas_masuk.tanggal <=', $endDate);
        }
        if ($unitId !== null && $unitId > 0) {
            $builder->where('kas_masuk.idunit', $unitId);
        }
        if ($kategoriId !== null && $kategoriId > 0) {
            $builder->where('kas_masuk.kategori_idkategori', $kategoriId);
        }
    }

    /** Jumlah total baris (tanpa filter) untuk DataTables. */
    public function countAllKasMasuk(): int
    {
        $row = $this->db->table('kas_masuk')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->countAllResults();

        return (int) $row;
    }

    /**
     * Total kas masuk manual dalam rentang tanggal (opsional per unit).
     *
     * Dipakai laporan Laba Rugi / Dashboard. Baris `kas awal` tidak ikut:
     * itu snapshot saldo, bukan pendapatan.
     */
    public function sumPendapatanRange(?string $tanggalAwal, ?string $tanggalAkhir, ?int $unitId = null): float
    {
        $builder = $this->db->table('kas_masuk')
            ->selectSum('jumlah', 'total')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false);

        if (!empty($tanggalAwal)) {
            $builder->where('DATE(kas_masuk.tanggal) >=', $tanggalAwal);
        }
        if (!empty($tanggalAkhir)) {
            $builder->where('DATE(kas_masuk.tanggal) <=', $tanggalAkhir);
        }
        if ($unitId !== null && $unitId > 0) {
            $builder->where('kas_masuk.idunit', $unitId);
        }

        return (float) ($builder->get()->getRow()->total ?? 0);
    }

    /**
     * Rincian kas masuk untuk laporan Laba Rugi (bukan `kas awal`).
     *
     * @return array<int, object>
     */
    public function getPendapatanDetail(?string $tanggalAwal, ?string $tanggalAkhir, ?int $unitId = null): array
    {
        $builder = $this->db->table('kas_masuk')
            ->select('kas_masuk.tanggal, kategori_kas.kategori, kas_masuk.deskripsi, kas_masuk.jumlah, unit.NAMA_UNIT as nama_unit')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori', 'left')
            ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left');

        if (!empty($tanggalAwal)) {
            $builder->where('DATE(kas_masuk.tanggal) >=', $tanggalAwal);
        }
        if (!empty($tanggalAkhir)) {
            $builder->where('DATE(kas_masuk.tanggal) <=', $tanggalAkhir);
        }
        if ($unitId !== null && $unitId > 0) {
            $builder->where('kas_masuk.idunit', $unitId);
        }

        return $builder->orderBy('kas_masuk.tanggal', 'DESC')->get()->getResult();
    }

    /**
     * Total kas masuk per kategori untuk grafik dashboard (bukan `kas awal`).
     *
     * @return array<int, object>
     */
    public function sumPendapatanPerKategori(?string $tanggalAwal, ?string $tanggalAkhir, ?int $unitId = null, int $limit = 5): array
    {
        $builder = $this->db->table('kas_masuk')
            ->select('kategori_kas.kategori, SUM(kas_masuk.jumlah) as total')
            ->where(self::SQL_BUKAN_KAS_AWAL, null, false)
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori');

        if (!empty($tanggalAwal)) {
            $builder->where('DATE(kas_masuk.tanggal) >=', $tanggalAwal);
        }
        if (!empty($tanggalAkhir)) {
            $builder->where('DATE(kas_masuk.tanggal) <=', $tanggalAkhir);
        }
        if ($unitId !== null && $unitId > 0) {
            $builder->where('kas_masuk.idunit', $unitId);
        }

        return $builder->groupBy('kategori_kas.kategori')
            ->orderBy('total', 'DESC')
            ->limit($limit)
            ->get()->getResult();
    }

    /** Jumlah baris setelah filter untuk DataTables. */
    public function countKasMasukFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): int
    {
        $builder = $this->baseKasMasukQuery();
        $this->applyKasMasukFilter($builder, $search, $startDate, $endDate, $unitId, $kategoriId);

        return (int) $builder->countAllResults();
    }

    /**
     * Total Rupiah dan pecahan tunai/transfer dari seluruh baris terfilter.
     * Baris tanpa `idbank` dianggap tunai; baris ber-`idbank` dianggap transfer.
     *
     * @return array{total: float, tunai: float, transfer: float, n: int}
     */
    public function sumKasMasukFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): array
    {
        $builder = $this->baseKasMasukQuery();
        $this->applyKasMasukFilter($builder, $search, $startDate, $endDate, $unitId, $kategoriId);
        $builder->select('COALESCE(SUM(kas_masuk.jumlah), 0) AS total', false)
            ->select("COALESCE(SUM(CASE WHEN kas_masuk.idbank IS NULL OR kas_masuk.idbank = '' THEN kas_masuk.jumlah ELSE 0 END), 0) AS tunai", false)
            ->select("COALESCE(SUM(CASE WHEN kas_masuk.idbank IS NOT NULL AND kas_masuk.idbank <> '' THEN kas_masuk.jumlah ELSE 0 END), 0) AS transfer", false)
            ->select('COUNT(*) AS n', false);

        $row = $builder->get()->getRow();

        return [
            'total' => (float) ($row->total ?? 0),
            'tunai' => (float) ($row->tunai ?? 0),
            'transfer' => (float) ($row->transfer ?? 0),
            'n' => (int) ($row->n ?? 0),
        ];
    }

    /** Baris data untuk DataTables server-side. */
    public function getKasMasukDataTable(int $length, int $start, string $search = '', ?string $orderCol = null, string $orderDir = 'DESC', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): array
    {
        $safeOrderCols = [
            'kas_masuk.tanggal',
            'unit.NAMA_UNIT',
            'kas_masuk.no_akun',
            'kategori_kas.kategori',
            'kas_masuk.deskripsi',
            'bank.nama_bank',
            'kas_masuk.penerima',
            'bank.norek',
            'kas_masuk.jumlah',
            'kas_masuk.jenis',
        ];
        $orderCol = in_array($orderCol, $safeOrderCols, true) ? $orderCol : 'kas_masuk.tanggal';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $builder = $this->baseKasMasukQuery();
        $this->applyKasMasukFilter($builder, $search, $startDate, $endDate, $unitId, $kategoriId);

        return $builder->orderBy($orderCol, $orderDir)
            ->orderBy('kas_masuk.idkas_masuk', 'DESC')
            ->limit(max(0, $length), max(0, $start))
            ->get()
            ->getResult();
    }
}
