<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Models\ModelAlokasiSaldoKasBank;

/**
 * Cut-off Kas & Bank — sumber tunggal semua perhitungan saldo periode baru.
 *
 * MODEL
 * -----
 * Cut-off      : Finance::$kasBankCutoffDate (tanggal SALDOKORAN yang disepakati)
 * Periode aktif: Finance::$kasBankPeriodeMulaiDate → sekarang
 *
 * Cut-off KasBank TERPISAH dari Finance::$cutoffDate. Yang terakhir global
 * (TutupKasir, Hutang-Piutang, KPI) dan tidak boleh digeser bersama ini.
 *
 * KAS tidak punya bank statement — dan TIDAK BOLEH membuatnya. Baseline KAS
 * adalah SALDO RIIL laci yang diinput user pada tanggal cut-off (`opening_kas`).
 * Tidak ada verifikasi dan tidak ada perbandingan dengan `tutup_kasir` — angka
 * opening langsung jadi pembuka saldo buku.
 *
 * Snapshot `kas_masuk` ber-deskripsi 'kas awal' BENIGN DIBACA: TutupKasir
 * menulisnya tiap hari untuk hari berikutnya, jadi menjumlahkannya akan
 * menghitung saldo berkali-kali. Sumber movement KAS juga tidak membaca
 * `kas_masuk` sama sekali (lihat KasBankSourceMovement::cashMasuk), jadi
 * snapshot itu tidak mungkin bocor jadi movement.
 *
 * BANK memakai model berbeda:
 *
 *     saldo_fisik(akun)  = statement(akun, cut-off) + netMovement sejak periode
 *     posisiUnit(u)      = openingAllocation(u) + netMovement(u) sejak periode
 *     LEGACY/UNASSIGNED  = statement(akun) - SUM(openingAllocation)
 *
 * KAS:
 *
 *     saldo_fisik(akun)  = opening_kas(akun, cut-off) + netMovement sejak periode
 *     posisiUnit(u)      = opening_kas(akun) + netMovement(u) sejak periode
 *
 * Opening KAS TIDAK PERNAH menjadi baris `transaksi_kas_bank` dan tidak pernah
 * ikut dijumlahkan sebagai movement — itu yang membuatnya dihitung tepat satu
 * kali.
 *
 * Opening balance yang diinput pada tanggal cut-off adalah SALDO RIIL akhir
 * hari itu, bukan SUM transaksi. Transaksi sebelum periode (1–7 Okt ketika
 * cut-off 7 Okt) tidak diposting sebagai transaksi Finance baru karena sudah
 * terserap di saldo riil tersebut — itu sebabnya batas bawah ledger
 * (kasBankPeriodeMulaiDate) selalu cut-off + 1 hari.
 *
* Tiga hal yang TIDAK boleh dilanggar:
 *
 *  1. STATEMENT BUKAN TRANSAKSI. Tidak ada baris transaksi_kas_bank yang
 *     mewakili saldo cut-off. Statement hanya reference (saldo_awal_kas_bank).
 *
 *  2. STATEMENT WAJIB DIISI PADA TANGGAL CUT-OFF. `statementAt()` boleh
 *     fallback ke baris yang lebih lama supaya saldo tidak tiba-tiba nol,
 *     TETAPI fallback itu bukan baseline. Baris placeholder lama yang belum
 *     VERIFIED tidak boleh diperlakukan sebagai fakta — pakai
 *     `baselineTersedia()` untuk membedakannya. Lihat juga
 *     `diagnostikBaseline()`.
 *
 *  3. LEGACY BUKAN SALDO UNIT. `legacyUnassigned()` adalah RESIDUAL, bukan
 *     hak siapa pun. Tidak ada jalur transaksi yang boleh menghabiskannya,
 *     karena setiap mutasi post-cut-off wajib punya unit_id yang entitled.
 *
 *  4. MOVEMENT BUKAN ALOKASI. `alokasi_saldo_kas_bank.nominal` hanya
 *     OPENING ALLOCATION (keputusan Finance saat cut-off). Angka harian
 *     tidak pernah ditulis ke sana; ia bergerak sendiri karena ledger.
 *
 * Invariant yang dijaga (lihat cekInvariant):
 *
 *     saldo_fisik == LEGACY + SUM(posisiUnit entitled)
 *
 * Syaratnya: setiap baris `transaksi_kas_bank` post-cut-off harus punya unit
 * yang entitled. Kalau ada baris dari unit lain, posisinya tidak pernah masuk
 * SUM(posisiUnit) dan invariant pecah — itulah gunanya cekInvariant().
 */
class KasBankCutoffService
{
    /** Status statement reference di saldo_awal_kas_bank.status. */
    public const STATEMENT_BELUM = 'BELUM_VERIFIKASI';
    public const STATEMENT_SUDAH = 'VERIFIED';

