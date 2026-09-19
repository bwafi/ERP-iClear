<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * KPI Ketepatan Pembayaran Piutang (auto).
 *
 * GAP KRITIS: tabel pembayaran_piutang TIDAK memiliki kolom tanggal bayar,
 * sehingga kategori "Tepat Waktu" belum dapat dihitung (mirip hutang).
 * Calculator ini hanya mampu menghitung:
 *  - Lunas    : status = 1 atau sisa_hutang <= 0 (tanpa pengetahuan tanggal).
 *  - Overdue  : belum lunas dan hari ini > jatuh_tempo.
 *  - Open     : belum lunas dan hari ini <= jatuh_tempo.
 *
 * Score = null dengan status 'gap_no_payment_date' sampai skema
 * pembayaran_piutang.tanggal_bayar ditambahkan dan diisi (Fase 3).
 * Detail tetap dikembalikan agar dashboard bisa menampilkan profil piutang.
 */
class PiutangTimelinessCalculator implements FinanceCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $today = date('Y-m-d');

        $items = $this->db->table('piutang')
            ->select('
                piutang.idpiutang,
                piutang.kode_piutang,
                piutang.tanggal,
                piutang.jatuh_tempo,
                piutang.jumlah_hutang,
                piutang.sisa_hutang,
                piutang.status,
                akun.NAMA_AKUN AS nama_pegawai
            ')
            ->join('akun', 'akun.ID_AKUN = piutang.pegawai_idpegawai', 'left')
            ->where('piutang.unit_idunit', $unitId)
            ->where('piutang.jatuh_tempo >=', $startDate)
            ->where('piutang.jatuh_tempo <=', $endDate)
            ->orderBy('piutang.jatuh_tempo', 'ASC')
            ->get()
            ->getResult();

        $lunas = 0;
        $overdue = 0;
        $open = 0;
        $detail = [];

        foreach ($items as $p) {
            $isLunas = (int) $p->status === 1 || (float) ($p->sisa_hutang ?? 0) <= 0;

            if ($isLunas) {
                $klasifikasi = 'Lunas';
                $lunas++;
            } elseif ($today > $p->jatuh_tempo) {
                $klasifikasi = 'Overdue';
                $overdue++;
            } else {
                $klasifikasi = 'Belum jatuh tempo (open)';
                $open++;
            }

            $detail[] = [
                'id' => (int) $p->idpiutang,
                'kode' => $p->kode_piutang,
                'pegawai' => $p->nama_pegawai,
                'tanggal' => $p->tanggal,
                'jatuh_tempo' => $p->jatuh_tempo,
                'jumlah' => (float) ($p->jumlah_hutang ?? 0),
                'sisa' => (float) ($p->sisa_hutang ?? 0),
                'klasifikasi' => $klasifikasi,
            ];
        }

        return [
            'score' => null, // GAP pembayaran_piutang.tanggal_bayar
            'status' => 'gap_no_payment_date',
            'detail' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'lunas' => $lunas,
                'overdue' => $overdue,
                'open' => $open,
                'items' => $detail,
            ],
        ];
    }
}