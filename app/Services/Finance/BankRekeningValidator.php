<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use App\Models\ModelBank;

/**
 * Validasi idbank untuk write path kas_masuk / kas_keluar / pembayaran.
 *
 * APA YANG DIPERBAIKI SEBELUMNYA
 * -----------------------------
 * 1. `idbank` dari form dulu langsung disimpan ke sumber, dan kegagalan baru
 *    ketahuan belakangan di ModeKasBank::resolveAkunDetail() yang hanya
 *    mengembalikan null + warning. Akibatnya kas masuk tetap tersimpan sebagai
 *    "berhasil" padahal tidak pernah masuk ledger.
 *
 * 2. `idbank` pernah di-(cast) ke (int) di beberapa tempat. idbank adalah
 *    VARCHAR: nilainya bisa 'BNI-001', '15', ' 15 ', atau '0'. Cast ke int
 *    mengubah 'BNI-001' -> 0 dan menganggap ' 15 ' sama dengan '15', jadi
 *    validasi berbasis hasil cast itu palsu. Di sini TIDAK ada cast; string
 *    dibandingkan apa adanya (hanya di-trim spasi tepi).
 *
 * APA YANG DIPERBAIKI PADA FASE 1
 * ------------------------------
 * Sebelumnya validator ini hanya memeriksa "rekening ada" dan "rekening aktif".
 * Tidak ada satu pun transversal ke unit, entitlement, atau is_shared.
 *
 * Akibatnya SETIAP unit bisa menulis ke rekening unit lain. Itulah sumber
 * seluruh polusi histori: 437 baris kas_masuk dari 6 unit berbeda ke rekening
 * milik unit lain, 12 baris Unit 5 di rekening Unit 3, 2 baris Unit 2 di
 * rekening kas Direksi — semuanya lolos karena "rekeningnya memang ada".
 *
 * Sekarang validate() menerima KONTEKS UNIT dan menolak transaksi yang unitnya
 * tidak berhak memakai rekening tersebut.
 *
 * TIGA HAL YANG TIDAK BOLEH DICAMPUR
 * ----------------------------------
 *   - HAK memakai rekening -> dicek di sini lewat entitlement.
 *   - SALDO REAL rekening  -> TIDAK dicek di sini. Itu urusan statement
 *     VERIFIED (KasBankCutoffService), bukan urusan validasi rekening.
 *   - ALOKASI nominal 0     -> tetap berarti punya HAK. Unit yang baris
 *     alokasinya 0 tetap boleh memakai rekening itu.
 */
class BankRekeningValidator
{
    protected ModelBank $bankModel;
    protected ModelAkunKasBank $akunModel;
    protected EntitlementPolicyService $policy;

    public function __construct(
        ?ModelBank $bank = null,
        ?ModelAkunKasBank $akun = null,
        ?EntitlementPolicyService $policy = null
    ) {
        $this->bankModel = $bank ?? new ModelBank();
        $this->akunModel = $akun ?? new ModelAkunKasBank();
        $this->policy    = $policy ?? new EntitlementPolicyService();
    }

