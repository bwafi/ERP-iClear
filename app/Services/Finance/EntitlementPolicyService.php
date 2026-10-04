<?php

namespace App\Services\Finance;

use Config\Finance;
use RuntimeException;

/**
 * Definisi tunggal "hak memakai rekening" untuk ERP ini.
 *
 * Service ini ada hanya untuk satu alasan: ada DUA sumber yang selama ini
 * bisa berbeda, dan keduanya sering disalahartikan satu sama lain.
 *
 *   1. Config\Finance::$rekeningResmiByBank  = POLICY. Disahkan bisnis, jadi rujukan.
 *       Di-key bank_idbank, bukan idakun_kas_bank: kolom itu AUTO_INCREMENT dan
 *       berbeda antar-linse data, sedangkan bank_idbank stabil.
 *   2. alokasi_saldo_kas_bank          = DATA. Sumber runtime yang sebenarnya.
 *
 * Runtime TIDAK bergantung pada array policy — dia membaca entitlement dari
 * tabel. Policy dipakai sebagai PENGAWAS: kalau data dan policy berbeda,
 * hasilnya dilaporkan sebagai drift, bukan diperbaiki diam-diam.
 *
 * TIGA HAL YANG HARUS TETAP TERPISAH
 * ---------------------------------
 *   - HAK memakai rekening   -> entitlement (policy + alokasi)
 *   - SALDO REAL rekening    -> saldo_awal_kas_bank status VERIFIED
 *   - ALOKASI saldo ke unit  -> alokasi_saldo_kas_bank.nominal
 *
 * Consequences yang sering salah:
 *
 *   - nominal = 0  => unit PEMILIK HAK memakai rekening, hanya belum ada
 *                     saldo yang dialokasikan. BUKAN "unit tidak punya hak".
 *   - nominal = 0  => TIDAK berarti saldo fisik rekening itu 0. Saldo fisik
 *                     hanya boleh dibaca dari statement VERIFIED.
 *   - Unit tidak punya alokasi TIDAK berarti saldo rekening 0; bisa berarti
 *                     saja unit itu memang tidak berhak, atau datanya belum
 *                     diisi.
 *
 * SEJARAH TRANSAKSI TIDAK PERNAH jadi sumber di sini. Transaksi hanya
 * dipakai auditor untuk menemukan anomali historis yang dilaporkan —
 * bukan untuk menurunkan siapa pemilik sah sebuah rekening.
 */
class EntitlementPolicyService
{
    /**
     * Cache bank_idbank => idakun_kas_bank per instance.
     *
     * Peta ini dibaca oleh hampir semua policy check, dan satu request bisa
     * memanggilnya puluhan kali. instance dibuat ulang per request, jadi
     * cache ini tidak pernah stale lintas request -- dan tidak perlu, karena
     * auto_increment tidak berubah di tengah request.
     *
     * @var array<string, int>|null
     */
    private ?array $cachePetaAkun = null;

    /**
     * Peta policy resmi: akun_kas_bank_id => [unit_id, ...].
     *
     * Bentuk account-id-keyed ini DITURUNKAN dari policy yang di-key
     * bank_idbank di Config, jadi nomor AUTO_INCREMENT tidak pernah masuk
     * ke dalam keputusan policy.
     *
     * @return array<int, list<int>>
     */
    public function mappingResmi(): array
    {
        $out = [];

        foreach ($this->petakanResmi() as $idbank => $units) {
            $akunId = $this->akunUntukBank((string) $idbank);
            if ($akunId === null) {
                // Rekening ini tidak ada di DB lineages ini. Melewati lebih
                // aman daripada menebak: tanpa akun, tidak ada yang bisa salah
                // entitlement, dan report bisa melaporkannya sebagai unresolved.
                continue;
            }

            $ids = [];
            foreach ((array) $units as $u) {
                $u = (int) $u;
                if ($u > 0) {
                    $ids[] = $u;
                }
            }
            sort($ids);
            $out[$akunId] = $ids;
        }

        return $out;
    }

    /**
     * Policy mentah dari config, di-key bank_idbank.
     *
     * @return array<string, list<int>>
     */
    public function petakanResmi(): array
    {
        return (array) (config(Finance::class)->rekeningResmiByBank ?? []);
    }

