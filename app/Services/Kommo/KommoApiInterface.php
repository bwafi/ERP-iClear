<?php

namespace App\Services\Kommo;

/**
 * Kontrak akses Kommo API v4 yang dibutuhkan sinkronisasi lead.
 *
 * Di-implementasikan oleh KommoApiService (akses nyata) dan dipakai
 * juga oleh fake API di test.
 */
interface KommoApiInterface
{
    /** true bila konfigurasi (subdomain + token) lengkap dan diizinkan. */
    public function isConfigured(): bool;

    /**
     * Detail lead + embedded contact id.
     *
     * @return array<string,mixed> lead dari Kommo /api/v4/leads/{id}
     * @throws KommoApiException NOT_FOUND bila 404, jangan buat fiktif.
     */
    public function getLead(int $leadId): array;

    /**
     * Daftar lead terbaru (debug/manual).
     *
     * @return list<array<string,mixed>>
     */
    public function getLeads(int $limit = 10): array;

    /**
     * Satu halaman lead (+ contact id ter-embed) untuk backfill.
     *
     * @return array{items: list<array<string,mixed>>, total: int, hasMore: bool}
     */
    public function getLeadsPage(int $page = 1, int $limit = 100): array;

    /**
     * Asal kanal percakapan (talk origin) untuk lead, dari talks contact.
     * Prioritas: talk yang tertaut lead ini (entity_id=leadId), lalu talk
     * tercatat paling awal milik contact. Mengembalikan origin baku Kommo
     * (mis. 'waba', 'instagram_business') atau null bila tidak ada talk.
     */
    public function talkOriginForLead(array $contactIds, int $leadId): ?string;

    /**
     * Detail contact (nama + nomor HP dsb).
     *
     * @return array<string,mixed> contact; [] bila tidak ditemukan (bukan error).
     */
    public function getContact(int $contactId): array;

    /** Nama user Kommo (responsible user), null bila gagal/tidak ada. */
    public function getUserName(int $userId): ?string;

    /**
     * Daftar status sebuah pipeline: [status_id => ['name'=>..., 'is_editable'=>bool]].
     *
     * @return array<int,array<string,mixed>>
     */
    public function statusInfo(int $pipelineId): array;
}