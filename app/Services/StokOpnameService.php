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
 * Alur Stok Opname v2 (berbasis periode, mulai berlaku Oktober 2026):
 *   - Mulai Opname : buat periode DRAFT + seed HANYA barang berstok (stok_akhir <> 0).
 *   - Simpan Draft  : input jumlah_real per barang, boleh dicicil, boleh dikosongkan lagi.
 *   - Finalisasi    : HANYA boleh bila terisi_barang = total_barang. Tidak ada lagi
 *                    finalisasi sebagian dengan peringatan.
 *   - Reopen        : koreksi -> kembali DRAFT. Baris final lama ditandai is_reverted
 *                    (bukan dihapus) sehingga stok_akhir berubah dan jejaknya tetap ada.
 *
 * Berbeda dengan versi lama, tidak ada lagi batasan "harus selesai di hari yang sama":
 * satu periode/unit hanya boleh punya satu DRAFT terbuka, dan boleh dilanjutkan
 * pada hari-hari berikutnya sampai operator memfinalisasikannya.
 */
class StokOpnameService
{
    /** Jumlah barang per statement saat menyimpan draft. */
    private const BATCH_SIZE = 200;

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

    /**
     * DRAFT yang masih bisa dilanjutkan. dippedakai untuk banner "lanjutkan".
     */
    public function draftTerbuka(int $unit): ?object
    {
        return $this->db->table('stok_opname_periode')
            ->where('unit_idunit', $unit)
            ->where('status', 'DRAFT')
            ->orderBy('tanggal', 'ASC')
            ->get()
            ->getRow();
    }

