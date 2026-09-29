<?php
/**
 * Smoke test skala tipografi & menu sidebar Kas & Bank.
 *
 * Latar Belakang
 * --------------
 * Modul kas_bank sebelumnya punya TIGA skala font yang saling lepas, terpisah
 * dari sisa aplikasi:
 *
 *   - dashboard.php  -> kelas karangan sendiri (font-size-9 s/d font-size-15),
 *                       satu-satunya view se-ERP yang memakainya
 *   - transfer.php   -> fs-8 (~22px) untuk body text
 *   - antar_unit.php -> fs-8 (~22px) untuk body text
 *   - akun.php       -> token sendiri (--kb-fs-meta/body/title = 10.5/12/13px)
 *
 * Penyebab besarnya: skala `fs-*` di public/template/assets/css/styles.css
 * TERBALIK dari Bootstrap 5.3 standar. Template Madminate ini memakai
 * fs-1 = 0.625rem (terkecil) ... fs-6 = 1.25rem, sementara Bootstrap 5.3
 * memakai fs-1 = terbesar dan fs-6 = 0.875rem. Jadi `fs-6` yang dipakai
 * se-ERP sebagai "ikon kecil" sebenarnya 20px -- lebih besar dari body 14.4px.
 *
 * Fix: semua ukuran font keempat halaman disatukan ke kas_bank/_theme.php
 * memakai token yang nilainya sama persis dengan fs-1..fs-5 yang dipakai
 * seluruh aplikasi:
 *
 *   --kb-fs-metric 1.125rem (18px) = fs-5
 *   --kb-fs-title  1rem     (16px) = fs-4
 *   --kb-fs-body   0.875rem (14px) = fs-3
 *   --kb-fs-meta   0.75rem  (12px) = fs-2
 *   --kb-fs-micro  0.625rem (10px) = fs-1
 *
 * Class kb-card-header / kb-card-title / kb-card-sub / kb-step-badge /
 * kb-banner / kb-main / btn-xs yang dipakai transfer.php & antar_unit.php
 * TIDAK PERNAH terdefinisi di halaman itu (style-nya hanya ada di akun.php),
 * jadi keduanya dirender tanpa kartu sama sekali. Sekarang terpusat di _theme.php.
 *
 * Menu: parent 10120 "Kas & Bank" punya url = NULL, sementara left_vertical.php
 * meng-hardcode href="#" untuk semua parent, sehingga menu itu tidak bisa
 * diklik. Fix view + migration mengisi url = 'kas_bank'.
 *
 * Invariant yang dijaga test ini:
 *   1. keempat halaman meng-include _nav.php dan _theme.php
 *   2. setiap kelas kb-* yang dipakai terdefinisi di _theme.php/_nav.php
 *   3. tidak ada skala karangan (font-size-N) atau fs-6..fs-14 di modul ini
 *   4. token font _theme.php == nilai fs-* yang dipakai aplikasi
 *   5. parent sidebar memakai url menu, bukan href="#" hardcode
 *   6. parent sidebar tanpa url tetap '#' (tidak jadi navigasi ke base_url)
 *
 * Jalankan: php app/Scripts/kas_bank_tipografi_smoke.php
 */

$root = dirname(__DIR__, 2);
$dir  = $root . '/app/Views/kas_bank';

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

$halaman = ['akun', 'dashboard', 'transfer', 'antar_unit'];

/** Buang blok <style> dan <script> supaya yang diperiksa benar-benar markup. */
$markup = static function (string $teks): string {
    $teks = preg_replace('#<style>.*?</style>#s', '', $teks);
    $teks = preg_replace('#<script>.*?</script>#s', '', $teks);

    return $teks;
};

echo "1. Keempat halaman meng-include _nav.php dan _theme.php\n";
foreach ($halaman as $h) {
    $t = file_get_contents("$dir/$h.php");
    check(
        "$h.php meng-include _nav.php + _theme.php",
        strpos($t, "include('kas_bank/_nav')") !== false
        && strpos($t, "include('kas_bank/_theme')") !== false
    );
}

echo "\n2. Semua kelas kb-* yang dipakai benar-benar terdefinisi\n";
$cssTheme = file_get_contents("$dir/_theme.php");
$cssNav   = file_get_contents("$dir/_nav.php");
$terdefinisi = [];
foreach ([$cssTheme, $cssNav] as $css) {
    preg_match_all('/\.(kb-[a-z0-9-]+)/', $css, $m);
    foreach ($m[1] as $c) {
        $terdefinisi[$c] = true;
    }
}

