<?php

namespace App\Services\Kpi;

use App\Models\ModelAsetKpi;
use App\Models\ModelAuditAsetPeriode;
use App\Models\ModelAuditAsetItem;

/**
 * AsetKpiService — data & skor KPI KONTROL_ASET Kepala Toko.
 *
 * Dua bagian yang benar-benar terpisah:
 *
 * 1. ASSET MASTER (Admin Center / Root / Direktur / Manager)
 *    - Mengelola baseline aset: unit, nama, kode (AST{unit}-XXXX), quantity,
 *      is_active (aktif/nonaktif), keterangan.
 *    - Quantity master TIDAK PERNAH berubah karena hasil audit.
 *
 * 2. KONTROL ASET (SPV)
 *    - Audit bulanan per unit (satu periode FINAL per bulan).
 *    - Per aset: quantity_ditemukan (sumber Existence), perawatan
 *      TERAWAT/TIDAK_TERAWAT (sumber Maintenance), KONDISI hanya informasi.
 *    - Дitemukan = baseline - ditemukan (otomatis, bukan pilihan kondisi).
 *
 * Skor KPI (nilai 0-100, null = Belum Diaudit):
 *   Existence   = sum(ditemukan) / sum(quantity aktif) × 100
 *   Maintenance = jumlah aset TERAWAT / sum(quantity aktif) × 100
 *   Final       = Existence × 70% + Maintenance × 30%
 *
 * Aturan:
 *   - Audit harus LENGKAP (semua aset aktif punya item) & status FINAL.
 *   - TANPA fallback ke audit bulan sebelumnya.
 *   - Bulan belum diaudit/final → null.
 *   - Kondisi fisik TIDAK ikut menghitung skor.
 */
class AsetKpiService
{
    public const KONDISI_LIST = ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Rusak / Perlu Diganti'];
    public const PERAWATAN_LIST = ['TERAWAT', 'TIDAK_TERAWAT'];

    protected function asetModel(): ModelAsetKpi
    {
        return new ModelAsetKpi();
    }

    protected function periodeModel(): ModelAuditAsetPeriode
    {
        return new ModelAuditAsetPeriode();
    }

    protected function itemModel(): ModelAuditAsetItem
    {
        return new ModelAuditAsetItem();
    }

    /**
     * Unit yang boleh dikelola:
     *   - 0 / 1 / 2 (Admin Center / Root / Direktur) → semua unit.
     *   - 40 (SPV) → unit di spv_units, fallback unit sendiri.
     *   - selain itu → [] (tidak berhak).
     *
     * @return int[]|null null = semua unit.
     */
    public function scopeUnits(int $myRole, int $myUnit, int $myId): ?array
    {
        if (in_array($myRole, [0, 1, 2, 34], true)) {
            return null;
        }

        if ($myRole === 40) {
            $db = \Config\Database::connect();
            $mappings = $db->table('spv_units')
                ->where('spv_id', $myId)
                ->get()
                ->getResultArray();

            $units = !empty($mappings)
                ? array_map('intval', array_column($mappings, 'unit_id'))
                : [(int)$myUnit];

            return array_values(array_unique($units));
        }

        return [];
    }

    /* ════════════════════════ 1. ASSET MASTER ════════════════════════ */

    /**
     * Seluruh aset master sebuah unit (aktif & nonaktif).
     *
     * @return array each: id, unit, asset, kode_aset, quantity, harga, is_active, keterangan
     */
    public function masterAssets(int $unit): array
    {
        $rows = $this->asetModel()
            ->where('unit', $unit)
            ->orderBy('kode_aset', 'ASC')
            ->findAll();

        return array_map(fn($a) => [
            'id'         => (int)$a->id,
            'unit'       => (int)$a->unit,
            'asset'      => (string)$a->asset,
            'kode_aset'  => (string)$a->kode_aset,
            'quantity'   => (int)$a->quantity,
            'harga'      => $a->harga !== null ? (float)$a->harga : null,
            'is_active'  => (int)$a->is_active === 1,
            'keterangan' => (string)$a->keterangan,
        ], $rows);
    }

