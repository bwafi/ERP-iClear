<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * Sumber tunggal saldo awal Tutup Kasir.
 *
 * KONSEP:
 *   Saldo awal = saldo kas yang benar-benar diteruskan. Bukan angka tetap,
 *   bukan sisa penghitung, dan bukan angka yang dikarang sistem.
 *
 * URUTAN SUMBER (berhenti di yang pertama yang tersedia):
 *
 *   1. CLOSING SEBELUMNYA  `tutup_kasir.akhir_cash` untuk unit yang sama pada
 *                         tanggal paling akhir yang strictly < tanggalClosing.
 *                         Ini carry-forward: saldo akhir hari N menjadi saldo
 *                         awal hari N+1. Tapi TIDAK semua closing sah —
 *                         lihat "Kenapa closing pada jendela reset KasBank
 *                         ditolak" di bawah.
 *
 *   2. BASELINE PERIODE   `opening_kas.opening` pada rekening KAS unit tsb,
 *                         barisnya cukup ADA pada tanggal cut-off. Dipakai
 *                         `KasOpeningService` supaya definisi baseline TIDAK
 *                         diduplikasi di sini.
 *
 *   3. BELUM DITETAPKAN    Tidak ada fallback. Tidak ke Rp1.000.000, tidak ke
 *                         `kas_masuk`, tidak ke 0, tidak ke saldo lama.
 *                         Pemanggil wajib menampilkan kondisinya supaya
 *                         angka palsu tidak pernah dibaca sebagai saldo riil.
 *
 * Kenapa opening TIDAK lagi diverifikasi:
 *   Opening KAS adalah saldo riil uang fisik laci yang Finance input pada
 *   tanggal cut-off; begitu barisnya tersimpan, angka itu langsung dipakai
 *   sebagai baseline. Tidak ada perbandingan dengan `tutup_kasir.akhir_cash`
 *   dan tidak ada status `BELUM_VERIFIKASI / TERVERIFIKASI / TIDAK_COCOK`.
 *   Ini prinsip yang sama dengan guard Setor/Tarik
 *   (`KasBankCutoffService::openingTersedia()`), jadi kedua sisi tidak
 *   memakai dua standar berbeda untuk masalah yang sama.
 *
 * Kenapa tanggal baseline harus BERLAKU pada tanggal closing:
 *   Baseline opening dicatat pada `Finance::kasBankCutoffDate()` — posisi laci PADA
 *   tanggal itu. Memakainya untuk closing yang tanggalnya lebih awal berarti
 *   memakai informasi dari masa depan: baseline tanggal cut-off tidak boleh
 *   jadi saldo awal closing sebelumnya. Sebaliknya, menutup kasir pada tanggal
 *   cut-off itu justru harus boleh memakai baseline tanggal yang sama — itu
 *   HARI PERTAMA, dan baseline tersebut memang tercatat pada tanggal itu.
 *
 *   Syaratnya karena itu `tanggal_baseline <= tanggal_closing`, persis sama
 *   dengan `KasOpeningService::openingBerlaku()` yang dipakai Finance untuk
 *   membaca baseline di jalur lain. Dua jenis baseline (Opening KAS dan
 *   statement bank) memakai aturan tanggal yang sama, supaya tidak ada
 *   standar ketiga yang muncul diam-diam.
 *
 * Kenapa `closing sebelumnya` DI UTAMAKAN dari `opening_kas`:
 *   Opening KAS itu BASELINE posisi kas pada awal periode — baseline yang SAMA
 *   yang dipegang `KasOpeningService` / `KasBankCutoffService` untuk saldo buku.
 *   Opening bukan tambahan transaksi dan tidak boleh dijumlahkan dengan apa pun.
 *   Setelah hari pertama ditutup, posisi kas digerakkan `akhir_cash`, bukan
 *   opening lagi — kalau tidak, setiap hari akan memakai baseline periode
 *   sehingga saldo melenceng dari saldo bendahara.
 *
 * Kenapa closing dari LEDGER LAMA TIDAK BOLEH menimpa baseline periode baru:
 *   Cut-off KasBank (`Finance::kasBankCutoffDate`) adalah hari terakhir
 *   ledger LAMA; hari berikutnya (`Finance::kasBankPeriodeMulaiDate`) adalah
 *   hari pertama ledger BARU, yang posisinya berasal dari baseline cut-off
 *   (Opening KAS / statement bank), bukan dari penjumlahan closing lama.
 *
 *   Closing yang bertanggal <= cut-off mencatat `akhir_*` dengan ledger lama,
 *   jadi angkanya tidak sebanding dengan baseline baru. Untuk closing target
 *   di periode baru (>= periodeMulai), closing dari ledger lama — termasuk
 *   yang jauh sebelum cut-off — DITOLAK. Kalau tidak, seluruh periode baru
 *   dibuka dengan angka pra-reset (pada data produksi: closing 8 Okt
 *   Rp33.389.372 alih-alih baseline cut-off).
 *
 *   Setelah ada closing pertama DI DALAM periode baru, carry-forward antar-hari
 *   periode baru berjalan normal (sumber dan target sama-sama >= periodeMulai).
 *
 *   Penolakan dilakukan sebagai PASCA-FILTER atas baris terakhir, bukan
 *   sebagai `WHERE` di query. Bedanya menentukan hasil: kalau baris terakhir
 *   dibuang lewat WHERE, query jatuh balik ke closing yang lebih lama dan
 *   angka legacy tetap bocor; dengan pasca-filter, baris terakhir gugur dan
 *   sumber jatuh ke baseline cut-off.
 *
 * Kenapa TIDAK `getRow()` tanpa ordering:
 *   Ada closing ganda di data lama (unit 3 tgl 2 Okt, unit 5 tgl 1 Okt).
 *   `getRow()` tanpa ORDER BY mengembalikan baris yang tidak dijamin, jadi
 *   saldo awal bisa berubah-ubah antar request. Karena itu closing terakhir
 *  WAJIB dipilih dengan `ORDER BY tanggal DESC, id DESC LIMIT 1`.
 *
 * @see \App\Controllers\TutupKasir::index()
 */
