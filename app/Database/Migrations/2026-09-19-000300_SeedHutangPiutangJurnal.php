<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Hutang Piutang — seed template jurnal.
 *
 * Mapping COA yang disetujui:
 *  - Piutang pelanggan (input)      : Dr Piutang Usaha (Pelanggan) 1020101000
 *                                     Cr Pendapatan Lain-lain      7019900000
 *  - Piutang pelanggan (bayar tunai): Dr Kas Besar                 1010101000
 *                                     Cr Piutang Usaha (Pelanggan) 1020101000
 *  - Piutang pelanggan (bayar bank) : Dr Kas di Bank               1010102000
 *                                     Cr Piutang Usaha (Pelanggan) 1020101000
 *  - Kasbon pegawai (input)         : Dr Piutang Pegawai           1020201000
 *                                     Cr Kas Besar                 1010101000
 *  - Kasbon pegawai (bayar tunai)   : Dr Kas Besar                 1010101000
 *                                     Cr Piutang Pegawai           1020201000
 *  - Kasbon pegawai (bayar bank)    : Dr Kas di Bank               1010102000
 *                                     Cr Piutang Pegawai           1020201000
 *  - Kasbon dipotong payroll        : Dr Utang Gaji dan Upah       2010201000
 *                                     Cr Piutang Pegawai           1020201000
 *
 * Idempotent: hanya menyisipkan template yang belum ada (per kode_template).
 */
class SeedHutangPiutangJurnal extends Migration
{
    /**
     * kode_template => [ [no_akun, nama_akun, debet_kredit], ... ]
     */
    private function templates(): array
    {
        return [
            'hp_piutang_pelanggan_input' => [
                ['1020101000', 'Piutang Usaha (Pelanggan)', 'debet'],
                ['7019900000', 'Pendapatan Lain-lain', 'kredit'],
            ],
            'hp_piutang_pelanggan_bayar_tunai' => [
                ['1010101000', 'Kas Besar (Cash on Hand)', 'debet'],
                ['1020101000', 'Piutang Usaha (Pelanggan)', 'kredit'],
            ],
            'hp_piutang_pelanggan_bayar_bank' => [
                ['1010102000', 'Kas di Bank (Cash in Bank)', 'debet'],
                ['1020101000', 'Piutang Usaha (Pelanggan)', 'kredit'],
            ],
            'hp_kasbon_input' => [
                ['1020201000', 'Piutang Pegawai', 'debet'],
                ['1010101000', 'Kas Besar (Cash on Hand)', 'kredit'],
            ],
            'hp_kasbon_bayar_tunai' => [
                ['1010101000', 'Kas Besar (Cash on Hand)', 'debet'],
                ['1020201000', 'Piutang Pegawai', 'kredit'],
            ],
            'hp_kasbon_bayar_bank' => [
                ['1010102000', 'Kas di Bank (Cash in Bank)', 'debet'],
                ['1020201000', 'Piutang Pegawai', 'kredit'],
            ],
            'hp_kasbon_potong_payroll' => [
                ['2010201000', 'Utang Gaji dan Upah', 'debet'],
                ['1020201000', 'Piutang Pegawai', 'kredit'],
            ],
        ];
    }

    public function up()
    {
        $table = $this->db->table('template_jurnal');

        foreach ($this->templates() as $kode => $rows) {
            $exists = $table->where('kode_template', $kode)->countAllResults(false) > 0;
            if ($exists) {
                continue;
            }
            foreach ($rows as $row) {
                $table->insert([
                    'kode_template' => $kode,
                    'no_akun'       => $row[0],
                    'nama_akun'     => $row[1],
                    'debet_kredit'  => $row[2],
                    'array_value'   => 0,
                    'keterangan'    => 'Hutang Piutang',
                ]);
            }
        }
    }

    public function down()
    {
        $this->db->table('template_jurnal')
            ->whereIn('kode_template', array_keys($this->templates()))
            ->delete();
    }
}
