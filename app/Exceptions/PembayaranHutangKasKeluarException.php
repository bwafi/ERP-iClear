<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pembayaran hutang tidak bisa dicatat sebagai kas keluar + jurnal.
 *
 * Dipisahkan dari RuntimeException generik supaya controller bisa menampilkan
 * alasan bisnis (kategori TEMPO hilang, template jurnal tidak seimbang,
 * rekening tidak dipilih) tanpa ikut menelan pesan error database mentah.
 *
 * Dilempar SEBELUM baris pertama ditulis, sehingga pemanggil cukup rollback
 * transaksi yang sudah berjalan.
 */
class PembayaranHutangKasKeluarException extends RuntimeException
{
}
