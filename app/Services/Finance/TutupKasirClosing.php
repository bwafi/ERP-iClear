<?php

namespace App\Services\Finance;

use App\Libraries\ModeKasBank;
use Config\Database;

/**
 * Tutup Kasir = PENUTUP yang idempotent dan dihitung server.
 *
 * ================================================================
 * Kenapa kelas ini ada
 * ================================================================
 * `TutupKasir::tutup()` sebelumnya menyimpan angka apa pun yang dikirim
 * hidden field form:
 *
 *     'akhir_cash' => (int) $this->request->getPost('akhir_cash'),
 *
 * Artinya `tutup_kasir.akhir_cash` — yang besok menjadi SALDO AWAL laci —
 * bisa diubah sesuka hati dengan satu POST. Formnya tidak bisa dipercaya,
 * jadi di sini DIPINDAHKAN seluruh perhitungan ke server dan angka dari
 * request TIDAK LAGI dipakai untuk nilai uang.
 *
 * ================================================================
 * KONTRAK
 * ================================================================
 * 1. SATU-SATUNYA sumber angka adalah:
 *      - `TutupKasirSaldoAwal`         -> awal_cash / awal_transfer
 *      - `TutupKasirSourceDefinition`  -> kas masuk / transfer masuk /
 *                                         kas keluar ( definisi canonical,
 *                                         sama persis dengan Finance )
 *      - `TutupKasirTransferInternal`   -> setor / tarik
 *    Tidak ada formula sumber kedua yang ditulis di file ini.
 *
 * 2. FORMULA (tidak boleh diubah tanpa audit):
 *
 *      akhir_cash     = awal_cash + kas_masuk - kas_keluar - setor + tarik
 *      akhir_transfer = awal_transfer + transfer_masuk - transfer_keluar
 *
 *    `akhir_transfer` sengaja TIDAK dikurangi setor dan tidak ditambah
 *    tarik: yang berpindah lewat Setor/Tarik adalah UANG DI LACI, bukan
 *    saldo rekening bank. Setor/Tarik juga tidak pernah menulis ke
 *    `kas_masuk`/`kas_keluar`/penjualan, jadi pengurangannya di sisi bank
 *    akan jadi pengurangan ganda.
 *
 * 3. IDEMPOTEN terhadap (tanggal, unit):
 *      - `tutup()` dibungkus transaksi.
 *      - Kunci taken lewat `GET_LOCK()` MySQL (nama lock diturunkan dari
 *        tanggal+unit), sehingga dua request bersamaan untuk unit yang sama
 *        TIDAK bisa sama-sama lolos cek.
 *      - Kalau sudah ada closing, TIDAK ada INSERT kedua.
 *    Catatan: `SELECT ... FOR UPDATE` TIDAK dipakai untuk pengecekan
 *    keberadaan baris di tabel `tutup_kasir` karena tabel itu belum punya
 *    unique index (lihat `TutupKasirClosing::duplikat()`) — tanpa unique
 *    index, InnoDB tidak punya gap lock yang bisa diandalkan dan dua request
 *    bisa lolos bersamaan lalu=deadlock. `GET_LOCK` menutup celah itu tanpa
 *    bergantung pada index.
 *
 * 4. `cash_laci` adalah SATU-SATUNYA angka dari manusia, karena dia hasil
 *    hitung uang fisik di laci. Aturannya ketat: wajib diisi, harus numeric,
 *    harus >= 0, dan boleh BERBEDA dari `akhir_cash` — selisihnya justru
 *    informasi audit.
 *
 * 5. TIDAK ADA penulisan ke tabel lain. Tidak ada carry-forward ke
 *    `kas_masuk`, tidak ada pagarnya Rp1.000.000, tidak ada auto-transfer ke
 *    bank. Setor tetap harus lewat `KasBankSetorTarikService`.
 *
 * @see \App\Controllers\TutupKasir::tutup()
 * @see \App\Services\Finance\TutupKasirSaldoAwal
 * @see \App\Services\Finance\TutupKasirSourceDefinition
 * @see \App\Services\Finance\KasBankSetorTarikService
 */
class TutupKasirClosing
{
    /** Batas bawah / atas nama lock MySQL (64 byte). */
    private const LOCK_MAX = 60;

    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // =================================================================
    // HITUNG ULANG DARI DATABASE
    // =================================================================

