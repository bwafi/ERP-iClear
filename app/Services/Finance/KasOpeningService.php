<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Models\ModelOpeningKas;

/**
 * OPENING KAS — baseline SALDO RIIL laci yang ditetapkan Finance pada cut-off.
 *
 * MODEL (sama secara konsep dengan opening bank, tanpa bank statement)
 * --------------------------------------------------------------------
 *     saldo_buku_kas(akun) = opening_kas(akun, cutoff) + net movement sejak cutoff+1
 *
 * Opening KAS adalah BASELINE REAL BARU: saldo fisik aktual laci pada akhir
 * tanggal cut-off yang diinput user. Kali ini TIDAK ada perbandingan dengan
 * saldo ERP lama, tidak ada `real_cash`, tidak ada `selisih`, dan tidak ada
 * verifikasi/status. Begitu baris opening ada pada tanggal cut-off, angka
 * itulah titik awal saldo kas untuk seluruh transaksi setelah cut-off.
 *
 * ATURAN YANG DIJAGA
 * ------------------
 * 1. Opening TIDAK PERNAH menjadi movement. Tidak ada baris
 *    `transaksi_kas_bank` yang ditulis service ini, dan tidak ada opening
 *    yang dijumlahkan ke net movement. Opening masuk lewat kolom, bukan lewat
 *    baris.
 * 2. Opening dihitung SATU KALI. Satu rekening hanya punya satu baris opening
 *    per tanggal (unique di database), dan baris itulah yang dibaca. Tidak ada
 *    penjumlahan antar beberapa baris.
 * 3. `tutup_kasir` TIDAK dibaca sama sekali. Saldo awal KAS bukan berasal dari
 *    `akhir_cash` maupun `cash_laci` — keduanya urusan modul Tutup Kasir yang
 *    terpisah dan tidak ikut diubah di sini.
 * 4. Opening hanya sah pada tanggal cut-off. Tanggal lain berarti angka yang
 *    diinput bukan baseline periode ini.
 */
class KasOpeningService
{
    protected ModelAkunKasBank $akunModel;
    protected ModelOpeningKas $openingModel;

    public function __construct(?ModelAkunKasBank $akun = null, ?ModelOpeningKas $opening = null)
    {
        $this->akunModel    = $akun ?? new ModelAkunKasBank();
        $this->openingModel = $opening ?? new ModelOpeningKas();
    }

    // =====================================================================
    // 1. PEMBACAAN BASELINE
    // =====================================================================

    /**
     * Semua baris opening pada tanggal cut-off, seluruh rekening.
     *
     * Dipakai halaman daftar laci supaya operator melihat laci mana yang
     * sudah punya baseline dan mana yang belum. Rekening yang TIDAK ada
     * barisnya sengaja TIDAK dikarang sebagai nol di sini.
     *
     * @return object[]
     */
    public function seluruhOpeningPerAkun(?string $tanggal = null): array
    {
        $tanggal ??= FinanceScopeService::cutoffDate();

        return db_connect()->table('opening_kas')
            ->where('tanggal', $tanggal)
            ->get()
            ->getResult();
    }

    /**
     * Baris opening pada (akun, tanggal) persis.
     *
     * Baris inilah yang berarti "baseline". Kalau tidak ada, opening belum
     * ditetapkan dan itu harus dibaca sebagai "belum diisi", bukan nol.
     *
     * @return object|null
     */
    public function openingAt(int $akunId, ?string $tanggal = null)
    {
        $tanggal ??= FinanceScopeService::cutoffDate();

        return $this->openingModel->getByAkunTanggal($akunId, $tanggal);
    }

    /**
     * Baris opening yang berlaku: tepat di tanggal itu, atau terakhir yang
     * <= tanggal itu.
     *
     * Fallback ini meniru statementAt() supaya kedua jenis baseline punya
     * perilaku baca yang sama. Yang BEDA: fallback TIDAK boleh dipakai sebagai
     * alasan menyatakan baseline ada — untuk itu pakai openingAda()/openingAt()
     * pada tanggal cut-off.
     *
     * @return object|null
     */
    public function openingBerlaku(int $akunId, ?string $tanggal = null)
    {
        $tanggal ??= FinanceScopeService::cutoffDate();
        $db        = db_connect();

        $tepat = $this->openingAt($akunId, $tanggal);
        if ($tepat !== null) {
            return $tepat;
        }

        return $db->table('opening_kas')
            ->where('akun_kas_bank_id', $akunId)
            ->where('tanggal <=', $tanggal)
            ->orderBy('tanggal', 'DESC')
            ->get()
            ->getRow();
    }

