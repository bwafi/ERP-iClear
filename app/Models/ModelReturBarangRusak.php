<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelReturBarangRusak extends Model
{
    protected $table = 'retur_klaim';
    protected $primaryKey = 'idretur_klaim';
    protected $returnType = 'object';

    public function getKlaimBelumRetur(?int $unitId, string $awal, string $akhir): array
    {
        $damaged = $this->db->table('barang_rusak_claim brc')
            ->select('brc.service_idservice, GROUP_CONCAT(b.nama_barang SEPARATOR ", ") AS nama_barang_rusak', false)
            ->join('barang b', 'b.idbarang = brc.barang_idbarang', 'left')
            ->groupBy('brc.service_idservice')
            ->getCompiledSelect(false);

        $builder = $this->db->table('service_sparepart sp')
            ->select('
                sp.idservice_sparepart,
                sp.service_idservice,
                sp.barang_idbarang,
                sp.jumlah_tambahan_garansi,
                sp.hpp_penjualan_garansi,
                sp.status_retur,
                sp.tanggal_hpp_klaim,
                s.no_service,
                s.tanggal_claim_garansi,
                s.unit_idunit AS service_unit,
                b.kode_barang,
                b.nama_barang,
                u.NAMA_UNIT,
                br.nama_barang_rusak
            ')
            ->join('service s', 's.idservice = sp.service_idservice')
            ->join('barang b', 'b.idbarang = sp.barang_idbarang', 'left')
            ->join('unit u', 'u.idunit = COALESCE(sp.unit_idunit, s.unit_idunit)', 'left')
            ->join("({$damaged}) br", 'br.service_idservice = s.idservice', 'left')
            ->whereIn('sp.status_retur', ['bisa', 'tidak_bisa'])
            ->where('sp.jumlah_tambahan_garansi >', 0)
            ->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->prefixTable('retur_klaim_item') . ' rki WHERE rki.service_sparepart_idservice_sparepart = sp.idservice_sparepart)', null, false)
            ->where('DATE(s.tanggal_claim_garansi) >=', $awal)
            ->where('DATE(s.tanggal_claim_garansi) <=', $akhir);

        if ($unitId !== null) {
            $builder->where('COALESCE(sp.unit_idunit, s.unit_idunit)', $unitId);
        }

        return $builder
            ->orderBy('s.tanggal_claim_garansi', 'DESC')
            ->orderBy('sp.idservice_sparepart', 'DESC')
            ->get()
            ->getResult();
    }

    public function getRiwayat(?int $unitId, string $awal, string $akhir): array
    {
        $builder = $this->db->table('retur_klaim rk')
            ->select('
                rk.*,
                s.nama_suplier,
                u.NAMA_UNIT,
                COUNT(rki.idretur_klaim_item) AS jumlah_item,
                COALESCE(SUM(rki.nilai), 0) AS total_nilai
            ')
            ->join('suplier s', 's.id_suplier = rk.suplier_id_suplier', 'left')
            ->join('unit u', 'u.idunit = rk.unit_idunit', 'left')
            ->join('retur_klaim_item rki', 'rki.retur_klaim_idretur_klaim = rk.idretur_klaim', 'left')
            ->where('DATE(rk.tanggal) >=', $awal)
            ->where('DATE(rk.tanggal) <=', $akhir);

        if ($unitId !== null) {
            $builder->where('rk.unit_idunit', $unitId);
        }

        return $builder
            ->groupBy('rk.idretur_klaim')
            ->orderBy('rk.tanggal', 'DESC')
            ->orderBy('rk.idretur_klaim', 'DESC')
            ->get()
            ->getResult();
    }

    public function getItems(int $id): array
    {
        return $this->db->table('retur_klaim_item rki')
            ->select('
                rki.*,
                b.nama_barang,
                bg.nama_barang AS nama_barang_pengganti,
                sp.hpp_penjualan_garansi,
                s.no_service
            ')
            ->join('barang b', 'b.idbarang = rki.barang_idbarang', 'left')
            ->join('barang bg', 'bg.idbarang = rki.barang_pengganti_idbarang', 'left')
            ->join('service_sparepart sp', 'sp.idservice_sparepart = rki.service_sparepart_idservice_sparepart', 'left')
            ->join('service s', 's.idservice = sp.service_idservice', 'left')
            ->where('rki.retur_klaim_idretur_klaim', $id)
            ->orderBy('rki.idretur_klaim_item', 'ASC')
            ->get()
            ->getResult();
    }
}
