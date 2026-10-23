<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelRiwayatKlaimGaransi extends Model
{
    protected $table = 'riwayat_claim_garansi';
    protected $primaryKey = 'idriwayat_claim_garansi';
    protected $returnType = 'object';
    protected $allowedFields = ['idriwayat_claim_garansi', 'service_idservice', 'tanggal_claim', 'catatan'];

    /**
     * Ambil semua riwayat claim
     */
    public function getAll()
    {
        return $this->findAll();
    }

    /**
     * Ambil riwayat claim berdasarkan service_idservice
     */
    public function getByService($serviceId)
    {
        return $this->where('service_idservice', $serviceId)->findAll();
    }

    /**
     * Insert riwayat claim baru
     */
    public function insertRiwayat($data)
    {
        return $this->insert($data);
    }

    /**
     * Ambil data berdasarkan ID riwayat
     */
    public function getById($id)
    {
        return $this->where('idriwayat_claim_garansi', $id)->first();
    }
}
