<?php

namespace App\Database\Migrations;

use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * FASE 1B / M4 — Kunci rekening Finance/HO: tidak boleh punya alokasi unit.
 *
 * KENAPA
 * ------
 * Rekening IRA / Finance (idbank=3, akaun 1) adalah rekening kas Direksi.
 * Ia BUKAN rekening operasional salah satu unit, dan bukan rekening milik
 * Head Office (unit 50) — Head Office tidak punya rekening operasional
 * sendiri sama sekali.
 *
 * Model entitlement berbasis unit TIDAK bisa menjelaskan rekening ini. Kalau
 * dipaksa Give Unit 50 "pemiliknya", konsekuensinya user unit 50 otomatis
 * mendapat akses ke seluruh kas Direksi hanya karena is_finance_ho = 1.
 * Itulah bypass yang harus dihapus; otorisasinya pindah ke ROLE.
 *
 * Yang dikunci di sini hanya ATURAN DATA: akun Finance/HO tidak boleh punya
 * satu pun baris di alokasi_saldo_kas_bank. Kalau suatu saat ada, itu berarti
 * ada proses yang salah menulis entitlement, dan migration ini berhenti dengan
 * exception alih-alih menghapusnya sendiri.
 *
 * Yang SENGAJA TIDAK disentuh
 * --------------------------
 *   - saldo_awal_kas_bank. Rekening Finance dikecualikan dari mekanisme
 *     statement: tidak butuh saldo real cutoff dan tidak akan diisi angka 0
 *     oleh migration ini.
 *   - transaksi historis. Termasuk 2 baris kas_keluar Unit 2 Jember
 *     (Rp565.000) yang memakai rekening ini — dilaporkan sebagai audit
 *     finding, tidak diubah dan tidak dihapus.
 *   - flag is_finance_ho. Nilainya sudah benar; migration ini tidak menyentuhnya.
 *
 * PERBAIKAN SCOPE NYATA ADA DI KODE
 * ----------------------------------
 * Migration ini hanya insurance terhadap data. Otorisasi berbasis ROLE
 * diimplementasikan di KasBankScopeService / ModelAkunKasBank, bukan di sini.
 *
 * IDEMPOTEN & FAIL-LOUD
 * ---------------------
 *   - Dijalankan dua kali -> hasil identik, tidak menyentuh apa pun bila
 *     sudah bersih.
 *   - Berhenti dengan exception bila akun 1 hilang / bukan Finance/HO, atau
 *     bila TERNYATA masih ada alokasi unit (tidak dihapus diam-diam).
 *   - DML dibungkus transaction.
 */
class KunciEntitlementFinance extends Migration
{

    /** Pengaman: akun ini harus masih menunjuk rekening bank IRA. */
    private const IDBANK = '3';
    private const NAMA = 'Rekening Finance/HO';

    /**
     * Nomor rekening, di-resolve dari bank_idbank -- bukan dari konstanta.
     *
     * Kenapa tidak hardcode: idakun_kas_bank berasal dari AUTO_INCREMENT, dan
     * migration 2026-09-21-000200 membuat rekening bank tanpa id eksplisit.
     * Nomor urutnya berbeda antar-linse data: dump lama punya CV=16/SABRINA=15,
     * dump produksi lain bisa CV=12/SABRINA=11. Hardcode pecah begitu dump
     * diganti. bank_idbank stabil = FK ke master `bank`, nilainya sudah
     * disahkan Finance, jadi itu yang jadi kunci.
     */
    private ?int $akunCache = null;

