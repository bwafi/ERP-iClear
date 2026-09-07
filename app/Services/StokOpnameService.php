<?php

namespace App\Services;

use CodeIgniter\Database\ConnectionInterface;
use App\Models\ModelStokOpname;
use App\Models\ModelStokOpnameDraft;
use App\Models\ModelStokOpnamePeriode;
use App\Models\ModelHppBarang;
use App\Models\ModelStokAwal;

/**
 * StokOpnameService
 *
 * Alur kerja Stok Opname berbasis PERIODE (mirip Kontrol Aset):
 *   - Mulai Opname  : buat periode DRAFT + seed daftar barang dari stok_barang.
 *   - Simpan Draft  : input/update jumlah_real per barang, boleh dicicil.
 *   - Finalisasi    : wajib semua barang terisi -> salin ke stok_opname (final) & kunci.
 *   - Reopen        : koreksi -> kembali DRAFT, hasil final periode dihapus.
 */
class StokOpnameService
{
    protected $db;
    protected $model;
    protected $draftModel;
    protected $finalModel;
    protected $hppModel;
    protected $stokAwalModel;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->model = new ModelStokOpnamePeriode();
        $this->draftModel = new ModelStokOpnameDraft();
        $this->finalModel = new ModelStokOpname();
        $this->hppModel = new ModelHppBarang();
        $this->stokAwalModel = new ModelStokAwal();
    }

    public function periodeModel(): ModelStokOpnamePeriode
    {
        return $this->model;
    }

    public function periode(int $unit, string $tanggal): ?object
    {
        return $this->model->getByUnitTanggal($unit, $tanggal);
    }

    public function periodeItems(int $unit, string $tanggal): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return [];
        }

        $isFinal = $periode->status === 'FINAL';
        $table = $isFinal ? 'stok_opname' : 'stok_opname_draft';

        $builder = $this->db->table($table)
            ->select("$table.*, barang.kode_barang, barang.nama_barang, barang.jenis_hp, barang.warna, unit.NAMA_UNIT")
            ->join('barang', "barang.idbarang = $table.barang_idbarang")
            ->join('unit', "unit.idunit = $table.unit_idunit")
            ->where("$table.periode_id", (int)$periode->id)
            ->orderBy('barang.kode_barang', 'ASC');

        $rows = $builder->get()->getResultArray();

        $items = [];
        foreach ($rows as $r) {
            $realFilled = $r['jumlah_real'] !== null && trim((string)$r['jumlah_real']) !== '';
            $items[] = [
                'id_opname'     => (int)$r['idstok_opname'],
                'barang_id'     => (int)$r['barang_idbarang'],
                'kode_barang'   => (string)$r['kode_barang'],
                'nama_barang'   => (string)$r['nama_barang'],
                'jenis_hp'      => (string)$r['jenis_hp'],
                'warna'         => (string)$r['warna'],
                'unit'          => (string)$r['NAMA_UNIT'],
                'jumlah_komp'   => (float)$r['jumlah_komp'],
                'jumlah_real'   => $realFilled ? (float)$r['jumlah_real'] : null,
                'jumlah_selisih' => $realFilled ? (float)$r['jumlah_selisih'] : null,
                'satuan'        => (string)$r['satuan_terkecil'],
                'terisi'        => $realFilled,
                'selisih_positif' => $realFilled && (float)$r['jumlah_selisih'] > 0,
                'selisih_negatif' => $realFilled && (float)$r['jumlah_selisih'] < 0,
            ];
        }

        return $items;
    }

    /**
     * Mulai opname: buat periode DRAFT + seed daftar barang dari stok_barang.
     */
    public function createPeriode(int $unit, string $tanggal, int $userId): array
    {
        if ($this->periode($unit, $tanggal)) {
            return ['success' => false, 'errors' => ['Periode stok opname sudah ada untuk unit & tanggal ini.'], 'periode' => null];
        }

        $this->db->transBegin();

        try {
            $this->db->query(
                'INSERT INTO stok_opname_periode
                    (unit_idunit, tanggal, status, mulai_by, created_at, updated_at)
                 VALUES (?, ?, \'DRAFT\', ?, NOW(), NOW())',
                [$unit, $tanggal, $userId]
            );
            $periodeId = (int)$this->db->insertID();

            $stocks = $this->db->table('stok_barang')
                ->where('id_unit', $unit)
                ->orderBy('kode_barang', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($stocks as $stock) {
                $barangId = (int)$stock['idbarang'];
                $stokAwal = $this->stokAwalModel->getByIdBarang($barangId);
                $satuan = $stokAwal->satuan_terkecil ?? null;
                $hppRow = $this->hppModel->getById($barangId);
                $hpp = (float)($hppRow->hpp ?? 0);

                $this->db->table('stok_opname_draft')->insert([
                    'tanggal'          => $tanggal,
                    'hpp'              => $hpp,
                    'jumlah_real'      => null,
                    'jumlah_komp'      => (float)$stock['stok_akhir'],
                    'jumlah_selisih'   => null,
                    'satuan_terkecil'  => $satuan,
                    'barang_idbarang'  => $barangId,
                    'unit_idunit'      => $unit,
                    'periode_id'       => $periodeId,
                ]);
            }

            $this->refreshSummary($periodeId);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal menyiapkan stok opname.'], 'periode' => null];
            }

            $this->db->transCommit();

            return ['success' => true, 'errors' => [], 'periode' => $this->model->find($periodeId)];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'periode' => null];
        }
    }

    /**
     * Simpan draft: update jumlah_real per barang (dicicil, boleh sebagian).
     */
    public function saveDraft(int $unit, string $tanggal, array $rows, int $userId): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return ['success' => false, 'errors' => ['Mulai stok opname terlebih dahulu.'], 'saved' => 0];
        }
        if ($periode->status === 'FINAL') {
            return ['success' => false, 'errors' => ['Periode sudah difinalisasi. Reopen terlebih dahulu untuk mengubah.'], 'saved' => 0];
        }

        $saved = 0;
        $errors = [];
        $invalid = [];
        foreach ($rows as $barangId => $value) {
            $barangId = (int)$barangId;
            if ($barangId <= 0) {
                continue;
            }
            // Input kosong = belum diisi, bukan error (dicegah membuat peringatan berjubel).
            $value = is_scalar($value) ? trim((string)$value) : '';
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || (float)$value < 0) {
                $invalid[] = $barangId;
                continue;
            }

            $real = (float)$value;
            $draft = $this->db->table('stok_opname_draft')
                ->where('periode_id', (int)$periode->id)
                ->where('barang_idbarang', $barangId)
                ->get()
                ->getRow();

            if ($draft) {
                $selisih = $real - (float)$draft->jumlah_komp;
                $this->db->table('stok_opname_draft')
                    ->where('idstok_opname', $draft->idstok_opname)
                    ->update([
                        'jumlah_real'    => $real,
                        'jumlah_selisih' => $selisih,
                    ]);
                $saved++;
            } else {
                // Barang baru muncul di stok_barang setelah periode dibuat.
                $stok = $this->db->table('stok_barang')
                    ->where('id_unit', $unit)
                    ->where('idbarang', $barangId)
                    ->get()
                    ->getRow();
                if (!$stok) {
                    continue;
                }
                $stokAwal = $this->stokAwalModel->getByIdBarang($barangId);
                $hppRow = $this->hppModel->getById($barangId);
                $komp = (float)$stok->stok_akhir;
                $this->db->table('stok_opname_draft')->insert([
                    'tanggal'          => $tanggal,
                    'hpp'              => (float)($hppRow->hpp ?? 0),
                    'jumlah_real'      => $real,
                    'jumlah_komp'      => $komp,
                    'jumlah_selisih'   => $real - $komp,
                    'satuan_terkecil'  => $stokAwal->satuan_terkecil ?? null,
                    'barang_idbarang'  => $barangId,
                    'unit_idunit'      => $unit,
                    'periode_id'       => (int)$periode->id,
                ]);
                $saved++;
            }
        }

        if ($invalid) {
            $count = count($invalid);
            $errors[] = $count . ' barang memiliki nilai Jumlah Real tidak valid (harus angka 0 atau lebih). Nilai tsb diabaikan.';
        }

        $this->refreshSummary((int)$periode->id);

        return ['success' => true, 'errors' => $errors, 'saved' => $saved];
    }

    /**
     * Finalisasi: wajib semua barang terisi. Salin draft -> stok_opname (final),
     * lalu kunci periode FINAL.
     */
    public function finalize(int $unit, string $tanggal, int $userId): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return ['success' => false, 'errors' => ['Periode stok opname tidak ditemukan.'], 'periode' => null];
        }
        if ($periode->status === 'FINAL') {
            return ['success' => false, 'errors' => ['Periode sudah FINAL.'], 'periode' => null];
        }

        $total = (int)$periode->total_barang;
        $terisi = (int)$periode->terisi_barang;
        if ($total === 0) {
            return ['success' => false, 'errors' => ['Tidak ada barang untuk diopname diproses.'], 'periode' => null];
        }

        // Finalisasi DIPERBOLEHKAN meski belum lengkap — cukup dengan peringatan.
        $kurang = max(0, $total - $terisi);
        $warning = $kurang > 0 ? "PERINGATAN: masih ada $kurang barang yang belum diisi jumlah real." : null;

        $this->db->transBegin();

        try {
            $periodeId = (int)$periode->id;

            // Hapus hasil final lama (jika pernah final lalu reopen).
            $this->db->query('DELETE FROM stok_opname WHERE periode_id = ?', [$periodeId]);

            // Salin draft -> final.
            $this->db->query(
                'INSERT INTO stok_opname
                    (tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                     satuan_terkecil, barang_idbarang, unit_idunit, periode_id)
                 SELECT tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                        satuan_terkecil, barang_idbarang, unit_idunit, ?
                 FROM stok_opname_draft WHERE periode_id = ?',
                [$periodeId, $periodeId]
            );

            // Ringkasan final.
            $sum2 = $this->db->query(
                'SELECT COALESCE(SUM(CAST(jumlah_real AS DECIMAL(15,2))),0) AS real_jml,
                        COALESCE(SUM(CAST(jumlah_selisih AS DECIMAL(15,2))),0) AS selisih
                 FROM stok_opname WHERE periode_id = ?',
                [$periodeId]
            )->getRow();

            $this->db->table('stok_opname_periode')
                ->where('id', $periodeId)
                ->update([
                    'status'            => 'FINAL',
                    'jumlah_real'       => (float)$sum2->real_jml,
                    'jumlah_selisih'    => (float)$sum2->selisih,
                    'finalisasi_by'     => $userId,
                    'tanggal_finalisasi' => date('Y-m-d H:i:s'),
                ]);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal finalisasi stok opname.'], 'periode' => null];
            }

            $this->db->transCommit();

            return ['success' => true, 'errors' => [], 'warning' => $warning, 'periode' => $this->model->find($periodeId)];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'periode' => null];
        }
    }

    /**
     * Reopen: kembalikan ke DRAFT & hapus hasil final periode ini (biar bisa dikoreksi).
     */
    public function reopen(int $unit, string $tanggal, int $userId): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return ['success' => false, 'errors' => ['Periode stok opname tidak ditemukan.'], 'periode' => null];
        }
        if ($periode->status === 'DRAFT') {
            return ['success' => false, 'errors' => ['Periode masih berstatus DRAFT.'], 'periode' => null];
        }

        $this->db->transBegin();

        try {
            $periodeId = (int)$periode->id;
            $this->db->query('DELETE FROM stok_opname WHERE periode_id = ?', [$periodeId]);

            $this->db->table('stok_opname_periode')
                ->where('id', $periodeId)
                ->update([
                    'status'             => 'DRAFT',
                    'jumlah_real'        => null,
                    'jumlah_selisih'     => null,
                    'finalisasi_by'      => null,
                    'tanggal_finalisasi' => null,
                ]);

            $this->refreshSummary($periodeId);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal reopen stok opname.'], 'periode' => null];
            }

            $this->db->transCommit();

            return ['success' => true, 'errors' => [], 'periode' => $this->model->find($periodeId)];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'periode' => null];
        }
    }

    private function refreshSummary(int $periodeId)
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL AND TRIM(CAST(jumlah_real AS CHAR)) <> \'\' THEN 1 ELSE 0 END),0) AS terisi,
                    COALESCE(SUM(CAST(jumlah_komp AS DECIMAL(15,2))),0) AS komp,
                    COALESCE(SUM(
                        CASE WHEN jumlah_real IS NOT NULL AND TRIM(CAST(jumlah_real AS CHAR)) <> \'\'
                             THEN CAST(jumlah_real AS DECIMAL(15,2)) ELSE 0 END
                    ),0) AS real_jml,
                    COALESCE(SUM(
                        CASE WHEN jumlah_selisih IS NOT NULL THEN CAST(jumlah_selisih AS DECIMAL(15,2)) ELSE 0 END
                    ),0) AS selisih
             FROM stok_opname_draft WHERE periode_id = ?',
            [$periodeId]
        )->getRow();

        $this->db->table('stok_opname_periode')
            ->where('id', $periodeId)
            ->update([
                'total_barang'    => (int)($row->total ?? 0),
                'terisi_barang'   => (int)($row->terisi ?? 0),
                'jumlah_komp'     => (float)($row->komp ?? 0),
                'jumlah_real'     => (float)($row->real_jml ?? 0),
                'jumlah_selisih'  => (float)($row->selisih ?? 0),
            ]);
    }
}