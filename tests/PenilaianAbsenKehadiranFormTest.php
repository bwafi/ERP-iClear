<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Form /penilaian/absen hanya mengisi skor manual. KEHADIRAN dan SERAGAM
 * berasal dari absen mobile, jadi tidak ada input status/shift/jam di form.
 *
 * @internal
 */
final class PenilaianAbsenKehadiranFormTest extends CIUnitTestCase
{
    private function view(): string
    {
        return (string) file_get_contents(APPPATH . 'Views/penilaian/penilaian_absen.php');
    }

    public function testFormMasihMenyimpanSkorManual(): void
    {
        $view = $this->view();

        $this->assertStringContainsString('name="tanggal"', $view);
        $this->assertStringContainsString('name="skor_', $view);
        $this->assertStringContainsString('name="confirm"', $view);
    }

    public function testInputKehadiranSudahDihapus(): void
    {
        $view = $this->view();

        foreach (['name="kehadiran_status"', 'name="shift"', 'name="attendance_type"', 'name="jam_masuk', 'id="atsStatus"', 'id="atsOffNote"', 'id="atsPreview"', 'pa-punch'] as $markup) {
            $this->assertStringNotContainsString($markup, $view, $markup . ' seharusnya tidak ada di form.');
        }
    }
}
