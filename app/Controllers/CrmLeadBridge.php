<?php

namespace App\Controllers;

/** Bridge internal ERP → CRM, khusus baca-saja. */
class CrmLeadBridge extends BaseController
{
    public function leadSummary()
    {
        $auth = $this->authorize('summary');
        if (!is_array($auth)) return $this->error($auth, 403);
        [$month] = $auth;
        $phone = preg_replace('/\D+/', '', (string) $this->request->getGet('phone'));
        if (!preg_match('/^[1-9][0-9]{6,19}$/', $phone)) {
            return $this->error('Permintaan bridge tidak valid.', 403);
        }
        [$from, $until] = $this->monthRange($month);
        // Filter tanggal memakai index marketing_lead.tanggal. Normalisasi
        // nomor dikerjakan setelah kandidat satu bulan tersebut dibatasi.
        $suffix = substr($phone, -10);
        $normal = "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '+', ''), '-', ''), ' ', ''), '(', ''), 10)";
        $rows = db_connect()->table('marketing_lead ml')
            ->select('ml.id,ml.tanggal,ml.nama,ml.platform,ml.tipe,ml.ads_organic,ml.status,ml.tanggal_booking,ml.omset,ml.service_id,ml.cabang,ml.unit_id,ml.keterangan')
            ->where('ml.tanggal >=', $from)->where('ml.tanggal <', $until)
            ->groupStart()
            ->where(sprintf($normal, 'ml.no_telp_wa') . ' =', $suffix, false)
            ->orWhere(sprintf($normal, 'ml.no_hp') . ' =', $suffix, false)
            ->groupEnd()->orderBy('ml.tanggal', 'DESC')->orderBy('ml.id', 'DESC')->limit(10)->get()->getResultArray();
        return $this->response->setContentType('application/json')->setJSON(['ok' => true, 'month' => $month, 'items' => $rows]);
    }

    /** Daftar ringkas untuk halaman Data ERP CRM. Hanya baca, maksimum 50 baris. */
    public function leadList()
    {
        $auth = $this->authorize('list');
        if (!is_array($auth)) return $this->error($auth, 403);
        [$month, $page, $status, $query] = $auth;
        [$from, $until] = $this->monthRange($month);
        $allowed = ['ALL', 'PROSPEK', 'CLOSING', 'BATAL'];
        if (!in_array($status, $allowed, true)) return $this->error('Filter status tidak valid.', 422);
        $page = max(1, min(1000, $page));
        $limit = 50;

        $build = static function () use ($from, $until, $status, $query) {
            $b = db_connect()->table('marketing_lead ml')->where('ml.tanggal >=', $from)->where('ml.tanggal <', $until);
            if ($status !== 'ALL') $b->where('ml.status', $status);
            if ($query !== '') $b->groupStart()->like('ml.nama', $query)->orLike('ml.no_telp_wa', $query)->orLike('ml.no_hp', $query)->groupEnd();
            return $b;
        };
        $items = $build()->select('ml.id,ml.tanggal,ml.nama,ml.no_telp_wa,ml.no_hp,ml.platform,ml.tipe,ml.ads_organic,ml.status,ml.tanggal_booking,ml.omset,ml.service_id,ml.cabang,ml.unit_id,ml.keterangan')
            ->orderBy('ml.tanggal', 'DESC')->orderBy('ml.id', 'DESC')->limit($limit + 1, ($page - 1) * $limit)->get()->getResultArray();
        $hasMore = count($items) > $limit;
        if ($hasMore) array_pop($items);

        $summary = db_connect()->table('marketing_lead ml')->select("COUNT(*) AS leads, SUM(ml.status='CLOSING') AS closings, COALESCE(SUM(CASE WHEN ml.status='CLOSING' THEN COALESCE(ml.omset,0) ELSE 0 END),0) AS omzet", false)
            ->where('ml.tanggal >=', $from)->where('ml.tanggal <', $until)->get()->getRowArray();
        $branches = db_connect()->table('marketing_lead ml')->select("COALESCE(NULLIF(ml.cabang,''),'Belum diisi') AS cabang, COUNT(*) AS leads, SUM(ml.status='CLOSING') AS closings, COALESCE(SUM(CASE WHEN ml.status='CLOSING' THEN COALESCE(ml.omset,0) ELSE 0 END),0) AS omzet", false)
            ->where('ml.tanggal >=', $from)->where('ml.tanggal <', $until)->groupBy("COALESCE(NULLIF(ml.cabang,''),'Belum diisi')", false)->orderBy('omzet', 'DESC')->limit(20)->get()->getResultArray();
        return $this->response->setContentType('application/json')->setJSON(['ok' => true, 'month' => $month, 'page' => $page, 'has_more' => $hasMore, 'items' => $items, 'summary' => $summary, 'branches' => $branches]);
    }

    private function authorize(string $type): array
    {
        $secret = trim((string) env('CRM_BRIDGE_SECRET', ''));
        $month = trim((string) $this->request->getGet('month'));
        $time = trim((string) $this->request->getHeaderLine('X-CRM-Time'));
        $given = trim((string) $this->request->getHeaderLine('X-CRM-Signature'));
        if ($secret === '' || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month) || !ctype_digit($time) || abs(time() - (int) $time) > 300) {
            return 'Permintaan bridge tidak valid.';
        }
        if ($type === 'summary') {
            $phone = preg_replace('/\D+/', '', (string) $this->request->getGet('phone'));
            $signed = $phone . "\n" . $month . "\n" . $time;
            if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) return 'Signature bridge tidak valid.';
            return [$month];
        }
        $page = (int) $this->request->getGet('page');
        $status = strtoupper(trim((string) ($this->request->getGet('status') ?? 'ALL')));
        $query = trim((string) ($this->request->getGet('q') ?? ''));
        if (mb_strlen($query) > 100) return 'Pencarian terlalu panjang.';
        $signed = $month . "\n" . $page . "\n" . $status . "\n" . $query . "\n" . $time;
        if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) return 'Signature bridge tidak valid.';
        return [$month, $page, $status, $query];
    }

    private function monthRange(string $month): array
    {
        [$year, $mon] = array_map('intval', explode('-', $month));
        $from = sprintf('%04d-%02d-01', $year, $mon);
        return [$from, date('Y-m-d', strtotime($from . ' +1 month'))];
    }

    private function error(string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setContentType('application/json')->setJSON(['ok' => false, 'error' => $message]);
    }
}
