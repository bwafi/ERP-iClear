<?php

namespace App\Services\Finance;

use CodeIgniter\Database\ConnectionInterface;
use Config\Database;
use Config\Finance;

/**
 * KPI Kesehatan Uang per Cabang (FIN_KESEHATAN_UANG) — bobot 30%.
 *
 * RASIO = uang masuk bulan itu / uang keluar bulan itu x 100
 * SKOR  = min((rasio / target 120%) x 100, 100)
 *
 * Definisi yang dijaga (keputusan bisnis 2026-10-15: ARUS KAS bulan itu,
 * bukan lagi saldo rekening):
 *  - Uang masuk = penerimaan periode = penjualan (tunai + transfer) +
 *    service selesai (tunai + residual transfer). Definisi PERSIS sama
 *    dengan KPI Cash Flow (TutupKasirSourceDefinition) — satu sumber angka,
 *    bukan query tulis ulang.
 *  - Uang keluar = HPP (harga pokok penjualan) + Kas Keluar operasional +
 *    Payroll pada periode yang sama.
 *  - Periode = BULAN PENUH (tanggal 1 s/d akhir bulan evaluasi), tanpa batas
 *    cut-off, supaya "arus kas bulan itu" utuh.
 *  - Transfer internal (Setor/Tarik, Pindah Saldo, pelunasan H/P antar unit)
 *    TIDAK dihitung sebagai pengeluaran operasional: angka kas keluar diambil
 *    dari tabel `kas_keluar` (definisi TutupKasirSourceDefinition), bukan dari
 *    ledger `transaksi_kas_bank`.
 *  - Tidak menghitung biaya dua kali: kategori `HPP` di `kas_keluar`
 *    dikecualikan dari komponen "Kas Keluar operasional" karena HPP sudah
 *    dihitung dari harga pokok penjualan; kategori `SDM` (gaji/komisi) juga
 *    dikecualikan karena Payroll dihitung tersendiri dari register
 *    `finance_payroll`.
 *
 * Keamanan data:
 *  - Uang keluar nol / negatif -> status 'kebutuhan_nol', SKOR null. Tidak ada
 *    pembagian dengan nol.
 */
class KesehatanUangCalculator implements FinanceCalculatorInterface
{
    /** Target rasio kesehatan (%) yang menghasilkan skor kategori 100. */
    public const TARGET_RASIO_DEFAULT = 120.0;

    /** Kategori kas_keluar yang dikeluarkan dari "Kas Keluar operasional". */
    public const KATEGORI_HPP = 'HPP';
    public const KATEGORI_SDM = 'SDM';

    protected ?ConnectionInterface $db = null;
    protected TutupKasirSourceDefinition $src;
    protected Finance $config;

    public function __construct(?TutupKasirSourceDefinition $src = null, ?Finance $config = null)
    {
        $this->src    = $src ?? new TutupKasirSourceDefinition();
        $this->config = $config ?? new Finance();
    }

    /**
     * Koneksi dibuat lazy supaya bagian MURNI (assemble/skorKategori) bisa
     * diuji tanpa database.
     */
    protected function db(): ConnectionInterface
    {
        if ($this->db === null) {
            $this->db = Database::connect();
        }

        return $this->db;
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        // BULAN PENUH: arus kas periode bulan evaluasi, tanpa batas cut-off.
        $dari   = sprintf('%04d-%02d-01', $year, $month);
        $sampai = date('Y-m-t', strtotime($dari));

        return $this->assemble([
            'unit_id'              => $unitId,
            'periode_mulai'        => $dari,
            'periode_akhir'        => $sampai,
            'masuk'                => $this->uangMasuk($unitId, $dari, $sampai),
            'hpp'                  => $this->hpp($unitId, $dari, $sampai),
            'kas_keluar'           => $this->kasKeluarOperasional($unitId, $dari, $sampai),
            'payroll'              => $this->payroll($unitId, $dari, $sampai),
            'kas_keluar_excluded'  => $this->kasKeluarDikecualikan($unitId, $dari, $sampai),
        ]);
    }

