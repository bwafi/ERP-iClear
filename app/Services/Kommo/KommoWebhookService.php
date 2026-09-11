<?php

namespace App\Services\Kommo;

use App\Config\Kommo as KommoConfig;
use App\Models\ModelMarketingLead;
use App\Models\ModelMarketingSource;

/**
 * Pemroses webhook Kommo → tabel marketing_lead yang EXISTING.
 *
 * Prinsip:
 *  - Webhook hanya TRIGGER. Data otoritatif selalu diambil dari Kommo API
 *    (payload webhook dianggap tidak lengkap). Tidak ada lead yang di-INSERT
 *    berdasar id semata tanpa detail API.
 *  - Idempotent: unique index kommo_lead_id + upsert (cari lalu insert/update).
 *  - Tidak pernah membuat Customer / Conversion / Omzet fiktif — logika
 *    Lead→Customer/Won tetap milik Marketing existing (marketing/leads/status).
 *  - Google Sheet TIDAK dipanggil dari sini (backup terpisah).
 *
 * Status internal: NEW / FOLLOW_UP / WON / LOST.
 * Pemetaan status_id Kommo → internal dibuat via Config\Kommo::won/lostStatusIds
 * + heuristik nama status non-editable (closed). Lihat TODO di kelas ini.
 */
class KommoWebhookService
{
    private KommoConfig $config;

    private KommoApiInterface $api;

    private ModelMarketingLead $leadModel;

    private ModelMarketingSource $sourceModel;

    public function __construct(
        ?KommoApiInterface $api = null,
        ?ModelMarketingLead $leadModel = null,
        ?ModelMarketingSource $sourceModel = null
    ) {
        $this->config      = new KommoConfig();
        $this->api         = $api ?? new KommoApiService();
        $this->leadModel   = $leadModel ?? new ModelMarketingLead();
        $this->sourceModel = $sourceModel ?? new ModelMarketingSource();
    }

    /**
     * Proses seluruh event dalam satu payload webhook.
     *
     * @return array{ok: bool, events: int, processed: list<array<string,mixed>>, errors: list<array<string,mixed>>}
     */
    public function processWebhook(array $payload): array
    {
        $ok        = true;
        $processed = [];
        $errors    = [];

        $events = $this->normalizeEvents($payload);
        if (empty($events)) {
            log_message('error', '[Kommo] Webhook tanpa event lead yang dikenali.');
            return ['ok' => false, 'events' => 0, 'processed' => [], 'errors' => [['event' => 'unknown', 'error' => 'payload tanpa event lead valid']]];
        }

        foreach ($events as $event) {
            try {
                $processed[] = $this->processEvent($event);
            } catch (KommoApiException $e) {
                $ok = false;
                $errors[] = [
                    'event'  => $event['event'],
                    'lead'   => $event['lead_id'],
                    'error'  => $e->getCode() === KommoApiException::NOT_FOUND ? 'lead_tidak_ditemukan' : 'kommo_api_error',
                    'detail' => 'kode ' . $e->getCode(),
                ];
                log_message('error', '[Kommo] Event ' . $event['event'] . ' lead #' . $event['lead_id'] . ' gagal: kode ' . $e->getCode());
            } catch (\Throwable $e) {
                $ok = false;
                $errors[] = [
                    'event' => $event['event'],
                    'lead'  => $event['lead_id'],
                    'error' => 'database_error',
                    'detail' => $e->getMessage(),
                ];
                log_message('error', '[Kommo] Event ' . $event['event'] . ' lead #' . $event['lead_id'] . ' GAGAL database: ' . $e->getMessage());
            }
        }

        return [
            'ok'        => $ok && empty($errors),
            'events'    => count($events),
            'processed' => $processed,
            'errors'    => $errors,
        ];
    }

