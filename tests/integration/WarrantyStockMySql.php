<?php

namespace CodeIgniter\Database {
    class Migration
    {
    }
}

namespace {
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000500_LinkDamagedStockToSupplierReturn.php';
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000700_CreateWarrantyStockIssues.php';
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000800_LinkWarrantyDamagedStock.php';
    require __DIR__ . '/../../app/Services/Finance/BalanceSheetReconciliation.php';
    require __DIR__ . '/../../app/Services/Finance/SupplierCreditReconciliation.php';
    require __DIR__ . '/../../app/Services/Finance/WarrantyJournalReconciliation.php';

    $databaseName = 'codex_erp_warranty_' . bin2hex(random_bytes(4));
    $connection = new \PDO(
        'mysql:host=localhost;charset=utf8mb4',
        getenv('ERP_SYNTHETIC_MYSQL_USER') ?: 'root',
        getenv('ERP_SYNTHETIC_MYSQL_PASSWORD') ?: ''
    );
    $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $connection->exec("CREATE DATABASE `{$databaseName}`");

    try {
        $connection->exec("USE `{$databaseName}`");
        $connection->exec('CREATE TABLE barang (
            idbarang INT PRIMARY KEY, kode_barang VARCHAR(40), nama_barang VARCHAR(100), harga BIGINT,
            harga_beli BIGINT, idkategori INT, imei VARCHAR(30), jenis_hp VARCHAR(40),
            internal VARCHAR(40), warna VARCHAR(40), status VARCHAR(20), status_ppn VARCHAR(2),
            status_barang VARCHAR(20), stok_minimum INT, deleted VARCHAR(2), input VARCHAR(20),
            nama_barang_id VARCHAR(100)
        )');
        $connection->exec('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(50))');
        $connection->exec('CREATE TABLE detail_pembelian (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE stok_awal (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE detail_penjualan (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE detail_mutasi (barang_idbarang INT, kirim_idunit INT, terima_idunit INT, jumlah_kirim INT, jumlah_terima INT)');
        $connection->exec('CREATE TABLE retur_pelanggan (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE retur_suplier (barang_idbarang INT, unit_idunit INT, jumlah INT, barang_rusak_id INT)');
        $connection->exec('CREATE TABLE barang_rusak (idbarang_rusak INT PRIMARY KEY, barang_idbarang INT, unit_idunit INT, jumlah INT, claim_id INT NULL)');
        $connection->exec('CREATE TABLE stok_opname (barang_idbarang INT, unit_idunit INT, is_reverted INT)');
        $connection->exec('CREATE TABLE garansi_sparepart_keluar (
            id INT AUTO_INCREMENT PRIMARY KEY, claim_id INT, service_id INT, barang_id INT, unit_id INT, jumlah INT, hpp_satuan BIGINT,
            UNIQUE KEY uniq_garansi_claim_barang (claim_id, barang_id)
        )');
        $connection->exec('CREATE TABLE kredit_retur_suplier (
            id INT PRIMARY KEY, status VARCHAR(20), nominal BIGINT, sisa_kredit BIGINT,
            akun_debet VARCHAR(10), akun_piutang VARCHAR(10), akun_kredit VARCHAR(10)
        )');
        $connection->exec('CREATE TABLE alokasi_kredit_retur_suplier (kredit_id INT, nominal BIGINT)');
        $connection->exec('CREATE TABLE jurnal (
            no_akun VARCHAR(10), debet BIGINT, kredit BIGINT, id_referensi INT, tabel_referensi VARCHAR(40)
        )');
        $connection->exec("INSERT INTO barang (idbarang, kode_barang, nama_barang, deleted) VALUES (1, 'TEST', 'Sintetis', '0')");
        $connection->exec("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'TEST')");
        $connection->exec('INSERT INTO detail_pembelian VALUES (1, 1, 10)');
        $connection->exec('INSERT INTO detail_penjualan VALUES (1, 1, 1)');
        $connection->exec('INSERT INTO barang_rusak VALUES (100, 1, 1, 1, NULL)');
        $connection->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 100)');

