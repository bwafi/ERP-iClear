<?php

namespace App\Controllers;

use App\Models\ModelReturBarangRusak;
use App\Models\ModelUnit;
use App\Services\Service\GaransiClaimService;
use Config\Database;

class ReturBarangRusak extends BaseController
{
    private ModelReturBarangRusak $returModel;
    private ModelUnit $unitModel;

    public function __construct()
    {
        $this->returModel = new ModelReturBarangRusak();
        $this->unitModel = new ModelUnit();
    }

    public function index()
    {
        $db = Database::connect();
        $canSeeAll = $this->canSeeAllUnits();
        $selectedUnit = $canSeeAll
            ? (int) ($this->request->getGet('unit') ?: 0)
            : (int) session('ID_UNIT');

        $awal = $this->normalizeDate($this->request->getGet('tanggal_awal')) ?: date('Y-m-01');
        $akhir = $this->normalizeDate($this->request->getGet('tanggal_akhir')) ?: date('Y-m-t');
        $unitFilter = $selectedUnit > 0 ? $selectedUnit : null;

        $klaim = $this->returModel->getKlaimBelumRetur($unitFilter, $awal, $akhir);
        $riwayat = $this->returModel->getRiwayat($unitFilter, $awal, $akhir);
        $itemsByRetur = [];
        foreach ($riwayat as $header) {
            $itemsByRetur[(int) $header->idretur_klaim] = $this->returModel->getItems((int) $header->idretur_klaim);
        }

        $supplierBuilder = $db->table('suplier')
            ->select('id_suplier, nama_suplier')
            ->where('deleted', '0');
        if ($unitFilter !== null) {
            $supplierBuilder->groupStart()
                ->where('unit_idunit', $unitFilter)
                ->orWhere('unit_idunit', null)
                ->groupEnd();
        }
        $suppliers = $supplierBuilder->orderBy('nama_suplier', 'ASC')->get()->getResult();

        $barang = $db->table('barang')
            ->select('idbarang, kode_barang, nama_barang')
            ->where('deleted', '0')
            ->orderBy('nama_barang', 'ASC')
            ->get()
            ->getResult();

        return view('template', [
            'body' => 'retur/retur_barang_rusak',
            'klaim' => $klaim,
            'riwayat' => $riwayat,
            'items_by_retur' => $itemsByRetur,
            'suppliers' => $suppliers,
            'barang' => $barang,
            'units' => $canSeeAll ? $this->unitModel->getUnit() : [],
            'can_see_all_units' => $canSeeAll,
            'selected_unit' => $selectedUnit,
            'tanggal_awal' => $awal,
            'tanggal_akhir' => $akhir,
        ]);
    }

