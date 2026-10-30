<?php

namespace App\Services\Finance;

use App\Models\ModelAkunKasBank;
use CodeIgniter\Database\ConnectionInterface;
use Config\Database;
use Config\Finance;

/**
 * KPI Kesehatan Uang per Cabang (FIN_KESEHATAN_UANG) — bobot 30%.
 *
 * RASIO = saldo kas & bank akhir bulan / biaya bulan itu x 100
 * SKOR  = min((rasio / target 120%) x 100, 100)
 *
 * Definisi yang dijaga (keputusan bisnis 2026-10-30: SALDO, bukan uang
 * diterima):
 *  - Saldo = posisi unit pada rekening KAS + BANK (termasuk porsi rekening
 *    bersama) per akhir bulan evaluasi, dari KasBankCutoffService::posisiUnit.
 *    Sumber yang sama dengan halaman Kas & Bank; rekening Finance/HO tidak ikut.
 *  - Biaya = Kas Keluar operasional + Payroll pada periode yang sama. HPP
 *    penjualan TIDAK dihitung (keputusan bisnis 2026-10-30); kategori `HPP`
 *    dan `SDM` di `kas_keluar` juga dikecualikan agar tidak dobel dengan
 *    pembelian stok dan register payroll.
 *    Rasio berarti "saldo cukup untuk berapa bulan biaya"; target 120% =
 *    saldo >= 1,2x biaya sebulan.
 *  - Periode biaya = BULAN PENUH (tanggal 1 s/d akhir bulan evaluasi), tanpa
 *    batas cut-off, supaya biaya bulan itu utuh.
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
 *  - Periode sebelum ledger Kas & Bank dimulai -> status 'saldo_belum_tersedia',
 *    SKOR null (tidak mengarang saldo dari statement lama).
 */
class KesehatanUangCalculator implements FinanceCalculatorInterface
{
    /** Target rasio kesehatan (%) yang menghasilkan skor kategori 100. */
    public const TARGET_RASIO_DEFAULT = 120.0;

    /** Kategori kas_keluar yang dikeluarkan dari "Kas Keluar operasional". */
    public const KATEGORI_HPP = 'HPP';
    public const KATEGORI_SDM = 'SDM';
    public const KATEGORI_BEBAN_CABANG = 'BEBAN CABANG';
    public const KATEGORI_TEMPO = 'TEMPO';
    public const KATEGORI_KASBON = 'KASBON';

    protected ?ConnectionInterface $db = null;
    protected Finance $config;
    protected ?KasBankCutoffService $kasBank = null;
    protected ?ModelAkunKasBank $akunKasBank = null;

    /**
     * Cache per-request: komponen ini diminta berkali-kali dalam satu
     * perhitungan KPI (dan oleh kalkulator lain) untuk unit/rentang yang sama.
     * Tabel sumbernya tidak ditulis selama request.
     */
    private static $memo = [];

