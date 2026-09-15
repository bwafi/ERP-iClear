<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pindahkan id unit HO dari 5 ke 50.
 *
 * Alasan: id 5 akan dipakai cabang baru di kemudian hari, jadi HO memakai
 * id unik 50. HO berlokasi di Probolinggo (kota sama dengan unit 1).
 *
 * Termasuk:
 *  - re-tag seluruh referensi data lama unit 5 -> 50.
 *  - pastikan jabatan pusat (Manager, Admin Center, SPV, Multimedia,
 *    Kepala Divisi, IT, CS) berada di unit HO (50).
 *
 * Tunjangan Penempatan tetap otomatis: karyawan HO dengan alamat di luar
 * Probolinggo -> penempatan=0 -> dapat tunjangan 350rb.
 */
class RenumberHOUnitTo50 extends Migration
{
    private const HQ_JABATAN = [34, 0, 40, 44, 43, 45, 42];

    public function up()
    {
        // FK checks dimatikan sementara: update data dulu, konsistensi final
        // dijamin karena seluruh referensi 5 dipindah & id unit 5 dihapus.
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        $children = [
            'kpi_targets'               => 'unit_id',
            'presensi'                  => 'unit_idunit',
            'akun'                      => 'ID_UNIT',
            'content_units'             => 'unit_id',
            'publications'              => 'unit_id',
            'incentive_members'         => 'unit_id',
            'retur_pelanggan'           => 'unit_idunit',
            'retur_suplier'             => 'unit_idunit',
            'service_sparepart'         => 'unit_idunit',
            'detail_penjualan'          => 'unit_idunit',
        ];

        foreach ($children as $table => $column) {
            $this->db->query(
                "UPDATE `{$table}` SET `{$column}` = 50 WHERE `{$column}` = 5"
            );
        }

        // Pindahkan jabatan pusat ke HO (50).
        $this->db->query(
            "UPDATE akun SET ID_UNIT = 50
             WHERE ID_JABATAN IN (" . implode(',', self::HQ_JABATAN) . ")
               AND STATUS_PEGAWAI = 1"
        );

        // Ubah id unit HO (sekarang tidak ada referensi lain ke 5).
        $this->db->query("UPDATE unit SET idunit = 50 WHERE idunit = 5");

        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        // Hati-hati: hanya balik bila id 5 masih tersedia.
        $this->db->query(
            "UPDATE unit SET idunit = 5 WHERE idunit = 50
             AND NOT EXISTS (SELECT 1 FROM unit WHERE idunit = 5)"
        );

        foreach ([
            'presensi'                 => 'unit_idunit',
            'retur_pelanggan'          => 'unit_idunit',
            'retur_suplier'            => 'unit_idunit',
            'service_sparepart'        => 'unit_idunit',
            'detail_penjualan'         => 'unit_idunit',
            'content_units'            => 'unit_id',
            'publications'             => 'unit_id',
            'incentive_members'        => 'unit_id',
            'kpi_targets'              => 'unit_id',
            'akun'                     => 'ID_UNIT',
        ] as $table => $col) {
            $this->db->query("UPDATE `{$table}` SET `{$col}` = 5 WHERE `{$col}` = 50");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }
}