class TutupKasirSaldoAwal
{
    /** Sumber saldo awal. */
    public const SUMBER_CLOSING = 'closing_sebelumnya';
    public const SUMBER_OPENING = 'opening_kas';
    public const SUMBER_ALOKASI = 'alokasi_shared';
    public const SUMBER_BELUM  = 'belum_ditetapkan';

    /** Nilai opening yang berarti "dipakai" oleh Tutup Kasir. */
    public const BELUM_ADA = null;

    /**
     * COA Kas Besar, acuannya ModeKasBank::COA_KAS_BESAR.
     *
     * Disalin sebagai konstanta lokal supaya file yang dilarang tidak perlu
     * disentuh. Nilainya internal, bukan input pengguna.
     */
    private const COA_KAS_BESAR = '1010101000';

    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // =================================================================
    // PENENTUAN SALDO AWAL
    // =================================================================

    /**
     * Saldo awal KAS unit pada $tanggal.
     *
     * @param int    $unitId  unit pemilik laci
     * @param string $tanggal YYYY-MM-DD
     *
     * @return array{
     *     ada:bool,
     *     nilai:int|null,
     *     sumber:string,
     *     tanggal:string|null,
     *     akun_kas_bank_id:int|null,
     *     closing_id:int|null,
     *     pesan:string
     * }
     *   `nilai` bernilai null bila belum ditetapkan. PEMANGGIL WAJIB
     *   memeriksa `ada` dulu — nilai 0 yang sah (laci kosong) tetap `ada`=true.
     */
    public function saldoAwalKas(int $unitId, string $tanggal): array
    {
        $closing = $this->closingTerakhir($unitId, $tanggal);

        if ($closing !== null) {
            return [
                'ada'              => true,
                'nilai'            => (int) $closing['akhir_cash'],
                'sumber'           => self::SUMBER_CLOSING,
                'tanggal'          => (string) $closing['tanggal'],
                'akun_kas_bank_id' => null,
                'closing_id'       => (int) $closing['idtutupkasir'],
                'pesan'            => 'Saldo awal diteruskan dari tutup kasir '
                    . $closing['tanggal'] . ' (saldo akhir saat itu).',
            ];
        }

        $akunId  = $this->akunKasUnit($unitId);
        $opening = $this->openingKas($akunId, $tanggal);

        if ($opening['ada']) {
            return [
                'ada'              => true,
                'nilai'            => $opening['nilai'],
                'sumber'           => self::SUMBER_OPENING,
                'tanggal'          => $opening['tanggal'],
                'akun_kas_bank_id' => $akunId === null ? null : (int) $akunId,
                'closing_id'       => null,
                'pesan'            => 'Saldo awal memakai baseline Opening KAS periode ini (Rp '
                    . number_format($opening['nilai'], 0, ',', '.') . ').',
            ];
        }

        return [
            'ada'              => false,
            'nilai'            => self::BELUM_ADA,
            'sumber'           => self::SUMBER_BELUM,
            'tanggal'          => null,
            'akun_kas_bank_id' => $akunId === null ? null : (int) $akunId,
            'closing_id'       => null,
            'pesan'            => $this->pesanBelumTersedia($akunId, $opening['alasan']),
        ];
    }

