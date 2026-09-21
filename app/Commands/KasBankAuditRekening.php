<?php

namespace App\Commands;

use App\Models\ModelTransaksiKasBank;
use Config\Database;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Audit rekening Kas & Bank dalam konsep REKENING FISIK != UNIT.
 *
 *   php spark kasbank:audit-rekening
 *
 * Menampilkan untuk tiap akun (rekening fisik): saldo awal, masuk/keluar,
 * saldo fisik, alokasi per unit, rekening bersama. Mendeteksi:
 * - bank_idbank yang dipakai >1 akun BANK aktif (duplicate/shared candidate,
 *   TIDAK di-merge otomatis — butuh konfirmasi user),
 * - saldo fisik negatif (kasus BCA Jember),
 * - total alokasi unit melebihi saldo fisik.
 */
class KasBankAuditRekening extends BaseCommand
{
    protected $group       = 'KasBank';
    protected $name        = 'kasbank:audit-rekening';
    protected $description = 'Audit rekening Kas & Bank: saldo fisik per akun, rekening bersama/duplikat, saldo negatif, alokasi berlebih.';
    protected $usage       = 'kasbank:audit-rekening';

    public function run(array $params)
    {
        $db = Database::connect();
        $trans = new ModelTransaksiKasBank();

        $akun = $db->query(
            'SELECT a.*, u.NAMA_UNIT, b.nama_bank, b.norek, b.atas_nama
             FROM akun_kas_bank a
             LEFT JOIN unit u ON u.idunit = a.unit_id
             LEFT JOIN bank b ON b.idbank = a.bank_idbank
             WHERE a.tipe = \'BANK\'
             ORDER BY a.bank_idbank ASC, a.unit_id ASC'
        )->getResult();

        CLI::write('AUDIT REKENING KAS & BANK (FISIK != UNIT)', 'yellow');
        CLI::write('');

        $mapBankId = [];
        $negatif   = [];
        $alokasiLb = [];

        foreach ($akun as $a) {
            $aid   = (int) $a->idakun_kas_bank;
            $fisik = $trans->getSaldoFisikAkun($aid);
            $alokasi = $trans->getTotalAlokasiUnit($aid);

            $mapBankId[(string) $a->bank_idbank][] = $aid;

            if ($fisik < 0) {
                $negatif[] = $a;
            }
            if ($alokasi > $fisik) {
                $alokasiLb[] = $a;
            }

            $flag = [];
            if ((int) $a->is_shared === 1 || empty($a->unit_id)) {
                $flag[] = 'BERSAMA';
            }
            if ($fisik < 0) {
                $flag[] = 'NEGATIF';
            }
            if ($alokasi > $fisik) {
                $flag[] = 'ALOKASI > FISIK';
            }

            $label = sprintf(
                '#%d %-28s bank=%s (%-10s %s)',
                $aid,
                mb_substr((string) $a->nama_akun, 0, 28),
                (string) $a->bank_idbank,
                (string) ($a->norek ?? '-'),
                (string) ($a->atas_nama ?? '')
            );
            CLI::write($label, $fisik < 0 ? 'red' : 'light_gray');
            CLI::write(sprintf('      fisik=%s  awal=%s  masuk=%s  keluar=%s  unit=%s%s',
                number_format($fisik),
                number_format($this->saldoAwal($aid)),
                number_format($this->rimbun($aid, 'MASUK')),
                number_format($this->rimbun($aid, 'KELUAR')),
                $a->unit_id ? ($a->NAMA_UNIT ?: ('Unit ' . $a->unit_id)) : 'Fisik lintas unit',
                $flag ? '  [' . implode(' | ', $flag) . ']' : ''
            ), $fisik < 0 ? 'red' : 'light_gray');
        }

        CLI::write('');
        CLI::write('KANDIDAT REKENING DIPAKAI >1 AKUN (TIDAK DI-MERGE OTOMATIS)', 'yellow');
        $found = false;
        foreach ($mapBankId as $bankId => $ids) {
            if (count($ids) > 1) {
                $found = true;
                CLI::write('  bank ' . $bankId . ' -> akun ' . implode(', ', $ids), 'light_red');
            }
        }
        if (!$found) {
            CLI::write('  Tidak ada bank_idbank yang dipakai lebih dari satu akun.', 'green');
        }

        CLI::write('');
        if ($negatif) {
            CLI::write(sprintf('SALDO FISIK NEGATIF: %d rekening', count($negatif)), 'red');
            foreach ($negatif as $a) {
                CLI::write(sprintf('  #%d %s (bank %s) fisik %s',
                    (int) $a->idakun_kas_bank,
                    $a->nama_akun,
                    $a->bank_idbank,
                    number_format($trans->getSaldoFisikAkun((int) $a->idakun_kas_bank))
                ), 'red');
            }
        } else {
            CLI::write('Saldo fisik negatif: tidak ada.', 'green');
        }

        CLI::write('');
        if ($alokasiLb) {
            CLI::write(sprintf('ALOKASI MELEBIHI SALDO FISIK: %d rekening', count($alokasiLb)), 'red');
            foreach ($alokasiLb as $a) {
                CLI::write(sprintf('  #%d %s | alokasi %s > fisik %s',
                    (int) $a->idakun_kas_bank,
                    $a->nama_akun,
                    number_format($trans->getTotalAlokasiUnit((int) $a->idakun_kas_bank)),
                    number_format($trans->getSaldoFisikAkun((int) $a->idakun_kas_bank))
                ), 'red');
            }
        } else {
            CLI::write('Alokasi melebihi saldo fisik: tidak ada.', 'green');
        }

        CLI::write('');
        CLI::write('Selesai.', 'yellow');
        return EXIT_SUCCESS;
    }

    private function saldoAwal(int $akunId): int
    {
        $row = Database::connect()->table('saldo_awal_kas_bank')
            ->select('COALESCE(SUM(saldo), 0) AS total')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    private function rimbun(int $akunId, string $arah): int
    {
        $row = (new ModelTransaksiKasBank())
            ->select('COALESCE(SUM(jumlah), 0) AS total')
            ->where('akun_kas_bank_id', $akunId)
            ->where('arah', $arah)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }
}