<?php

namespace App\Controllers;

use App\Config\Kommo as KommoConfig;
use App\Services\Kommo\KommoApiService;
use App\Services\Kommo\KommoWebhookService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Endpoint webhook Kommo CRM → ERP.
 *
 * POST /api/kommo/webhook
 *
 * - Public (tanpa filter auth): Kommo memanggil tanpa sesi browser.
 * - Memproses payload JSON baru ATAU form-urlencoded lama.
 * - Merespon JSON ringkas agar Kommo tahu webhook diproses.
 */
class KommoWebhook extends Controller
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    public function handle()
    {
        $config = new KommoConfig();

        if (!$config->enabled) {
            return $this->respond(503, ['ok' => false, 'error' => 'Kommo integration disabled (KOM_ENABLED=false di .env).']);
        }

        if (!$this->request->is('post')) {
            return $this->respond(405, ['ok' => false, 'error' => 'Method not allowed (use POST).']);
        }

        if (!$this->authorized($config)) {
            return $this->respond(401, ['ok' => false, 'error' => 'Webhook signature invalid.']);
        }

        $payload = $this->collectPayload();
        if ($payload === null) {
            return $this->respond(400, ['ok' => false, 'error' => 'Payload webhook tidak valid (bukan JSON/form).']);
        }

        $service = new KommoWebhookService(new KommoApiService());
        $result  = $service->processWebhook($payload);

        $status = $result['ok'] ? 200 : ($this->hasApiError($result) ? 502 : 200);
        return $this->respond($status, $result);
    }

    // ── Helper ────────────────────────────────────────────────────

    private function respond(int $status, $data, string $errorMessage = '')
    {
        if ($errorMessage !== '') {
            $data = ['ok' => false, 'error' => $errorMessage];
        }
        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setJSON($data);
    }

    /**
     * Proteksi opsional: bila KOM_WEBHOOK_SECRET diisi, permintaan harus
     * menyertakan header `X-Kommo-Webhook-Secret` (atau query `?secret=`)
     * dengan nilai yang sama.
     *
     * TODO: Kommo "custom webhooks" mengirim header `X-Hub-Signature`
     * (HMAC-SHA1) bila webhook diarahkan via klien webhook resmi. Verifikasi
     * HMAC menunggu kepastian format header pada akun bersangkutan.
     */
    private function authorized(KommoConfig $config): bool
    {
        if ($config->webhookSecret === '') {
            return true;
        }
        $given = (string)$this->request->getHeaderLine('X-Kommo-Webhook-Secret');
        if ($given === '') {
            $given = (string)$this->request->getGet('secret');
        }
        return hash_equals($config->webhookSecret, $given);
    }

    /** Terima JSON body atau $_POST (form lama), return null bila tak terpakai. */
    private function collectPayload(): ?array
    {
        $raw  = (string)$this->request->getBody();
        $trim = trim($raw);

        if ($trim !== '') {
            // Coba JSON dulu (format webhook baru Kommo).
            $asJson = json_decode($trim, true);
            if (is_array($asJson)) {
                return $asJson;
            }
            // Bukan JSON tapi punya body — bila form-urlencoded, $_POST terisi.
        }

        $post = $this->request->getPost();
        if (is_array($post) && count($post) > 0) {
            return $post;
        }

        return null;
    }

    private function hasApiError(array $result): bool
    {
        foreach ($result['errors'] as $e) {
            if (in_array($e['error'] ?? '', ['kommo_api_error', 'lead_tidak_ditemukan'], true)) {
                return true;
            }
        }
        return false;
    }
}