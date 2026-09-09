<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Content — 1 baris = 1 content (pekerjaan/karya).
 */
class ModelContent extends Model
{
    protected $table = 'contents';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'judul', 'deskripsi', 'content_type_id', 'jenis_konten', 'target_scope', 'deadline',
        'status', 'published_at', 'completed_at', 'performance_metric_id',
        'performance_target', 'created_by',
    ];

    public const STATUSES = ['DRAFT', 'PRODUCTION', 'QC', 'APPROVED', 'PUBLISHED', 'COMPLETED', 'REVISION'];

    public function getById($id)
    {
        return $this->select('
                contents.*,
                content_types.name AS content_type_name,
                performance_metrics.name AS performance_metric_name
            ')
            ->join('content_types', 'content_types.id = contents.content_type_id', 'left')
            ->join('performance_metrics', 'performance_metrics.id = contents.performance_metric_id', 'left')
            ->where('contents.id', $id)
            ->first();
    }

    /**
     * Server-side processing untuk DataTables daftar konten.
     *
     * $filters mendukung: periode('Y-m'), unit, multimedia, talent, status,
     * platform, content_type, search, dan scope_sql (fragment WHERE mentah).
     */
    public function getContentsDT(int $limit, int $offset, array $filters = [], string $orderCol = 'c.deadline', string $orderDir = 'DESC')
    {
        $builder = $this->contentTableBuilder($filters);

        $allowedOrder = [
            'c.judul', 'content_types.name', 'c.deadline', 'c.status', 'c.target_scope', 'c.created_at',
        ];
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'c.deadline';
        }
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $rows = $builder
            ->orderBy($orderCol, $orderDir)
            ->limit($limit, $offset)
            ->get()
            ->getResult();

        foreach ($rows as &$row) {
            $row->talent_names = $this->peopleNames((int)$row->id, 'TALENT');
            $row->creative_names = $this->peopleNames((int)$row->id, 'CREATIVE');
        }

        return $rows;
    }

    public function countContentsDT(array $filters = [])
    {
        return $this->contentTableBuilder($filters)->countAllResults(false);
    }

    private function contentTableBuilder(array $filters = [])
    {
        $builder = $this->db->table('contents c')
            ->select('
                c.id, c.judul, c.deskripsi, c.content_type_id, c.jenis_konten, c.target_scope,
                c.deadline, c.status, c.published_at, c.completed_at,
                c.created_by, c.created_at,
                content_types.name AS content_type_name
            ')
            ->join('content_types', 'content_types.id = c.content_type_id', 'left');

        if (!empty($filters['search'])) {
            $builder->groupStart()
                ->like('c.judul', $filters['search'])
                ->orLike('c.deskripsi', $filters['search'])
                ->groupEnd();
        }

        if (!empty($filters['periode'])) {
            // Periode bisa berupa tanggal persis (YYYY-MM-DD) atau bulan (YYYY-MM).
            if (strlen((string)$filters['periode']) === 10) {
                $builder->where('c.deadline', $filters['periode']);
            } else {
                $builder->where("DATE_FORMAT(c.deadline, '%Y-%m') =", $filters['periode']);
            }
        }

        if (!empty($filters['status'])) {
            $builder->where('c.status', $filters['status']);
        }

        if (!empty($filters['content_type'])) {
            $builder->where('c.content_type_id', (int)$filters['content_type']);
        }

        // Platform: konten yang memiliki publikasi di platform tsb.
        if (!empty($filters['platform'])) {
            $builder->where(
                "EXISTS(SELECT 1 FROM publications p WHERE p.content_id = c.id AND p.platform_id = " . (int)$filters['platform'] . ")",
                null,
                false
            );
        }

        // Unit: target (ALL atau target unit terpilih) ATAU publikasi di unit tsb.
        if (!empty($filters['unit'])) {
            $uid = (int)$filters['unit'];
            $builder->groupStart()
                ->where('c.target_scope', 'ALL')
                ->orWhere("EXISTS(SELECT 1 FROM content_units cu WHERE cu.content_id = c.id AND cu.unit_id = {$uid})", null, false)
                ->orWhere("EXISTS(SELECT 1 FROM publications pp WHERE pp.content_id = c.id AND pp.unit_id = {$uid})", null, false)
                ->groupEnd();
        }

        // Multimedia (creative) / talent.
        if (!empty($filters['multimedia'])) {
            $aid = (int)$filters['multimedia'];
            $builder->where("EXISTS(SELECT 1 FROM content_people cp WHERE cp.content_id = c.id AND cp.akun_id = {$aid} AND cp.role = 'CREATIVE')", null, false);
        }
        if (!empty($filters['talent'])) {
            $aid = (int)$filters['talent'];
            $builder->where("EXISTS(SELECT 1 FROM content_people cp WHERE cp.content_id = c.id AND cp.akun_id = {$aid} AND cp.role = 'TALENT')", null, false);
        }

        // Scope akses user (fragment SQL mentah).
        if (!empty($filters['scope_sql'])) {
            $builder->where($filters['scope_sql'], null, false);
        }

        return $builder;
    }

    public function peopleNames(int $contentId, string $role): string
    {
        $rows = $this->db->table('content_people')
            ->select('akun.NAMA_AKUN')
            ->join('akun', 'akun.ID_AKUN = content_people.akun_id', 'left')
            ->where('content_people.content_id', $contentId)
            ->where('content_people.role', $role)
            ->orderBy('akun.NAMA_AKUN', 'ASC')
            ->get()
            ->getResult();

        $names = array_map(fn($r) => $r->NAMA_AKUN, $rows);

        return implode(', ', $names);
    }

    public function targetUnits(int $contentId): array
    {
        $rows = $this->db->table('content_units')
            ->select('content_units.unit_id, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = content_units.unit_id', 'left')
            ->where('content_units.content_id', $contentId)
            ->orderBy('unit.idunit', 'ASC')
            ->get()
            ->getResult();

        return array_map(fn($r) => (object)['unit_id' => (int)$r->unit_id, 'nama_unit' => $r->NAMA_UNIT], $rows);
    }

    public function people(int $contentId): array
    {
        $rows = $this->db->table('content_people')
            ->select('content_people.akun_id, content_people.role, akun.NAMA_AKUN, akun.ID_JABATAN')
            ->join('akun', 'akun.ID_AKUN = content_people.akun_id', 'left')
            ->where('content_people.content_id', $contentId)
            ->orderBy('content_people.role', 'ASC')
            ->get()
            ->getResult();

        return $rows;
    }

    public function qcHistory(int $contentId): array
    {
        return $this->db->table('content_qc')
            ->select('content_qc.*, akun.NAMA_AKUN AS checker_name')
            ->join('akun', 'akun.ID_AKUN = content_qc.checker_id', 'left')
            ->where('content_qc.content_id', $contentId)
            ->orderBy('content_qc.checked_at', 'DESC')
            ->get()
            ->getResult();
    }

    public function checklist(int $contentId): array
    {
        return $this->db->table('content_checklists')
            ->select('content_checklists.*, brand_checklist_items.name AS item_name, brand_checklist_items.code AS item_code, akun.NAMA_AKUN AS checked_by_name')
            ->join('brand_checklist_items', 'brand_checklist_items.id = content_checklists.item_id', 'left')
            ->join('akun', 'akun.ID_AKUN = content_checklists.checked_by', 'left')
            ->where('content_checklists.content_id', $contentId)
            ->orderBy('brand_checklist_items.id', 'ASC')
            ->get()
            ->getResult();
    }

    public function publications(int $contentId): array
    {
        return $this->db->table('publications')
            ->select('
                publications.*,
                unit.NAMA_UNIT,
                platforms.name AS platform_name,
                platforms.code AS platform_code
            ')
            ->join('unit', 'unit.idunit = publications.unit_id', 'left')
            ->join('platforms', 'platforms.id = publications.platform_id', 'left')
            ->where('publications.content_id', $contentId)
            ->orderBy('publications.id', 'ASC')
            ->get()
            ->getResult();
    }

    public function publicationPerformance(int $publicationId): array
    {
        return $this->db->table('publication_performance')
            ->select('publication_performance.*, performance_metrics.name AS metric_name, performance_metrics.code AS metric_code')
            ->join('performance_metrics', 'performance_metrics.id = publication_performance.metric_id', 'left')
            ->where('publication_performance.publication_id', $publicationId)
            ->orderBy('publication_performance.period_year', 'ASC')
            ->orderBy('publication_performance.period_month', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Daftar akun aktif (untuk pilihan talent/creative).
     */
    public function activePeople(): array
    {
        return $this->peopleQuery()->get()->getResult();
    }

    /**
     * Hanya akun dengan jabatan Multimedia/Creative (ID_JABATAN = 44).
     */
    public function multimediaPeople(): array
    {
        return $this->peopleQuery()
            ->where('akun.ID_JABATAN', 44)
            ->get()
            ->getResult();
    }

    private function peopleQuery()
    {
        return $this->db->table('akun')
            ->select('akun.ID_AKUN, akun.NAMA_AKUN, akun.ID_JABATAN, jabatan.NAMA_JABATAN')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->where('akun.STATUS_PEGAWAI', 1)
            ->where('akun.deleted', null)
            ->orderBy('akun.NAMA_AKUN', 'ASC');
    }
}