    /**
     * Seluruh angka closing untuk satu unit pada satu tanggal, dihitung dari DB.
     *
     * TIDAK melempar exception kalau ada masalah; mengembalikan `siap => false`
     * plus `alasan` supaya pemanggil bisa membalas dengan pesan yang jelas
     * tanpa perlu tahu detail internal.
     *
     * @return array{
     *   siap:bool, alasan:string,
     *   tanggal:string, unit:int,
     *   awal_cash:int, kas_masuk:int, kas_keluar:int, setor:int, tarik:int,
     *   akhir_cash:int, akhir_transfer:int,
     *   awal_transfer:int, transfer_masuk:int, transfer_keluar:int,
     *   saldo_awal_kas:array, saldo_awal_tf:array,
     *   transfer_internal:array, angka:array<string,int>
     * }
     */
    public function hitung(int $unitId, string $tanggal): array
    {
        $koso = array_merge(
            [
                'siap'    => false,
                'alasan'  => '',
                'tanggal' => $tanggal,
                'unit'    => $unitId,
            ],
            array_fill_keys([
                'awal_cash', 'kas_masuk', 'kas_keluar', 'setor', 'tarik',
                'akhir_cash', 'awal_transfer', 'transfer_masuk',
                'transfer_keluar', 'akhir_transfer',
            ], 0),
            [
                'saldo_awal_kas'     => ['ada' => false, 'nilai' => null, 'pesan' => ''],
                'saldo_awal_tf'      => ['ada' => false, 'nilai' => null, 'pesan' => ''],
                'transfer_internal'  => ['ada' => false, 'setor' => 0, 'tarik' => 0, 'pesan' => ''],
                'angka'              => [],
            ]
        );

        if ($unitId <= 0) {
            $koso['alasan'] = 'Unit tidak valid, jadi tutup kasir tidak bisa diproses.';

            return $koso;
        }

        $saldoAwal = new TutupKasirSaldoAwal($this->db);
        $srcDef    = new TutupKasirSourceDefinition($this->db);

        $awalKas  = $saldoAwal->saldoAwalKas($unitId, $tanggal);
        $awalTf   = $saldoAwal->saldoAwalTransfer($unitId, $tanggal);
        $transfer = (new TutupKasirTransferInternal($this->db))
            ->hariIni($unitId, $tanggal, $awalKas['akun_kas_bank_id'] ?? null);

        $awalCashNum = $awalKas['ada'] ? (int) $awalKas['nilai'] : 0;
        $awalTfNum   = $awalTf['ada'] ? (int) $awalTf['nilai'] : 0;

        $kasMasuk        = $srcDef->cashMasuk($unitId, $tanggal);
        $transferMasuk   = $srcDef->transferMasuk($unitId, $tanggal);
        $kasKeluar       = $srcDef->kasKeluarCash($unitId, $tanggal);
        $transferKeluar  = $srcDef->kasKeluarTransfer($unitId, $tanggal);
        $setor           = (int) $transfer['setor'];
        $tarik           = (int) $transfer['tarik'];

        // FORMULA — server yang menghitung, bukan form.
        $akhirCash    = $awalCashNum + $kasMasuk - $kasKeluar - $setor + $tarik;
        $akhirTransfer = $awalTfNum + $transferMasuk - $transferKeluar;

        $angka = [
            // Kolom `tutup_kasir`. Nama disamakan dengan kolom tabel supaya
            // pemanggil tidak perlu tahu asal-usul angkanya.
            'awal_cash'           => $awalCashNum,
            'akhir_cash'          => $akhirCash,
            'pendapatan_cash'     => $kasMasuk,
            'pendapatan_transfer' => $transferMasuk,
            'pengeluaran_cash'    => $kasKeluar,
            'pengeluaran_transfer' => $transferKeluar,

            // Dipakai view saja.
            'kas_masuk'           => $kasMasuk,
            'transfer_masuk'      => $transferMasuk,
            'kas_keluar'          => $kasKeluar,
            'transfer_keluar'     => $transferKeluar,
            'total_pendapatan'    => $kasMasuk + $transferMasuk,
            'total_pengeluaran'   => $kasKeluar + $transferKeluar,
            'setor'               => $setor,
            'tarik'               => $tarik,
            'awal_transfer'       => $awalTfNum,
            'akhir_transfer'      => $akhirTransfer,
            'saldo_awal_cash'     => $awalCashNum,
            'saldo_awal_transfer' => $awalTfNum,
        ];

        $koso['angka']             = $angka;
        $koso['saldo_awal_kas']   = $awalKas;
        $koso['saldo_awal_tf']    = $awalTf;
        $koso['transfer_internal'] = $transfer;
        $koso['awal_cash']        = $awalCashNum;
        $koso['kas_masuk']        = $kasMasuk;
        $koso['kas_keluar']       = $kasKeluar;
        $koso['setor']            = $setor;
        $koso['tarik']            = $tarik;
        $koso['akhir_cash']       = $akhirCash;
        $koso['awal_transfer']    = $awalTfNum;
        $koso['transfer_masuk']   = $transferMasuk;
        $koso['transfer_keluar']  = $transferKeluar;
        $koso['akhir_transfer']   = $akhirTransfer;

        // --- GERBANG: tanpa sumber saldo yang sah, Tutup Kasir terkunci ---
        //
        // Tidak ada fallback ke 0, Rp1.000.000, `kas_masuk`, atau saldo lama.
        // Fallback seperti itu memindahkan angka karangan ke hari-hari berikutnya.
        if (! $awalKas['ada']) {
            $koso['alasan'] = 'Saldo awal KAS belum ditetapkan, jadi tutup kasir ditolak. '
                . $awalKas['pesan'];

            return $koso;
        }

        // Opening TRANSFER punya aturan yang sama dengan opening cash: kalau
        // tidak ada sumbernya, `akhir_transfer` jangan diisi 0 diam-diam karena
        // `(int) ''` = 0 dan angka nol itu akan jadi carry-forward besok.
        if (! $awalTf['ada']) {
            $koso['alasan'] = 'Saldo awal TRANSFER belum ditetapkan, jadi tutup kasir ditolak. '
                . $awalTf['pesan'];

            return $koso;
        }

        if ($akhirCash < 0) {
            $koso['alasan'] = 'Saldo akhir KAS hasil hitung sistem minus '
                . number_format(abs($akhirCash), 0, ',', '.')
                . ' (laci minus). Laci tidak boleh minus; periksa saldo awal atau pengeluaran hari ini.';

            return $koso;
        }

        $koso['siap'] = true;

        return $koso;
    }

