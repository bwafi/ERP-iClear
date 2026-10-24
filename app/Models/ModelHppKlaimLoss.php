<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Sumber angka HPP klaim yang mengurangi omset/laba.
 *
 * Periode selalu tanggal_hpp_klaim, bukan tanggal service.
 */
class ModelHppKlaimLoss extends Model
{
    protected $table = 'service_sparepart';
    protected $primaryKey = 'idservice_sparepart';
    protected $returnType = 'object';

    public function getLoss($start, $end, $unitId = null, $teknisiId = null): array
    {
        $builder = $this->db->table('service_sparepart sp')
            ->select('
                sp.idservice_sparepart,
                sp.service_idservice,
                sp.tanggal_hpp_klaim,
                sp.hpp_omset_garansi AS hpp,
                sp.jumlah_tambahan_garansi AS qty,
                (sp.hpp_omset_garansi * sp.jumlah_tambahan_garansi) AS total,
                s.no_service,
                s.service_by,
                a.NAMA_AKUN AS nama_teknisi,
                b.nama_barang,
                u.NAMA_UNIT
            ')
            ->join('service s', 's.idservice = sp.service_idservice')
            ->join('akun a', 'a.ID_AKUN = s.service_by', 'left')
            ->join('barang b', 'b.idbarang = sp.barang_idbarang', 'left')
            ->join('unit u', 'u.idunit = COALESCE(sp.unit_idunit, s.unit_idunit)', 'left')
            ->whereIn('sp.status_retur', ['tidak_bisa', 'ditolak'])
            ->where('sp.jumlah_tambahan_garansi >', 0)
            ->where('sp.tanggal_hpp_klaim >=', $start)
            ->where('sp.tanggal_hpp_klaim <=', $end);

        if ($unitId !== null && $unitId !== '' && (int) $unitId !== 0) {
            $builder->where('COALESCE(sp.unit_idunit, s.unit_idunit)', (int) $unitId);
        }

        if ($teknisiId !== null && $teknisiId !== '' && (int) $teknisiId !== 0) {
            $builder->where('s.service_by', (int) $teknisiId);
        }

        return $builder
            ->orderBy('sp.tanggal_hpp_klaim', 'DESC')
            ->orderBy('sp.idservice_sparepart', 'DESC')
            ->get()
            ->getResult();
    }

    public function getTotalLoss($start, $end, $unitId = null, $teknisiId = null): int
    {
        $total = 0;
        foreach ($this->getLoss($start, $end, $unitId, $teknisiId) as $row) {
            $total += (int) $row->total;
        }

        return $total;
    }
}