    /**
     * Seluruh aset master + info audit terakhir (untuk halaman Asset Master).
     *
     * Untuk setiap aset dicari audit FINAL TERBARU yang memuat aset tsb
     * (aset nonaktif tetap menampilkan audit terakhir saat masih aktif).
     *
     * @return array each: id, unit, asset, kode_aset, quantity, harga, is_active, keterangan,
     *                     last_audit => [periode_id, bulan, tahun, tanggal_audit, status,
     *                                   quantity_ditemukan, hilang, kondisi, perawatan] | null
     */
    public function masterAssetsWithLastAudit(int $unit): array
    {
        $assets = $this->masterAssets($unit);

        // Semua periode FINAL unit ini (terbaru dulu).
        $periodes = $this->periodeModel()
            ->where('unit', $unit)
            ->where('status', 'FINAL')
            ->orderBy('tahun', 'DESC')
            ->orderBy('bulan', 'DESC')
            ->findAll();

        // Cache items per periode.
        $itemsByPeriode = [];
        foreach ($periodes as $p) {
            $itemsByPeriode[(int)$p->id] = $this->itemModel()->itemsByPeriode((int)$p->id);
        }

        foreach ($assets as &$a) {
            $asetId = $a['id'];
            $a['last_audit'] = null;

            foreach ($periodes as $p) {
                $periodeId = (int)$p->id;
                $item = $itemsByPeriode[$periodeId][$asetId] ?? null;
                if ($item && $item->quantity_ditemukan !== null) {
                    $ditemukan = (int)$item->quantity_ditemukan;
                    $a['last_audit'] = [
                        'periode_id'         => $periodeId,
                        'bulan'              => (int)$p->bulan,
                        'tahun'              => (int)$p->tahun,
                        'tanggal_audit'      => (string)$p->tanggal_audit,
                        'status'             => (string)$p->status,
                        'quantity_ditemukan' => $ditemukan,
                        'hilang'             => $a['quantity'] - $ditemukan,
                        'kondisi'            => (string)$item->kondisi,
                        'perawatan'          => (string)$item->perawatan,
                    ];
                    break;
                }
            }
        }

        return $assets;
    }

    /**
     * Tambah aset MASTER baru. Kode otomatis AST{unit}-{4 digit acak unik}.
     *
     * @return array ['success'=>bool, 'errors'=>string[], 'data'=>object|null]
     */
    public function addMaster(int $unit, string $asset, int $quantity, int $createdBy, string $keterangan = '', ?float $harga = null, ?string $kodeAset = null): array
    {
        $asset = trim($asset);
        if ($asset === '') {
            return ['success' => false, 'errors' => ['Nama aset tidak boleh kosong.'], 'data' => null];
        }
        if ($quantity < 1) {
            return ['success' => false, 'errors' => ['Quantity master minimal 1.'], 'data' => null];
        }

        $kode = trim((string)$kodeAset);
        if ($kode === '') {
            $kode = $this->generateKode($unit);
        }

        $model = $this->asetModel();
        if (!$model->insert([
            'unit'       => $unit,
            'asset'      => $asset,
            'kode_aset'  => $kode,
            'quantity'   => $quantity,
            'harga'      => $harga,
            'is_active'  => 1,
            'keterangan' => trim($keterangan) === '' ? null : trim($keterangan),
            'created_by' => $createdBy,
        ])) {
            return ['success' => false, 'errors' => ['Gagal menyimpan aset. Pastikan kode unik.'], 'data' => null];
        }

        return ['success' => true, 'errors' => [], 'data' => $model->find($model->getInsertID())];
    }

    /**
     * Update master (field MASTER saja — tidak menyentuh hasil audit).
     */
    public function updateMaster(int $id, int $unit, string $asset, string $kodeAset, int $quantity, string $keterangan = '', ?float $harga = null, ?bool $isActive = null): array
    {
        $model = $this->asetModel();
        $row = $model->find($id);
        if (!$row) {
            return ['success' => false, 'errors' => ['Aset tidak ditemukan.']];
        }

        $asset = trim($asset);
        $kode  = trim($kodeAset);
        if ($asset === '' || $kode === '') {
            return ['success' => false, 'errors' => ['Nama aset dan kode wajib diisi.']];
        }
        if ($quantity < 1) {
            return ['success' => false, 'errors' => ['Quantity master minimal 1.']];
        }

        // Kode harus unik (selain milik sendiri).
        if ($model->where('kode_aset', $kode)->where('id !=', $id)->countAllResults() > 0) {
            return ['success' => false, 'errors' => ['Kode aset sudah dipakai aset lain.']];
        }

        $payload = [
            'unit'       => $unit,
            'asset'      => $asset,
            'kode_aset'  => $kode,
            'quantity'   => $quantity,
            'harga'      => $harga,
            'keterangan' => trim($keterangan) === '' ? null : trim($keterangan),
        ];
        if ($isActive !== null) {
            $payload['is_active'] = $isActive ? 1 : 0;
        }

        if (!$model->update($id, $payload)) {
            return ['success' => false, 'errors' => ['Gagal memperbarui aset master.']];
        }

        return ['success' => true, 'errors' => [], 'data' => $model->find($id)];
    }