    /**
     * Pesan "belum bisa jadi saldo awal".
     *
     * Kalau baseline-nya ADA tapi ditolak (bertanggal di masa depan), alasan
     * dihitung lebih dulu supaya kasir tahu apa yang harus diperbaiki —
     * bukan hanya "belum ada".
     */
    private function pesanBelumTersedia(?int $akunId, string $alasan): string
    {
        if ($akunId === null) {
            return 'Belum ada rekening KAS aktif untuk unit ini, jadi saldo awal belum bisa ditetapkan. '
                . 'Tambahkan rekening dulu di menu Kas & Bank.';
        }

        if ($alasan !== '') {
            return $alasan . ' Saldo awal Tutup Kasir belum ditetapkan.';
        }

        return 'Belum ada baseline Opening KAS untuk rekening KAS unit ini, dan tidak ada tutup kasir '
            . 'yang bisa dipakai sebagai sumber. Saldo awal belum ditetapkan — ZERO TIDAK boleh '
            . 'disimpulkan dari halaman ini.';
    }

    /**
     * Saldo awal BANK/TRANSFER unit pada $tanggal.
     *
     * URUTAN SUMBER (berhenti di yang pertama tersedia):
     *
     *   1. CLOSING SEBELUMNYA  `tutup_kasir.akhir_transfer` — carry-forward,
     *                          persis sama seperti sisi KAS, termasuk
     *                          syarat `carryForwardSah()`: closing pada
     *                          jendela reset KasBank gugur ke jalur 2/3.
     *   2. REKENING BANK MILIK UNIT — `akunBankUnit()`, jalur lama.
     *   3. ALOKASI REKENING BERSAMA — unit tidak punya BANK sendiri, tapi
     *                          punya baris alokasi pada rekening BANK shared
     *                          (`akunBankUnitTerAlokasi()`).
     *   4. BELUM DITETAPKAN    Tidak ada fallback ke 0.
     *
     * Yang berbeda antara sisi KAS dan sisi TRANSFER hanya definisi
     * BASELINE-nya: KAS memakai `opening_kas`, TRANSFER memakai statement
     * bank di `saldo_awal_kas_bank`.
     *
     * PENTING untuk jalur 3 (rekening bersama):
     *   Nilai yang dikembalikan adalah ALOKASI unit itu saja, bukan saldo
     *   fisik seluruh rekening. Satu rekening shared bisa dialokasikan ke
     *   beberapa unit (mis. Rp20.000.000 dibagi Rp10.000.000 per unit), dan
     *   setiap unit hanya boleh melihat bagian miliknya. Angka saldo
     *   berjalan (alokasi + movement) TIDAK pernah dibaca di sini —
     *   `netMovement()` sengaja tidak dipanggil supaya movement periode
     *   tidak pernah bocor jadi saldo awal.
     *
     * @return array{ada:bool, nilai:int|null, sumber:string, tanggal:string|null, pesan:string}
     */
    public function saldoAwalTransfer(int $unitId, string $tanggal): array
    {
        $closing = $this->closingTerakhirTransfer($unitId, $tanggal);

        if ($closing !== null) {
            return [
                'ada'     => true,
                'nilai'   => (int) $closing['akhir_transfer'],
                'sumber'  => self::SUMBER_CLOSING,
                'tanggal' => (string) $closing['tanggal'],
                'pesan'   => 'Saldo transfer awal diteruskan dari tutup kasir '
                    . $closing['tanggal'] . '.',
            ];
        }

        // 2. Rekening bank MILIK UNIT (tipe BANK, bukan shared HO).
        $akunBank = $this->akunBankUnit($unitId);
        $alokasi  = null;

        // 3. Unit tidak punya BANK sendiri -> pakai rekening BANK shared yang
        //    punya alokasi untuk unit ini. Tanpa alokasi, unit berhak atas 0.
        if ($akunBank === null) {
            $alokasi = $this->akunBankUnitTerAlokasi($unitId);

            if ($alokasi === null) {
                return [
                    'ada'     => false,
                    'nilai'   => self::BELUM_ADA,
                    'sumber'  => self::SUMBER_BELUM,
                    'tanggal' => null,
                    'pesan'   => 'Belum ada rekening bank milik unit ini dan unit ini tidak '
                        . 'punya alokasi pada rekening bank bersama, jadi saldo transfer awal '
                        . 'belum ditetapkan.',
                ];
            }

            $akunBank = (int) $alokasi['akun_id'];
        }

        // Baseline statement bank, bukan Opening KAS laci.
        // Delegasi ke KasBankCutoffService supaya definisi "baseline rekening"
        // hanya punya satu implementasi.
        //
        // Syarat tanggalnya SAMA dengan opening KAS: `tanggal <= $tanggal`.
        // Baseline berlaku pada tanggal closing atau sebelumnya; yang
        // terlarang adalah baseline bertanggal SETELAH closing (data masa
        // depan). Statement pada tanggal cut-off tidak boleh jadi saldo awal
        // closing sebelumnya, tapi boleh jadi saldo awal closing di tanggal
        // cut-off itu sendiri.
        $statement = $this->baselineBank($akunBank, $tanggal);

        // `baselineBank()` mengembalikan baris PLUS `alasan` kalau baris itu ada
        // tapi tidak boleh dipakai (tanggalnya di masa depan). Dulu baris
        // bertanggal masa depan tetap dianggap sah karena pemanggil hanya
        // mengecek `!== null` — jadi baseline 2 Nov bisa jadi saldo awal
        // closing 8 Okt. `alasan` yang terisi = DITOLAK.
        if ($statement !== null && (string) ($statement['alasan'] ?? '') === '') {
            // Jalur rekening BERSAMA: yang jadi saldo awal unit adalah
            // ALOKASInya, bukan saldo fisik seluruh rekening.
            if ($alokasi !== null) {
                $nominal = (int) $alokasi['nominal'];

                return [
                    'ada'     => true,
                    'nilai'   => $nominal,
                    'sumber'  => self::SUMBER_ALOKASI,
                    'tanggal' => (string) $statement['tanggal'],
                    'pesan'   => 'Saldo transfer awal memakai alokasi unit atas rekening '
                        . 'bersama (Rp ' . number_format($nominal, 0, ',', '.')
                        . ' dari total Rp ' . number_format((int) $statement['saldo'], 0, ',', '.')
                        . ' pada cut-off ' . (string) $statement['tanggal'] . ').',
                ];
            }

            return [
                'ada'     => true,
                'nilai'   => (int) $statement['saldo'],
                'sumber'  => self::SUMBER_OPENING,
                'tanggal' => (string) $statement['tanggal'],
                'pesan'   => 'Saldo transfer awal memakai baseline statement bank (Rp '
                    . number_format((int) $statement['saldo'], 0, ',', '.') . ').',
            ];
        }

        $alasan = (string) ($statement['alasan'] ?? '');

        if ($alasan === '') {
            $alasan = $alokasi !== null
                ? 'Belum ada baseline statement bank untuk rekening bersama yang dialokasikan '
                    . 'ke unit ini, dan tidak ada tutup kasir yang bisa dipakai sebagai sumber. '
                    . 'Saldo transfer awal belum ditetapkan.'
                : 'Belum ada baseline statement bank untuk rekening unit ini, dan tidak ada '
                    . 'tutup kasir yang bisa dipakai sebagai sumber. Saldo transfer awal '
                    . 'belum ditetapkan.';
        }

        return [
            'ada'     => false,
            'nilai'   => self::BELUM_ADA,
            'sumber'  => self::SUMBER_BELUM,
            'tanggal' => null,
            'pesan'   => $alasan,
        ];
    }

