<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Libraries\ModeKasBank;
use Config\Finance;

/**
 * Fondasi SETOR TUNAI & PENARIKAN TUNAI.
 *
 * BELUM ADA UI / ROUTE. Kelas ini disiapkan supaya model datanya terkunci
 * dulu dan bisa diuji, sebelum ada layar yang memakainya.
 *
 * MODEL
 * -----
 * Setor tunai  : KAS unit  KELUAR  X   +   BANK rekening  MASUK  X
 * Penarikan    : BANK rekening KELUAR X   +   KAS unit  MASUK  X
 *
 * Kedua leg memakai `unit_id` YANG SAMA (unit pemilik KAS). Itulah yang
 * membuat entitlement ikut bergerak tanpa menyentuh tabel alokasi:
 *
 *     posisiUnit(u) = openingAllocation(u) + netMovement(u)
 *
 * Legacy/UNASSIGNED tidak pernah ikut bergerak. Kalau saldo 30 Sep seluruhnya
 * diletakkan di LEGACY (opening allocation 0), maka:
 *
 *     statement 100 jt  ->  LEGACY 100 jt, posisi semua unit 0
 *     Jember setor 5 jt  ->  LEGACY 100 jt (tetap), posisi Jember 5 jt
 *     Probolinggo 0     ->  tidak boleh menarik, saldo fisik cukup bukan alasan
 *
 * ATOMIKITAS
 * ----------
 * Kedua leg ditulis dalam SATU transaksi database. Kalau leg kedua gagal,
 * leg pertama rollback. "Kas sudah berkurang tapi bank tidak bertambah" tidak
 * boleh terjadi.
 *
 * IDEMPOTENSI
 * -----------
 * `submission_key` (unik di DB) jadi idempotency key. Kirim ulang dengan key
 * sama tidak menghasilkan mutasi ganda; service melaporkan status 'skipped'.
 *
 * GUARD
 * -----
 *  - Penarikan: posisi unit harus >= nominal (BUKAN saldo fisik rekening).
 *  - Setor    : saldo fisik KAS harus >= nominal.
 *  - Tanggal harus >= periodeMulaiDate(). Transaksi bertanggal sebelum
 *    cut-off tidak boleh masuk ledger periode baru.
 */
class KasBankSetorTarikService
{
    public const SUMBER_TIPE_SETOR = 'SETOR_TUNAI';
    public const SUMBER_TIPE_TARIK = 'PENARIKAN_TUNAI';

    /**
     * Subtype TRANSFER_INTERNAL untuk "Pindah Saldo" (BANK -> BANK).
     *
     * Ditaruh di kelas ini, bukan di controller, supaya ketiga nilai
     * `sumber_tipe` untuk `jenis = TRANSFER_INTERNAL` punya satu tempat
     * definisi. Nilai `jenis` sengaja TIDAK diubah: Setor/Tarik dan Pindah
     * Saldo sama-sama perpindahan antar rekening milik sendiri tanpa
     * perubahan kepemilikan unit, jadi secara ledger memang satu jenis.
     * Yang membedakan adalah `sumber_tipe` - itulah diskriminator resminya.
     *
     * PINDAH_SALDO selalu BANK -> BANK. KAS <-> BANK TIDAK boleh lewat
     * sumber_tipe ini: itu fitur Setor/Tarik dan harus memakai
     * `setorTunai()` / `tarikTunai()` supaya semua guard baseline, saldo,
     * dan idempotensi ikut jalan.
     */
    public const SUMBER_TIPE_PINDAH_SALDO = 'PINDAH_SALDO';

    protected ModelAkunKasBank $akunModel;
    protected KasBankCutoffService $cutoff;
    protected EntitlementPolicyService $policy;
    protected KasBankScopeService $scope;

    public function __construct(
        ?ModelAkunKasBank $akun = null,
        ?KasBankCutoffService $cutoff = null,
        ?EntitlementPolicyService $policy = null,
        ?KasBankScopeService $scope = null
    ) {
        $this->akunModel = $akun ?? new ModelAkunKasBank();
        $this->cutoff    = $cutoff ?? new KasBankCutoffService();
        $this->policy    = $policy ?? new EntitlementPolicyService();
        $this->scope     = $scope ?? new KasBankScopeService();
    }

    // =====================================================================
    // SETOR TUNAI — KAS unit -> BANK rekening
    // =====================================================================