    /** Hasil cekInvariant(). */
    public const INV_SEIMBANG = 'SEIMBANG';
    public const INV_LEBIH = 'LEBIH';
    public const INV_KURANG = 'KURANG';

    protected ModelAkunKasBank $akunModel;
    protected ModelAlokasiSaldoKasBank $alokasiModel;
    protected ?KasBankSourceMovement $movementSrc = null;
    protected ?KasOpeningService $openingSrc = null;

    public function __construct(?ModelAkunKasBank $akun = null, ?ModelAlokasiSaldoKasBank $alokasi = null)
    {
        $this->akunModel   = $akun ?? new ModelAkunKasBank();
        $this->alokasiModel = $alokasi ?? new ModelAlokasiSaldoKasBank();
    }

    /**
     * Sumber baseline KAS. Lazy supaya modul yang tidak memakai KAS (mis.
     * alokasi rekening bank) tidak melakukan query ke `opening_kas` sama sekali.
     */
    protected function openingSrc(): KasOpeningService
    {
        if ($this->openingSrc === null) {
            $this->openingSrc = new KasOpeningService($this->akunModel);
        }

        return $this->openingSrc;
    }

    // =====================================================================
    // TANGGAL — semuanya delegate ke FinanceScopeService (sumber tunggal)
    // =====================================================================

    /** Tanggal baseline statement/opening (Finance::$kasBankCutoffDate). */
    public function tanggalCutoff(): string
    {
        return FinanceScopeService::kasBankCutoffDate();
    }

    /** Hari pertama periode ledger KasBank (Finance::$kasBankPeriodeMulaiDate). */
    public function tanggalMulaiPeriode(): string
    {
        return FinanceScopeService::kasBankPeriodeMulaiDate();
    }

    // =====================================================================
    // 1. STATEMENT REFERENCE (baseline bank, bukan transaksi)
    // =====================================================================

    /**
     * Baris statement reference satu rekening pada tanggal tertentu.
     *
     * Kalau baris untuk tanggal itu tidak ada, cari baris TERAKHIR yang
     * tanggalnya <= tanggal cut-off. Ini yang membuat satu rekening tetap
     * punya baseline walau statement berikutnya belum diinput — saldo
     * terakumulasi dari reference terakhir, bukan suddenly nol.
     *
     * @return object|null
     */
    public function statementAt(int $akunId, ?string $tanggal = null)
    {
        $tanggal ??= $this->tanggalCutoff();

        $db = db_connect();

        return $db->table('saldo_awal_kas_bank')
            ->where('akun_kas_bank_id', $akunId)
            ->where('tanggal', $tanggal)
            ->get()
            ->getRow()
            ?? $db->table('saldo_awal_kas_bank')
                ->where('akun_kas_bank_id', $akunId)
                ->where('tanggal <=', $tanggal)
                ->orderBy('tanggal', 'DESC')
                ->get()
                ->getRow();
    }

    /**
     * Saldo statement (baseline) satu rekening. 0 kalau belum ada reference
     * sama sekali — dan itu harus dibaca sebagai "belum diisi", bukan nol.
     */
    public function saldoStatement(int $akunId, ?string $tanggal = null): int
    {
        return (int) ($this->statementAt($akunId, $tanggal)->saldo ?? 0);
    }

    /**
     * Apakah statement-nya sudah diverifikasi Finance. Tanpa ini, saldo
     * statement 0 akan terbaca seperti "rekening ini memang kosong".
     *
     * Kolom `status` dibaca defensif: pada database yang belum menjalankan
     * migration 2026-10-03-000400 kolomnya belum ada, dan saat itu statement
     * dianggap belum terverifikasi (konservatif — lebih baik milestone
     * "butuh konfirmasi Finance" muncul daripada hilang).
     */
    public function statementVerified(int $akunId, ?string $tanggal = null): bool
    {
        $row = $this->statementAt($akunId, $tanggal);
        if ($row === null) {
            return false;
        }

        $status = is_object($row) ? ($row->status ?? null) : ($row['status'] ?? null);

        return $status !== null && (string) $status === self::STATEMENT_SUDAH;
    }

    /**
     * Apakah rekening ini SUDAH punya baris statement pada TANGGAL CUT-OFF?
     *
     * Bedanya dengan statementAt():
     *   - statementAt() boleh fallback ke baris lebih lama, supaya saldo
     *     tidak tiba-tiba nol ketika statement berikutnya belum diinput.
     *   - baselineTersedia() hanya true kalau baris PERSIS pada tanggal
     *     cut-off ada. Fallback TIDAK dihitung sebagai baseline.
     *
     * Kenapa harus dibedakan: begitu cut-off digeser, baris placeholder dari
     * cut-off lama (nilai 0, BELUM_VERIFIKASI) masih ada dan WILLY
     * terbaca oleh statementAt(). Tanpa pemisahan ini, rekening yang
     * statement-nya belum diinput akan terlihat punya "baseline" padahal
     * tidak, dan tidak ada yang memberi tahu operator.
     *
     * @return object|null baris statement pada tanggal cut-off, atau null
     */
    public function baselineTersedia(int $akunId, ?string $tanggal = null)
    {
        $tanggal ??= $this->tanggalCutoff();
        $db        = db_connect();

        return $db->table('saldo_awal_kas_bank')
            ->where('akun_kas_bank_id', $akunId)
            ->where('tanggal', $tanggal)
            ->get()
            ->getRow();
    }

