<?php

namespace App\Services\Pembelian;

/**
 * Normalisasi pembayaran pembelian Lunas: tunai, transfer, atau keduanya.
 * Service ini tidak menyentuh database; validasi rekening tetap di controller
 * lewat BankRekeningValidator.
 */
final class PembelianPaymentSplit
{
    private const METODE = ['tunai', 'transfer', 'tunai_transfer'];

    public function normalize(array $post): array
    {
        $metode = strtolower(trim((string) ($post['metode_bayar'] ?? '')));

        if (! in_array($metode, self::METODE, true)) {
            return $this->gagal('Metode pembayaran Lunas wajib dipilih: Tunai, Transfer, atau Tunai + Transfer.');
        }

        $pakaiTunai = $metode !== 'transfer';
        $pakaiBank = $metode !== 'tunai';
        $errors = [];

        $cash = 0;
        if ($pakaiTunai) {
            $cash = PembelianCalculator::parseRupiah($post['bayar'] ?? '0');
            if ($cash === null) {
                $errors[] = 'Nominal pembayaran tunai tidak valid.';
                $cash = 0;
            }
        }

        $banks = [];
        $terpakai = [];
        if ($pakaiBank) {
            $rows = $post['bank'] ?? [];
            if (! is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $jumlah = PembelianCalculator::parseRupiah($row['jumlah'] ?? '0');
                if ($jumlah === null) {
                    $errors[] = 'Nominal pembayaran bank tidak valid.';
                    continue;
                }
                if ($jumlah <= 0) {
                    continue;
                }

                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '' || $id === '0') {
                    $errors[] = 'Rekening bank wajib dipilih untuk nominal transfer.';
                    continue;
                }
                if (in_array($id, $terpakai, true)) {
                    $errors[] = 'Rekening bank tidak boleh dipilih lebih dari sekali.';
                    continue;
                }

                $terpakai[] = $id;
                $banks[] = ['id' => $id, 'jumlah' => $jumlah];
            }
        }

        $total = $cash;
        foreach ($banks as $bank) {
            $total += $bank['jumlah'];
        }

        if ($total <= 0 && $errors === []) {
            $errors[] = $metode === 'transfer'
                ? 'Pembayaran Lunas via Transfer wajib memilih minimal satu rekening bank.'
                : 'Pembayaran Lunas wajib memiliki minimal satu komponen tunai atau transfer.';
        }

        return [
            'cash' => $cash,
            'banks' => $banks,
            'total' => $total,
            'errors' => $errors,
        ];
    }

    private function gagal(string $pesan): array
    {
        return ['cash' => 0, 'banks' => [], 'total' => 0, 'errors' => [$pesan]];
    }
}