    public function periodeItems(int $unit, string $tanggal): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return [];
        }

        $isFinal = $periode->status === 'FINAL';
        $table = $isFinal ? 'stok_opname' : 'stok_opname_draft';
        $extraWhere = $isFinal ? "$table.is_reverted = 0" : '1 = 1';

        $rows = $this->db->table($table)
            ->select("$table.*, barang.kode_barang, barang.nama_barang, barang.jenis_hp, barang.warna, unit.NAMA_UNIT")
            ->join('barang', "barang.idbarang = $table.barang_idbarang")
            ->join('unit', "unit.idunit = $table.unit_idunit")
            ->where("$table.periode_id", (int)$periode->id)
            ->where($extraWhere)
            ->orderBy('barang.kode_barang', 'ASC')
            ->get()
            ->getResultArray();

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
     * Mulai opname: buat periode DRAFT + seed daftar barang berstok.
     *
     * Hanya barang dengan stok_akhir <> 0 yang masuk daftar. Barang stok 0 tidak
     * perlu dihitung fisik, dan barang stok negatif wajib diopname karena itu
     * kondisi bermasalah yang justru perlu ditemukan.
     */
    public function createPeriode(int $unit, string $tanggal, int $userId): array
    {
        if ($this->periode($unit, $tanggal)) {
            return ['success' => false, 'errors' => ['Periode stok opname sudah ada untuk unit & tanggal ini.'], 'periode' => null];
        }

        // Satu unit hanya boleh punya satu DRAFT terbuka. Kalau boleh lebih dari
        // satu, operator bisa punya dua daftar yang sama-sama menggantung dan
        // "lanjutkan yang mana?" jadi ambigu.
        $terbuka = $this->draftTerbuka($unit);
        if ($terbuka) {
            return [
                'success' => false,
                'errors'  => ['Masih ada stok opname belum difinalisasi untuk unit ini (tanggal ' . $terbuka->tanggal . '). '
                    . 'Lanjutkan periode tersebut atau selesaikan dulu sebelum membuat yang baru.'],
                'periode' => $terbuka,
            ];
        }

        $stocks = $this->db->table('stok_barang')
            ->where('id_unit', $unit)
            ->orderBy('kode_barang', 'ASC')
            ->get()
            ->getResultArray();

        if ($stocks === []) {
            return [
                'success' => false,
                'errors'  => ['Tidak ada barang berstok di unit ini, tidak ada yang perlu diopname.'],
                'periode' => null,
            ];
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

            $batch = [];
            foreach ($stocks as $stock) {
                $barangId = (int)$stock['idbarang'];
                $stokAwal = $this->stokAwalModel->getByIdBarang($barangId);
                $hppRow = $this->hppModel->getById($barangId);

                $batch[] = [
                    'tanggal'         => $tanggal,
                    'hpp'             => (float)($hppRow->hpp ?? 0),
                    'jumlah_real'     => null,
                    'jumlah_komp'     => (float)$stock['stok_akhir'],
                    'jumlah_selisih'  => null,
                    'satuan_terkecil' => $stokAwal->satuan_terkecil ?? null,
                    'barang_idbarang' => $barangId,
                    'unit_idunit'     => $unit,
                    'periode_id'      => $periodeId,
                ];
            }
            $this->db->table('stok_opname_draft')->insertBatch($batch);

            $summary = $this->refreshSummary($periodeId);
            $this->audit($periodeId, $unit, $tanggal, 'mulai', $userId, $summary);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal menyiapkan stok opname.'], 'periode' => null];
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'errors'  => [],
                'periode' => $this->model->find($periodeId),
                'jumlah'  => count($stocks),
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'periode' => null];
        }
    }

    /**
     * Simpan draft: update jumlah_real per barang, boleh dicicil dan boleh
     * dikosongkan kembali. Semua perubahan ditulis dalam satu statement per
     * batch supaya tidak ada queries N-per-form-submit.
     *
     * Nilai kosong berarti "belum diisi" (dikosongkan), bukan error.
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

        // Hanya barang yang benar-benar ada di draft periode ini yang boleh diubah.
        $known = [];
        foreach ($this->db->table('stok_opname_draft')
            ->select('barang_idbarang')
            ->where('periode_id', (int)$periode->id)
            ->get()
            ->getResultArray() as $row
        ) {
            $known[(int)$row['barang_idbarang']] = true;
        }

        $values = [];
        $errors = [];
        $diabaikan = 0;
        $invalid = 0;

        foreach ($rows as $barangId => $value) {
            $barangId = (int)$barangId;
            if ($barangId <= 0) {
                continue;
            }
            if (!isset($known[$barangId])) {
                $diabaikan++;
                continue;
            }

            $value = is_scalar($value) ? trim((string)$value) : '';
            if ($value === '') {
                $values[$barangId] = null; // dikosongkan -> belum terisi
                continue;
            }
            if (!is_numeric($value) || (float)$value < 0) {
                // Jumlah hasil hitung fisik tidak mungkin negatif.
                $invalid++;
                continue;
            }
            $values[$barangId] = (float)$value;
        }

        $errors = $this->simpanErrors($diabaikan, $invalid);

        if ($values === []) {
            return ['success' => true, 'errors' => $errors, 'saved' => 0];
        }

        $this->db->transBegin();

        try {
            $this->updateDraftValues((int)$periode->id, $values);
            $summary = $this->refreshSummary((int)$periode->id);
            $this->audit(
                (int)$periode->id, $unit, $tanggal, 'simpan', $userId, $summary,
                count($values) . ' barang'
            );

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal menyimpan draft.'], 'saved' => 0];
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'errors'  => $errors,
                'saved'   => count($values),
                'summary' => $summary,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'saved' => 0];
        }
    }

    /**
     * Bangun pesan input yang ditolak. Nilai yang ditolak sengaja dilaporkan
     * supaya operator tahu ada baris yang tidak tersimpan, bukan bingung kenapa
     * angkanya tidak berubah.
     */
    private function simpanErrors(int $diabaikan, int $invalid): array
    {
        $errors = [];
        if ($invalid > 0) {
            $errors[] = "$invalid nilai ditolak: jumlah real harus angka 0 atau lebih (hasil hitung fisik tidak mungkin negatif).";
        }
        if ($diabaikan > 0) {
            $errors[] = "$diabaikan baris diabaikan karena bukan barang dalam periode ini.";
        }
        return $errors;
    }

    /**
     * Tulis jumlah_real + jumlah_selisih sekaligus lewat satu UPDATE per batch.
     * jumlah_selisih dihitung di SQL dari jumlah_komp supaya tidak ada selisih
     * antara angka yang tersimpan dan angka yang dihitung ulang di PHP.
     */
    private function updateDraftValues(int $periodeId, array $values): void
    {
        foreach (array_chunk($values, self::BATCH_SIZE, true) as $chunk) {
            $selects = [];
            $ids = [];

            foreach ($chunk as $barangId => $real) {
                $ids[] = (int)$barangId;
                // "real" adalah keyword MySQL/MariaDB, jadi kolom turunan named `val`.
                $selects[] = $real === null
                    ? 'SELECT ' . (int)$barangId . ' AS id, CAST(NULL AS DECIMAL(15,2)) AS val'
                    : 'SELECT ' . (int)$barangId . ' AS id, CAST(' . (float)$real . ' AS DECIMAL(15,2)) AS val';
            }

            $sql = 'UPDATE stok_opname_draft d
                    JOIN (' . implode(' UNION ALL ', $selects) . ') v ON v.id = d.barang_idbarang
                    SET d.jumlah_real    = v.val,
                        d.jumlah_selisih = CASE WHEN v.val IS NULL THEN NULL ELSE v.val - d.jumlah_komp END
                    WHERE d.periode_id = ? AND d.barang_idbarang IN (' . implode(',', $ids) . ')';

            $this->db->query($sql, [$periodeId]);
        }
    }

    /**
     * Finalisasi: HANYA boleh bila seluruh barang berstok pada periode sudah terisi.
     * Operator unit memfinalisasi sendiri, tidak perlu approval.
     */
    public function finalize(int $unit, string $tanggal, int $userId, ?string $catatan = null): array
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

        // Hanya barang dengan stok komputer (jumlah_komp) != 0 yang wajib terisi
        $wajib = (int) $this->db->query(
            'SELECT COUNT(*) AS c FROM stok_opname_draft WHERE periode_id = ? AND jumlah_komp != 0',
            [(int)$periode->id]
        )->getRow()->c;

        $terisiWajib = (int) $this->db->query(
            'SELECT COUNT(*) AS c FROM stok_opname_draft WHERE periode_id = ? AND jumlah_komp != 0 AND jumlah_real IS NOT NULL',
            [(int)$periode->id]
        )->getRow()->c;

        if ($wajib > 0 && $terisiWajib < $wajib) {
            $kurang = $wajib - $terisiWajib;
            return [
                'success' => false,
                'errors'  => ["Finalisasi ditolak: masih ada $kurang dari $wajib barang berstok (stok > 0) yang belum diisi jumlah real."],
                'periode' => $periode,
            ];
        }

        $this->db->transBegin();

        try {
            $periodeId = (int)$periode->id;

            // Baris hasil finalisasi sebelumnya pada periode ini (setelah reopen)
            // tetap disimpan sebagai riwayat, tapi dikeluarkan dari stok aktif.
            $this->db->query(
                'UPDATE stok_opname SET is_reverted = 1 WHERE periode_id = ? AND is_reverted = 0',
                [$periodeId]
            );

            $this->db->query(
                'INSERT INTO stok_opname
                    (tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                     satuan_terkecil, barang_idbarang, unit_idunit, periode_id, is_reverted)
                 SELECT tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                        satuan_terkecil, barang_idbarang, unit_idunit, ?, 0
                 FROM stok_opname_draft WHERE periode_id = ?',
                [$periodeId, $periodeId]
            );

            $sum = $this->db->query(
                'SELECT COALESCE(SUM(jumlah_real),0) AS real_jml,
                        COALESCE(SUM(jumlah_selisih),0) AS selisih
                 FROM stok_opname WHERE periode_id = ? AND is_reverted = 0',
                [$periodeId]
            )->getRow();

            $this->db->table('stok_opname_periode')
                ->where('id', $periodeId)
                ->update([
                    'status'             => 'FINAL',
                    'jumlah_real'        => (float)$sum->real_jml,
                    'jumlah_selisih'     => (float)$sum->selisih,
                    'finalisasi_by'      => $userId,
                    'tanggal_finalisasi' => date('Y-m-d H:i:s'),
                    'catatan_finalisasi' => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 255) : null,
                    'updated_at'         => date('Y-m-d H:i:s'),
                ]);

            $this->audit(
                $periodeId, $unit, $tanggal, 'finalisasi', $userId,
                ['total' => $total, 'terisi' => $terisi],
                $catatan
            );

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return ['success' => false, 'errors' => ['Gagal finalisasi stok opname.'], 'periode' => null];
            }

            $this->db->transCommit();

            return ['success' => true, 'errors' => [], 'periode' => $this->model->find($periodeId)];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'errors' => ['Terjadi kesalahan: ' . $e->getMessage()], 'periode' => null];
        }
    }

    /**
     * Reopen: kembali DRAFT untuk dikoreksi.
     *
     * Baris final lama di-MARK is_reverted, bukan dihapus — nilai historis tetap
     * bisa ditelusuri, dan perubahan terhadap stok_akhir selalu punya jejak.
     * Wajib menyertakan alasan.
     */
    public function reopen(int $unit, string $tanggal, int $userId, ?string $alasan = null): array
    {
        $periode = $this->periode($unit, $tanggal);
        if (!$periode) {
            return ['success' => false, 'errors' => ['Periode stok opname tidak ditemukan.'], 'periode' => null];
        }
        if ($periode->status === 'DRAFT') {
            return ['success' => false, 'errors' => ['Periode masih berstatus DRAFT.'], 'periode' => null];
        }

        $alasan = $alasan !== null ? trim($alasan) : '';
        if ($alasan === '') {
            return ['success' => false, 'errors' => ['Alasan reopen wajib diisi.'], 'periode' => $periode];
        }
        $alasan = mb_substr($alasan, 0, 255);

        $this->db->transBegin();

        try {
            $periodeId = (int)$periode->id;

            $this->db->query(
                'UPDATE stok_opname
                 SET is_reverted = 1, reverted_by = ?, reverted_at = NOW(), revert_alasan = ?
                 WHERE periode_id = ? AND is_reverted = 0',
                [$userId, $alasan, $periodeId]
            );

            $this->db->table('stok_opname_periode')
                ->where('id', $periodeId)
                ->update([
                    'status'             => 'DRAFT',
                    'reopen_by'          => $userId,
                    'tanggal_reopen'     => date('Y-m-d H:i:s'),
                    'alasan_reopen'      => $alasan,
                    'updated_at'         => date('Y-m-d H:i:s'),
                ]);

            $summary = $this->refreshSummary($periodeId);
            $this->audit($periodeId, $unit, $tanggal, 'reopen', $userId, $summary, $alasan);

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

    /**
     * Hitung ulang total_barang / terisi_barang / jumlah_* dari draft.
     *
     * Terisi dihitung dari draft, bukan dari baris final, supaya ringkasan
     * periode selalu mencerminkan pekerjaan yang sedang berjalan.
     */
    private function refreshSummary(int $periodeId): array
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL THEN 1 ELSE 0 END),0) AS terisi,
                    COALESCE(SUM(jumlah_komp),0) AS komp,
                    COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL THEN jumlah_real ELSE 0 END),0) AS real_jml,
                    COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL THEN jumlah_selisih ELSE 0 END),0) AS selisih
             FROM stok_opname_draft WHERE periode_id = ?',
            [$periodeId]
        )->getRow();

        $summary = [
            'total'   => (int)($row->total ?? 0),
            'terisi'  => (int)($row->terisi ?? 0),
            'komp'    => (float)($row->komp ?? 0),
            'real'    => (float)($row->real_jml ?? 0),
            'selisih' => (float)($row->selisih ?? 0),
        ];

        $this->db->table('stok_opname_periode')
            ->where('id', $periodeId)
            ->update([
                'total_barang'   => $summary['total'],
                'terisi_barang'  => $summary['terisi'],
                'jumlah_komp'    => $summary['komp'],
                'jumlah_real'    => $summary['real'],
                'jumlah_selisih' => $summary['selisih'],
            ]);

        return $summary;
    }

    private function audit(
        int $periodeId,
        int $unit,
        string $tanggal,
        string $aksi,
        ?int $actorId,
        array $summary,
        ?string $catatan = null
    ): void {
        $this->db->table('stok_opname_audit')->insert([
            'periode_id'     => $periodeId,
            'unit_idunit'    => $unit,
            'tanggal'        => $tanggal,
            'aksi'           => $aksi,
            'actor_id'       => $actorId,
            'jumlah_barang'  => $summary['total'] ?? 0,
            'jumlah_terisi'  => $summary['terisi'] ?? 0,
            'catatan'        => $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 255) : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