    /**
     * Aktifkan / nonaktifkan baseline aset.
     */
    public function toggleMaster(int $id, bool $active): array
    {
        $row = $this->asetModel()->find($id);
        if (!$row) {
            return ['success' => false, 'errors' => ['Aset tidak ditemukan.']];
        }

        $this->asetModel()->update($id, ['is_active' => $active ? 1 : 0]);
        return ['success' => true, 'errors' => []];
    }

    /**
     * Hapus master secara permanen. Ditolak jika sudah pernah diaudit.
     */
    public function deleteMaster(int $id): array
    {
        $row = $this->asetModel()->find($id);
        if (!$row) {
            return ['success' => false, 'errors' => ['Aset tidak ditemukan.']];
        }

        $audited = (new ModelAuditAsetItem())
            ->where('aset_kpi_id', $id)
            ->countAllResults();

        if ($audited > 0) {
            return ['success' => false, 'errors' => ['Aset sudah pernah diaudit — nonaktifkan lewat status aktif/nonaktif saja.']];
        }

        $this->asetModel()->delete($id);
        return ['success' => true, 'errors' => []];
    }

    /* ════════════════════════ 2. KONTROL ASET ════════════════════════ */

    /**
     * Data halaman Kontrol Aset: aset aktif + item periode + ringkasan.
     *
     * @return array
     *   assets       => list per aset (+ item), masing-masing:
     *                    id, kode_aset, asset, quantity, ditemukan, hilang,
     *                    kondisi, perawatan, keterangan, audited(bool)
     *   periode      => object|null
     *   summary      => totalAset, totalTerdaftar, totalDitemukan, totalHilang,
     *                    progress(%), auditedCount, existence, maintenance,
     *                    final, complete(finalizable), statusText
     */
    public function controlData(int $unit, int $bulan, int $tahun): array
    {
        $assets = $this->asetModel()
            ->where('unit', $unit)
            ->where('is_active', 1)
            ->orderBy('kode_aset', 'ASC')
            ->findAll();

        $periode = $this->periodeModel()
            ->where('unit', $unit)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first() ?: null;

        $items = $periode
            ? $this->itemModel()->itemsByPeriode((int)$periode->id)
            : [];

        $baseline = 0;
        $ditemukan = 0;
        $hilangTotal = 0;
        $terawatQty = 0;
        $auditedCount = 0;
        $rows = [];

        foreach ($assets as $a) {
            $qty = (int)$a->quantity;
            $baseline += $qty;

            $item = $items[(int)$a->id] ?? null;
            $audited = $item !== null && $item->quantity_ditemukan !== null;

            $ditemukanRow = $audited ? (int)$item->quantity_ditemukan : 0;
            $selisih = $qty - $ditemukanRow;

            if ($audited) {
                $ditemukan += $ditemukanRow;
                $hilangTotal += max(0, $selisih);
                $auditedCount++;

                // Maintenance = qty terawat / baseline
                if ((string)$item->perawatan === 'TERAWAT') {
                    $terawatQty += $ditemukanRow;
                }
            }

            $rows[] = [
                'id'              => (int)$a->id,
                'kode_aset'       => (string)$a->kode_aset,
                'asset'           => (string)$a->asset,
                'quantity'        => $qty,
                'ditemukan'       => $audited ? $ditemukanRow : null,
                'hilang'          => $selisih,
                'kondisi'         => $item ? (string)$item->kondisi : '',
                'perawatan'       => $item ? (string)$item->perawatan : '',
                'keterangan'      => $item ? (string)$item->keterangan : '',
                'audited'         => $audited,
            ];
        }

        $complete = $auditedCount === count($assets);
        $final = $this->kpiScore($unit, $bulan, $tahun);

        $existence = $complete && $baseline > 0
            ? round(min($ditemukan / $baseline * 100.0, 100.0), 2)
            : null;
        $maintenance = $complete && $baseline > 0
            ? round(min($terawatQty / $baseline * 100.0, 100.0), 2)
            : null;

        return [
            'assets'  => $rows,
            'periode' => $periode,
            'summary' => [
                'totalAset'      => count($assets),
                'totalTerdaftar' => $baseline,
                'totalDitemukan' => $ditemukan,
                'totalHilang'    => $hilangTotal,
                'auditedCount'   => $auditedCount,
                'progress'       => count($assets) > 0 ? round($auditedCount / count($assets) * 100.0) : 0,
                'existence'      => $existence,
                'maintenance'    => $maintenance,
                'final'          => $final,
                'complete'       => $complete,
                'status'         => $periode ? (string)$periode->status : 'BELUM_DIMULAI',
            ],
        ];
    }

