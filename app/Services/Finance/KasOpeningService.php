<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Models\ModelOpeningKas;

/**
 * OPENING KAS — baseline yang ditetapkan Finance, diverifikasi terhadap real cash.
 *
 * MODEL (sama secara konsep dengan opening bank, tanpa bank statement)
 * --------------------------------------------------------------------
 *     saldo_buku_kas(akun) = opening_kas(akun, cutoff) + net movement sejak cutoff+1
 *
 * Tiga angka yang TIDAK BOLEH dicampur:
 *
 *   opening  = BASELINE. Angka yang menetapkan Finance untuk saldo riil laci
 *              pada akhir tanggal cut-off. Inilah satu-satunya angka yang
 *              dipakai sebagai pembuka saldo buku.
 *
 *   real_cash = REAL/ACTUAL. Angka hasil HITUNG LACI saat Tutup Kasir pada
 *              tanggal cut-off yang sama (tutup_kasir.akhir_cash). Dipakai
 *              untuk rekonsiliasi, tidak pernah menjadi sumber opening.
 *
 *   selisih  = real_cash - opening. 0 berarti cocok. Nilai selain 0 berarti
 *              ada yang perlu dijelaskan manusia — bukan sesuatu yang boleh
 *              dirapikan otomatis.
 *
 * Kenapa `tutup_kasir.akhir_cash` BUKAN sumber opening:
 * `akhir_cash` adalah apa yang ada di laci, sedangkan opening adalah keputusan
 * Finance atas saldo riil. Kalau yang kedua diambil otomatis dari yang pertama,
 * tidak ada angka yang pernahNeeds penjelasanFinance dan setiap selisih hilang
 * tanpa jejak. Di sini keduanya dicatat terpisah lalu dibandingkan.
 *
 * Kenapa tidak pakai `saldo_awal_kas_bank`:
 * tabel itu adalah statement reference — koran bank. KAS tidak punya koran,
 * dan membuatnya berarti sistem mengarang bank statement palsu.
 *
 * ATURAN YANG DIJAGA
 * ------------------
 * 1. Opening TIDAK PERNAH menjadi movement. Tidak ada baris
 *    `transaksi_kas_bank` yang ditulis service ini, dan tidak ada opening yang
 *    dijumlahkan ke net movement. Opening masuk lewat kolom, bukan lewat baris.
 * 2. Opening dihitung SATU KALI. Satu rekening hanya punya satu baris opening
 *    per tanggal (unique di database), dan baris itulah yang dibaca. Tidak ada
 *    penjumlahan antar beberapa baris.
 * 3. Mengubah opening MEMBATALKAN verifikasi. Kalau tidak, orang bisa
 *    menaikkan/menurunkan opening sampai cocok dengan real cash lalu
 *    memverifikasi — verifikasi jadi tidak bermakna.
 * 4. Verifikasi tanpa closing pada tanggal cut-off DITOLAK. Tidak ada real
 *    cash berarti tidak ada yang bisa dicocokkan; menebak angka laci adalah
 *    mengarang data.
 * 5. `tutup_kasir` hanya dibaca. Tidak ada baris yang diubah atau dibuat.
 */
class KasOpeningService
{
    /** Opening sudah diinput, belum dicocokkan dengan real cash. */
    public const STATUS_BELUM = 'BELUM_VERIFIKASI';

    /** Opening sudah dicocokkan dengan real cash dan selisihnya 0. */
    public const STATUS_SUDAH = 'TERVERIFIKASI';

    /** Sudah dicocokkan, tapi selisihnya bukan 0. Rekonsiliasi, bukan baseline. */
    public const STATUS_GAGAL = 'TIDAK_COCOK';

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
     * Baris opening pada (akun, tanggal) persis.
     *
     *_baris inilah yang berarti "baseline". Kalau tidak ada, opening belum
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
     * alasan menyatakan baseline ada — untuk itu pakai openingAt() pada tanggal
     * cut-off.
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

    public function openingAda(int $akunId, ?string $tanggal = null): bool
    {
        return $this->openingAt($akunId, $tanggal) !== null;
    }

    /**
     * Opening TERVERIFIKASI: ada baris di tanggal cut-off DAN selisihnya
     * sudah dicocokkan dengan real cash = 0.
     *
     * Inilah yang dipakai sebagai syarat boleh/tidaknya Setor/Tarik
     * memakai saldo laci sebagai acuan — padanannya dengan statementVerified()
     * untuk rekening bank.
     */
    public function terverifikasi(int $akunId, ?string $tanggal = null): bool
    {
        $row = $this->openingAt($akunId, $tanggal);

        return $row !== null && (string) $row->status === self::STATUS_SUDAH;
    }

