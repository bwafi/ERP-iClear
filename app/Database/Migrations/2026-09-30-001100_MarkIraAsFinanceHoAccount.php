<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tandai rekening IRA sebagai Finance/HO.
 *
 * KEPUTUSAN BISNIS (sudah disetujui user)
 * --------------------------------------
 * akun_kas_bank.id = 1 — "BCA Admin Center/Finance 0391943558 (IRA)"
 * adalah rekening FINANCE/HO:
 *
 *   - bukan milik unit manapun  -> unit_id tetap NULL
 *   - tetap berstatus shared   -> is_shared tetap 1
 *   - TIDAK membutuhkan alokasi_saldo_kas_bank
 *   - boleh jadi TUJUAN transfer dari unit mana pun yang transaksinya valid
 *   - boleh jadi SUMBER hanya oleh role di Config\Finance::$financeHoSourceRoles
 *     (0 = ADMIN CENTER, 1 = Admin root)
 *
 * BENTUK datanya sama persis dengan rekening shared antar-unit
 * (unit_id NULL + is_shared 1). Keduanya hanya bisa dibedakan lewat flag
 * eksplisit `is_finance_ho` yang sudah dibuat di migration sebelumnya
 * (2026-09-30-001000). Migration ini HANYA mengisi flag itu — tidak membuat
 * kolom baru.
 *
 * AKUN 4 ("Bank BCA 3251427508") SENGAJA TIDAK DISENTUH. Ia tetap
 * is_finance_ho = 0 dan tetap require unit allocation.
 *
 * Yang SENGAJA tidak dilakukan di migration ini:
 *   - tidak insert alokasi_saldo_kas_bank
 *   - tidak backfill transaksi
 *   - tidak mengubah saldo awal
 *   - tidak mengubah mapping bank / idbank
 *   - tidak mengubah cutoff
 *   - tidak mengubah unit_id atau is_shared milik akun mana pun
 */
class MarkIraAsFinanceHoAccount extends Migration
{
    /** Rekening yang disepakati menjadi Finance/HO. */
    private const IRA_ID = 1;

    public function up()
    {
        if (! $this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            // Kolom belum ada -> biarkan migration 001000 yang menyiapkan.
            return;
        }

        $row = $this->db->query(
            'SELECT idakun_kas_bank, nama_akun, unit_id, is_shared, is_finance_ho
               FROM akun_kas_bank WHERE idakun_kas_bank = ' . self::IRA_ID
        )->getRow();

        if ($row === null) {
            return;
        }

        // Idempotent: sudah ditandai Finance/HO -> tidak ada yang perlu diubah.
        if ((int) $row->is_finance_ho === 1) {
            return;
        }

        // Penjaga: hanya tandai kalau bentuknya masih persis rekening HO
        // (unit NULL + shared). Kalau suatu saat akun ini dipakai ulang jadi
        // rekening unit, migration ini tidak boleh menandainya diam-diam.
        if ($row->unit_id !== null || (int) $row->is_shared !== 1) {
            return;
        }

        $this->db->query(
            'UPDATE akun_kas_bank SET is_finance_ho = 1
              WHERE idakun_kas_bank = ' . self::IRA_ID . ' AND is_finance_ho = 0'
        );
    }

    public function down()
    {
        if (! $this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            return;
        }

        $this->db->query(
            'UPDATE akun_kas_bank SET is_finance_ho = 0 WHERE idakun_kas_bank = ' . self::IRA_ID
        );
    }
}
