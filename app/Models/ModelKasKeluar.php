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

    /** Query dasar kas keluar + join kategori/bank/unit/COA. */
    private function baseKasKeluarQuery()
    {
        return $this->select('kas_keluar.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT, no_akun.nama_akun')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left')
            ->join('bank', 'bank.idbank = kas_keluar.idbank', 'left')
            ->join('unit', 'unit.idunit = kas_keluar.idunit', 'left')
            ->join('no_akun', 'no_akun.no_akun = kas_keluar.no_akun', 'left');
    }

    /**
     * Terapkan filter umum (search/tanggal/unit) pada builder kas keluar.
     *
     * $idExact memilih pencocokan persis primary key. Kueri yang isinya hanya angka
     * memakai mode ini: mencocokkan "3" sebagai substring mengembalikan ratusan
     * baris yang tidak pernah dimaksud, sementara `idkas_keluar = 3` memakai
     * indeks dan selesai seketika.
     */
    private function applyKasKeluarFilter($builder, string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false)
    {
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

    /** Jumlah total baris (tanpa filter) untuk DataTables. */
    public function countAllKasKeluar(): int
    {
        return $this->baseKasKeluarQuery()->countAllResults();
    }

    /** Jumlah baris setelah filter untuk DataTables. */
    public function countKasKeluarFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false): int
    {
        $builder = $this->baseKasKeluarQuery();
        $this->applyKasKeluarFilter($builder, $search, $startDate, $endDate, $unitId, $idExact);
        return $builder->countAllResults();
    }

    /**
     * Total Rupiah dari SELURUH baris terfilter, bukan hanya halaman aktif.
     * Dipakai supaya angka "Total" di kaki tabel tidak berbeda dengan yang
     * dibaca pengguna dari hasil filter.
     */
    public function sumKasKeluarFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false): float
    {
        $builder = $this->baseKasKeluarQuery();
        $this->applyKasKeluarFilter($builder, $search, $startDate, $endDate, $unitId, $idExact);

        $row = $builder->select('COALESCE(SUM(kas_keluar.jumlah), 0) AS total', true)->first();

        return (float)($row->total ?? 0);
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
    public function isKasKeluarInScope(int $id, ?string $startDate = null, ?string $endDate = null, ?int $unitId = null): bool
    {
        $builder = $this->baseKasKeluarQuery();
        $this->applyKasKeluarFilter($builder, (string)$id, $startDate, $endDate, $unitId, true);

        return $builder->countAllResults() > 0;
    }

    /** Baris data untuk DataTables server-side. */
    public function getKasKeluarDataTable(int $length, int $start, string $search = '', ?string $orderCol = null, string $orderDir = 'DESC', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, bool $idExact = false): array
    {
        $builder = $this->baseKasKeluarQuery();
        $this->applyKasKeluarFilter($builder, $search, $startDate, $endDate, $unitId, $idExact);

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
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $builder->orderBy($orderCol, $orderDir)
            ->orderBy('kas_keluar.idkas_keluar', 'DESC');

return $builder->limit($length, max(0, $start))->get()->getResult();
    }
}