    // =================================================================
    // VALIDASI cash_laci — satu-satunya angka dari manusia
    // =================================================================

    /**
     * Validasi `cash_laci`.
     *
     * Strict on purpose:
     *   - null / '' / missing  DITOLAK (bukan 0)
     *   - non-numeric           DITOLAK (PHP akan memotong "12abc" jadi 12)
     *   - negatif               DITOLAK (uang fisik tidak negatif)
     *   - float                 DITOLAK (pecahan rupiah tidak pernah ada di laci)
     *   - > 0                   TIDAK diminta: laci kosong itu sah
     *
     * Tidak dipaksa sama dengan `akhir_cash` — selisihnya justru informasi audit.
     *
     * @param mixed $mentah nilai POST apa adanya
     *
     * @return array{ok:bool, nilai:int, alasan:string}
     */
    public function validasiCashLaci($mentah): array
    {
        $gagal = static fn (string $alasan): array => ['ok' => false, 'nilai' => 0, 'alasan' => $alasan];

        if (is_array($mentah) || is_object($mentah)) {
            return $gagal('Input uang di laci tidak valid.');
        }

        if ($mentah === null || $mentah === false) {
            return $gagal('Uang di laci wajib diisi. Kalau laci memang kosong, isi 0.');
        }

        // String kosong / whitespace: TIDAK boleh dianggap 0.
        if (is_string($mentah) && trim($mentah) === '') {
            return $gagal('Uang di laci wajib diisi. Kalau laci memang kosong, isi 0.');
        }

        if (is_bool($mentah)) {
            return $gagal('Input uang di laci tidak valid.');
        }

        if (is_float($mentah) || is_double($mentah)) {
            return $gagal('Uang di laci harus angka bulat rupiah, tanpa desimal.');
        }

        if (is_string($mentah)) {
            $mentah = $this->bereskanThousands($mentah);

            if ($mentah === null) {
                return $gagal(
                    'Uang di laci harus angka bulat rupiah. Pemisah ribuan hanya boleh `.` '
                    . 'atau `,` dalam kelipatan tiga digit, misalnya 1.500.000.'
                );
            }
        }

        if (! is_numeric($mentah)) {
            return $gagal('Uang di laci harus angka. Nilai yang dikirim tidak bisa dibaca sebagai nominal.');
        }

        // `is_finite` menutup INF/NAN yang bisa muncul dari konversi float.
        $nilai = $mentah + 0;

        if (! is_finite($nilai)) {
            return $gagal('Uang di laci harus angka finite.');
        }

        if (floor($nilai) !== (float) $nilai) {
            return $gagal('Uang di laci harus angka bulat rupiah, tanpa desimal.');
        }

        $nilai = (int) $nilai;

        if ($nilai < 0) {
            return $gagal('Uang di laci tidak boleh minus.');
        }

        return ['ok' => true, 'nilai' => $nilai, 'alasan' => ''];
    }

