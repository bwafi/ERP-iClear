<?php

namespace App\Controllers;

/** Bridge internal ERP → CRM, khusus baca-saja. */
class CrmLeadBridge extends BaseController
{
    public function leadSummary()
    {
        $secret = trim((string) env('CRM_BRIDGE_SECRET', ''));
        $phone = preg_replace('/\D+/', '', (string) $this->request->getGet('phone'));
        $month = trim((string) $this->request->getGet('month'));
        $time = trim((string) $this->request->getHeaderLine('X-CRM-Time'));
        $given = trim((string) $this->request->getHeaderLine('X-CRM-Signature'));
        if (
            $secret === '' || !preg_match('/^[1-9][0-9]{6,19}$/', $phone)
            || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month)
            || !ctype_digit($time) || abs(time() - (int) $time) > 300
        ) {
            return $this->error('Permintaan bridge tidak valid.', 403);
        }
        $signed = $phone . "\n" . $month . "\n" . $time;
        if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) {
            return $this->error('Signature bridge tidak valid.', 403);
        }
        [$year, $mon] = array_map('intval', explode('-', $month));
        $from = sprintf('%04d-%02d-01', $year, $mon);
        $until = date('Y-m-d', strtotime($from . ' +1 month'));
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

    private function error(string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setContentType('application/json')->setJSON(['ok' => false, 'error' => $message]);
    }
}
