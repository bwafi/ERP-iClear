<?php

namespace App\Services\Finance;

use App\Models\ModelHutangPiutang;
use App\Models\ModelPembayaranHutangPiutang;
use Config\Database;

/**
 * HutangPiutangService — layer terpusat modul Hutang Piutang.
 *
 * PRINSIP ANTI-DUPLIKASI
 * ----------------------
 * Registry `hutang_piutang` menyimpan posisi SEMUA jenis, tetapi hanya
 * `sumber_tipe` berikut yang authoritative (saldonya dihitung service ini):
 *   - piutang_pelanggan
 *   - kasbon
 *
 * Jenis existing dibaca dari sumbernya (projection, TIDAK ditulis):
 *   - pembelian       -> saldo dari `pembelian.sisa`, bayar di `pembayaran_hutang`
 *   - piutang_legacy  -> saldo dari `piutang.sisa_hutang`, bayar di `pembayaran_piutang`
 *
 * Ringkasan dashboard membaca tiap sumber TEPAT SEKALI sehingga tidak ada
 * double counting.
 */
class HutangPiutangService
{
    public const SUMBER_PEMBELIAN = 'pembelian';
    public const SUMBER_PIUTANG_PELANGGAN = 'piutang_pelanggan';
    public const SUMBER_KASBON = 'kasbon';
    public const SUMBER_PIUTANG_LEGACY = 'piutang_legacy';
    public const SUMBER_JASA_TEKNISI = 'jasa_teknisi';
    public const SUMBER_KELEBIHAN_TRANSFER = 'kelebihan_transfer';
    public const SUMBER_RETUR_BARANG = 'retur_barang';
    public const SUMBER_MANUAL = 'manual';

    public const STATUS_BELUM = 'belum_lunas';
    public const STATUS_SEBAGIAN = 'sebagian';
    public const STATUS_LUNAS = 'lunas';

    /** Enumerasi pihak yang didukung registry. */
    public const PIHAK_SUPLIER = 'suplier';
    public const PIHAK_PELANGGAN = 'pelanggan';
    public const PIHAK_PEGAWAI = 'pegawai';
    public const PIHAK_TEKNISI = 'teknisi';
    public const PIHAK_LAINNYA = 'lainnya';

    /**
     * sumber_tipe yang saldonya dikelola service ini (authoritative).
     *
     * `pembelian` & `piutang_legacy` TIDAK termasuk: saldonya milik tabel
     * sumber masing-masing (projection). Jenis baru (jasa_teknisi,
     * kelebihan_transfer, retur_barang, manual) diinput manual lewat modul ini
     * sehingga saldonya authoritative.
     */
    public const AUTHORITATIVE_SUMBER = [
        self::SUMBER_PIUTANG_PELANGGAN,
        self::SUMBER_KASBON,
        self::SUMBER_MANUAL,
        self::SUMBER_JASA_TEKNISI,
        self::SUMBER_KELEBIHAN_TRANSFER,
        self::SUMBER_RETUR_BARANG,
    ];

