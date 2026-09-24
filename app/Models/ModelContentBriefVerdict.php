<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * content_brief_verdicts — penilaian kesesuaian brief oleh Kepala Divisi (43).
 *
 * Setiap penilaian = baris baru; verdict terbaru diambil via MAX(id).
 */
class ModelContentBriefVerdict extends Model
{
    protected $table = 'content_brief_verdicts';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'content_id', 'sesuai', 'catatan', 'penilai_id',
    ];

    /** Verdict terbaru + nama penilai, atau null bila belum dinilai. */
    public function latestForContent(int $contentId)
    {
        return $this->select('content_brief_verdicts.*, akun.NAMA_AKUN AS penilai_nama')
            ->join('akun', 'akun.ID_AKUN = content_brief_verdicts.penilai_id', 'left')
            ->where('content_brief_verdicts.content_id', $contentId)
            ->orderBy('content_brief_verdicts.id', 'DESC')
            ->first();
    }

    public function insertVerdict(int $contentId, int $sesuai, ?string $catatan, int $penilaiId): bool
    {
        return $this->insert([
            'content_id' => $contentId,
            'sesuai'     => in_array($sesuai, [0, 1], true) ? $sesuai : 0,
            'catatan'    => $catatan !== null && trim($catatan) !== '' ? trim($catatan) : null,
            'penilai_id' => $penilaiId,
        ]) !== false;
    }
}