<?php
/**
 * Matriks rincian gaji untuk lampiran invoice.
 *
 * @var array<int,array{nama:string,nominal:int,komponen:array<int,array{nama:string,nominal:int}>}> $rows
 * @var int $total
 */
$komponenUrutan = [
    'Gaji Pokok', 'Tunjangan Kinerja', 'Tunjangan Absensi',
    'Tunjangan Penempatan', 'Insentif', 'Lembur', 'Penyesuaian payroll',
];

$komponenKolom = [];
$totalKolom    = [];
foreach ($rows as $row) {
    foreach (($row['komponen'] ?? []) as $k) {
        $nama = (string) ($k['nama'] ?? '');
        if ($nama === '') {
            continue;
        }
        if (! isset($totalKolom[$nama])) {
            $komponenKolom[] = $nama;
            $totalKolom[$nama] = 0;
        }
        $totalKolom[$nama] += (int) round($k['nominal'] ?? 0);
    }
}
usort($komponenKolom, static function (string $a, string $b) use ($komponenUrutan): int {
    $pa = array_search($a, $komponenUrutan, true);
    $pb = array_search($b, $komponenUrutan, true);

    return ($pa === false ? 99 : $pa) <=> ($pb === false ? 99 : $pb) ?: strcmp($a, $b);
});
$labelKomponen = static fn (string $nama): string => str_replace(
    ['Tunjangan ', 'Penyesuaian payroll'],
    ['Tunj. ', 'Penyesuaian'],
    $nama
);
?>
<table class="gaji-matrix">
    <thead>
        <tr>
            <th class="col-nama">Karyawan</th>
            <?php foreach ($komponenKolom as $nama): ?>
                <th><?= esc($labelKomponen($nama)) ?></th>
            <?php endforeach; ?>
            <th class="col-jumlah">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $perKomponen = [];
            foreach (($row['komponen'] ?? []) as $k) {
                $nama = (string) ($k['nama'] ?? '');
                $perKomponen[$nama] = ($perKomponen[$nama] ?? 0) + (int) round($k['nominal'] ?? 0);
            }
            ?>
            <tr>
                <td class="col-nama"><?= esc($row['nama'] ?? '-') ?></td>
                <?php foreach ($komponenKolom as $nama): ?>
                    <?php $nilai = $perKomponen[$nama] ?? 0; ?>
                    <td class="<?= $nilai === 0 ? 'nol' : '' ?>">
                        <?= $nilai === 0 ? '&ndash;' : number_format($nilai, 0, ',', '.') ?>
                    </td>
                <?php endforeach; ?>
                <td class="col-jumlah"><?= number_format((int) ($row['nominal'] ?? 0), 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td class="col-nama">Jumlah</td>
            <?php foreach ($komponenKolom as $nama): ?>
                <td><?= number_format($totalKolom[$nama], 0, ',', '.') ?></td>
            <?php endforeach; ?>
            <td class="col-jumlah"><?= number_format((int) $total, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>
