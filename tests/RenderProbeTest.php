<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Render terisolasi view kas_bank/transfer + antar_unit dengan data yang
 * sama persis seperti yang dikirim controller setelah perubahan ARAH
 * (akun_sumber / akun_tujuan / akun_pengirim / akun_penerima).
 *
 * View ini tidak mewarisi template.php, jadi bisa diuji tanpa layout global.
 */
class RenderProbeTest extends CIUnitTestCase
{
    private function akun(int $id, int $unit, string $tipe, string $nama, int $shared, int $ho = 0): object
    {
        return (object) [
            'idakun_kas_bank' => $id,
            'unit_id'         => $unit,
            'tipe'            => $tipe,
            'nama_akun'       => $nama,
            'status'          => 'aktif',
            'is_shared'       => $shared,
            'is_finance_ho'   => $ho,
            'bank_idbank'     => 'X-' . $id,
            'no_akun_coa'     => '1-' . $id,
        ];
    }

    private function render(string $view, array $vars, string $seg2 = ''): string
    {
        // _nav.php memanggil service('uri')->getSegment(2), yang tidak ada
        // pada CLI. Stub supaya view bisa diuji terisolasi.
        \Config\Services::injectMock('uri', new class($seg2) extends \CodeIgniter\HTTP\URI {
            private string $seg2;

            public function __construct(string $seg2)
            {
                parent::__construct('http://localhost/');
                $this->seg2 = $seg2;
            }

            public function getSegment(int $number, string $default = ''): string
            {
                return $number === 2 ? $this->seg2 : $default;
            }
        });

        $r = service('renderer');
        foreach ($vars as $k => $v) {
            $r->setVar($k, $v);
        }

        return (string) $r->include($view);
    }