    /**
     * Simpan (DRAFT) item audit bulanan. Master quantity tidak disentuh.
     *
     * $rows = [ aset_kpi_id => ['ditemukan'=>int|null, 'kondisi'=>, 'perawatan'=>, 'keterangan'=>] ]
     */
    public function saveControl(int $unit, int $bulan, int $tahun, string $tanggalAudit, int $auditorId, array $rows): array
    {
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return ['saved' => 0, 'errors' => ['Periode tidak valid.']];
        }
        if (strtotime($tanggalAudit) === false) {
            return ['saved' => 0, 'errors' => ['Tanggal audit tidak valid.']];
        }

        $activeIds = [];
        $assets = $this->asetModel()
            ->where('unit', $unit)
            ->where('is_active', 1)
            ->whereIn('id', array_keys($rows))
            ->findAll();
        foreach ($assets as $a) {
            $activeIds[(int)$a->id] = $a;
        }

        $periode = $this->periodeModel()->getOrCreatePeriode($unit, $bulan, $tahun, $auditorId, $tanggalAudit);
        $model = $this->itemModel();
        $saved = 0;
        $errors = [];

        foreach ($rows as $asetId => $v) {
            $asetId = (int)$asetId;
            if (!isset($activeIds[$asetId])) {
                $errors[] = 'Aset tidak valid untuk unit ini.';
                continue;
            }

            $ditemukan = isset($v['ditemukan']) && $v['ditemukan'] !== '' && $v['ditemukan'] !== null
                ? (int)$v['ditemukan']
                : null;
            if ($ditemukan !== null && $ditemukan < 0) {
                $errors[] = 'Quantity ditemukan ' . $activeIds[$asetId]->asset . ' tidak boleh negatif.';
                continue;
            }

            $kondisi   = trim((string)($v['kondisi'] ?? ''));
            if ($kondisi !== '' && !in_array($kondisi, self::KONDISI_LIST, true)) {
                $errors[] = 'Kondisi tidak valid utk ' . $activeIds[$asetId]->asset . '.';
                continue;
            }

            $perawatan = trim((string)($v['perawatan'] ?? ''));
            if ($perawatan !== '' && !in_array($perawatan, self::PERAWATAN_LIST, true)) {
                $errors[] = 'Perawatan tidak valid utk ' . $activeIds[$asetId]->asset . '.';
                continue;
            }

            $keterangan = trim((string)($v['keterangan'] ?? ''));

            // Skip baris kosong total kecuali sudah ada item (biarkan apa adanya).
            if ($ditemukan === null && $perawatan === '' && $kondisi === '' && $keterangan === '') {
                continue;
            }

            if ($model->upsertItem((int)$periode->id, $asetId, [
                'quantity_ditemukan' => $ditemukan,
                'kondisi'            => $kondisi === '' ? null : $kondisi,
                'perawatan'          => $perawatan === '' ? null : $perawatan,
                'keterangan'         => $keterangan === '' ? null : $keterangan,
            ])) {
                $saved++;
            } else {
                $errors[] = 'Gagal simpan item ' . $activeIds[$asetId]->asset . '.';
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    /**
     * Finalisasi audit: wajib lengkap & valid, status periode → FINAL.
     */
    public function finalizeControl(int $unit, int $bulan, int $tahun, string $tanggalAudit, int $auditorId): array
    {
        $assets = $this->asetModel()
            ->where('unit', $unit)
            ->where('is_active', 1)
            ->findAll();

        if (empty($assets)) {
            return ['success' => false, 'errors' => ['Tidak ada aset aktif utk diaudit.']];
        }
        if (strtotime($tanggalAudit) === false) {
            return ['success' => false, 'errors' => ['Tanggal audit tidak valid.']];
        }

        $periode = $this->periodeModel()->getOrCreatePeriode($unit, $bulan, $tahun, $auditorId, $tanggalAudit);
        $items = $this->itemModel()->itemsByPeriode((int)$periode->id);

        $belumAudit = [];
        $belumTerawat = [];
        foreach ($assets as $a) {
            $item = $items[(int)$a->id] ?? null;
            if (!$item || $item->quantity_ditemukan === null) {
                $belumAudit[] = $a->asset . ' (' . $a->kode_aset . ')';
                continue;
            }
            if (!in_array((string)$item->perawatan, self::PERAWATAN_LIST, true)) {
                $belumTerawat[] = $a->asset . ' (' . $a->kode_aset . ')';
            }
        }

        if (!empty($belumAudit)) {
            return ['success' => false, 'errors' => ['Audit belum lengkap: ' . implode(', ', array_slice($belumAudit, 0, 5)) . '.']];
        }
        if (!empty($belumTerawat)) {
            return ['success' => false, 'errors' => ['Status perawatan belum lengkap: ' . implode(', ', array_slice($belumTerawat, 0, 5)) . '.']];
        }

        $this->periodeModel()->update((int)$periode->id, [
            'status'        => ModelAuditAsetPeriode::STATUS_FINAL,
            'tanggal_audit' => $tanggalAudit,
            'auditor_id'    => $auditorId,
        ]);

        return ['success' => true, 'errors' => []];
    }

    /**
     * Buka kembali audit yang sudah FINAL (untuk perbaikan) → DRAFT.
     */
    public function reopenControl(int $unit, int $bulan, int $tahun): array
    {
        $periode = $this->periodeModel()
            ->where('unit', $unit)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if (!$periode) {
            return ['success' => false, 'errors' => ['Periode audit tidak ditemukan.']];
        }

        $this->periodeModel()->update((int)$periode->id, ['status' => ModelAuditAsetPeriode::STATUS_DRAFT]);
        return ['success' => true, 'errors' => []];
    }

    /**
     * Skor KPI KONTROL_ASET utk (unit, bulan, tahun).
     *
     * null = BeLum Diaudit: belum ada periode FINAL bulan itu, atau audit belum
     * lengkap. TANPA fallback ke audit bulan sebelumnya.
     */
    public function kpiScore(int $unit, int $bulan, int $tahun): ?float
    {
        $assets = $this->asetModel()
            ->where('unit', $unit)
            ->where('is_active', 1)
            ->findAll();

        if (empty($assets)) {
            return null;
        }

        $baseline = 0;
        foreach ($assets as $a) {
            $baseline += (int)$a->quantity;
        }
        if ($baseline <= 0) {
            return null;
        }

        $periodeFinal = $this->periodeModel()->findFinalPeriode($unit, $bulan, $tahun);
        if (!$periodeFinal) {
            return null;
        }

        $items = $this->itemModel()->itemsByPeriode((int)$periodeFinal->id);

        // Audit harus lengkap: setiap aset aktif punya item.
        $ditemukan = 0;
        $terawatQty = 0;
        foreach ($assets as $a) {
            $item = $items[(int)$a->id] ?? null;
            if (!$item || $item->quantity_ditemukan === null) {
                return null;
            }
            $ditemukan += (int)$item->quantity_ditemukan;

            // Maintenance = jumlah QUANTITY aset TERAWAT / baseline × 100
            if ((string)$item->perawatan === 'TERAWAT') {
                $terawatQty += (int)$item->quantity_ditemukan;
            }
        }

        $existence   = round(min($ditemukan / $baseline * 100.0, 100.0), 4);
        $maintenance = round(min($terawatQty / $baseline * 100.0, 100.0), 4);
        $final       = round($existence * 0.7 + $maintenance * 0.3, 2);

        return $final;
    }

    /**
     * Jumlah aset (baris master aktif) berperawatan TERAWAT pada periode.
     */
    protected function countTerawat(?object $periode, array $items, array $assets): int
    {
        if (!$periode) {
            return 0;
        }
        $count = 0;
        foreach ($assets as $a) {
            $item = $items[(int)$a->id] ?? null;
            if ($item && (string)$item->perawatan === 'TERAWAT') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generate kode aset unik: AST{unit}-{4 digit acak}.
     */
    protected function generateKode(int $unit): string
    {
        $model = $this->asetModel();
        for ($i = 0; $i < 20; $i++) {
            $kode = sprintf('AST%d-%04d', $unit, random_int(0, 9999));
            if ($model->where('kode_aset', $kode)->countAllResults() === 0) {
                return $kode;
            }
        }

        return sprintf('AST%d-%04d', $unit, (int)date('His') % 10000);
    }
}