    /**
     * Validasi rekening bank, dengan konteks unit bila tersedia.
     *
     * Parameter `$unitId` bersifat opsional supaya pemanggil lama yang belum
     * punya konteks unit tidak ikut gagal — TETAPI pemanggil yang menulis
     * transaksi untuk unit tertentu WAJIB mengisinya, lewat
     * validateUntukUnit().
     *
     * @param  string|null $idbank      idbank apa adanya (TIDAK di-int-cast)
     * @param  bool        $bolehKosong idbank kosong boleh (mis. transaksi KAS)
     * @param  int|null    $unitId      unit yang melakukan transaksi; null =
     *                                  konteks unit tidak diketahui
     * @param  int|null    $role        ID_JABATAN, untuk otorisasi Finance
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    public function validate(
        ?string $idbank,
        bool $bolehKosong = true,
        ?int $unitId = null,
        ?int $role = null
    ): array {
        $idbank = $idbank === null ? null : trim($idbank);

        if ($idbank === null || $idbank === '' || $idbank === '0') {
            if ($bolehKosong) {
                return $this->ok(null, null, null);
            }

            return $this->gagal('Rekening bank wajib dipilih.');
        }

        $bank = $this->bankModel->getById($idbank);
        if ($bank === null) {
            return $this->gagal('Rekening "' . $idbank . '" tidak terdaftar pada master bank.');
        }

        $akun = $this->akunModel->getAkunByBankIdbank($idbank);
        if ($akun === null) {
            return $this->gagal(
                'Rekening "' . $idbank . '" belum dipetakan ke akun kas/bank. '
                . 'Hubungi Finance untuk memetakannya.'
            );
        }

        if ((string) $akun->status !== 'aktif') {
            return $this->gagal('Rekening "' . $idbank . '" berstatus nonaktif dan tidak bisa dipakai.');
        }

        if ((string) $akun->tipe !== 'BANK') {
            return $this->gagal('Rekening "' . $idbank . '" bukan rekening bank.');
        }

        if ($unitId === null) {
            // Tidak ada konteks unit: validasi rekening dasar tetap berlaku,
            // tetapi pemanggil yang menulis transaksi unit WAJUB memakai
            // validateUntukUnit() supaya hak akses ikut diperiksa.
            return $this->ok($idbank, $akun, $bank);
        }

        return $this->validasiHakUnit($idbank, $akun, $bank, $unitId, $role);
    }

    /**
     * Validasi rekening untuk transaksi milik satu unit.
     *
     * Ini yang dipakai write path kas_masuk / kas_keluar. Selain checks rekening
     * ada & aktif, method ini memastikan unit tersebut benar-benar berhak
     * memakai rekening itu.
     *
     * Rekening Finance/HO diperlakukan specially: itu rekening kas Direksi,
     * BUKAN rekening operasional unit, jadi tidak punya entitlement unit.
     * Aksesnya datang dari role, bukan dari userId/unit mana pun.
     *
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    public function validateUntukUnit(
        ?string $idbank,
        int $unitId,
        ?int $role = null,
        bool $bolehKosong = true
    ): array {
        $hasil = $this->validate($idbank, $bolehKosong, $unitId, $role);

        return $hasil;
    }

    /**
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    private function validasiHakUnit(string $idbank, object $akun, ?object $bank, int $unitId, ?int $role): array
    {
        if ($unitId <= 0) {
            return $this->gagal('Unit tidak valid, transaksi tidak bisa dicatat ke rekening "' . $idbank . '".');
        }

        $akunId = (int) $akun->idakun_kas_bank;

        // Rekening kas Direksi: bukan rekening operasional unit. Tidak ada
        // entitlement unit yang relevan; otorisasinya dari ROLE.
        if ((int) ($akun->is_finance_ho ?? 0) === 1) {
            // Rekening Direksi tidak boleh punya alokasi unit sama sekali (M4).
            // Kalau ada, statusnya sudah tidak konsisten -- role sehebat apa pun
            // tidak boleh menutupi data yang rusak seperti ini.
            if ($this->policy->unitEntitledDb($akunId) !== []) {
                return $this->gagal(sprintf(
                    'Rekening kas Direksi "%s" tercatat dialokasikan ke unit tertentu sehingga tidak lagi '
                    . 'berstatus rekening Direksi. Hubungi Finance untuk memperbaikinya.',
                    $idbank
                ));
            }

            // PENTING: jangan pakai (int) session('ID_JABATAN') langsung.
            // Session kosong akan jadi 0, dan 0 adalah ROOT — jadi konteks tanpa
            // session diam-diam mendapat hak penuh. Di sini null berarti
            // "konteks role tidak diketahui", dan itu DITOLAK, bukan diizinkan.
            if ($role === null) {
                $sessionRole = session('ID_JABATAN');
                if ($sessionRole !== null && $sessionRole !== '') {
                    $role = (int) $sessionRole;
                }
            }

            $roleBoleh = $role !== null
                && in_array($role, array_map('intval', config(\Config\Finance::class)->financeHoSourceRoles ?? []), true);

            if ($roleBoleh) {
                return $this->ok($idbank, $akun, $bank);
            }

            return $this->gagal(sprintf(
                'Rekening kas Direksi "%s" hanya bisa dipakai oleh role Finance/Direksi. '
                . 'Role Anda belum berwenang — hubungi Finance.',
                $idbank
            ));
        }

        // Operational account: haknya per unit. Di sinilah guard fail-closed
        // dipasang: `adakahEntitlement()` membaca DB saja, jadi alokasi yang
        // tidak pernah disahkan policy akan lolos begitu saja.
        // Lihat EntitlementPolicyService::guardEntitlementSensif().
        $cek = $this->policy->guardEntitlementSensif($akunId, $unitId);

        if (! $cek['ok']) {
            return $this->gagal($cek['alasan']);
        }

        return $this->ok($idbank, $akun, $bank);
    }

    /**
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    private function ok(?string $idbank, ?object $akun, ?object $bank): array
    {
        return ['ok' => true, 'alasan' => '', 'idbank' => $idbank, 'akun' => $akun, 'bank' => $bank];
    }

    /**
     * @return array{ok:bool, alasan:string, idbank:?string, akun:?object, bank:?object}
     */
    private function gagal(string $alasan): array
    {
        return ['ok' => false, 'alasan' => $alasan, 'idbank' => null, 'akun' => null, 'bank' => null];
    }
}