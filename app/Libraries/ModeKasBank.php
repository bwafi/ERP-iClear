<?php

namespace App\Libraries;

use App\Models\ModelAkunKasBank;
use App\Models\ModelDetailMutasi;
use App\Models\ModelHutangPiutang;
use App\Models\ModelKasKeluar;
use App\Models\ModelKasMasuk;
use App\Models\ModelMutasiStok;
use App\Models\ModelPembayaranHutang;
use App\Models\ModelPembayaranPiutang;
use App\Models\ModelTransaksiKasBank;
use App\Models\ModelUnit;

/**
 * ModeKasBank:
 * Helper terpusat fitur Kas & Bank + Pembayaran Antar Unit.
 *
 * Prinsip:
 * - Semua posting ke transaksi_kas_bank harus idempotent
 *   (guard: sumber_tipe + sumber_id + akun + arah).
 * - Jangan mengubah posting jurnal existing (kas_masuk/kas_keluar).
 * - Akun ditentukan dari unit transaksi + kas/bank yang dipakai (bank_idbank),
 *   bukan dari COA global.
 * - Mutasi antar unit menghasilkan pasangan HUTANG/PIUTANG di registry
 *   hutang_piutang existing dengan sumber_tipe=mutasi_unit.
 */
class ModeKasBank
{
    public const JENIS_PEMASUKAN = 'PEMASUKAN';
    public const JENIS_PENGELUARAN = 'PENGELUARAN';
    public const JENIS_TRANSFER = 'TRANSFER_INTERNAL';
    public const JENIS_ANTAR_UNIT = 'PEMBAYARAN_ANTAR_UNIT';

    public const ARAH_MASUK = 'MASUK';
    public const ARAH_KELUAR = 'KELUAR';

    protected $AkunModel;
    protected $TransaksiModel;
    protected $HPModel;
    protected $UnitModel;
    protected $MutasiModel;
    protected $DetailMutasiModel;
    protected $KasMasukModel;
    protected $KasKeluarModel;
    protected $PembayaranHutangModel;
    protected $PembayaranPiutangModel;

    public function __construct()
    {
        $this->AkunModel = new ModelAkunKasBank();
        $this->TransaksiModel = new ModelTransaksiKasBank();
        $this->HPModel = new ModelHutangPiutang();
        $this->UnitModel = new ModelUnit();
        $this->MutasiModel = new ModelMutasiStok();
        $this->DetailMutasiModel = new ModelDetailMutasi();
        $this->KasMasukModel = new ModelKasMasuk();
        $this->KasKeluarModel = new ModelKasKeluar();
        $this->PembayaranHutangModel = new ModelPembayaranHutang();
        $this->PembayaranPiutangModel = new ModelPembayaranPiutang();
    }

    /**
     * Resolve akun kas/bank dari unit & bank.
     * Prioritas: akun BANK yang cocok (bank_idbank), lalu akun KAS pertama unit.
     * Return idakun_kas_bank atau null jika tidak ada -> posting di-skip.
     */
    public function resolveAkun(int $unitId, ?string $bankId = null): ?int
    {
        $akun = $this->AkunModel
            ->where('status', 'aktif')
            ->where('unit_id', $unitId);

        if (!empty($bankId)) {
            $bank = (clone $akun)->where('tipe', 'BANK')->where('bank_idbank', $bankId)->first();
            if ($bank) {
                return (int)$bank->idakun_kas_bank;
            }
        }

        $kas = (clone $akun)->where('tipe', 'KAS')->first();
        if ($kas) {
            return (int)$kas->idakun_kas_bank;
        }

        return null;
    }

