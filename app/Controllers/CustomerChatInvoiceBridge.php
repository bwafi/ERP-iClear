<?php

namespace App\Controllers;

class CustomerChatInvoiceBridge extends BaseController
{
  public function progress()
  {
    $invoice = trim((string) $this->request->getGet('invoice'));
    $time = trim((string) $this->request->getHeaderLine('X-CRM-Time'));
    $given = trim((string) $this->request->getHeaderLine('X-CRM-Signature'));
    $secret = trim((string) env('CRM_BRIDGE_SECRET', ''));
    if (
      $secret === '' || !ctype_digit($time) || abs(time() - (int) $time) > 300
      || !preg_match('/^SRV[0-9]{6,30}$/D', $invoice)
    ) {
      return $this->result(['ok' => false, 'error' => 'Permintaan tidak valid.'], 403);
    }
    $signed = "customer-chat-invoice-progress-v1\n" . $invoice . "\n" . $time;
    if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) {
      return $this->result(['ok' => false, 'error' => 'Permintaan tidak valid.'], 403);
    }

    // Select only fields already used by the public ERP progress page.
    $rows = db_connect()->table('service')
      ->select('no_service,status_service,created_at,tanggal_bisa_diambil,tanggal_selesai')
      ->where('no_service', $invoice)->limit(2)->get()->getResultArray();
    if (count($rows) > 1) {
      return $this->result(['ok' => false, 'error' => 'Nomor servis belum bisa diverifikasi.'], 409);
    }
    if (count($rows) === 0) {
      return $this->result(['ok' => true, 'read_only' => true, 'found' => false, 'item' => null]);
    }
    $row = $rows[0];
    if ($row['no_service'] !== $invoice) {
      return $this->result(['ok' => false, 'error' => 'Nomor servis belum bisa diverifikasi.'], 409);
    }
    $labels = [
      1 => 'Permintaan servis diterima',
      2 => 'Dalam proses perbaikan',
      3 => 'Siap diambil',
      4 => 'Selesai - sudah diambil',
      90 => 'Dibatalkan',
      91 => 'Dibatalkan'
    ];
    $row['status_service'] = (int) $row['status_service'];
    $row['status_label'] = $labels[$row['status_service']] ?? 'Status belum bisa dijelaskan; hubungi iClear.';
    return $this->result(['ok' => true, 'read_only' => true, 'found' => true, 'item' => $row]);
  }

  private function result(array $data, int $status = 200)
  {
    return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')
      ->setJSON($data);
  }
}
