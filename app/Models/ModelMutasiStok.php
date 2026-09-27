<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMutasiStok extends Model
{
    protected $table = 'mutasi';
    protected $primaryKey = 'idmutasi';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idmutasi',
        'no_nota_mutasi',
        'tanggal_kirim',
        'tanggal_terima',
        'status',
        'kirim_idunit',
        'terima_idunit',
        'input_by',
        'created_on',
        'updated_on',
        // Jejak pembatalan penerimaan (MutasiStok::batalTerima). Wajib ada
        // di sini: CodeIgniter membuang field yang tidak terdaftar, dan
        // pembuangan yang sunyi itu akan terlihat seperti fitur yang tidak
        // pernah menyimpan apa pun.
        'batal_oleh',
        'batal_at',
        'batal_alasan',

    ];

    public function getMutasiStok()
    {
        return $this->findAll();
    }

    public function insert_MutasiStok($data)
    {
        return $this->insert($data);
    }

    public function getById($idmutasi)
    {
        return $this->where(['idmutasi' => $idmutasi])->first();
    }

    /**
     * Satu halaman mutasi masuk untuk halaman konfirmasi terima.
     *
     * Dipisah dari countMutasiMasuk() hanya soal limit/offset; syarat
     * filternya diambil dari method yang sama supaya isi tabel dan angka di
     * footer tidak pernah berbeda jawaban.
     */
    public function getMutasiMasuk(int $perPage, int $page, bool $isLintas, int $unit, array $f): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $builder = $this->db->table('mutasi');
        $this->applyFilterMasuk($builder, $isLintas, $unit, $f);

        return $builder->orderBy('idmutasi', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResult();
    }

    public function countMutasiMasuk(bool $isLintas, int $unit, array $f): int
    {
        $builder = $this->db->table('mutasi');
        $this->applyFilterMasuk($builder, $isLintas, $unit, $f);

        return (int) $builder->countAllResults();
    }

    /**
     * Syarat filter, dipakai bersama oleh get & count.
     *
     * Scope unit adalah batas akses, bukan sekadar filter: untuk user yang
     * bukan lintas unit, `terima_idunit` dipatok di sini. Kalau nanti ada
     * yang menambahkan syarat hanya di salah satu query, `total` di footer
     * akan membocorkan ada atau tidaknya mutasi unit lain.
     */
    private function applyFilterMasuk($builder, bool $isLintas, int $unit, array $f): void
    {
        if (! $isLintas) {
            $builder->where('terima_idunit', $unit);
        }

        if (($f['search'] ?? '') !== '') {
            $builder->groupStart()->like('no_nota_mutasi', $f['search'])->groupEnd();
        }
        if (($f['status'] ?? '') !== '') {
            $builder->where('status', $f['status']);
        }
        if (($f['dari'] ?? '') !== '') {
            $builder->where('tanggal_kirim >=', $f['dari'] . ' 00:00:00');
        }
        if (($f['sampai'] ?? '') !== '') {
            $builder->where('tanggal_kirim <=', $f['sampai'] . ' 23:59:59');
        }
        // Hanya berlaku untuk user lintas unit. Untuk user satu unit, filter
        // ini tidak pernah mengubah apa pun karena scope di atas sudah lebih
        // ketat — jadi kontrolnya tidak perlu ditampilkan.
        if ($isLintas && ! empty($f['unit'])) {
            $builder->groupStart()
                ->where('kirim_idunit', (int) $f['unit'])
                ->orWhere('terima_idunit', (int) $f['unit'])
                ->groupEnd();
        }
    }
}