    /**
     * Seeder akun KAS default per unit ("Kas <NAMA_UNIT>", COA 1010101000).
     * Idempotent: hanya membuat bila belum ada akun KAS aktif di unit tsb.
     * Akun BANK tidak otomatis dibuat karena butuh bank_idbank (FK bank).
     */
    public function seedAkunDefault(?int $unitId = null): array
    {
        $builder = $this->UnitModel;
        if ($unitId) {
            $builder = $builder->where('idunit', (int)$unitId);
        }

        $created = 0;
        $skipped = 0;
        foreach ($builder->findAll() as $unit) {
            $idUnit = (int)$unit->idunit;
            $nama   = trim((string)$unit->NAMA_UNIT);
            if ($idUnit <= 0 || $nama === '') {
                continue;
            }

            $exists = $this->AkunModel
                ->where('unit_id', $idUnit)
                ->where('tipe', 'KAS')
                ->where('status', 'aktif')
                ->first();
            if ($exists) {
                $skipped++;
                continue;
            }

            $this->AkunModel->insert([
                'unit_id'     => $idUnit,
                'tipe'        => 'KAS',
                'nama_akun'   => 'Kas ' . $nama,
                'bank_idbank' => null,
                'no_akun_coa' => '1010101000',
                'status'      => 'aktif',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Insert transaksi kas/bank satu sisi secara idempotent.
     * $data wajib berisi paling tidak: tanggal, unit_id, akun_kas_bank_id, jenis,
     * arah, jumlah, sumber_tipe, sumber_id. Return idtransaksi (0 jika sudah ada).
     */
    public function insertIdempotent(array $data): int
    {
        $data = array_merge([
            'tanggal'          => date('Y-m-d'),
            'unit_id'          => null,
            'akun_kas_bank_id' => null,
            'jenis'            => null,
            'arah'             => null,
            'jumlah'           => 0,
            'akun_tujuan_id'   => null,
            'transfer_ref'     => null,
            'sumber_tipe'      => null,
            'sumber_id'        => null,
            'keterangan'       => null,
            'bukti'            => null,
            'input_by'         => $_SESSION['ID_AKUN'] ?? null,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ], $data);

        if (empty($data['akun_kas_bank_id'])) {
            return 0;
        }

        $existing = $this->TransaksiModel
            ->where('sumber_tipe', $data['sumber_tipe'])
            ->where('sumber_id', $data['sumber_id'])
            ->where('akun_kas_bank_id', $data['akun_kas_bank_id'])
            ->where('arah', $data['arah'])
            ->first();

        if ($existing) {
            return (int)$existing->idtransaksi;
        }

        $this->TransaksiModel->insert($data);

        return (int)$this->TransaksiModel->insertID();
    }

    /**
     * Cek apakah satu sisi transaksi untuk sumber + akun + arah sudah ada.
     */
    public function sudahTerposting(string $sumberTipe, int $sumberId, int $akunId, string $arah): bool
    {
        return (bool) $this->TransaksiModel
            ->where('sumber_tipe', $sumberTipe)
            ->where('sumber_id', $sumberId)
            ->where('akun_kas_bank_id', $akunId)
            ->where('arah', $arah)
            ->first();
    }

    /**
     * Hapus seluruh posting ledger untuk satu sumber (update/reversal).
     */
    public function hapusPosting(string $sumberTipe, int $sumberId): void
    {
        $this->TransaksiModel
            ->where('sumber_tipe', $sumberTipe)
            ->where('sumber_id', $sumberId)
            ->delete();
    }

    /**
     * Integrasi kas_masuk existing -> transaksi_kas_bank (PEMASUKAN/MASUK).
     * Jurnal existing TIDAK diubah. Idempotent.
     */
    public function postingKasMasuk(int $id): array
    {
        $row = $this->KasMasukModel->find($id);
        if (!$row) {
            return ['status' => 'failed', 'reason' => 'kas masuk tidak ditemukan', 'id' => $id];
        }

        $akunId = $this->resolveAkun((int)$row->idunit, $row->idbank ?? null);
        if (!$akunId) {
            return ['status' => 'skipped', 'reason' => 'akun kas/bank tidak terkonfigurasi', 'id' => $id];
        }

        if ($this->sudahTerposting('kas_masuk', (int)$id, $akunId, self::ARAH_MASUK)) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $id];
        }

        $idTransaksi = $this->insertIdempotent([
            'tanggal'          => $row->tanggal,
            'unit_id'          => (int)$row->idunit,
            'akun_kas_bank_id' => $akunId,
            'jenis'            => self::JENIS_PEMASUKAN,
            'arah'             => self::ARAH_MASUK,
            'jumlah'           => (int)$row->jumlah,
            'sumber_tipe'      => 'kas_masuk',
            'sumber_id'        => (int)$id,
            'keterangan'       => $row->deskripsi,
        ]);

        if ($idTransaksi === 0) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $id];
        }

        return ['status' => 'inserted', 'id' => $id, 'transaksi_id' => $idTransaksi];
    }