    /**
     * Ubah input nominal berformat ribuan menjadi digit murni.
     *
     * PROBLEM: pemisah ribuan Indonesia adalah `.` (titik), sama persis dengan
     * pemisah desimal. `str_replace('.', '')` yang dulu dipakai membuat
     * `1.5` terbaca 15 — 15 kali lipat lebih besar dari yang diketik orang.
     * Itu akan jadi catatan closing dan tidak boleh lolos.
     *
     * ATURAN YANG DIPAKAI: pemisah ribuan hanya diterima kalau sillanya memang
     * bentuk ribuan Indonesia, yaitu:
     *   - kelompok pertama 1-3 digit, tiap kelompok berikutnya PERSIS 3 digit
     *     -> `1.500.000` = 1500000, `1.500` = 1500, `12.345.678` = 12345678
     *   - sisanya hanya digit
     *     -> `1.500.000` valid, `1.5` DITOLAK (kelompok hanya 1 digit),
     *        `1.234.5` DITOLAK, `12.34` DITOLAK
     * - `.` dan `,` boleh bercampur asal sama-sama kelipatan tiga digit
     *   -> `1,234,567.890` valid
     * - satu pemisah yang menandai desimal (bukan kelipatan tiga digit) TIDAK
     *   pernah diterima; pecahan rupiah di laci memang tidak ada
     *   -> `1750.50` DITOLAK
     * - spasi, NBSP, dan `'` sebagai pemisah ringan juga dibersihkan, tapi
     *   hanya kalau sisanya sudah bentuk ribuan yang valid.
     *
     * @return string|null digit murni, atau null kalau formatnya tidak sah
     */
    private function bereskanThousands(string $mentah): ?string
    {
        $s = str_replace(["\xc2\xa0", ' '], '', trim($mentah));

        if ($s === '') {
            return null;
        }

        // Tanda pengurang di depan sudah ditangani pemanggil sebagai "belum
        // diisi"/invalid; di sini cukup tolak supaya tidak jadi angka negatif.
        if (strpos($s, '-') !== false) {
            return null;
        }

        // Tanda `'` dipakai orang sebagai pemisah ribuan yang ringan.
        if (strpos($s, "'") !== false) {
            $s = str_replace("'", '', $s);

            if ($s === '') {
                return null;
            }
        }

        if (preg_match('/^\d+$/', $s) === 1) {
            return $s;
        }

        // Mengandung pemisah: harus persis pola ribuan di atas.
        if (preg_match('/^(\d{1,3}(?:[.,]\d{3})+)$/', $s) !== 1) {
            return null;
        }

        return str_replace(['.', ','], '', $s);
    }

    // =================================================================
    // SIMPAN — idempotent terhadap (tanggal, unit)
    // =================================================================

