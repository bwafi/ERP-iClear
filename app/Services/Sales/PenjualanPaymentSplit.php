<?php

namespace App\Services\Sales;

/**
 * Normalisasi pembayaran penjualan (tunai, transfer, atau keduanya).
 *
 * `metode_bayar` adalah sumber kebenaran zona pembayaran:
 *  - `tunai`          hanya memakai nominal tunai, baris bank diabaikan;
 *  - `transfer`       hanya memakai baris bank, nominal tunai diabaikan;
 *  - `tunai_transfer` memakai keduanya;
 *  - kosong/nilai lain memakai perilaku lama: jumlahkan yang ada.
 *
 * Service ini tidak menyentuh database. Daftar rekening yang boleh dipakai
 * dikirim controller dari `ModelBank::getBankDalamScopeUnit()`, sehingga baris
 * bank di luar scope unit ditolak sebelum menyentuh pembayaran_bank/jurnal.
 */
class PenjualanPaymentSplit
{
    /**
     * @param array $post           Payload request (metode_bayar, bayar, bank).
     * @param array $allowedBankIds ID rekening yang boleh dipakai unit ini.
     *
     * @return array{cash:float, banks:array<int,array{id:string,jumlah:float}>, total:float, errors:array<int,string>}
     */
    public function normalize(array $post, array $allowedBankIds): array
    {
        $metode = strtolower(trim((string) ($post['metode_bayar'] ?? '')));
        $pakaiTunai = $metode !== 'transfer';
        $pakaiBank = $metode !== 'tunai';

        $errors = [];
        $cash = 0.0;
        $banks = [];

        if ($pakaiTunai) {
            [$cash, $negatif] = $this->parseMoney($post['bayar'] ?? '0');
            if ($negatif) {
                $cash = 0.0;
                $errors[] = 'Nominal pembayaran tunai tidak boleh negatif.';
            }
        }

        if ($pakaiBank) {
            $allowed = [];
            foreach ($allowedBankIds as $id) {
                $allowed[] = trim((string) $id);
            }

            $rows = $post['bank'] ?? [];
            if (! is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                [$jumlah, $negatif] = $this->parseMoney($row['jumlah'] ?? '0');

                if ($negatif) {
                    $errors[] = 'Nominal pembayaran bank tidak boleh negatif.';
                    continue;
                }

                if ($jumlah <= 0) {
                    continue;
                }

                $id = trim((string) ($row['id'] ?? ''));

                if ($id === '') {
                    $errors[] = 'Rekening bank wajib dipilih untuk nominal transfer.';
                    continue;
                }

                if (! in_array($id, $allowed, true)) {
                    $errors[] = 'Rekening bank tidak termasuk rekening unit ini.';
                    continue;
                }

                $banks[] = [
                    'id' => $id,
                    'jumlah' => $jumlah,
                ];
            }
        }

        $total = $cash;
        foreach ($banks as $bank) {
            $total += $bank['jumlah'];
        }

        return [
            'cash' => $cash,
            'banks' => $banks,
            'total' => $total,
            'errors' => $errors,
        ];
    }

    /**
     * Ubah nominal ketikan kasir menjadi float rupiah.
     *
     * Mendukung "Rp 150.000", "150.000", "150000", "150000.00", dan
     * "1.234.567,89". String yang mengandung tanda minus selalu dilaporkan
     * negatif; nilainya dikembalikan 0 agar tidak pernah ikut dijumlahkan.
     *
     * @param mixed $raw
     *
     * @return array{0:float, 1:bool} [nilai, apakah_negatif]
     */
    private function parseMoney($raw): array
    {
        if (is_int($raw) || is_float($raw)) {
            $value = (float) $raw;

            return $value < 0 ? [0.0, true] : [round($value, 2), false];
        }

        $raw = trim((string) $raw);
        $negatif = strpos($raw, '-') !== false;

        $clean = preg_replace('/[^0-9,.]/', '', $raw);

        if ($clean === null || $clean === '') {
            return [0.0, false];
        }

        $adaKoma = strpos($clean, ',') !== false;
        $adaTitik = strpos($clean, '.') !== false;

        if ($adaKoma && $adaTitik) {
            // 1.234.567,89 -> 1234567.89
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif ($adaKoma) {
            $clean = str_replace(',', '.', $clean);
        } elseif ($adaTitik && preg_match('/^\d{1,3}(\.\d{3})+$/', $clean) === 1) {
            // 150.000 dan 1.234.567 adalah pemisah ribuan, bukan desimal.
            $clean = str_replace('.', '', $clean);
        }

        $value = (float) $clean;

        if ($negatif) {
            return [0.0, $value > 0];
        }

        return [round($value, 2), false];
    }
}
