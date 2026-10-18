<?php

namespace App\Commands;

use App\Services\Meta\MetaAdsPullService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Tarik biaya & insight iklan Meta ke laporan Performa Ads.
 *
 *   php spark meta:pull                                     # 7 hari terakhir
 *   php spark meta:pull --dari=2026-10-01 --sampai=2026-10-15
 *   php spark meta:pull --dry-run                           # hitung saja
 *
 * Default 7 hari karena `spend` Meta masih menyesuaikan beberapa hari setelah
 * tanggalnya — tarikan ulang menimpa baris yang sama, bukan menggandakan.
 *
 * Cron harian (03:00):
 *   0 3 * * * cd /path/erp && php74 spark meta:pull >> writable/logs/meta_pull.log 2>&1
 */
class MetaPull extends BaseCommand
{
    protected $group       = 'Meta';
    protected $name        = 'meta:pull';
    protected $description = 'Tarik insight iklan Meta ke laporan Performa Ads.';
    protected $usage       = 'meta:pull [--dari=YYYY-MM-DD] [--sampai=YYYY-MM-DD] [--dry-run]';

    protected $options = [
        '-dari'    => 'Tanggal mulai (YYYY-MM-DD). Default 7 hari lalu.',
        '-sampai'  => 'Tanggal akhir (YYYY-MM-DD). Default hari ini.',
        '-dry-run' => 'Hitung saja, jangan menulis apa pun.',
    ];

    public function run(array $params)
    {
        $sampai = $this->nilaiOpsi('sampai');
        $dari   = $this->nilaiOpsi('dari');
        $dryRun = $this->adaOpsi('dry-run');

        $sampai = $sampai === '' ? date('Y-m-d') : $sampai;
        $dari   = $dari === '' ? date('Y-m-d', strtotime($sampai . ' -6 days')) : $dari;

        foreach (['--dari' => $dari, '--sampai' => $sampai] as $label => $value) {
            if (!$this->tanggalValid($value)) {
                CLI::error("{$label} harus format YYYY-MM-DD dan tanggal yang nyata. Diberi: {$value}");
                return EXIT_ERROR;
            }
        }

        if ($dari > $sampai) {
            CLI::error("--dari ({$dari}) tidak boleh lebih akhir dari --sampai ({$sampai}).");
            return EXIT_ERROR;
        }

        CLI::write("Menarik insight Meta {$dari} s/d {$sampai}" . ($dryRun ? ' (dry-run)' : '') . '…', 'yellow');

        if (!$dryRun) {
            // Campaign baru harus ada dulu, kalau tidak barisnya dilewati
            // sebagai "Tanpa peta". Kegagalan sync tidak menggagalkan tarikan.
            try {
                $h = (new \App\Services\Meta\MetaCampaignSyncService())->sync(
                    (int)date('n', strtotime($sampai)),
                    (int)date('Y', strtotime($sampai))
                );
                CLI::write("Campaign: {$h['dibuat']} baru, {$h['diperbarui']} diperbarui.", 'green');
            } catch (\Throwable $e) {
                CLI::write('Sync campaign dilewati: ' . $e->getMessage(), 'yellow');
            }
        }

        try {
            $hasil = (new MetaAdsPullService())->pull($dari, $sampai, $dryRun);
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }

        CLI::newLine();
        CLI::write("Baris baru      : {$hasil['baru']}");
        CLI::write("Baris diperbarui: {$hasil['diperbarui']}");
        CLI::write(
            "Baris dilewati  : {$hasil['dilewati']}",
            $hasil['dilewati'] > 0 ? 'yellow' : 'white'
        );

        if ($hasil['tanpa_peta'] !== []) {
            CLI::write('Kampanye Meta ini belum dipetakan ke campaign ERP:', 'yellow');
            foreach ($hasil['tanpa_peta'] as $metaId) {
                CLI::write("  - {$metaId}");
            }
            CLI::write('Isi kolom "ID Campaign Meta" di menu Marketing → Campaign.', 'yellow');
        }

        if ($dryRun) {
            CLI::write('Dry-run: tidak ada data yang ditulis.', 'yellow');
        } else {
            CLI::write("Log tarikan #{$hasil['log_id']} tersimpan.", 'green');
        }

        return EXIT_SUCCESS;
    }

    /** Format YYYY-MM-DD DAN tanggal yang benar-benar ada (menolak 2026-13-45). */
    private function tanggalValid(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /**
     * Nilai sebuah opsi.
     *
     * CodeIgniter 4.4 hanya memecah bentuk `--nama nilai`; bentuk `--nama=nilai`
     * masuk apa adanya sebagai kunci. Kalau tidak dipecah di sini, opsinya
     * hilang tanpa pesan dan perintah diam-diam memakai rentang tanggal default.
     */
    private function nilaiOpsi(string $nama): string
    {
        $nilai = CLI::getOption($nama);
        if (is_string($nilai) && trim($nilai) !== '') {
            return trim($nilai);
        }

        foreach (array_keys(CLI::getOptions()) as $kunci) {
            $kunci = (string)$kunci;
            if (strpos($kunci, $nama . '=') === 0) {
                return trim(substr($kunci, strlen($nama) + 1));
            }
        }

        return '';
    }

    /** Apakah flag (opsi tanpa nilai) diberikan. */
    private function adaOpsi(string $nama): bool
    {
        if (CLI::getOption($nama) !== null) {
            return true;
        }

        foreach (array_keys(CLI::getOptions()) as $kunci) {
            if (strpos((string)$kunci, $nama . '=') === 0) {
                $nilai = substr((string)$kunci, strlen($nama) + 1);

                return $nilai !== '' && $nilai !== '0' && $nilai !== 'false';
            }
        }

        return false;
    }
}