    /**
     * Bagian MURNI: dari angka mentah menjadi rasio, skor, dan status.
     *
     * Dipisah dari query supaya formula bisa diuji tanpa database.
     *
     * @param array{
     *   unit_id?:int, periode_mulai?:string, periode_akhir?:string,
     *   masuk:array{tunai?:int,transfer?:int,total?:int},
     *   hpp:int, kas_keluar:int, payroll:int,
     *   kas_keluar_excluded?:array
     * } $in
     */
    public function assemble(array $in): array
    {
        $masukTunai    = (int) ($in['masuk']['tunai'] ?? 0);
        $masukTransfer = (int) ($in['masuk']['transfer'] ?? 0);
        $masuk         = (int) ($in['masuk']['total'] ?? ($masukTunai + $masukTransfer));
        $hpp           = (int) ($in['hpp'] ?? 0);
        $kasKeluar     = (int) ($in['kas_keluar'] ?? 0);
        $payroll       = (int) ($in['payroll'] ?? 0);
        $keluar        = $hpp + $kasKeluar + $payroll;
        $target        = (float) ($this->config->kesehatanUangTargetPercent ?? self::TARGET_RASIO_DEFAULT);
        $bobot         = (float) ($this->config->kpiWeights['kesehatan_uang'] ?? 30);

        $rasio = null;
        $skor  = null;
        $status = 'ok';

        if ($keluar <= 0) {
            // Tidak ada pembagian dengan nol.
            $status = 'kebutuhan_nol';
            $skor   = null;
        } else {
            $rasio = round(($masuk / $keluar) * 100, 2);
            $skor  = self::skorKategori($rasio, $target);
        }

        return [
            'score'  => $skor,
            'status' => $status,
            'detail' => [
                'unit_id'             => (int) ($in['unit_id'] ?? 0),
                'periode_mulai'       => $in['periode_mulai'] ?? null,
                'periode_akhir'       => $in['periode_akhir'] ?? null,
                'masuk_tunai'         => $masukTunai,
                'masuk_transfer'      => $masukTransfer,
                'total_masuk'         => $masuk,
                'hpp'                 => $hpp,
                'kas_keluar'          => $kasKeluar,
                'payroll'             => $payroll,
                'total_keluar'        => $keluar,
                'rasio'               => $rasio,
                'target_rasio'        => $target,
                'skor_kategori'       => $skor,
                'kontribusi'          => $skor === null
                    ? null
                    : round($skor * $bobot / 100, 2),
                'kas_keluar_excluded' => $in['kas_keluar_excluded'] ?? [],
            ],
        ];
    }

    /**
     * Skor kategori dari rasio: min((rasio / target) x 100, 100), minimum 0.
     * Rasio negatif tetap 0, bukan negatif.
     */
    public static function skorKategori(?float $rasio, float $target): ?float
    {
        if ($rasio === null) {
            return null;
        }

        if ($target <= 0) {
            return null;
        }

        return round(min(max(($rasio / $target) * 100, 0), 100), 2);
    }

    /**
     * Uang masuk periode = penjualan (tunai + transfer) + service selesai
     * (tunai + residual transfer).
     *
     * Definisi PERSIS sama dengan KPI Cash Flow supaya satu sumber angka
     * (TutupKasirSourceDefinition), bukan query yang ditulis ulang.
     *
     * @return array{tunai:int,transfer:int,total:int}
     */
    public function uangMasuk(int $unitId, string $dari, string $sampai): array
    {
        $tunai = $this->src->cashPenjualanRange($unitId, $dari, $sampai)
            + $this->src->cashServiceRange($unitId, $dari, $sampai);

        $transfer = $this->src->transferPenjualanRange($unitId, $dari, $sampai)
            + $this->src->transferServiceRange($unitId, $dari, $sampai);

        return [
            'tunai'    => $tunai,
            'transfer' => $transfer,
            'total'    => $tunai + $transfer,
        ];
    }

