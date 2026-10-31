<?php

if (! function_exists('terbilang_rupiah')) {
    /**
     * Angka rupiah menjadi kata, mis. 41314927 -> "Empat Puluh Satu Juta ...".
     */
    function terbilang_rupiah(int $angka): string
    {
        $negatif = $angka < 0;
        $kata    = trim(_terbilang(abs($angka))) . ' Rupiah';

        return ucwords(($negatif ? 'minus ' : '') . $kata);
    }
}

if (! function_exists('_terbilang')) {
    function _terbilang(int $n): string
    {
        if ($n === 0) {
            return 'nol';
        }

        $satuan = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan',
            'sembilan', 'sepuluh', 'sebelas',
        ];

        if ($n < 12) {
            return $satuan[$n];
        }
        if ($n < 20) {
            return _terbilang($n - 10) . ' belas';
        }
        if ($n < 100) {
            return _terbilang(intdiv($n, 10)) . ' puluh' . ($n % 10 ? ' ' . _terbilang($n % 10) : '');
        }
        if ($n < 200) {
            return 'seratus' . ($n % 100 ? ' ' . _terbilang($n % 100) : '');
        }
        if ($n < 1000) {
            return _terbilang(intdiv($n, 100)) . ' ratus' . ($n % 100 ? ' ' . _terbilang($n % 100) : '');
        }
        if ($n < 2000) {
            return 'seribu' . ($n % 1000 ? ' ' . _terbilang($n % 1000) : '');
        }
        if ($n < 1000000) {
            return _terbilang(intdiv($n, 1000)) . ' ribu' . ($n % 1000 ? ' ' . _terbilang($n % 1000) : '');
        }
        if ($n < 1000000000) {
            return _terbilang(intdiv($n, 1000000)) . ' juta' . ($n % 1000000 ? ' ' . _terbilang($n % 1000000) : '');
        }
        if ($n < 1000000000000) {
            return _terbilang(intdiv($n, 1000000000)) . ' miliar' . ($n % 1000000000 ? ' ' . _terbilang($n % 1000000000) : '');
        }

        return _terbilang(intdiv($n, 1000000000000)) . ' triliun' . ($n % 1000000000000 ? ' ' . _terbilang($n % 1000000000000) : '');
    }
}