    protected $db;
    protected $hp;
    protected $bayar;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->hp = new ModelHutangPiutang();
        $this->bayar = new ModelPembayaranHutangPiutang();
    }

    // =====================================================================
    // Helper status
    // =====================================================================

    public function statusFromSisa(int $total, int $sisa, int $dibayar = 0): string
    {
        if ($sisa <= 0) {
            return self::STATUS_LUNAS;
        }
        if ($dibayar > 0) {
            return self::STATUS_SEBAGIAN;
        }
        return self::STATUS_BELUM;
    }

    public static function labelStatus(string $status): string
    {
        $map = [
            self::STATUS_BELUM => 'Belum Lunas',
            self::STATUS_SEBAGIAN => 'Sebagian',
            self::STATUS_LUNAS => 'Lunas',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    // =====================================================================
    // Penomoran
    // =====================================================================

    /**
     * Kode: HP-<SEG>-<Ymd><unit><seq4>.
     * SEG: HUT (hutang) / PLG (piutang pelanggan) / KSB (kasbon) / PGW (legacy).
     */
    public function generateKode(string $jenis, string $sumberTipe, ?int $unitId = null): string
    {
        if ($sumberTipe === self::SUMBER_KASBON) {
            $seg = 'KSB';
        } elseif ($sumberTipe === self::SUMBER_PIUTANG_PELANGGAN) {
            $seg = 'PLG';
        } elseif ($sumberTipe === self::SUMBER_PIUTANG_LEGACY) {
            $seg = 'PGW';
        } elseif ($sumberTipe === self::SUMBER_JASA_TEKNISI) {
            $seg = 'JST';
        } elseif ($sumberTipe === self::SUMBER_KELEBIHAN_TRANSFER) {
            $seg = 'KLB';
        } elseif ($sumberTipe === self::SUMBER_RETUR_BARANG) {
            $seg = 'RTB';
        } elseif ($sumberTipe === self::SUMBER_MANUAL) {
            $seg = 'MNU';
        } else {
            $seg = $jenis === 'hutang' ? 'HUT' : 'PUT';
        }

        $prefix = 'HP-' . $seg . '-' . date('Ymd') . ($unitId ?: '0');

        $last = $this->hp->like('kode', $prefix, 'after')
            ->orderBy('kode', 'DESC')
            ->first();

        $next = 1;
        if ($last) {
            $next = (int) substr($last->kode, -4) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // =====================================================================
    // Posting jurnal
    // =====================================================================

    /**
     * Posting jurnal dari template_jurnal untuk satu nilai.
     *
     * Tidak menggagalkan transaksi bila template belum tersedia (dicatat ke log),
     * agar data operasional tetap tersimpan meski COA belum siap.
     */
    private function postJurnal(string $tanggal, string $kodeTemplate, int $jumlah, string $keterangan, $idReferensi, string $tabelReferensi, ?int $unitId, ?int $inputBy): void
    {
        if ($jumlah <= 0) {
            return;
        }

        $rows = $this->db->table('template_jurnal')
            ->where('kode_template', $kodeTemplate)
            ->orderBy('idtemplate_jurnal', 'ASC')
            ->get()->getResult();

        if (empty($rows)) {
            log_message('error', 'HutangPiutang: template jurnal tidak ditemukan: ' . $kodeTemplate);
            return;
        }

        if (!$inputBy) {
            $inputBy = (int) session('ID_AKUN');
        }
        $tanggalDb = date('Y-m-d', strtotime($tanggal));

        foreach ($rows as $r) {
            $this->db->table('jurnal')->insert([
                'tanggal'         => $tanggalDb,
                'no_akun'         => $r->no_akun,
                'nama_akun'       => $r->nama_akun,
                'debet'           => $r->debet_kredit === 'debet' ? $jumlah : 0,
                'kredit'          => $r->debet_kredit === 'kredit' ? $jumlah : 0,
                'keterangan'      => $keterangan,
                'id_referensi'    => $idReferensi,
                'tabel_referensi' => $tabelReferensi,
                'id_unit'         => $unitId,
                'id_akun'         => $inputBy,
            ]);
        }
    }

    /**
     * Kode template jurnal untuk pembayaran posisi authoritative.
     */
    private function bayarTemplate(string $sumberTipe, string $jenis, string $metode): string
    {
        if ($sumberTipe === self::SUMBER_KASBON) {
            return 'hp_kasbon_bayar_' . $metode;
        }
        if ($sumberTipe === self::SUMBER_PIUTANG_PELANGGAN) {
            return 'hp_piutang_pelanggan_bayar_' . $metode;
        }
        // Jenis manual baru: mapping COA belum disetujui, template kemungkinan
        // belum ada sehingga jurnal tidak terposting (postJurnal mencatat log).
        return 'hp_manual_' . ($jenis === 'hutang' ? 'hutang' : 'piutang') . '_bayar_' . $metode;
    }

    // =====================================================================
    // CRUD posisi authoritative
    // =====================================================================

    /**
     * Buat posisi baru (piutang_pelanggan / kasbon).
     *
     * @return array{success:bool,id?:int,kode?:string,message:string}
     */
    public function createPosition(array $in, ?int $inputBy = null): array
    {
        $jenis = $in['jenis'] ?? '';
        $sumberTipe = $in['sumber_tipe'] ?? '';
        $pihakTipe = $in['pihak_tipe'] ?? '';
        $pihakId = (int) ($in['pihak_id'] ?? 0);
        $total = (int) preg_replace('/[^\d]/', '', (string) ($in['total'] ?? 0));
        $tanggal = $in['tanggal'] ?? date('Y-m-d');
        $jatuhTempo = $in['jatuh_tempo'] ?? null;
        $unitId = isset($in['unit_id']) ? (int) $in['unit_id'] : (int) session('ID_UNIT');

        if (!in_array($jenis, ['hutang', 'piutang'], true)) {
            return ['success' => false, 'message' => 'Jenis transaksi tidak valid.'];
        }
        if (!in_array($sumberTipe, self::AUTHORITATIVE_SUMBER, true)) {
            return ['success' => false, 'message' => 'Sumber transaksi tidak valid untuk input baru.'];
        }

        // Pihak & jenis dipaksa sesuai jenis sumber agar konsisten.
        $forced = [
            self::SUMBER_KASBON => ['pihak' => self::PIHAK_PEGAWAI, 'jenis' => 'piutang'],
            self::SUMBER_PIUTANG_PELANGGAN => ['pihak' => self::PIHAK_PELANGGAN, 'jenis' => 'piutang'],
            self::SUMBER_JASA_TEKNISI => ['pihak' => self::PIHAK_TEKNISI, 'jenis' => 'hutang'],
            self::SUMBER_KELEBIHAN_TRANSFER => ['pihak' => self::PIHAK_SUPLIER, 'jenis' => 'piutang'],
            self::SUMBER_RETUR_BARANG => ['pihak' => self::PIHAK_SUPLIER, 'jenis' => 'piutang'],
        ];
        if (isset($forced[$sumberTipe])) {
            $pihakTipe = $forced[$sumberTipe]['pihak'];
            $jenis = $forced[$sumberTipe]['jenis'];
        }

        if (!in_array($pihakTipe, [self::PIHAK_SUPLIER, self::PIHAK_PELANGGAN, self::PIHAK_PEGAWAI, self::PIHAK_TEKNISI, self::PIHAK_LAINNYA], true)) {
            return ['success' => false, 'message' => 'Tipe pihak tidak valid.'];
        }
        if ($sumberTipe === self::SUMBER_KASBON && $pihakTipe !== self::PIHAK_PEGAWAI) {
            return ['success' => false, 'message' => 'Kasbon harus terkait pegawai.'];
        }
        if ($total <= 0) {
            return ['success' => false, 'message' => 'Nominal transaksi harus lebih dari 0.'];
        }

        $namaInput = trim((string) ($in['nama_pihak'] ?? ''));
        if ($pihakTipe === self::PIHAK_LAINNYA) {
            if ($namaInput === '') {
                return ['success' => false, 'message' => 'Nama pihak wajib diisi untuk tipe "Lainnya".'];
            }
            $pihakId = 0;
            $namaPihak = $namaInput;
        } else {
            if (!$pihakId) {
                return ['success' => false, 'message' => 'Pihak belum dipilih.'];
            }
            $namaPihak = $namaInput !== '' ? $namaInput : (string) $this->resolveNamaPihak($pihakTipe, $pihakId);
            if ($namaPihak === '') {
                return ['success' => false, 'message' => 'Data pihak tidak ditemukan.'];
            }
        }

        // Jenis input manual baru wajib punya keterangan agar mudah dilacak.
        $keterangan = trim((string) ($in['keterangan'] ?? ''));
        $butuhKeterangan = in_array($sumberTipe, [
            self::SUMBER_MANUAL,
            self::SUMBER_JASA_TEKNISI,
            self::SUMBER_KELEBIHAN_TRANSFER,
            self::SUMBER_RETUR_BARANG,
        ], true);
        if ($butuhKeterangan && $keterangan === '') {
            return ['success' => false, 'message' => 'Keterangan wajib diisi.'];
        }

        // Retur/kelebihan transfer tidak boleh dibuat untuk proyeksi transaksi
        // existing yang sama (cegah double count) — walaupun belum diproyeksikan
        // otomatis, sumber_id tetap dibiarkan null dan diisi id registry sendiri.
        $kode = $this->generateKode($jenis, $sumberTipe, $unitId);

        $data = [
            'kode' => $kode,
            'jenis' => $jenis,
            'sumber_tipe' => $sumberTipe,
            'sumber_id' => null,
            'is_projection' => 0,
            'pihak_tipe' => $pihakTipe,
            'pihak_id' => $pihakId ?: null,
            'nama_pihak' => $namaPihak,
            'tanggal' => $tanggal,
            'jatuh_tempo' => $jatuhTempo ?: null,
            'uraian' => $in['uraian'] ?? null,
            'total' => $total,
            'total_dibayar' => 0,
            'sisa' => $total,
            'status' => self::STATUS_BELUM,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'unit_id' => $unitId,
            'input_by' => $inputBy,
            'deleted' => 0,
        ];

        $this->db->transBegin();
        try {
            $id = $this->hp->insert($data, true);
            if (!$id) {
                throw new \RuntimeException('Insert transaksi gagal.');
            }

            // Input manual: sumber_id merefer ke record registry sendiri agar
            // referensi jelas & tidak bertabrakan dengan projection transaksi.
            if (in_array($sumberTipe, [
                self::SUMBER_MANUAL,
                self::SUMBER_JASA_TEKNISI,
                self::SUMBER_KELEBIHAN_TRANSFER,
                self::SUMBER_RETUR_BARANG,
            ], true)) {
                $this->hp->update((int) $id, ['sumber_id' => (int) $id]);
            }

            // Jurnal hanya untuk jenis lama yang COA-nya sudah disetujui. Jenis
            // manual baru menunggu persetujuan mapping COA (tidak digubah di sini).
            if ($sumberTipe === self::SUMBER_KASBON) {
                $this->postJurnal($tanggal, 'hp_kasbon_input', $total, 'Kasbon Pegawai: ' . $kode, (int) $id, 'hutang_piutang', $unitId, $inputBy);
            } elseif ($sumberTipe === self::SUMBER_PIUTANG_PELANGGAN) {
                $this->postJurnal($tanggal, 'hp_piutang_pelanggan_input', $total, 'Piutang Pelanggan: ' . $kode, (int) $id, 'hutang_piutang', $unitId, $inputBy);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()];
        }

        return ['success' => true, 'id' => (int) $id, 'kode' => $kode, 'message' => 'Transaksi berhasil disimpan.'];
    }

    /**
     * Catat pembayaran (penuh/cicilan) untuk posisi authoritative.
     *
     * Validasi: transaksi belum lunas, nominal > 0, nominal <= sisa.
     * Semua dalam DB transaction + row lock.
     *
     * @return array{success:bool,message:string,sisa?:int,status?:string}
     */
    public function bayar(int $positionId, array $in, ?int $inputBy = null): array
    {
        $jumlah = (int) preg_replace('/[^\d]/', '', (string) ($in['jumlah_bayar'] ?? 0));
        $tunai = (int) preg_replace('/[^\d]/', '', (string) ($in['bayar_tunai'] ?? 0));
        $bank = (int) preg_replace('/[^\d]/', '', (string) ($in['bayar_bank'] ?? 0));

        if ($jumlah <= 0) {
            return ['success' => false, 'message' => 'Nominal pembayaran harus lebih dari 0.'];
        }
        if ($tunai + $bank > 0 && ($tunai + $bank) !== $jumlah) {
            $jumlah = $tunai + $bank;
        }
        if ($tunai + $bank <= 0) {
            // Tidak ada rincian metode: anggap seluruhnya tunai agar jurnal tetap terposting.
            $tunai = $jumlah;
        }

        $this->db->transBegin();
        try {
            $row = $this->db->query(
                'SELECT * FROM hutang_piutang WHERE id = ? AND deleted = 0 FOR UPDATE',
                [$positionId]
            )->getRow();

            if (!$row) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
            }
            if (!in_array($row->sumber_tipe, self::AUTHORITATIVE_SUMBER, true) || (int) $row->is_projection === 1) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Jenis transaksi ini tidak mendukung pembayaran di modul ini.'];
            }
            if ($row->status === self::STATUS_LUNAS || (int) $row->sisa <= 0) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Transaksi sudah lunas, tidak dapat dibayar lagi.'];
            }
            if ($jumlah > (int) $row->sisa) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Nominal pembayaran melebihi sisa (' . number_format((int) $row->sisa, 0, ',', '.') . ').'];
            }

            $inserted = $this->bayar->insert([
                'hutang_piutang_id' => $positionId,
                'tanggal_bayar' => $in['tanggal_bayar'] ?? date('Y-m-d'),
                'jumlah_bayar' => $jumlah,
                'bayar_tunai' => $tunai,
                'bayar_bank' => $bank,
                'bank_idbank' => $in['bank_idbank'] ?? null,
                'sumber' => 'manual',
                'referensi_tipe' => null,
                'referensi_id' => null,
                'keterangan' => $in['keterangan'] ?? null,
                'input_by' => $inputBy,
            ]);
            if ($inserted === false) {
                throw new \RuntimeException('Insert pembayaran gagal.');
            }

            $sisa = (int) $row->sisa - $jumlah;
            $dibayar = (int) $row->total_dibayar + $jumlah;
            $status = $this->statusFromSisa((int) $row->total, $sisa, $dibayar);

            $this->hp->update($positionId, [
                'total_dibayar' => $dibayar,
                'sisa' => $sisa,
                'status' => $status,
            ]);

            $tanggalBayar = $in['tanggal_bayar'] ?? date('Y-m-d');
            $keteranganJurnal = 'Pembayaran ' . $row->kode;
            if ($tunai > 0) {
                $this->postJurnal($tanggalBayar, $this->bayarTemplate($row->sumber_tipe, $row->jenis, 'tunai'), $tunai, $keteranganJurnal, (int) $inserted, 'pembayaran_hutang_piutang', (int) $row->unit_id, $inputBy);
            }
            if ($bank > 0) {
                $this->postJurnal($tanggalBayar, $this->bayarTemplate($row->sumber_tipe, $row->jenis, 'bank'), $bank, $keteranganJurnal, (int) $inserted, 'pembayaran_hutang_piutang', (int) $row->unit_id, $inputBy);
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'message' => 'Pembayaran berhasil dicatat.',
                'sisa' => $sisa,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Gagal mencatat pembayaran: ' . $e->getMessage()];
        }
    }

    public function getPembayaran(int $positionId): array
    {
        return $this->bayar->getByPosition($positionId);
    }

    /**
     * Total sisa kasbon aktif seorang pegawai (untuk slip gaji).
     */
    public function getSisaKasbonPegawai(int $pegawaiId, ?int $unitId = null): int
    {
        $q = $this->db->table('hutang_piutang')
            ->selectSum('sisa', 'total')
            ->where('deleted', 0)
            ->where('is_projection', 0)
            ->where('sumber_tipe', self::SUMBER_KASBON)
            ->where('pihak_tipe', 'pegawai')
            ->where('pihak_id', $pegawaiId)
            ->where('status !=', self::STATUS_LUNAS);
        if ($unitId) {
            $q->where('unit_id', $unitId);
        }
        $row = $q->get()->getRow();
        return (int) ($row->total ?? 0);
    }

    // =====================================================================
    // Ringkasan dashboard (anti double counting)
    // =====================================================================

    /**
     * Ringkasan posisi. Setiap sumber dibaca tepat sekali.
     *
     * @param int[]|null $unitIds null = semua unit
     * @return array
     */
    public function getRingkasan(?array $unitIds = null): array
    {
        $unitIds = $unitIds ? array_map('intval', $unitIds) : null;

        // 1. Hutang supplier — dibaca dari `pembelian` (BUKAN registry)
        $qb = $this->db->table('pembelian')
            ->selectSum('sisa', 'sisa')
            ->selectCount('idpembelian', 'jml')
            ->groupStart()
                ->where('sisa >', 0)
                ->orWhere('status !=', 'Lunas')
            ->groupEnd();
        if ($unitIds) {
            $qb->whereIn('unit_idunit', $unitIds);
        }
        $hutangSupplier = $qb->get()->getRow();
        $hutangSupplierSisa = (int) ($hutangSupplier->sisa ?? 0);
        $hutangSupplierJml = (int) ($hutangSupplier->jml ?? 0);

        // 2. Jenis authoritative (piutang pelanggan, kasbon, manual, teknisi,
        //    kelebihan transfer, retur barang) — dibaca dari registry.
        $auth = $this->db->table('hutang_piutang')
            ->select('sumber_tipe, jenis, COUNT(*) AS jml, COALESCE(SUM(sisa),0) AS sisa')
            ->where('deleted', 0)
            ->whereIn('sumber_tipe', self::AUTHORITATIVE_SUMBER)
            ->groupBy('sumber_tipe, jenis');
        if ($unitIds) {
            $auth->whereIn('unit_id', $unitIds);
        }
        $authRows = $auth->get()->getResult();

        $piutangPelangganSisa = 0;
        $piutangPelangganJml = 0;
        $kasbonSisa = 0;
        $kasbonJml = 0;
        $authHutangSisa = 0;
        $authHutangJml = 0;
        $piutangSupplierSisa = 0;
        $piutangSupplierJml = 0;
        $piutangLainSisa = 0;
        $piutangLainJml = 0;
        foreach ($authRows as $r) {
            if ($r->sumber_tipe === self::SUMBER_PIUTANG_PELANGGAN) {
                $piutangPelangganSisa = (int) $r->sisa;
                $piutangPelangganJml = (int) $r->jml;
            } elseif ($r->sumber_tipe === self::SUMBER_KASBON) {
                $kasbonSisa = (int) $r->sisa;
                $kasbonJml = (int) $r->jml;
            } elseif ($r->jenis === 'hutang') {
                $authHutangSisa += (int) $r->sisa;
                $authHutangJml += (int) $r->jml;
            } elseif ($r->sumber_tipe === self::SUMBER_KELEBIHAN_TRANSFER || $r->sumber_tipe === self::SUMBER_RETUR_BARANG) {
                $piutangSupplierSisa += (int) $r->sisa;
                $piutangSupplierJml += (int) $r->jml;
            } else {
                $piutangLainSisa += (int) $r->sisa;
                $piutangLainJml += (int) $r->jml;
            }
        }

        // 3. Piutang pegawai legacy — dibaca dari `piutang` (BUKAN registry)
        $qp = $this->db->table('piutang')
            ->selectSum('sisa_hutang', 'sisa')
            ->selectCount('idpiutang', 'jml')
            ->groupStart()
                ->where('sisa_hutang >', 0)
                ->orWhere('status !=', 1)
            ->groupEnd();
        if ($unitIds) {
            $qp->whereIn('unit_idunit', $unitIds);
        }
        $piutangLegacy = $qp->get()->getRow();
        $piutangLegacySisa = (int) ($piutangLegacy->sisa ?? 0);
        $piutangLegacyJml = (int) ($piutangLegacy->jml ?? 0);

        $totalHutang = $hutangSupplierSisa + $authHutangSisa;
        $totalPiutang = $piutangPelangganSisa + $kasbonSisa + $piutangSupplierSisa + $piutangLainSisa + $piutangLegacySisa;

        // 4. Status & jatuh tempo (dari registry authoritative + sumber existing)
        $jatuhTempo = $this->countJatuhTempo($unitIds);

        return [
            'total_hutang' => $totalHutang,
            'total_piutang' => $totalPiutang,
            'total_hutang_supplier' => $hutangSupplierSisa,
            'total_hutang_lain' => $authHutangSisa,
            'total_piutang_pelanggan' => $piutangPelangganSisa,
            'total_piutang_supplier' => $piutangSupplierSisa,
            'total_piutang_lain' => $piutangLainSisa,
            'total_kasbon' => $kasbonSisa,
            'total_piutang_legacy' => $piutangLegacySisa,
            'jml_hutang' => $hutangSupplierJml,
            'jml_hutang_lain' => $authHutangJml,
            'jml_piutang_pelanggan' => $piutangPelangganJml,
            'jml_piutang_supplier' => $piutangSupplierJml,
            'jml_piutang_lain' => $piutangLainJml,
            'jml_kasbon' => $kasbonJml,
            'jml_piutang_legacy' => $piutangLegacyJml,
            'belum_lunas' => $jatuhTempo['belum_lunas'],
            'sebagian' => $jatuhTempo['sebagian'],
            'lunas' => $jatuhTempo['lunas'],
            'jatuh_tempo' => $jatuhTempo['jatuh_tempo'],
            'terlambat' => $jatuhTempo['terlambat'],
        ];
    }

    /**
     * Hitung status & jatuh tempo tanpa double counting.
     * Registry dipakai HANYA untuk jenis authoritative; jenis existing dihitung
     * dari sumber masing-masing.
     */
    private function countJatuhTempo(?array $unitIds): array
    {
        $today = date('Y-m-d');

        $belum = 0;
        $sebagian = 0;
        $lunas = 0;
        $jatuhTempo = 0;
        $terlambat = 0;

        // Registry authoritative (piutang_pelanggan, kasbon)
        $q = $this->db->table('hutang_piutang')
            ->select('status, sisa, jatuh_tempo')
            ->where('deleted', 0)
            ->whereIn('sumber_tipe', self::AUTHORITATIVE_SUMBER);
        if ($unitIds) {
            $q->whereIn('unit_id', $unitIds);
        }
        foreach ($q->get()->getResult() as $r) {
            $this->tallyStatus($r->status, $r->sisa, $r->jatuh_tempo, $today, $belum, $sebagian, $lunas, $jatuhTempo, $terlambat);
        }

        // Pembelian (hutang supplier)
        $qp = $this->db->table('pembelian')->select('sisa, jatuh_tempo');
        if ($unitIds) {
            $qp->whereIn('unit_idunit', $unitIds);
        }
        foreach ($qp->get()->getResult() as $r) {
            $status = ((int) $r->sisa <= 0) ? self::STATUS_LUNAS : self::STATUS_BELUM;
            $this->tallyStatus($status, $r->sisa, $r->jatuh_tempo, $today, $belum, $sebagian, $lunas, $jatuhTempo, $terlambat);
        }

        // Piutang legacy
        $ql = $this->db->table('piutang')->select('sisa_hutang, jumlah_hutang, jatuh_tempo');
        if ($unitIds) {
            $ql->whereIn('unit_idunit', $unitIds);
        }
        foreach ($ql->get()->getResult() as $r) {
            $sisa = (int) $r->sisa_hutang;
            $total = (int) $r->jumlah_hutang;
            if ($sisa <= 0) {
                $status = self::STATUS_LUNAS;
            } elseif ($total - $sisa > 0) {
                $status = self::STATUS_SEBAGIAN;
            } else {
                $status = self::STATUS_BELUM;
            }
            $this->tallyStatus($status, $sisa, $r->jatuh_tempo, $today, $belum, $sebagian, $lunas, $jatuhTempo, $terlambat);
        }

        return [
            'belum_lunas' => $belum,
            'sebagian' => $sebagian,
            'lunas' => $lunas,
            'jatuh_tempo' => $jatuhTempo,
            'terlambat' => $terlambat,
        ];
    }

    private function tallyStatus($status, $sisa, $jatuhTempo, string $today, int &$belum, int &$sebagian, int &$lunas, int &$jt, int &$terlambat): void
    {
        if ($status === self::STATUS_LUNAS) {
            $lunas++;
            return;
        }
        if ($status === self::STATUS_SEBAGIAN) {
            $sebagian++;
        } else {
            $belum++;
        }
        if (!empty($jatuhTempo)) {
            if ($jatuhTempo < $today) {
                $terlambat++;
            } elseif ($jatuhTempo === $today) {
                $jt++;
            }
        }
    }

    // =====================================================================
    // Listing posisi (registry + refresh projection dari sumber)
    // =====================================================================

    /**
     * @param array $filters jenis,status,sumber_tipe,pihak_tipe,pihak_id,unit_id,bulan,q
     * @return array<int, array> baris siap tampil
     */
    public function listPositions(array $filters = []): array
    {
        $this->refreshProjections($filters);

        $q = $this->db->table('hutang_piutang hp')
            ->select('hp.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = hp.unit_id', 'left')
            ->where('hp.deleted', 0);

        if (!empty($filters['jenis'])) {
            $q->where('hp.jenis', $filters['jenis']);
        }
        if (!empty($filters['status'])) {
            $q->where('hp.status', $filters['status']);
        }
        if (!empty($filters['sumber_tipe'])) {
            $q->where('hp.sumber_tipe', $filters['sumber_tipe']);
        }
        if (!empty($filters['pihak_tipe'])) {
            $q->where('hp.pihak_tipe', $filters['pihak_tipe']);
        }
        if (!empty($filters['pihak_id'])) {
            $q->where('hp.pihak_id', (int) $filters['pihak_id']);
        }
        if (!empty($filters['unit_id'])) {
            $q->where('hp.unit_id', (int) $filters['unit_id']);
        }
        if (!empty($filters['bulan'])) {
            $q->where("DATE_FORMAT(hp.tanggal, '%Y-%m') =", $filters['bulan']);
        }
        if (!empty($filters['q'])) {
            $q->groupStart()
                ->like('hp.kode', $filters['q'])
                ->orLike('hp.nama_pihak', $filters['q'])
                ->orLike('hp.uraian', $filters['q'])
            ->groupEnd();
        }

        return $q->orderBy('hp.tanggal', 'DESC')->orderBy('hp.id', 'DESC')->get()->getResultArray();
    }

    /**
     * Segarkan snapshot saldo baris projection dari sumbernya (tidak mengubah
     * authoritative). Idempotent; dipanggil sebelum listing.
     */
    public function refreshProjections(array $filters = []): void
    {
        $pembelianIds = $this->db->table('hutang_piutang')
            ->select('sumber_id')
            ->where('is_projection', 1)
            ->where('sumber_tipe', self::SUMBER_PEMBELIAN)
            ->where('deleted', 0)
            ->get()->getResultArray();
        $pembelianIds = array_filter(array_map('intval', array_column($pembelianIds, 'sumber_id')));
        if ($pembelianIds) {
            $rows = $this->db->table('pembelian')
                ->select('idpembelian, total_bayar, bayar, sisa, jatuh_tempo')
                ->whereIn('idpembelian', $pembelianIds)
                ->get()->getResult();
            foreach ($rows as $r) {
                $sisa = (int) $r->sisa;
                $dibayar = (int) $r->bayar;
                $this->db->table('hutang_piutang')
                    ->where('sumber_tipe', self::SUMBER_PEMBELIAN)
                    ->where('sumber_id', (int) $r->idpembelian)
                    ->update([
                        'total' => (int) $r->total_bayar,
                        'total_dibayar' => $dibayar,
                        'sisa' => $sisa,
                        'status' => $this->statusFromSisa((int) $r->total_bayar, $sisa, $dibayar),
                        'jatuh_tempo' => $r->jatuh_tempo,
                    ]);
            }
        }

        $legacyIds = $this->db->table('hutang_piutang')
            ->select('sumber_id')
            ->where('is_projection', 1)
            ->where('sumber_tipe', self::SUMBER_PIUTANG_LEGACY)
            ->where('deleted', 0)
            ->get()->getResultArray();
        $legacyIds = array_filter(array_map('intval', array_column($legacyIds, 'sumber_id')));
        if ($legacyIds) {
            $rows = $this->db->table('piutang')
                ->select('idpiutang, jumlah_hutang, sisa_hutang, jatuh_tempo')
                ->whereIn('idpiutang', $legacyIds)
                ->get()->getResult();
            foreach ($rows as $r) {
                $sisa = (int) $r->sisa_hutang;
                $dibayar = (int) $r->jumlah_hutang - $sisa;
                $this->db->table('hutang_piutang')
                    ->where('sumber_tipe', self::SUMBER_PIUTANG_LEGACY)
                    ->where('sumber_id', (int) $r->idpiutang)
                    ->update([
                        'total' => (int) $r->jumlah_hutang,
                        'total_dibayar' => $dibayar,
                        'sisa' => $sisa,
                        'status' => $this->statusFromSisa((int) $r->jumlah_hutang, $sisa, $dibayar),
                        'jatuh_tempo' => $r->jatuh_tempo,
                    ]);
            }
        }
    }

    public function getById(int $id)
    {
        return $this->hp->where('id', $id)->where('deleted', 0)->first();
    }

    /**
     * Riwayat pembayaran gabungan (authoritative + existing) tanpa double count.
     *
     * @param array $filters unit_id, bulan (YYYY-MM), jenis
     * @return array<int, array>
     */
    public function getRiwayatPembayaran(array $filters = []): array
    {
        $rows = [];

        // 1. Authoritative: pembayaran_hutang_piutang
        $q = $this->db->table('pembayaran_hutang_piutang php')
            ->select('php.tanggal_bayar AS tanggal, hp.jenis, hp.sumber_tipe, hp.kode, hp.nama_pihak, php.jumlah_bayar, php.sumber, php.keterangan, hp.unit_id')
            ->join('hutang_piutang hp', 'hp.id = php.hutang_piutang_id', 'left')
            ->where('hp.deleted', 0);
        if (!empty($filters['unit_id'])) {
            $q->where('hp.unit_id', (int) $filters['unit_id']);
        }
        if (!empty($filters['jenis'])) {
            $q->where('hp.jenis', $filters['jenis']);
        }
        if (!empty($filters['bulan'])) {
            $q->where("DATE_FORMAT(php.tanggal_bayar, '%Y-%m') =", $filters['bulan']);
        }
        foreach ($q->get()->getResultArray() as $r) {
            $r['sisa'] = null;
            $rows[] = $r;
        }

        // 2. Hutang supplier: pembayaran_hutang + pembelian
        $q2 = $this->db->table('pembayaran_hutang ph')
            ->select("ph.tanggal_bayar AS tanggal, 'hutang' AS jenis, 'pembelian' AS sumber_tipe, p.no_nota_supplier AS kode, s.nama_suplier AS nama_pihak, ph.bayar AS jumlah_bayar, 'manual' AS sumber, '' AS keterangan, p.unit_idunit AS unit_id, ph.sisa_hutang AS sisa")
            ->join('pembelian p', 'p.idpembelian = ph.pembelian_idpembelian', 'left')
            ->join('suplier s', 's.id_suplier = p.suplier_id_suplier', 'left');
        if (!empty($filters['unit_id'])) {
            $q2->where('p.unit_idunit', (int) $filters['unit_id']);
        }
        if (!empty($filters['jenis']) && $filters['jenis'] !== 'hutang') {
            $q2->where('1 = 0');
        }
        if (!empty($filters['bulan'])) {
            $q2->where("DATE_FORMAT(ph.tanggal_bayar, '%Y-%m') =", $filters['bulan']);
        }
        foreach ($q2->get()->getResultArray() as $r) {
            $rows[] = $r;
        }

        // 3. Piutang legacy: pembayaran_piutang + piutang
        $q3 = $this->db->table('pembayaran_piutang pp')
            ->select("pt.tanggal AS tanggal, 'piutang' AS jenis, 'piutang_legacy' AS sumber_tipe, pt.kode_piutang AS kode, a.NAMA_AKUN AS nama_pihak, pp.jumlah_bayar, 'manual' AS sumber, '' AS keterangan, pt.unit_idunit AS unit_id, pp.sisa_hutang AS sisa")
            ->join('piutang pt', 'pt.idpiutang = pp.idpiutang', 'left')
            ->join('akun a', 'a.ID_AKUN = pt.pegawai_idpegawai', 'left');
        if (!empty($filters['unit_id'])) {
            $q3->where('pt.unit_idunit', (int) $filters['unit_id']);
        }
        if (!empty($filters['jenis']) && $filters['jenis'] !== 'piutang') {
            $q3->where('1 = 0');
        }
        if (!empty($filters['bulan'])) {
            $q3->where("DATE_FORMAT(pt.tanggal, '%Y-%m') =", $filters['bulan']);
        }
        foreach ($q3->get()->getResultArray() as $r) {
            $rows[] = $r;
        }

        usort($rows, static function ($a, $b) {
            return strcmp((string) $b['tanggal'], (string) $a['tanggal']);
        });

        return $rows;
    }

    /**
     * Opsi jenis/kategori transaksi input manual (authoritative).
     *
     * `jenis` = arah yang dipaksa (hutang/piutang); null berarti mengikuti
     * pilihan user (khusus kategori `manual`).
     */
    public static function inputTypes(): array
    {
        return [
            self::SUMBER_MANUAL => ['jenis' => null, 'label' => 'Manual (bebas)'],
            self::SUMBER_JASA_TEKNISI => ['jenis' => 'hutang', 'label' => 'Hutang Jasa Teknisi'],
            self::SUMBER_KELEBIHAN_TRANSFER => ['jenis' => 'piutang', 'label' => 'Piutang Kelebihan Transfer Supplier'],
            self::SUMBER_RETUR_BARANG => ['jenis' => 'piutang', 'label' => 'Piutang Retur Barang ke Supplier'],
            self::SUMBER_PIUTANG_PELANGGAN => ['jenis' => 'piutang', 'label' => 'Piutang Pelanggan'],
            self::SUMBER_KASBON => ['jenis' => 'piutang', 'label' => 'Kasbon Pegawai'],
        ];
    }

    /**
     * Opsi tipe pihak yang tersedia untuk suatu arah.
     *
     * @return array<string,string> tipe => label
     */
    public static function pihakTypes(string $jenis): array
    {
        if ($jenis === 'hutang') {
            return [
                self::PIHAK_SUPLIER => 'Supplier',
                self::PIHAK_TEKNISI => 'Teknisi',
                self::PIHAK_LAINNYA => 'Lainnya',
            ];
        }
        return [
            self::PIHAK_SUPLIER => 'Supplier',
            self::PIHAK_PELANGGAN => 'Customer',
            self::PIHAK_PEGAWAI => 'Karyawan',
            self::PIHAK_LAINNYA => 'Lainnya',
        ];
    }

    /**
     * Label kelompok tampilan (HUTANG SUPPLIER / TEKNISI / LAINNYA dst).
     */
    public static function labelKelompok(string $jenis, string $pihakTipe): string
    {
        $prefix = $jenis === 'hutang' ? 'Hutang' : 'Piutang';
        $map = [
            self::PIHAK_SUPLIER => 'Supplier',
            self::PIHAK_PELANGGAN => 'Customer',
            self::PIHAK_PEGAWAI => 'Karyawan',
            self::PIHAK_TEKNISI => 'Teknisi',
            self::PIHAK_LAINNYA => 'Lainnya',
        ];
        return $prefix . ' ' . ($map[$pihakTipe] ?? ucfirst($pihakTipe));
    }

    // =====================================================================
    // Integrasi Payroll: potong penuh sisa kasbon
    // =====================================================================

    /**
     * Potong penuh sisa kasbon pegawai saat payroll dibayar.
     *
     * Idempotent: bila referensi payroll sudah pernah dipakai, tidak memotong
     * lagi (UNIQUE uniq_php_referensi + guard existsByReferensi).
     *
     * @return array{success:bool,potongan:int,jumlah_kasbon:int,message:string}
     */
    public function settleKasbonFromPayroll(int $payrollId, int $pegawaiId, ?int $unitId = null, ?int $inputBy = null): array
    {
        if ($this->bayar->existsByReferensi('finance_payroll', $payrollId)) {
            return ['success' => true, 'potongan' => 0, 'jumlah_kasbon' => 0, 'message' => 'Settlement payroll sudah pernah dilakukan (dilewati).'];
        }

        $q = $this->db->table('hutang_piutang')
            ->where('deleted', 0)
            ->where('is_projection', 0)
            ->where('sumber_tipe', self::SUMBER_KASBON)
            ->where('pihak_tipe', 'pegawai')
            ->where('pihak_id', $pegawaiId)
            ->where('status !=', self::STATUS_LUNAS)
            ->where('sisa >', 0);
        if ($unitId) {
            $q->where('unit_id', $unitId);
        }
        $kasbons = $q->orderBy('tanggal', 'ASC')->get()->getResult();

        if (empty($kasbons)) {
            return ['success' => true, 'potongan' => 0, 'jumlah_kasbon' => 0, 'message' => 'Tidak ada kasbon aktif.'];
        }

        $this->db->transBegin();
        try {
            $totalPotongan = 0;

            foreach ($kasbons as $kasbon) {
                // Lock per baris agar aman dari pembayaran paralel.
                $row = $this->db->query(
                    'SELECT * FROM hutang_piutang WHERE id = ? FOR UPDATE',
                    [$kasbon->id]
                )->getRow();
                if (!$row || (int) $row->sisa <= 0) {
                    continue;
                }

                $bayar = (int) $row->sisa;
                if ($bayar <= 0) {
                    continue;
                }

                $inserted = $this->bayar->insert([
                    'hutang_piutang_id' => (int) $row->id,
                    'tanggal_bayar' => date('Y-m-d'),
                    'jumlah_bayar' => $bayar,
                    'bayar_tunai' => 0,
                    'bayar_bank' => 0,
                    'bank_idbank' => null,
                    'sumber' => 'payroll',
                    'referensi_tipe' => 'finance_payroll',
                    'referensi_id' => $payrollId,
                    'keterangan' => 'Potongan gaji (payroll #' . $payrollId . ')',
                    'input_by' => $inputBy,
                ]);
                if ($inserted === false) {
                    throw new \RuntimeException('Insert settlement payroll gagal (payroll #' . $payrollId . ').');
                }

                $dibayar = (int) $row->total_dibayar + $bayar;
                $this->hp->update((int) $row->id, [
                    'total_dibayar' => $dibayar,
                    'sisa' => 0,
                    'status' => self::STATUS_LUNAS,
                ]);

                $this->postJurnal(
                    date('Y-m-d'),
                    'hp_kasbon_potong_payroll',
                    $bayar,
                    'Potongan kasbon ' . $row->kode . ' (payroll #' . $payrollId . ')',
                    (int) $inserted,
                    'pembayaran_hutang_piutang',
                    $unitId ? (int) $unitId : (int) $row->unit_id,
                    $inputBy
                );

                $totalPotongan += $bayar;
            }

            $this->db->transCommit();

            return [
                'success' => true,
                'potongan' => $totalPotongan,
                'jumlah_kasbon' => count($kasbons),
                'message' => 'Potongan kasbon dicatat.',
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'potongan' => 0, 'jumlah_kasbon' => 0, 'message' => 'Gagal settlement kasbon: ' . $e->getMessage()];
        }
    }

    // =====================================================================
    // Sinkronisasi projection dari transaksi existing
    // =====================================================================

    /**
     * Upsert baris projection dari pembelian (hutang supplier).
     */
    public function syncFromPembelian(int $pembelianId): void
    {
        $p = $this->db->table('pembelian p')
            ->select('p.*, s.nama_suplier')
            ->join('suplier s', 's.id_suplier = p.suplier_id_suplier', 'left')
            ->where('p.idpembelian', $pembelianId)
            ->get()->getRow();
        if (!$p) {
            return;
        }

        $sisa = (int) $p->sisa;
        $dibayar = (int) $p->bayar;
        $data = [
            'jenis' => 'hutang',
            'sumber_tipe' => self::SUMBER_PEMBELIAN,
            'sumber_id' => $pembelianId,
            'is_projection' => 1,
            'pihak_tipe' => 'suplier',
            'pihak_id' => $p->suplier_id_suplier,
            'nama_pihak' => $p->nama_suplier ?: '-',
            'tanggal' => $p->tanggal_masuk,
            'jatuh_tempo' => $p->jatuh_tempo,
            'uraian' => $p->no_nota_supplier,
            'total' => (int) $p->total_bayar,
            'total_dibayar' => $dibayar,
            'sisa' => $sisa,
            'status' => $this->statusFromSisa((int) $p->total_bayar, $sisa, $dibayar),
            'unit_id' => $p->unit_idunit,
            'deleted' => 0,
        ];

        $existing = $this->hp->findBySumber(self::SUMBER_PEMBELIAN, $pembelianId);
        if ($existing) {
            unset($data['sumber_tipe'], $data['sumber_id']);
            $this->hp->update($existing->id, $data);
            return;
        }

        $data['kode'] = 'HP-HUT-PB' . $pembelianId;
        $data['input_by'] = null;
        $this->hp->insert($data);
    }

    /**
     * Upsert baris projection dari piutang pegawai legacy.
     */
    public function syncFromPiutangLegacy(int $piutangId): void
    {
        $p = $this->db->table('piutang pt')
            ->select('pt.*, a.NAMA_AKUN')
            ->join('akun a', 'a.ID_AKUN = pt.pegawai_idpegawai', 'left')
            ->where('pt.idpiutang', $piutangId)
            ->get()->getRow();
        if (!$p) {
            return;
        }

        $sisa = (int) $p->sisa_hutang;
        $total = (int) $p->jumlah_hutang;
        $data = [
            'jenis' => 'piutang',
            'sumber_tipe' => self::SUMBER_PIUTANG_LEGACY,
            'sumber_id' => $piutangId,
            'is_projection' => 1,
            'pihak_tipe' => 'pegawai',
            'pihak_id' => $p->pegawai_idpegawai,
            'nama_pihak' => $p->NAMA_AKUN ?: '-',
            'tanggal' => $p->tanggal,
            'jatuh_tempo' => $p->jatuh_tempo,
            'uraian' => $p->kode_piutang,
            'total' => $total,
            'total_dibayar' => $total - $sisa,
            'sisa' => $sisa,
            'status' => $this->statusFromSisa($total, $sisa, $total - $sisa),
            'unit_id' => $p->unit_idunit,
            'deleted' => 0,
        ];

        $existing = $this->hp->findBySumber(self::SUMBER_PIUTANG_LEGACY, $piutangId);
        if ($existing) {
            unset($data['sumber_tipe'], $data['sumber_id']);
            $this->hp->update($existing->id, $data);
            return;
        }

        $data['kode'] = 'HP-PUT-PG' . $piutangId;
        $data['input_by'] = null;
        $this->hp->insert($data);
    }

    // =====================================================================
    // Resolusi pihak
    // =====================================================================

    public function resolveNamaPihak(string $pihakTipe, int $pihakId): string
    {
        if ($pihakTipe === 'suplier') {
            $r = $this->db->table('suplier')->where('id_suplier', $pihakId)->get()->getRow();
            return $r->nama_suplier ?? '';
        }
        if ($pihakTipe === 'pelanggan') {
            $r = $this->db->table('pelanggan')->where('id_pelanggan', $pihakId)->get()->getRow();
            return $r->nama ?? '';
        }
        if ($pihakTipe === 'pegawai' || $pihakTipe === 'teknisi') {
            $r = $this->db->table('akun')->where('ID_AKUN', $pihakId)->get()->getRow();
            return $r->NAMA_AKUN ?? '';
        }
        return '';
    }

    // =====================================================================
    // Detail transaksi untuk dokumen cetak
    // =====================================================================

    public static function labelSumber(string $sumberTipe): string
    {
        $map = [
            self::SUMBER_PEMBELIAN => 'Hutang Supplier',
            self::SUMBER_PIUTANG_PELANGGAN => 'Piutang Pelanggan',
            self::SUMBER_KASBON => 'Kasbon Pegawai',
            self::SUMBER_PIUTANG_LEGACY => 'Piutang Pegawai',
            self::SUMBER_JASA_TEKNISI => 'Hutang Jasa Teknisi',
            self::SUMBER_KELEBIHAN_TRANSFER => 'Piutang Kelebihan Transfer',
            self::SUMBER_RETUR_BARANG => 'Piutang Retur Barang',
            self::SUMBER_MANUAL => 'Manual',
        ];
        return $map[$sumberTipe] ?? ucfirst(str_replace('_', ' ', $sumberTipe));
    }

    /**
     * Susun identitas & detail transaksi untuk dokumen cetak berdasarkan
     * sumber_tipe. Data diambil dari transaksi sumber (adapter), bukan query
     * dari view.
     *
     * @param object $row baris `hutang_piutang`
     * @return array<string,mixed>
     */
    public function getDetailTransaksi($row): array
    {
        $detail = [
            'sumber_label' => self::labelSumber($row->sumber_tipe),
            'pihak_label' => 'Nama Pihak',
            'pihak_nama' => (string) $row->nama_pihak,
            'referensi_label' => null,
            'referensi' => null,
            'detail' => $row->uraian ?: null,
            'items' => [],
            'meta' => [],
        ];

        switch ($row->sumber_tipe) {
            case self::SUMBER_PEMBELIAN:
                return $this->detailPembelian($row, $detail);
            case self::SUMBER_PIUTANG_LEGACY:
                return $this->detailPiutangLegacy($row, $detail);
            case self::SUMBER_KASBON:
                return $this->detailKasbon($row, $detail);
            case self::SUMBER_PIUTANG_PELANGGAN:
                return $this->detailPiutangPelanggan($row, $detail);
            case self::SUMBER_JASA_TEKNISI:
            case self::SUMBER_MANUAL:
            case self::SUMBER_KELEBIHAN_TRANSFER:
            case self::SUMBER_RETUR_BARANG:
                return $this->detailManual($row, $detail);
        }

        return $detail;
    }

    /**
     * Detail untuk baris authoritative input manual (termasuk jasa teknisi,
     * kelebihan transfer, retur barang). Mengambil identitas pihak dari master.
     */
    protected function detailManual($row, array $detail): array
    {
        $labels = [
            self::PIHAK_SUPLIER => 'Nama Supplier',
            self::PIHAK_PELANGGAN => 'Nama Pelanggan',
            self::PIHAK_PEGAWAI => 'Nama Karyawan',
            self::PIHAK_TEKNISI => 'Nama Teknisi',
            self::PIHAK_LAINNYA => 'Nama Pihak',
        ];
        $detail['pihak_label'] = $labels[$row->pihak_tipe] ?? 'Nama Pihak';

        if ((int) $row->pihak_id > 0) {
            $nama = $this->resolveNamaPihak((string) $row->pihak_tipe, (int) $row->pihak_id);
            if ($nama !== '') {
                $detail['pihak_nama'] = $nama;
            }
            $detail['meta'] = array_merge($detail['meta'], $this->metaPihak((string) $row->pihak_tipe, (int) $row->pihak_id));
        }

        // Referensi record registry manual (jelas & tidak menunjuk transaksi lain).
        $detail['referensi_label'] = 'Kode Referensi';
        $detail['referensi'] = $row->kode;
        $detail['detail'] = $row->uraian ?: ($row->keterangan ?: self::labelSumber($row->sumber_tipe));

        return $detail;
    }

    protected function metaPihak(string $pihakTipe, int $pihakId): array
    {
        $meta = [];
        if ($pihakTipe === self::PIHAK_SUPLIER) {
            $r = $this->db->table('suplier')->where('id_suplier', $pihakId)->get()->getRow();
            if ($r) {
                if (!empty($r->no_hp)) {
                    $meta[] = ['label' => 'No. HP Supplier', 'value' => $r->no_hp];
                }
                if (!empty($r->alamat)) {
                    $meta[] = ['label' => 'Alamat Supplier', 'value' => $r->alamat];
                }
            }
        } elseif ($pihakTipe === self::PIHAK_PELANGGAN) {
            $r = $this->db->table('pelanggan')->where('id_pelanggan', $pihakId)->get()->getRow();
            if ($r) {
                if (!empty($r->no_hp)) {
                    $meta[] = ['label' => 'No. HP', 'value' => $r->no_hp];
                }
                if (!empty($r->alamat)) {
                    $meta[] = ['label' => 'Alamat', 'value' => $r->alamat];
                }
            }
        } elseif ($pihakTipe === self::PIHAK_PEGAWAI || $pihakTipe === self::PIHAK_TEKNISI) {
            $r = $this->db->table('akun')->where('ID_AKUN', $pihakId)->get()->getRow();
            if ($r && !empty($r->HP)) {
                $meta[] = ['label' => 'No. HP', 'value' => $r->HP];
            }
        }
        return $meta;
    }

    protected function detailKasbon($row, array $detail): array
    {
        $detail['sumber_label'] = 'Kasbon Pegawai';
        $detail['pihak_label'] = 'Nama Pegawai';

        $pegawai = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, HP')
            ->where('ID_AKUN', (int) $row->pihak_id)
            ->get()->getRow();
        if ($pegawai) {
            $detail['pihak_nama'] = $pegawai->NAMA_AKUN ?: $detail['pihak_nama'];
            if (!empty($pegawai->HP)) {
                $detail['meta'][] = ['label' => 'No. HP Pegawai', 'value' => $pegawai->HP];
            }
        }

        $potong = $this->db->table('pembayaran_hutang_piutang')
            ->where('hutang_piutang_id', (int) $row->id)
            ->where('referensi_tipe', 'finance_payroll')
            ->orderBy('id', 'DESC')
            ->get()->getRow();
        if ($potong && !empty($potong->referensi_id)) {
            $detail['referensi_label'] = 'Referensi Potong Gaji';
            $detail['referensi'] = 'Payroll #' . $potong->referensi_id;
        }

        $detail['detail'] = $row->uraian ?: 'Kasbon Pegawai';

        return $detail;
    }

    protected function detailPiutangPelanggan($row, array $detail): array
    {
        $detail['sumber_label'] = 'Piutang Pelanggan';
        $detail['pihak_label'] = 'Nama Pelanggan';

        $pel = $this->db->table('pelanggan')
            ->where('id_pelanggan', (int) $row->pihak_id)
            ->get()->getRow();
        if ($pel) {
            $detail['pihak_nama'] = $pel->nama ?: $detail['pihak_nama'];
            if (!empty($pel->no_hp)) {
                $detail['meta'][] = ['label' => 'No. HP', 'value' => $pel->no_hp];
            }
            if (!empty($pel->alamat)) {
                $detail['meta'][] = ['label' => 'Alamat', 'value' => $pel->alamat];
            }
        }

        // Nomor referensi service/penjualan "jika tersedia" (dideteksi dari
        // uraian/keterangan lalu diambil detailnya dari transaksi sumber).
        $ref = $this->resolveSourceReference(trim((string) $row->uraian . ' ' . (string) $row->keterangan));
        if ($ref) {
            $detail['referensi_label'] = $ref['referensi_label'];
            $detail['referensi'] = $ref['referensi'];
            if (!empty($ref['items'])) {
                $detail['items'] = $ref['items'];
            }
            if (!empty($ref['detail'])) {
                if (empty($detail['detail'])) {
                    $detail['detail'] = $ref['detail'];
                } else {
                    $detail['meta'][] = ['label' => $ref['detail_label'] ?? 'Detail Sumber', 'value' => $ref['detail']];
                }
            }
            $detail['meta'] = array_merge($detail['meta'], $ref['meta']);
        }

        if (empty($detail['detail'])) {
            $detail['detail'] = 'Piutang Pelanggan';
        }

        return $detail;
    }

    protected function detailPembelian($row, array $detail): array
    {
        $detail['sumber_label'] = 'Hutang Supplier';
        $detail['pihak_label'] = 'Nama Supplier';

        if (empty($row->sumber_id)) {
            return $detail;
        }

        $p = $this->db->table('pembelian pb')
            ->select('pb.*, s.nama_suplier, s.no_hp, s.alamat')
            ->join('suplier s', 's.id_suplier = pb.suplier_id_suplier', 'left')
            ->where('pb.idpembelian', (int) $row->sumber_id)
            ->get()->getRow();
        if (!$p) {
            return $detail;
        }

        $detail['pihak_nama'] = $p->nama_suplier ?: $detail['pihak_nama'];
        $detail['referensi_label'] = 'No. Nota Supplier';
        $detail['referensi'] = $p->no_nota_supplier ?: ('Pembelian #' . $p->idpembelian);
        if (!empty($p->no_hp)) {
            $detail['meta'][] = ['label' => 'No. HP Supplier', 'value' => $p->no_hp];
        }
        if (!empty($p->tanggal_masuk)) {
            $detail['meta'][] = ['label' => 'Tanggal Pembelian', 'value' => date('d-m-Y', strtotime($p->tanggal_masuk))];
        }
        $detail['items'] = $this->itemsPembelian((int) $p->idpembelian);
        $detail['detail'] = $row->uraian ?: ('Pembelian #' . $p->idpembelian);

        return $detail;
    }

    protected function detailPiutangLegacy($row, array $detail): array
    {
        $detail['sumber_label'] = 'Piutang Pegawai';
        $detail['pihak_label'] = 'Nama Pegawai';

        if (empty($row->sumber_id)) {
            return $detail;
        }

        $pt = $this->db->table('piutang pt')
            ->select('pt.*, a.NAMA_AKUN, a.HP')
            ->join('akun a', 'a.ID_AKUN = pt.pegawai_idpegawai', 'left')
            ->where('pt.idpiutang', (int) $row->sumber_id)
            ->get()->getRow();
        if (!$pt) {
            return $detail;
        }

        $detail['pihak_nama'] = $pt->NAMA_AKUN ?: $detail['pihak_nama'];
        $detail['referensi_label'] = 'Kode Piutang';
        $detail['referensi'] = $pt->kode_piutang ?: ('Piutang #' . $pt->idpiutang);
        if (!empty($pt->HP)) {
            $detail['meta'][] = ['label' => 'No. HP Pegawai', 'value' => $pt->HP];
        }
        $detail['detail'] = $row->uraian ?: ('Piutang #' . $pt->idpiutang);

        return $detail;
    }

    /**
     * Deteksi nomor referensi service/invoice penjualan di teks bebas lalu
     * ambil detailnya dari transaksi sumber. Kembalikan null bila tidak ada.
     */
    protected function resolveSourceReference(string $text): ?array
    {
        if ($text === '' || !preg_match_all('/\b[A-Z]{2,6}[-\/][A-Z0-9\-\/]{2,}\b/i', $text, $m)) {
            return null;
        }

        foreach (array_unique(array_map('strtoupper', $m[0])) as $token) {
            $svc = $this->db->table('service')->where('no_service', $token)->get()->getRow()
                ?: $this->db->table('service')->like('no_service', $token)->get()->getRow();
            if ($svc) {
                return [
                    'referensi_label' => 'No. Service',
                    'referensi' => $svc->no_service,
                    'detail' => $svc->keluhan ?: null,
                    'detail_label' => 'Keluhan / Detail Service',
                    'items' => $this->itemsService((int) $svc->idservice),
                    'meta' => array_values(array_filter([
                        !empty($svc->tipe_hp) ? ['label' => 'Tipe HP', 'value' => $svc->tipe_hp] : null,
                        !empty($svc->imei) ? ['label' => 'IMEI', 'value' => $svc->imei] : null,
                    ])),
                ];
            }

            $jual = $this->db->table('penjualan')->where('kode_invoice', $token)->get()->getRow()
                ?: $this->db->table('penjualan')->like('kode_invoice', $token)->get()->getRow();
            if ($jual) {
                return [
                    'referensi_label' => 'No. Invoice Penjualan',
                    'referensi' => $jual->kode_invoice,
                    'detail' => $jual->keterangan ?: null,
                    'detail_label' => 'Keterangan Penjualan',
                    'items' => $this->itemsPenjualan((int) $jual->idpenjualan),
                    'meta' => [],
                ];
            }
        }

        return null;
    }

    protected function itemsPembelian(int $pembelianId): array
    {
        $rows = $this->db->table('detail_pembelian d')
            ->select('d.jumlah, d.hrg_beli, d.total_harga, d.satuan_beli, b.nama_barang')
            ->join('barang b', 'b.idbarang = d.barang_idbarang', 'left')
            ->where('d.pembelian_idpembelian', $pembelianId)
            ->get()->getResult();

        return $this->mapItems($rows, 'nama_barang', 'jumlah', 'hrg_beli', 'total_harga', 'satuan_beli');
    }

    protected function itemsPenjualan(int $penjualanId): array
    {
        $rows = $this->db->table('detail_penjualan d')
            ->select('d.jumlah, d.harga_penjualan, d.sub_total, d.satuan_jual, b.nama_barang')
            ->join('barang b', 'b.idbarang = d.barang_idbarang', 'left')
            ->where('d.penjualan_idpenjualan', $penjualanId)
            ->get()->getResult();

        return $this->mapItems($rows, 'nama_barang', 'jumlah', 'harga_penjualan', 'sub_total', 'satuan_jual');
    }

    protected function itemsService(int $serviceId): array
    {
        $rows = $this->db->table('service_sparepart d')
            ->select('d.jumlah, d.harga_penjualan, d.sub_total, d.satuan_jual, b.nama_barang')
            ->join('barang b', 'b.idbarang = d.barang_idbarang', 'left')
            ->where('d.service_idservice', $serviceId)
            ->get()->getResult();

        return $this->mapItems($rows, 'nama_barang', 'jumlah', 'harga_penjualan', 'sub_total', 'satuan_jual');
    }

    protected function mapItems($rows, string $nama, string $qty, string $harga, string $subtotal, string $satuan): array
    {
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'nama' => $r->{$nama} ?: 'Item',
                'qty' => (int) $r->{$qty},
                'satuan' => (string) ($r->{$satuan} ?? ''),
                'harga' => (int) $r->{$harga},
                'subtotal' => (int) $r->{$subtotal},
            ];
        }
        return $items;
    }

    // =====================================================================
    // Kompensasi piutang <-> hutang satu pihak
    // =====================================================================

    /**
     * Baris posisi lawan (jenis berlawanan) milik pihak yang sama yang masih
     * bersaldo, layak dikompensasikan.
     */
    public function getLawanKompensasi($row): array
    {
        if ((int) $row->is_projection === 1
            || !in_array($row->sumber_tipe, self::AUTHORITATIVE_SUMBER, true)
            || empty($row->pihak_id)) {
            return [];
        }

        $lawanJenis = $row->jenis === 'hutang' ? 'piutang' : 'hutang';

        return $this->db->table('hutang_piutang')
            ->where('deleted', 0)
            ->where('is_projection', 0)
            ->whereIn('sumber_tipe', self::AUTHORITATIVE_SUMBER)
            ->where('jenis', $lawanJenis)
            ->where('pihak_tipe', $row->pihak_tipe)
            ->where('pihak_id', (int) $row->pihak_id)
            ->where('status !=', self::STATUS_LUNAS)
            ->where('sisa >', 0)
            ->orderBy('tanggal', 'ASC')
            ->get()->getResult();
    }

    public function getKompensasi(int $positionId): array
    {
        return $this->db->table('kompensasi_hutang_piutang')
            ->groupStart()
                ->where('hutang_piutang_id', $positionId)
                ->orWhere('piutang_piutang_id', $positionId)
            ->groupEnd()
            ->orderBy('tanggal', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResult();
    }

    /**
     * Alokasikan piutang untuk mengurangi hutang satu pihak yang sama.
     *
     * Record asal TIDAK dihapus: sisa kedua sisi dikurangi dan relasi dicatat
     * di `kompensasi_hutang_piutang` + 2 baris audit di
     * `pembayaran_hutang_piutang`. Idempotent dijaga oleh lock baris.
     */
    public function kompensasi(int $hutangId, int $piutangId, int $jumlah, ?string $keterangan = null, ?int $inputBy = null): array
    {
        $jumlah = (int) preg_replace('/[^\d]/', '', (string) $jumlah);
        if ($jumlah <= 0) {
            return ['success' => false, 'message' => 'Nilai kompensasi harus lebih dari 0.'];
        }

        $this->db->transBegin();
        try {
            $h = $this->db->query('SELECT * FROM hutang_piutang WHERE id = ? AND deleted = 0 FOR UPDATE', [$hutangId])->getRow();
            $p = $this->db->query('SELECT * FROM hutang_piutang WHERE id = ? AND deleted = 0 FOR UPDATE', [$piutangId])->getRow();

            if (!$h || !$p) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Transaksi hutang/piutang tidak ditemukan.'];
            }
            if ($h->jenis !== 'hutang' || $p->jenis !== 'piutang') {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Pasangan kompensasi harus terdiri dari hutang dan piutang.'];
            }
            foreach ([$h, $p] as $row) {
                if ((int) $row->is_projection === 1 || !in_array($row->sumber_tipe, self::AUTHORITATIVE_SUMBER, true)) {
                    $this->db->transRollback();
                    return ['success' => false, 'message' => 'Transaksi ' . $row->kode . ' tidak mendukung kompensasi di modul ini.'];
                }
                if ((int) $row->sisa <= 0 || $row->status === self::STATUS_LUNAS) {
                    $this->db->transRollback();
                    return ['success' => false, 'message' => 'Transaksi ' . $row->kode . ' sudah lunas.'];
                }
            }
            if ((int) $h->pihak_id !== (int) $p->pihak_id || $h->pihak_tipe !== $p->pihak_tipe || empty($h->pihak_id)) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Kompensasi hanya untuk pihak yang sama.'];
            }

            $maks = min((int) $h->sisa, (int) $p->sisa);
            if ($jumlah > $maks) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'Nilai kompensasi melebihi sisa terkecil (' . number_format($maks, 0, ',', '.') . ').'];
            }

            $this->db->table('kompensasi_hutang_piutang')->insert([
                'tanggal' => date('Y-m-d'),
                'hutang_piutang_id' => $hutangId,
                'piutang_piutang_id' => $piutangId,
                'jumlah' => $jumlah,
                'pihak_tipe' => $h->pihak_tipe,
                'pihak_id' => (int) $h->pihak_id,
                'keterangan' => $keterangan,
                'input_by' => $inputBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $kompId = (int) $this->db->insertID();
            if (!$kompId) {
                throw new \RuntimeException('Insert kompensasi gagal.');
            }

            $this->applyKompensasiSisi($h, $jumlah, $kompId, 'hutang', $p->kode, $keterangan, $inputBy);
            $this->applyKompensasiSisi($p, $jumlah, $kompId, 'piutang', $h->kode, $keterangan, $inputBy);

            $this->db->transCommit();

            return ['success' => true, 'id' => $kompId, 'message' => 'Kompensasi berhasil dicatat.'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return ['success' => false, 'message' => 'Gagal kompensasi: ' . $e->getMessage()];
        }
    }

    private function applyKompensasiSisi($row, int $jumlah, int $kompId, string $sisi, string $kodeLawan, ?string $keterangan, ?int $inputBy): void
    {
        $inserted = $this->bayar->insert([
            'hutang_piutang_id' => (int) $row->id,
            'tanggal_bayar' => date('Y-m-d'),
            'jumlah_bayar' => $jumlah,
            'bayar_tunai' => 0,
            'bayar_bank' => 0,
            'bank_idbank' => null,
            'sumber' => 'kompensasi',
            'referensi_tipe' => 'kompensasi_' . $sisi,
            'referensi_id' => $kompId,
            'keterangan' => trim('Kompensasi dengan ' . $kodeLawan . ($keterangan ? ' — ' . $keterangan : '')),
            'input_by' => $inputBy,
        ]);
        if ($inserted === false) {
            throw new \RuntimeException('Insert audit kompensasi gagal (sisi ' . $sisi . ').');
        }

        $sisa = (int) $row->sisa - $jumlah;
        $dibayar = (int) $row->total_dibayar + $jumlah;
        $this->hp->update((int) $row->id, [
            'total_dibayar' => $dibayar,
            'sisa' => $sisa,
            'status' => $this->statusFromSisa((int) $row->total, $sisa, $dibayar),
        ]);
    }

    public function getPelangganOptions(): array
    {
        return $this->db->table('pelanggan')
            ->select('id_pelanggan, nama')
            ->groupStart()
                ->where('deleted', '0')
                ->orWhere('deleted IS NULL')
            ->groupEnd()
            ->orderBy('nama', 'ASC')
            ->get()->getResult();
    }

    public function getSuplierOptions(): array
    {
        return $this->db->table('suplier')
            ->select('id_suplier, nama_suplier')
            ->groupStart()
                ->where('deleted', '0')
                ->orWhere('deleted IS NULL')
            ->groupEnd()
            ->orderBy('nama_suplier', 'ASC')
            ->get()->getResult();
    }

    public function getPegawaiOptions(?int $unitId = null): array
    {
        $q = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, ID_UNIT')
            ->groupStart()
                ->where('deleted', 0)
                ->orWhere('deleted IS NULL')
            ->groupEnd();
        if ($unitId) {
            $q->where('ID_UNIT', $unitId);
        }
        return $q->orderBy('NAMA_AKUN', 'ASC')->get()->getResult();
    }

    /**
     * Opsi teknisi = akun yang jabatannya mengandung "teknisi".
     */
    public function getTeknisiOptions(?int $unitId = null): array
    {
        $q = $this->db->table('akun a')
            ->select('a.ID_AKUN, a.NAMA_AKUN, a.ID_UNIT, j.NAMA_JABATAN')
            ->join('jabatan j', 'j.ID_JABATAN = a.ID_JABATAN', 'left')
            ->groupStart()
                ->where('a.deleted', 0)
                ->orWhere('a.deleted IS NULL')
            ->groupEnd()
            ->like('j.NAMA_JABATAN', 'teknisi');
        if ($unitId) {
            $q->where('a.ID_UNIT', $unitId);
        }
        return $q->orderBy('a.NAMA_AKUN', 'ASC')->get()->getResult();
    }

    public function getBankOptions(): array
    {
        return $this->db->table('bank')->orderBy('nama_bank', 'ASC')->get()->getResult();
    }

    public function getUnitOptions(): array
    {
        return $this->db->table('unit')->orderBy('NAMA_UNIT', 'ASC')->get()->getResult();
    }
}
