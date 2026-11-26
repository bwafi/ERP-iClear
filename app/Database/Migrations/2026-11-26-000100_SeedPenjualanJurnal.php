<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed template jurnal penjualan yang dipakai Penjualan::insert_penjualan().
 *
 * Controller memecah jurnal penjualan menjadi beberapa grup referensi:
 *  - pembayaran_bank : debit kas/bank saja (`penjualan_lunas_tunai`,
 *    `penjualan_lunas_bank_<idbank>`);
 *  - penjualan       : kredit pendapatan per kategori, kredit PPN, dan debit
 *    diskon (`penjualan_hp_baru_cash`, `penjualan_hp_baru_ppn`,
 *    `penjualan_hp_second_cash`, `penjualan_acc_cash`,
 *    `penjualan_bayar_ppn`, `penjualan_diskon`).
 *
 * Karena pemecahan itu, setiap template sengaja hanya berisi SATU sisi.
 * Keseimbangan dijaga oleh kombinasi seluruh grup, bukan per template.
 * Sebelum migration ini kode template tersebut tidak ada sehingga
 * ModelJurnal::insertJurnal() hanya log error dan penjualan commit tanpa
 * jurnal.
 *
 * Template diganti (delete + insert) agar database yang sudah punya baris
 * template lama/berbeda ikut diperbaiki. Baris `jurnal` historis tidak
 * disentuh.
 */
class SeedPenjualanJurnal extends Migration
{
    private const AKUN_KAS_BESAR = '1010101000';
    private const AKUN_KAS_BANK = '1010102000';
    private const AKUN_PPN_KELUARAN = '2010401000';
    private const AKUN_PENJUALAN_HP_PPN = '4010101000';
    private const AKUN_PENJUALAN_HP_NON_PPN = '4010102000';
    private const AKUN_PENJUALAN_HP_SECOND = '4010103000';
    private const AKUN_PENJUALAN_AKSESORIS = '4010104000';
    private const AKUN_DISKON_PENJUALAN = '4010108000';

    public function up()
    {
        if (! $this->db->tableExists('template_jurnal')) {
            return;
        }

        $templates = $this->templates();

        if ($this->db->tableExists('bank')) {
            foreach ($this->db->table('bank')->select('idbank')->get()->getResult() as $bank) {
                $idbank = trim((string) ($bank->idbank ?? ''));
                if ($idbank === '') {
                    continue;
                }

                $templates['penjualan_lunas_bank_' . $idbank] = $this->templateBank();
            }
        }

        $table = $this->db->table('template_jurnal');

        foreach ($templates as $kode => $rows) {
            $table->where('kode_template', $kode)->delete();

            foreach ($rows as $row) {
                $table->insert([
                    'kode_template' => $kode,
                    'no_akun'       => $row[0],
                    'nama_akun'     => $row[1],
                    'debet_kredit'  => $row[2],
                    'array_value'   => 0,
                    'keterangan'    => 'Penjualan',
                ]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('template_jurnal')) {
            return;
        }

        $kode = array_keys($this->templates());

        if ($this->db->tableExists('bank')) {
            foreach ($this->db->table('bank')->select('idbank')->get()->getResult() as $bank) {
                $idbank = trim((string) ($bank->idbank ?? ''));
                if ($idbank !== '') {
                    $kode[] = 'penjualan_lunas_bank_' . $idbank;
                }
            }
        }

        $this->db->table('template_jurnal')
            ->whereIn('kode_template', $kode)
            ->delete();
    }

    /**
     * @return array<string, array<int, array{0:string,1:string,2:string}>>
     */
    private function templates(): array
    {
        return [
            // Pembayaran: hanya sisi debit kas/bank. Sisi kredit ada di
            // template pendapatan/PPN pada grup referensi penjualan.
            'penjualan_lunas_tunai' => [
                [self::AKUN_KAS_BESAR, 'Kas Besar (Cash on Hand)', 'debet'],
            ],
            'penjualan_lunas_bank' => $this->templateBank(),

            // Pendapatan per kategori: hanya sisi kredit.
            'penjualan_hp_baru_ppn' => [
                [self::AKUN_PENJUALAN_HP_PPN, 'Penjualan Handphone Baru (PPN)', 'kredit'],
            ],
            'penjualan_hp_baru_cash' => [
                [self::AKUN_PENJUALAN_HP_NON_PPN, 'Pendapatan Penjualan Handphone Baru (Non PPN)', 'kredit'],
            ],
            'penjualan_hp_second_cash' => [
                [self::AKUN_PENJUALAN_HP_SECOND, 'Penjualan Handphone Second', 'kredit'],
            ],
            'penjualan_acc_cash' => [
                [self::AKUN_PENJUALAN_AKSESORIS, 'Penjualan Aksesoris', 'kredit'],
            ],

            // PPN keluaran: hanya sisi kredit.
            'penjualan_bayar_ppn' => [
                [self::AKUN_PPN_KELUARAN, 'Utang PPN Keluaran (Pajak yang harus disetor)', 'kredit'],
            ],

            // Diskon penjualan: contra-revenue, hanya sisi debit.
            'penjualan_diskon' => [
                [self::AKUN_DISKON_PENJUALAN, 'Diskon Penjualan', 'debet'],
            ],
        ];
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function templateBank(): array
    {
        return [
            [self::AKUN_KAS_BANK, 'Kas di Bank (Cash in Bank)', 'debet'],
        ];
    }
}