    /**
     * Ubah beragam format webhook Kommo ke daftar event internal.
     *  - format JSON baru ("custom webhook"): [event, data.object_id, ...]
     *  - format lama (form-urlencoded):     [leads][add/update/status/restore/delete][N][id]
     *
     * @return list<array{event: string, lead_id: int, extra?: array<string,mixed>}>
     */
    public function normalizeEvents(array $payload): array
    {
        $events = [];

        // Format JSON (payload['event'] = 'leads.add' | 'leads.update' | 'leads.status' | 'leads.restore' | ...).
        if (is_string($payload['event'] ?? null)) {
            $rawEvent = strtolower(trim((string)$payload['event']));
            $leadId   = (int)($payload['data']['object_id'] ?? 0);
            if ($leadId === 0) {
                $properties = $payload['properties'] ?? (is_array($payload['data'] ?? null) ? $payload['data'] : []);
                $leadId = (int)($properties['id'] ?? 0);
            }
            if ($leadId > 0 && str_starts_with($rawEvent, 'leads.')) {
                $map = ['add' => 'add', 'update' => 'update', 'status' => 'status', 'restore' => 'restore', 'delete' => 'delete'];
                $ev = $map[substr($rawEvent, 6)] ?? $rawEvent;
                if (in_array($ev, ['add', 'update', 'status', 'restore', 'delete'], true)) {
                    $events[] = ['event' => $ev, 'lead_id' => $leadId];
                }
            }
            return $events;
        }

        // Format lama: payload['leads']['add'][0]['id'] dst.
        $groups = $payload['leads'] ?? [];
        if (is_array($groups)) {
            // Kadang 'leads' berisi list polos (satu set) — key 'id' langsung.
            if (isset($groups['id'])) {
                $groups = ['add' => [$groups]];
            }
            foreach (['add', 'update', 'status', 'restore', 'delete'] as $ev) {
                $items = $groups[$ev] ?? [];
                if (!is_array($items)) {
                    $items = [];
                }
                if (isset($items['id'])) { // satu elemen polos
                    $items = [$items];
                }
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $leadId = (int)($item['id'] ?? 0);
                    if ($leadId <= 0) {
                        continue;
                    }
                    $extra = [];
                    if ($ev === 'status') {
                        $extra = [
                            'status_id'      => (int)($item['status_id'] ?? 0),
                            'old_status_id'  => (int)($item['old_status_id'] ?? 0),
                            'pipeline_id'    => (int)($item['pipeline_id'] ?? 0),
                            'account_id'     => (int)($payload['account']['id'] ?? 0),
                        ];
                    }
                    $events[] = ['event' => $ev, 'lead_id' => $leadId, 'extra' => $extra];
                }
            }
        }

