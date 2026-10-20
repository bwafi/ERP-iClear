<?php
/**
 * Backfill mutasi lama yang sudah diterima secara fisik tetapi masih status=0.
 * Hanya jalankan --execute setelah kecocokan fisik per nota diverifikasi.
 *
 * Dry-run:
 *   php app/Scripts/backfill_mutasi_legacy.php --month=2026-09
 *
 * Eksekusi:
 *   php app/Scripts/backfill_mutasi_legacy.php --month=2026-09 --execute
 *
 * Yang dilakukan saat --execute:
 *  - backup tabel mutasi + hutang_piutang
 *  - status=1, tanggal_terima memakai tanggal_terima lama / tanggal_kirim
 *  - buat pasangan H/P legacy lewat ModeKasBank::buatHutangPiutangDariMutasi()
 *  - verifikasi jumlah status dan pasangan H/P
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';

$paths = new Config\Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

$_SESSION = [];
$month = null;
$execute = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--execute') {
        $execute = true;
    } elseif (strpos($arg, '--month=') === 0) {
        $month = substr($arg, 8);
    }
}

if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    fwrite(STDERR, "Usage: php app/Scripts/backfill_mutasi_legacy.php --month=YYYY-MM [--execute]\n");
    exit(1);
}

$mulai = $month . '-01 00:00:00';
$selesai = date('Y-m-01 00:00:00', strtotime($mulai . ' +1 month'));
$db = Config\Database::connect();
$db->transStrict(false);

$rows = $db->query(
    "SELECT m.idmutasi, m.no_nota_mutasi, m.tanggal_kirim, m.tanggal_terima,
            ku.NAMA_UNIT AS unit_kirim, tu.NAMA_UNIT AS unit_terima,
            COUNT(d.iddetail_mutasi) AS jml_barang,
            COALESCE(SUM(d.jumlah_kirim), 0) AS qty_kirim,
            COALESCE(SUM(d.jumlah_terima), 0) AS qty_terima
     FROM mutasi m
     LEFT JOIN unit ku ON ku.idunit = m.kirim_idunit
     LEFT JOIN unit tu ON tu.idunit = m.terima_idunit
     LEFT JOIN detail_mutasi d ON d.mutasi_idmutasi = m.idmutasi
     WHERE m.status = 0
       AND m.tanggal_kirim >= ?
       AND m.tanggal_kirim < ?
     GROUP BY m.idmutasi, m.no_nota_mutasi, m.tanggal_kirim, m.tanggal_terima, ku.NAMA_UNIT, tu.NAMA_UNIT
     ORDER BY m.tanggal_kirim ASC, m.idmutasi ASC",
    [$mulai, $selesai]
)->getResult();

echo 'Mode: ' . ($execute ? 'EKSEKUSI' : 'DRY-RUN') . "\n";
echo 'Periode: ' . $mulai . ' s/d ' . $selesai . "\n";
echo 'Kandidat: ' . count($rows) . " mutasi\n\n";

$fmtQty = static function ($value): string {
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
};

$ids = [];
$totalQty = 0;
foreach ($rows as $row) {
    $ids[] = (int) $row->idmutasi;
    $totalQty += (float) $row->qty_terima;
    echo sprintf(
        "ID %d | %s | %s | %s -> %s | %d barang | qty terima %s\n",
        $row->idmutasi,
        $row->no_nota_mutasi,
        $row->tanggal_kirim,
        $row->unit_kirim,
        $row->unit_terima,
        $row->jml_barang,
        $fmtQty($row->qty_terima)
    );
}
echo "\nTotal qty terima: " . $fmtQty($totalQty) . "\n";

if (!$execute) {
    echo "\nDry-run selesai. Tidak ada data yang diubah.\n";
    exit(0);
}

if ($ids === []) {
    echo "Tidak ada kandidat. Selesai.\n";
    exit(0);
}

$label = str_replace('-', '', $month);
$suffix = date('Ymd_His');
$backupMutasi = 'mutasi_backup_legacy_' . $label . '_' . $suffix;
$backupHp = 'hutang_piutang_backup_legacy_' . $label . '_' . $suffix;

$db->query("CREATE TABLE `{$backupMutasi}` AS SELECT * FROM `mutasi`");
$db->query("CREATE TABLE `{$backupHp}` AS SELECT * FROM `hutang_piutang`");
echo "Backup: {$backupMutasi}, {$backupHp}\n";

$lib = new App\Libraries\ModeKasBank();
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$db->transBegin();

try {
    foreach ($rows as $row) {
        $id = (int) $row->idmutasi;
        $tanggalTerima = $row->tanggal_terima ?: $row->tanggal_kirim;

        $db->table('mutasi')->where('idmutasi', $id)->update([
            'status'         => '1',
            'tanggal_terima' => $tanggalTerima,
        ]);

        $hasil = $lib->buatHutangPiutangDariMutasi($id);
        if (!in_array($hasil['status'] ?? '', ['inserted', 'skipped'], true)) {
            throw new RuntimeException('Mutasi #' . $id . ': ' . ($hasil['reason'] ?? 'gagal'));
        }

        echo "OK #{$id} {$row->no_nota_mutasi} -> {$hasil['status']}\n";
    }

    if ($db->transStatus() === false) {
        throw new RuntimeException('Transaksi database gagal.');
    }
    $db->transCommit();
} catch (Throwable $e) {
    $db->transRollback();
    fwrite(STDERR, "ROLLBACK: {$e->getMessage()}\n");
    exit(1);
}

$status = $db->query(
    "SELECT COUNT(*) AS c FROM mutasi WHERE idmutasi IN ({$placeholders}) AND status = 1",
    $ids
)->getRow()->c;
$hp = $db->query(
    "SELECT COUNT(*) AS c FROM hutang_piutang WHERE sumber_tipe = 'mutasi_unit' AND deleted = 0 AND sumber_id IN ({$placeholders})",
    $ids
)->getRow()->c;
$hpActive = $db->query(
    "SELECT COUNT(*) AS c FROM hutang_piutang WHERE sumber_tipe = 'mutasi_unit' AND deleted = 0 AND scope = 'active' AND sumber_id IN ({$placeholders})",
    $ids
)->getRow()->c;

echo "\nVerifikasi:\n";
echo "  status=1: {$status}/" . count($ids) . "\n";
echo "  baris H/P: {$hp}/" . (count($ids) * 2) . "\n";
echo "  H/P aktif (harus 0): {$hpActive}\n";
echo "  backup mutasi: " . $db->query("SELECT COUNT(*) AS c FROM `{$backupMutasi}`")->getRow()->c . "\n";
echo "  backup H/P: " . $db->query("SELECT COUNT(*) AS c FROM `{$backupHp}`")->getRow()->c . "\n";

if ((int) $status !== count($ids) || (int) $hp !== count($ids) * 2 || (int) $hpActive !== 0) {
    fwrite(STDERR, "VERIFIKASI GAGAL. Periksa backup: {$backupMutasi}, {$backupHp}\n");
    exit(1);
}

echo "\nBackfill selesai dan terverifikasi.\n";