    /**
     * Ringkasan baseline cut-off untuk SEMUA rekening kas & bank aktif.
     *
     * Dipakai KasBank::diagnostikKonfigurasi() supaya "baseline belum
     * diinput" terlihat sebagai peringatan, bukan angka senyap yang
     * menunggu salah input.
     *
     * Rekening KAS termasuk sejak opening KAS punya jalurnya sendiri
     * (opening_kas + real cash Tutup Kasir). Sebelumnya method ini hanya
     * memanggil tipe = 'BANK', sehingga laci kas yang opening-nya kosong
     * tidak pernah muncul sebagai peringatan sama sekali — dan itu sebabnya
     * saldo laci bisa terbaca 0 tanpa siapa pun diberi tahu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function diagnostikBaseline(?string $tanggal = null): array
    {
        $tanggal ??= $this->tanggalCutoff();
        $out      = [];

        foreach ($this->akunModel->whereIn('tipe', ['BANK', 'KAS'])->where('status', 'aktif')->findAll() as $akun) {
            $akunId = (int) $akun->idakun_kas_bank;
            $tipe   = strtoupper((string) $akun->tipe);

            if ($tipe === TutupKasirSourceDefinition::TIPE_KAS) {
                $out[] = $this->diagnostikBaselineKas($akun, $tanggal);

                continue;
            }

            $row    = $this->baselineTersedia($akunId, $tanggal);
            $status = $row === null ? null : (is_object($row) ? ($row->status ?? null) : ($row['status'] ?? null));

            $out[] = [
                'akun_id'                 => $akunId,
                'tipe'                    => $tipe,
                'nama_akun'               => (string) ($akun->nama_akun ?? ''),
                'unit_id'                 => $akun->unit_id === null ? null : (int) $akun->unit_id,
                'is_shared'               => (int) ($akun->is_shared ?? 0) === 1,
                'baseline_ada'            => $row !== null,
                'baseline_terverifikasi'  => $status !== null && (string) $status === self::STATEMENT_SUDAH,
                'saldo'                   => (int) ($row->saldo ?? 0),
                'tanggal_baseline'        => $row === null ? null : $tanggal,
                // Tanggal baris fallback yang SEDANG dipakai statementAt().
                // Disimpan supaya operator tahu angka 0 itu dari mana.
                'tertagih'                => $row === null ? $this->tanggalTertagih($akunId, $tanggal) : null,
            ];
        }

        return $out;
    }

    /**
     * Baris diagnostics untuk rekening KAS.
     *
     * Baseline KAS adalah opening yang sudah diinput Finance pada tanggal
     * cut-off. Kalau barisnya ada, baseline tersedia dan saldo laci bisa
     * dipakai; kalau belum, dilaporkan sebagai belum ada sehingga tidak ada
     * saldo 0 senyap.
     *
     * @return array<string, mixed>
     */
    private function diagnostikBaselineKas(object $akun, string $tanggal): array
    {
        $akunId = (int) $akun->idakun_kas_bank;
        $row    = $this->openingSrc()->openingAt($akunId, $tanggal);

        $opening = $row === null ? null : (int) $row->opening;

        return [
            'akun_id'                 => $akunId,
            'tipe'                    => TutupKasirSourceDefinition::TIPE_KAS,
            'nama_akun'               => (string) ($akun->nama_akun ?? ''),
            'unit_id'                 => $akun->unit_id === null ? null : (int) $akun->unit_id,
            'is_shared'               => false,
            'baseline_ada'            => $row !== null,
            'baseline_terverifikasi'  => $row !== null,
            'saldo'                   => $opening ?? 0,
            'tanggal_baseline'        => $row === null ? null : $tanggal,
            'tertagih'                => null,
            'opening'                 => $opening,
            'keterangan'              => $row === null ? null : $row->keterangan,
        ];
    }

    /**
     * Tanggal baris statement fallback yang SEDANG dipakai statementAt().
     * null kalau memang tidak ada baris sama sekali.
     */
    public function tanggalTertagih(int $akunId, ?string $tanggal = null): ?string
    {
        $tanggal ??= $this->tanggalCutoff();
        $row        = $this->statementAt($akunId, $tanggal);

        return $row === null ? null : FinanceScopeService::tanggalStr(
            is_object($row) ? $row->tanggal : $row['tanggal']
        );
    }

    // =====================================================================
    // 2. OPENING ALLOCATION + LEGACY / UNASSIGNED
    // =====================================================================

