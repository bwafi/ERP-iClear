<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed template jurnal pembelian yang seimbang.
 *
 * Sebelumnya template pembelian yang ada di data lokal memakai beberapa baris
 * dengan `array_value = 0` sekaligus, sehingga satu nilai yang sama dipakai
 * untuk debit dan kredit dan jurnal bisa tidak seimbang. Di sini setiap
 * template dibuat dua baris: sisi persediaan/PPN mengkredit Utang Usaha,
 * sedangkan jurnal pembayaran mendebit Utang Usaha dan mengkredit kas/bank.
 *
 * Template diganti (delete + insert) supaya database yang sudah punya template
 * lama yang tidak seimbang ikut diperbaiki. Baris `jurnal` historis tidak
 * disentuh.
 */
class SeedPembelianJurnal extends Migration
{
    private const AKUN_UTANG_USAHA = '2010100000';

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

                $templates['pembelian_lunas_bank_' . $idbank] = $this->templateBank();
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
                    'keterangan'    => 'Pembelian',
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
                    $kode[] = 'pembelian_lunas_bank_' . $idbank;
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
            // Pelunasan: Dr Utang Usaha, Cr Kas/Bank.
            'pembelian_lunas_tunai' => [
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'debet'],
                ['1010101000', 'Kas Besar (Cash on Hand)', 'kredit'],
            ],
            'pembelian_lunas_bank' => $this->templateBank(),

            // Persediaan & PPN: Dr persediaan/PPN, Cr Utang Usaha.
            'pembelian_ppn_lunas' => [
                ['1040600000', 'Pajak Dibayar di Muka (PPN)', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
            'pembelian_aksesoris' => [
                ['1030104000', 'Aksesoris Handphone', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
            'pembelian_sparepart' => [
                ['1030105000', 'Sparepart Service Handphone', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
            'pembelian_lunas_hp_baru_ppn' => [
                ['1030101000', 'Handphone Baru', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
            'pembelian_lunas_hp_baru' => [
                ['1030101000', 'Handphone Baru', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
            'pembelian_lunas_hp_second' => [
                ['1030102000', 'Handphone Second', 'debet'],
                [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'kredit'],
            ],
        ];
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function templateBank(): array
    {
        return [
            [self::AKUN_UTANG_USAHA, 'Utang Usaha', 'debet'],
            ['1010102000', 'Kas di Bank (Cash in Bank)', 'kredit'],
        ];
    }
}
