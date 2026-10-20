<?php

namespace App\Services\Service;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ServiceWorkflow
{
    public const DRAFT = 'draft';
    public const QUEUED = 'queued';
    public const RETURNED = 'returned';
    public const PROCESSING = 'processing';
    public const DONE = 'done';

    public function __construct(private BaseConnection $db)
    {
    }

    public function sendToCashier(int $serviceId, int $actorId, string $recommendation): void
    {
        $this->db->transException(true);
        $this->db->transStart();
        try {
            $row = $this->locked($serviceId);
            if (! $row || ! in_array($row->queue_status, [self::DRAFT, self::RETURNED], true)
                || (int) $row->technician_idakun !== $actorId) {
                throw new RuntimeException('Service tidak dapat dikirim ulang dari status antrean saat ini.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('service_workflow')->where('service_idservice', $serviceId)->update([
                'queue_status' => self::QUEUED,
                'technician_idakun' => $actorId,
                'recommendation' => trim($recommendation),
                'submitted_at' => $now,
                'returned_at' => null,
                'return_reason' => null,
                'cashier_idakun' => null,
                'updated_at' => $now,
            ]);
            $this->event($serviceId, 'sent_to_cashier', $actorId, $now, trim($recommendation));
            if (! $this->db->transComplete()) {
                throw new RuntimeException('Gagal menyimpan serah-terima ke antrean kasir.');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function claimByCashier(int $serviceId, int $actorId): object
    {
        $this->db->transException(true);
        $this->db->transStart();
        try {
            $row = $this->locked($serviceId);
            if (! $row || ! in_array($row->queue_status, [self::QUEUED, self::PROCESSING], true)) {
                throw new RuntimeException('Service tidak sedang menunggu kasir.');
            }
            if ($row->queue_status === self::PROCESSING && (int) $row->cashier_idakun !== $actorId) {
                throw new RuntimeException('Service sedang diproses kasir lain.');
            }
            if ($row->queue_status === self::QUEUED) {
                $now = date('Y-m-d H:i:s');
                $this->db->table('service_workflow')->where('service_idservice', $serviceId)->update([
                    'queue_status' => self::PROCESSING,
                    'cashier_idakun' => $actorId,
                    'processing_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->event($serviceId, 'claimed_by_cashier', $actorId, $now, null);
            }
            if (! $this->db->transComplete()) {
                throw new RuntimeException('Gagal mengunci antrean kasir.');
            }
            return $row;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function returnToTechnician(int $serviceId, int $actorId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Alasan pengembalian wajib diisi.');
        }
        $this->db->transException(true);
        $this->db->transStart();
        try {
            $row = $this->locked($serviceId);
            if (! $row || $row->queue_status !== self::PROCESSING || (int) $row->cashier_idakun !== $actorId) {
                throw new RuntimeException('Service bukan milik antrean kasir yang sedang Anda proses.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('service_workflow')->where('service_idservice', $serviceId)->update([
                'queue_status' => self::RETURNED,
                'cashier_idakun' => null,
                'returned_at' => $now,
                'return_reason' => $reason,
                'updated_at' => $now,
            ]);
            $this->event($serviceId, 'returned_to_technician', $actorId, $now, $reason);
            if (! $this->db->transComplete()) {
                throw new RuntimeException('Gagal mengembalikan service ke teknisi.');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function markDone(int $serviceId, int $actorId): void
    {
        $this->db->transException(true);
        $this->db->transStart();
        try {
            $row = $this->locked($serviceId);
            if (! $row || $row->queue_status !== self::PROCESSING || (int) $row->cashier_idakun !== $actorId) {
                throw new RuntimeException('Service bukan milik antrean kasir yang sedang Anda proses.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('service_workflow')->where('service_idservice', $serviceId)->update([
                'queue_status' => self::DONE,
                'updated_at' => $now,
            ]);
            $this->event($serviceId, 'cashier_completed', $actorId, $now, null);
            if (! $this->db->transComplete()) {
                throw new RuntimeException('Gagal memperbarui status antrean kasir.');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function locked(int $serviceId): ?object
    {
        $sql = $this->db->table('service_workflow')->where('service_idservice', $serviceId)->getCompiledSelect();
        if (in_array(strtolower((string) $this->db->DBDriver), ['mysqli', 'postgre'], true)) {
            $sql .= ' FOR UPDATE';
        }
        return $this->db->query($sql)->getRow();
    }

    private function event(int $serviceId, string $type, int $actorId, string $now, ?string $detail): void
    {
        $this->db->table('service_workflow_event')->insert([
            'service_idservice' => $serviceId,
            'event_type' => $type,
            'actor_idakun' => $actorId,
            'occurred_at' => $now,
            'detail' => $detail,
        ]);
    }
}
