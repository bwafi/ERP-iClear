<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pastikan Unit "ICLEAR Genteng" tersedia untuk Social Media KPI.
 *
 * Hanya membuat jika belum ada (cek berdasarkan NAMA_UNIT / kode_unit GNT),
 * jadi idempoten dan tidak pernah membuat duplicate.
 *
 * Menggunakan tabel unit existing; TIDAK membuat tabel baru.
 */
class EnsureUnitGenteng extends Migration
{
    public function up()
    {
        $existing = $this->db->table('unit')
            ->groupStart()
            ->like('NAMA_UNIT', 'Genteng', 'both')
            ->orWhere('kode_unit', 'GNT')
            ->groupEnd()
            ->get()
            ->getRow();

        if ($existing) {
            return;
        }

        // id 5 bebas (HO sudah pindah ke 50); pakai bila kosong, jika tidak auto.
        $data = [
            'idunit'        => 5,
            'NAMA_UNIT'     => 'ICLEAR Genteng',
            'kode_unit'     => 'GNT',
            'NOID_UNIT'     => '05',
            'NOTELP'        => '085183270910',
            'JALAN_UNIT'    => 'Genteng, Banyuwangi',
            'KELURAHAN_UNIT'=> 'iclear.genteng',
            'KABUPATEN_UNIT'=> 'Banyuwangi',
            'jenis'         => 'franchise',
            'RADIUS'        => '200',
        ];

        if ($this->db->table('unit')->where('idunit', 5)->countAllResults() === 0) {
            $this->db->table('unit')->insert($data);
        } else {
            unset($data['idunit']);
            $this->db->table('unit')->insert($data);
        }
    }

    public function down()
    {
        // Tidak menghapus Unit di rollback: data bisnis unit tidak boleh
        // hilang otomatis. Administrator dapat mengelola via modul Unit.
    }
}