        $migration = new \App\Database\Migrations\LinkWarrantyDamagedStock();
        $viewMethod = new \ReflectionMethod($migration, 'warrantyDamagedStockView');
        $connection->exec($viewMethod->invoke($migration));
        $stock = static function () use ($connection): int {
            return (int) $connection->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 1 AND id_unit = 1')->fetchColumn();
        };
        if ($stock() !== 8) {
            throw new \RuntimeException('Retur terkait barang rusak mengurangi stok dua kali.');
        }
        $connection->exec('INSERT INTO garansi_sparepart_keluar (claim_id, service_id, barang_id, unit_id, jumlah, hpp_satuan) VALUES (7, 12, 1, 1, 2, 230000)');
        if ($stock() !== 6) {
            throw new \RuntimeException('Penggantian garansi tidak mengurangi stok tepat dua unit.');
        }
        $connection->exec('INSERT INTO barang_rusak VALUES (101, 1, 1, 1, 7)');
        if ($stock() !== 6) {
            throw new \RuntimeException('Barang rusak garansi mengurangi stok jual lagi.');
        }
        $connection->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 101)');
        if ($stock() !== 6) {
            throw new \RuntimeException('Retur barang rusak garansi mengurangi stok jual lagi.');
        }
        try {
            $connection->exec('INSERT INTO garansi_sparepart_keluar (claim_id, service_id, barang_id, unit_id, jumlah, hpp_satuan) VALUES (7, 12, 1, 1, 2, 230000)');
            throw new \RuntimeException('Duplikasi klaim garansi tidak ditolak.');
        } catch (\PDOException $error) {
            if ($stock() !== 6) {
                throw new \RuntimeException('Duplikasi klaim mengubah saldo stok.');
            }
        }
        $connection->exec('INSERT INTO garansi_sparepart_keluar (claim_id, service_id, barang_id, unit_id, jumlah, hpp_satuan) VALUES (8, 12, 1, 1, 1, 250000)');
        if ($stock() !== 5) {
            throw new \RuntimeException('Klaim kedua tidak mengurangi stok satu unit tambahan.');
        }
        $hpp = (int) $connection->query('SELECT SUM(jumlah * hpp_satuan) FROM garansi_sparepart_keluar WHERE service_id = 12')->fetchColumn();
        if ($hpp !== 710000) {
            throw new \RuntimeException('HPP dua klaim berbeda tidak tersimpan terpisah.');
        }
        foreach ($connection->query('SELECT id, jumlah, hpp_satuan FROM garansi_sparepart_keluar')->fetchAll(\PDO::FETCH_ASSOC) as $issue) {
            $amount = (int) $issue['jumlah'] * (int) $issue['hpp_satuan'];
            $connection->exec("INSERT INTO jurnal VALUES ('5000000001', {$amount}, 0, {$issue['id']}, 'garansi_sparepart_keluar')");
            $connection->exec("INSERT INTO jurnal VALUES ('1000000001', 0, {$amount}, {$issue['id']}, 'garansi_sparepart_keluar')");
        }
        $connection->exec("INSERT INTO kredit_retur_suplier VALUES (1, 'disetujui', 220000, 20000, '2000000001', '1000000002', '4000000001')");
        $connection->exec('INSERT INTO alokasi_kredit_retur_suplier VALUES (1, 200000)');
        $connection->exec("INSERT INTO jurnal VALUES
            ('2000000001', 200000, 0, 1, 'kredit_retur_suplier'),
            ('1000000002', 220000, 0, 1, 'kredit_retur_suplier'),
            ('1000000002', 0, 200000, 1, 'kredit_retur_suplier'),
            ('4000000001', 0, 220000, 1, 'kredit_retur_suplier')");

        $all = static fn (string $query): array => $connection->query($query)->fetchAll(\PDO::FETCH_ASSOC);
        $warrantyReconciliation = \App\Services\Finance\WarrantyJournalReconciliation::summarize(
            $all('SELECT id, jumlah, hpp_satuan FROM garansi_sparepart_keluar'),
            $all("SELECT * FROM jurnal WHERE tabel_referensi = 'garansi_sparepart_keluar'")
        );
        $creditReconciliation = \App\Services\Finance\SupplierCreditReconciliation::summarize(
            $all('SELECT * FROM kredit_retur_suplier'),
            $all('SELECT * FROM alokasi_kredit_retur_suplier'),
            $all("SELECT * FROM jurnal WHERE tabel_referensi = 'kredit_retur_suplier'")
        );
        $balance = \App\Services\Finance\BalanceSheetReconciliation::summarize(
            $all('SELECT no_akun, SUM(debet) AS debet, SUM(kredit) AS kredit FROM jurnal GROUP BY no_akun')
        );
        if ($warrantyReconciliation['hpp_total'] !== 710000
            || $warrantyReconciliation['tanpa_jurnal'] !== 0
            || $warrantyReconciliation['tidak_sesuai'] !== 0
            || $creditReconciliation['sisa_kredit'] !== 20000
            || $creditReconciliation['alokasi_tidak_sesuai'] !== 0
            || $creditReconciliation['jurnal_tidak_sesuai'] !== 0
            || $balance['selisih_jurnal'] !== 0 || $balance['selisih'] !== 0) {
            throw new \RuntimeException('Rekonsiliasi stok, kredit, jurnal, atau Neraca sintetis tidak sesuai.');
        }
        echo "MySQL sintetis: garansi → retur → kredit → jurnal → Neraca lulus.\n";
    } finally {
        $connection->exec("DROP DATABASE `{$databaseName}`");
    }
}
