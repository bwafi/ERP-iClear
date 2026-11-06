<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * DataTables melempar "Incorrect column count" kalau jumlah sel <tbody>
 * tidak sama dengan jumlah <th> di <thead>. Regresi ini menjaga agar
 * baris kosong dan status kehadiran yang tidak dikenal tetap punya sel.
 *
 * @internal
 */
final class RiwayatPresensiTableColumnsTest extends CIUnitTestCase
{
    private function render(array $rows): string
    {
        return view('riwayat/presensi', ['presensi' => $rows]);
    }

    /** @return array{headers: int, rows: list<int>} */
    private function columns(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $headers = $xpath->query('//table[@id="riwayat-presensi-table"]/thead/tr/th')->length;

        $rows = [];
        foreach ($xpath->query('//table[@id="riwayat-presensi-table"]/tbody/tr') as $tr) {
            $cells = 0;
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells += (int) ($td->getAttribute('colspan') ?: 1);
            }
            $rows[] = $cells;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function row(array $override = []): object
    {
        return (object) ($override + [
            'NAMA_AKUN'           => 'Budi',
            'NAMA_UNIT'           => 'ICLEAR Probolinggo',
            'created_at'          => '2026-11-01 08:40:00',
            'jmmasuk'             => '08:45:00',
            'toleransi'           => '09:00:00',
            'waktu_masuk'         => '2026-11-01 08:40:00',
            'waktu_pulang'        => '2026-11-01 17:00:00',
            'status_kehadiran'    => 0,
            'idpresensi'          => 1,
            'status_absensi'      => 1,
            'lat'                 => '-6.200000',
            'long'                => '106.800000',
            'ip'                  => '10.0.0.1',
            'jarak'               => 12,
            'sumber'              => 'mobile',
            'foto'                => 'masuk.jpg',
            'foto_pulang'         => 'pulang.jpg',
            'jam_jadwal_masuk'    => '08:45',
            'jam_toleransi'       => '09:00:00',
            'jam_jadwal_pulang'   => '17:00',
            'LATITUDE'            => '-6.200000',
            'LONGTITUDE'          => '106.800000',
        ]);
    }

    public function testTabelKosongTidakMemakaiBarisPlaceholder(): void
    {
        $table = $this->columns($this->render([]));

        $this->assertSame([], $table['rows'], 'Baris placeholder membuat DataTables menghitung kolom salah.');
        $this->assertSame(8, $table['headers']);
    }

    public function testStatusKehadiranNullTetapMemilikiSel(): void
    {
        $table = $this->columns($this->render([$this->row(['status_kehadiran' => null])]));

        $this->assertSame([$table['headers']], $table['rows']);
    }

    public function testStatusKehadiranTidakDikenalTetapMemilikiSel(): void
    {
        $table = $this->columns($this->render([$this->row(['status_kehadiran' => 3])]));

        $this->assertSame([$table['headers']], $table['rows']);
    }

    public function testBarisNormalMemilikiJumlahKolomSamaDenganHeader(): void
    {
        $table = $this->columns($this->render([$this->row()]));

        $this->assertSame([$table['headers']], $table['rows']);
    }
}