    /**
     * Kunci baris BASELINE milik satu rekening sampai transaksi selesai.
     *
     * WAJIB dipanggil di dalam transaksi. Baris baseline itu UNIQUE per
     * (akun, tanggal), jadi mengunciNYA membuat semua penulisan yang menyentuh
     * rekening tersebut saling menunggu. Ini yang mencegah dua alokasi
     * bersamaan lolos guard di waktu yang sama lalu total alokasi melebihi
     * statement.
     *
     * Tabel yang dikunci mengikuti tipe rekening: statement bank dikunci di
     * `saldo_awal_kas_bank`, opening KAS dikunci di `opening_kas`. Sebelum
     * opening KAS ada, rekening KAS tidak punya baris yang bisa dikunci sama
     * sekali — dan justru itu yang membuat pembaruan ini penting: begitu
     * opening KAS terisi, dua Setor dari laci yang sama bisa saja berlomba
     * membaca saldo yang sama lalu sama-sama lolos.
     *
     * Kenapa raw SQL: CodeIgniter 4.4.8 tidak punya `forUpdate()` di query
     * builder maupun di MySQLi driver, jadi tidak ada jalan lain tanpa
     * menurunkan framework.
     *
     * Kalau baris baseline belum ada, tidak ada yang dikunci — pemanggil
     * tetap harus menolak menebak baseline.
     */
    public function lockRekening(int $akunId, ?string $tanggal = null): void
    {
        $tanggal = $tanggal ?? $this->tanggalCutoff();
        $db      = db_connect();

        $tabel = $this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS
            ? 'opening_kas'
            : 'saldo_awal_kas_bank';

        // protectIdentifiers(), bukan escapeIdentifier(): di CodeIgniter 4.4.8
        // Connection hanya menyediakan yang pertama, dan dia juga menambah
        // DBPrefix otomatis -- penting supaya query ini jalan di database tes
        // yang prefix-nya `db_`.
        $namatabel = $db->protectIdentifiers($tabel, true, true, false);

        $sql = 'SELECT id FROM ' . $namatabel
             . ' WHERE akun_kas_bank_id = ' . (int) $akunId
             . '   AND tanggal = ' . $db->escape($tanggal)
             . ' FOR UPDATE';

        $db->query($sql);
    }

    /**
     * Total opening allocation lintas unit untuk satu rekening.
     */
    public function totalOpeningAllocation(int $akunId): int
    {
        return (int) ($this->alokasiModel
            ->selectSum('nominal')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getRow()->nominal ?? 0);
    }

    /**
     * LEGACY / UNASSIGNED = opening - SUM(opening allocation).
     *
     * Residual ini milik SEKOLOMPOK yang tidak diketahui, bukan milik unit
     * mana pun. Sengaja tidak disimpan sebagai baris tabel: menambahkannya
     * sebagai "unit semu" akan membuatnya bisa dipilih sebagai reviewer dan
     * HABIS. Lihat cekInvariant(): alokasi opening yang melebihi baseline
     * menghasilkan residual negatif yang harus DITOLAK, bukan disimpan apa
     * adanya.
     *
     * Untuk rekening KAS nilainya 0: laci milik satu unit, jadi tidak ada
     * saldo "tak bertuan" yang bisa muncul. Opening KAS tidak boleh dilabeli
     * LEGACY — itu akan membuatnya terlihat seperti dana yang tidak beratas
     * siapa pun, dan bertentangan dengan posisi unit yang memang memegangnya.
     */
    public function legacyUnassigned(int $akunId, ?string $tanggal = null): int
    {
        if ($this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS) {
            return 0;
        }

        return $this->opening($akunId, $tanggal) - $this->totalOpeningAllocation($akunId);
    }

    // =====================================================================
    // 3. MOVEMENT SETELAH CUT-OFF
    // =====================================================================

/**
     * Net movement satu rekening sejak periode aktif, opsional sampai tanggal
     * tertentu.
     *
     * $unitId null = seluruh unit (dipakai untuk saldo fisik).
     * $unitId diisi = hanya mutasi unit itu (dipakai untuk posisi unit).
     *
     * $sampai hanya membatasi sisi ATAS rentang. Tanpa ini, jawaban untuk
     * tanggal 7 Okt ikut naik begitu ada transaksi 8 Okt.
     *
     * Filter lower bound wajib kasBankPeriodeMulaiDate(), BUKAN kasBankCutoffDate():
     * tanggal 30 Sep adalah tanggal statement, bukan mutasi.
     */
    public function netMovement(int $akunId, ?int $unitId = null, ?string $sampai = null): int
    {
        return $this->sourceMovement()->netMovement(
            $akunId,
            $unitId,
            FinanceScopeService::kasBankPeriodeMulaiDate(),
            $sampai
        );
    }

