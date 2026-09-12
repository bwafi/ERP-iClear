<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BASELINE SCHEMA - dibentuk otomatis dari database lokal (sumber kebenaran).
 *
 * Mereplikasi persis schema erp_local (64 tabel + 5 view): kolom, tipe, nullable,
 * default, auto_increment, primary key, unique, index, foreign key, engine & collation.
 * Hanya schema - TANPA data.
 *
 * Catatan: seluruh migration lama (36 file) dipindah ke app/Database/Archive.
 */
class CreateBaselineSchema extends Migration
{
    public function up()
    {
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");

        $this->db->query('CREATE TABLE `akun` (
  `ID_AKUN` int(11) NOT NULL AUTO_INCREMENT,
  `ID_JABATAN` int(11) NOT NULL,
  `ID_UNIT` int(11) NOT NULL DEFAULT 0,
  `NOID` varchar(255) NOT NULL,
  `KTP` varchar(255) DEFAULT NULL,
  `EMAIL` varchar(500) DEFAULT NULL,
  `PASSWORD` text NOT NULL,
  `ROLES` text DEFAULT NULL,
  `NAMA_AKUN` varchar(100) NOT NULL,
  `ALAMAT` varchar(250) DEFAULT NULL,
  `JENIS_KELAMIN` varchar(225) NOT NULL,
  `HP` varchar(25) DEFAULT NULL,
  `JENIS_PEGAWAI` int(11) DEFAULT 100000 COMMENT \'0: Tetap 1.Kontrak 2 Probation\',
  `STATUS_PEGAWAI` int(11) DEFAULT 1 COMMENT \'0 ; non aktif\\n1 : aktif\',
  `FOTO_KTP` varchar(255) DEFAULT NULL,
  `FOTO_KK` varchar(255) DEFAULT NULL,
  `deleted` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID_AKUN`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `asset` (
  `idasset` int(11) NOT NULL AUTO_INCREMENT,
  `idkategori_asset` int(11) NOT NULL,
  `asset_code` varchar(20) DEFAULT NULL,
  `asset` varchar(200) DEFAULT NULL,
  `tanggal_perolehan` date DEFAULT NULL,
  `nilai_perolehan` int(11) DEFAULT NULL,
  `penyusutan_bulanan` int(11) DEFAULT NULL,
  `jangka_waktu` datetime DEFAULT NULL,
  `nilai_sekarang` int(11) DEFAULT NULL,
  `kondisi` varchar(200) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `deleted` int(11) DEFAULT 0,
  PRIMARY KEY (`idasset`) USING BTREE,
  UNIQUE KEY `asset_code` (`asset_code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `bank` (
  `idbank` varchar(20) NOT NULL,
  `jenis_bank` enum(\'bank\',\'qris\',\'ecommerce\') DEFAULT NULL,
  `nama_bank` varchar(255) DEFAULT NULL,
  `atas_nama` varchar(255) DEFAULT NULL,
  `isppn` enum(\'0\',\'1\') DEFAULT \'0\',
  `norek` varchar(255) DEFAULT NULL,
  `gambar_qris` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idbank`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `barang` (
  `idbarang` int(11) NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(255) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `harga` int(11) NOT NULL,
  `harga_beli` int(11) DEFAULT NULL,
  `idkategori` int(11) DEFAULT NULL,
  `id_sub_kategori` int(11) DEFAULT NULL,
  `imei` varchar(255) DEFAULT NULL,
  `jenis_hp` varchar(255) DEFAULT NULL,
  `internal` varchar(255) DEFAULT NULL,
  `warna` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT \'1\' COMMENT \'Status Approval\\n1 = Approve\\n0 = Non Approve\',
  `status_ppn` int(11) DEFAULT 0 COMMENT \'1 = ppn\\n0 = non pun\',
  `status_barang` int(11) DEFAULT 0 COMMENT \'1 = second\\n0 = baru\',
  `stok_minimum` int(11) DEFAULT 0,
  `deleted` varchar(255) DEFAULT NULL,
  `input` varchar(255) NOT NULL DEFAULT \'Faisal\',
  `nama_barang_id` int(11) DEFAULT 0,
  PRIMARY KEY (`idbarang`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `barang_rusak` (
  `idbarang_rusak` int(11) NOT NULL AUTO_INCREMENT,
  `idpembelian` varchar(255) DEFAULT NULL,
  `no_nota_sup` varchar(255) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `tanggal_rusak` date NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  `input_by` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`idbarang_rusak`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `bundle` (
  `idbundle` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bundle` varchar(255) DEFAULT NULL,
  `harga_total` varchar(255) DEFAULT NULL,
  `harga_jual` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idbundle`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `ci_sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `data` blob NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `detail_bundle` (
  `iddetail_bundle` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_idbundle` int(11) DEFAULT NULL,
  `barang_idbarang` int(11) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `harga` int(11) DEFAULT NULL,
  PRIMARY KEY (`iddetail_bundle`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `detail_mutasi` (
  `iddetail_mutasi` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal_kirim` date NOT NULL DEFAULT current_timestamp(),
  `tanggal_terima` date DEFAULT NULL,
  `jumlah_kirim` int(11) DEFAULT 0,
  `jumlah_terima` int(11) DEFAULT NULL,
  `satuan` text DEFAULT NULL,
  `hpp_barang` int(11) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `kirim_idunit` int(11) NOT NULL,
  `terima_idunit` int(11) NOT NULL,
  `mutasi_idmutasi` varchar(255) DEFAULT NULL,
  `harga_beli` int(11) DEFAULT NULL,
  `harga_mutasi` int(11) DEFAULT NULL,
  `harga_jual` int(11) DEFAULT NULL,
  PRIMARY KEY (`iddetail_mutasi`) USING BTREE,
  KEY `fk_detail_pembelian_barang1_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_detail_pembelian_unit1_idx` (`kirim_idunit`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `detail_pembelian` (
  `iddetail_pembelian` int(11) NOT NULL AUTO_INCREMENT,
  `no_batch` varchar(45) DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `jumlah` int(11) DEFAULT 0,
  `hrg_beli` int(11) DEFAULT NULL,
  `diskon` int(11) DEFAULT NULL,
  `ppn` int(11) DEFAULT NULL,
  `hitung_hpp` varchar(45) DEFAULT NULL,
  `total_harga` int(11) DEFAULT NULL,
  `satuan_beli` text DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `pembelian_idpembelian` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  `biaya_tambahan` int(11) DEFAULT NULL,
  `keterangan_tambahan` text DEFAULT NULL,
  PRIMARY KEY (`iddetail_pembelian`) USING BTREE,
  KEY `fk_detail_pembelian_barang1_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_detail_pembelian_pembelian1_idx` (`pembelian_idpembelian`) USING BTREE,
  KEY `fk_detail_pembelian_unit1_idx` (`unit_idunit`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `detail_penjualan` (
  `iddetail_penjualan` int(11) NOT NULL AUTO_INCREMENT,
  `jumlah` int(11) DEFAULT NULL,
  `harga_penjualan` int(11) DEFAULT NULL,
  `sub_total` int(11) DEFAULT 0,
  `hpp_penjualan` int(11) DEFAULT NULL,
  `satuan_jual` varchar(225) DEFAULT NULL,
  `diskon_penjualan` varchar(255) DEFAULT NULL,
  `penjualan_idpenjualan` int(11) NOT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  `bundle` int(11) DEFAULT 0,
  PRIMARY KEY (`iddetail_penjualan`) USING BTREE,
  KEY `fk_detail_pemesanan_barang_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_detail_penjualan_penjualan1_idx` (`penjualan_idpenjualan`) USING BTREE,
  KEY `fk_detail_penjualan_unit1_idx` (`unit_idunit`) USING BTREE,
  CONSTRAINT `dp_barang` FOREIGN KEY (`barang_idbarang`) REFERENCES `barang` (`idbarang`),
  CONSTRAINT `dp_penjualan` FOREIGN KEY (`penjualan_idpenjualan`) REFERENCES `penjualan` (`idpenjualan`),
  CONSTRAINT `dp_unit` FOREIGN KEY (`unit_idunit`) REFERENCES `unit` (`idunit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `districts` (
  `id` char(7) NOT NULL,
  `regency_id` char(4) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `districts_id_index` (`regency_id`) USING BTREE,
  CONSTRAINT `districts_regency_id_foreign` FOREIGN KEY (`regency_id`) REFERENCES `regencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;');

        $this->db->query('CREATE TABLE `fungsi` (
  `idfungsi` int(11) NOT NULL AUTO_INCREMENT,
  `nama_fungsi` varchar(255) NOT NULL,
  `deleted` int(11) DEFAULT 0,
  PRIMARY KEY (`idfungsi`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `izin` (
  `idizin` int(11) NOT NULL AUTO_INCREMENT,
  `alasan` text DEFAULT NULL,
  `status` int(11) DEFAULT 0 COMMENT \'0 = unconfirmed\\\\n1 = confirm\',
  `tanggal_mulai` date DEFAULT NULL,
  `uuid_atasan` text DEFAULT NULL,
  `pegawai_uuid` varchar(100) NOT NULL,
  `tanggal_akhir` date DEFAULT NULL,
  `file` text DEFAULT NULL,
  `balasan` text DEFAULT NULL,
  `jenis_perizinan_idjenis_perizinan` int(11) NOT NULL,
  PRIMARY KEY (`idizin`) USING BTREE,
  KEY `fk_izin_pegawai1_idx` (`pegawai_uuid`) USING BTREE,
  KEY `fk_izin_jenis_perizinan1_idx` (`jenis_perizinan_idjenis_perizinan`) USING BTREE,
  CONSTRAINT `fk_izin_jenis_perizinan1` FOREIGN KEY (`jenis_perizinan_idjenis_perizinan`) REFERENCES `jenis_perizinan` (`idjenis_perizinan`),
  CONSTRAINT `fk_izin_pegawai1` FOREIGN KEY (`pegawai_uuid`) REFERENCES `pegawai` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `jabatan` (
  `ID_JABATAN` int(11) NOT NULL AUTO_INCREMENT,
  `NAMA_JABATAN` varchar(150) DEFAULT NULL,
  `ROLES_JABATAN` text DEFAULT NULL,
  PRIMARY KEY (`ID_JABATAN`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `jadwal_jabatan` (
  `idjadwal_jabatan` int(11) NOT NULL AUTO_INCREMENT,
  `jj_idjadwal_masuk` int(11) DEFAULT NULL,
  `jj_idjabatan` int(11) DEFAULT NULL,
  PRIMARY KEY (`idjadwal_jabatan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `jadwal_masuk` (
  `idjadwal_masuk` int(11) NOT NULL AUTO_INCREMENT,
  `nama_jadwal` text DEFAULT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `total_jamkerja` varchar(45) DEFAULT NULL,
  `jml_wfh` int(11) DEFAULT NULL COMMENT \'Hitungan dalam 1 Bulan\',
  `jml_wfo` int(11) DEFAULT NULL COMMENT \'Hitungan dalam 1 Bulan\',
  `jenis` int(11) DEFAULT 1 COMMENT \'1 = WFO\\\\n2 = WFH\',
  `toleransi` time DEFAULT NULL,
  `unit_idunit` int(11) DEFAULT NULL,
  PRIMARY KEY (`idjadwal_masuk`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `jenis_payroll` (
  `idjenis_payroll` int(11) NOT NULL AUTO_INCREMENT,
  `nama_payroll` varchar(255) DEFAULT NULL,
  `status_payroll` enum(\'payroll\',\'iuran\') NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idjenis_payroll`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `jenis_perizinan` (
  `idjenis_perizinan` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_izin` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`idjenis_perizinan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `jurnal` (
  `idjurnal` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `no_akun` varchar(255) DEFAULT NULL,
  `nama_akun` varchar(255) DEFAULT NULL,
  `debet` int(11) DEFAULT NULL,
  `kredit` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `id_referensi` varchar(255) DEFAULT NULL,
  `tabel_referensi` varchar(255) DEFAULT NULL,
  `id_unit` int(11) DEFAULT NULL,
  `id_akun` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idjurnal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `kas_keluar` (
  `idkas_keluar` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `kategori_idkategori` int(11) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `penerima` varchar(255) DEFAULT NULL,
  `idunit` int(11) DEFAULT NULL,
  `jenis` varchar(255) DEFAULT NULL,
  `idbank` varchar(11) DEFAULT NULL,
  `created_on` varchar(255) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `no_akun` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idkas_keluar`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `kas_masuk` (
  `idkas_masuk` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `kategori_idkategori` int(11) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `penerima` varchar(255) DEFAULT NULL,
  `idunit` int(11) DEFAULT NULL,
  `jenis` varchar(255) DEFAULT NULL,
  `idbank` varchar(11) DEFAULT NULL,
  `created_on` varchar(255) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `no_akun` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idkas_masuk`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idkategori` varchar(255) DEFAULT NULL,
  `nama_kategori` varchar(255) DEFAULT NULL,
  `kategori_hp` int(11) DEFAULT NULL COMMENT \'1 = HP\\n0 = Bukan HP\',
  `delete` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `kategori_asset` (
  `idkategori_asset` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_asset` varchar(255) DEFAULT NULL,
  `deleted` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idkategori_asset`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `kategori_kas` (
  `idkategori_kas` int(11) NOT NULL AUTO_INCREMENT,
  `kategori` varchar(255) DEFAULT NULL,
  `kode_template_jurnal` varchar(255) DEFAULT NULL,
  `jenis_kas` enum(\'kas_masuk\',\'kas_keluar\') DEFAULT NULL,
  PRIMARY KEY (`idkategori_kas`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `menu` (
  `idmenu` int(11) NOT NULL AUTO_INCREMENT,
  `urutan` int(11) NOT NULL,
  `nama_menu` text DEFAULT NULL,
  `roles` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `show_menu` int(11) DEFAULT 1,
  `sub` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `utama` int(11) DEFAULT NULL,
  `categories` int(11) DEFAULT NULL,
  `icon` text DEFAULT NULL,
  `manualbook` text DEFAULT NULL,
  PRIMARY KEY (`idmenu`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `mutasi` (
  `idmutasi` int(11) NOT NULL AUTO_INCREMENT,
  `no_nota_mutasi` varchar(45) DEFAULT NULL,
  `tanggal_kirim` datetime DEFAULT NULL,
  `tanggal_terima` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT \'0 = menunggu\\n1 = kirim\\n2 = terima\',
  `kirim_idunit` int(11) NOT NULL,
  `terima_idunit` int(11) NOT NULL,
  `input_by` varchar(45) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idmutasi`) USING BTREE,
  KEY `fk_pembelian_unit1_idx1` (`kirim_idunit`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `nama_handphone` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `no_akun` (
  `no_akun` varchar(255) NOT NULL,
  `nama_akun` varchar(255) DEFAULT NULL,
  `jenis_akun` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`no_akun`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `payroll_pegawai` (
  `idpayroll_pegawai` int(11) NOT NULL AUTO_INCREMENT,
  `idjenis_payroll` int(11) DEFAULT NULL,
  `ID_AKUN` int(11) DEFAULT NULL,
  `biaya_payroll` int(11) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idpayroll_pegawai`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `pelanggan` (
  `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(255) DEFAULT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `kategori` int(11) DEFAULT 1 COMMENT \'1 = umum  2 = toko\\r\\n\',
  `no_hp` varchar(255) DEFAULT NULL,
  `deleted` varchar(255) DEFAULT \'0\',
  `create_on` datetime DEFAULT current_timestamp(),
  `provinsi` varchar(255) DEFAULT NULL,
  `kabupaten` varchar(255) DEFAULT NULL,
  `kecamatan` varchar(255) DEFAULT NULL,
  `mengetahui_dari` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_pelanggan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `pembayaran_bank` (
  `idpembayaran_bank` int(11) NOT NULL AUTO_INCREMENT,
  `kode_pembayaran` varchar(255) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `bank_idbank` varchar(11) DEFAULT NULL,
  `tabel_referensi` varchar(255) DEFAULT NULL,
  `id_referensi` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpembayaran_bank`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `pembayaran_hutang` (
  `idpembayaran_hutang` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal_bayar` date DEFAULT NULL,
  `bayar` int(11) DEFAULT NULL,
  `bayar_tunai` int(11) DEFAULT 0,
  `bayar_bank` int(11) DEFAULT 0,
  `sisa_hutang` int(11) DEFAULT NULL,
  `pembelian_idpembelian` varchar(255) DEFAULT NULL,
  `bank_idbank` varchar(11) DEFAULT NULL,
  `input_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idpembayaran_hutang`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `pembayaran_piutang` (
  `idpembayaran_piutang` int(11) NOT NULL AUTO_INCREMENT,
  `idpiutang` int(11) DEFAULT NULL,
  `jumlah_bayar` int(11) DEFAULT NULL,
  `sisa_hutang` int(11) DEFAULT NULL,
  `bayar_tunai` int(11) DEFAULT NULL,
  `bank_idbank` varchar(11) DEFAULT NULL,
  `bayar_bank` int(11) DEFAULT NULL,
  `input_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpembayaran_piutang`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `pembelian` (
  `idpembelian` int(11) NOT NULL AUTO_INCREMENT,
  `no_nota_supplier` varchar(45) DEFAULT NULL,
  `foto_nota` text DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `tanggal_lunas` date DEFAULT NULL,
  `sisa` int(11) DEFAULT NULL,
  `status` varchar(25) DEFAULT NULL,
  `total_transaksi` int(11) DEFAULT NULL,
  `total_diskon` int(11) DEFAULT NULL,
  `total_ppn` int(11) DEFAULT NULL,
  `total_bayar` int(11) DEFAULT NULL,
  `bayar` int(11) DEFAULT NULL,
  `bayar_tunai` int(11) DEFAULT NULL,
  `bank_idbank` varchar(11) DEFAULT NULL,
  `bayar_bank` int(11) DEFAULT NULL,
  `suplier_id_suplier` int(11) DEFAULT NULL,
  `pelanggan_id_pelanggan` int(11) DEFAULT NULL,
  `unit_idunit` int(11) NOT NULL,
  `input_by` varchar(45) DEFAULT NULL,
  `frontliner` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpembelian`) USING BTREE,
  KEY `fk_pembelian_suplier1_idx` (`suplier_id_suplier`) USING BTREE,
  KEY `fk_pembelian_pelanggan1_idx` (`pelanggan_id_pelanggan`) USING BTREE,
  KEY `fk_pembelian_unit1_idx1` (`unit_idunit`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `penilaian` (
  `idpenilaian` int(11) NOT NULL AUTO_INCREMENT,
  `aspek` varchar(255) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `skor` decimal(11,0) DEFAULT NULL,
  `pegawai_idpegawai` varchar(255) DEFAULT NULL,
  `input_by` varchar(255) DEFAULT NULL,
  `tanggal_penilaian` date DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`idpenilaian`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `penilaian_detail` (
  `iddetail_penilaian` int(11) NOT NULL AUTO_INCREMENT,
  `template_penilaian_idtemplate_penilaian` int(11) DEFAULT NULL,
  `pegawai_idpegawai` int(11) DEFAULT NULL,
  `bobot` double DEFAULT NULL,
  `target` int(11) DEFAULT NULL,
  `skor` int(11) DEFAULT NULL,
  `tanggal_penilaian` datetime DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `penilaian_idpenilaian` int(11) DEFAULT NULL,
  PRIMARY KEY (`iddetail_penilaian`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `penilaian_kpi` (
  `idpenilaian_kpi` int(11) NOT NULL AUTO_INCREMENT,
  `kpi_utama` varchar(255) DEFAULT NULL,
  `bobot` int(11) DEFAULT NULL,
  `target` double DEFAULT NULL,
  `realisasi` double DEFAULT NULL,
  `score` double DEFAULT NULL,
  `pegawai_idpegawai` varchar(255) DEFAULT NULL,
  `tanggal_penilaian_kpi` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `updated_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `unit_idunit` int(11) DEFAULT NULL,
  `penilaian_idpenilaian` int(11) DEFAULT NULL,
  `level` enum(\'1\',\'2\') NOT NULL COMMENT \'1 = grading\\n2 = KPI\',
  `template_kpi_idtemplate_kpi` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpenilaian_kpi`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `penjualan` (
  `idpenjualan` int(11) NOT NULL AUTO_INCREMENT,
  `kode_invoice` varchar(45) DEFAULT NULL,
  `tanggal` datetime DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `total_penjualan` int(11) DEFAULT NULL,
  `diskon` int(11) DEFAULT NULL,
  `total_ppn` int(11) DEFAULT 0,
  `harus_dibayar` varchar(45) DEFAULT NULL,
  `waktu_penjualan` datetime DEFAULT NULL,
  `bayar` int(11) DEFAULT NULL,
  `bayar_tunai` int(11) DEFAULT NULL,
  `bank_idbank` varchar(11) DEFAULT \'0\',
  `bayar_bank` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `id_pelanggan` int(11) DEFAULT 0,
  `input_by` int(11) NOT NULL,
  `sales_by` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  PRIMARY KEY (`idpenjualan`) USING BTREE,
  KEY `fk_penjualan_akun1_idx` (`input_by`) USING BTREE,
  KEY `fk_penjualan_unit1_idx1` (`unit_idunit`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `piutang` (
  `idpiutang` int(11) NOT NULL AUTO_INCREMENT,
  `kode_piutang` varchar(255) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jumlah_hutang` int(11) DEFAULT NULL,
  `sisa_hutang` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT \'1 lunas 0 belum lunas\',
  `pegawai_idpegawai` int(11) DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `kirim_bank` int(11) DEFAULT 0,
  `kirim_tunai` int(11) DEFAULT 0,
  `input_by` int(11) DEFAULT NULL,
  `unit_idunit` int(11) DEFAULT NULL,
  PRIMARY KEY (`idpiutang`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `presensi` (
  `idpresensi` int(11) NOT NULL AUTO_INCREMENT,
  `waktu_masuk` datetime DEFAULT NULL,
  `waktu_pulang` datetime DEFAULT NULL,
  `jam_jadwal_masuk` time DEFAULT NULL,
  `jam_jadwal_pulang` time DEFAULT NULL,
  `jam_toleransi` time DEFAULT NULL,
  `status_absensi` int(11) DEFAULT 0 COMMENT \'0 = unconfirmed\\\\n1 = confirm\',
  `lat` text DEFAULT NULL,
  `long` text DEFAULT NULL,
  `ip` text DEFAULT NULL,
  `foto` text DEFAULT NULL,
  `idjadwal_masuk` int(11) DEFAULT NULL,
  `akun_idakun` int(11) NOT NULL,
  `unit_idunit` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `jarak` int(11) DEFAULT NULL,
  `status_kehadiran` int(11) DEFAULT NULL,
  `foto_pulang` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`idpresensi`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `proses_service` (
  `idproses_service` int(11) NOT NULL AUTO_INCREMENT,
  `service_idservice` int(11) DEFAULT NULL,
  `status_statusproses` int(11) DEFAULT NULL COMMENT \'1 = belum dicek 2 = sedang dicek 3 = sedang dikerjakan  4 = sedang tes  5 = menunggu konfirmasi 6 =  menunggu sparepart\',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`idproses_service`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `provinces` (
  `id` char(2) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;');

        $this->db->query('CREATE TABLE `regencie` (
  `id` char(4) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `province_id` char(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `retur_pelanggan` (
  `idretur_pelanggan` int(11) NOT NULL AUTO_INCREMENT,
  `no_retur_pelanggan` varchar(45) DEFAULT NULL,
  `tanggal` datetime DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `satuan` varchar(45) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `detail_penjualan_iddetail_penjualan` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  `input_by` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idretur_pelanggan`) USING BTREE,
  KEY `fk_retur_customer_barang1_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_retur_customer_detail_penjualan1_idx` (`detail_penjualan_iddetail_penjualan`) USING BTREE,
  KEY `fk_retur_pelanggan_unit1_idx` (`unit_idunit`) USING BTREE,
  CONSTRAINT `rp_barang` FOREIGN KEY (`barang_idbarang`) REFERENCES `barang` (`idbarang`),
  CONSTRAINT `rp_unit` FOREIGN KEY (`unit_idunit`) REFERENCES `unit` (`idunit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `retur_suplier` (
  `idretur_suplier` int(11) NOT NULL AUTO_INCREMENT,
  `no_retur_suplier` varchar(45) DEFAULT NULL,
  `tanggal` datetime DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `satuan` varchar(45) DEFAULT NULL,
  `input_by` varchar(45) DEFAULT NULL,
  `detail_pembelian_iddetail_pembelian` int(11) NOT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  PRIMARY KEY (`idretur_suplier`) USING BTREE,
  KEY `rs_idbarang` (`barang_idbarang`) USING BTREE,
  KEY `rs_idunit` (`unit_idunit`) USING BTREE,
  CONSTRAINT `rs_barang` FOREIGN KEY (`barang_idbarang`) REFERENCES `barang` (`idbarang`),
  CONSTRAINT `rs_unit` FOREIGN KEY (`unit_idunit`) REFERENCES `unit` (`idunit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `riwayat_asset` (
  `idriwayat_asset` int(11) NOT NULL AUTO_INCREMENT,
  `asset_idasset` int(11) DEFAULT NULL,
  `penyusutan` int(11) DEFAULT NULL,
  `nilai_riwayat` int(11) DEFAULT NULL,
  `tanggal_penyusutan` datetime DEFAULT NULL,
  PRIMARY KEY (`idriwayat_asset`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `riwayat_claim_garansi` (
  `idriwayat_claim_garansi` int(11) NOT NULL AUTO_INCREMENT,
  `service_idservice` int(11) DEFAULT NULL,
  `tanggal_claim` date DEFAULT NULL,
  PRIMARY KEY (`idriwayat_claim_garansi`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `satuan` (
  `idsatuan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_satuan` varchar(225) DEFAULT NULL,
  `jml_kecil` varchar(45) DEFAULT NULL,
  `jml_sedang` varchar(45) DEFAULT NULL,
  `jml_besar` varchar(45) DEFAULT NULL,
  `jenis_satuan` varchar(45) DEFAULT \'1\' COMMENT \'1 = kecil\\n2 = sedang\\n3 = besar\',
  PRIMARY KEY (`idsatuan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `service` (
  `idservice` int(11) NOT NULL AUTO_INCREMENT,
  `no_service` varchar(255) DEFAULT NULL,
  `no_hp` varchar(255) DEFAULT NULL,
  `imei` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `keluhan` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `passcode` varchar(255) DEFAULT NULL,
  `tipe_hp` varchar(255) DEFAULT NULL,
  `type_passcode` varchar(255) DEFAULT NULL,
  `email_icloud` varchar(255) DEFAULT NULL,
  `password_icloud` text DEFAULT NULL,
  `status_service` int(11) DEFAULT 1 COMMENT \'1 = menunggu\\n2 = proses services\\n3 = pengambilan\\n4 = selesai dan pembayaran\\n9 = dibatalkan\',
  `status_proses` int(11) DEFAULT 1 COMMENT \'1 = belum dicek 2 = sedang dicek 3 = sedang dikerjakan  4 = sedang tes  5 = menunggu konfirmasi 6 =  menunggu sparepart\',
  `total_service` int(11) DEFAULT 0,
  `total_diskon` int(11) DEFAULT 0,
  `harus_dibayar` int(11) DEFAULT 0,
  `bayar` int(11) DEFAULT 0,
  `dp_bayar` int(11) DEFAULT 0,
  `garansi_hari` int(11) DEFAULT 0,
  `pelanggan_id_pelanggan` int(11) NOT NULL DEFAULT 1,
  `unit_idunit` int(11) NOT NULL,
  `service_by` int(11) NOT NULL,
  `input_by` int(11) NOT NULL,
  `tanggal_bisa_diambil` datetime DEFAULT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\' ON UPDATE current_timestamp(),
  `estimasi_biaya` varchar(255) DEFAULT NULL,
  `tanggal_claim_garansi` datetime DEFAULT NULL,
  `biaya_tambahan_garansi` int(11) DEFAULT 0,
  `total_service_garansi` int(11) DEFAULT 0,
  `total_diskon_garansi` int(11) DEFAULT 0,
  `service_by_garansi` int(11) DEFAULT NULL,
  `bayar_garansi` int(11) DEFAULT 0,
  `harus_dibayar_garansi` int(11) DEFAULT 0,
  `bayar_tunai` int(11) DEFAULT NULL,
  `bayar_bank` varchar(255) DEFAULT NULL,
  `bayar_tunai_garansi` int(11) DEFAULT NULL,
  `prioritas` int(11) DEFAULT 0,
  PRIMARY KEY (`idservice`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `service_kerusakan` (
  `idservice_kerusakan` int(11) NOT NULL AUTO_INCREMENT,
  `fungsi_idfungsi` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `service_idservice` int(11) DEFAULT NULL,
  PRIMARY KEY (`idservice_kerusakan`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `service_sparepart` (
  `idservice_sparepart` int(11) NOT NULL AUTO_INCREMENT,
  `jumlah` int(11) DEFAULT NULL,
  `harga_penjualan` int(11) DEFAULT NULL,
  `sub_total` int(11) DEFAULT 0,
  `hpp_penjualan` int(11) DEFAULT NULL,
  `satuan_jual` varchar(225) DEFAULT NULL,
  `diskon_penjualan` int(11) DEFAULT 0,
  `service_idservice` int(11) NOT NULL,
  `barang_idbarang` int(11) DEFAULT NULL,
  `unit_idunit` int(11) NOT NULL,
  `harga_penjualan_garansi` int(11) DEFAULT 0,
  `sub_total_garansi` int(11) DEFAULT 0,
  `diskon_penjualan_garansi` int(11) DEFAULT 0,
  `jumlah_tambahan_garansi` int(11) DEFAULT 0,
  `hpp_penjualan_garansi` int(11) DEFAULT 0,
  PRIMARY KEY (`idservice_sparepart`) USING BTREE,
  KEY `fk_detail_pemesanan_barang_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_detail_penjualan_penjualan1_idx` (`service_idservice`) USING BTREE,
  KEY `fk_detail_penjualan_unit1_idx` (`unit_idunit`) USING BTREE,
  CONSTRAINT `service_sparepart_ibfk_1` FOREIGN KEY (`barang_idbarang`) REFERENCES `barang` (`idbarang`),
  CONSTRAINT `service_sparepart_ibfk_2` FOREIGN KEY (`service_idservice`) REFERENCES `service` (`idservice`),
  CONSTRAINT `service_sparepart_ibfk_3` FOREIGN KEY (`unit_idunit`) REFERENCES `unit` (`idunit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `stok_awal` (
  `idstok_awal` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `harga_beli` int(11) DEFAULT NULL,
  `satuan_terkecil` varchar(225) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  `suplier_id_suplier` varchar(45) DEFAULT NULL,
  `pelanggan_id_pelanggan` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idstok_awal`) USING BTREE,
  KEY `fk_stok_opname_barang1_idx` (`barang_idbarang`) USING BTREE,
  KEY `fk_stok_awal_unit1_idx` (`unit_idunit`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `stok_opname` (
  `idstok_opname` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `hpp` int(11) DEFAULT NULL,
  `jumlah_real` int(11) DEFAULT NULL,
  `jumlah_komp` int(11) DEFAULT NULL,
  `jumlah_selisih` int(11) DEFAULT NULL,
  `satuan_terkecil` varchar(11) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  PRIMARY KEY (`idstok_opname`) USING BTREE,
  KEY `fk_stok_opname_unit1_idx1` (`unit_idunit`) USING BTREE,
  KEY `fk_stok_opname_barang1_idx1` (`barang_idbarang`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `stok_opname_draft` (
  `idstok_opname` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `hpp` int(11) DEFAULT NULL,
  `jumlah_real` int(11) DEFAULT NULL,
  `jumlah_komp` int(11) DEFAULT NULL,
  `jumlah_selisih` int(11) DEFAULT NULL,
  `satuan_terkecil` varchar(11) DEFAULT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `unit_idunit` int(11) NOT NULL,
  PRIMARY KEY (`idstok_opname`) USING BTREE,
  KEY `fk_stok_opname_unit1_idx1` (`unit_idunit`) USING BTREE,
  KEY `fk_stok_opname_barang1_idx1` (`barang_idbarang`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;');

        $this->db->query('CREATE TABLE `sub_kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_sub_kategori` varchar(255) NOT NULL,
  `id_kategori_parent` int(11) NOT NULL,
  `delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_id_kategori_parent` (`id_kategori_parent`) USING BTREE,
  KEY `idx_delete` (`delete`) USING BTREE,
  CONSTRAINT `fk_sub_kategori_parent` FOREIGN KEY (`id_kategori_parent`) REFERENCES `kategori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;');

        $this->db->query('CREATE TABLE `suplier` (
  `id_suplier` int(11) NOT NULL AUTO_INCREMENT,
  `nama_suplier` varchar(255) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `no_hp` varchar(255) DEFAULT NULL,
  `deleted` varchar(255) DEFAULT NULL,
  `unit_idunit` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_suplier`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `template_jurnal` (
  `idtemplate_jurnal` int(11) NOT NULL AUTO_INCREMENT,
  `kode_template` varchar(255) DEFAULT NULL,
  `no_akun` varchar(255) DEFAULT NULL,
  `nama_akun` varchar(255) DEFAULT NULL,
  `debet_kredit` enum(\'debet\',\'kredit\') DEFAULT NULL,
  `array_value` int(11) DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`idtemplate_jurnal`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `template_kpi` (
  `idtemplate_kpi` int(11) NOT NULL AUTO_INCREMENT,
  `template_kpi` varchar(255) DEFAULT NULL,
  `bobot` int(11) DEFAULT NULL,
  `formula` varchar(255) DEFAULT NULL,
  `jabatan_idjabatan` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `update_on` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `target` double DEFAULT NULL,
  `status` int(11) NOT NULL COMMENT \'0 = otomatis 1 = slider 2= manual\',
  `level` enum(\'1\',\'2\') DEFAULT NULL COMMENT \'1 =  Grading\\n2 = KPI\',
  PRIMARY KEY (`idtemplate_kpi`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `template_penilaian` (
  `idtemplate_penilaian` int(11) NOT NULL AUTO_INCREMENT,
  `aspek_penilaian` varchar(255) DEFAULT NULL,
  `keterangan_penilaian` varchar(255) DEFAULT NULL,
  `jabatan_idjabatan` int(11) DEFAULT NULL,
  `idtemplate_kpi` int(11) DEFAULT NULL,
  `target` int(11) NOT NULL,
  `bobot` int(11) NOT NULL,
  PRIMARY KEY (`idtemplate_penilaian`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `tugas` (
  `idtugas` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tugas` varchar(255) DEFAULT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `foto_tugas` varchar(255) DEFAULT NULL,
  `akun_ID_AKUN` int(11) DEFAULT NULL,
  `status_template` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT \'1 = To Do 2 = In Progress 3 = Pending 4 = Done\',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `template_penilaian_idtemplate_penilaian` int(11) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `template_kpi_idtemplate_kpi` int(11) DEFAULT NULL,
  PRIMARY KEY (`idtugas`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `tugas_template` (
  `idtemplate_tugas` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tugas` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `ID_JABATAN` int(11) DEFAULT NULL,
  `template_penilaian_idtemplate_penilaian` varchar(255) DEFAULT NULL,
  `template_kpi_idtemplate_kpi` int(11) DEFAULT NULL,
  PRIMARY KEY (`idtemplate_tugas`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE TABLE `tutup_kasir` (
  `idtutupkasir` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `awal_cash` int(11) DEFAULT NULL,
  `awal_transfer` int(11) DEFAULT NULL,
  `akhir_cash` int(11) DEFAULT NULL,
  `akhir_transfer` int(11) DEFAULT NULL,
  `pendapatan_cash` int(11) DEFAULT NULL,
  `pendapatan_transfer` int(11) DEFAULT NULL,
  `pengeluaran_cash` int(11) DEFAULT NULL,
  `pengeluaran_transfer` int(11) DEFAULT NULL,
  `cash_laci` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `akun_ID_AKUN` int(11) DEFAULT NULL,
  `unit` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`idtutupkasir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;');

        $this->db->query('CREATE TABLE `unit` (
  `idunit` int(11) NOT NULL AUTO_INCREMENT,
  `NAMA_UNIT` varchar(150) DEFAULT NULL,
  `NOID_UNIT` varchar(100) DEFAULT NULL,
  `NOTELP` varchar(255) DEFAULT NULL,
  `JALAN_UNIT` varchar(100) DEFAULT NULL,
  `KELURAHAN_UNIT` varchar(100) DEFAULT NULL,
  `KECAMATAN_UNIT` varchar(100) DEFAULT NULL,
  `KABUPATEN_UNIT` varchar(100) DEFAULT NULL,
  `PROVINSI_UNIT` varchar(100) DEFAULT NULL,
  `jenis` varchar(255) DEFAULT NULL,
  `tanggungan` int(11) DEFAULT NULL,
  `LATITUDE` varchar(255) DEFAULT NULL,
  `LONGTITUDE` varchar(255) DEFAULT NULL,
  `RADIUS` varchar(255) DEFAULT NULL,
  `LOGO` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idunit`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;');

        $this->db->query('CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `hpp_barang` AS select `barang`.`idbarang` AS `idbarang`,`barang`.`kode_barang` AS `kode_barang`,`barang`.`nama_barang` AS `nama_barang`,`detail_pembelian`.`satuan_beli` AS `satuan_beli`,coalesce(nullif(round(case when `barang`.`idkategori` = 1 then `barang`.`harga_beli` else sum(`detail_pembelian`.`jumlah` * (`detail_pembelian`.`hrg_beli` + `detail_pembelian`.`ppn`)) / nullif(sum(`detail_pembelian`.`jumlah`),0) end,0),0),coalesce(`barang`.`harga_beli`,0)) AS `hpp`,`barang`.`harga` AS `harga`,coalesce(`barang`.`harga_beli`,0) AS `harga_beli`,`barang`.`idkategori` AS `idkategori`,`barang`.`id_sub_kategori` AS `id_sub_kategori`,`barang`.`imei` AS `imei`,`barang`.`jenis_hp` AS `jenis_hp`,`barang`.`internal` AS `internal`,`barang`.`warna` AS `warna`,`barang`.`status` AS `status`,`barang`.`status_ppn` AS `status_ppn`,`barang`.`status_barang` AS `status_barang`,`barang`.`stok_minimum` AS `stok_minimum`,`barang`.`deleted` AS `deleted`,`barang`.`input` AS `input`,`barang`.`nama_barang_id` AS `nama_barang_id` from (`barang` left join `detail_pembelian` on(`barang`.`idbarang` = `detail_pembelian`.`barang_idbarang`)) group by `barang`.`idbarang`;');

        $this->db->query('CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `stok_barang` AS select `barang`.`idbarang` AS `idbarang`,`barang`.`kode_barang` AS `kode_barang`,`barang`.`nama_barang` AS `nama_barang`,`barang`.`harga` AS `harga`,`barang`.`harga_beli` AS `harga_beli`,`barang`.`idkategori` AS `idkategori`,`barang`.`imei` AS `imei`,`barang`.`jenis_hp` AS `jenis_hp`,`barang`.`internal` AS `internal`,`barang`.`warna` AS `warna`,`barang`.`status` AS `status`,`barang`.`status_ppn` AS `status_ppn`,`barang`.`status_barang` AS `status_barang`,`barang`.`stok_minimum` AS `stok_minimum`,`barang`.`deleted` AS `deleted`,`barang`.`input` AS `input`,`barang`.`nama_barang_id` AS `nama_barang_id`,`combined_stocks`.`idunit` AS `id_unit`,`unit`.`NAMA_UNIT` AS `nama_unit`,sum(0 + `combined_stocks`.`stok_awal` + `combined_stocks`.`stok_opname` + `combined_stocks`.`total_pembelian` + `combined_stocks`.`total_mutasi_masuk` + `combined_stocks`.`total_retur_pelanggan` - `combined_stocks`.`total_penjualan` - `combined_stocks`.`total_mutasi_keluar` - `combined_stocks`.`total_retur_supplier` - `combined_stocks`.`total_kerusakan`) AS `stok_akhir`,sum(`combined_stocks`.`stok_awal`) AS `stok_awal`,sum(`combined_stocks`.`stok_opname`) AS `stok_opname`,sum(`combined_stocks`.`total_pembelian`) AS `total_pembelian`,sum(`combined_stocks`.`total_mutasi_masuk`) AS `total_mutasi_masuk`,sum(`combined_stocks`.`total_retur_pelanggan`) AS `total_retur_pelanggan`,sum(`combined_stocks`.`total_penjualan`) AS `total_penjualan`,sum(`combined_stocks`.`total_mutasi_keluar`) AS `total_mutasi_keluar`,sum(`combined_stocks`.`total_retur_supplier`) AS `total_retur_supplier`,sum(`combined_stocks`.`total_kerusakan`) AS `total_kerusakan` from ((`barang` join (select `detail_pembelian`.`barang_idbarang` AS `idbarang`,`detail_pembelian`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,sum(`detail_pembelian`.`jumlah`) AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_pembelian` group by `detail_pembelian`.`barang_idbarang`,`detail_pembelian`.`unit_idunit` union all select `stok_awal`.`barang_idbarang` AS `idbarang`,`stok_awal`.`unit_idunit` AS `idunit`,sum(`stok_awal`.`jumlah`) AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `stok_awal` group by `stok_awal`.`barang_idbarang`,`stok_awal`.`unit_idunit` union all select `detail_penjualan`.`barang_idbarang` AS `idbarang`,`detail_penjualan`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,sum(`detail_penjualan`.`jumlah`) AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_penjualan` group by `detail_penjualan`.`barang_idbarang`,`detail_penjualan`.`unit_idunit` union all select `detail_mutasi`.`barang_idbarang` AS `idbarang`,`detail_mutasi`.`kirim_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,sum(`detail_mutasi`.`jumlah_kirim`) AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_mutasi` group by `detail_mutasi`.`barang_idbarang`,`detail_mutasi`.`kirim_idunit` union all select `detail_mutasi`.`barang_idbarang` AS `idbarang`,`detail_mutasi`.`terima_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,sum(`detail_mutasi`.`jumlah_terima`) AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_mutasi` group by `detail_mutasi`.`barang_idbarang`,`detail_mutasi`.`terima_idunit` union all select `retur_pelanggan`.`barang_idbarang` AS `idbarang`,`retur_pelanggan`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,sum(`retur_pelanggan`.`jumlah`) AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `retur_pelanggan` group by `retur_pelanggan`.`barang_idbarang`,`retur_pelanggan`.`unit_idunit` union all select `retur_suplier`.`barang_idbarang` AS `idbarang`,`retur_suplier`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,sum(`retur_suplier`.`jumlah`) AS `total_retur_supplier`,0 AS `total_kerusakan` from `retur_suplier` group by `retur_suplier`.`barang_idbarang`,`retur_suplier`.`unit_idunit` union all select `barang_rusak`.`barang_idbarang` AS `idbarang`,`barang_rusak`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,sum(`barang_rusak`.`jumlah`) AS `total_kerusakan` from `barang_rusak` group by `barang_rusak`.`barang_idbarang`,`barang_rusak`.`unit_idunit` union all select `stok_opname`.`barang_idbarang` AS `idbarang`,`stok_opname`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,sum(`stok_opname`.`jumlah_selisih`) AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `stok_opname` group by `stok_opname`.`barang_idbarang`,`stok_opname`.`unit_idunit`) `combined_stocks` on(`barang`.`idbarang` = `combined_stocks`.`idbarang`)) join `unit` on(`unit`.`idunit` = `combined_stocks`.`idunit`)) where `barang`.`deleted` = \'0\' group by `barang`.`idbarang`,`combined_stocks`.`idunit`;');

        $this->db->query('CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `stok_hp` AS select `nama_handphone`.`id` AS `id`,`nama_handphone`.`nama` AS `nama`,`nama_handphone`.`type` AS `type`,`nama_handphone`.`size` AS `size`,`combined_stocks`.`idunit` AS `id_unit`,`unit`.`NAMA_UNIT` AS `nama_unit`,sum(0 + `combined_stocks`.`stok_awal` + `combined_stocks`.`stok_opname` + `combined_stocks`.`total_pembelian` + `combined_stocks`.`total_mutasi_masuk` + `combined_stocks`.`total_retur_pelanggan` - `combined_stocks`.`total_penjualan` - `combined_stocks`.`total_mutasi_keluar` - `combined_stocks`.`total_retur_supplier` - `combined_stocks`.`total_kerusakan`) AS `stok_akhir`,sum(`combined_stocks`.`stok_awal`) AS `stok_awal`,sum(`combined_stocks`.`stok_opname`) AS `stok_opname`,sum(`combined_stocks`.`total_pembelian`) AS `total_pembelian`,sum(`combined_stocks`.`total_mutasi_masuk`) AS `total_mutasi_masuk`,sum(`combined_stocks`.`total_retur_pelanggan`) AS `total_retur_pelanggan`,sum(`combined_stocks`.`total_penjualan`) AS `total_penjualan`,sum(`combined_stocks`.`total_mutasi_keluar`) AS `total_mutasi_keluar`,sum(`combined_stocks`.`total_retur_supplier`) AS `total_retur_supplier`,sum(`combined_stocks`.`total_kerusakan`) AS `total_kerusakan` from (((`barang` join (select `detail_pembelian`.`barang_idbarang` AS `idbarang`,`detail_pembelian`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,sum(`detail_pembelian`.`jumlah`) AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_pembelian` group by `detail_pembelian`.`barang_idbarang`,`detail_pembelian`.`unit_idunit` union all select `detail_penjualan`.`barang_idbarang` AS `idbarang`,`detail_penjualan`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,sum(`detail_penjualan`.`jumlah`) AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_penjualan` group by `detail_penjualan`.`barang_idbarang`,`detail_penjualan`.`unit_idunit` union all select `detail_mutasi`.`barang_idbarang` AS `idbarang`,`detail_mutasi`.`kirim_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,sum(`detail_mutasi`.`jumlah_kirim`) AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_mutasi` group by `detail_mutasi`.`barang_idbarang`,`detail_mutasi`.`kirim_idunit` union all select `detail_mutasi`.`barang_idbarang` AS `idbarang`,`detail_mutasi`.`terima_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,sum(`detail_mutasi`.`jumlah_terima`) AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `detail_mutasi` group by `detail_mutasi`.`barang_idbarang`,`detail_mutasi`.`terima_idunit` union all select `retur_pelanggan`.`barang_idbarang` AS `idbarang`,`retur_pelanggan`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,sum(`retur_pelanggan`.`jumlah`) AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `retur_pelanggan` group by `retur_pelanggan`.`barang_idbarang`,`retur_pelanggan`.`unit_idunit` union all select `retur_suplier`.`barang_idbarang` AS `idbarang`,`retur_suplier`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,sum(`retur_suplier`.`jumlah`) AS `total_retur_supplier`,0 AS `total_kerusakan` from `retur_suplier` group by `retur_suplier`.`barang_idbarang`,`retur_suplier`.`unit_idunit` union all select `barang_rusak`.`barang_idbarang` AS `idbarang`,`barang_rusak`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,sum(`barang_rusak`.`jumlah`) AS `total_kerusakan` from `barang_rusak` group by `barang_rusak`.`barang_idbarang`,`barang_rusak`.`unit_idunit` union all select `stok_awal`.`barang_idbarang` AS `idbarang`,`stok_awal`.`unit_idunit` AS `idunit`,sum(`stok_awal`.`jumlah`) AS `stok_awal`,0 AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `stok_awal` group by `stok_awal`.`barang_idbarang`,`stok_awal`.`unit_idunit` union all select `stok_opname`.`barang_idbarang` AS `idbarang`,`stok_opname`.`unit_idunit` AS `idunit`,0 AS `stok_awal`,sum(`stok_opname`.`jumlah_selisih`) AS `stok_opname`,0 AS `total_pembelian`,0 AS `total_penjualan`,0 AS `total_mutasi_keluar`,0 AS `total_mutasi_masuk`,0 AS `total_retur_pelanggan`,0 AS `total_retur_supplier`,0 AS `total_kerusakan` from `stok_opname` group by `stok_opname`.`barang_idbarang`,`stok_opname`.`unit_idunit`) `combined_stocks` on(`barang`.`idbarang` = `combined_stocks`.`idbarang`)) join `unit` on(`unit`.`idunit` = `combined_stocks`.`idunit`)) join `nama_handphone` on(`nama_handphone`.`id` = `barang`.`nama_barang_id`)) where `barang`.`deleted` = \'0\' group by `barang`.`nama_barang_id`,`combined_stocks`.`idunit`;');

        $this->db->query('CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `summary_checklist` AS select `akun`.`ID_AKUN` AS `ID_AKUN`,`akun`.`ID_JABATAN` AS `ID_JABATAN`,`akun`.`ID_UNIT` AS `ID_UNIT`,`akun`.`NAMA_AKUN` AS `NAMA_AKUN`,concat(year(`penilaian_detail`.`tanggal_penilaian`),\'-\',month(`penilaian_detail`.`tanggal_penilaian`)) AS `tanggal`,coalesce(sum(`penilaian_detail`.`bobot`),0) AS `bobot`,coalesce(sum(`penilaian_detail`.`target`),0) AS `target`,coalesce(sum(`penilaian_detail`.`skor`),0) AS `skor` from (`akun` join `penilaian_detail` on(`akun`.`ID_AKUN` = `penilaian_detail`.`pegawai_idpegawai`)) group by `penilaian_detail`.`pegawai_idpegawai`,year(`penilaian_detail`.`tanggal_penilaian`),month(`penilaian_detail`.`tanggal_penilaian`);');

        $this->db->query('CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `summary_grading_kpi` AS select `akun`.`ID_AKUN` AS `ID_AKUN`,`akun`.`ID_JABATAN` AS `ID_JABATAN`,`akun`.`ID_UNIT` AS `ID_UNIT`,`unit`.`NAMA_UNIT` AS `NAMA_UNIT`,`jabatan`.`NAMA_JABATAN` AS `NAMA_JABATAN`,`akun`.`NAMA_AKUN` AS `NAMA_AKUN`,`penilaian_kpi`.`level` AS `level`,concat(year(`penilaian_kpi`.`tanggal_penilaian_kpi`),\'-\',month(`penilaian_kpi`.`tanggal_penilaian_kpi`)) AS `tanggal`,coalesce(sum(`penilaian_kpi`.`realisasi`),0) AS `realisasi`,coalesce(sum(`penilaian_kpi`.`target`),0) AS `target`,coalesce(sum(`penilaian_kpi`.`score`),0) AS `score` from (((`akun` join `penilaian_kpi` on(`akun`.`ID_AKUN` = `penilaian_kpi`.`pegawai_idpegawai`)) join `unit` on(`unit`.`idunit` = `akun`.`ID_UNIT`)) join `jabatan` on(`jabatan`.`ID_JABATAN` = `akun`.`ID_JABATAN`)) group by `penilaian_kpi`.`pegawai_idpegawai`,`penilaian_kpi`.`level`,year(`penilaian_kpi`.`tanggal_penilaian_kpi`),month(`penilaian_kpi`.`tanggal_penilaian_kpi`);');

        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    public function down()
    {
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");

        $this->db->query('DROP VIEW IF EXISTS `summary_grading_kpi`');
        $this->db->query('DROP VIEW IF EXISTS `summary_checklist`');
        $this->db->query('DROP VIEW IF EXISTS `stok_hp`');
        $this->db->query('DROP VIEW IF EXISTS `stok_barang`');
        $this->db->query('DROP VIEW IF EXISTS `hpp_barang`');
        $this->db->query('DROP TABLE IF EXISTS `unit`');
        $this->db->query('DROP TABLE IF EXISTS `tutup_kasir`');
        $this->db->query('DROP TABLE IF EXISTS `tugas_template`');
        $this->db->query('DROP TABLE IF EXISTS `tugas`');
        $this->db->query('DROP TABLE IF EXISTS `template_penilaian`');
        $this->db->query('DROP TABLE IF EXISTS `template_kpi`');
        $this->db->query('DROP TABLE IF EXISTS `template_jurnal`');
        $this->db->query('DROP TABLE IF EXISTS `suplier`');
        $this->db->query('DROP TABLE IF EXISTS `sub_kategori`');
        $this->db->query('DROP TABLE IF EXISTS `stok_opname_draft`');
        $this->db->query('DROP TABLE IF EXISTS `stok_opname`');
        $this->db->query('DROP TABLE IF EXISTS `stok_awal`');
        $this->db->query('DROP TABLE IF EXISTS `service_sparepart`');
        $this->db->query('DROP TABLE IF EXISTS `service_kerusakan`');
        $this->db->query('DROP TABLE IF EXISTS `service`');
        $this->db->query('DROP TABLE IF EXISTS `satuan`');
        $this->db->query('DROP TABLE IF EXISTS `riwayat_claim_garansi`');
        $this->db->query('DROP TABLE IF EXISTS `riwayat_asset`');
        $this->db->query('DROP TABLE IF EXISTS `retur_suplier`');
        $this->db->query('DROP TABLE IF EXISTS `retur_pelanggan`');
        $this->db->query('DROP TABLE IF EXISTS `regencie`');
        $this->db->query('DROP TABLE IF EXISTS `provinces`');
        $this->db->query('DROP TABLE IF EXISTS `proses_service`');
        $this->db->query('DROP TABLE IF EXISTS `presensi`');
        $this->db->query('DROP TABLE IF EXISTS `piutang`');
        $this->db->query('DROP TABLE IF EXISTS `penjualan`');
        $this->db->query('DROP TABLE IF EXISTS `penilaian_kpi`');
        $this->db->query('DROP TABLE IF EXISTS `penilaian_detail`');
        $this->db->query('DROP TABLE IF EXISTS `penilaian`');
        $this->db->query('DROP TABLE IF EXISTS `pembelian`');
        $this->db->query('DROP TABLE IF EXISTS `pembayaran_piutang`');
        $this->db->query('DROP TABLE IF EXISTS `pembayaran_hutang`');
        $this->db->query('DROP TABLE IF EXISTS `pembayaran_bank`');
        $this->db->query('DROP TABLE IF EXISTS `pelanggan`');
        $this->db->query('DROP TABLE IF EXISTS `payroll_pegawai`');
        $this->db->query('DROP TABLE IF EXISTS `no_akun`');
        $this->db->query('DROP TABLE IF EXISTS `nama_handphone`');
        $this->db->query('DROP TABLE IF EXISTS `mutasi`');
        $this->db->query('DROP TABLE IF EXISTS `menu`');
        $this->db->query('DROP TABLE IF EXISTS `kategori_kas`');
        $this->db->query('DROP TABLE IF EXISTS `kategori_asset`');
        $this->db->query('DROP TABLE IF EXISTS `kategori`');
        $this->db->query('DROP TABLE IF EXISTS `kas_masuk`');
        $this->db->query('DROP TABLE IF EXISTS `kas_keluar`');
        $this->db->query('DROP TABLE IF EXISTS `jurnal`');
        $this->db->query('DROP TABLE IF EXISTS `jenis_perizinan`');
        $this->db->query('DROP TABLE IF EXISTS `jenis_payroll`');
        $this->db->query('DROP TABLE IF EXISTS `jadwal_masuk`');
        $this->db->query('DROP TABLE IF EXISTS `jadwal_jabatan`');
        $this->db->query('DROP TABLE IF EXISTS `jabatan`');
        $this->db->query('DROP TABLE IF EXISTS `izin`');
        $this->db->query('DROP TABLE IF EXISTS `fungsi`');
        $this->db->query('DROP TABLE IF EXISTS `districts`');
        $this->db->query('DROP TABLE IF EXISTS `detail_penjualan`');
        $this->db->query('DROP TABLE IF EXISTS `detail_pembelian`');
        $this->db->query('DROP TABLE IF EXISTS `detail_mutasi`');
        $this->db->query('DROP TABLE IF EXISTS `detail_bundle`');
        $this->db->query('DROP TABLE IF EXISTS `ci_sessions`');
        $this->db->query('DROP TABLE IF EXISTS `bundle`');
        $this->db->query('DROP TABLE IF EXISTS `barang_rusak`');
        $this->db->query('DROP TABLE IF EXISTS `barang`');
        $this->db->query('DROP TABLE IF EXISTS `bank`');
        $this->db->query('DROP TABLE IF EXISTS `asset`');
        $this->db->query('DROP TABLE IF EXISTS `akun`');

        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}