    /**
     * Simpan satu closing. Idempotent terhadap (tanggal, unit).
     *
     * @param int    $unitId
     * @param string $tanggal  YYYY-MM-DD
     * @param mixed  $cashLaci hasil POST apa adanya, divalidasi di sini
     * @param int    $userId
     *
     * @return array{ok:bool, alasan:string, kode:string, id:int|null, closing:array|null, hitung:array}
     *   `kode`: 'tersimpan' | 'sudah_ada' | 'ditolak'
     */
    public function simpan(int $unitId, string $tanggal, $cashLaci, $userId): array
    {
        // `ok` HARUS false di sini. Kalau penolakan dilaporkan `ok = true`,
        // pemanggil melihat success, masuk ke cabang "berhasil menyimpan",
        // lalu memakai `closing` yang null. Itu persis bug yang membuat
        // request tidak valid terlihat sukses di layar kasir.
        $gagal = static fn (string $kode, string $alasan): array => [
            'ok' => false, 'kode' => $kode, 'alasan' => $alasan, 'id' => null,
            'closing' => null, 'hitung' => null,
        ];

        $hariIni = date('Y-m-d');

        // Closing hanya boleh untuk HARI INI. Ini mencegah form lama (atau
        // request crafted) menulis closing bertanggal lampau dan menggeser
        // retroaktif seluruhOpening harian berikutnya.
        if ($tanggal !== $hariIni) {
            return $gagal('ditolak', 'Tanggal tutup kasir harus hari ini (' . $hariIni . ').');
        }

        $lock = $this->namaLock($tanggal, $unitId);

        // Pessimistic lock yang benar-benar berlaku lintas-koneksi: MySQL
        // `GET_LOCK` milik server, bukan milik transaksi InnoDB. Kalau gagal
        // dapat lock, ada request lain yang sedang menutup unit yang sama.
        $lockDidapat = $this->kunci($lock);

        if ($lockDidapat === false) {
            return $gagal(
                'ditolak',
                'Ada proses tutup kasir lain yang sedang berjalan untuk unit ini. '
                . 'Tunggu sebentar lalu coba lagi.'
            );
        }

        try {
            // 1) CEK DUPLIKAT — di dalam critical section, jadi tidak ada
            //    request lain yang bisa menyisipkan closing untuk
            //    (tanggal, unit) ini.
            $sudah = $this->closingPada($tanggal, $unitId);

            if ($sudah !== null) {
                return [
                    'ok'      => true,
                    'kode'     => 'sudah_ada',
                    'alasan'   => 'Tutup kasir unit ini pada ' . $tanggal
                        . ' sudah tercatat (ID ' . (int) $sudah['idtutupkasir'] . '), jadi tidak disimpan dua kali.',
                    'id'       => (int) $sudah['idtutupkasir'],
                    'closing'  => $sudah,
                    'hitung'   => null,
                ];
            }

            // 2) VALIDASI cash_laci SEBELUM query berat, supaya pesan error
            //    yang muncul memang soal input manusia.
            $laci = $this->validasiCashLaci($cashLaci);

            if (! $laci['ok']) {
                return $gagal('ditolak', $laci['alasan']);
            }

            // 3) TRANSAKSI dibuka SEBELUM hitung, bukan sesudah.
            //
            //    Kalau transaksi baru dibuka setelah hitung(), ada jendela
            //    di mana penjualan/kas_keluar/Setor bisa ter-insert di antara
            //    hitung dan insert closing. Closing-nya lalu berisi angka
            //    yang sudah tidak pernah benar-benar ada. Membuka transaksi
            //    lebih dulu membuat snapshot sumber konsisten dengan baris
            //    yang ditulis.
            $this->db->transBegin();

            try {
                // Cek ulang di dalam transaksi. `SELECT ... FOR UPDATE`
                // sengaja TIDAK dipakai: `tutup_kasir` belum punya unique
                // index, jadi tidak ada gap lock yang bisa diandalkan dan dua
                // insert bisa lolos bersamaan lalu deadlock. Yang menahan
                // runner lain adalah `GET_LOCK` yang sudah dipegang sejak
                // sebelum blok ini.
                $dobel = $this->closingPada($tanggal, $unitId);

                if ($dobel !== null) {
                    $this->db->transRollback();

                    return [
                        'ok'      => true,
                        'kode'    => 'sudah_ada',
                        'alasan'  => 'Tutup kasir unit ini pada ' . $tanggal . ' sudah tercatat.',
                        'id'      => (int) $dobel['idtutupkasir'],
                        'closing' => $dobel,
                        'hitung'  => null,
                    ];
                }

                // 5) HITUNG ULANG dari database. Nilai dari request tidak dipakai.
                $h = $this->hitung($unitId, $tanggal);

                if (! $h['siap']) {
                    $this->db->transRollback();

                    return $gagal('ditolak', $h['alasan']);
                }

                $a = $h['angka'];

                $row = [
                    'tanggal'    => $tanggal,
                    'unit'       => $unitId,

                    'awal_cash'      => $a['awal_cash'],
                    'akhir_cash'     => $a['akhir_cash'],
                    'awal_transfer'  => $a['awal_transfer'],
                    'akhir_transfer' => $a['akhir_transfer'],

                    'pendapatan_cash'      => $a['pendapatan_cash'],
                    'pendapatan_transfer'  => $a['pendapatan_transfer'],
                    'pengeluaran_cash'     => $a['pengeluaran_cash'],
                    'pengeluaran_transfer' => $a['pengeluaran_transfer'],

                    // Input fisik manusia. Boleh berbeda dari akhir_cash.
                    'cash_laci' => $laci['nilai'],

                    'status'       => 'selesai',
                    'akun_ID_AKUN' => $userId > 0 ? $userId : null,
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ];

                if ($this->db->table('tutup_kasir')->insert($row) === false) {
                    $e = $this->db->error();

                    $this->db->transRollback();

                    return $gagal(
                        'ditolak',
                        'Tutup kasir gagal disimpan: '
                            . ($e['message'] !== '' ? $e['message'] : 'penyebab tidak dilaporkan driver')
                    );
                }

                $id = (int) $this->db->insertID();

                $this->db->transCommit();

                return [
                    'ok'      => true,
                    'kode'    => 'tersimpan',
                    'alasan'  => '',
                    'id'      => $id,
                    'closing' => $row + ['idtutupkasir' => $id],
                    'hitung'  => $h,
                ];
            } catch (\Throwable $e) {
                // Query sumber bisa hilang (mis. tabel di-drop saat deploy),
                // atau koneksi putus. Tanpa catch, transaksi menggantung dan
                // request berikutnya akan salah baca sebagai "sudah ada".
                if ($this->db->transStatus()) {
                    $this->db->transRollback();
                }

                // Pesan driver kalau ada; kalau tidak, pakai exception-nya.
                // `error()['message']` mengembalikan string KOSONG (bukan null)
                // saat tidak ada error, jadi `?? $e->getMessage()` tidak pernah
                // dipakai dan penyebabnya hilang. Harus dicek `!== ''`.
                $pesanDb = (string) ($this->db->error()['message'] ?? '');

                return $gagal(
                    'ditolak',
                    'Tutup kasir gagal disimpan: '
                        . ($pesanDb !== '' ? $pesanDb : $e->getMessage())
                );
            }
        } finally {
            $this->lepaskan($lock);
        }
    }