    /**
     * Nilai opening yang dipakai pembuka saldo buku. 0 kalau belum ada
     * baris — dan pemanggil wajib membedakan itu lewat openingAda().
     */
    public function opening(int $akunId, ?string $tanggal = null): int
    {
        $row = $this->openingBerlaku($akunId, $tanggal);

        return $row === null ? 0 : (int) $row->opening;
    }

    /**
     * Apakah baseline laci tersedia: baris opening pada tanggal cut-off ada.
     *
     * Inilah satu-satunya syarat yang dipakai guard Setor/Penarikan — opening
     * yang sudah tersimpan langsung sah sebagai acuan; tidak ada verifikasi.
     */
    public function openingAda(int $akunId, ?string $tanggal = null): bool
    {
        return $this->openingAt($akunId, $tanggal) !== null;
    }

    // =====================================================================
    // 2. INPUT OPENING (Finance)
    // =====================================================================

    /**
     * Finance menetapkan opening KAS = SALDO RIIL fisik laci pada tanggal
     * cut-off. Nilai ini langsung menjadi baseline periode.
     *
     * Opening hanya boleh pada tanggal cut-off, sama seperti statement bank:
     * tanggal lain berarti angka yang diinput bukan baseline periode ini.
     * Mengubah angka opening hanya mengganti baseline; saldo setelah cut-off
     * tetap dihitung dari opening baru + seluruh mutasi setelah cut-off.
     *
     * @param int      $opening  Saldo riil fisik laci yang diinput user
     * @param int|null $userId   User yang menginput
     * @return array{ok:bool, alasan:string, data:array<string,mixed>}
     */
    public function inputOpening(
        int $akunId,
        int $opening,
        ?string $keterangan = null,
        ?int $userId = null,
        ?string $tanggal = null
    ): array {
        $tanggal ??= FinanceScopeService::cutoffDate();

        $akun = $this->akunModel->find($akunId);
        if ($akun === null || (string) $akun->status !== 'aktif') {
            return $this->gagal('Rekening kas tidak ditemukan / tidak aktif.');
        }

        if (strtoupper((string) $akun->tipe) !== 'KAS') {
            return $this->gagal(
                'Opening KAS hanya untuk rekening tipe KAS. Saldo awal rekening bank '
                . 'tetap lewat statement bank di saldo_awal_kas_bank.'
            );
        }

        $unitId = (int) ($akun->unit_id ?? 0);
        if ($unitId <= 0) {
            return $this->gagal(
                'Rekening laci kas ini tidak punya unit pemilik, jadi opening tidak bisa '
                . 'ditempatkan. Perbaiki unit pemilik dulu.'
            );
        }

        if ($tanggal !== FinanceScopeService::cutoffDate()) {
            return $this->gagal(sprintf(
                'Tanggal opening KAS harus %s (tanggal cut-off), sama dengan opening bank. '
                . 'Tanggal yang dipilih: %s.',
                FinanceScopeService::cutoffDate(),
                $tanggal
            ));
        }

        if ($opening < 0) {
            return $this->gagal('Opening KAS tidak boleh negatif. Saldo laci yang minus harus lewat koreksi, bukan opening minus.');
        }

        $now    = date('Y-m-d H:i:s');
        $existing = $this->openingAt($akunId, $tanggal);

        $data = [
            'akun_kas_bank_id' => $akunId,
            'unit_id'          => $unitId,
            'tanggal'          => $tanggal,
            'opening'          => $opening,
            'keterangan'       => $keterangan,
            'updated_at'       => $now,
        ];

        if ($existing !== null) {
            $data['input_by'] = $userId ?? $existing->input_by;

            $this->openingModel->update((int) $existing->id, $data);

            return [
                'ok'     => true,
                'alasan' => '',
                'data'   => ['id' => (int) $existing->id, 'perubahan' => 'diperbarui'],
            ];
        }

        $data['input_by']   = $userId;
        $data['created_at'] = $now;

        $id = (int) $this->openingModel->insert($data, true);

        return [
            'ok'     => true,
            'alasan' => '',
            'data'   => ['id' => $id, 'perubahan' => 'disimpan'],
        ];
    }

    // =====================================================================

    /**
     * @return array{ok:bool, alasan:string, data:array<string,mixed>}
     */
    private function gagal(string $alasan): array
    {
        return ['ok' => false, 'alasan' => $alasan, 'data' => []];
    }
}