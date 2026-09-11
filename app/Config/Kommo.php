<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi integrasi Kommo CRM → ERP.
 *
 * Semua kredensial dibaca dari .env (prefix KOM_*), TIDAK disimpan
 * hardcode di kode. Kunci nilai (token) tidak pernah di-log.
 *
 * Variabel .env:
 *   KOM_ENABLED        = true/false — aktifkan webhook & sync.
 *   KOM_SUBDOMAIN      = subdomain akun Kommo (mis. iclear.kommo.com → 'iclear').
 *   KOM_CLIENT_ID      = client_id aplikasi OAuth2 Kommo (Integration settings).
 *   KOM_CLIENT_SECRET  = client_secret aplikasi OAuth2 Kommo.
 *   KOM_REDIRECT_URI   = redirect URI (untuk OAuth2 authorization code grant).
 *   KOM_ACCESS_TOKEN   = access token OAuth2 (untuk panggilan API v4).
 *   KOM_REFRESH_TOKEN  = refresh token — dipakai otomatis bila access token kadaluarsa (401).
 *   KOM_TIMEOUT        = timeout HTTP ke Kommo (detik, default 15).
 *   KOM_WEBHOOK_SECRET = opsional; bila diisi, webhook harus menyertakan
 *                        param/header yang sama (lihat TODO di KommoWebhook controller).
 */
class Kommo extends BaseConfig
{
    /** Aktifkan integrasi (dibaca dari env). */
    public $enabled = false;

    /** Subdomain akun Kommo. */
    public $subdomain = '';

    public $clientId = '';

    public $clientSecret = '';

    public $redirectUri = '';

    public $accessToken = '';

    public $refreshToken = '';

    /** Timeout HTTP (connect & total) dalam detik. */
    public $timeout = 15;

    /**
     * Opsional — override host API Kommo (mis. 'api-c.kommo.com').
     * Bila kosong, otomatis dibaca dari payload JWT (claim api_domain);
     * bila token bukan JWT, fallback ke https://{subdomain}.kommo.com.
     */
    public $apiHost = '';

    /** Opsional — proteksi tambahan endpoint webhook. */
    public $webhookSecret = '';

    /**
     * Field kode custom field Kommo yang memuat nomor HP contact.
     * Default mengikuti kode bawaan Kommo (PHONE).
     */
    public $phoneFieldCodes = ['PHONE', 'PHONES'];

    /** Custom field lead = Nama User (dipakai sebagai nama bila ada). */
    public $namaFieldCodes = ['NAMA_USER', 'NAME_USER'];

    /** Custom field lead = Cabang Tujuan (cabang outlet tujuan). */
    public $cabangFieldCodes = ['CABANG_TUJUAN', 'CABANG'];

    /**
     * Peta origin talk/messenger Kommo → nama marketing_source.
     * Talks adalah BUKTI kanal percakapan (IG/WA/TikTok/FB widget),
     * dipakai sebagai fallback source bila UTM custom field kosong.
     */
    public $talkOriginToSource = [
        'waba'               => 'Whatsapp',
        'whatsapp'           => 'Whatsapp',
        'whatsapp_business'  => 'Whatsapp',
        'instagram_business' => 'Instagram',
        'instagram'          => 'Instagram',
        'facebook'           => 'Facebook',
        'fb'                 => 'Facebook',
        'tiktok_kommo'       => 'Tiktok',
        'tiktok'             => 'Tiktok',
        'telegram'           => 'Telegram',
        'vk'                 => 'VK',
    ];

    /**
     * Field kode/name custom field pada LEAD yang memuat source/channel
     * (mis. UTM_SOURCE, SOURCE). Nilai dicocokkan ke marketing_source
     * (name atau code, case-insensitive).
     */
    public $sourceFieldCodes = ['SOURCE', 'UTM_SOURCE', 'SRC', 'CHANNEL'];

    /**
     * Field yang menandakan lead berbayar/referral ('ADS'/'ORGANIC'),
     * dipakai untuk admin_organic marketing_lead.
     */
    public $paidFieldCodes = ['ADS_ORGANIC', 'UTM_MEDIUM', 'LEAD_TYPE'];

    /**
     * status_id Kommo yang berarti WON. Bisa array flat (berlaku semua pipeline)
     * ATAU array keyed per pipeline: [pipelineId => [statusId, ...]].
     *
     * Default mengikuti pipeline utama bawaan Kommo:
     *   142 = "Penjualan berhasil", 143 = "Penjualan gagal".
     */
    public $wonStatusIds = [142];

    /** Sama seperti wonStatusIds, untuk LOST. */
    public $lostStatusIds = [143];

    public function __construct()
    {
        parent::__construct();

        $this->enabled       = (bool)filter_var($this->env('KOM_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        $this->subdomain     = (string)$this->env('KOM_SUBDOMAIN', '');
        $this->clientId      = (string)$this->env('KOM_CLIENT_ID', '');
        $this->clientSecret  = (string)$this->env('KOM_CLIENT_SECRET', '');
        $this->redirectUri   = (string)$this->env('KOM_REDIRECT_URI', '');
        $this->accessToken   = (string)$this->env('KOM_ACCESS_TOKEN', '');
        $this->refreshToken  = (string)$this->env('KOM_REFRESH_TOKEN', '');
        $this->timeout       = (int)$this->env('KOM_TIMEOUT', '15');
        $this->webhookSecret = (string)$this->env('KOM_WEBHOOK_SECRET', '');
        $this->apiHost       = (string)$this->env('KOM_API_HOST', '');
    }

    private function env(string $key, string $default = '')
    {
        $value = getenv($key);
        return $value === false || $value === null || $value === '' ? $default : $value;
    }
}