    public function simpan()
    {
        $db = Database::connect();
        $target = base_url('retur_barang_rusak');
        $items = $this->request->getPost('items');
        $items = is_array($items) ? $items : [];
        $selected = array_values(array_filter($items, static fn ($item) => is_array($item) && !empty($item['selected'])));

        if ($selected === []) {
            return redirect()->to($target)->with('gagal', 'Pilih minimal satu barang yang diretur.');
        }

        $supplierId = (int) $this->request->getPost('suplier_id');
        $supplier = $db->table('suplier')
            ->where('id_suplier', $supplierId)
            ->where('deleted', '0')
            ->get()
            ->getRow();
        if (! $supplier) {
            return redirect()->to($target)->with('gagal', 'Supplier tidak valid.');
        }

        $tanggal = $this->normalizeDate($this->request->getPost('tanggal')) ?: date('Y-m-d');
        $catatan = trim((string) $this->request->getPost('catatan'));

        $db->transBegin();

        try {
            $validated = [];
            $unitId = null;
            $sparepartTable = $db->prefixTable('service_sparepart');
            $serviceTable = $db->prefixTable('service');

            foreach ($selected as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0) {
                    throw new \RuntimeException('Item retur tidak valid.');
                }

                $row = $db->query(
                    "SELECT sp.*, s.unit_idunit AS service_unit, s.tanggal_claim_garansi, s.no_service, s.refund_garansi_at
                       FROM {$sparepartTable} sp
                       JOIN {$serviceTable} s ON s.idservice = sp.service_idservice
                      WHERE sp.idservice_sparepart = ?
                      FOR UPDATE",
                    [$id]
                )->getRow();

                if (! $row) {
                    throw new \RuntimeException('Sparepart klaim tidak ditemukan.');
                }

                if (! empty($row->refund_garansi_at)) {
                    throw new \RuntimeException('Service sudah direfund; retur tidak dapat disimpan.');
                }

                if ($row->status_retur !== 'bisa') {
                    throw new \RuntimeException('Hanya sparepart berstatus bisa retur yang dapat diretur.');
                }

                $sudahRetur = $db->table('retur_klaim_item')
                    ->where('service_sparepart_idservice_sparepart', $id)
                    ->countAllResults();
                if ($sudahRetur > 0) {
                    throw new \RuntimeException('Sparepart klaim sudah pernah diretur.');
                }

                $rowUnit = (int) ($row->unit_idunit ?: $row->service_unit);
                if (! $this->canSeeAllUnits() && $rowUnit !== (int) session('ID_UNIT')) {
                    throw new \RuntimeException('Sparepart klaim bukan milik unit Anda.');
                }

                if ($unitId === null) {
                    $unitId = $rowUnit;
                } elseif ($unitId !== $rowUnit) {
                    throw new \RuntimeException('Satu retur hanya boleh berisi barang dari satu unit.');
                }

                $hppManual = $this->rupiahToInt($item['hpp_manual'] ?? 0);
                if ($hppManual <= 0) {
                    throw new \RuntimeException('HPP manual wajib diisi lebih dari 0.');
                }

                $hasil = (string) ($item['hasil'] ?? '');
                if (! in_array($hasil, ['barang_pengganti', 'potong_tagihan', 'uang_kembali', 'ditolak'], true)) {
                    throw new \RuntimeException('Hasil retur tidak valid.');
                }

                $qty = (float) $row->jumlah_tambahan_garansi;
                if ($qty <= 0) {
                    throw new \RuntimeException('Qty klaim tidak valid untuk diretur.');
                }
                $nilai = (int) round($hppManual * $qty);

                $penggantiId = null;
                $penggantiQty = null;
                if ($hasil === 'barang_pengganti') {
                    $penggantiId = (int) ($item['barang_pengganti_id'] ?? 0);
                    $penggantiQty = (float) ($item['jumlah_pengganti'] ?? 0);
                    if ($penggantiId <= 0 || $penggantiQty <= 0) {
                        throw new \RuntimeException('Barang pengganti dan qty pengganti wajib diisi.');
                    }

                    $barangAda = $db->table('barang')->where('idbarang', $penggantiId)->countAllResults();
                    if ($barangAda === 0) {
                        throw new \RuntimeException('Barang pengganti tidak ditemukan.');
                    }
                }

                $validated[] = [
                    'id' => $id,
                    'barang_idbarang' => (int) $row->barang_idbarang,
                    'jumlah' => $qty,
                    'hpp_manual' => $hppManual,
                    'hasil' => $hasil,
                    'barang_pengganti_idbarang' => $penggantiId,
                    'jumlah_pengganti' => $penggantiQty,
                    'nilai' => $nilai,
                    'keterangan' => trim((string) ($item['keterangan'] ?? '')),
                ];
            }

            $supplierUnit = (int) ($supplier->unit_idunit ?? 0);
            if ($supplierUnit !== 0 && $supplierUnit !== $unitId) {
                throw new \RuntimeException('Supplier bukan milik unit retur.');
            }

            $prefix = 'RTB' . $unitId . date('Ymd', strtotime($tanggal));
            $last = $db->table('retur_klaim')
                ->like('no_retur', $prefix, 'after')
                ->orderBy('no_retur', 'DESC')
                ->get()
                ->getRow();
            $sequence = $last ? ((int) substr((string) $last->no_retur, -3)) + 1 : 1;
            $noRetur = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $db->table('retur_klaim')->insert([
                'no_retur' => $noRetur,
                'tanggal' => $tanggal,
                'suplier_id_suplier' => $supplierId,
                'unit_idunit' => $unitId,
                'input_by' => session('ID_AKUN'),
                'catatan' => $catatan !== '' ? $catatan : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $headerId = (int) $db->insertID();

            foreach ($validated as $item) {
                $db->table('retur_klaim_item')->insert([
                    'retur_klaim_idretur_klaim' => $headerId,
                    'service_sparepart_idservice_sparepart' => $item['id'],
                    'barang_rusak_claim_idbarang_rusak_claim' => null,
                    'barang_idbarang' => $item['barang_idbarang'],
                    'jumlah' => $item['jumlah'],
                    'hpp_manual' => $item['hpp_manual'],
                    'hasil' => $item['hasil'],
                    'barang_pengganti_idbarang' => $item['barang_pengganti_idbarang'],
                    'jumlah_pengganti' => $item['jumlah_pengganti'],
                    'nilai' => $item['nilai'],
                    'keterangan' => $item['keterangan'] !== '' ? $item['keterangan'] : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $update = $item['hasil'] === 'ditolak'
                    ? [
                        'status_retur' => 'ditolak',
                        'hpp_omset_garansi' => $item['hpp_manual'],
                        'tanggal_hpp_klaim' => $tanggal,
                    ]
                    : [
                        'status_retur' => 'diterima',
                        'hpp_omset_garansi' => 0,
                        'tanggal_hpp_klaim' => null,
                    ];

                $db->table('service_sparepart')
                    ->where('idservice_sparepart', $item['id'])
                    ->update($update);
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($target)->with('gagal', $e->getMessage());
        }

        return redirect()->to($target)->with('sukses', 'Retur barang rusak berhasil disimpan.');
    }

    public function ubahStatus(int $id)
    {
        $target = $this->kembaliUrl();
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['bisa', 'tidak_bisa'], true)) {
            return redirect()->to($target)->with('gagal', 'Status retur tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $row = $this->lockSparepart($db, $id);
            $this->pastikanUnit($row);

            if (! empty($row->refund_garansi_at)) {
                throw new \RuntimeException('Service sudah direfund; status retur tidak dapat diubah.');
            }

            if ((int) $row->jumlah_tambahan_garansi <= 0
                || ! in_array($row->status_retur, ['bisa', 'tidak_bisa'], true)) {
                throw new \RuntimeException('Baris ini bukan klaim yang bisa diubah statusnya.');
            }

            $sudahRetur = $db->table('retur_klaim_item')
                ->where('service_sparepart_idservice_sparepart', $id)
                ->countAllResults();
            if ($sudahRetur > 0) {
                throw new \RuntimeException('Sparepart klaim sudah diretur; batalkan retur dulu.');
            }

            $update = $status === 'tidak_bisa'
                ? [
                    'status_retur' => 'tidak_bisa',
                    'hpp_omset_garansi' => (int) $row->hpp_penjualan_garansi,
                    'tanggal_hpp_klaim' => date('Y-m-d', strtotime((string) ($row->tanggal_claim_garansi ?: 'now'))),
                ]
                : [
                    'status_retur' => 'bisa',
                    'hpp_omset_garansi' => 0,
                    'tanggal_hpp_klaim' => null,
                ];

            $db->table('service_sparepart')->where('idservice_sparepart', $id)->update($update);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($target)->with('gagal', $e->getMessage());
        }

        return redirect()->to($target)->with('sukses', 'Status retur berhasil diubah.');
    }

    public function batal(int $id)
    {
        $db = Database::connect();
        $target = $this->kembaliUrl();
        $db->transBegin();

        try {
            $header = $db->query(
                'SELECT * FROM ' . $db->prefixTable('retur_klaim') . ' WHERE idretur_klaim = ? FOR UPDATE',
                [$id]
            )->getRow();

            if (! $header) {
                throw new \RuntimeException('Retur tidak ditemukan.');
            }

            if (! $this->canSeeAllUnits() && (int) $header->unit_idunit !== (int) session('ID_UNIT')) {
                throw new \RuntimeException('Retur bukan milik unit Anda.');
            }

            $sudahRefund = $db->query(
                'SELECT 1 FROM ' . $db->prefixTable('retur_klaim_item') . ' rki
                   JOIN ' . $db->prefixTable('service_sparepart') . ' sp ON sp.idservice_sparepart = rki.service_sparepart_idservice_sparepart
                   JOIN ' . $db->prefixTable('service') . ' s ON s.idservice = sp.service_idservice
                  WHERE rki.retur_klaim_idretur_klaim = ? AND s.refund_garansi_at IS NOT NULL
                  LIMIT 1',
                [$id]
            )->getRow();

            if ($sudahRefund) {
                throw new \RuntimeException('Retur tidak dapat dibatalkan karena service sudah direfund.');
            }

            $items = $db->table('retur_klaim_item')
                ->where('retur_klaim_idretur_klaim', $id)
                ->get()
                ->getResult();

            foreach ($items as $item) {
                $db->table('service_sparepart')
                    ->where('idservice_sparepart', $item->service_sparepart_idservice_sparepart)
                    ->update([
                        'status_retur' => 'bisa',
                        'hpp_omset_garansi' => 0,
                        'tanggal_hpp_klaim' => null,
                    ]);
            }

            $db->table('retur_klaim_item')->where('retur_klaim_idretur_klaim', $id)->delete();
            $db->table('retur_klaim')->where('idretur_klaim', $id)->delete();
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($target)->with('gagal', $e->getMessage());
        }

        return redirect()->to($target)->with('sukses', 'Retur dibatalkan dan status barang dikembalikan.');
    }

    private function lockSparepart($db, int $id): object
    {
        $row = $db->query(
            'SELECT sp.*, s.unit_idunit AS service_unit, s.tanggal_claim_garansi, s.refund_garansi_at
               FROM ' . $db->prefixTable('service_sparepart') . ' sp
               JOIN ' . $db->prefixTable('service') . ' s ON s.idservice = sp.service_idservice
              WHERE sp.idservice_sparepart = ?
              FOR UPDATE',
            [$id]
        )->getRow();

        if (! $row) {
            throw new \RuntimeException('Sparepart klaim tidak ditemukan.');
        }

        return $row;
    }

    private function pastikanUnit(object $row): void
    {
        $rowUnit = (int) ($row->unit_idunit ?: ($row->service_unit ?? 0));
        if (! $this->canSeeAllUnits() && $rowUnit !== (int) session('ID_UNIT')) {
            throw new \RuntimeException('Sparepart klaim bukan milik unit Anda.');
        }
    }

    private function canSeeAllUnits(): bool
    {
        return GaransiClaimService::canSeeAllUnits((int) session('ID_JABATAN'));
    }

    private function kembaliUrl(): string
    {
        $base = base_url('retur_barang_rusak');
        $kembali = $this->request->getPost('kembali');
        $query = is_string($kembali) ? trim($kembali) : '';

        return $query !== '' && preg_match('/^[A-Za-z0-9_%&=.,+-]+$/', $query) === 1
            ? $base . '?' . $query
            : $base;
    }

    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $time = strtotime($value);

        return $time ? date('Y-m-d', $time) : null;
    }

    private function rupiahToInt($value): int
    {
        if (! is_scalar($value)) {
            return 0;
        }

        return (int) preg_replace('/[^0-9]/', '', (string) $value);
    }
}