    /**
     * bank_idbank => idakun_kas_bank, hanya untuk rekening yang benar-benar ada.
     *
     * Ini SATU-SATUNYA tempat nomor rekening dibaca dari DB. Semua policy di
     * config di-key bank_idbank, jadi bentuk account-id-keyed yang dipakai
     * metode lain di kelas ini selalu lewat sini.
     *
     * @return array<string, int>
     */
    public function petaAkunPerBank(): array
    {
        if ($this->cachePetaAkun !== null) {
            return $this->cachePetaAkun;
        }

        $this->cachePetaAkun = [];

        $db = $this->db();
        if (! $db->tableExists('akun_kas_bank')) {
            return $this->cachePetaAkun;
        }

        $rows = $db->table('akun_kas_bank')
            ->select('idakun_kas_bank, bank_idbank')
            ->where('tipe', 'BANK')
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as $r) {
            $idbank = trim((string) $r['bank_idbank']);
            if ($idbank === '') {
                continue;
            }

            $akun = (int) $r['idakun_kas_bank'];

            // Satu bank_idbank tidak boleh punya dua rekening kas-bank. Kalau
            // terjadi mapping jadi ambigu dan HARUS gagal keras, bukan pilih
            // satu diam-diam.
            if (isset($this->cachePetaAkun[$idbank])) {
                throw new RuntimeException(sprintf(
                    'bank_idbank %s punya lebih dari satu rekening kas-bank (%d dan %d). '
                    . 'Peta bank ke rekening jadi ambigu dan tidak ditebak.',
                    $idbank,
                    $this->cachePetaAkun[$idbank],
                    $akun
                ));
            }

            $this->cachePetaAkun[$idbank] = $akun;
        }

        return $this->cachePetaAkun;
    }

    /**
     * idakun_kas_bank untuk satu bank_idbank, atau null kalau tidak ada.
     */
    public function akunUntukBank(string $idbank): ?int
    {
        return $this->petaAkunPerBank()[$idbank] ?? null;
    }

    /**
     * bank_idbank milik satu akun, atau null kalau akun ini bukan rekening bank.
     */
    public function idbankRekening(int $akunId): ?string
    {
        $found = array_search($akunId, $this->petaAkunPerBank(), true);

        return $found === false ? null : (string) $found;
    }

    /**
     * Unit yang berhak memakai satu akun, menurut POLICY.
     *
     * @return list<int>
     */
    public function unitResmi(int $akunId): array
    {
        return $this->mappingResmi()[$akunId] ?? [];
    }

    /**
     * Rekening Finance/HO: rekening kas Direksi, BUKAN rekening operasional unit.
     *
     * PENTING: ini TIDAK berarti "terbuka untuk semua unit". Kalau begitu,
     * user unit 50 (Head Office) otomatis mendapat akses hanya karena
     * is_finance_ho = 1 — itu bypass yang Fase 1 hapus. Akses Finance
     * berasal dari ROLE (Finance::$financeHoSourceRoles), bukan dari unitId.
     */
    public function adalahRekeningFinance(int $akunId): bool
    {
        $idbank = $this->idbankRekening($akunId);
        if ($idbank === null) {
            return false;
        }

        $daftar = array_map('strval', (array) (config(Finance::class)->financeHoBankIds ?? []));

        return in_array($idbank, $daftar, true);
    }

    /**
     * Rekening yang tunduk pada mekanisme SALDO REAL.
     *
     * Rekening Finance/HO dikecualikan: bukan rekening operasional unit dan
     * tidak punya statement cutoff.
     */
    public function wajibStatementVerifikasi(int $akunId): bool
    {
        if ($this->adalahRekeningFinance($akunId)) {
            return false;
        }

        $idbank = $this->idbankRekening($akunId);
        if ($idbank === null) {
            return false;
        }

        $daftar = array_map('strval', (array) (config(Finance::class)->rekeningWajibStatementByBank ?? []));

        return in_array($idbank, $daftar, true);
    }

    /**
     * Bentuk master yang diharapkan: bank_idbank, unit_id, is_shared.
     *
     * @return array{bank_idbank:string, unit_id:?int, is_shared:int}|null
     */
    public function masterHarapan(int $akunId): ?array
    {
        $idbank = $this->idbankRekening($akunId);
        if ($idbank === null) {
            return null;
        }

        $semua = (array) (config(Finance::class)->rekeningResmiMasterByBank ?? []);
        $row   = $semua[$idbank] ?? null;

        if ($row === null) {
            return null;
        }

        return [
            'bank_idbank' => $idbank,
            'unit_id'     => $row['unit_id'] === null ? null : (int) $row['unit_id'],
            'is_shared'   => (int) $row['is_shared'],
        ];
    }

    /**
     * Unit yang punya HAK memakai akun menurut DATA (alokasi_saldo_kas_bank).
     *
     * Ini sumber runtime. Sengaja TIDAK membaca policy, supaya alokasi yang
     * sudah benar-benar tertulis di database yang dipakai untuk otorisasi.
     *
     * @return list<int>
     */
    public function unitEntitledDb(?int $akunId): array
    {
        if ($akunId === null || $akunId <= 0 || ! $this->db()->tableExists('alokasi_saldo_kas_bank')) {
            return [];
        }

        $rows = $this->db()->table('alokasi_saldo_kas_bank')
            ->select('unit_id')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getResultArray();

        $ids = array_map(static fn ($r) => (int) $r['unit_id'], $rows);
        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * Apakah unit punya hak memakai akun ini?
     *
     * Mengembalikan TRUE bila baris alokasi ADA — berapa pun nominalnya.
     * `nominal = 0` tetap berarti hak pakai, bukan tanpa hak.
     */
    public function adakahEntitlement(int $akunId, ?int $unitId): bool
    {
        if ($unitId === null || $unitId <= 0) {
            return false;
        }

        return in_array($unitId, $this->unitEntitledDb($akunId), true);
    }

    /**
     * Apakah DATA dan POLICY untuk satu akun benar-benar sama?
     *
     * Dipakai migration (sebagai post-condition) dan command audit — bukan
     * untuk memperbaiki diam-diam. Bank hanya dianggap selaras bila DUA hal
     * benar: entitlement unit sama, dan bentuk master (bank_idbank, unit_id,
     * is_shared) sama. Rekening Finance/HO otomatis tidak boleh punya
     * allocation, jadi untuk akun itu penambahan unit apa pun tidak pernah
     * bisa lolos sebagai "selaras".
     */
    public function selarasDenganPolicy(int $akunId): bool
    {
        if ($this->unitEntitledDb($akunId) !== $this->unitResmi($akunId)) {
            return false;
        }

        $harapan = $this->masterHarapan($akunId);

        if ($harapan === null) {
            return true;
        }

        if (! $this->tableAda()) {
            return true;
        }

        $row = $this->db()->table('akun_kas_bank')
            ->select('bank_idbank, unit_id, is_shared')
            ->where('idakun_kas_bank', $akunId)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return false;
        }

        return (string) ($row['bank_idbank'] ?? '') === $harapan['bank_idbank']
            && ($row['unit_id'] === null ? null : (int) $row['unit_id']) === $harapan['unit_id']
            && (int) ($row['is_shared'] ?? 0) === $harapan['is_shared'];
    }