    // =====================================================================
    // 2. INPUT OPENING (Finance)
    // =====================================================================

    /**
     * Finance menetapkan opening KAS pada tanggal cut-off.
     *
     * Opening hanya boleh pada tanggal cut-off, sama seperti statement bank:
     * tanggal lain berarti angka yang diinput bukan baseline periode ini.
     *
     * @param int      $opening  Saldo riil yang ditetapkan Finance
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

        // Opening yang sudah diverifikasi TIDAK boleh diedit diam-diam dan tetap
        // berstatus terverifikasi: kalau boleh, verifikasi kehilangan makna
        // karena opening bisa "digeser" sampai cocok dengan real cash.
        // Verifikasi ulang harus lewat jalur eksplisit.
        $data = [
            'akun_kas_bank_id' => $akunId,
            'unit_id'          => $unitId,
            'tanggal'          => $tanggal,
            'opening'          => $opening,
            'status'           => self::STATUS_BELUM,
            'real_cash'        => null,
            'selisih'          => null,
            'verifikasi_by'    => null,
            'verifikasi_at'    => null,
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
    // 3. VERIFIKASI: opening vs real cash pada cutoff yang sama
    // =====================================================================

    /**
     * Cocokkan opening KAS dengan real cash hasil hitung laci (Tutup Kasir)
     * pada tanggal cut-off yang sama.
     *
     * Tidak ada closing pada tanggal cut-off DITOLAK: tanpa real cash tidak
     * ada yang bisa dicocokkan, dan mengarang angka laci akan menghapus
     * informasi yang justru dicari.
     *
     * @return array{ok:bool, alasan:string, data:array<string,mixed>}
     */
    public function verifikasi(int $akunId, ?int $userId = null, ?string $tanggal = null): array
    {
        $tanggal ??= FinanceScopeService::cutoffDate();

        $row = $this->openingAt($akunId, $tanggal);
        if ($row === null) {
            return $this->gagal('Belum ada opening KAS yang ditetapkan Finance pada tanggal ' . $tanggal . '.');
        }

        $akun = $this->akunModel->find($akunId);
        if ($akun === null) {
            return $this->gagal('Rekening kas tidak ditemukan.');
        }

        $unitId  = (int) ($akun->unit_id ?? 0);
        $closing = $this->realCashCutoff($unitId, $tanggal);

        if (! $closing['ada']) {
            return $this->gagal(sprintf(
                'Belum ada Tutup Kasir unit %d pada %s, jadi real cash tidak ada dan opening '
                . 'tidak bisa diverifikasi. Tutup kasir dulu pada tanggal cut-off itu.',
                $unitId,
                $tanggal
            ));
        }

        if (! $closing['unambiguous']) {
            return $this->gagal(sprintf(
                'Ada %d baris Tutup Kasir unit %d pada %s dengan nilai akhir_cash berbeda, jadi '
                . 'real cash tidak tunggal. Perbaiki datanya dulu sebelum memverifikasi opening.',
                $closing['jumlah'],
                $unitId,
                $tanggal
            ));
        }

        $opening = (int) $row->opening;
        $real    = (int) $closing['nilai'];
        $selisih = $real - $opening;
        $cocok   = $selisih === 0;

        $this->openingModel->update((int) $row->id, [
            'real_cash'     => $real,
            'selisih'       => $selisih,
            'status'        => $cocok ? self::STATUS_SUDAH : self::STATUS_GAGAL,
            'verifikasi_by' => $userId,
            'verifikasi_at' => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok'     => true,
            'alasan' => '',
            'data'   => [
                'opening'    => $opening,
                'real_cash'  => $real,
                'selisih'    => $selisih,
                'status'     => $cocok ? self::STATUS_SUDAH : self::STATUS_GAGAL,
                'verifikasi' => $userId,
            ],
        ];
    }

    /**
     * Real cash satu unit pada tanggal cut-off, dibaca dari Tutup Kasir.
     *
     * HANYA BACA. Tidak ada baris `tutup_kasir` yang dibuat atau diubah.
     *
     * @return array{ada:bool, unambiguous:bool, nilai:int, jumlah:int}
     */
    public function realCashCutoff(int $unitId, ?string $tanggal = null): array
    {
        $tanggal ??= FinanceScopeService::cutoffDate();
        $kosong    = ['ada' => false, 'unambiguous' => true, 'nilai' => 0, 'jumlah' => 0];

        if ($unitId <= 0) {
            return $kosong;
        }

        $rows = db_connect()->table('tutup_kasir')
            ->select('akhir_cash')
            ->where('tanggal', $tanggal)
            ->where('unit', $unitId)
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return $kosong;
        }

        $nilai = array_map(static fn ($r) => (int) ($r['akhir_cash'] ?? 0), $rows);
        $beda  = array_values(array_unique($nilai));

        return [
            'ada'        => true,
            'unambiguous' => count($beda) === 1,
            'nilai'      => (int) $beda[0],
            'jumlah'     => count($nilai),
        ];
    }