    /**
     * @return array{ok:bool, status:string, alasan:string, transfer_ref:string}
     */
    public function setorTunai(
        int $unitId,
        int $akunKasId,
        int $akunBankId,
        int $nominal,
        string $tanggal,
        string $submissionKey,
        string $keterangan = '',
        ?int $inputBy = null
    ): array {
        return $this->pindahDana([
            'unitId'         => $unitId,
            'akunKasId'      => $akunKasId,
            'akunBankId'     => $akunBankId,
            'nominal'        => $nominal,
            'tanggal'        => $tanggal,
            'submissionKey'  => $submissionKey,
            'keterangan'     => $keterangan,
            'inputBy'        => $inputBy,
            'arahKas'        => ModeKasBank::ARAH_KELUAR,
            'arahBank'       => ModeKasBank::ARAH_MASUK,
            'sumberTipe'     => self::SUMBER_TIPE_SETOR,
        ]);
    }

    // =====================================================================
    // PENARIKAN TUNAI — BANK rekening -> KAS unit
    // =====================================================================

    /**
     * @return array{ok:bool, status:string, alasan:string, transfer_ref:string}
     */
    public function tarikTunai(
        int $unitId,
        int $akunKasId,
        int $akunBankId,
        int $nominal,
        string $tanggal,
        string $submissionKey,
        string $keterangan = '',
        ?int $inputBy = null
    ): array {
        return $this->pindahDana([
            'unitId'         => $unitId,
            'akunKasId'      => $akunKasId,
            'akunBankId'     => $akunBankId,
            'nominal'        => $nominal,
            'tanggal'        => $tanggal,
            'submissionKey'  => $submissionKey,
            'keterangan'     => $keterangan,
            'inputBy'        => $inputBy,
            'arahKas'        => ModeKasBank::ARAH_MASUK,
            'arahBank'       => ModeKasBank::ARAH_KELUAR,
            'sumberTipe'     => self::SUMBER_TIPE_TARIK,
        ]);
    }

    // =====================================================================