    public function __construct(
        ?Finance $config = null,
        ?KasBankCutoffService $kasBank = null,
        ?ModelAkunKasBank $akunKasBank = null
    ) {
        $this->config      = $config ?? new Finance();
        $this->kasBank     = $kasBank;
        $this->akunKasBank = $akunKasBank;
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
        // BULAN PENUH untuk biaya; saldo diambil per akhir bulan (atau hari
        // ini kalau bulan evaluasi belum selesai).
        $dari         = sprintf('%04d-%02d-01', $year, $month);
        $sampai       = date('Y-m-t', strtotime($dari));
        $tanggalSaldo = min($sampai, date('Y-m-d'));

        // Saldo hanya sah sejak ledger Kas & Bank dimulai. Bulan sebelumnya
        // tidak punya baseline, jadi jangan menampilkan angka statement lama
        // sebagai saldo bulan itu.
        $saldoTersedia = $sampai >= FinanceScopeService::kasBankPeriodeMulaiDate();
        $saldo = $saldoTersedia
            ? $this->saldoKasBank($unitId, $tanggalSaldo)
            : ['tunai' => 0, 'bank' => 0, 'total' => 0];

        return $this->assemble([
            'unit_id'              => $unitId,
            'periode_mulai'        => $dari,
            'periode_akhir'        => $sampai,
            'tanggal_saldo'        => $tanggalSaldo,
            'saldo_tersedia'       => $saldoTersedia,
            'saldo'                => $saldo,
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
     *   tanggal_saldo?:string, saldo_tersedia?:bool,
     *   saldo:array{tunai?:int,bank?:int,total?:int},
     *   kas_keluar:int, payroll:int,
     *   kas_keluar_excluded?:array
     * } $in
     */
    public function assemble(array $in): array
    {
        $saldoTunai    = (int) ($in['saldo']['tunai'] ?? 0);
        $saldoBank     = (int) ($in['saldo']['bank'] ?? 0);
        $saldo         = (int) ($in['saldo']['total'] ?? ($saldoTunai + $saldoBank));
        $kasKeluar     = (int) ($in['kas_keluar'] ?? 0);
        $payroll       = (int) ($in['payroll'] ?? 0);
        $keluar        = $kasKeluar + $payroll;
        $target        = (float) ($this->config->kesehatanUangTargetPercent ?? self::TARGET_RASIO_DEFAULT);
        $bobot         = (float) ($this->config->kpiWeights['kesehatan_uang'] ?? 30);

        $rasio = null;
        $skor  = null;
        $status = 'ok';

        if (! ($in['saldo_tersedia'] ?? true)) {
            // Baseline Kas & Bank belum ada untuk periode ini.
            $status = 'saldo_belum_tersedia';
        } elseif ($keluar <= 0) {
            // Tidak ada pembagian dengan nol.
            $status = 'kebutuhan_nol';
        } else {
            $rasio = round(($saldo / $keluar) * 100, 2);
            $skor  = self::skorKategori($rasio, $target);
        }

        return [
            'score'  => $skor,
            'status' => $status,
            'detail' => [
                'unit_id'             => (int) ($in['unit_id'] ?? 0),
                'periode_mulai'       => $in['periode_mulai'] ?? null,
                'periode_akhir'       => $in['periode_akhir'] ?? null,
                'tanggal_saldo'       => $in['tanggal_saldo'] ?? null,
                'saldo_tersedia'      => (bool) ($in['saldo_tersedia'] ?? true),
                'saldo_tunai'         => $saldoTunai,
                'saldo_bank'          => $saldoBank,
                'total_saldo'         => $saldo,
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
     * Saldo unit per tanggal = posisi pada rekening KAS + BANK.
     *
     * Memakai KasBankCutoffService::posisiUnit supaya rekening bersama ikut
     * porsi unit (alokasi + movement), bukan saldo fisik seluruh rekening.
     * Rekening Finance/HO tidak punya unit pemilik dan otomatis 0.
     *
     * @return array{tunai:int,bank:int,total:int}
     */
    public function saldoKasBank(int $unitId, string $sampai): array
    {
        $tunai = 0;
        $bank  = 0;

        foreach ($this->akunKasBank()->getDalamScopeUnit([$unitId]) as $akun) {
            $posisi = (int) $this->kasBank()->posisiUnit((int) $akun->idakun_kas_bank, $unitId, $sampai);

            if (strtoupper((string) ($akun->tipe ?? '')) === TutupKasirSourceDefinition::TIPE_KAS) {
                $tunai += $posisi;
            } else {
                $bank += $posisi;
            }
        }

        return [
            'tunai' => $tunai,
            'bank'  => $bank,
            'total' => $tunai + $bank,
        ];
    }

    protected function kasBank(): KasBankCutoffService
    {
        return $this->kasBank ??= new KasBankCutoffService();
    }

    protected function akunKasBank(): ModelAkunKasBank
    {
        return $this->akunKasBank ??= new ModelAkunKasBank();
    }

    /**
     * HPP = harga pokok penjualan periode = SUM(hpp_penjualan x jumlah).
     *
     * Tidak lagi dipakai sebagai komponen Kesehatan Uang (keputusan bisnis
     * 2026-10-30); dipertahankan untuk verifikasi memo/query.
     *
     * Definisi baris sama dengan LabaRugi/OmsetCabangCalculator; `jumlah`
     * dikalikan karena `hpp_penjualan` adalah HPP per unit.
     */
    public function hpp(int $unitId, string $dari, string $sampai): int
    {
        $key = \App\Libraries\ReadCache::kunci('hpp', $unitId, $dari, $sampai);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $row = $this->db()->table('detail_penjualan dp')
            ->select('COALESCE(SUM(dp.hpp_penjualan * dp.jumlah), 0) AS total')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->where('p.unit_idunit', $unitId)
            ->where('DATE(p.tanggal) >=', $dari)
            ->where('DATE(p.tanggal) <=', $sampai)
            ->get()
            ->getRow();

        return self::$memo[$key] = (int) ($row->total ?? 0);
    }

    /**
     * Kas Keluar operasional = kas_keluar di luar kategori HPP dan SDM.
     *
     * Kategori HPP (pembelian stok), SDM, BEBAN CABANG, TEMPO, dan KASBON
     * sengaja tidak dihitung sebagai komponen Kesehatan Uang; SDM sudah
     * diwakili register Payroll, sedangkan TEMPO adalah utang dagang. Tabel
     * `kas_keluar` tidak pernah memuat transfer internal (Setor/Tarik, Pindah
     * Saldo, pelunasan H/P antar unit hidup di `transaksi_kas_bank`), jadi
     * arus internal tidak ikut terhitung.
     */
    public function kasKeluarOperasional(int $unitId, string $dari, string $sampai): int
    {
        $key = \App\Libraries\ReadCache::kunci('kasKeluarOperasional', $unitId, $dari, $sampai);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $row = $this->db()->table('kas_keluar kk')
            ->select('COALESCE(SUM(kk.jumlah), 0) AS total')
            ->join('kategori_kas kat', 'kat.idkategori_kas = kk.kategori_idkategori', 'left')
            ->where('kk.idunit', $unitId)
            ->where('DATE(kk.tanggal) >=', $dari)
            ->where('DATE(kk.tanggal) <=', $sampai)
            ->groupStart()
                ->where('kat.kategori IS NULL', null, false)
                ->orWhereNotIn('kat.kategori', self::kategoriDikecualikan())
            ->groupEnd()
            ->get()
            ->getRow();

        return self::$memo[$key] = (int) ($row->total ?? 0);
    }

    /**
     * Nominal kas_keluar kategori HPP, SDM, BEBAN CABANG, TEMPO & KASBON yang
     * SENGAJA dikeluarkan dari komponen operasional (untuk transparansi di UI).
     *
     * @return array{hpp:int,sdm:int,beban_cabang:int,tempo:int,kasbon:int}
     */
    public function kasKeluarDikecualikan(int $unitId, string $dari, string $sampai): array
    {
        $key = \App\Libraries\ReadCache::kunci('kasKeluarDikecualikan', $unitId, $dari, $sampai);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $rows = $this->db()->table('kas_keluar kk')
            ->select('kat.kategori AS kategori, COALESCE(SUM(kk.jumlah), 0) AS total')
            ->join('kategori_kas kat', 'kat.idkategori_kas = kk.kategori_idkategori', 'left')
            ->where('kk.idunit', $unitId)
            ->where('DATE(kk.tanggal) >=', $dari)
            ->where('DATE(kk.tanggal) <=', $sampai)
            ->whereIn('kat.kategori', self::kategoriDikecualikan())
            ->groupBy('kat.kategori')
            ->get()
            ->getResultArray();

        $out = ['hpp' => 0, 'sdm' => 0, 'beban_cabang' => 0, 'tempo' => 0, 'kasbon' => 0];
        foreach ($rows as $r) {
            $kategori = strtoupper((string) $r['kategori']);
            if ($kategori === self::KATEGORI_HPP) {
                $out['hpp'] = (int) $r['total'];
            } elseif ($kategori === self::KATEGORI_SDM) {
                $out['sdm'] = (int) $r['total'];
            } elseif ($kategori === self::KATEGORI_BEBAN_CABANG) {
                $out['beban_cabang'] = (int) $r['total'];
            } elseif ($kategori === self::KATEGORI_TEMPO) {
                $out['tempo'] = (int) $r['total'];
            } elseif ($kategori === self::KATEGORI_KASBON) {
                $out['kasbon'] = (int) $r['total'];
            }
        }

        return self::$memo[$key] = $out;
    }

    /** @return array<int,string> */
    private static function kategoriDikecualikan(): array
    {
        return [
            self::KATEGORI_HPP,
            self::KATEGORI_SDM,
            self::KATEGORI_BEBAN_CABANG,
            self::KATEGORI_TEMPO,
            self::KATEGORI_KASBON,
        ];
    }

    /**
     * Payroll periode = SUM(finance_payroll.total) yang jatuh tempo di rentang.
     */
    public function payroll(int $unitId, string $dari, string $sampai): int
    {
        $key = \App\Libraries\ReadCache::kunci('payroll', $unitId, $dari, $sampai);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        if (! $this->db()->tableExists('finance_payroll')) {
            return self::$memo[$key] = 0;
        }

        $row = $this->db()->table('finance_payroll')
            ->select('COALESCE(SUM(total), 0) AS total')
            ->where('unit_id', $unitId)
            ->where('due_date >=', $dari)
            ->where('due_date <=', $sampai)
            ->get()
            ->getRow();

        return self::$memo[$key] = (int) ($row->total ?? 0);
    }
}
