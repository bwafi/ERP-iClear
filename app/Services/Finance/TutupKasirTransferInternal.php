<?php

namespace App\Services\Finance;

use App\Libraries\ModeKasBank;

/**
 * Setor / Tarik untuk saldo Tutup Kasir.
 *
 * ARAH (dampak ke saldo KAS laci):
 *
 *   SETOR  KAS -> BANK   kas laci berkurang          `arahKas = ARAH_KELUAR`
 *   TARIK  BANK -> KAS   kas laci bertambah          `arahKas = ARAH_MASUK`
 *
 * Formula saldo akhir KAS:
 *
 *     Saldo Akhir = Saldo Awal + Kas Masuk - Kas Keluar - Setor + Tarik
 *
 * DEFINISI TIDAK DIDUPLIKASI:
 *   Angka Setor/Tarik diambil dari `KasBankSourceMovement::rincianMovement()`,
 *   yang sudah menjadi satu-satunya definisi "transfer internal" di codebase ini
 *   (lihat docblock `transferInternal()` di sana: "supaya definisi transfer
 *   internal hanya ada di satu tempat"). Jadi yang berlaku di Tutup Kasir
 *   PASTI sama dengan yang berlaku di Finance & Cash Flow:
 *
 *     - hanya baris `transaksi_kas_bank` dengan `transfer_ref IS NOT NULL`
 *     - hanya rekening KAS unit tsb
 *
 *   Legacy mirror (`sumber_tipe='kas_keluar'`, `transfer_ref IS NULL`) tidak
 *   punya marker transfer, jadi otomatis tersaring dan tidak pernah ikut
 *   terhitung di sini.
 *
 * BATAS `jenis` — kenapa ini penting untuk Tutup Kasir secara khusus:
 *   `transfer_ref IS NOT NULL` ternyata lebih longgar dari "transfer internal".
 *   `KasBank::bayarAntarUnit()` juga menulis `transfer_ref`, dengan
 *   `jenis = PEMBAYARAN_ANTAR_UNIT`. Kalau transfer antar unit itu menyentuh
 *   rekening KAS, marker `transfer_ref` sendirian akan menghitungnya —
 *   padahal arus antarunit BUKAN Setor/Tarik laci.
 *
 *   Di Finance hal itu tidak apa-apa: yang dihitung adalah posisi rekening
 *   secara utuh, dan arus antarunit memang movement. Di Tutup Kasir tidak —
 *   angka ini langsung mengurangi saldo laci. Jadi kelas ini meminta
 *   `jenis = TRANSFER_INTERNAL` secara eksplisit lewat parameter opsional
 *   `rincianMovement()`, sementara jalur Finance memakai default (tanpa filter
 *   jenis) sehingga angka Finance tidak ikut berubah.
 *
 * TIDAK ADA PENULISAN:
 *   Kelas ini murni membaca. Setor/Tarik tetap dibuat lewat
 *   `KasBankSetorTarikService` (dengan idempotency `submission_key` +
 *   `transfer_ref`), TIDAK lewat kas_masuk / kas_keluar, dan TIDAK lewat
 *   Tutup Kasir. Kalau admin ingin mengurangi kas di laci, dia memakai fitur
 *   Setor — Tutup Kasir tidak pernah mengarang perpindahan dana.
 *
 * @see \App\Services\Finance\KasBankSourceMovement::rincianMovement()
 * @see \App\Services\Finance\KasBankSetorTarikService
 */
class TutupKasirTransferInternal
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Setor & Tarik KAS laci unit pada satu tanggal.
     *
     * @return array{ada:bool, setor:int, tarik:int, akun_kas_bank_id:int|null, pesan:string}
     *   `ada` = true hanya kalau ada transfer internal nyata pada tanggal itu.
     *   Nilai 0 yang sah (tidak ada setor/tarik) tetap `ada` = false supaya
     *   view tidak menampilkan baris kosong yang tidak perlu.
     */
    public function hariIni(int $unitId, string $tanggal, ?int $akunKasId = null): array
    {
        $kosong = [
            'ada'              => false,
            'setor'            => 0,
            'tarik'            => 0,
            'akun_kas_bank_id' => $akunKasId,
            'pesan'            => 'Belum ada Setor/Tarik pada tanggal ini.',
        ];

        if ($unitId <= 0) {
            $kosong['pesan'] = 'Unit tidak valid, jadi Setor/Tarik tidak bisa dihitung.';

            return $kosong;
        }

        if ($akunKasId === null) {
            $akunKasId = (new TutupKasirSaldoAwal($this->db))->akunKasUnit($unitId);
        }

        if ($akunKasId === null || $akunKasId <= 0) {
            $kosong['pesan'] = 'Belum ada rekening KAS unit ini, jadi Setor/Tarik tidak bisa dihitung.';

            return $kosong;
        }

        $r = (new KasBankSourceMovement($this->db))->rincianMovement(
            $akunKasId,
            $unitId,
            $tanggal,
            $tanggal,
            // Hanya movement Setor/Tarik. Lihat catatan panjang di docblock.
            ModeKasBank::JENIS_TRANSFER
        );

        // Di rekening KAS: KELUAR = kas laci ke bank (Setor), MASUK = bank ke kas (Tarik).
        $setor = (int) ($r['transfer_keluar'] ?? 0);
        $tarik = (int) ($r['transfer_masuk'] ?? 0);
        $ada   = ($setor !== 0 || $tarik !== 0);

        return [
            'ada'              => $ada,
            'setor'            => $setor,
            'tarik'            => $tarik,
            'akun_kas_bank_id' => (int) $akunKasId,
            'pesan'            => $ada
                ? sprintf('Setor Rp %s, Tarik Rp %s.', number_format($setor, 0, ',', '.'), number_format($tarik, 0, ',', '.'))
                : 'Belum ada Setor/Tarik pada tanggal ini.',
        ];
    }
}