$takTerdefinisi = [];
foreach ($halaman as $h) {
    $html = $markup(file_get_contents("$dir/$h.php"));
    preg_match_all('/class=(?:"([^"]*)"|\'([^\']*)\')/', $html, $m);
    foreach ($m[1] + $m[2] as $attr) {
        foreach (preg_split('/\s+/', (string) $attr) as $c) {
            if (strpos($c, 'kb-') === 0 && ! isset($terdefinisi[$c])) {
                $takTerdefinisi[] = "$h: $c";
            }
        }
    }
}
$takTerdefinisi = array_values(array_unique($takTerdefinisi));
check(
    'tidak ada kelas kb-* tanpa definisi',
    $takTerdefinisi === [],
    implode('; ', $takTerdefinisi)
);

echo "\n3. Tidak ada skala font karangan / fs-6..fs-14 di modul ini\n";
foreach ($halaman as $h) {
    $t = file_get_contents("$dir/$h.php");
    $html = $markup($t);
    // fs-6 s/d fs-14 = skala terbalik, terlalu besar untuk body/kolom tabel
    preg_match_all('/\bfs-(?:[6-9]|1[0-4])\b/', $html, $m1);
    // kelas karangan lama milik dashboard.php
    preg_match_all('/\bfont-size-\d+\b/', $html, $m2);
    $bocor = array_merge($m1[0], $m2[0]);
    check("$h.php bebas skala karangan", $bocor === [], implode(', ', array_unique($bocor)));
}

echo "\n4. Token font _theme.php sama dengan nilai fs-* aplikasi\n";
$harapan = [
    '--kb-fs-metric' => '1.125rem', // fs-5
    '--kb-fs-title'  => '1rem',     // fs-4
    '--kb-fs-body'   => '0.875rem', // fs-3
    '--kb-fs-meta'   => '0.75rem',  // fs-2
    '--kb-fs-micro'  => '0.625rem', // fs-1
];
foreach ($harapan as $var => $nilai) {
    preg_match('/' . preg_quote($var, '/') . '\s*:\s*([^;]+);/', $cssTheme, $m);
    $aktor = isset($m[1]) ? trim($m[1]) : '(tidak ada)';
    check("$var = $nilai", $aktor === $nilai, "aktual: $aktor");
}

echo "\n5. Parent sidebar memakai url menu, bukan href=\"#\" hardcode\n";
$sidebar = file_get_contents($root . '/app/Views/inc/left_vertical.php');
$polaParentHref = '/\$parentHref\s*=\s*!empty\(\s*\$mymenu\[\'url\'\]\s*\)\s*\?\s*base_url\(\s*\$mymenu\[\'url\'\]\s*\)\s*:\s*\'#\'/';
check(
    'parent pakai $parentHref dari url menu',
    preg_match($polaParentHref, $sidebar) === 1
);
$parentAnchor = null;
if (preg_match('/<a class="sidebar-link has-arrow[^"]*"\s*\n?\s*href="([^"]*)"/', $sidebar, $m)) {
    $parentAnchor = trim($m[1]);
}
check(
    'anchor parent tidak lagi href="#"',
    $parentAnchor === '<?= $parentHref ?>',
    'href="' . var_export($parentAnchor, true) . '"'
);
check(
    'parent tanpa url tetap "#" (tidak navigasi ke base_url)',
    strpos($sidebar, ": '#';") !== false
);

echo "\n6. Migrasi url menu 10120 tersedia\n";
$migrasi = glob($root . '/app/Database/Migrations/*MakeKasBankMenuClickable.php');
check('file migrasi MakeKasBankMenuClickable ada', count($migrasi) === 1);
if ($migrasi) {
    $mig = file_get_contents($migrasi[0]);
    check('migrasi mengisi url 10120 = kas_bank', strpos($mig, "MENU_ID = 10120") !== false
        && strpos($mig, "URL = 'kas_bank'") !== false);
    check('migrasi punya down() yang mengembalikan url jadi NULL', strpos($mig, "update(['url' => null])") !== false);
}

echo $fail === 0 ? "\nSemua PASS.\n" : "\n{$fail} FAIL.\n";
exit($fail === 0 ? 0 : 1);