/**
     * Guard fail-closed untuk operasi rekening sensitif.
     *
     * HANYA untuk security boundary write path (validasi rekening sebelum
     * mutasi). JANGAN dipakai di listing/dropdown: listing dipanggil tiap
     * render dan yang dibutuhkan di sana hanya hak, bukan verdict policy.
     *
     * KENAPA INI ADALAH TAMBAHAN, BUKAN PENGGANTI `adakahEntitlement()`
     * --------------------------------------------------------------
     * `adakahEntitlement()` sengaja membaca DB saja, karena entri alokasi
     * itulah yang benar-benar dipakai untuk otorisasi runtime. Masalahnya:
     * kalau DB sendiri yang salah, DB akan membenarkan dirinya sendiri
     * terus-menerus dan tidak ada yang pernah tahu.
     *
     * Pemisahan tugas tetap dijaga, tapi kedua sisinya dikunci:
     *   - DB entitlement    -> HAK RUNTIME (sumber kebenaran operasional)
     *   - official mapping  -> KORROBORASI dan deteksi drift
     *
     * Aturan fail-closed:
     *   1. Unit tidak punya baris alokasi di DB             -> tolak (biasanya).
     *   2. Unit punya alokasi di DB tapi TIDAK ada di policy -> TOLAK + laporkan
     *      drift. Ini kasus berbahaya: DB memberi hak yang tidak pernah
     *      disahkan bisnis. Menolaknya lebih penting daripada membela DB,
     *      karena dana keluar dari sistem ini.
     *   3. Rekening Finance/HO yang punya baris alokasi     -> TOLAK. M4 sudah
     *      mengunci aturan ini, jadi menerimanya diam-diam di runtime
     *      akan membatalkan hasil M4.
     *
     * Biaya: TIDAK ada query tambahan. `unitEntitledDb()` sudah dibutuhkan
     * untuk langkah 1, dan `unitResmi()` hanya membaca array config yang
     * sudah di-cache CodeIgniter. Yang berat (`ringkasanDrift()` dengan join
     * ke master) hanya dipanggil pada cabang drift, bukan di setiap request.
     *
     * @return array{ok:bool, alasan:string, drift:list<string>}
     */
    public function guardEntitlementSensif(int $akunId, ?int $unitId): array
    {
        if ($akunId <= 0 || $unitId === null || $unitId <= 0) {
            return $this->gagalSensif(
                'Konteks unit tidak valid, hak pakai rekening tidak bisa dipastikan.',
                []
            );
        }

        // Rekening Finance/HO tidak punya unit asal, jadi entitas yang
        // berwenang adalah ROLE, bukan baris alokasi. Otorisasi role dicek
        // di lapisan pemanggil (BankRekeningValidator), di sini kita hanya
        // menjaga invarian datanya.
        if ($this->adalahRekeningFinance($akunId)) {
            if ($this->unitEntitledDb($akunId) !== []) {
                return $this->gagalSensif(
                    sprintf(
                        'Rekening kas Direksi (akun %d) tercatat punya alokasi unit. '
                        . 'Data ini melanggar aturan M4 dan tidak boleh dipakai sampai Finance memperbaikinya.',
                        $akunId
                    ),
                    $this->ringkasanDrift($akunId)
                );
            }

            return ['ok' => true, 'alasan' => '', 'drift' => []];
        }

        // Operational account: haknya per unit.
        $entitledDb = $this->unitEntitledDb($akunId);

        if (! in_array($unitId, $entitledDb, true)) {
            return $this->gagalSensif(
                sprintf(
                    'Rekening (akun %d) tidak dialokasikan ke Unit %d. '
                    . 'Kalau unit Anda memang berhak, hubungi Finance untuk memperbarui entitlement.',
                    $akunId,
                    $unitId
                ),
                []
            );
        }

        // Hak ada di DB. Dari sini baru kita tanya apakah policy setuju?
        $unitResmi = $this->unitResmi($akunId);

        if (! in_array($unitId, $unitResmi, true)) {
            $drift = $this->ringkasanDrift($akunId);

            return $this->gagalSensif(
                sprintf(
                    'Hak pakai rekening (akun %d) untuk Unit %d hanya ada di data, tidak ada di mapping resmi '
                    . '[%s]. Transaksi ditolak sampai mapping resmi diperbarui — hubungi Finance.',
                    $akunId,
                    $unitId,
                    $unitResmi === [] ? '(kosong)' : implode(', ', $unitResmi)
                ),
                $drift
            );
        }

        return ['ok' => true, 'alasan' => '', 'drift' => []];
    }

    /**
     * @param  list<string> $drift
     * @return array{ok:bool, alasan:string, drift:list<string>}
     */
    private function gagalSensif(string $alasan, array $drift): array
    {
        return ['ok' => false, 'alasan' => $alasan, 'drift' => $drift];
    }

    /**
     * Pesan drift yang bisa dibaca manusia, untuk exception migration & log.
     *
     * @return list<string>
     */
    public function ringkasanDrift(int $akunId): array
    {
        $pesan  = [];
        $policy = $this->unitResmi($akunId);
        $db     = $this->unitEntitledDb($akunId);

        if ($policy !== $db) {
            $pesan[] = sprintf(
                'entitlement data [%s] tidak sama dengan policy [%s] pada akun %d',
                $db === [] ? '(kosong)' : implode(',', $db),
                $policy === [] ? '(kosong)' : implode(',', $policy),
                $akunId
            );
        }

        if ($this->adalahRekeningFinance($akunId) && $db !== []) {
            $pesan[] = sprintf(
                'akun Finance/HO %d tidak boleh punya allocation unit, tapi punya [%s]',
                $akunId,
                implode(',', $db)
            );
        }

        return $pesan;
    }

    private function tableAda(): bool
    {
        $db = $this->db();

        return $db->tableExists('akun_kas_bank') && $db->tableExists('alokasi_saldo_kas_bank');
    }

    private function db()
    {
        return \Config\Database::connect();
    }
}