    /**
     * Integrasi kas_keluar existing -> transaksi_kas_bank (PENGELUARAN/KELUAR).
     */
    public function postingKasKeluar(int $id): array
    {
        $row = $this->KasKeluarModel->find($id);
        if (!$row) {
            return ['status' => 'failed', 'reason' => 'kas keluar tidak ditemukan', 'id' => $id];
        }

        $akunId = $this->resolveAkun((int)$row->idunit, $row->idbank ?? null);
        if (!$akunId) {
            return ['status' => 'skipped', 'reason' => 'akun kas/bank tidak terkonfigurasi', 'id' => $id];
        }

        if ($this->sudahTerposting('kas_keluar', (int)$id, $akunId, self::ARAH_KELUAR)) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $id];
        }

        $idTransaksi = $this->insertIdempotent([
            'tanggal'          => $row->tanggal,
            'unit_id'          => (int)$row->idunit,
            'akun_kas_bank_id' => $akunId,
            'jenis'            => self::JENIS_PENGELUARAN,
            'arah'             => self::ARAH_KELUAR,
            'jumlah'           => (int)$row->jumlah,
            'sumber_tipe'      => 'kas_keluar',
            'sumber_id'        => (int)$id,
            'keterangan'       => $row->deskripsi ?? $row->penerima,
        ]);

        if ($idTransaksi === 0) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $id];
        }

        return ['status' => 'inserted', 'id' => $id, 'transaksi_id' => $idTransaksi];
    }

    /**
     * Integrasi pembayaran hutang existing (pembayaran_hutang) -> PENGELUARAN.
     * Bagian bayar_tunai diposting ke akun KAS unit; bagian bayar_bank ke akun
     * BANK (bank_idbank). Bisa menghasilkan 1 atau 2 baris ledger. Idempotent
     * per (sumber, akun, arah).
     */
    public function postingCicilanHutang(int $idPembayaranHutang): array
    {
        $row = $this->PembayaranHutangModel->getById($idPembayaranHutang);
        if (!$row) {
            return ['status' => 'failed', 'reason' => 'pembayaran hutang tidak ditemukan', 'id' => $idPembayaranHutang];
        }

        $unitId = (int)($row->unit_idunit ?? null);
        if (!$unitId) {
            return ['status' => 'skipped', 'reason' => 'unit tidak diketahui', 'id' => $idPembayaranHutang];
        }

        $tunai = (int)($row->bayar_tunai ?? 0);
        $bank  = (int)($row->bayar_bank ?? 0);
        if ($tunai <= 0 && $bank <= 0) {
            $tunai = (int)($row->bayar ?? 0);
        }

        $legs = [];
        if ($tunai > 0) {
            $legs[] = ['akun' => $this->resolveAkun($unitId, null), 'jumlah' => $tunai, 'bank' => null];
        }
        if ($bank > 0) {
            $legs[] = ['akun' => $this->resolveAkun($unitId, $row->bank_idbank ?? null), 'jumlah' => $bank, 'bank' => $row->bank_idbank ?? null];
        }

        $inserted = 0;
        $skipped  = 0;
        $ids      = [];

        foreach ($legs as $leg) {
            if (!$leg['akun']) {
                continue;
            }
            if ($this->sudahTerposting('pembayaran_hutang', (int)$idPembayaranHutang, $leg['akun'], self::ARAH_KELUAR)) {
                $skipped++;
                continue;
            }
            $idTransaksi = $this->insertIdempotent([
                'tanggal'          => $row->tanggal_bayar ?? date('Y-m-d'),
                'unit_id'          => $unitId,
                'akun_kas_bank_id' => $leg['akun'],
                'jenis'            => self::JENIS_PENGELUARAN,
                'arah'             => self::ARAH_KELUAR,
                'jumlah'           => (int)$leg['jumlah'],
                'sumber_tipe'      => 'pembayaran_hutang',
                'sumber_id'        => (int)$idPembayaranHutang,
                'keterangan'       => 'Pembayaran hutang pembelian',
            ]);
            if ($idTransaksi > 0) {
                $inserted++;
                $ids[] = $idTransaksi;
            }
        }

        if ($inserted > 0) {
            return ['status' => 'inserted', 'id' => $idPembayaranHutang, 'transaksi_ids' => $ids, 'skipped' => $skipped];
        }
        if ($skipped > 0) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $idPembayaranHutang];
        }

        return ['status' => 'skipped', 'reason' => 'akun kas/bank tidak terkonfigurasi', 'id' => $idPembayaranHutang];
    }

    /**
     * Integrasi pembayaran piutang existing (pembayaran_piutang) -> PEMASUKAN.
     */
    public function postingBayarPiutang(int $idPembayaranPiutang, int $unitId): array
    {
        $row = $this->PembayaranPiutangModel->find($idPembayaranPiutang);
        if (!$row) {
            return ['status' => 'failed', 'reason' => 'pembayaran piutang tidak ditemukan', 'id' => $idPembayaranPiutang];
        }

        $akunId = $this->resolveAkun($unitId, $row->bank_idbank ?? null);
        if (!$akunId) {
            return ['status' => 'skipped', 'reason' => 'akun kas/bank tidak terkonfigurasi', 'id' => $idPembayaranPiutang];
        }

        if ($this->sudahTerposting('pembayaran_piutang', (int)$idPembayaranPiutang, $akunId, self::ARAH_MASUK)) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $idPembayaranPiutang];
        }

        $idTransaksi = $this->insertIdempotent([
            'tanggal'          => date('Y-m-d'),
            'unit_id'          => $unitId,
            'akun_kas_bank_id' => $akunId,
            'jenis'            => self::JENIS_PEMASUKAN,
            'arah'             => self::ARAH_MASUK,
            'jumlah'           => (int)$row->jumlah_bayar,
            'sumber_tipe'      => 'pembayaran_piutang',
            'sumber_id'        => (int)$idPembayaranPiutang,
            'keterangan'       => 'Pembayaran piutang',
        ]);

        if ($idTransaksi === 0) {
            return ['status' => 'skipped', 'reason' => 'sudah terposting', 'id' => $idPembayaranPiutang];
        }

        return ['status' => 'inserted', 'id' => $idPembayaranPiutang, 'transaksi_id' => $idTransaksi];
    }

    /**
     * Nilai mutasi per detail: harga_mutasi × jumlah_kirim,
     * fallback per-item ke hpp_barang bila harga_mutasi kosong/0.
     */
    public function nilaiDetailMutasi(array $detail): int
    {
        $satuan = (int)($detail['jumlah_kirim'] ?? max((int)($detail['jumlah_terima'] ?? 0), 1));
        $harga = (int)($detail['harga_mutasi'] ?? 0);
        if ($harga <= 0) {
            $harga = (int)($detail['hpp_barang'] ?? 0);
        }
        return $satuan * $harga;
    }

    /**
     * Buat pasangan HUTANG/PIUTANG antar unit dari satu mutasi.
     * Idempotent (guard uniq_hp_sumber + cek existing).
     * $akunUser = input_by pada hutang_piutang (fallback session / mutasi.input_by).
     */
    public function buatHutangPiutangDariMutasi(int $idMutasi, ?int $akunUser = null): array
    {
        $mutasi = $this->MutasiModel->find($idMutasi);
        if (!$mutasi) {
            return ['status' => 'failed', 'reason' => 'mutasi tidak ditemukan', 'id' => $idMutasi];
        }

        $kirim  = (int)$mutasi->kirim_idunit;
        $terima = (int)$mutasi->terima_idunit;

        $existing = $this->HPModel
            ->where('sumber_tipe', 'mutasi_unit')
            ->where('sumber_id', $idMutasi)
            ->findAll();

        $piutangExists = false;
        $hutangExists  = false;
        foreach ($existing as $row) {
            if ($row->jenis === 'piutang') {
                $piutangExists = true;
            }
            if ($row->jenis === 'hutang') {
                $hutangExists = true;
            }
        }

        if ($piutangExists && $hutangExists) {
            return ['status' => 'skipped', 'reason' => 'pasangan sudah ada', 'id' => $idMutasi];
        }

        $details = $this->DetailMutasiModel->getByIdMutasi($idMutasi);
        $total = 0;
        foreach ($details as $detail) {
            $total += $this->nilaiDetailMutasi((array)$detail);
        }
        $total = (int)$total;

        if ($total <= 0) {
            return ['status' => 'failed', 'reason' => 'nilai mutasi 0/kosong', 'id' => $idMutasi];
        }

        if (!$akunUser) {
            $akunUser = (int)($mutasi->input_by ?? session()->get('ID_AKUN'));
        }

        $unitKirim  = $this->UnitModel->find($kirim);
        $unitTerima = $this->UnitModel->find($terima);
        $namaKirim  = $unitKirim ? $unitKirim->NAMA_UNIT : "Unit $kirim";
        $namaTerima = $unitTerima ? $unitTerima->NAMA_UNIT : "Unit $terima";

        $tanggal = date('Y-m-d', strtotime($mutasi->tanggal_kirim));
        $uraian  = "Mutasi antar unit: {$namaKirim} → {$namaTerima} ({$mutasi->no_nota_mutasi})";
        $kodeP   = 'MUT-' . $kirim . '-' . $idMutasi . '-P';
        $kodeH   = 'MUT-' . $terima . '-' . $idMutasi . '-H';

        $insertedP = 0;
        $insertedH = 0;

        if (!$piutangExists) {
            if ($this->HPModel->insert([
                'kode'           => $kodeP,
                'jenis'          => 'piutang',
                'sumber_tipe'    => 'mutasi_unit',
                'sumber_id'      => (int)$idMutasi,
                'is_projection'  => 0,
                'pihak_tipe'     => 'unit',
                'pihak_id'       => $kirim,
                'lawan_unit_id'  => $terima,
                'nama_pihak'     => $namaKirim,
                'tanggal'        => $tanggal,
                'jatuh_tempo'    => null,
                'uraian'         => $uraian,
                'total'          => $total,
                'total_dibayar'  => 0,
                'sisa'           => $total,
                'status'         => 'belum_lunas',
                'unit_id'        => $kirim,
                'input_by'       => $akunUser,
                'deleted'        => 0,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ])) {
                $insertedP = (int)$this->HPModel->insertID();
            }
        }

        if (!$hutangExists) {
            if ($this->HPModel->insert([
                'kode'           => $kodeH,
                'jenis'          => 'hutang',
                'sumber_tipe'    => 'mutasi_unit',
                'sumber_id'      => (int)$idMutasi,
                'is_projection'  => 0,
                'pihak_tipe'     => 'unit',
                'pihak_id'       => $terima,
                'lawan_unit_id'  => $kirim,
                'nama_pihak'     => $namaTerima,
                'tanggal'        => $tanggal,
                'jatuh_tempo'    => null,
                'uraian'         => $uraian,
                'total'          => $total,
                'total_dibayar'  => 0,
                'sisa'           => $total,
                'status'         => 'belum_lunas',
                'unit_id'        => $terima,
                'input_by'       => $akunUser,
                'deleted'        => 0,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ])) {
                $insertedH = (int)$this->HPModel->insertID();
            }
        }

        return [
            'status'   => 'inserted',
            'id'       => $idMutasi,
            'total'    => $total,
            'piutang'  => $insertedP,
            'hutang'   => $insertedH,
        ];
    }

    /**
     * Hitung status hutang/piutang dari sisa & total_dibayar.
     */
    public function hitungStatus(int $sisa, int $totalDibayar): string
    {
        if ($sisa <= 0) {
            return 'lunas';
        }
        if ($totalDibayar > 0) {
            return 'sebagian';
        }
        return 'belum_lunas';
    }

    /**
     * Terapkan pembayaran (kurangi sisa) pada satu baris hutang_piutang.
     * $jumlah harus <= sisa. Return data baru untuk auditable.
     */
    public function terapkanPembayaranHP(int $idHP, int $jumlah): array
    {
        $hp = $this->HPModel->find($idHP);
        if (!$hp) {
            return ['status' => 'failed', 'reason' => 'hutang/piutang tidak ditemukan', 'id' => $idHP];
        }
        if ((int)$hp->sisa < $jumlah) {
            return ['status' => 'failed', 'reason' => 'jumlah melebihi sisa', 'id' => $idHP];
        }

        $totalDibayar = (int)$hp->total_dibayar + $jumlah;
        $sisa         = (int)$hp->sisa - $jumlah;
        $status       = $this->hitungStatus($sisa, $totalDibayar);

        $this->HPModel->update($idHP, [
            'total_dibayar' => $totalDibayar,
            'sisa'          => $sisa,
            'status'        => $status,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return [
            'status'        => 'ok',
            'id'            => $idHP,
            'total_dibayar' => $totalDibayar,
            'sisa'          => $sisa,
            'status_hp'     => $status,
        ];
    }

    /**
     * Restore pembayaran (kembalikan sisa) pada satu baris hutang_piutang.
     * Digunakan saat reversal.
     */
    public function restorePembayaranHP(int $idHP, int $jumlah): array
    {
        $hp = $this->HPModel->find($idHP);
        if (!$hp) {
            return ['status' => 'failed', 'reason' => 'hutang/piutang tidak ditemukan', 'id' => $idHP];
        }

        $totalDibayar = max(0, (int)$hp->total_dibayar - $jumlah);
        $sisa         = (int)$hp->sisa + $jumlah;
        $status       = $this->hitungStatus($sisa, $totalDibayar);

        $this->HPModel->update($idHP, [
            'total_dibayar' => $totalDibayar,
            'sisa'          => $sisa,
            'status'        => $status,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return [
            'status'        => 'ok',
            'id'            => $idHP,
            'total_dibayar' => $totalDibayar,
            'sisa'          => $sisa,
            'status_hp'     => $status,
        ];
    }
}