    // =================================================================
    // CLOSING SEBELUMNYA (carry-forward yang jujur)
    // =================================================================

    /**
     * Closing KAS terakhir unit SEBELUM $tanggal.
     *
     * Syarat: unit sama, tanggal < $tanggal, diurutkan tanggal DESC lalu id DESC,
     * ambil satu. `akhir_cash` NULL TIDAK dipakai — closing yang belum punya
     * saldo bukan posisi kas yang bisa diteruskan.
     *
     * Ditolak juga kalau tanggalnya berasal dari ledger lama (<= cut-off)
     * sementara $tanggal berada di periode KasBank baru — lihat
     * `carryForwardSah()`. Penolakan itu membuat method ini mengembalikan
     * null, sehingga pemanggil jatuh ke baseline cut-off.
     *
     * @return array<string,mixed>|null
     */
    public function closingTerakhir(int $unitId, string $tanggal): ?array
    {
        return $this->closingTerakhirFor('akhir_cash', $unitId, $tanggal);
    }

    /** Sama seperti closingTerakhir(), untuk sisi transfer. */
    public function closingTerakhirTransfer(int $unitId, string $tanggal): ?array
    {
        return $this->closingTerakhirFor('akhir_transfer', $unitId, $tanggal);
    }

    private function closingTerakhirFor(string $kolom, int $unitId, string $tanggal): ?array
    {
        if ($unitId <= 0 || $kolom === '' || ! preg_match('/^[a-z_]+$/', $kolom)) {
            return null;
        }

        $row = $this->db->table('tutup_kasir')
            ->select('idtutupkasir, tanggal, ' . $kolom . ' as nilai')
            ->where('unit', $unitId)
            ->where('tanggal <', $tanggal)
            ->where($kolom . ' IS NOT NULL', null, false)
            ->orderBy('tanggal', 'DESC')
            ->orderBy('idtutupkasir', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        // Jendela reset KasBank: closing yang dihitung dengan ledger LAMA
        // (tanggal <= cut-off) tidak sebanding dengan baseline periode baru,
        // jadi tidak boleh menimpa baseline saat closing target berada di
        // periode baru. Post-filter atas baris INI saja (bukan WHERE) supaya
        // tidak jatuh balik ke closing yang lebih lama dan juga berasal dari
        // ledger lama.
        if (! $this->carryForwardSah((string) $row['tanggal'], $tanggal)) {
            return null;
        }

        return [
            'idtutupkasir' => (int) $row['idtutupkasir'],
            'tanggal'     => (string) $row['tanggal'],
            'nilai'       => (int) $row['nilai'],
            $kolom        => (int) $row['nilai'],
        ];
    }

    /**
     * Apakah closing bertanggal $tanggalSource sah jadi sumber carry-forward
     * untuk closing target $tanggalTarget.
     *
     * Aturan inti periode baru:
     *   Closing dari LEDGER LAMA (`< kasBankPeriodeMulaiDate`, yaitu tanggal
     *   cut-off dan sebelumnya) TIDAK BOLEH menjadi sumber bagi closing di
     *   PERIODE BARU (`>= kasBankPeriodeMulaiDate`). Posisi closing lama
     *   dihitung dengan ledger pra-reset, sehingga tidak sebanding dengan
     *   baseline cut-off. Sumber yang sah untuk hari-hari periode baru adalah
     *   baseline cut-off (`openingKas()` / `baselineBank()`), sampai ada
     *   closing pertama DI DALAM periode baru; setelah itu carry-forward
     *   antar-hari periode baru berjalan normal.
     *
     * Di dalam SATU ledger (target dan sumber sama-sama pra- atau sama-sama
     * pasca-reset), carry-forward biasa tetap berlaku. Khusus sumber tepat di
     * jendela `[cut-off, periodeMulai)` tetap ditolak secara defensif (dulu:
     * satu-satunya jendela yang ditolak) supaya jendela reset kosong pun tidak
     * pernah dipakai.
     *
     * Hanya memakai accessor KasBank — `Finance::$cutoffDate` milik modul lain
     * sengaja tidak disentuh.
     */
    private function carryForwardSah(string $tanggalSource, string $tanggalTarget): bool
    {
        if ($tanggalSource === '' || $tanggalTarget === '') {
            return false;
        }

        $cutoff = FinanceScopeService::kasBankCutoffDate();
        $mulai  = FinanceScopeService::kasBankPeriodeMulaiDate();

        // Sumber dari ledger lama tidak boleh bocor ke periode baru.
        if ($tanggalTarget >= $mulai && $tanggalSource < $mulai) {
            return false;
        }

        // Jendela reset [cut-off, periodeMulai) selalu ditolak (defensif).
        return $tanggalSource < $cutoff || $tanggalSource >= $mulai;
    }

    // =================================================================
    // REKENING & BASELINE
    // =================================================================

    /**
     * Rekening KAS aktif milik unit.
     *
     * Urutan WAJIB eksplisit (sama dengan resolveAkun() ModeKasBank, tapi
     * ditulis ulang di sini supaya file yang dilarang tidak perlu disentuh):
     * Kas Besar (COA 1010101000) > rekening tanpa COA > Kas Kecil / COA lain.
     * Abaikan rekening shared & milik Finance/HO — itu bukan laci unit ini.
     */
    public function akunKasUnit(int $unitId): ?int
    {
        return $this->akunUnitTipe($unitId, 'KAS');
    }

    /** Rekening BANK aktif milik unit (bukan shared HO). */
    public function akunBankUnit(int $unitId): ?int
    {
        return $this->akunUnitTipe($unitId, 'BANK');
    }

    /**
     * Rekening BANK shared yang punya alokasi untuk unit ini.
     *
     * DIPAKAI SEBAGAI FALLBACK, setelah `akunBankUnit()` (rekening BANK
     * milik unit sendiri) lebih dulu ditolak. Jadi rekening non-shared tidak
     * pernah digantikan oleh rekening bersama.
     *
     * Resolusi akunnya dipinjam dari `KasBankSourceMovement::rekeningDefaultUnit()`
     * supaya SATU definisi untuk dua hal yang harus selalu cocok:
     *   - ke mana transfer masuk unit ini diposting  (movement)
     *   - dari mana saldo awal unit ini diambil       (resolver ini)
     * Rantai di sana: BANK non-shared milik unit -> BANK shared yang punya
     * baris alokasi -> null. Rekening `is_finance_ho = 1` tidak pernah
     * terpilih.
     *
     * Syarat tambahan di sini: `SUM(nominal)` alokasi unit itu harus > 0.
     * Baris alokasi bernilai 0 berarti "hak terpetakan, saldo belum
     * dialokasikan" — itu BUKAN saldo awal.
     *
     * @return array{akun_id:int, nominal:int}|null
     *   null = unit ini tidak berhak atas rekening bank mana pun.
     */
    public function akunBankUnitTerAlokasi(int $unitId): ?array
    {
        if ($unitId <= 0) {
            return null;
        }

        $akunId = (new KasBankSourceMovement($this->db))->rekeningDefaultUnit($unitId);

        if ($akunId === null || $akunId <= 0) {
            return null;
        }

        // Rekening yang dipilih harus benar-benar rekening BERSAMA. Kalau
        // bukan, pemanggil seharusnya sudah berhenti di akunBankUnit();
        // pengulangan ini menutup celah bila resolver berubah di kemudian hari.
        $akun = $this->db->table('akun_kas_bank')
            ->select('is_shared, is_finance_ho, tipe, status')
            ->where('idakun_kas_bank', $akunId)
            ->get()
            ->getRow();

        if ($akun === null
            || (int) $akun->is_shared !== 1
            || (int) $akun->is_finance_ho !== 0
            || $akun->tipe !== 'BANK'
            || $akun->status !== 'aktif'
        ) {
            return null;
        }

        $nominal = (int) ($this->db->table('alokasi_saldo_kas_bank')
            ->selectSum('nominal')
            ->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->get()
            ->getRow()->nominal ?? 0);

        if ($nominal <= 0) {
            return null;
        }

        return ['akun_id' => $akunId, 'nominal' => $nominal];
    }

    private function akunUnitTipe(int $unitId, string $tipe): ?int
    {
        if ($unitId <= 0) {
            return null;
        }

        // CATATAN: `orderBy(..., escape: false)` tidak mengikat placeholder `?`.
        // CASE-nya harus ditulis lengkap dengan nilai literalnya.
        $coa = $this->db->escape(self::COA_KAS_BESAR);

        $row = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank')
            ->where('unit_id', $unitId)
            ->where('tipe', $tipe)
            ->where('status', 'aktif')
            ->where('is_shared', 0)
            ->where('is_finance_ho', 0)
            ->orderBy('CASE WHEN no_akun_coa = ' . $coa . ' THEN 0 ELSE 1 END', 'ASC', false)
            ->orderBy('CASE WHEN no_akun_coa IS NULL THEN 1 ELSE 2 END', 'ASC', false)
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()
            ->getRow();

        return $row === null ? null : (int) $row->idakun_kas_bank;
    }

    /**
     * Baseline Opening KAS pada tanggal cut-off, lewat KasOpeningService.
     *
     * Syarat WAJIB, yang tersisa hanya satu:
     *
     *   1. Baris ada di `Finance::kasBankCutoffDate()`.
     *
     * Tidak ada lagi syarat status: opening KAS yang tersimpan langsung sah
     * sebagai baseline (tidak ada verifikasi).
     *
     *   2. Tanggal baseline TIDAK setelah $tanggalClosing. Baseline pada
     *      tanggal cut-off tidak boleh jadi saldo awal closing sebelumnya.
     *
     * Sengaja TIDAK memakai `opening()`: method itu mengembalikan 0 kalau
     * baris tidak ada, dan 0 itu sah (laci kosong) — sehingga tidak bisa
     * membedakan "tidak ada baseline". Yang dibedakan di sini justru kondisi
     * TIDAK BOLEH dipakai, dan itu dikembalikan lewat flag `ada` = false
     * plus `alasan` supaya pesan yang tampil menyebut penyebabnya.
     *
     * @return array{ada:bool, nilai:int, tanggal:string|null, alasan:string}
     */
    private function openingKas(?int $akunId, string $tanggalClosing): array
    {
        $kosong = ['ada' => false, 'nilai' => 0, 'tanggal' => null, 'alasan' => ''];

        if ($akunId === null || $akunId <= 0) {
            return $kosong;
        }

        $cutoff = FinanceScopeService::kasBankCutoffDate();
        $row    = (new KasOpeningService())->openingAt($akunId, $cutoff);

        if ($row === null) {
            return $kosong;
        }

        // --- syarat 2: baseline tidak boleh bertanggal di masa depan ---
        //
        // Aturannya `tanggal_baseline <= tanggal_closing` — sama dengan
        // `KasOpeningService::openingBerlaku()` yang dipakai Finance untuk
        // membaca baseline. Baseline dicatat sebagai POSISI KAS pada tanggal
        // itu, jadi menutup kasir pada tanggal yang sama memakainya bukan
        // memakai data masa depan.
        //
        // Dulu aturan ini `>=` (harus strictly sebelum). Akibatnya menutup
        // kasir pada tanggal cut-off — yaitu HARI PERTAMA periode — selalu
        // ditolak, karena baseline juga bertanggal cut-off. Hari pertama
        // justru hari yang paling butuh baseline.
        $tanggalBaseline = (string) ($row->tanggal ?? $cutoff);

        if ($tanggalBaseline > $tanggalClosing) {
            return [
                'ada'     => false,
                'nilai'   => 0,
                'tanggal' => $tanggalBaseline,
                'alasan'  => 'Baseline Opening KAS bertanggal ' . $tanggalBaseline . ' berada setelah '
                    . 'tanggal closing ' . $tanggalClosing . ', jadi tidak bisa dipakai sebagai '
                    . 'saldo awal (baseline harus berlaku pada tanggal closing atau sebelumnya).',
            ];
        }

        return [
            'ada'     => true,
            'nilai'   => (int) $row->opening,
            'tanggal' => $tanggalBaseline,
            'alasan'  => '',
        ];
    }

    /**
     * Baseline statement bank milik rekening tsb.
     *
     * Kolom `saldo_awal_kas_bank` memang "statement/mirror bank", bukan
     * Opening KAS laci — jadi keduanya sengaja tidak dicampur.
     *
     * Syarat tanggal sama seperti opening KAS: baseline `kasBankCutoffDate()` boleh
     * dipakai untuk closing yang tanggalnya SAMA DENGAN atau setelah baseline.
     * Closing yang tanggalnya SEBELUM baseline tidak punya sumber yang sah,
     * jadi dikembalikan sebagai belum tersedia — bukan memakai angka masa depan.
     *
     * @return array{saldo:int, tanggal:string, alasan:string}|null
     *   `null` = tidak ada baris sama sekali. `alasan` terisi kalau baris
     *   ADA tapi tidak boleh dipakai (mis. tanggalnya di masa depan).
     */
    private function baselineBank(int $akunId, string $tanggalClosing): ?array
    {
        $cutoff = FinanceScopeService::kasBankCutoffDate();
        $row    = $this->db->table('saldo_awal_kas_bank')
            ->select('saldo, tanggal')
            ->where('akun_kas_bank_id', $akunId)
            ->where('tanggal', $cutoff)
            ->get()
            ->getRow();

        if ($row === null) {
            return null;
        }

        $tanggalBaseline = (string) ($row->tanggal ?? $cutoff);

        if ($tanggalBaseline > $tanggalClosing) {
            return [
                'saldo'   => (int) $row->saldo,
                'tanggal' => $tanggalBaseline,
                'alasan'  => 'Baseline statement bank bertanggal ' . $tanggalBaseline . ' berada setelah '
                    . 'tanggal closing ' . $tanggalClosing
                    . ', jadi tidak bisa dipakai sebagai saldo awal.',
            ];
        }

        return ['saldo' => (int) $row->saldo, 'tanggal' => $tanggalBaseline, 'alasan' => ''];
    }
}