    private function akun(): int
    {
        if ($this->akunCache !== null) {
            return $this->akunCache;
        }

        $rows = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank, bank_idbank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', self::IDBANK)
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: tidak ada akun_kas_bank dengan bank_idbank=%s (%s). '
                . 'Rekening ini tidak bisa dibetulkan dan migration ini tidak menebak akun mana yang dimaksud. '
                . 'Pastikan master `bank` punya baris idbank=%s dan rekeningnya sudah dibuat migration sebelumnya.',
                self::IDBANK,
                self::NAMA,
                self::IDBANK
            ));
        }

        if (count($rows) > 1) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: ada %d rekening kas-bank dengan bank_idbank=%s (%s): %s. '
                . 'Peta rekening jadi ambigu dan migration ini tidak memilih salah satu.',
                count($rows),
                self::IDBANK,
                self::NAMA,
                implode(', ', array_column($rows, 'idakun_kas_bank'))
            ));
        }

        return $this->akunCache = (int) $rows[0]['idakun_kas_bank'];
    }


    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('alokasi_saldo_kas_bank')) {
            log_message('info', '[Migration KunciEntitlementFinance] tabel belum ada — dilewati.');

            return;
        }

        $this->pastikanAkunSesuai();

        $this->db->transBegin();

        try {
            $policy = new EntitlementPolicyService();

            // Migration ini tidak membuat alokasi. Kalau policy lokal ternyata
            // punya entri untuk akun Finance, itu bug konfigurasi — stop.
            $this->assertPolicyTidakMemberiEntitlement($policy);

            $this->tolakAlokasiYangAda();
            $this->pastikanTetapFinance();

            $this->assertTepat($policy);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        $this->db->transCommit();

        log_message('info', sprintf(
            '[Migration KunciEntitlementFinance] akun %d terkunci: tanpa alokasi unit, tanpa statement wajib.',
            $this->akun()
        ));
    }

    public function down()
    {
        // Tidak ada perubahan yang aman untuk dibatalkan: kembalikan alokasi
        // berarti mengembalikan akses kas Direksi ke unit yang tidak berhak.
    }

    // -----------------------------------------------------------------
    // Langkah-langkah
    // -----------------------------------------------------------------

    private function pastikanAkunSesuai(): void
    {
        $row = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank, nama_akun, bank_idbank, is_finance_ho')
            ->where('idakun_kas_bank', $this->akun())
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException(
                'Migration dihentikan: akun_kas_bank ' . $this->akun() . ' (bank_idbank=' . self::IDBANK . ') tidak ditemukan. '
                . 'Penguncian tidak bisa diverifikasi dan migration ini tidak menebak akun mana yang dimaksud.'
            );
        }

        if ((string) ($row['bank_idbank'] ?? '') !== self::IDBANK) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: akun %d kini menunjuk idbank=%s, bukan %s (rekening Finance/HO). '
                . 'Master rekening berubah — TIDAK diperbaiki otomatis.',
                $this->akun(),
                var_export($row['bank_idbank'] ?? null, true),
                self::IDBANK
            ));
        }

        if ((int) ($row['is_finance_ho'] ?? 0) !== 1) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: akun %d tidak lagi ditandai Finance/HO (is_finance_ho=%d). '
                . 'Penguncian alokasi hanya berlaku untuk rekening Finance/HO.',
                $this->akun(),
                (int) ($row['is_finance_ho'] ?? 0)
            ));
        }
    }

    /** Rekening Finance tidak boleh masuk daftar yang wajib statement. */
    private function assertPolicyTidakMemberiEntitlement(EntitlementPolicyService $policy): void
    {
        if ($policy->unitResmi($this->akun()) !== []) {
            throw new RuntimeException(sprintf(
                'Config\Finance::$rekeningResmiByBank memberi unit [%s] ke bank Finance/HO %d. '
                . 'Rekening kas Direksi bukan rekening operasional unit. Perbaiki config, bukan database.',
                implode(',', $policy->unitResmi($this->akun())),
                $this->akun()
            ));
        }

        if ($policy->wajibStatementVerifikasi($this->akun())) {
            throw new RuntimeException(sprintf(
                'Config\Finance menandai akun Finance/HO %d sebagai wajib statement. '
                . 'Rekening Finance tidak tunduk pada mekanisme saldo real operasional.',
                $this->akun()
            ));
        }
    }

    /**
     * Kalau ada alokasi, migration BERHENTI — tidak menghapus.
     *
     * Menghapus diam-diam akan menutupi bug di proses lain yang menulis
     * entitlement untuk rekening Finance. Itu harus terlihat.
     */
    private function tolakAlokasiYangAda(): void
    {
        $ada = array_map('intval', array_column($this->unitAdaDb(), 'unit_id'));

        if ($ada === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Migration dihentikan: akun Finance/HO %d punya alokasi untuk unit [%s]. '
            . 'Rekening kas Direksi tidak boleh punya entitlement unit — terutama tidak untuk unit 50 '
            . '(Head Office tidak punya rekening operasional). Alokasi TIDAK dihapus otomatis; '
            . 'tentukan asal-usulnya, hapus sebagai keputusan bisnis, lalu jalankan ulang.',
            $this->akun(),
            implode(',', $ada)
        ));
    }

    /**
     * Rekening Finance: lintas unit (unit_id NULL) dan tidak ikut alokasi.
     * Bentuknya dibetulkan hanya bila masih menyimpang.
     */
    private function pastikanTetapFinance(): void
    {
        $this->db->table('akun_kas_bank')
            ->where('idakun_kas_bank', $this->akun())
            ->where('is_finance_ho', 1)
            ->update([
                'unit_id'    => null,
                'is_shared'  => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function assertTepat(EntitlementPolicyService $policy): void
    {
        $entitled = $policy->unitEntitledDb($this->akun());

        if ($entitled !== []) {
            throw new RuntimeException(sprintf(
                'Migration GAGAL: akun Finance/HO %d masih punya alokasi unit [%s].',
                $this->akun(),
                implode(',', $entitled)
            ));
        }

        if (! $policy->selarasDenganPolicy($this->akun())) {
            throw new RuntimeException(
                'Migration GAGAL: data rekening Finance belum selaras dengan policy. '
                . 'Detail: ' . implode('; ', $policy->ringkasanDrift($this->akun()))
            );
        }
    }

    /** @return list<array{unit_id:int|string}> */
    private function unitAdaDb(): array
    {
        return $this->db->table('alokasi_saldo_kas_bank')
            ->select('unit_id')
            ->where('akun_kas_bank_id', $this->akun())
            ->get()
            ->getResultArray();
    }
}