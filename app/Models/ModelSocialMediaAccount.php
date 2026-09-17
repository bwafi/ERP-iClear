<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model Social Media Account.
 *
 * Relasi: social_media_accounts → (unit_id) → unit
 */
class ModelSocialMediaAccount extends Model
{
    protected $DBGroup = 'default';

    protected $table         = 'social_media_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'unit_id',
        'platform',
        'account_name',
        'username',
        'external_account_id',
        'profile_url',
        'provider',
        'is_active',
    ];

    /** Ambil akun aktif untuk scraping provider tertentu. */
    public function activeByProvider(string $provider, ?array $platforms = null): array
    {
        $builder = $this->where('provider', $provider)->where('is_active', 1);
        if ($platforms !== null && $platforms !== []) {
            $builder->whereIn('platform', $platforms);
        }
        return $builder->orderBy('platform', 'ASC')->orderBy('account_name', 'ASC')->findAll();
    }

    /** Ambil akun berdasar identity unik (unit_id + platform + profile_url). */
    public function findByIdentity(int $unitId, string $platform, string $profileUrl): ?object
    {
        return $this->where('unit_id', $unitId)
            ->where('platform', $platform)
            ->where('profile_url', $profileUrl)
            ->first();
    }

    /** Daftar akun + nama unit untuk UI management. */
    public function withUnit(): array
    {
        return $this->select('social_media_accounts.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = social_media_accounts.unit_id', 'left')
            ->orderBy('social_media_accounts.platform', 'ASC')
            ->orderBy('unit.NAMA_UNIT', 'ASC')
            ->findAll();
    }
}