    // =================================================================
    // HELPER
    // =================================================================

    /**
     * Nama lock MySQL untuk (tanggal, unit).
     *
     * Dipublikasikan supaya test bisa memegang lock yang sama dari koneksi
     * lain dan menguji guard-nya secara nyata, tanpa menyalin format namanya
     * di dua tempat. Kalau formatnya berubah, test ikut berubah otomatis.
     */
    public function lockName(string $tanggal, int $unitId): string
    {
        return $this->namaLock($tanggal, $unitId);
    }

    /**
     * Nama lock MySQL. Diturunkan dari (tanggal, unit) supaya dua unit berbeda
     * tidak saling menyerialisasi.
     */
    private function namaLock(string $tanggal, int $unitId): string
    {
        return 'tutup_kasir:' . substr(str_replace('-', '', $tanggal) . ':' . $unitId, 0, self::LOCK_MAX);
    }

    /** @return bool|null true = dapat, false = ditolak, null = tidak didukung. */
    private function kunci(string $lock): ?bool
    {
        try {
            $row = $this->db->query(
                'SELECT GET_LOCK(' . $this->db->escape($lock) . ', 10) AS got'
            )->getRow();
        } catch (\Throwable $e) {
            // Driver tanpa GET_LOCK: jangan diam-diam lanjut tanpa lock.
            return false;
        }

        if ($row === null || ! property_exists($row, 'got')) {
            return false;
        }

        return (int) $row->got === 1;
    }

