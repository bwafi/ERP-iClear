<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPresensiRoster extends Model
{
    protected $table = 'presensi_roster';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'akun_idakun',
        'tanggal',
        'shift',
        'unit_idunit',
        'keterangan',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    public function getByAkunTanggal(int $akunId, string $tanggal)
    {
        return $this->where('akun_idakun', $akunId)
            ->where('tanggal', $tanggal)
            ->first();
    }

    /** @return array<string, object> keyed by tanggal */
    public function getRentang(int $akunId, string $dari, string $sampai): array
    {
        $rows = $this->where('akun_idakun', $akunId)
            ->where('tanggal >=', $dari)
            ->where('tanggal <=', $sampai)
            ->findAll();

        $hasil = [];
        foreach ($rows as $row) {
            $hasil[$row->tanggal] = $row;
        }

        return $hasil;
    }

    public function ada(int $akunId, string $tanggal): bool
    {
        return $this->where('akun_idakun', $akunId)
            ->where('tanggal', $tanggal)
            ->countAllResults() > 0;
    }

    public function simpan(int $akunId, string $tanggal, string $shift, ?string $keterangan, int $oleh, ?int $unitId = null): bool
    {
        $sekarang = date('Y-m-d H:i:s');
        $baris    = $this->getByAkunTanggal($akunId, $tanggal);
        $data     = [
            'shift'      => $shift,
            'keterangan' => $keterangan,
            'updated_by' => $oleh,
            'updated_at' => $sekarang,
        ];

        // Null = pertahankan unit yang sudah ada (mis. saat izin menimpa shift).
        if ($unitId !== null) {
            $data['unit_idunit'] = $unitId;
        }

        if ($baris) {
            return (bool) $this->update($baris->id, $data);
        }

        return (bool) $this->insert($data + [
            'akun_idakun' => $akunId,
            'tanggal'     => $tanggal,
            'created_by'  => $oleh,
            'created_at'  => $sekarang,
        ]);
    }
}