    /**
     * Movement dihitung dari TABEL SUMBER, bukan `transaksi_kas_bank`.
     *
     * Ledger tidak lagi jadi sumber kebenaran Finance. Definisi nominalnya
     * mengikuti TutupKasirSourceDefinition; dimensi rekeningnya mengikuti
     * KasBankSourceMovement.
     */
    protected function sourceMovement(): KasBankSourceMovement
    {
        if ($this->movementSrc === null) {
            $this->movementSrc = new KasBankSourceMovement();
        }

        return $this->movementSrc;
    }

    // =====================================================================
    // 4. SALDO FISIK & POSISI UNIT
    // =====================================================================

    /**
     * Saldo fisik rekening = opening (baseline) + net movement.
     *
     * Opening TIPE-AWARE:
     *   - rekening KAS  -> opening_kas (baseline Finance pada cutoff)
     *   - rekening BANK -> statement di saldo_awal_kas_bank
     *
     * Opening dihitung SATU KALI: ia dibaca dari satu baris per (rekening,
     * tanggal), tidak pernah dijumlahkan dengan movement dan tidak pernah
     * muncul sebagai baris transaksi.
     *
     * Tidak ada dimensi unit di sini: satu rekening fisik = satu saldo.
     *
     * $tanggal dipakai untuk KEDUA suku: opening pada tanggal itu dan
     * movement HINGGA tanggal itu. Dulu $tanggal hanya masuk ke opening
     * sementara movement tetap dihitung tanpa batas atas, sehingga
     * `saldoFisik($akun, '2026-11-02')` diam-diam mengembalikan saldo
     * 8 Okt dan seterusnya.
     */
    public function saldoFisik(int $akunId, ?string $tanggal = null): int
    {
        return $this->opening($akunId, $tanggal) + $this->netMovement($akunId, null, $tanggal);
    }

    /**
     * Alur KAS sampai tanggal tertentu, dipecah per komponen:
     *
     *   Opening (baseline) + Cash In - Cash Out ± Setor/Tarik = Saldo Buku
     *
     * Opening dibaca dari baseline yang berlaku pada $sampai. Opening BUKAN
     * bagian movement dan tidak pernah dihitung dua kali.
     *
     * @return array{
     *     akun_id:int, unit_id:int, tanggal:string,
     *     opening:int, opening_ada:bool,
     *     cash_in:int, cash_out:int,
     *     transfer_masuk:int, transfer_keluar:int,
     *     movement:int, saldo_buku:int
     * }
     */
    public function alurKasSampai(int $akunId, string $sampai): array
    {
        $akun    = $this->akunModel->find($akunId);
        $opening = $this->opening($akunId, $sampai);
        $rincian = $this->sourceMovement()->rincianMovement(
            $akunId,
            null,
            FinanceScopeService::kasBankPeriodeMulaiDate(),
            $sampai
        );

        $movement   = (int) $rincian['net'];
        $openingRow = $this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS
            ? $this->openingSrc()->openingAt($akunId, FinanceScopeService::kasBankCutoffDate())
            : null;

        return [
            'akun_id'         => $akunId,
            'unit_id'         => (int) ($akun->unit_id ?? 0),
            'tanggal'         => $sampai,
            'opening'         => $opening,
            // Opening KAS hanya sah kalau baris cut-off-nya benar-benar ada
            // dan terverifikasi. Tanpa ini, saldo "0" bisa terbaca sebagai
            // laci kosong padahal opening-nya belum diinput.
            'opening_ada'     => $openingRow !== null,
            'cash_in'         => (int) $rincian['cash_in'],
            'cash_out'        => (int) $rincian['cash_out'],
            'transfer_masuk'  => (int) $rincian['transfer_masuk'],
            'transfer_keluar' => (int) $rincian['transfer_keluar'],
            'movement'        => $movement,
            'saldo_buku'      => $opening + $movement,
        ];
    }

    /**
     * BASELINE satu rekening pada tanggal — nol dimensionless.
     *
     * Ini satu-satunya jalan membaca baseline untuk tipe APAPUN. Semua
     * perhitungan saldo (saldoFisik, legacy, invariant, alokasi) memakai cara ini, bukan
     * `saldoStatement()` langsung, supaya rekening KAS tidak diam-diam
     * terbaca sebagai 0.
     */
    public function opening(int $akunId, ?string $tanggal = null): int
    {
        if ($this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS) {
            return $this->openingSrc()->opening($akunId, $tanggal);
        }

        return $this->saldoStatement($akunId, $tanggal);
    }

    /**
     * Apakah BASELINE rekening ini tersedia untuk Setor/Penarikan.
     *
     * Tipe-aware:
     *   - KAS  -> baris opening pada tanggal cut-off ada (opening langsung
     *             sah sebagai acuan; tidak ada verifikasi).
     *   - BANK -> statement sudah diverifikasi Finance.
     *
     * Inilah syarat yang dipakai Setor/Tarik sebelum memakai saldo rekening
     * sebagai acuan, jadi "baseline laci" ditentukan oleh keberadaan opening,
     * sedangkan "baseline koran" tetap wajib diverifikasi.
     */
    public function openingTersedia(int $akunId, ?string $tanggal = null): bool
    {
        if ($this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS) {
            return $this->openingSrc()->openingAda($akunId, $tanggal);
        }

        return $this->statementVerified($akunId, $tanggal);
    }

