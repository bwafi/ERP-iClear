<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kelompokkan menu absensi desktop di bawah satu parent "Absensi" pada blok
 * KEPEGAWAIAN. Halaman mobile-first (`/absen`, `/pengajuan_izin`) tidak punya
 * baris menu dan tidak ditambahkan di sini.
 *
 * Idempoten: baris grup dibuat sekali, child hanya diarahkan ulang.
 */
class GroupAbsensiMenu extends Migration
{
    private const GROUP_ID = 10161;

    /** idmenu => urutan tampil di dalam grup. */
    private const CHILDREN = [
        112 => 101, // Presensi (absensi)
        116 => 102, // Approval Presensi
        115 => 103, // Riwayat Presensi
        117 => 104, // Riwayat Presensi(admin)
        118 => 105, // Jadwal Masuk
    ];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $grup = $this->db->table('menu')->where('idmenu', self::GROUP_ID)->get()->getRow();
        if ($grup && $grup->nama_menu !== 'Absensi') {
            // Jangan menimpa menu lain bila id ini sudah dipakai di database berbeda.
            log_message('error', 'GroupAbsensiMenu: idmenu {id} sudah dipakai menu "{nama}".', [
                'id'   => self::GROUP_ID,
                'nama' => $grup->nama_menu,
            ]);

            return;
        }

        if (! $grup) {
            $this->db->table('menu')->insert([
                'idmenu'     => self::GROUP_ID,
                'urutan'     => 100,
                'nama_menu'  => 'Absensi',
                'roles'      => null,
                'url'        => null,
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 0,
                'utama'      => 1,
                'categories' => 1,
                'icon'       => '<iconify-icon icon="solar:checklist-bold" width="24" height="24"></iconify-icon>',
            ]);
        }

        // Header KEPEGAWAIAN digeser satu supaya grup Absensi langsung di bawahnya.
        $this->db->table('menu')->where('idmenu', 100)->update(['urutan' => 99]);

        foreach (self::CHILDREN as $id => $urutan) {
            $this->db->table('menu')->where('idmenu', $id)->update([
                'parent'     => self::GROUP_ID,
                'categories' => 0,
                'urutan'     => $urutan,
            ]);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $grup = $this->db->table('menu')->where('idmenu', self::GROUP_ID)->get()->getRow();
        if (! $grup || $grup->nama_menu !== 'Absensi') {
            return;
        }

        $this->db->table('menu')->where('idmenu', self::GROUP_ID)->delete();
        $this->db->table('menu')->where('idmenu', 100)->update(['urutan' => 100]);

        $awal = [
            112 => ['parent' => 0, 'categories' => 1, 'urutan' => 101],
            116 => ['parent' => 0, 'categories' => 1, 'urutan' => 101],
            115 => ['parent' => 10015, 'categories' => 0, 'urutan' => 115],
            117 => ['parent' => 10015, 'categories' => 0, 'urutan' => 117],
            118 => ['parent' => 0, 'categories' => 1, 'urutan' => 118],
        ];

        foreach ($awal as $id => $data) {
            $this->db->table('menu')->where('idmenu', $id)->update($data);
        }
    }
}
