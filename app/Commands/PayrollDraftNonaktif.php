<?php

namespace App\Commands;

use App\Services\Payroll\PayrollDraftService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Bersihkan draft payroll (`finance_payroll` status `rencana`) milik pegawai
 * yang sudah nonaktif/resign. Default dry-run; gunakan `--apply` untuk
 * menghapus. Baris `dibayar` dan data akun tidak pernah disentuh.
 *
 * Contoh:
 *   php spark payroll:draft-nonaktif
 *   php spark payroll:draft-nonaktif --pegawai=54 --bulan=2026-11
 *   php spark payroll:draft-nonaktif --pegawai=54 --bulan=2026-11 --apply
 */
class PayrollDraftNonaktif extends BaseCommand
{
    protected $group       = 'payroll';
    protected $name        = 'payroll:draft-nonaktif';
    protected $description = 'Tampilkan atau batalkan draft payroll (rencana) milik pegawai nonaktif/resign.';

    public function run(array $params): int
    {
        $pegawai = (int) $this->opsi($params, 'pegawai');
        $bulan   = trim((string) $this->opsi($params, 'bulan'));
        $apply   = (bool) $this->opsi($params, 'apply');

        if ($bulan !== '' && ! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            CLI::error('Format --bulan harus YYYY-MM.');

            return 1;
        }

        $db      = Database::connect();
        $service = new PayrollDraftService($db);

        $filter = ['hanya_nonaktif' => true];
        if ($pegawai > 0) {
            $filter['pegawai_id'] = $pegawai;
        }
        if ($bulan !== '') {
            $filter['bulan'] = $bulan;
        }

        try {
            $rows = $service->cari($filter);
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());

            return 1;
        }

        if ($rows === []) {
            CLI::write('Tidak ada draft rencana pegawai nonaktif yang cocok.', 'green');

            return 0;
        }

        CLI::write('Draft rencana pegawai nonaktif:', 'yellow');
        foreach ($rows as $row) {
            CLI::write(sprintf(
                '  #%d %s (%s) - jatuh tempo %s - Rp %s',
                (int) $row['id'],
                (string) ($row['NAMA_AKUN'] ?? '-'),
                (string) ($row['NAMA_UNIT'] ?? '-'),
                (string) ($row['due_date'] ?? '-'),
                number_format((float) ($row['total'] ?? 0), 0, ',', '.')
            ));
        }

        if (! $apply) {
            CLI::write(sprintf(
                'Total %d draft. DRY-RUN: tidak ada data yang dihapus. Jalankan ulang dengan --apply untuk membatalkan.',
                count($rows)
            ), 'yellow');

            return 0;
        }

        $db->transBegin();
        $terhapus = 0;

        try {
            foreach ($rows as $row) {
                $service->batalkan((int) $row['id']);
                $terhapus++;
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            CLI::error('Gagal membatalkan draft: ' . $e->getMessage());

            return 1;
        }

        CLI::write(sprintf(
            '%d draft dibatalkan. Baris dibayar dan data akun tidak disentuh.',
            $terhapus
        ), 'green');

        return 0;
    }

    /**
     * Baca opsi baik `--nama=nilai` maupun `--nama nilai`.
     *
     * CI 4.4 hanya memecah bentuk spasi, sehingga `--bulan=2026-11` masuk ke
     * `$params` sebagai key `bulan=2026-11`.
     *
     * @return mixed
     */
    private function opsi(array $params, string $nama)
    {
        $nilai = CLI::getOption($nama);
        if ($nilai !== null) {
            return $nilai;
        }

        foreach ($params as $key => $value) {
            if (is_string($key) && strpos($key, $nama . '=') === 0) {
                return substr($key, strlen($nama) + 1);
            }
        }

        return null;
    }
}