    /**
     * Rekening ini tipe apa: 'KAS' atau 'BANK'. Default BANK supaya rekening
     * yang tidak ditemukan tidak ikut berubah perilakunya.
     */
    public function tipeRekening(int $akunId): string
    {
        $akun = $this->akunModel->find($akunId);

        return $akun === null
            ? TutupKasirSourceDefinition::TIPE_BANK
            : strtoupper((string) $akun->tipe);
    }

    /**
     * Apakah unit punya entitlement atas rekening ini?
     *
     * non-shared -> hanya unit pemilik; shared -> harus ada baris alokasi.
     * Rekening Finance/HO tidak punya unit pemilik: selalu false.
     */
    public function entitled(int $akunId, int $unitId): bool
    {
        if ($unitId <= 0) {
            return false;
        }

        $akun = $this->akunModel->find($akunId);
        if ($akun === null) {
            return false;
        }

        if ((int) ($akun->is_finance_ho ?? 0) === 1) {
            return false;
        }

        if ((int) ($akun->is_shared ?? 0) !== 1) {
            return (int) ($akun->unit_id ?? 0) === $unitId;
        }

        return $this->alokasiModel
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->first() !== null;
    }

    /**
     * Posisi unit = opening allocation + net movement unit sejak cut-off.
 *
     * Unit yang TIDAK entitled mengembalikan 0 dan `entitled()`-nya false.
     * Nol itu bukan "saldo unit habis", tapi "unit ini memang tidak punya
     * hak atas rekening" — bedanya dipakai guard penarikan.
     *
     * Rekening KAS tidak punya alokasi: laci milik satu unit, jadi opening-nya
     * langsung jadi posisi unit itu. Tanpa cabang khusus, KAS akan selalu
     * terlihat nol padahal opening-nya ada.
     */
    public function posisiUnit(int $akunId, int $unitId, ?string $tanggal = null): int
    {
        if (! $this->entitled($akunId, $unitId)) {
            return 0;
        }

        $alokasi = (int) ($this->alokasiModel
            ->selectSum('nominal')
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->get()
            ->getRow()->nominal ?? 0);

        // Rekening KAS: opening adalah posisi awal unit pemilik laci, bukan
        // pembagian dari rekening bersama.
        $pembuka = $this->tipeRekening($akunId) === TutupKasirSourceDefinition::TIPE_KAS
            ? $this->openingSrc()->opening($akunId, $tanggal)
            : $alokasi;

        // Sama seperti saldoFisik(): $tanggal membatasi KEDUA suku, bukan
        // hanya opening.
        return $pembuka + $this->netMovement($akunId, $unitId, $tanggal);
    }

    /**
     * Total posisi seluruh unit yang entitled.
     */
    public function totalPosisiUnit(int $akunId): int
    {
        $total = 0;

        foreach ($this->entitledUnitIds($akunId) as $unitId) {
            $total += $this->posisiUnit($akunId, $unitId);
        }

        return $total;
    }

    /**
     * @return int[]
     */
    public function entitledUnitIds(int $akunId): array
    {
        $akun = $this->akunModel->find($akunId);
        if ($akun === null || (int) ($akun->is_finance_ho ?? 0) === 1) {
            return [];
        }

        if ((int) ($akun->is_shared ?? 0) !== 1) {
            $unitId = (int) ($akun->unit_id ?? 0);

            return $unitId > 0 ? [$unitId] : [];
        }

        $rows = $this->alokasiModel
            ->select('unit_id')
            ->where('akun_kas_bank_id', $akunId)
            ->groupBy('unit_id')
            ->findAll();

        $ids = array_values(array_unique(array_map(
            static fn ($r) => (int) (is_object($r) ? $r->unit_id : $r['unit_id']),
            $rows
        )));

        return array_values(array_filter($ids, static fn ($id) => $id > 0));
    }

    // =====================================================================
    // 5. INVARIANT
    // =====================================================================

