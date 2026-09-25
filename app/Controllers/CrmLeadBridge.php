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

    /**
     * Katalog penawaran untuk CRM. Endpoint ini tidak membuat booking, tidak
     * mengubah stok, dan tidak mengubah harga ERP. Harga selalu dibaca ulang
     * dari barang/stok_barang pada saat CRM meminta data.
     */
    public function priceCatalog()
    {
        $auth = $this->authorize('catalog');
        if (!is_array($auth)) return $this->error($auth, 403);
        [, $query, $unit, $item] = $auth;
        $db = db_connect();

        // Sejumlah instalasi ERP menyimpan harga cabang pada VIEW stok_barang,
        // sebagian lain memakai harga master barang. Pilih harga cabang bila
        // tersedia, lalu aman jatuh ke harga master tanpa menulis apa pun.
        $stockFields = array_map('strtolower', $db->getFieldNames('stok_barang'));
        // Hanya harga jual yang boleh dibuka ke CRM/WhatsApp. HPP atau harga
        // beli tidak pernah dipilih atau dikirim oleh bridge ini.
        $stockPrice = $this->firstField($stockFields, ['harga_penjualan', 'harga_jual', 'harga_retail', 'harga', 'price']);
        $priceSql = $stockPrice !== null ? 'COALESCE(s.' . $stockPrice . ', b.harga)' : 'b.harga';

        $builder = $db->table('barang b')
            ->select("b.idbarang,b.kode_barang,b.nama_barang,b.jenis_hp,b.harga,{$priceSql} AS harga_erp,"
                . "s.id_unit,s.nama_unit,s.stok_akhir,k.nama_kategori,sk.nama_sub_kategori", false)
            ->join('stok_barang s', 's.idbarang=b.idbarang', 'left')
            ->join('kategori k', 'k.id=b.idkategori', 'left')
            ->join('sub_kategori sk', 'sk.id=b.id_sub_kategori', 'left')
            ->where('b.deleted', 0);
        if ($unit > 0) $builder->where('s.id_unit', $unit);
        if ($item > 0) $builder->where('b.idbarang', $item);
        if ($query !== '') {
            $builder->groupStart()
                ->like('b.nama_barang', $query)
                ->orLike('b.jenis_hp', $query)
                ->orLike('k.nama_kategori', $query)
                ->orLike('sk.nama_sub_kategori', $query)
                ->groupEnd();
        }
        $rows = $builder->orderBy('b.nama_barang', 'ASC')->limit(100)->get()->getResultArray();
        $day = (int) date('j');
        $items = [];
        foreach ($rows as $row) {
            $offer = $this->offerFor($row, $day);
            // Hanya item yang bisa dikenali sebagai layanan/part servis yang
            // dikirim. Ini mencegah stok HP utuh muncul sebagai harga servis.
            if ($offer['service'] === '') continue;
            $items[] = array_merge($row, $offer);
        }
        return $this->response->setContentType('application/json')->setJSON([
            'ok' => true,
            'date' => date('Y-m-d'),
            'items' => $items,
            'read_only' => true,
        ]);
    }

    /** Status service order menurut ERP, dicari dari nomor WhatsApp customer. */
    public function serviceSummary()
    {
        $auth = $this->authorize('service');
        if (!is_array($auth)) return $this->error($auth, 403);
        [$phone] = $auth;
        $suffix = substr($phone, -10);
        $normal = "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), '+', ''), '-', ''), ' ', ''), '(', ''), 10)";
        $rows = db_connect()->table('service s')
            ->select("s.idservice,s.no_service,s.no_hp,s.tipe_hp,s.keterangan,s.status_service,s.status_proses,s.harus_dibayar,s.bayar,s.garansi_hari,s.tanggal_bisa_diambil,s.tanggal_selesai,s.created_at,s.unit_idunit, CASE s.unit_idunit WHEN 1 THEN 'Probolinggo' WHEN 2 THEN 'Jember' WHEN 3 THEN 'Banyuwangi' WHEN 4 THEN 'Pandaan' ELSE CONCAT('Unit ',COALESCE(s.unit_idunit,'')) END AS cabang", false)
            ->where(sprintf($normal, 's.no_hp') . ' =', $suffix, false)
            ->orderBy('s.created_at', 'DESC')->limit(10)->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['status_label'] = $this->serviceStatusLabel((int) ($row['status_service'] ?? 0));
            $due = max(0, (float) ($row['harus_dibayar'] ?? 0));
            $paid = max(0, (float) ($row['bayar'] ?? 0));
            $row['payment_due'] = $due;
            $row['payment_paid'] = $paid;
            $row['payment_remaining'] = max(0, $due - $paid);
            $row['payment_label'] = $due <= 0 ? 'Belum ada tagihan final' : ($paid + 0.01 >= $due ? 'Lunas' : 'Menunggu pembayaran');
        }
        return $this->response->setContentType('application/json')->setJSON(['ok' => true, 'items' => $rows, 'read_only' => true]);
    }

    private function authorize(string $type)
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
        if ($type === 'catalog') {
            $query = trim((string) ($this->request->getGet('q') ?? ''));
            $unit = (int) ($this->request->getGet('unit') ?? 0);
            $item = (int) ($this->request->getGet('item') ?? 0);
            if (mb_strlen($query) > 100 || $unit < 0 || $unit > 999999999 || $item < 0 || $item > 999999999) return 'Filter katalog tidak valid.';
            $signed = $month . "\n" . $query . "\n" . $unit . "\n" . $item . "\n" . $time;
            if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) return 'Signature bridge tidak valid.';
            return [$month, $query, $unit, $item];
        }
        if ($type === 'service') {
            $phone = preg_replace('/\D+/', '', (string) $this->request->getGet('phone'));
            if (!preg_match('/^[1-9][0-9]{6,19}$/', $phone)) return 'Nomor WhatsApp tidak valid.';
            $signed = $phone . "\n" . $month . "\n" . $time;
            if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) return 'Signature bridge tidak valid.';
            return [$phone];
        }
        $page = (int) $this->request->getGet('page');
        $status = strtoupper(trim((string) ($this->request->getGet('status') ?? 'ALL')));
        $query = trim((string) ($this->request->getGet('q') ?? ''));
        if (mb_strlen($query) > 100) return 'Pencarian terlalu panjang.';
        $signed = $month . "\n" . $page . "\n" . $status . "\n" . $query . "\n" . $time;
        if (!hash_equals(hash_hmac('sha256', $signed, $secret), $given)) return 'Signature bridge tidak valid.';
        return [$month, $page, $status, $query];
    }

    private function firstField(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) if (in_array($candidate, $fields, true)) return $candidate;
        return null;
    }

    /** Menyamakan nama ERP ke bahasa yang tampil di WhatsApp. */
    private function offerFor(array $row, int $day): array
    {
        $haystack = mb_strtolower(implode(' ', [
            $row['nama_barang'] ?? '',
            $row['jenis_hp'] ?? '',
            $row['nama_kategori'] ?? '',
            $row['nama_sub_kategori'] ?? '',
        ]), 'UTF-8');
        $service = '';
        if (str_contains($haystack, 'lcd')) $service = 'LCD';
        elseif (str_contains($haystack, 'baterai') || str_contains($haystack, 'battery')) $service = 'Baterai';
        elseif (str_contains($haystack, 'backglass') || str_contains($haystack, 'back glass')) $service = 'Backglass';
        elseif (str_contains($haystack, 'housing')) $service = 'Housing';
        elseif (str_contains($haystack, 'jasa') && str_contains($haystack, 'pasang')) $service = 'Jasa pasang';
        elseif (str_contains($haystack, 'mesin') || str_contains($haystack, 'logicboard') || str_contains($haystack, 'motherboard')) $service = 'Service mesin';
        elseif (str_contains($haystack, 'flex') || str_contains($haystack, 'sparepart') || str_contains($haystack, 'spare part')) $service = 'Sparepart';
        if ($service === '') return ['service' => ''];

        $variant = '';
        if (str_contains($haystack, 'grade aq')) $variant = 'Grade A';
        elseif (str_contains($haystack, 'grade qa')) $variant = 'Grade Ori';
        elseif (str_contains($haystack, 'original apple')) $variant = 'Original Apple';
        elseif (str_contains($haystack, 'genuine')) $variant = 'Genuine Part';
        elseif (str_contains($haystack, 'original')) $variant = 'Original';

        $normal = max(0, (float) ($row['harga_erp'] ?? 0));
        $discount = 0;
        $promo = '';
        $hideWarranty = false;
        if ($day === 21) {
            $discount = $this->hsiDiscount($service, $variant);
            if ($discount > 0) $promo = 'HSI tanggal 21';
        } elseif ($day >= 1 && $day <= 10) {
            $early = $this->earlyMonthPrice($haystack, $service, $variant);
            if ($early !== null) {
                $normal = $normal > 0 ? $normal : $early;
                $promo = 'Promo awal bulan tanggal 1–10';
                $hideWarranty = true;
            }
        }
        $offer = $normal;
        if ($day === 21 && $discount > 0) $offer = round($normal * (100 - $discount) / 100);
        if ($day >= 1 && $day <= 10 && isset($early) && $early !== null) $offer = $early;
        return [
            'service' => $service,
            'variant_display' => $variant,
            'price_normal' => $normal,
            'discount_percent' => $discount,
            'price_offer' => $offer,
            'promo_name' => $promo,
            'hide_warranty' => $hideWarranty,
            'warranty_display' => $hideWarranty ? '' : $this->warrantyFor($service, $variant),
            'estimate_display' => $this->estimateFor($service),
        ];
    }

    private function hsiDiscount(string $service, string $variant): int
    {
        $rules = [
            'LCD|Grade A' => 5,
            'LCD|Grade Ori' => 18,
            'LCD|Original' => 10,
            'LCD|Original Apple' => 10,
            'Baterai|Grade Ori' => 3,
            'Baterai|Original Apple' => 10,
            'Baterai|Genuine Part' => 17,
            'Backglass|' => 25,
            'Jasa pasang|' => 30,
            'Sparepart|' => 10,
        ];
        return $rules[$service . '|' . $variant] ?? $rules[$service . '|'] ?? 0;
    }

    private function warrantyFor(string $service, string $variant): string
    {
        $rules = [
            'LCD|Grade A' => 'Garansi 6 bulan',
            'LCD|Grade Ori' => 'Garansi 2 tahun',
            'LCD|Original Apple' => 'Garansi seumur hidup',
            'Baterai|Grade Ori' => 'Garansi 6 bulan',
            'Baterai|Original Apple' => 'Garansi 2 tahun',
            'Baterai|Genuine Part' => 'Garansi 2 tahun',
        ];
        return $rules[$service . '|' . $variant] ?? '';
    }

    private function estimateFor(string $service): string
    {
        if ($service === 'Service mesin') return 'Estimasi 5–7 hari; tergantung kerusakan dan pengiriman ke pusat Probolinggo.';
        if (in_array($service, ['Backglass', 'Housing'], true)) return 'Estimasi 2–5 jam.';
        return 'Estimasi 15–35 menit, dapat ditunggu.';
    }

    private function serviceStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Permintaan servis diterima',
            2 => 'Dalam proses perbaikan',
            3 => 'Siap diambil',
            4 => 'Selesai — sudah diambil',
            90, 91 => 'Dibatalkan',
            default => 'Status ERP: ' . $status,
        };
    }

    /** Harga poster promo awal bulan; berlaku hanya LCD Grade A, baterai Grade Ori, dan backglass. */
    private function earlyMonthPrice(string $haystack, string $service, string $variant): ?float
    {
        if (($service === 'LCD' && $variant !== 'Grade A') || ($service === 'Baterai' && $variant !== 'Grade Ori') || !in_array($service, ['LCD', 'Baterai', 'Backglass'], true)) return null;
        $prices = [
            'iphone x' => [429000, 199000, 199000],
            'iphone xr' => [399000, 199000, 199000],
            'iphone xs' => [429000, 199000, 199000],
            'iphone xs max' => [449000, 249000, 199000],
            'iphone 11' => [449000, 249000, 199000],
            'iphone 11 pro' => [499000, 349000, 249000],
            'iphone 11 pro max' => [549000, 399000, 249000],
            'iphone 12' => [599000, 299000, 299000],
            'iphone 12 mini' => [599000, 299000, 349000],
            'iphone 12 pro' => [649000, 399000, 349000],
            'iphone 12 pro max' => [699000, 399000, 349000],
            'iphone 13' => [599000, 399000, 399000],
            'iphone 13 mini' => [649000, 399000, 399000],
            'iphone 13 pro' => [949000, 449000, 399000],
            'iphone 13 pro max' => [1149000, 449000, 399000],
            'iphone 14' => [849000, 549000, 399000],
            'iphone 14+' => [899000, 549000, 399000],
            'iphone 14 pro' => [1049000, 649000, 549000],
            'iphone 14 pro max' => [1249000, 649000, 549000],
        ];
        // Model yang lebih panjang harus dicek lebih dulu supaya “iPhone 14”
        // tidak mengambil harga iPhone 14 Pro.
        uksort($prices, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($prices as $model => $row) if (str_contains($haystack, $model)) {
            return $row[$service === 'LCD' ? 0 : ($service === 'Baterai' ? 1 : 2)];
        }
        return null;
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
