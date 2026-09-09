<?php

namespace App\Services\Konten;

/**
 * Workflow sederhana content:
 *   DRAFT → PRODUCTION → QC → (PASS) APPROVED → PUBLISHED → COMPLETED
 *                          ↘ (REJECT) REVISION → PRODUCTION → QC
 *
 * Status overdue TIDAK disimpan sebagai status; dihitung dari deadline saat
 * ditampilkan.
 */
class ContentWorkflowService
{
    /**
     * Transisi yang diperbolehkan dari suatu status.
     */
    public const TRANSITIONS = [
        'DRAFT'      => ['PRODUCTION'],
        'PRODUCTION' => ['QC'],
        'QC'         => ['APPROVED', 'REVISION'],
        'REVISION'   => ['PRODUCTION'],
        'APPROVED'   => ['PRODUCTION', 'PUBLISHED'],
        'PUBLISHED'  => ['COMPLETED'],
        'COMPLETED'  => [],
    ];

    /**
     * @param object $content row contents (memiliki status, published_at, completed_at)
     */
    public function transition(object $content, string $to, int $actorId): array
    {
        $from = (string)($content->status ?? 'DRAFT');
        $to = strtoupper(trim($to));

        if (!in_array($to, (self::TRANSITIONS[$from] ?? []), true)) {
            return $this->error("Transisi tidak diizinkan: {$from} → {$to}.");
        }

        $now = date('Y-m-d H:i:s');
        $updated = ['status' => $to, 'updated_at' => $now];

        if ($to === 'PUBLISHED' && empty($content->published_at)) {
            $updated['published_at'] = $now;
        }
        if ($to === 'COMPLETED') {
            if (empty($content->published_at)) {
                $updated['published_at'] = $now;
            }
            $updated['completed_at'] = $now;
        }

        (new \App\Models\ModelContent())->update($content->id, $updated);

        return ['ok' => true, 'status' => $to, 'message' => "Status menjadi {$to}."];
    }

    /**
     * Act QC: PASS → APPROVED, REJECT → REVISION. Mencatat histori di content_qc.
     */
    public function qc(object $content, string $result, ?string $note, int $checkerId): array
    {
        $result = strtoupper(trim($result));
        if (!in_array($result, ['PASS', 'REJECT'], true)) {
            return $this->error('Hasil QC harus PASS atau REJECT.');
        }

        if ((string)($content->status ?? '') !== 'QC') {
            return $this->error('QC hanya bisa dilakukan saat status CONTENT = QC.');
        }

        $now = date('Y-m-d H:i:s');

        (new \App\Models\ModelContentQc())->insert([
            'content_id'  => (int)$content->id,
            'status'      => $result,
            'note'        => $note ?: null,
            'checker_id'  => $checkerId,
            'checked_at'  => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $to = $result === 'PASS' ? 'APPROVED' : 'REVISION';

        (new \App\Models\ModelContent())->update((int)$content->id, [
            'status'     => $to,
            'updated_at' => $now,
        ]);

        return [
            'ok'      => true,
            'status'  => $to,
            'message' => $result === 'PASS' ? 'QC PASS — content disetujui (APPROVED).' : 'QC REJECT — content dikembalikan ke REVISION.',
        ];
    }

    private function error(string $msg): array
    {
        return ['ok' => false, 'message' => $msg];
    }
}