    /**
     * HPP = harga pokok penjualan periode = SUM(hpp_penjualan x jumlah).
     *
     * Definisi baris sama dengan LabaRugi/OmsetCabangCalculator; `jumlah`
     * dikalikan karena `hpp_penjualan` adalah HPP per unit.
     */
    public function hpp(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db()->table('detail_penjualan dp')
            ->select('COALESCE(SUM(dp.hpp_penjualan * dp.jumlah), 0) AS total')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->where('p.unit_idunit', $unitId)
            ->where('DATE(p.tanggal) >=', $dari)
            ->where('DATE(p.tanggal) <=', $sampai)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * Kas Keluar operasional = kas_keluar di luar kategori HPP dan SDM.
     *
     * Kategori HPP sudah menjadi komponen HPP; kategori SDM sudah menjadi
     * komponen Payroll. Mengeluarkan keduanya mencegah biaya dihitung dua
     * kali. Tabel `kas_keluar` tidak pernah memuat transfer internal
     * (Setor/Tarik, Pindah Saldo, pelunasan H/P antar unit hidup di
     * `transaksi_kas_bank`), jadi arus internal tidak ikut terhitung.
     */
    public function kasKeluarOperasional(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db()->table('kas_keluar kk')
            ->select('COALESCE(SUM(kk.jumlah), 0) AS total')
            ->join('kategori_kas kat', 'kat.idkategori_kas = kk.kategori_idkategori', 'left')
            ->where('kk.idunit', $unitId)
            ->where('DATE(kk.tanggal) >=', $dari)
            ->where('DATE(kk.tanggal) <=', $sampai)
            ->groupStart()
                ->where('kat.kategori IS NULL', null, false)
                ->orWhereNotIn('kat.kategori', [self::KATEGORI_HPP, self::KATEGORI_SDM])
            ->groupEnd()
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * Nominal kas_keluar kategori HPP & SDM yang SENGAJA dikeluarkan dari
     * komponen operasional (untuk transparansi/rincian di UI).
     *
     * @return array{hpp:int,sdm:int}
     */
    public function kasKeluarDikecualikan(int $unitId, string $dari, string $sampai): array
    {
        $rows = $this->db()->table('kas_keluar kk')
            ->select('kat.kategori AS kategori, COALESCE(SUM(kk.jumlah), 0) AS total')
            ->join('kategori_kas kat', 'kat.idkategori_kas = kk.kategori_idkategori', 'left')
            ->where('kk.idunit', $unitId)
            ->where('DATE(kk.tanggal) >=', $dari)
            ->where('DATE(kk.tanggal) <=', $sampai)
            ->whereIn('kat.kategori', [self::KATEGORI_HPP, self::KATEGORI_SDM])
            ->groupBy('kat.kategori')
            ->get()
            ->getResultArray();

        $out = ['hpp' => 0, 'sdm' => 0];
        foreach ($rows as $r) {
            if (strtoupper((string) $r['kategori']) === self::KATEGORI_HPP) {
                $out['hpp'] = (int) $r['total'];
            } elseif (strtoupper((string) $r['kategori']) === self::KATEGORI_SDM) {
                $out['sdm'] = (int) $r['total'];
            }
        }

        return $out;
    }

    /**
     * Payroll periode = SUM(finance_payroll.total) yang jatuh tempo di rentang.
     */
    public function payroll(int $unitId, string $dari, string $sampai): int
    {
        if (! $this->db()->tableExists('finance_payroll')) {
            return 0;
        }

        $row = $this->db()->table('finance_payroll')
            ->select('COALESCE(SUM(total), 0) AS total')
            ->where('unit_id', $unitId)
            ->where('due_date >=', $dari)
            ->where('due_date <=', $sampai)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }
}
