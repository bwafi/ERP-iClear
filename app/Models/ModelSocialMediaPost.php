<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model Social Media Post.
 *
 * Identity post = (social_media_account_id + external_post_id), bukan URL.
 */
class ModelSocialMediaPost extends Model
{
    protected $DBGroup = 'default';

    protected $table         = 'social_media_posts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'social_media_account_id',
        'platform',
        'external_post_id',
        'post_url',
        'post_type',
        'caption',
        'published_at',
    ];

    public function findByPostIdentity(int $accountId, string $externalPostId): ?object
    {
        return $this->where('social_media_account_id', $accountId)
            ->where('external_post_id', $externalPostId)
            ->first();
    }

    /** Upsert post berdasarkan identity (account_id + external_post_id). */
    public function upsert(int $accountId, string $platform, array $post): int
    {
        $externalPostId = trim((string)($post['external_post_id'] ?? ''));
        if ($externalPostId === '') {
            throw new \InvalidArgumentException('external_post_id tidak boleh kosong.');
        }

        $existing = $this->findByPostIdentity($accountId, $externalPostId);

        $data = [
            'platform'         => $platform,
            'post_url'         => isset($post['post_url']) && $post['post_url'] !== '' ? $post['post_url'] : null,
            'post_type'        => isset($post['post_type']) && $post['post_type'] !== '' ? $post['post_type'] : null,
            'caption'          => isset($post['caption']) && $post['caption'] !== '' ? $post['caption'] : null,
            'published_at'     => isset($post['published_at']) && $post['published_at'] !== '' ? $post['published_at'] : null,
        ];

        if ($existing) {
            $this->update($existing->id, $data);
            return (int)$existing->id;
        }

        $data['social_media_account_id'] = $accountId;
        $data['external_post_id']        = $externalPostId;
        $this->insert($data);
        return (int)$this->insertID();
    }

    /**
     * Daftar post aktif + akun untuk periode tertentu.
     * Sumber KPI = posts (published_at) + snapshots terbaru.
     */
    public function findPostsInPeriod(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        $builder = $this->select('social_media_posts.*, social_media_accounts.unit_id')
            ->join('social_media_accounts', 'social_media_accounts.id = social_media_posts.social_media_account_id')
            ->where("DATE_FORMAT(social_media_posts.published_at, '%Y-%m')", sprintf('%04d-%02d', $year, $month))
            ->where('social_media_accounts.is_active', 1);

        if ($unitIds !== null && $unitIds !== []) {
            $builder->whereIn('social_media_accounts.unit_id', $unitIds);
        }
        if ($platform !== null && $platform !== '') {
            $builder->where('social_media_posts.platform', $platform);
        }

        return $builder->findAll();
    }
}