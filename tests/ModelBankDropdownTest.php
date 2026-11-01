<?php

namespace Tests;

use App\Models\ModelBank;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Dropdown transaksi hanya boleh memuat bank yang punya rekening aktif.
 *
 * Bank tanpa `akun_kas_bank` aktif tetap tersimpan di master `bank`, tetapi
 * tidak boleh dipilih di form transaksi: transaksi sumbernya bisa tersimpan
 * sementara posting ledger gagal karena akun tujuannya tidak ada.
 */
class ModelBankDropdownTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_akun_kas_bank');
        $this->db->query('DROP TABLE IF EXISTS db_bank');
        $this->db->query('CREATE TABLE db_bank (
            idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, atas_nama TEXT NULL,
            norek TEXT NULL, gambar_qris TEXT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $this->db->query('CREATE TABLE db_akun_kas_bank (
            idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
            bank_idbank VARCHAR(20) NULL, tipe TEXT NULL, status TEXT NULL)');

        $this->db->query("INSERT INTO db_bank (idbank, nama_bank) VALUES
            ('B-AKTIF', 'Bank Aktif'), ('B-NONAKTIF', 'Bank Nonaktif'), ('B-TANPA', 'Tanpa Akun')");
        $this->db->query("INSERT INTO db_akun_kas_bank (bank_idbank, tipe, status) VALUES
            ('B-AKTIF', 'BANK', 'aktif'), ('B-NONAKTIF', 'BANK', 'nonaktif')");
    }

    public function testDropdownHanyaBankDenganRekeningAktif(): void
    {
        $ids = array_column((new ModelBank())->getBank(), 'idbank');

        $this->assertSame(['B-AKTIF'], $ids);
    }

    public function testHalamanMasterTetapMenampilkanSemuaBank(): void
    {
        $ids = array_column((new ModelBank())->getBankSemua(), 'idbank');
        sort($ids);

        $this->assertSame(['B-AKTIF', 'B-NONAKTIF', 'B-TANPA'], $ids);
    }
}
