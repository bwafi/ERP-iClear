<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelBank extends Model
{
    protected $table = 'bank';
    protected $primaryKey = 'idbank';
    protected $returnType = 'object';
    protected $allowedFields = ['idbank', 'nama_bank', 'atas_nama', 'norek', 'gambar_qris', 'created_on', 'updated_on'];

    public function getBank()
    {

        return $this->findAll();
    }

    public function getById($idbank)
    {
        return $this->where(['idbank' => $idbank])->first();
    }

    /**
     * Rekening bank fisik yang boleh dipakai oleh satu unit.
     *
     * Memakai aturan scope rekening yang sama dengan modul Kas & Bank:
     * rekening milik unit tersebut, plus rekening shared yang punya baris
     * alokasi ke unit itu. Rekening milik unit LAIN dan rekening Finance/HO
     * (kas Direksi) TIDAK ikut ditampilkan.
     *
     * Dipakai oleh dropdown "Rekening Tujuan (DP)" pada transaksi Service
     * supaya unit tidak bisa menargetkan rekening cabang lain.
     *
     * @return object[]
     */
    public function getBankDalamScopeUnit(int $unitId, bool $aktifOnly = true): array
    {
        $unitId = (int) $unitId;
        if ($unitId <= 0) {
            return [];
        }

        // Instalasi lama / lingkungan uji tanpa skema Kas & Bank: kembalikan
        // daftar apa adanya supaya kolom rekening tidak mendadak kosong.
        if (!$this->db->tableExists('akun_kas_bank')) {
            return $this->getBank();
        }

        $akunList = (new ModelAkunKasBank())->getDalamScopeUnit([$unitId], $unitId, $aktifOnly);

        $idbankDisetujui = [];
        foreach ($akunList as $akun) {
            if (strtoupper((string) ($akun->tipe ?? '')) !== 'BANK') {
                continue;
            }
            if ((int) ($akun->is_finance_ho ?? 0) === 1) {
                continue;
            }
            $idbank = trim((string) ($akun->bank_idbank ?? ''));
            if ($idbank !== '') {
                $idbankDisetujui[$idbank] = true;
            }
        }

        if ($idbankDisetujui === []) {
            return [];
        }

        return array_values(array_filter($this->getBank(), static function ($bank) use ($idbankDisetujui) {
            return isset($idbankDisetujui[trim((string) ($bank->idbank ?? ''))]);
        }));
    }
}