    private function lepaskan(string $lock): void
    {
        try {
            $this->db->query('SELECT RELEASE_LOCK(' . $this->db->escape($lock) . ')');
        } catch (\Throwable $e) {
            // Lock dilepas otomatis kalau koneksi putus / thread selesai.
        }
    }

    /**
     * Closing yang sudah ada untuk (tanggal, unit), deterministik.
     *
     * Dipakai sebagai gerbang idempotensi dan untuk melapor duplikat historis.
     */
    public function closingPada(string $tanggal, int $unitId, ?string $kolom = null): ?array
    {
        $kolom = $kolom === null ? 'idtutupkasir' : $kolom;

        if (! preg_match('/^[a-z_]+$/', $kolom)) {
            return null;
        }

        return $this->db->table('tutup_kasir')
            ->select('*')
            ->where('tanggal', $tanggal)
            ->where('unit', $unitId)
            ->orderBy('idtutupkasir', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    /**
     * Daftar pasangan closing ganda historis — READ ONLY.
     *
     * Tidak memilih dan tidak menghapus salah satu. Melaporkan saja, supaya
     * keputusan data tetap di tangan orang yang berwenang.
     *
     * @return array<int,array{tanggal:string, unit:int|null, jumlah:int, id:int[], akhir_cash:int[], saldo_berbeda:bool}>
     */
    public function duplikat(?int $unitId = null): array
    {
        $b = $this->db->table('tutup_kasir')
            ->select('tanggal, unit, COUNT(*) AS jumlah, '
                . 'GROUP_CONCAT(idtutupkasir ORDER BY idtutupkasir ASC) AS ids, '
                . 'GROUP_CONCAT(akhir_cash ORDER BY idtutupkasir ASC) AS akhir, '
                . 'COUNT(DISTINCT akhir_cash) AS nilai_beda')
            ->groupBy('tanggal')
            ->groupBy('unit')
            ->having('COUNT(*) > 1')
            ->orderBy('tanggal', 'DESC')
            ->orderBy('unit', 'ASC');

        if ($unitId !== null) {
            $b->where('unit', $unitId);
        }

        $out = [];

        foreach ($b->get()->getResultArray() as $r) {
            $ids    = array_map('intval', explode(',', (string) $r['ids']));
            $akhir  = array_map('intval', explode(',', (string) $r['akhir']));

            $out[] = [
                'tanggal'         => (string) $r['tanggal'],
                'unit'            => $r['unit'] === null ? null : (int) $r['unit'],
                'jumlah'          => (int) $r['jumlah'],
                'id'              => $ids,
                'akhir_cash'      => $akhir,
                'saldo_berbeda'   => (int) $r['nilai_beda'] > 1,
            ];
        }

        return $out;
    }

    /**
     * Apakah unique index `(tanggal, unit)` bisa dibuat sekarang.
     *
     * Penting: `tutup_kasir` punya 20 baris dengan `unit IS NULL`, dan MySQL
     * mengizinkan banyak NULL pada UNIQUE. Jadi baris legacy `unit IS NULL`
     * TIDAK menghalangi unique index — hanya pasangan konkret yang sama
     * tanggal + unit yang menghalanginya.
     */
    public function bisaUniqueIndex(): array
    {
        $dup = $this->duplikat();
        $tabel = $this->db->tablePrefix . 'tutup_kasir';

        $sudahAda = (int) ($this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $this->db->escape($tabel)
            . " AND INDEX_NAME = 'uniq_tutup_kasir_tanggal_unit'"
        )->getRow()->c ?? 0);

        return [
            'bisa'            => $dup === [] && $sudahAda === 0,
            'index_sudah_ada' => $sudahAda > 0,
            'jumlah_duplikat' => count($dup),
            'duplikat'        => $dup,
        ];
    }

    /** Total closing dengan unit NULL (legacy) — tidak menghalangi UNIQUE. */
    public function closingTanpaUnit(): int
    {
        return (int) ($this->db->table('tutup_kasir')
            ->where('unit', null)
            ->countAllResults() ?? 0);
    }
}
