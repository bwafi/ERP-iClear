<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Master aset: asal barang (dari_unit) + lokasi (unit) + kode unit singkat.
 *
 * - unit: tambah kolom kode_unit (HO, PRO, JBR, BYW, PDN) untuk kode aset
 *   otomatis AST-{KODE}-{4 digit acak} yang mengikuti asal barang.
 * - tambah unit HO (kepala kantor) agar "barang dari HO" bisa dipilih.
 * - aset_kpi: tambah dari_unit; ambil kembali unit sebagai lokasi barang.
 * - kode aset lama (AST{n}-NNNN) dimigrasikan ke format baru supaya seragam.
 */
class AddAssetOriginAndUnitCodes extends Migration
{
    private const UNIT_CODES = [
        'ICLEAR Probolinggo' => 'PRO',
        'ICLEAR Jember'      => 'JBR',
        'ICLEAR Banyuwangi'  => 'BYW',
        'ICLEAR Pandaan'     => 'PDN',
    ];

    public function up()
    {
        $this->db->query('ALTER TABLE unit ADD COLUMN kode_unit VARCHAR(10) NULL AFTER NAMA_UNIT');

        foreach (self::UNIT_CODES as $name => $code) {
            $this->db->table('unit')
                ->where('UPPER(TRIM(NAMA_UNIT))', strtoupper($name))
                ->update(['kode_unit' => $code]);
        }
        // Fallback: kode acak dari nama unit (tanpa kata "ICLEAR").
        $this->db->query(
            "UPDATE unit u SET u.kode_unit = UPPER(LEFT(REPLACE(REPLACE(TRIM(u.NAMA_UNIT),'ICLEAR ',''),' ICLEAR',''),3))
             WHERE u.kode_unit IS NULL OR u.kode_unit = ''"
        );

        // Unit HO (Head Office) bila belum ada.
        $ho = $this->db->table('unit')->where('UPPER(TRIM(NAMA_UNIT))', 'HO')->get()->getRow();
        if (!$ho) {
            $this->db->table('unit')->insert([
                'idunit'    => 5,
                'NAMA_UNIT' => 'HO',
                'kode_unit' => 'HO',
                'jenis'     => 'Kantor',
            ]);
        }

        // Asal barang.
        $this->db->query('ALTER TABLE aset_kpi ADD COLUMN dari_unit INT NULL AFTER unit');
        $this->db->query('UPDATE aset_kpi SET dari_unit = unit WHERE dari_unit IS NULL');

        // Migrasi kode lama AST{n}-NNNN → AST-{KODE}-NNNN (unik).
        $rows = $this->db->query('SELECT a.id, a.kode_aset, u.kode_unit FROM aset_kpi a LEFT JOIN unit u ON u.idunit = a.dari_unit')->getResult();
        foreach ($rows as $r) {
            $kodeBaru = sprintf('AST-%s-', (string)$r->kode_unit);
            if ($r->kode_unit && strpos((string)$r->kode_aset, $kodeBaru) !== 0) {
                $kode = $this->newKode((string)$r->kode_unit);
                if ($kode !== null) {
                    $this->db->table('aset_kpi')->where('id', $r->id)->update(['kode_aset' => $kode]);
                }
            }
        }
    }

    private function newKode(string $unitCode): ?string
    {
        if ($unitCode === '') {
            return null;
        }
        for ($i = 0; $i < 20; $i++) {
            $kode = sprintf('AST-%s-%04d', $unitCode, random_int(0, 9999));
            if ($this->db->table('aset_kpi')->where('kode_aset', $kode)->countAllResults() === 0) {
                return $kode;
            }
        }
        return null;
    }

    public function down()
    {
        $this->db->query("UPDATE unit SET kode_unit = NULL");
        $this->db->query("DELETE FROM unit WHERE UPPER(TRIM(NAMA_UNIT)) = 'HO'");
        $this->db->query('ALTER TABLE unit DROP COLUMN kode_unit');
        $this->db->query('ALTER TABLE aset_kpi DROP COLUMN dari_unit');
    }
}