    /**
     * @param  array<string, mixed> $p
     * @return array{ok:bool, status:string, alasan:string, transfer_ref:string}
     */
    private function pindahDana(array $p): array
    {
        $unitId     = (int) $p['unitId'];
        $akunKasId  = (int) $p['akunKasId'];
        $akunBankId = (int) $p['akunBankId'];
        $nominal    = (int) $p['nominal'];
        $tanggal    = FinanceScopeService::tanggalStr((string) $p['tanggal']);
        $key        = trim((string) $p['submissionKey']);
        $ket        = trim((string) $p['keterangan']);
        $inputBy    = $p['inputBy'] !== null ? (int) $p['inputBy'] : (int) (session('ID_AKUN') ?? 0);
        $sumberTipe = (string) $p['sumberTipe'];
        $arahKas    = (string) $p['arahKas'];
        $arahBank   = (string) $p['arahBank'];
        $transferRef = $sumberTipe . '-' . $tanggal . '-' . strtoupper(substr(sha1($key), 0, 12));

        // =============================================================
        // 1. IDEMPOTENSI — SEBELUM VALIDASI DAN SEBELUM CEK SALDO
        // =============================================================
        //
        // Ini urutan WAJIB, bukan preferensi. `submission_key` adalah
        // janji dari client: "request ini sudah pernah dikirim, jangan
        // kerjakan dua kali". Kalau key-nya sudah pernah diproses, hasil
        // yang benar adalah 'skipped' -- bukan 'failed' dengan alasan apa pun.
        //
        // Urutan lama menaruh cek ini paling akhir, setelah semua guard, dan
        // itu merusak janji idempotensi: begitu request pertama benar-benar
        // memindahkan uang, saldonya sudah berubah, jadi retry dengan key yang
        // sama akan menabrak guard saldo dan dijawab 'Saldo kas unit tidak
        // cukup' -- padahal tidak ada permintaan baru sama sekali. Akibatnya
        // client yang benar-benar melakukan retry menerima jawaban 'gagal'
        // untuk operasi yang SUDAH BERHASIL, lalu saat mencoba lagi dengan
        // key baru, uangnya keluar dua kali.
        //
        // Di bawah ini tidak ada satu pun query ke saldo, ke entitlement, ke
        // statement, atau ke opening. Kalau key-nya sudah dipakai kita berhenti
        // di sini: saldo tidak dicek, tidak dikunci, dan tidak ada baris ledger
        // yang ditulis.
        //
        // Satu-satunya yang dicek sebelum key adalah key-nya sendiri tidak
        // kosong -- karena key kosong tidak punya makna untuk dicari.
        if ($key === '') {
            return $this->gagal('submission_key wajib diisi sebagai idempotency key.');
        }

        $sudah = $this->cariTransaksiByKey($key);
        if ($sudah !== null) {
            return $this->skipped($sudah->transfer_ref ?? $transferRef);
        }

        // =============================================================
        // 2. VALIDASI REQUEST
        // =============================================================
        if ($nominal <= 0) {
            return $this->tolakDenganIdempotensi($key, 'Nominal harus lebih dari 0.');
        }
        if ($unitId <= 0) {
            return $this->tolakDenganIdempotensi($key, 'Unit pemilik kas wajib diisi.');
        }
        if ($akunKasId <= 0 || $akunBankId <= 0) {
            return $this->tolakDenganIdempotensi($key, 'Rekening kas dan rekening bank wajib dipilih.');
        }
        if ($akunKasId === $akunBankId) {
            return $this->tolakDenganIdempotensi($key, 'Rekening asal dan tujuan tidak boleh sama.');
        }
        if ($tanggal === '') {
            return $this->tolakDenganIdempotensi($key, 'Tanggal transaksi wajib diisi.');
        }
        if ($tanggal < FinanceScopeService::periodeMulaiDate()) {
            return $this->tolakDenganIdempotensi($key, sprintf(
                'Tanggal %s berada sebelum periode operasional baru (%s). Mutasi sebelum '
                . 'cut-off tidak boleh masuk ledger periode baru.',
                $tanggal,
                FinanceScopeService::periodeMulaiDate()
            ));
        }

        // ---- Validasi rekening ----
        $kas  = $this->akunModel->find($akunKasId);
        $bank = $this->akunModel->find($akunBankId);

        if ($kas === null || (string) $kas->tipe !== 'KAS' || (string) $kas->status !== 'aktif') {
            return $this->tolakDenganIdempotensi($key, 'Rekening kas tidak valid atau tidak aktif.');
        }
        if ((int) ($kas->unit_id ?? 0) !== $unitId) {
            return $this->tolakDenganIdempotensi($key, 'Rekening kas bukan milik unit yang dipilih.');
        }
        if ($bank === null || (string) $bank->tipe !== 'BANK' || (string) $bank->status !== 'aktif') {
            return $this->tolakDenganIdempotensi($key, 'Rekening bank tidak valid atau tidak aktif.');
        }

        // ---- Guard rekening bank: entitlement + statement + role ----
        //
        // Tiga hal ini TIDAK boleh dilewati hanya karena "rekeningnya ada".
        // Inilah penjaga terakhir, karena form sudah disaring server-side
        // (1F) tapi form tetap bisa dimanipulasi.
        $alasanRekening = $this->guardRekeningTujuan(
            $bank,
            $unitId,
            $p['role'] ?? null,
            $tanggal,
            $arahBank
        );
        if ($alasanRekening !== null) {
            return $this->tolakDenganIdempotensi($key, $alasanRekening);
        }

        // ---- Guard baseline laci KAS ----
        //
        // Laci kas tidak punya statement bank; baseline-nya adalah opening
        // (uang fisik laci) yang Finance input pada tanggal cut-off
        // (KasOpeningService). Opening yang sudah tersimpan langsung sah
        // sebagai acuan — tidak ada verifikasi.
        //
        // Cek ini WAJIB ada di service, bukan hanya di form: kalau form
        // memblokir sementara service mengizinkan, request yang sama akan
        // dapat dua jawaban berbeda tergantung lewat mana dia datang.
        if (! $this->cutoff->openingTersedia($akunKasId)) {
            return $this->tolakDenganIdempotensi($key, sprintf(
                'Laci kas "%s" belum punya opening KAS pada %s. Saldo laci hanya '
                . 'boleh dipakai sebagai acuan setelah Finance menetapkan opening '
                . '(uang fisik laci) pada tanggal cut-off.',
                (string) ($kas->nama_akun ?? $akunKasId),
                $this->cutoff->tanggalCutoff()
            ));
        }

        // =============================================================
        // 3. CEK SALDO
        // =============================================================
        if ($arahKas === ModeKasBank::ARAH_KELUAR) {
            // SETOR: dana harus benar-benar ada di laci.
            $saldoKas = $this->cutoff->saldoFisik($akunKasId);
            if ($saldoKas < $nominal) {
                return $this->tolakDenganIdempotensi($key, sprintf(
                    'Saldo kas unit tidak cukup untuk disetor (tersedia Rp%s, diminta Rp%s).',
                    KasBankCutoffService::rupiah($saldoKas),
                    KasBankCutoffService::rupiah($nominal)
                ));
            }
        } else {
            // PENARIKAN: cek POSISI UNIT, bukan saldo fisik rekening.
            // Di rekening bersama dengan legacy besar, saldo fisik cukup
            // sementara posisi unit nol — dan itu harus ditolak.
            $cek = $this->cutoff->cekTarikUnit($akunBankId, $unitId, $nominal);
            if (! $cek['ok']) {
                return $this->tolakDenganIdempotensi($key, $cek['alasan']);
            }
        }

        // =============================================================
        // 4. MUTASI
        // =============================================================
        //
        // Balapan idempotensi tidak bisa diselesaikan dengan satu cek, dan
        // tidak juga dengan teori "cek lagi di dalam transaksi". Bukti dari
        // uji balapan (6 proses, key sama): SEMUA enam worker membaca
        // snapshot yang sama dan tidak melihat baris milik pemenang --
        // MySQL REPEATABLE READ membuat snapshot transaksi
        // ditetapkan pada pembacaan pertama di dalam transaksi, yaitu
        // SEBELUM pemenang sempat commit. Jadi cek kedua di dalam
        // transaksi secara rutin tidak menemukan apa-apa.
        //
        // Yang benar-benar bekerja adalah UNIQUE KEY uniq_tbk_submission,
        // karena itu satu-satunya penjaga yang benar-benar atomik. Karena
        // begitu, kegagalan di dalam transaksi tidak boleh langsung
        // dilaporkan 'failed':
        //
        //   1. Setelah rollback, cek submission_key sekali lagi.
        //      Kalau barisnya ada, request lain sudah menyelesaikan operasi
        //      yang sama -> jawab 'skipped'. Ini yang menangani duplicate-key
        //      race (errno 1062).
        //   2. Kalau barisnya belum ada, kegagalan itu sementara --
        //      deadlock (1213) atau lock wait timeout (1205). Balapan
        //      beberapa request pada rekening yang sama memang memicu ini.
        //      Semua-or-nothing dari transaksi berarti tidak ada mutasi
        //      yang setengah jadi, jadi mengulang transaksi itu aman.
        //   3. Coba lagi sampai batas. Kalau batasnya habis, baru 'failed'.
        //
        // Penting: CodeIgniter tidak melempar exception untuk query yang
        // gagal di dalam transaksi (transDepth !== 0) -- dia hanya
        // menandai transStatus = FALSE, lalu rollback di transComplete().
        // Jadi klasifikasi lewat kode error TIDAK bisa diandalkan di sini;
        // pemeriksaan submission_key setelah rollback adalah satu-satunya
        // penentu yang jujur.
        $percobaanMaks = 4;

        for ($percobaan = 1; $percobaan <= $percobaanMaks; $percobaan++) {
            $hasil = $this->cobaTransfer(
                $key,
                $transferRef,
                $tanggal,
                $unitId,
                $akunKasId,
                $akunBankId,
                $nominal,
                $arahKas,
                $arahBank,
                $sumberTipe,
                $ket,
                $inputBy
            );

            if ($hasil['lagi'] !== true) {
                return $hasil['jawaban'];
            }

            // Backoff singkat dengan sedikit acak. Deadlock antar kontender
            // pada rekening yang sama akan hilang kalau mereka tidak mencoba
            // lock itu pada milidetik yang sama persis.
            usleep(random_int(3000, 12000) * $percobaan);
        }

        // Percobaan habis. Satu pemeriksaan terakhir: kalau request lain
        // memang menyelesaikan operasi yang sama di detik yang sama, jawaban
        // yang benar tetap 'skipped' -- bukan 'failed'. Melewatkan cek ini
        // akan melaporkan kegagalan untuk operasi yang sudah berhasil.
        $terakhir = $this->cariTransaksiByKey($key);
        if ($terakhir !== null) {
            return $this->skipped($terakhir->transfer_ref ?? $transferRef);
        }

        return $this->gagal('Gagal menyimpan transaksi. Tidak ada perubahan saldo.');
    }

