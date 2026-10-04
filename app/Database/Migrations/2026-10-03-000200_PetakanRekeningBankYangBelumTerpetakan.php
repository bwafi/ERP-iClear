<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Petakan rekening bank yang SUDAH ADA di tabel `bank` ke akun_kas_bank.
 *
 * MASALAH YANG DIPERBAIKI
 * ----------------------
 * Tabel `bank` punya 5 rekening, tapi akun_kas_bank hanya memetakan 3
 * (idbank 3, 4, 5). idbank 1 dan 2 dipakai ratusan kali di kas_keluar,
 * namun tidak punya akun fisik sama sekali, sehingga resolveAkun() selalu
 * null untuk keduanya dan baris kas_keluar historis itu tidak pernah masuk
 * ke transaksi_kas_bank.
 *
 * BUKAN MENGARANG REKENING
 * ------------------------
 * Row akun_kas_bank di sini diturunkan apa adanya dari tabel `bank` yang
 * sudah ada: nama bank, nomor rekening, dan atas nama. Tidak ada rekening
 * fiktif dan tidak ada COA baru; COA memakai kode yang sudah terdaftar di
 * master no_akun (1010102000 = Kas di Bank).
 *
 * SCOPE — kenapa shared, bukan milik satu unit
 * -------------------------------------------
 * Unit pemakai diambil dari histori transaksi yang nyata:
 *
 *   idbank 1 -> kas_keluar unit 1, 3, 4      (5 + 1 + 124 baris)
 *   idbank 2 -> kas_keluar unit 1, 2, 4, 50   (403 + 48 + 2 + 4)
 *
 * Kedua rekening dipakai lebih dari satu unit, jadi tidak bisa diletakkan
 * pada satu `unit_id` (bentuk rekening unit hanya memberi hak ke unit itu).
 * Bentuknya dibuat shared (unit_id NULL + is_shared 1), lalu tiap unit
 * pemakai diberi satu baris di alokasi_saldo_kas_bank. Cakupan scope-nya
 * persis seperti realita transaksi: tidak melebar ke unit yang tidak pernah
 * memakai rekening tersebut.
 *
 * PENTING soal alokasi_saldo_kas_bank
 * ----------------------------------
 * Kolom `nominal` sengaja diisi 0. Di model sekarang tabel ini punya dua
 * fungsi: (a) menyatakan unit mana yang punya HAK atas rekening shared, dan
 * (b) membagi saldo awal untuk laporan. Yang dipastikan di sini adalah (a).
 * Menulis nominal 0 berarti:
 *   - tidak ada angka saldo karangan yang masuk ke laporan;
 *   - saldo_awal_kas_bank rekening ini tetap kosong;
 *   - admin tetap bisa menginput angka riilnya lewat /kas_bank/akun tanpa
 *     menimpa keputusan scope di migration ini.
 *
 * Yang SENGAJA TIDAK dilakukan:
 *   - tidak membuat rekening baru di tabel `bank`;
 *   - tidak mengubah, menonaktifkan, atau memindai ulang akun yang sudah
 *     terpetakan (termasuk tidak menyentuh is_finance_ho);
 *   - tidak mengubah atau membuat baris transaksi apa pun;
 *   - tidak menjalankan backfill transaksi_kas_bank.
 */