    private function dataDasar(): array
    {
        return [
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit 2'],
            ],
            // 1 KAS u1, 2 shared u1+u2, 3 KAS u2, 9 HO/IRA
            'akun_kas_bank' => [
                $this->akun(1, 1, 'KAS', 'Kas Unit 1', 0),
                $this->akun(2, 0, 'BANK', 'Bank Bersama', 1),
                $this->akun(3, 2, 'KAS', 'Kas Unit 2', 0),
                $this->akun(9, 0, 'BANK', 'Rekening IRA/BCA Finance', 1, 1),
            ],
            'transaksi'         => [],
            'unit_terpilih'    => 1,
            'can_transaksi'     => true,
            'canInput'          => true,
            'canKelola'         => true,
            'bisa_pilih_unit'   => true,
            'submit_token'      => 'tok',
            'submit_topik'      => [],
            'hp_hutang'         => [],
            'hp_piutang'        => [],
            'mutasi'            => [],
            'detail_mutasi_map' => [],
            'pembayaran'        => [],
            'histori'           => [],
            'akun_milik'        => [],
        ];
    }

    public function testTransferMemakaiDaftarSumberDanTujuanTerpisah()
    {
        $html = $this->render('kas_bank/transfer', $this->dataDasar() + [
            'akun_sumber' => [$this->akun(1, 1, 'KAS', 'Kas Unit 1', 0)],
            'akun_tujuan' => [
                $this->akun(3, 2, 'KAS', 'Kas Unit 2', 0),
                $this->akun(9, 0, 'BANK', 'Rekening IRA/BCA Finance', 1, 1),
            ],
        ], 'transfer');

        $this->assertStringContainsString('name="akun_asal_id"', $html);
        $this->assertStringContainsString('name="akun_tujuan_id"', $html);

        // Pisahkan isi <select> asal vs tujuan lalu cek IRA hanya di tujuan.
        preg_match('#<select name="akun_asal_id".*?</select>#s', $html, $mAsal);
        preg_match('#<select name="akun_tujuan_id".*?</select>#s', $html, $mTujuan);
        $asal   = $mAsal[0] ?? '';
        $tujuan = $mTujuan[0] ?? '';

        $this->assertStringContainsString('value="1"', $asal, 'KAS Unit 1 harus jadi pilihan asal');
        $this->assertStringNotContainsString('value="9"', $asal, 'IRA TIDAK boleh jadi pilihan asal');
        $this->assertStringContainsString('value="3"', $tujuan, 'KAS Unit 2 boleh jadi tujuan');
        $this->assertStringContainsString('value="9"', $tujuan, 'IRA BOLEH jadi pilihan tujuan');
    }

    public function testAntarUnitMemakaiDaftarPengirimDanPenerimaTerpisah()
    {
        $html = $this->render('kas_bank/antar_unit', $this->dataDasar() + [
            'akun_pengirim' => [$this->akun(1, 1, 'KAS', 'Kas Unit 1', 0)],
            'akun_penerima' => [
                $this->akun(3, 2, 'KAS', 'Kas Unit 2', 0),
                $this->akun(9, 0, 'BANK', 'Rekening IRA/BCA Finance', 1, 1),
            ],
        ], 'antar-unit');

        preg_match('#<select name="akun_pengirim_id".*?</select>#s', $html, $mKirim);
        preg_match('#<select name="akun_penerima_id".*?</select>#s', $html, $mTerima);
        $kirim = $mKirim[0] ?? '';
        $terima = $mTerima[0] ?? '';

        $this->assertStringContainsString('value="1"', $kirim);
        $this->assertStringNotContainsString('value="9"', $kirim, 'IRA TIDAK boleh jadi pilihan pengirim');
        $this->assertStringContainsString('value="9"', $terima, 'IRA BOLEH jadi pilihan penerima');
    }

    public function testAntarUnitMenampilkanNamaUnitLawanDiLuarCakupan()
    {
        // Scope user hanya Unit 1 & 2 (dropdown), tapi H/P antar unit boleh
        // punya lawan unit di luar cakupan (mis. Unit 4). Kolom "Pemberi
        // barang · berpiutang" harus menampilkan NAMA unit, bukan "U4".
        $html = $this->render('kas_bank/antar_unit', array_replace($this->dataDasar(), [
            'akun_pengirim' => [$this->akun(1, 1, 'KAS', 'Kas Unit 1', 0)],
            'akun_penerima' => [$this->akun(3, 2, 'KAS', 'Kas Unit 2', 0)],
            'unit_map'      => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit 2'],
                (object) ['idunit' => 4, 'NAMA_UNIT' => 'Unit Empat'],
            ],
            'hp_hutang' => [
                (object) [
                    'id'            => 7,
                    'kode'          => 'H-7',
                    'sisa'          => 500000,
                    'status'        => 'belum_lunas',
                    'unit_id'       => 1,
                    'lawan_unit_id' => 4,
                    'nama_pihak'    => 'Pihak Test',
                    'jatuh_tempo'   => null,
                    'sumber_tipe'   => 'mutasi_unit',
                    'sumber_id'     => 0,
                ],
            ],
        ]), 'antar-unit');

        $this->assertStringContainsString('Unit Empat', $html, 'lawan unit di luar cakupan harus tampil sebagai NAMA unit');
        $this->assertStringNotContainsString('U4', $html, 'fallback "U<id>" tidak boleh muncul untuk lawan yang unitnya ada');
    }

    public function testTidakAdaPhpErrorDiKeduaView()
    {
        foreach ([['transfer', ['akun_sumber' => [], 'akun_tujuan' => []]], ['antar_unit', ['akun_pengirim' => [], 'akun_penerima' => []]]] as [$view, $extra]) {
            $html = $this->render('kas_bank/' . $view, $this->dataDasar() + $extra, $view);

            foreach ([
                'Fatal error', 'Parse error', 'Undefined variable', 'Undefined property',
                'Call to a member function', 'Whoops', 'Undefined array key', 'Attempt to read',
            ] as $pola) {
                $this->assertStringNotContainsString($pola, $html, "{$view}: PHP error ({$pola})");
            }

            printf("\n%-11s len=%-7d %s\n", $view, strlen($html), 'OK');
        }
    }

    // =====================================================================
    // Master Rekening: admin harus bisa Melihat status jenis rekening
    // =====================================================================

    /** Data yang dikirim KasBank::akun() ke view. */
    private function dataMaster(): array
    {
        $unit = [
            (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1'],
            (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit 2'],
        ];

        $akun = [
            $this->akun(1, 1, 'KAS', 'Kas Unit 1', 0),
            // Finance/HO: bentuknya sama dengan shared lama (unit NULL, shared=1)
            // sehingga HANYA bisa dibedakan lewat is_finance_ho.
            $this->akun(9, 0, 'BANK', 'Rekening IRA/BCA Finance', 1, 1),
            // Shared: unit NULL + shared=1 tapi BUKAN HO.
            $this->akun(4, 0, 'BANK', 'Bank Bersama Tanpa Alokasi', 1, 0),
        ];

        $jenis = [];
        foreach ($akun as $a) {
            $jenis[(int) $a->idakun_kas_bank] = $a->is_finance_ho == 1
                ? \App\Services\Finance\KasBankScopeService::KIND_FINANCE_HO
                : ($a->is_shared == 1
                    ? \App\Services\Finance\KasBankScopeService::KIND_SHARED
                    : \App\Services\Finance\KasBankScopeService::KIND_UNIT);
        }

        return [
            'unit'            => $unit,
            'unit_terpilih'  => 1,
            'bisa_pilih_unit' => true,   // view memakai ini sebagai $canInput
            'akun_kas_bank'  => $akun,
            'akun_jenis'     => $jenis,
            'akun_scope'     => [4 => [1, 2]],
            'saldo_fisik_akun' => [1 => 5000, 9 => 25000000, 4 => 3000],
            'alokasi'        => [4 => [(object) ['unit_id' => 1, 'nominal' => 1000]]],
            'bank'           => [],
            'no_akun'        => [],
            'saldo_awal'     => [],
            'unit_list'      => $unit,
            'submit_token'   => 'tok',
            'hp_hutang'      => [],
            'hp_piutang'     => [],
        ];
    }

    public function testMasterMenampilkanStatusFinanceHo()
    {
        $html = $this->render('kas_bank/akun', $this->dataMaster(), 'akun');

        $this->assertStringContainsString('Finance/HO', $html, 'status Finance/HO harus terlihat');
        $this->assertStringContainsString('Rekening IRA/BCA Finance', $html);

        // Rekening HO tidak boleh disuruh alokasi.
        $this->assertStringNotContainsString(
            'belum dialokasikan',
            $html,
            'Rekening Finance/HO tidak boleh tampil sebagai "belum dialokasikan"'
        );
    }

    public function testMasterMenampilkanStatusSharedDanUnit()
    {
        $html = $this->render('kas_bank/akun', $this->dataMaster(), 'akun');

        $this->assertStringContainsString('Shared Antar Unit', $html);
        $this->assertStringContainsString('Unit 1', $html);
        $this->assertStringNotContainsString('Bersama ·', $html, 'label lama harus diganti');
    }

    public function testAlokasiFormTidakMenawarkanRekeningFinanceHo()
    {
        $html = $this->render('kas_bank/akun', $this->dataMaster(), 'akun');

        preg_match_all('#<select name="akun_kas_bank_id".*?</select>#s', $html, $m);

        $alokasi = '';
        foreach ($m[0] as $s) {
            // Form alokasi berada di pane "Bagi hak per unit"; form saldo awal
            // (pane 2) juga memakai nama field yang sama.
            if (str_contains($s, 'value="4"')) {
                $alokasi = $s;
            }
        }

        $this->assertNotSame('', $alokasi, 'form alokasi harus ada di halaman');
        $this->assertStringNotContainsString('value="9"', $alokasi, 'rekening HO tidak boleh masuk form alokasi');
        $this->assertStringContainsString('value="4"', $alokasi, 'rekening shared boleh masuk form alokasi');
    }

    /**
     * Banner diagnosa harus muncul kalau ada masalah konfigurasi, dan TIDAK
     * muncul sama sekali kalau semua rekening sudah rapi — supaya halaman
     * Kas & Bank tidak perpetually berantakan setelah config-nya dibenahi.
     */
    public function testBannerDiagnostikHanyaMunculKalauAdaMasalah(): void
    {
        // CI4 membungkus output view dengan komentar DEBUG-VIEW saat debug aktif,
        // jadi yang diperiksa adalah isi banner-nya, bukan string kosong absolut.
        $sehat = $this->render('kas_bank/_diagnostik', ['diagnostik_konfigurasi' => []]);
        $this->assertStringNotContainsString('Ada rekening yang belum bisa dipakai transaksi', $sehat);
        $this->assertStringNotContainsString('kb-banner', $sehat);

        $bermasalah = $this->render('kas_bank/_diagnostik', [
            'diagnostik_konfigurasi' => [
                [
                    'level'  => 'danger',
                    'judul'  => '2 unit belum punya akun KAS aktif',
                    'detail' => ['Unit A (#1)', 'Unit B (#2)'],
                    'aksi'   => 'Jalankan `php spark migrate`.',
                ],
            ],
        ]);

        $this->assertStringContainsString('2 unit belum punya akun KAS aktif', $bermasalah);
        $this->assertStringContainsString('Unit A (#1)', $bermasalah);
        $this->assertStringContainsString('Unit B (#2)', $bermasalah);
        $this->assertStringContainsString('php spark migrate', $bermasalah);
        $this->assertStringContainsString('transaksi_kas_bank', $bermasalah, 'harus jelas ini bukan kegagalan jurnal');
    }

    /** Halaman master harus merender banner tanpa error walau datanya kosong. */
    public function testHalamanAkunTetapRenderSaatDiagnostikTidakDiberi(): void
    {
        $html = $this->render('kas_bank/akun', $this->dataMaster(), 'akun');
        $this->assertStringNotContainsString('Notice:', $html);
        $this->assertStringNotContainsString('Undefined', $html);
    }

    /**
     * Halaman /kas_bank (dashboard) juga memuat banner diagnostik. Smoke test
     * ini menangkap regresi include (mis. nama view salah ketik) yang tidak
     * akan terlihat dari unit test controller mana pun.
     */
    public function testDashboardKasBankMerenderBannerDiagnostik(): void
    {
        $vars = $this->dataMaster() + [
            'total_kas'             => 5000,
            'total_bank'            => 25300000,
            'total_semua'           => 25305000,
            'total_fisik_kas'       => 5000,
            'total_fisik_bank'      => 25300000,
            'total_fisik_semua'     => 25305000,
            'saldo_fisik_per_akun'  => [1 => 5000, 9 => 25000000, 4 => 3000],
            'saldo_unit_per_akun'   => [],
            'ringkasan'             => [],
            'net_cash_flow'         => 0,
            'filter'                => [],
        ];

        $sehat = $this->render('kas_bank/dashboard', $vars + ['diagnostik_konfigurasi' => []], '');
        $this->assertStringNotContainsString('Ada rekening yang belum bisa dipakai transaksi', $sehat);
        $this->assertStringNotContainsString('Notice:', $sehat);
        $this->assertStringNotContainsString('Undefined', $sehat);

        $bermasalah = $this->render('kas_bank/dashboard', $vars + [
            'diagnostik_konfigurasi' => [[
                'level'  => 'danger',
                'judul'  => '1 rekening bank belum punya akun fisik',
                'detail' => ['idbank 7 — Bank X (ATAS NAMA)'],
                'aksi'   => 'Tambahkan lewat /kas_bank/akun.',
            ]],
        ], '');
        $this->assertStringContainsString('1 rekening bank belum punya akun fisik', $bermasalah);
        $this->assertStringContainsString('idbank 7', $bermasalah);
    }
}
