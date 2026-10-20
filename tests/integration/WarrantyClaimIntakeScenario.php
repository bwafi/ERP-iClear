<?php

namespace Tests\integration;

use App\Controllers\StatusGaransi;
use App\Database\Migrations\AddWarrantyClaimIntake;
use App\Services\Inventory\InventoryBatchLedger;
use App\Services\Inventory\InventoryLedger;
use App\Services\Service\WarrantyClaimIntake;
use Config\Database;

trait WarrantyClaimIntakeScenario
{
    private function assertWarrantyClaimIntake($db): void
    {
        require_once APPPATH . 'Database/Migrations/2026-10-20-000400_AddWarrantyClaimIntake.php';
        $migration = new AddWarrantyClaimIntake(Database::forge($db));
        $migration->up();
        $migration->up();
        self::assertTrue($db->fieldExists('original_service_sparepart_id', 'riwayat_claim_garansi'));
        $db->query("INSERT INTO barang (idbarang,kode_barang,nama_barang,jenis_item,deleted) VALUES (20,'UAT-CLAIM','UAT-CLAIM PART','fisik','0')");
        $db->query('INSERT INTO hpp_barang VALUES (20,28000)');
        $ledger = new InventoryLedger($db);
        $batches = new InventoryBatchLedger($db);
        $db->query("INSERT INTO detail_pembelian (iddetail_pembelian,pembelian_idpembelian,barang_idbarang,unit_idunit,jumlah,tanggal,no_batch,hitung_hpp) VALUES (81,1,20,1,2,'2026-10-19 08:00:00','UAT-A',28000)");
        $ledger->postIncoming(20, 1, 'detail_pembelian', '81', 'purchase', 2, 28000);
        $batches->post(20, 1, 'detail_pembelian', '81', 'purchase', 2, 81);
        $db->query('INSERT INTO detail_penjualan VALUES (91,1,20,1,1,28000,81)');
        $ledger->postOutgoing(20, 1, 'detail_penjualan', '91', 'sale', 1);
        $batches->post(20, 1, 'detail_penjualan', '91', 'sale', -1, 81);
        $db->query("INSERT INTO detail_pembelian (iddetail_pembelian,pembelian_idpembelian,barang_idbarang,unit_idunit,jumlah,tanggal,no_batch,hitung_hpp) VALUES (82,2,20,1,1,'2026-10-19 10:00:00','UAT-B',35000)");
        $ledger->postIncoming(20, 1, 'detail_pembelian', '82', 'purchase', 1, 35000);
        $batches->post(20, 1, 'detail_pembelian', '82', 'purchase', 1, 82);
        require_once APPPATH . 'Database/Migrations/2026-10-20-000500_CreateProductTypeApprovalHistory.php';
        $typeMigration = new \App\Database\Migrations\CreateProductTypeApprovalHistory(Database::forge($db));
        $typeMigration->up();
        $typeMigration->up();
        $catalog = new \App\Services\Inventory\WarrantyReplacementCatalog($db);
        self::assertSame(['ready' => [], 'pending' => []], $catalog->forUnit(0));
        $db->table('barang')->where('idbarang', 20)->update(['jenis_item' => null]);
        self::assertContains('20', array_map('strval', array_column($catalog->forUnit(1)['pending'], 'idbarang')));
        self::assertNotContains('20', array_map('strval', array_column($catalog->forUnit(1)['ready'], 'idbarang')));
        self::assertNotContains('20', array_map('strval', array_column($catalog->forUnit(2)['pending'], 'idbarang')));
        $approval = new \App\Services\Inventory\ProductTypeApproval($db);
        try {
            $approval->approve(20, 'jasa', 9, 'Uji penolakan jasa untuk stok aktif');
            self::fail('Jasa dengan riwayat stok diterima.');
        } catch (\DomainException $expected) {
            self::assertStringContainsString('riwayat', $expected->getMessage());
        }
        self::assertNull($db->table('barang')->where('idbarang', 20)->get()->getRow()->jenis_item);
        self::assertSame(0, $db->table('barang_tipe_history')->countAllResults());
        self::assertTrue($approval->approve(20, 'fisik', 9, 'Barang sintetis diperiksa fisik dan nota UAT-A/UAT-B.'));
        self::assertFalse($approval->approve(20, 'fisik', 9, 'Permintaan uji diulang tanpa menggandakan audit.'));
        self::assertSame(1, $db->table('barang_tipe_history')->countAllResults());
        self::assertSame(2, $batches->total(20, 1));
        self::assertSame(63000, (int) $db->table('inventory_ledger_balances')->where('item_id', 20)->get()->getRow()->value);
        self::assertSame(28000, (int) $db->table('hpp_barang')->where('idbarang', 20)->get()->getRow()->hpp);
        $ready = array_values(array_filter($catalog->forUnit(1)['ready'], static fn ($row) => (int) $row['idbarang'] === 20));
        self::assertCount(1, $ready);
        self::assertCount(2, $ready[0]['batches']);
        self::assertSame(['Supplier B', 'Supplier A'], array_column($ready[0]['batches'], 'nama_suplier'));
        $origin = new \App\Services\Inventory\PurchaseBatchOrigin($db);
        self::assertSame(81, $origin->serviceSelection('81', null, 20, 1));
        self::assertSame(81, $origin->serviceSelection('', (object) ['source_purchase_detail_id' => 81], 20, 1));
        self::assertNull($origin->serviceSelection('', (object) ['source_purchase_detail_id' => null], 20, 1));
        foreach ([[null, null], [82, (object) ['source_purchase_detail_id' => 81]], [81, (object) ['source_purchase_detail_id' => null]]] as [$selection, $existing]) {
            try {
                $origin->serviceSelection($selection, $existing, 20, 1);
                self::fail('Asal supplier dilewati atau histori ditulis ulang.');
            } catch (\DomainException $expected) {
                self::assertNotEmpty($expected->getMessage());
            }
        }
        $db->query("INSERT INTO service (idservice,no_service,unit_idunit,status_service,status_proses,garansi_opsi_snapshot,garansi_mulai,garansi_akhir,tanggal_selesai) VALUES (91,'UAT-CLAIM-SERVICE',1,4,1,'selamanya','2026-10-19',NULL,'2026-10-19 11:00:00')");
        $db->query('INSERT INTO service_sparepart (idservice_sparepart,service_idservice,barang_idbarang,unit_idunit,jumlah,source_purchase_detail_id,hpp_penjualan,harga_penjualan) VALUES (91,91,20,1,1,81,28000,450000)');
        $db->query('INSERT INTO proses_service VALUES (91,91,1)');
        $intake = new WarrantyClaimIntake($db);
        $original = $intake->originals(91)[0];
        self::assertSame('Supplier A', $original['nama_suplier']);
        self::assertSame('A-1', $original['no_nota_supplier']);
        self::assertSame('UAT-A', $original['no_batch']);
        $input = ['idservice' => '91', 'original_part_id' => '91', 'claim_quantity' => '1',
            'claim_reason' => 'UAT-CLAIM pecah saat uji', 'replacement_id' => '20',
            'replacement_quantity' => '1', 'replacement_batch_id' => '82'];
        self::assertSame(82, $intake->validate(91, 1, $input)['replacement']['purchase_detail_id']);
        foreach ([['replacement_id' => ''], ['replacement_batch_id' => ''], ['replacement_quantity' => '1.5'],
            ['replacement_quantity' => '2'], ['original_part_id' => '999'], ['claim_quantity' => '2'],
            ['replacement_id' => '8'], ['claim_reason' => '']] as $invalid) {
            try {
                $intake->validate(91, 1, array_replace($input, $invalid));
                self::fail('Input klaim tidak valid diterima.');
            } catch (\DomainException | \InvalidArgumentException $expected) {
                self::assertNotEmpty($expected->getMessage());
            }
        }
        try {
            $intake->validate(91, 2, $input);
            self::fail('Batch cabang lain diterima.');
        } catch (\InvalidArgumentException $expected) {
            self::assertStringContainsString('saldo batch', $expected->getMessage());
        }
        $db->table('service_sparepart')->where('idservice_sparepart', 91)->update(['source_purchase_detail_id' => null]);
        self::assertNull($intake->originals(91)[0]['nama_suplier']);
        self::assertNull($intake->originals(91)[0]['source_purchase_detail_id']);
        $db->table('service_sparepart')->where('idservice_sparepart', 91)->update(['source_purchase_detail_id' => 81]);

        $instances = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
        $previous = $instances->getValue();
        $instances->setValue(null, array_replace($previous, ['tests' => $db]));
        session()->set(['ID_AKUN' => 9, 'ID_UNIT' => 1, 'ID_JABATAN' => 7]);
        $reflection = new \ReflectionClass(StatusGaransi::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        foreach (['ServiceModel' => 'ModelService', 'ServiceSparepartModel' => 'ModelServiceSparepart',
            'RiwayatKlaimGaransiModel' => 'ModelRiwayatKlaimGaransi', 'ProsesServiceModel' => 'ModelProsesService',
            'HppBarangModel' => 'ModelHppBarang', 'StokAwalModel' => 'ModelStokAwal'] as $property => $model) {
            $class = 'App\\Models\\' . $model;
            $reflection->getProperty($property)->setValue($controller, new $class($db));
        }
        $authorization = new class {
            public bool $allowed = true;
            public function getByIdWithJabatan($accountId): object
            {
                return (object) ['ROLES_JABATAN' => $this->allowed ? '[76]' : '[]'];
            }
        };
        $reflection->getProperty('AuthModel')->setValue($controller, $authorization);
        $request = \Config\Services::request();
        $reflection->getProperty('request')->setValue($controller, $request);
        $reflection->getProperty('response')->setValue($controller, \Config\Services::response());
        try {
            $productReflection = new \ReflectionClass(\App\Controllers\Produk::class);
            $productController = $productReflection->newInstanceWithoutConstructor();
            $productReflection->getProperty('request')->setValue($productController, $request);
            $productReflection->getProperty('response')->setValue($productController, \Config\Services::response());
            self::assertSame(403, $productController->approveTipe()->getStatusCode());
            self::assertSame(1, $db->table('barang_tipe_history')->countAllResults());
            $authorization->allowed = false;
            $request->setGlobal('post', $input);
            self::assertSame(403, $controller->claim_garansi()->getStatusCode());
            self::assertSame(0, $db->table('riwayat_claim_garansi')->countAllResults());
            $authorization->allowed = true;
            $request->setGlobal('post', ['idservice' => 91]);
            $controller->claim_garansi();
            self::assertSame(4, (int) $db->table('service')->where('idservice', 91)->get()->getRow()->status_service);
            self::assertSame(0, $db->table('riwayat_claim_garansi')->countAllResults());
            $request->setGlobal('post', $input);
            $controller->claim_garansi();
            self::assertSame(0, $db->table('garansi_sparepart_keluar')->countAllResults());
            self::assertSame(4, (int) $db->table('service')->where('idservice', 91)->get()->getRow()->status_service);
            self::assertSame(2, $batches->total(20, 1));
            self::assertSame(63000, (int) $db->table('inventory_ledger_balances')->where('item_id', 20)->get()->getRow()->value);
            $db->query("INSERT INTO no_akun VALUES ('6010301000','Beban Penanganan Keluhan Pelanggan')");
            $db->query("INSERT INTO template_jurnal (kode_template,no_akun,debet_kredit) VALUES ('garansi_sparepart_keluar','6010301000','debet'),('garansi_sparepart_keluar','1030100000','kredit')");
            $controller->claim_garansi();
            $issue = $db->table('garansi_sparepart_keluar')->get()->getRow();
            self::assertNotNull($issue, 'Klaim valid harus mengeluarkan sparepart pengganti.');
            self::assertSame(31500, (int) $issue->hpp_satuan);
            self::assertSame(82, (int) $issue->source_purchase_detail_id);
            self::assertSame(1, $batches->total(20, 1));
            self::assertSame(0, (int) $db->table('inventory_batch_balances')->where('batch_id', 82)->get()->getRow()->quantity);
            self::assertSame(31500, (int) $db->table('inventory_ledger_balances')->where('item_id', 20)->get()->getRow()->value);
            self::assertSame(28000, (int) $db->table('service_sparepart')->where('idservice_sparepart', 91)->get()->getRow()->hpp_penjualan);
            $journal = $db->table('jurnal')->selectSum('debet')->selectSum('kredit')->where('tabel_referensi', 'garansi_sparepart_keluar')->get()->getRow();
            self::assertSame(31500, (int) $journal->debet);
            self::assertSame(31500, (int) $journal->kredit);
            self::assertSame(2, (int) $db->table('service')->where('idservice', 91)->get()->getRow()->status_service);
            self::assertSame(91, (int) $db->table('riwayat_claim_garansi')->get()->getRow()->original_service_sparepart_id);
            self::assertSame(81, (int) $db->table('riwayat_claim_garansi')->get()->getRow()->original_purchase_detail_id);
            $controller->claim_garansi();
            self::assertSame(1, $db->table('garansi_sparepart_keluar')->countAllResults());
            self::assertSame(1, $db->table('riwayat_claim_garansi')->countAllResults());
            self::assertSame(1, $batches->total(20, 1));

            $firstClaim = (int) $issue->claim_id;
            $damagedOrigin = new \App\Services\Inventory\ClaimDamagedOrigin($db);
            $claimSource = $damagedOrigin->get($firstClaim, 1);
            self::assertSame('Supplier A', $claimSource['supplier']);
            self::assertSame(1, $claimSource['available']);
            self::assertSame(1, $claimSource['purchase_id']);
            try {
                $damagedOrigin->get($firstClaim, 2);
                self::fail('Klaim unit lain terbaca.');
            } catch (\DomainException $expected) {
                self::assertNotEmpty($expected->getMessage());
            }
            $damagedReflection = new \ReflectionClass(\App\Controllers\BarangRusak::class);
            $damagedController = $damagedReflection->newInstanceWithoutConstructor();
            $damagedReflection->getProperty('BarangRusakModel')->setValue($damagedController, new \App\Models\ModelBarangRusak($db));
            $damagedReflection->getProperty('request')->setValue($damagedController, $request);
            $damagedReflection->getProperty('response')->setValue($damagedController, \Config\Services::response());
            $damage = ['idbarang' => ['20'], 'idpembelian' => ['2'], 'no_batch' => ['UAT-B'],
                'jumlah_rusak' => ['1'], 'tanggal_rusak' => ['2026-10-20'], 'keterangan' => ['UAT-CLAIM'],
                'claim_id' => [(string) $firstClaim], 'asal_dokumen' => ['terverifikasi']];
            $request->setGlobal('post', $damage);
            $damagedController->insert_barang_rusak();
            self::assertSame(0, $db->table('barang_rusak')->where('claim_id', $firstClaim)->countAllResults());
            $damage['idpembelian'] = ['1'];
            $damage['no_batch'] = ['UAT-A'];
            $request->setGlobal('post', $damage);
            $damagedController->insert_barang_rusak();
            self::assertSame(1, $db->table('barang_rusak')->where('claim_id', $firstClaim)->countAllResults());
            self::assertSame(1, $batches->total(20, 1));
            self::assertSame(0, $damagedOrigin->get($firstClaim, 1)['available']);
            $damagedController->insert_barang_rusak();
            self::assertSame(1, $db->table('barang_rusak')->where('claim_id', $firstClaim)->countAllResults());

            $installed = $intake->originals(91)[0];
            self::assertSame('Supplier B', $installed['nama_suplier']);
            self::assertSame(82, (int) $installed['source_purchase_detail_id']);
            $db->table('service')->where('idservice', 91)->update(['status_service' => 4]);
            $input['replacement_batch_id'] = '81';
            $request->setGlobal('post', $input);
            $controller->claim_garansi();
            self::assertSame(2, $db->table('riwayat_claim_garansi')->countAllResults());
            self::assertSame(2, $db->table('garansi_sparepart_keluar')->countAllResults());
            self::assertSame(0, $batches->total(20, 1));
            $secondClaim = $db->table('riwayat_claim_garansi')->orderBy('idriwayat_claim_garansi', 'DESC')->get()->getRow();
            $db->table('riwayat_claim_garansi')->insert([
                'service_idservice' => 91, 'original_service_sparepart_id' => 91,
                'original_barang_id' => 20, 'original_quantity' => 1,
                'original_purchase_detail_id' => null,
            ]);
            $unknownClaim = (int) $db->insertID();
            self::assertSame('belum_diketahui', $damagedOrigin->get($unknownClaim, 1)['origin_status']);
            $damage['claim_id'] = [(string) $unknownClaim];
            $damage['idpembelian'] = ['0'];
            $damage['no_batch'] = [''];
            $damage['asal_dokumen'] = ['belum_diketahui'];
            $request->setGlobal('post', $damage);
            $damagedController->insert_barang_rusak();
            $unknownDamage = $db->table('barang_rusak')->where('claim_id', $unknownClaim)->get()->getRow();
            self::assertNotNull($unknownDamage);
            self::assertNull($unknownDamage->idpembelian);
            self::assertNull($unknownDamage->no_nota_sup);
            self::assertSame(0, $batches->total(20, 1));
            $damagedController->insert_barang_rusak();
            self::assertSame(1, $db->table('barang_rusak')->where('claim_id', $unknownClaim)->countAllResults());
            self::assertSame(0, (int) $db->table('inventory_ledger_balances')->where('item_id', 20)->get()->getRow()->value);
            self::assertSame(82, (int) $secondClaim->original_purchase_detail_id);
            self::assertSame(81, (int) $db->table('riwayat_claim_garansi')->where('idriwayat_claim_garansi', $firstClaim)->get()->getRow()->original_purchase_detail_id);
            self::assertSame(28000, (int) $db->table('service_sparepart')->where('idservice_sparepart', 91)->get()->getRow()->hpp_penjualan);
            $damage['idpembelian'] = ['2'];
            $damage['no_batch'] = ['UAT-B'];
            $damage['claim_id'] = [(string) $secondClaim->idriwayat_claim_garansi];
            $damage['asal_dokumen'] = ['terverifikasi'];
            $request->setGlobal('post', $damage);
            $damagedController->insert_barang_rusak();
            self::assertSame(1, $db->table('barang_rusak')->where('claim_id', $secondClaim->idriwayat_claim_garansi)->countAllResults());
            self::assertSame(0, $batches->total(20, 1));
            $db->query('ALTER TABLE retur_suplier MODIFY idretur_suplier INT AUTO_INCREMENT, ADD no_retur_suplier VARCHAR(60), ADD detail_pembelian_iddetail_pembelian INT, ADD satuan BIGINT');
            $db->query('ALTER TABLE detail_pembelian ADD satuan_beli BIGINT DEFAULT 0');
            $db->query("ALTER TABLE pembelian ADD sisa BIGINT DEFAULT 0, ADD tanggal_masuk DATE, ADD status VARCHAR(20)");
            $db->query("CREATE TABLE kredit_retur_suplier (id INT AUTO_INCREMENT PRIMARY KEY, no_retur_suplier VARCHAR(60), suplier_id INT, unit_id INT, status VARCHAR(20), nominal BIGINT, created_at DATETIME, sisa_kredit BIGINT, nomor_nota_kredit VARCHAR(100), akun_debet VARCHAR(10), akun_piutang VARCHAR(10), akun_kredit VARCHAR(10), disetujui_oleh INT, disetujui_pada DATETIME) ENGINE=InnoDB");
            $db->table('detail_pembelian')->where('iddetail_pembelian', 81)->update(['satuan_beli' => 28000]);
            $returnReflection = new \ReflectionClass(\App\Controllers\Retur_Suplier::class);
            $returnController = $returnReflection->newInstanceWithoutConstructor();
            $returnReflection->getProperty('ReturSuplierModel')->setValue($returnController, new \App\Models\ModelReturSuplier($db));
            $returnReflection->getProperty('AuthModel')->setValue($returnController, new class {
                public function getById($accountId): object
                {
                    return (object) ['ID_UNIT' => 1];
                }
            });
            $returnReflection->getProperty('request')->setValue($returnController, $request);
            $returnReflection->getProperty('response')->setValue($returnController, \Config\Services::response());
            $request->setGlobal('post', ['tanggal_pengembalian' => '2026-10-20',
                'items' => [['selected' => '1', 'iddetail_pembelian' => '81', 'jumlah_retur' => '1']]]);
            $returnController->insert();
            $credit = $db->table('kredit_retur_suplier')->get()->getRow();
            self::assertNotNull($credit);
            self::assertSame('menunggu', $credit->status);
            self::assertSame(1, (int) $credit->suplier_id);
            self::assertSame(0, $batches->total(20, 1));
            $returnController->insert();
            self::assertSame(1, $db->table('kredit_retur_suplier')->countAllResults());
            $db->query("INSERT IGNORE INTO no_akun VALUES ('2010000000','Utang fixture'),('1020000000','Kredit supplier fixture'),('4010000000','Pemulihan fixture')");
            $creditService = new \App\Services\Finance\SupplierReturnCreditService();
            $creditService->approve((int) $credit->id, '28000', 'UAT-TEAM-CREDIT-A', '2010000000', '1020000000', '4010000000', 9);
            $approved = $db->table('kredit_retur_suplier')->where('id', $credit->id)->get()->getRow();
            self::assertSame('disetujui', $approved->status);
            self::assertSame(28000, (int) $approved->sisa_kredit);
            $creditJournal = $db->table('jurnal')->selectSum('debet')->selectSum('kredit')
                ->where('tabel_referensi', 'kredit_retur_suplier')->where('id_referensi', $credit->id)->get()->getRow();
            self::assertSame(28000, (int) $creditJournal->debet);
            self::assertSame(28000, (int) $creditJournal->kredit);
            try {
                $creditService->approve((int) $credit->id, '28000', 'UAT-TEAM-CREDIT-A', '2010000000', '1020000000', '4010000000', 9);
                self::fail('Kredit ganda diterima.');
            } catch (\RuntimeException $expected) {
                self::assertStringContainsString('sudah disetujui', $expected->getMessage());
            }
            self::assertSame(2, $db->table('jurnal')->where('tabel_referensi', 'kredit_retur_suplier')->countAllResults());
            self::assertSame(0, $batches->total(20, 1));
            self::assertSame(28000, (int) $db->table('service_sparepart')->where('idservice_sparepart', 91)->get()->getRow()->hpp_penjualan);
            try {
                $migration->down();
                self::fail('Rollback menghapus histori klaim.');
            } catch (\RuntimeException $expected) {
                self::assertStringContainsString('Rollback schema ditolak', $expected->getMessage());
            }
        } finally {
            $db->query('DROP TABLE IF EXISTS kredit_retur_suplier');
            $db->query('DROP TABLE IF EXISTS barang_tipe_history');
            $instances->setValue(null, $previous);
            session()->remove(['ID_AKUN', 'ID_UNIT', 'ID_JABATAN']);
        }
    }
}