class PetakanRekeningBankYangBelumTerpetakan extends Migration
{
    /** COA Kas di Bank — sudah ada di master no_akun. */
    private const COA_KAS_BANK = '1010102000';

    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('bank')) {
            return;
        }

        $banks = $this->db->table('bank')
            ->where('jenis_bank', 'bank')
            ->orderBy('idbank', 'ASC')
            ->get()
            ->getResult();

        foreach ($banks as $bank) {
            $idBank = (string) $bank->idbank;

            // Aturan main: 1 rekening fisik = 1 akun. Sudah terpetakan -> lewati.
            $sudahAda = $this->db->table('akun_kas_bank')
                ->where('bank_idbank', $idBank)
                ->countAllResults();
            if ($sudahAda > 0) {
                continue;
            }

            $unitPemakai = $this->unitPemakai($idBank);

            // Rekening yang belum pernah dipakai unit mana pun TIDAK punya
            // entitlements, jadi tidak ada unit yang bisa memakainya. Membuatnya
            // sebagai shared tanpa alokasi hanya menghasilkan rekening mati
            // yang gagal diam-diam saat resolveAkun(). Lebih baik dilewati agar
            // ditandai "belum terpetakan" dan owner-nya ditentukan manual.
            if ($unitPemakai === []) {
                log_message('warning', sprintf(
                    '[Migration PetakanRekeningBank] idbank=%s (%s) belum pernah dipakai transaksi — dilewati, tentukan manual.',
                    $idBank,
                    $this->namaAkun($bank)
                ));

                continue;
            }

            $data = [
                'unit_id'      => count($unitPemakai) === 1 ? array_key_first($unitPemakai) : null,
                'tipe'         => 'BANK',
                'nama_akun'    => $this->namaAkun($bank),
                'bank_idbank'  => $idBank,
                'no_akun_coa'  => self::COA_KAS_BANK,
                'status'       => 'aktif',
                'is_shared'    => count($unitPemakai) === 1 ? 0 : 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];

            $this->db->table('akun_kas_bank')->insert($data);
            $akunId = (int) $this->db->insertID();

            // Bentuk shared butuh baris alokasi supaya entiteled ke unit mana pun.
            if ((int) $data['is_shared'] === 1) {
                $this->seedEntitlement($akunId, $idBank, $unitPemakai);
            }

            log_message('info', sprintf(
                '[Migration PetakanRekeningBank] akun %d dibuat untuk idbank=%s (%s), %d unit pemakai%s.',
                $akunId,
                $idBank,
                $data['nama_akun'],
                count($unitPemakai),
                (int) $data['is_shared'] === 1 ? ' (shared)' : ' (milik unit)'
            ));
        }
    }

    /**
     * Unit mana saja yang MELETAKKAN UANG ke rekening ini, menurut histori nyata.
     *
     * `kas_masuk` berketerangan 'kas awal' SENGAJA DIKECUALIKAN: baris itu
     * carry-forward yang ditulis TutupKasir::tutup() dengan idbank = 1
     * Hardcoded, jadi tidak merekam rekening yang benar-benar dipakai kasir.
     * Memakainya sebagai bukti akan membuat rekening 1 terlihat dipakai
     * semua unit secara palsu.
     *
     * @return array<int, string> unit_id => nama unit
     */
    private function unitPemakai(string $idBank): array
    {
        $unitIds = [];

        foreach ([
            'SELECT DISTINCT idunit FROM kas_keluar WHERE idbank = ?',
            "SELECT DISTINCT idunit FROM kas_masuk  WHERE idbank = ? AND (deskripsi IS NULL OR LOWER(TRIM(deskripsi)) <> 'kas awal')",
        ] as $sql) {
            foreach ($this->db->query($sql, [$idBank])->getResult() as $row) {
                $unitId = (int) ($row->idunit ?? 0);
                if ($unitId > 0) {
                    $unitIds[$unitId] = true;
                }
            }
        }

        $hasil = [];
        foreach (array_keys($unitIds) as $unitId) {
            $nama = $this->db->table('unit')->select('NAMA_UNIT')->where('idunit', $unitId)->get()->getRow();
            $hasil[$unitId] = $nama ? (string) $nama->NAMA_UNIT : 'Unit ' . $unitId;
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Satu baris alokasi (entitlement) per unit pemakai, nominal 0.
     * Idempotent lewat unique key (akun_kas_bank_id, unit_id).
     *
     * @param array<int, string> $unitPemakai
     */
    private function seedEntitlement(int $akunId, string $idBank, array $unitPemakai): void
    {
        if (! $this->db->tableExists('alokasi_saldo_kas_bank')) {
            return;
        }

        foreach ($unitPemakai as $unitId => $namaUnit) {
            $ada = $this->db->table('alokasi_saldo_kas_bank')
                ->where('akun_kas_bank_id', $akunId)
                ->where('unit_id', $unitId)
                ->countAllResults();
            if ($ada > 0) {
                continue;
            }

            $this->db->table('alokasi_saldo_kas_bank')->insert([
                'akun_kas_bank_id' => $akunId,
                'unit_id'          => $unitId,
                'nominal'          => 0,
                'keterangan'       => 'Hak pakai dipetakan otomatis dari histori transaksi idbank=' . $idBank
                    . ' (unit ' . $namaUnit . '). Nominal 0 = belum ada alokasi saldo; saldo awal diinput terpisah.',
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Nama rekening mengikuti konvensi akun existing, misalnya
     * "Bank BCA Banyuwangi 1802016667 (ALFARIZKI)".
     * Bagian yang kosong tidak ikut dicetak supaya tidak ada spanda
     * ganda atau tanda kurung kosong.
     */
    private function namaAkun(object $bank): string
    {
        $namaBank  = trim((string) $bank->nama_bank);
        $norek     = trim((string) $bank->norek);
        $atasNama  = trim((string) $bank->atas_nama);

        $nama = trim(implode(' ', array_filter(
            ['Bank', $namaBank, $norek],
            static fn ($v) => $v !== ''
        )));

        if ($atasNama !== '') {
            $nama .= ' (' . $atasNama . ')';
        }

        return $nama === '' ? 'Bank ' . $bank->idbank : $nama;
    }

    /**
     * Menarik pemetaan yang dibuat migration ini.
     *
     * PENTING: yang dihapus HANYA akun yang benar-benar dicatat `up()` —
     * dicocokkan ke `bank` lewat `bank_idbank` DAN nama yang persis sama
     * dengan hasil `namaAkun()`. Tidak sekadar "semua akun ber-COA bank",
     * karena account tersebut juga bisa dibuat admin lewat /kas_bank/akun
     * dan TIDAK boleh ikut terhapus saat rollback.
     *
     * Baris alokasi ber-nominal 0 milik akun itu dihapus; alokasi yang sudah
     * diisi nominal riil oleh admin dipertahankan. Akun yang sudah dipakai
     * transaksi_kas_bank juga dilewati. Tabel transaksi tidak disentuh.
     */
    public function down()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('bank')) {
            return;
        }

        $banks = $this->db->table('bank')
            ->where('jenis_bank', 'bank')
            ->orderBy('idbank', 'ASC')
            ->get()
            ->getResult();

        foreach ($banks as $bank) {
            $idBank = (string) $bank->idbank;

            // Hanya akun dengan nama persis seperti yang akan ditulis up().
            $akuns = $this->db->table('akun_kas_bank')
                ->where('bank_idbank', $idBank)
                ->where('nama_akun', $this->namaAkun($bank))
                ->where('no_akun_coa', self::COA_KAS_BANK)
                ->get()
                ->getResult();

            foreach ($akuns as $akun) {
                $idAkun = (int) $akun->idakun_kas_bank;

                if ($this->db->tableExists('transaksi_kas_bank')
                    && $this->db->table('transaksi_kas_bank')->where('akun_kas_bank_id', $idAkun)->countAllResults() > 0
                ) {
                    continue; // sudah dipakai transaksi -> jangan dihapus
                }

                if ($this->db->tableExists('alokasi_saldo_kas_bank')) {
                    $this->db->table('alokasi_saldo_kas_bank')
                        ->where('akun_kas_bank_id', $idAkun)
                        ->where('nominal', 0)
                        ->delete();
                }

                $this->db->table('akun_kas_bank')->where('idakun_kas_bank', $idAkun)->delete();
            }
        }
    }
}