    /**
     * Periksa invariant satu rekening:
     *
     *     saldo_fisik == LEGACY + SUM(posisi unit entitled)
     *
     * @return array{
     *   akun_id:int, tipe:string, saldo_opening:int, opening_ada:bool,
     *   total_opening_allocation:int, legacy_unassigned:int, net_movement:int,
     *   saldo_fisik:int, total_posisi_unit:int, selisih:int,
     *   alokasi_melebihi_opening:bool, status:string, entitled:int[]
     * }
     */
    public function cekInvariant(int $akunId): array
    {
        $opening    = $this->opening($akunId);
        $alokasi    = $this->totalOpeningAllocation($akunId);
        // WAJIB lewat helper, jangan dihitung ulang di sini. Aturan "sisa
        // statement yang belum dialokasikan" hanya berlaku untuk rekening
        // BANK. Untuk KAS tidak ada alokasi sama sekali -- opening laci
        // langsung menjadi posisi unit pemiliknya -- sehingga
        // `opening - alokasi` akan mengarang "legacy" sebesar opening dan
        // membuat selisih invariant KAS selalu minus opening.
        $legacy     = $this->legacyUnassigned($akunId);
        $net        = $this->netMovement($akunId);
        $fisik      = $this->saldoFisik($akunId);
        $posisi     = $this->totalPosisiUnit($akunId);
        $entitled   = $this->entitledUnitIds($akunId);

        $selisih        = $fisik - ($legacy + $posisi);
        $melebihi   = $alokasi > $opening;

        if ($selisih > 0) {
            $status = self::INV_LEBIH;
        } elseif ($selisih < 0) {
            $status = self::INV_KURANG;
        } else {
            $status = self::INV_SEIMBANG;
        }

        return [
            'akun_id'                    => $akunId,
            'tipe'                       => $this->tipeRekening($akunId),
            'saldo_opening'              => $opening,
            'opening_ada'                => $this->openingTersedia($akunId),
            'total_opening_allocation'   => $alokasi,
            'legacy_unassigned'          => $legacy,
            'net_movement'               => $net,
            'saldo_fisik'                => $fisik,
            'total_posisi_unit'          => $posisi,
            'selisih'                    => $selisih,
            'alokasi_melebihi_opening'   => $melebihi,
            'status'                     => $status,
            'entitled'                   => $entitled,
        ];
    }

    /**
     * Invariant semua rekening kas/bank aktif — KAS termasuk.
     *
     * Invariant KAS yang dijaga: opening laci harus utuh sebagai posisi unit
     * owning-nya. Kalau opening KAS tidak terbaca (mis. baris hilang), posisi
     * unit dan saldo fisik ikut hilang bersama dan invariant tetap SEIMBANG
     * — itu sebabnya `opening_ada` ikut dilaporkan, supaya kondisi
     * "seimbang tapi baseline-nya tidak ada" tidak lolos tanpa suara.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cekInvariantSemua(): array
    {
        $out = [];

        foreach ($this->akunModel->whereIn('tipe', ['BANK', 'KAS'])->where('status', 'aktif')->findAll() as $akun) {
            $out[] = $this->cekInvariant((int) $akun->idakun_kas_bank);
        }

        return $out;
    }

    // =====================================================================
    // 6. GUARD PER-UNIT (yang tadinya tidak ada)
    // =====================================================================

    /**
     * Apakah unit boleh menarik $nominal dari rekening ini?
     *
     * INI yang menutup jalur "unit ambil dana milik unit lain". Ceknya per
     * posisi unit, BUKAN per saldo fisik rekening: pada rekening bersama
     * dengan legacy besar, saldo fisik cukup tapi posisi unit tidak.
     *
     * @return array{ok:bool, alasan:string, posisi:int, permintaan:int, sisa:int}
     */
    public function cekTarikUnit(int $akunId, int $unitId, int $nominal): array
    {
        $tolak = static fn (string $alasan, int $posisi): array => [
            'ok'        => false,
            'alasan'    => $alasan,
            'posisi'    => $posisi,
            'permintaan' => $nominal,
            'sisa'      => 0,
        ];

        if ($nominal <= 0) {
            return $tolak('Nominal penarikan harus lebih dari 0.', 0);
        }

        if (! $this->entitled($akunId, $unitId)) {
            // Termasuk rekening Finance/HO dan unit tanpa alokasi. Saldo
            // LEGACY tidak bisa dipakai: tidak ada unit yang memegang hak
            // atasnya.
            return $tolak(
                'Unit ini tidak punya hak atas rekening tersebut. Saldo LEGACY/UNASSIGNED '
                . 'tidak dapat digunakan sebagai saldo unit.',
                0
            );
        }

        $posisi = $this->posisiUnit($akunId, $unitId);

        if ($posisi < $nominal) {
            return [
                'ok'         => false,
                'alasan'     => sprintf(
                    'Posisi saldo unit pada rekening ini hanya Rp%s, tidak cukup untuk menarik Rp%s. '
                    . 'Saldo fisik rekening tidak boleh dipakai sebagai pengganti posisi unit.',
                    self::rupiah($posisi),
                    self::rupiah($nominal)
                ),
                'posisi'     => $posisi,
                'permintaan' => $nominal,
                'sisa'       => max(0, $posisi),
            ];
        }

        return [
            'ok'         => true,
            'alasan'     => '',
            'posisi'     => $posisi,
            'permintaan' => $nominal,
            'sisa'       => $posisi - $nominal,
        ];
    }