        return $events;
    }

    /**
     * Sinkronisasi Satu lead (backfill / cron / webhook manual).
     * Tersedia publik agar script backfill & tooling lain dapat reuse
     * alur yang sama persis dengan webhook (API → map → upsert).
     */
    public function syncLead(int $leadId, string $event = 'update'): array
    {
        return $this->processEvent(['event' => $event, 'lead_id' => $leadId]);
    }

    /**
     * Proses satu event: tarik detail dari API lalu upsert (add/update/status/restore),
     * atau soft-delete (delete).
     */
    private function processEvent(array $event): array
    {
        $leadId = (int)$event['lead_id'];

        if ($event['event'] === 'delete') {
            $this->markDeleted($leadId);
            return ['lead' => $leadId, 'action' => 'deleted'];
        }

        // add / update / status / restore: detail otoritatif dari API.
        $lead = $this->api->getLead($leadId);

        if (!empty($lead['is_unsorted'])) {
            log_message('info', '[Kommo] Lead #' . $leadId . ' masih unsorted — dilewati.');
            return ['lead' => $leadId, 'action' => 'skipped_unsorted'];
        }

        $mapped = $this->mapLead($lead);
        $result = $this->upsertLead($mapped);

        // Restore: pastikan kolom deleted dibersihkan.
        if ($event['event'] === 'restore' && $result['action'] === 'updated') {
            $this->leadModel->update((int)$result['id'], ['kommo_deleted_at' => null]);
        }

        return $result;
    }

    /**
     * Mapping data Kommo → struktur marketing_lead existing.
     *
     * @param array<string,mixed> $lead payload GET /api/v4/leads/{id}?with=contacts
     * @return array<string,mixed> kolom untuk ModelMarketingLead (allowedFields).
     */
    public function mapLead(array $lead): array
    {
        $id         = (int)($lead['id'] ?? 0);
        $name       = trim((string)($lead['name'] ?? ''));
        $createdAt  = (int)($lead['created_at'] ?? 0);
        $updatedAt  = (int)($lead['updated_at'] ?? 0);
        $statusId   = (int)($lead['status_id'] ?? 0);
        $pipelineId = (int)($lead['pipeline_id'] ?? 0);
        $responsible = (int)($lead['responsible_user_id'] ?? 0);
        $accountId  = (int)($lead['account_id'] ?? 0);

        // Contact utama (non-fiktif: hanya info kontak yang BENAR-BENAR ada).
        $contactId = $this->firstContactId($lead['_embedded']['contacts'] ?? []);
        $contact   = $contactId> 0 ? $this->api->getContact($contactId) : [];
        $contactName = $this->contactName($contact);
        $phone = $this->phoneFromContact($contact);

        $cs = $responsible > 0 ? $this->api->getUserName($responsible) : null;

        $leadFields   = $lead['custom_fields_values'] ?? [];
        $nameFromUser = $this->findCustomField($leadFields, $this->config->namaFieldCodes);
        if ($nameFromUser !== null && trim($nameFromUser) !== '') {
            // Pakai "Nama User" dan jangan hilangkan jejak lead asalnya.
            $name = trim($nameFromUser) . ' (#' . $id . ')';
        } elseif ($name === '') {
            $name = $contactName;
        }

        $price   = (int)($lead['price'] ?? 0);
        $sourceId   = $this->resolveSourceId($this->findCustomField($leadFields, $this->config->sourceFieldCodes));
        $adsOrganic = $this->normalizeAdsOrganic($this->findCustomField($leadFields, $this->config->paidFieldCodes));
        $cabang     = $this->findCustomField($leadFields, $this->config->cabangFieldCodes);

        // Fallback source dari bukti kanal percakapan Kommo (Talks).
        // UTM kosong di akun ini (cek sesi audit) → source diturunkan dari
        // origin talk (waba/instagram_business/dll) bila tersedia.
        if ($sourceId === null && $contactId > 0) {
            $origin = $this->api->talkOriginForLead([$contactId], $id);
            if ($origin !== null) {
                $sourceName = $this->config->talkOriginToSource[$origin]
                    ?? (preg_match('/^[a-z0-9_]+$/i', $origin) ? $origin : null);
                if ($sourceName !== null) {
                    $sourceId = $this->resolveSourceId($sourceName);
                    if ($sourceId !== null) {
                        log_message('info', '[Kommo] lead #' . $id . ' source dari talk origin "' . $origin . '".');
                    }
                }
            }
        }

        $mapped = [
            'kommo_lead_id'     => $id > 0 ? $id : null,
            'kommo_account_id'  => $accountId > 0 ? $accountId : null,
            'kommo_pipeline_id' => $pipelineId > 0 ? $pipelineId : null,
            'kommo_status_id'   => $statusId > 0 ? $statusId : null,
            'kommo_updated_at'  => $updatedAt > 0 ? $updatedAt : null,
            'nama'              => $name !== '' ? $name : null,
            'no_hp'             => $phone !== '' ? $phone : null,
            'cs'                => $cs ?? null,
            'price'             => $price > 0 ? $price : null,
            'cabang'            => $cabang !== null && trim($cabang) !== '' ? trim($cabang) : null,
            'ads_organic'       => $adsOrganic,
            'source_id'         => $sourceId,
        ];

        // Tanggal dibuat-lead → tanggal (dipakai KPI bulanan).
        if ($createdAt > 0) {
            $mapped['tanggal'] = date('Y-m-d', $createdAt);
        }

        // Status hanya diisi bila dapat ditentukan secara pasti; bila tidak
        // (mis. status open di luar heuristik), kolom TIDAK diset agar nilai
        // existing (manual) tidak tertimpa.
        $internalStatus = $this->statusIdToInternal($statusId, $pipelineId);
        if ($internalStatus !== null) {
            $mapped['status'] = $internalStatus;
        }

        return $mapped;
    }

    /**
     * Upsert idempotent berdasar kommo_lead_id. Perlindungan tambahan berupa
     * advisory lock (MySQL GET_LOCK) agar dua webhook bersamaan tidak
     * membuat duplikat (safety di atas unique index).
     */
    public function upsertLead(array $mapped): array
    {
        $leadId = (int)($mapped['kommo_lead_id'] ?? 0);
        if ($leadId <= 0) {
            throw new \RuntimeException('kommo_lead_id tidak valid untuk upsert.');
        }

        $db = \Config\Database::connect();
        $locked = (bool)$db->query('SELECT GET_LOCK(?, 5) AS l', ['kommo_lead_' . $leadId])->getRow()->l;
        try {
            $existing = $this->leadModel->where('kommo_lead_id', $leadId)->first();

            // Default hanya untuk INSERT (lead baru).
            if (!$existing) {
                $mapped['status']     = $mapped['status'] ?? 'NEW';
                $mapped['ads_organic'] = $mapped['ads_organic'] ?? 'ORGANIC';
                $mapped['tanggal']     = $mapped['tanggal'] ?? date('Y-m-d');
                $insertId = $this->leadModel->insert($mapped);
                if (!$insertId) {
                    throw new \RuntimeException('insert marketing_lead gagal.');
                }
                return ['lead' => $leadId, 'action' => 'created', 'id' => (int)$insertId];
            }

            // UPDATE: jangan timpa kolom yang nilainya null — Kommo tidak tahu
            // (source/ads_manual/null spesifik ERP). Hanya ubah data yang
            // benar-benar tersedia dari Kommo (prinsip: DB cerminkan Kommo,
            // nilai dikelola user di ERP jangan dihapus).
            $patch = array_filter($mapped, static fn($v) => $v !== null);

            $this->leadModel->update((int)$existing->id, $patch);
            return ['lead' => $leadId, 'action' => 'updated', 'id' => (int)$existing->id];
        } finally {
            if ($locked) {
                $db->query('SELECT RELEASE_LOCK(?) AS r', ['kommo_lead_' . $leadId]);
            }
        }
    }

    /** Kommo `leads.delete`: soft-delete, baris tetap ada (non-destructive). */
    public function markDeleted(int $leadId): void
    {
        $existing = $this->leadModel->where('kommo_lead_id', $leadId)->first();
        if (!$existing) {
            log_message('info', '[Kommo] leads.delete untuk #' . $leadId . ' — baris belum ada, diabaikan.');
            return;
        }
        $this->leadModel->update((int)$existing->id, ['kommo_deleted_at' => date('Y-m-d H:i:s')]);
        log_message('info', '[Kommo] leads.delete #' . $leadId . ' → soft-delete.');
    }

    // ── Helper mapping ────────────────────────────────────────────

    private function firstContactId(array $embeddedContacts): int
    {
        foreach ($embeddedContacts as $c) {
            if (is_array($c) && (int)($c['id'] ?? 0) > 0) {
                return (int)$c['id'];
            }
        }
        return 0;
    }

    private function contactName(array $contact): string
    {
        if (!is_array($contact) || empty($contact)) {
            return '';
        }
        $name = trim((string)($contact['name'] ?? ''));
        if ($name === '') {
            $name = trim(
                (string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? '')
            );
        }
        return $name;
    }

    private function phoneFromContact(array $contact): string
    {
        $fields = $contact['custom_fields_values'] ?? [];
        $value  = $this->findCustomField(is_array($fields) ? $fields : [], $this->config->phoneFieldCodes);
        return $value === null ? '' : trim((string)$value);
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @param list<string> $codes
     */
    private function findCustomField(array $fields, array $codes): ?string
    {
        $wanted = [];
        foreach ($codes as $c) {
            $wanted[strtoupper(str_replace('-', '_', (string)$c))] = true;
        }
        foreach ($fields as $f) {
            if (!is_array($f)) {
                continue;
            }
            $code = strtoupper((string)($f['field_code'] ?? ''));
            $name = strtoupper((string)($f['field_name'] ?? ''));
            // Normalisasi separator (spasi/garis) → underscore: "Nama User"/"no-hp" → NAMA_USER.
            $code = preg_replace('/[\s\-\/]+/', '_', $code);
            $name = preg_replace('/[\s\-\/]+/', '_', $name);
            if (isset($wanted[$code]) || isset($wanted[$name])) {
                $value = $f['values'][0]['value'] ?? null;
                if ($value !== null && trim((string)$value) !== '') {
                    return (string)$value;
                }
            }
        }
        return null;
    }

    private function resolveSourceId(?string $sourceValue): ?int
    {
        if ($sourceValue === null || $sourceValue === '') {
            return null;
        }
        $v = strtolower(trim($sourceValue));
        if ($v === '') {
            return null;
        }
        // Cocokkan NAME atau CODE marketing_source (case-insensitive).
        $db  = \Config\Database::connect();
        $esc = $db->escape($v);
        $src = $this->sourceModel
            ->where("LOWER(name) = {$esc}", null, false)
            ->orWhere("LOWER(code) = {$esc}", null, false)
            ->first();
        if ($src && (int)$src->id > 0) {
            return (int)$src->id;
        }
        log_message('info', '[Kommo] Source "' . $sourceValue . '" tidak ada di marketing_source — source_id=null (TBD auto-create).');
        return null;
    }

    private function normalizeAdsOrganic(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = strtolower(trim($value));
        if (preg_match('/ads|paid|cpc|cpm|berbayar/i', $v)) {
            return 'ADS';
        }
        if (preg_match('/organic|organik|free/i', $v)) {
            return 'ORGANIC';
        }
        return null;
    }

    /**
     * status_id Kommo → status internal.
     *  - Cocokkan config won/lostStatusIds (flat OR per pipeline).
     *  - Heuristik: status non-editable (closed) berdasarkan nama.
     *  - Status open lain → 'NEW'.
     *  - Status closed tanpa nama dikenal → null (preserve, agar tidak
     *    salah menandai WON/LOST — perlu konfigurasi won/lost ids per akun).
     */
    private function statusIdToInternal(int $statusId, int $pipelineId): ?string
    {
        if ($statusId <= 0) {
            return null;
        }

        if ($this->inStatusList($this->config->wonStatusIds, $statusId, $pipelineId)) {
            return 'WON';
        }
        if ($this->inStatusList($this->config->lostStatusIds, $statusId, $pipelineId)) {
            return 'LOST';
        }

        $st = $this->api->statusInfo($pipelineId)[$statusId] ?? null;
        if ($st && ($st['is_editable'] ?? true) === false) {
            $name = strtolower((string)($st['name'] ?? ''));
            if (preg_match('/won|gewonnen|sukses|success|closed|terjual|captado|ganado/i', $name)) {
                return 'WON';
            }
            if (preg_match('/lost|verloren|gagal|failed|cancel/i', $name)) {
                return 'LOST';
            }
            log_message('warning', '[Kommo] status_id ' . $statusId . ' closed tapi nama tidak dikenali — status dipertahankan.');
            return null;
        }

        // Status open → 'FOLLOW_UP' untuk stage lanjutan ("follow up", "prospek"),
        // selainnya 'NEW'. Pemetaan detail per-akun bisa dikustom di statusIdToInternal.
        $st = $this->api->statusInfo($pipelineId)[$statusId] ?? null;
        $name = strtolower((string)($st['name'] ?? ''));
        if (preg_match('/follow\s*up|prospek/i', $name)) {
            return 'FOLLOW_UP';
        }

        // Status open → NEW (pemetaan pipeline stage → FOLLOW_UP butuh konfigurasi, lihat TODO).
        return 'NEW';
    }

    private function inStatusList(array $list, int $statusId, int $pipelineId): bool
    {
        if (empty($list)) {
            return false;
        }
        // Support format per-pipeline: [pipelineId => [...]].
        if (array_key_exists($pipelineId, $list) && is_array($list[$pipelineId])) {
            return in_array($statusId, $list[$pipelineId], true);
        }
        return in_array($statusId, $list, true);
    }
}