    // =====================================================================
    // 4. LAPORAN REKONSILIASI
    // =====================================================================

    /**
     * Angkaopening / real / selisih / status untuk satu rekening.
     *
     * `real_cash_terkini` sengaja DIHITUNG ULANG dari Tutup Kasir, bukan
     * diambil dari snapshot saat verifikasi: kalau closing di tanggal cut-off
     * diubah belakangan, yang harus lihat adalah real cash yang
     * sekarang berlaku, plus status snapshot yang pernah dicocokkan.
     *
     * @return array<string,mixed>
     */
    public function rekonsiliasi(int $akunId, ?string $tanggal = null): array
    {
        $tanggal ??= FinanceScopeService::cutoffDate();

        $akun = $this->akunModel->find($akunId);
        $row  = $this->openingAt($akunId, $tanggal);

        $unitId  = $akun === null ? 0 : (int) ($akun->unit_id ?? 0);
        $closing = $this->realCashCutoff($unitId, $tanggal);

        $opening = $row === null ? null : (int) $row->opening;
        $status  = $row === null ? 'BELUM_ADA' : (string) $row->status;

        $realCashVerifikasi = $row === null ? null : $row->real_cash;
        $selisihVerifikasi  = $row === null ? null : $row->selisih;

        return [
            'akun_id'          => $akunId,
            'nama_akun'        => $akun === null ? null : (string) $akun->nama_akun,
            'unit_id'          => $unitId,
            'tanggal'          => $tanggal,
            'opening_ada'      => $row !== null,
            'opening'          => $opening,
            'real_cash_ada'    => $closing['ada'] && $closing['unambiguous'],
            'real_cash'        => $closing['ada'] ? $closing['nilai'] : null,
            'real_cash_ambigu' => $closing['ada'] && ! $closing['unambiguous'],
            'selisih'          => ($row === null || ! $closing['ada'] || ! $closing['unambiguous'])
                ? null
                : $closing['nilai'] - (int) $row->opening,
            // Snapshot yang tersimpan waktu verifikasi. `selisih` di atas
            // dihitung ULANG dari Tutup Kasir saat ini, jadi begitu Tutup
            // Kasir ditutup ulang angkanya bisa bergerak -- sedangkan status
            // di bawah masih menunjuk ke hasil verifikasi yang lama. Dua key
            // ini disimpan supaya perbedaannya bisa dilihat, bukan diam-diam.
            'real_cash_verifikasi' => $realCashVerifikasi,
            'selisih_verifikasi'   => $selisihVerifikasi,
            // true = real cash sudah berubah sejak verifikasi. null = belum
            // bisa dinilai (opening belum ada / belum diverifikasi / Tutup
            // Kasir belum ada), yang TIDAK sama dengan "aman".
            'selisih_bergeser' => $row === null
                || $status !== self::STATUS_SUDAH
                || ! $closing['ada']
                || ! $closing['unambiguous']
                || $selisihVerifikasi === null
                ? null
                : ($closing['nilai'] - (int) $row->opening) !== (int) $selisihVerifikasi,
            'status'           => $status,
            'terverifikasi'    => $status === self::STATUS_SUDAH,
            'terverifikasi_by' => $row === null ? null : $row->verifikasi_by,
            'terverifikasi_at' => $row === null ? null : $row->verifikasi_at,
            'keterangan'       => $row === null ? null : $row->keterangan,
        ];
    }

    /**
     * Rekonsiliasi opening KAS untuk semua rekening KAS aktif.
     *
     * @return array<int, array<string,mixed>>
     */
    public function rekonsiliasiSemua(?string $tanggal = null): array
    {
        $out = [];

        foreach ($this->akunModel->where('tipe', 'KAS')->where('status', 'aktif')->findAll() as $akun) {
            $out[] = $this->rekonsiliasi((int) $akun->idakun_kas_bank, $tanggal);
        }

        return $out;
    }

    /**
     * Rekening KAS aktif yang opening-nya belum TERVERIFIKASI.
     *
     * Dipakai diagnostics supaya "baseline laci belum diverifikasi" terlihat
     * sebagai peringatan, bukan saldo 0 yang senyap.
     *
     * @return array<int, array<string,mixed>>
     */
    public function belumTerverifikasi(?string $tanggal = null): array
    {
        return array_values(array_filter(
            $this->rekonsiliasiSemua($tanggal),
            static fn (array $r): bool => $r['terverifikasi'] !== true
        ));
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