    /**
     * Guard opening allocation: SUM(opening allocation) <= saldo statement.
     *
     * WAJIB membandingkan ke STATEMENT, bukan ke saldo fisik. Opening
     * allocation menggambarkan pembagian saldo 30 Sep; kalau dibandingkan ke
     * saldo fisik (statement + movement), alokasi bisa ikut bertambah sendiri
     * seiring mutasi dan eventuallyfasspassed statement.
     *
     * @return array{ok:bool, alasan:string, total:int, statement:int}
     */
    public function cekOpeningAllocation(int $akunId, int $tambahan, ?string $tanggal = null): array
    {
        $statement = $this->saldoStatement($akunId, $tanggal);
        $total     = $this->totalOpeningAllocation($akunId) + $tambahan;

        if ($total > $statement) {
            return [
                'ok'        => false,
                'alasan'    => sprintf(
                    'Total alokasi opening (Rp%s) tidak boleh melebihi saldo statement '
                    . 'cut-off (Rp%s). Selisihnya bukan LEGACY yang bisa dipakai unit.',
                    self::rupiah($total),
                    self::rupiah($statement)
                ),
                'total'     => $total,
                'statement' => $statement,
            ];
        }

        return [
            'ok'        => true,
            'alasan'    => '',
            'total'     => $total,
            'statement' => $statement,
        ];
    }

    // =====================================================================
    // 7. BASELINE KAS (dari tutup_kasir, bukan dari snapshot kas_masuk)
    // =====================================================================

    /**
     * Format angka untuk pesan error ke user.
     *
     * number_format() default memakai pemisah ribuan koma dan desimal titik,
     * yang untuk user Indonesia salah baca: Rp15,000,000 tampak seperti
     * "15 koma nol" bukan "15 juta".
     */
    public static function rupiah(int $nominal): string
    {
        return number_format($nominal, 0, ',', '.');
    }

    /**
     * Saldo kas baseline per unit pada tanggal cut-off, dari
     * `tutup_kasir.akhir_cash` — hitungan fisik laci saat tutup kasir.
     *
     * Query yang dipakai (below) sengaja memakai `tanggal = cut-off` persis,
     * bukan "terakhir yang <= cut-off": kalau ada unit yang tutup kasirnya
     * terlambat, hasil "terakhir <= cut-off" akan diam-diam memakai angka
     * lama. Lebih baik unit itu tidak muncul dan terlihat perlu konfirmasi.
     *
     * Unit tanpa closing pada tanggal cut-off TIDAK dikembalikan sebagai 0.
     * Caller wajib memeriksa unitTanpaClosingCutoff().
     *
     * @return array<int, array{unit_id:int, nama_unit:?string, saldo:?int}>
     */
    public static function querySaldoKasCutoff(?string $tanggalCutoff = null): array
    {
        $cutoff = $tanggalCutoff ?? FinanceScopeService::kasBankCutoffDate();
        $db     = db_connect();

        // Alias WAJIB. Tanpa alias, `tutup_kasir.unit` di query string tidak
        // ikut dapet DBPrefix, jadi jadi `tutup_kasir.unit` mentah sementara
        // tabelnya `db_tutup_kasir` -> Unknown column. Alias juga évite
        // ambiguitas dengan tabel `unit` yang di-join.
        $rows = $db->table('tutup_kasir tk')
            ->select('tk.unit as unit_id, u.NAMA_UNIT as nama_unit, tk.akhir_cash as saldo')
            ->join('unit u', 'u.idunit = tk.unit', 'left')
            ->where('tk.tanggal', $cutoff)
            ->where('tk.unit IS NOT NULL', null, false)
            ->orderBy('tk.unit', 'ASC')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'unit_id'          => (int) $r['unit_id'],
                'nama_unit'        => $r['nama_unit'] ?? null,
                'saldo'            => (int) ($r['saldo'] ?? 0),
                'butuh_konfirmasi' => false,
            ];
        }

        return $out;
    }

    /**
     * Unit aktif yang TIDAK punya closing kas pada tanggal cut-off.
     *
     * Unit 50 (Head Office) masuk daftar ini pada data saat ini: closing
     * terakhirnya 2026-09-28 dan tidak ada pada 30 Sep. Nilainya TIDAK
     * ditebak — hanya ditandai perlu konfirmasi Finance.
     *
     * @return int[]
     */
    public static function unitTanpaClosingCutoff(?string $tanggalCutoff = null): array
    {
        $cutoff = $tanggalCutoff ?? FinanceScopeService::kasBankCutoffDate();
        $db     = db_connect();

        $ada = $db->table('tutup_kasir tk')
            ->select('tk.unit as unit_id')
            ->where('tk.tanggal', $cutoff)
            ->where('tk.unit IS NOT NULL', null, false)
            ->groupBy('tk.unit')
            ->get()
            ->getResultArray();

        $sudah = array_map(static fn ($r) => (int) $r['unit_id'], $ada);

        $semua = $db->table('unit')->select('idunit')->get()->getResultArray();
        $ids   = array_map(static fn ($r) => (int) $r['idunit'], $semua);

        return array_values(array_diff($ids, $sudah));
    }
}
