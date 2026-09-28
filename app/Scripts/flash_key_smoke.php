<?php
/**
 * Smoke test kunci flash message.
 *
 * Views/template.php hanya merender flash 'sukses' (toastr.success) dan
 * 'gagal' (toastr.warning). Tapi controller mencampur tiga nama lain yang
 * tidak pernah dirender layout, sehingga pesannya hilang tanpa jejak:
 *
 *   - setFlashdata/->with('sukses')  -> tampil (mayoritas, 160-an call site)
 *   - ->with('success')              -> TIDAK tampil
 *   - ->with('error')                -> TIDAK tampil
 *   - ->with('info')                 -> TIDAK tampil
 *
 * Contoh paling nyata: menyimpan Kerusakan di /service sama sekali tidak
 * memunculkan toast, padahal operasinya sukses.
 *
 * Yang Diganti: 7 controller yang halaman tujuannya tidak merender flash
 * sendiri. ->with('sukses')/'gagal' dipetakan ke key yang benar, karena
 * RedirectResponse::with() memang menulis flashdata.
 *
 * Yang SENGAJA tidak diganti: modul penilaian/marketing/konten, karena view-nya
 * memang merender 'success'/'error' sendiri dengan alert inline. Kalau ikut
 * diubah jadi 'sukses'/'gagal', alert-nya jadi dobel (inline + toast).
 *
 * Invariant yang dijaga test ini:
 *   1. 7 controller tersebut bebas dari key success/error/info
 *   2. 4 modul yang self-render tetap memakai 'success'
 *   3. layout hanya merender 'sukses' dan 'gagal', dan meng-escape pesan sukses
 *   4. tidak ada halaman yang self-render flash jadi tujuan redirect dari 7
 *      controller itu (kalau ada, pesannya akan tampil dua kali)
 *
 * Jalankan: php app/Scripts/flash_key_smoke.php
 */

$root = dirname(__DIR__, 2);

/** Controller yang halaman tujuannya tidak merender flash sendiri. */
$butuhKeyTemplate = [
    'Service',
    'Riwayat_Service',
    'Payroll',
    'StatusGaransi',
    'JadwalMasuk',
    'TutupKasir',
    'Auth',
];

/** Modul yang view-nya sudah merender 'success'/'error' sendiri. */
$pakaiKeySendiri = [
    'PenilaianKPI',
    'Penilaian',
    'Marketing',
    'Konten',
];

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

$keyAsing = static function (string $teks): array {
    preg_match_all('/->with\(\s*[\'"](success|error|info)[\'"]/', $teks, $m);

    return array_values(array_unique(array_map('strtolower', $m[1])));
};

/** Semua file view yang memanggil getFlashdata. */
$selfRender = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views'));
foreach ($rii as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
        $teks = file_get_contents($f->getPathname());
        if (strpos($teks, 'getFlashdata') !== false) {
            $selfRender[] = $f->getPathname();
        }
    }
}

echo "1. Controller tanpa view flash sendiri -> wajib 'sukses'/'gagal'\n";
$tujuanRedirect = [];
foreach ($butuhKeyTemplate as $c) {
    $file = $root . '/app/Controllers/' . $c . '.php';
    $teks = file_get_contents($file);
    $sisa = $keyAsing($teks);
    check(
        $c . ': tidak ada key success/error/info',
        $sisa === [],
        'masih ada: ' . implode(', ', $sisa)
    );

    // Catat tujuan redirect untuk invariant no. 4. redirect()->back() dan
    // view() tidak bisa dipetakan ke path, jadi hanya string literal.
    preg_match_all('/redirect\(\)->to\(\s*(?:base_url\(\s*)?[\'"]([^\'"]+)/', $teks, $m);
    $tujuanRedirect[$c] = $m[1];
}

echo "\n2. Modul dengan view flash sendiri -> key 'success' TETAP\n";
foreach ($pakaiKeySendiri as $c) {
    $teks = file_get_contents($root . '/app/Controllers/' . $c . '.php');
    $punya = strpos($teks, "with('success'") !== false || strpos($teks, 'with("success"') !== false;
    check($c . ": masih pakai 'success' (view-nya yang merender)", $punya, 'punya=' . var_export($punya, true));
}

echo "\n3. Layout hanya merender 'sukses' dan 'gagal'\n";
$layout = file_get_contents($root . '/app/Views/template.php');
check('layout merender sukses', strpos($layout, "getFlashdata('sukses')") !== false);
check('layout merender gagal', strpos($layout, "getFlashdata('gagal')") !== false);
check(
    'layout tidak merender success/error/info langsung',
    strpos($layout, "getFlashdata('success')") === false
        && strpos($layout, "getFlashdata('error')") === false
        && strpos($layout, "getFlashdata('info')") === false
);
check(
    'layout meng-escape pesan sukses dengan json_encode',
    strpos($layout, "json_encode(session()->getFlashdata('sukses'))") !== false
);

echo "\n4. Tidak ada view self-render flash yang jadi tujuan redirect 7 controller\n";
// Folder pertama dari view, dicocokkan kasar ke path tujuan redirect.
$potensiBentrok = [];
foreach ($tujuanRedirect as $c => $paths) {
    foreach ($paths as $p) {
        $path = trim($p, '/');
        $parts = explode('/', $path);
        $cari = count($parts) >= 2 ? $parts[0] . '/' . $parts[1] : $parts[0];
        foreach ($selfRender as $v) {
            $rel = substr($v, strlen($root . '/app/Views/'));
            $token = strtolower(str_replace('_', '', explode('/', $rel)[0]));
            if (strpos(strtolower(str_replace('_', '', $cari)), $token) !== false) {
                $potensiBentrok[] = "$c -> /$p  vs  " . $rel;
            }
        }
    }
}
check('tidak ada bentrok alert dobel', $potensiBentrok === [], implode('; ', array_unique($potensiBentrok)));

echo "\n5. View modul self-render benar-benar membaca 'success'/'error'\n";
// Kalau view-nya ternyata tidak baca, berarti alasan membiarkan key 'success'
// di controller itu tidak benar dan harus ikut diubah.
foreach ($pakaiKeySendiri as $c) {
    $dasar = strtolower($c);
    $view  = array_merge(
        (array) glob($root . '/app/Views/' . $dasar . '/*.php'),
        (array) glob($root . '/app/Views/' . strtolower(str_replace('KPI', '_kpi', $c)) . '/*.php')
    );
    $ada = false;
    foreach ($view as $v) {
        $t = file_get_contents($v);
        if (strpos($t, "getFlashdata('success')") !== false || strpos($t, "getFlashdata('error')") !== false) {
            $ada = true;
            break;
        }
    }
    check($c . ': view-nya baca success/error (alasan tidak diubah)', $ada, 'dicek ' . count($view) . ' view');
}

echo $fail === 0 ? "\nSemua PASS.\n" : "\n{$fail} FAIL.\n";
exit($fail === 0 ? 0 : 1);