    /**
     * Satu percobaan menyimpan kedua kaki transfer dalam satu transaksi.
     *
     * @return array{jawaban:array, lagi:bool}
     */
    private function cobaTransfer(
        string $key,
        string $transferRef,
        string $tanggal,
        int $unitId,
        int $akunKasId,
        int $akunBankId,
        int $nominal,
        string $arahKas,
        string $arahBank,
        string $sumberTipe,
        string $ket,
        int $inputBy
    ): array {
        $db = db_connect();
        $db->transStart();

        $gagal = false;

        try {
            // Kunci baris baseline kedua rekening sampai selesai.
            //
            // Guard saldo di atas berjalan DI LUAR transaksi, jadi ada
            // celah TOCTOU: dua penarikan bersamaan bisa dua-duanya membaca
            // saldo yang sama dan dua-duanya lolos, lalu bersama-sama
            // menarik melebihi saldo yang tersedia. Baris baseline dikunci
            // SEBELUM saldo dicek ulang di bawah, jadi pemeriksaan kedua
            // berjalan atas data yang tidak bisa berubah di tengah.
            $this->cutoff->lockRekening($akunKasId, $tanggal);
            $this->cutoff->lockRekening($akunBankId, $tanggal);

            // Cek idempotensi sekali lagi di dalam transaksi, setelah lock
            // baseline. Ini cuma jaring pengaman untuk celah sempit antara
            // cek pra-transaksi dan dimulainya transaksi.
            //
            // PENTING: ini harus SELECT biasa, bukan SELECT ... FOR UPDATE.
            // Versi FOR UPDATE pernah dicoba dan justru MEMBUAT masalah:
            // untuk submission_key yang belum ada, InnoDB mengambil gap lock
            // di unique index, dan beberapa request berbeda yang mau insert
            // ke indeks yang sama lalu=deadlock satu sama lain (errno 1213).
            // Uji balapan 3 request dengan key berbeda gagal 1-2 di antaranya
            // karena alasan itu. Gap lock juga tidak menambah apa-apa: kunci
            // yang perlu dilindungi sudah dilindungi UNIQUE KEY-nya.
            //
            // Kekurangan SELECT biasa -- bisa membaca snapshot basi dan tidak
            // melihat baris pemenang -- memang sengaja diterima, karena
            // jalur setelah rollback (cek submission_key lagi + ulangi
            // transaksi) sudah menutup celah itu dengan benar.
            $menang = $this->cariTransaksiByKey($key);
            if ($menang !== null) {
                $db->transRollback();

                return ['lagi' => false, 'jawaban' => $this->skipped($menang->transfer_ref ?? $transferRef)];
            }

            $alasanSaldo = $this->cekSaldoUlangi($arahKas, $arahBank, $akunKasId, $akunBankId, $unitId, $nominal);
            if ($alasanSaldo !== null) {
                $db->transRollback();

                return ['lagi' => false, 'jawaban' => $this->gagal($alasanSaldo)];
            }

            $legKas = [
                'tanggal'          => $tanggal,
                'unit_id'          => $unitId,
                'akun_kas_bank_id' => $akunKasId,
                'jenis'            => ModeKasBank::JENIS_TRANSFER,
                'arah'             => $arahKas,
                'jumlah'           => $nominal,
                'transfer_ref'     => $transferRef,
                'submission_key'   => $key,
                'sumber_tipe'      => $sumberTipe,
                'keterangan'       => $ket,
                'input_by'         => $inputBy,
                'created_at'       => date('Y-m-d H:i:s'),
            ];

            $legBank = $legKas;
            $legBank['akun_kas_bank_id'] = $akunBankId;
            $legBank['arah']              = $arahBank;
            // submission_key hanya di leg pertama: kolomnya UNIQUE, jadi leg
            // kedua harus NULL. Idempotensi tetap utuh karena pencarian
            // dilakukan pada key-nya, bukan pada jumlah baris.
            $legBank['submission_key']    = null;

            // Nilai kembalian insert HARUS diperiksa, jangan dibuang.
            // INSERT yang ditolak UNIQUE KEY akan mengembalikan false; kalau
            // dibuang diam-diam, transaksi tetap terlihat "berhasil" padahal
            // kakinya tidak masuk -- dan itu bentuk kegagalan senyap yang
            // paling susah dicari kemudian.
            $legKasMasuk = $db->table('transaksi_kas_bank')->insert($legKas);
            // Kaki kedua hanya ditulis kalau kaki pertama benar-benar masuk.
            // Kalau kaki pertama ditolak, transaction sudah pasti di-rollback;
            // mencoba insert kedua hanya menambah lock yang tidak perlu dan
            // bisa memperpanjang deadlock.
            $legBankMasuk = $legKasMasuk !== false
                ? $db->table('transaksi_kas_bank')->insert($legBank)
                : false;

            if ($legKasMasuk === false || $legBankMasuk === false) {
                $gagal = true;
            }

            $db->transComplete();

            // transComplete() sudah me-rollback kalau ada query yang gagal,
            // tapi dia tidak melempar apa pun, jadi statusnya wajib diperiksa.
            if ($db->transStatus() === false) {
                $gagal = true;
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            $gagal  = true;
            $alasan = $e->getMessage();
        }

        if (! $gagal) {
            return [
                'lagi'     => false,
                'jawaban'  => [
                    'ok'           => true,
                    'status'       => ModeKasBank::STATUS_INSERTED,
                    'alasan'       => '',
                    'transfer_ref' => $transferRef,
                ],
            ];
        }

        $db->transRollback();

        // Satu-satunya penentu yang jujur: apakah key ini sudah punya
        // baris di ledger. Kalau punya, request lain sudah menyelesaikan
        // operasi yang sama dan jawaban yang benar adalah 'skipped' --
        // termasuk ketika yang menolak tadi adalah duplicate-key race.
        $sudah = $this->cariTransaksiByKey($key);
        if ($sudah !== null) {
            return ['lagi' => false, 'jawaban' => $this->skipped($sudah->transfer_ref ?? $transferRef)];
        }

        // Tidak ada row dan tidak ada mutasi parsial: ini kegagalan
        // sementara. Minta caller mencoba lagi.
        log_message('error', 'SetorTarik: percobaan gagal sementara pada ' . $sumberTipe
            . ($alasan ?? '') . ' (key ' . $key . ')');

        return ['lagi' => true, 'jawaban' => []];
    }

    /**
     * Guard rekening bank tujuan: entitlement, statement, dan role Finance.
     *
     * MENGAPA INI PISAH DARI VALIDASI REKENING UMUM
     * ---------------------------------------------
     * Cek "rekening ada & aktif" hanya membuktikan rekeningnya nyata. Itu
     * TIDAK membuktikan unit ini berhak memakainya, TIDAK membuktikan
     * baseline saldonya sudah dikonfirmasi Finance, dan TIDAK membuktikan
     * orang yangillés znajdu_thOOK(role) berwenang. Ketiganya dicek di sini.
     *
     * TIGA KASUS REKENING
     * -------------------
     * 1. FINANCE/HO (is_finance_ho = 1)
     *    Bukan rekening operasional unit, jadi TIDAK ada entitlement unit
     *    yang berlaku dan TIDAK butuh statement VERIFIED. Yang menggantikan
     *    entitlement adalah ROLE. Digunakan juga kalau unit yang transferring
     *    adalah unit yang rekeningnya belum diverifikasi — itulah gunanya
     *    rekening kas Direksi sebagai tujuan sementara.
     *
     * 2. REKENING WAJIB STATEMENT VERIFIED (config rekeningWajibStatementByBank)
     *    Wajib: (a) unit punya entitlement di rekening ini, dan (b) statement
     *    cutoff-nya sudah VERIFIED. Tanpa (b), saldo statement terbaca 0 dan
     *    rekening yang isinya 300 juta terlihat seperti rekening kosong —
     *    lalu sistem mengizinkan penarikan dan nanti muncul saldo minus.
     *
     * 3. REKENING LAINNYA
     *    Wajib punya entitlement unit. Statement tidak di-hardcode VERIFIED
     *    karena daftar rekening wajib statement adalah keputusan Finance,
     *    bukan tebakan sistem.
     *
     * @param  object      $bank    baris akun_kas_bank tipe BANK
     * @param  int         $unitId  unit yang melakukan transfer
     * @param  int|null    $role    ID_JABATAN; null = konteks role tidak diketahui
     * @param  string      $tanggal tanggal mutasi
     * @param  string      $arah    ARAH_MASUK atau ARAH_KELUAR pada rekening ini
     * @return string|null pesan error, atau null kalau lolos
     */
    private function guardRekeningTujuan(
        object $bank,
        int $unitId,
        $role,
        string $tanggal,
        string $arah
    ): ?string {
        $akunBankId = (int) $bank->idakun_kas_bank;

        // ---- USER SCOPE: unit leg harus milik user yang sedang login ----
        //
        // Ini diecek di DALAM service, bukan cuma di controller, supaya kelas
        // ini benar-benar menjadi security boundary. Kalau pemanggil lain
        // (CLI, cron, API, atau controller yang lupa memanggil guard) memakai
        // service ini, unit_id dari form tetap tidak bisa dipaksa masuk unit
        // orang lain.
        if (! $this->scope->userBolehUnit($unitId)) {
            return sprintf(
                'Unit %d berada di luar jangkauan akun Anda, sehingga tidak bisa dipakai untuk mutasi kas. '
                . 'Pilih unit yang memang Anda pegang.',
                $unitId
            );
        }

        // ---- KASUS 1: rekening Finance/HO ----
        if ((int) ($bank->is_finance_ho ?? 0) === 1) {
            // Jangan pakai (int) session('ID_JABATAN') langsung: session kosong
            // jadi 0, dan 0 adalah ROOT — konteks tanpa session akan dapat
            // hak penuh. null berarti "tidak diketahui", dan itu DITOLAK.
            if ($role === null) {
                $sessionRole = session('ID_JABATAN');
                if ($sessionRole !== null && $sessionRole !== '') {
                    $role = (int) $sessionRole;
                }
            }

            $roleBoleh = $role !== null
                && in_array($role, array_map('intval', config(Finance::class)->financeHoSourceRoles ?? []), true);

            if (! $roleBoleh) {
                return sprintf(
                    'Rekening kas Direksi "%s" hanya bisa dipakai oleh role Finance/Direksi. '
                    . 'Role Anda belum berwenang — hubungi Finance.',
                    (string) ($bank->nama_akun ?? $akunBankId)
                );
            }

            // Rekening Finance/HO tidak punya alokasi unit dan tidak butuh
            // statement VERIFIED:Saldo fisiknya memang di luar unit.
            return null;
        }

        // ---- ENTITLEMENT:rekening operasional harus milik/dialokasikan ke unit ini ----
        //
        // Ini yang menutup celah "unit mana pun bisa transfer ke rekening unit
        // lain". Kolom alokasi dengan nominal 0 TETAP dihitung entitlement:
        // unit itu punya hak pakai, hanya belum ada saldo teralokasikan.
        $entitled = $arah === ModeKasBank::ARAH_MASUK
            ? $this->cutoff->entitled($akunBankId, $unitId)
            : ((int) ($bank->unit_id ?? 0) === $unitId || $this->cutoff->entitled($akunBankId, $unitId));

        if (! $entitled) {
            $unitResmi = $this->policy->unitResmi($akunBankId);
            if ($unitResmi === []) {
                $unitResmi = $this->cutoff->entitledUnitIds($akunBankId);
            }

            return sprintf(
                'Rekening "%s" tidak bisa dipakai Unit %d. Rekening ini hanya dialokasikan ke unit [%s]. '
                . 'Kalau unit Anda memang berhak, hubungi Finance untuk memperbarui entitlement.',
                (string) ($bank->nama_akun ?? $akunBankId),
                $unitId,
                $unitResmi === [] ? 'tidak ada' : implode(', ', $unitResmi)
            );
        }

        // ---- STATEMENT VERIFIED ----
        //
        // Hanya berlaku untuk rekening yang WAJIB punya statement. Daftar ini
        // datang dari config Finance, bukan dari tebakan sistem.
        if ($this->policy->wajibStatementVerifikasi($akunBankId)) {
            if (! $this->cutoff->statementVerified($akunBankId, $tanggal)) {
                return sprintf(
                    'Rekening "%s" belum punya statement yang diverifikasi Finance pada %s. '
                    . 'Pengesahan saldo hanya bisa dilakukan setelah statement diinput dan diverifikasi.',
                    (string) ($bank->nama_akun ?? $akunBankId),
                    $this->cutoff->tanggalCutoff()
                );
            }
        }

        return null;
    }

    /**
     * Penolakan pra-transaksi yang sadar idempotensi.
     *
     * Ini menutup celah yang hanya muncul saat balapan. Cek idempotensi di
     * awal sudah menolak request lama, tapi request yang tiba BERBARENGAN
     * dengan request lain masih bisa menabrak guard saldo: begitu request
     * yang menang menarik uangnya, saldo yang tersisa sudah tidak cukup,
     * sehingga guard saldo menolak -- padahal request itu bukan permintaan
     * baru, dia cuma pengulangan dari operasi yang sudah berhasil.
     *
     * Kalau dibiarkan, guard saldo menjawab "tidak cukup" untuk operasi yang
     * sebenarnya sudah berhasil, dan client akan mengira uangnya hilang.
     * Jadi setiap penolakan pra-transaksi dicek ulang dulu ke submission_key:
     * kalau key-nya sudah dipakai, jawabannya 'skipped' -- bukan 'failed'.
     *
     * Guard/business rule-nya sendiri tidak berubah sama sekali. Ini hanya
     * memastikan balapan idempotensi tidak salah dilaporkan sebagai gagal.
     */
    private function tolakDenganIdempotensi(string $key, string $alasan, ?string $transferRef = null): array
    {
        $sudah = $this->cariTransaksiByKey($key);
        if ($sudah !== null) {
            return $this->skipped($sudah->transfer_ref ?? $transferRef);
        }

        return $this->gagal($alasan);
    }

    /**
     * Baris ledger yang sudah memakai `submission_key` ini.
     *
     * Hanya satu baris yang mungkin cocok: `submission_key` UNIQUE, jadi tidak
     * mungkin ada dua request dengan key sama yang sama-sama berhasil. Kolomnya
     * nullable (leg kedua transfer memang NULL), dan semua NULL di unique index
     * MySQL diperlakukan berbeda satu sama lain -- jadi pencarian dengan key
     * yang tidak kosong tidak akan pernah ikut menabrak leg-leg NULL itu.
     *
     * SENGaja tidak memakai FOR UPDATE. Untuk key yang belum ada, locking read
     * mengambil gap lock di unique index, dan beberapa request berbeda yang
     * insert ke indeks yang sama bisa=deadlock (errno 1213). UNIQUE KEY sudah
     * menjamin keunikan; pemeriksaannya tidak perlu menambah lock baru.
     *
     * @return object|null
     */
    private function cariTransaksiByKey(string $key)
    {
        // Sengaja TIDAK select kolom tertentu: tabel ini tidak punya kolom
        // `id`, jadi memintanya akan membuat query ini gagal total.
        return db_connect()->table('transaksi_kas_bank')
            ->where('submission_key', $key)
            ->get()
            ->getRow();
    }

    /**
     * @return array{ok:bool, status:string, alasan:string, transfer_ref:string}
     */
    private function skipped(?string $transferRef = null): array
    {
        return [
            'ok'           => true,
            'status'       => ModeKasBank::STATUS_SKIPPED,
            'alasan'       => 'submission_key sudah dipakai; transaksi tidak diduplikasi.',
            'transfer_ref' => (string) ($transferRef ?? ''),
        ];
    }

    /**
     * Cek ulang saldo di dalam transaksi, setelah baris statement dikunci.
     *
     * Sama persis dengan guard yang jalan di atas, tapi dijalankan SETELAH
     * lockRekening(). Gunanya menutup celah TOCTOU antar-request. Hasilnya
     * sama dengan cek pertama — kalau nol berarti ada request lain yang
     * duluan, jadi yang kedua ini yang harus ditolak.
     */
    private function cekSaldoUlangi(
        string $arahKas,
        string $arahBank,
        int $akunKasId,
        int $akunBankId,
        int $unitId,
        int $nominal
    ): ?string {
        if ($arahKas === ModeKasBank::ARAH_KELUAR) {
            // SETOR: dana harus benar-benar ada di laci.
            $saldoKas = $this->cutoff->saldoFisik($akunKasId);
            if ($saldoKas < $nominal) {
                return sprintf(
                    'Saldo kas unit tidak cukup untuk disetor (tersedia Rp%s, diminta Rp%s).',
                    KasBankCutoffService::rupiah($saldoKas),
                    KasBankCutoffService::rupiah($nominal)
                );
            }

            return null;
        }

        // PENARIKAN: cek POSISI UNIT, bukan saldo fisik rekening.
        $cek = $this->cutoff->cekTarikUnit($akunBankId, $unitId, $nominal);
        if (! $cek['ok']) {
            return $cek['alasan'];
        }

        return null;
    }

    /**
     * @return array{ok:bool, status:string, alasan:string, transfer_ref:string}
     */
    private function gagal(string $alasan): array
    {
        return [
            'ok'           => false,
            'status'       => ModeKasBank::STATUS_FAILED,
            'alasan'       => $alasan,
            'transfer_ref' => '',
        ];
    }
}
