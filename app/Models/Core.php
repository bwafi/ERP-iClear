<?php

namespace App\Models;

use CodeIgniter\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class Core extends Model
{
    protected $table = 'akun';

    public function __construct()
    {
        parent::__construct();
        // logs aktifitas
        if (session()->get("logged_in")) {
            $request = \Config\Services::request();
            // $insert_log_aktifitas = array(
            //     "ID_LOGIN" => session()->get("ID_LOGIN"),
            //     "agent" => $request->getUserAgent(),
            //     "ip" => $request->getIPAddress(),
            //     "user_id" => session()->get("ID_AKUN"),
            //     "username" => session()->get("NAMA"),
            //     "timestamp" => date("Y-m-d H:i:s"),
            //     "method" => $request->getMethod(),
            //     "url" => $request->getServer("REQUEST_URI"),
            //     "message" => json_encode(session()->getFlashdata())
            // );
            // db_connect("db_logs")->table("log_aktifitas")->insert($insert_log_aktifitas);
        }
    }

    function getGedung()
    {
        return db_connect()->table('gedung')->where(array("ID_GEDUNG" => session()->get('ID_GEDUNG')))->get()->getFirstRow();
    }

    function getManualBook()
    {
        $uri = $this->request->getUri()->getPath();
        return db_connect()->table('menu')->where(array("url" => $uri))->get()->getFirstRow();
    }

    // function biaya_admin($id)
    // {
    //     $this->db->where("idbiaya_admin", $id);
    //     return $this->db->get("biaya_admin")->row_array();
    // }

    function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ',', '.');
        return $hasil_rupiah;
    }

    function dua_digit($value)
    {
        $hasil = $value;
        if ($value < 10) {
            $hasil = "0" . $value;
        }
        return $hasil;
    }

    function tiga_digit($value)
    {
        $hasil = $value;
        if ($value < 10) {
            $hasil = "00" . $value;
        } elseif ($value < 100) {
            $hasil = "0" . $value;
        }
        return $hasil;
    }

    function enam_digit($value)
    {
        $hasil = $value;
        if ($value < 10) {
            $hasil = "00000" . $value;
        } elseif ($value < 100) {
            $hasil = "0000" . $value;
        } elseif ($value < 1000) {
            $hasil = "000" . $value;
        } elseif ($value < 10000) {
            $hasil = "00" . $value;
        } elseif ($value < 100000) {
            $hasil = "0" . $value;
        }
        return $hasil;
    }

    public function status_pemesanan($val)
    {
        $status = "";
        if ($val == 0) {
            $status = "ORDER";
        } elseif ($val == 1) {
            $status = "Menunggu Pembayaran";
        } elseif ($val == 2) {
            $status = "Konfirmasi Pembayaran";
        } elseif ($val == 3) {
            $status = "Pengiriman";
        } elseif ($val == 4) {
            $status = "Sudah Diterima";
        }
        return $status;
    }

    // public function get_saldo($norek)
    // {
    //     $data = $this->db
    //         ->order_by("notran", "DESC")
    //         ->get_where("tabungan", array("norek" => $norek))->row_array();
    //     if (empty($data)) {
    //         return 0;
    //     } else {
    //         return $data['saldo'];
    //     }
    // }

    // public function jenis_rek($norek)
    // {
    //     $norek = explode(".", $norek);
    //     $data = $this->db->get_where("jtab", array("notab" => $norek[0]))->row_array();
    //     // die(var_dump($norek));
    //     return $data;
    // }

    function get_role()
    {
        // $db      = \Config\Database::connect();
        $ID_AKUN = session()->get("ID_AKUN");
        if (!$ID_AKUN) {
            return redirect()->to(base_url('Login'));
        }
        $data_user = db_connect()->table("akun")->where("ID_AKUN", "$ID_AKUN")->get()->getRow();
        if (!$data_user) {
            return redirect()->to(base_url('Login'));
        }
        $data_jabatan = db_connect()->table("jabatan")->where("ID_JABATAN", $data_user->ID_JABATAN)->get()->getRow();
        $role = array("1");
        $role_user = json_decode($data_user->ROLES);
        $role_jabatan = json_decode($data_jabatan->ROLES_JABATAN);
        // for ($i=1; $i < 999; $i++) { 
        //     $role_jabatan[] = $i;
        // }
        if (is_array($role_jabatan)) {
            if (@count($role_jabatan) > 0) {
                $role = @array_merge($role, @$role_jabatan);
            }
        }
        if (is_array($role_user)) {
            if (@count($role_user) > 0) {
                $role = @array_merge($role, @$role_user);
            }
        }
        $menu = db_connect()->table("menu")->whereIn("idmenu", $role)->get()->getResult();
        // die(json_encode($menu));
        $response = array();
        foreach ($menu as $value) {
            array_push($response, $value->idmenu);
            array_push($response, $value->parent);
            if ($value->sub == 1 && $value->parent > 0) {
                $parent = db_connect()->table("menu")->where("idmenu", $value->parent)->get()->getRowArray();
                if ($parent) {
                    array_push($response, $parent['parent']);
                }
            }
        }
        return $response;
    }


    function get_menu()
    {
        $kategori = db_connect()->table("menu")->where(array("categories" => 1))->orderBy("urutan")->get()->getResult();
        $response = array();
        // die(json_encode($kategori));
        $no_kat = 0;
        foreach ($kategori as $kat) {
            $res_kategori = array(
                'id' => $kat->idmenu,
                'nama' => $kat->nama_menu,
                'icon' => $kat->icon,
                'url' => $kat->url,
                'menu' => array()
            );
            $menu = db_connect()->table("menu")
                ->where(array("parent" => $kat->idmenu))
                ->orderBy("urutan")->get()->getResult();
            // if (empty($menu)) {
            //     continue;
            // }
            array_push($response, $res_kategori);
            $no_menu = 0;
            foreach ($menu as $mymenu) {
                $res_menu = array(
                    'id' => $mymenu->idmenu,
                    'nama' => $mymenu->nama_menu,
                    'icon' => $mymenu->icon,
                    'url' => $mymenu->url,
                    'sub' => array(),
                );
                array_push($response[$no_kat]['menu'], $res_menu);
                $sub_menu = db_connect()->table("menu")
                    ->where(array("parent" => $mymenu->idmenu))
                    ->orderBy("urutan")->get()->getResult();
                foreach ($sub_menu as $sub) {
                    $res_sub = array(
                        'id' => $sub->idmenu,
                        'nama' => $sub->nama_menu,
                        'url' => $sub->url,
                        'icon' => $sub->icon,
                    );
                    array_push($response[$no_kat]['menu'][$no_menu]['sub'], $res_sub);
                }
                $no_menu++;
            }
            $no_kat++;
        }
        return $response;
    }

    function get_menu_show()
    {
        $kategori = db_connect()->table("menu")->where(array("categories" => 1, "show_menu" => 1))->orderBy("urutan")->get()->getResult();
        $response = array();
        // die(json_encode($kategori));
        $no_kat = 0;
        foreach ($kategori as $kat) {
            $res_kategori = array(
                'id'    => $kat->idmenu,
                'nama'  => $kat->nama_menu,
                'icon'  => $kat->icon,
                'url'   => $kat->url,
                'role'  => $kat->roles,
                'utama' => $kat->utama,
                'menu' => array()
            );
            $menu = db_connect()->table("menu")
                ->where(array("parent" => $kat->idmenu, "show_menu" => 1))
                ->orderBy("urutan")->get()->getResult();
            // if (empty($menu)) {
            //     continue;
            // }
            array_push($response, $res_kategori);
            $no_menu = 0;
            foreach ($menu as $mymenu) {
                $res_menu = array(
                    'id' => $mymenu->idmenu,
                    'nama' => $mymenu->nama_menu,
                    'icon' => $mymenu->icon,
                    'url' => $mymenu->url,
                    'role' => $mymenu->roles,
                    'utama' => $mymenu->utama,
                    'sub' => array(),
                );
                array_push($response[$no_kat]['menu'], $res_menu);
                $sub_menu = db_connect()->table("menu")
                    ->where(array("parent" => $mymenu->idmenu, "show_menu" => 1))
                    ->orderBy("urutan")->get()->getResult();
                foreach ($sub_menu as $sub) {
                    $res_sub = array(
                        'id' => $sub->idmenu,
                        'nama' => $sub->nama_menu,
                        'url' => $sub->url,
                        'icon' => $sub->icon,
                        'role' => $sub->roles,
                    );
                    array_push($response[$no_kat]['menu'][$no_menu]['sub'], $res_sub);
                }
                $no_menu++;
            }
            $no_kat++;
        }
        return $response;
    }

    function combine_harga($harga)
    {
        $jml_bayar = "";
        $ex_jml_bayar = explode(",", $harga);
        for ($i = 0; $i < count($ex_jml_bayar); $i++) {
            $jml_bayar = $jml_bayar . "" . $ex_jml_bayar[$i];
        }
        return $jml_bayar;
    }

    public function umur($tgl_lahir = '')
    {
        if ($tgl_lahir == null) {
            return;
        }
        // Tanggal Lahir
        $birthday = $tgl_lahir;

        // Convert Ke Date Time
        $biday = new DateTime($birthday);
        $today = new DateTime();

        $diff = $today->diff($biday);

        // Display
        return $diff->y . " Tahun";
    }

    function terbilang($numb)
    {

        $huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        if ($numb < 12)
            return "" . $huruf[$numb];
        elseif ($numb < 20)
            return $this->terbilang($numb - 10) . " belas ";
        elseif ($numb < 100)
            return $this->terbilang($numb / 10) . " puluh " . $this->terbilang($numb % 10);
        elseif ($numb < 200)
            return "seratus " . $this->terbilang($numb - 100);
        elseif ($numb < 1000)
            return $this->terbilang($numb / 100) . " ratus " . $this->terbilang($numb % 100);
        elseif ($numb < 2000)
            return "seribu " . $this->terbilang($numb - 1000);
        elseif ($numb < 1000000)
            return $this->terbilang($numb / 1000) . " ribu " . $this->terbilang($numb % 1000);
        elseif ($numb < 1000000000)
            return $this->terbilang($numb / 1000000) . " juta " . $this->terbilang($numb % 1000000);
        elseif ($numb >= 1000000000)
            return false;
    }
    public function tgl_terbilang($tanggal)
    {
        $nama_hari = array(
            'Sun' => "Minggu",
            'Mon' => "Senin",
            'Tue' => "Selasa",
            'Wed' => "Rabu",
            'Thu' => "Kamis",
            'Fri' => "Jum'at",
            'Sat' => "Sabtu"
        );
        $nama_bulan = array(
            1 => "Januari",
            2 => "Februari",
            3 => "Maret",
            4 => "April",
            5 => "Mei",
            6 => "Juni",
            7 => "Juli",
            8 => "Agustus",
            9 => "September",
            10 => "Oktober",
            11 => "November",
            12 => "Desember",
        );
        $hari = date("D", strtotime($tanggal));
        $tgl = (int) date("d", strtotime($tanggal));
        $bulan = (int) date("m", strtotime($tanggal));
        $tahun = date("Y", strtotime($tanggal));
        return "hari ini " . ucwords($nama_hari[$hari]) . " tanggal " . ucwords($this->terbilang($tgl)) . " bulan " . ucfirst($nama_bulan[(int) $bulan]) . " tahun " . ucwords($this->terbilang($tahun));
    }

    function jenis_periode()
    {
        $data = array(
            array(
                'id' => 'bln',
                'nama' => 'Bulan'
            ),
            array(
                'id' => 'hr',
                'nama' => 'Hari'
            ),
            array(
                'id' => 'thn',
                'nama' => 'Tahun'
            ),
            // array(
            //     'id' => 'rw',
            //     'nama' => 'Rentang Waktu'
            // ),
        );
        return $data;
    }

    function getBulan($index = 0)
    {
        $bulan = array(
            "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "Nopember",
            "Desember"
        );
        if ($index > 0) {
            return $bulan[$index];
        } else {
            return $bulan;
        }
    }

    function getTahun()
    {
        $tahun = array();
        for ($i = 2023; $i <= date("Y"); $i++) {
            array_push($tahun, $i);
        }
        return $tahun;
    }

    function nama_bulan($bulan)
    {
        $nama_bulan = array(
            1 => "Januari",
            2 => "Februari",
            3 => "Maret",
            4 => "April",
            5 => "Mei",
            6 => "Juni",
            7 => "Juli",
            8 => "Agustus",
            9 => "September",
            10 => "Oktober",
            11 => "November",
            12 => "Desember",
        );
        return $nama_bulan[(int) $bulan];
    }

    function setExportExcel($title = "File Excel Simkes", $filename = "file Excel Simkes", $data = array())
    {
        // Create a new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Get the active sheet
        $sheet = $spreadsheet->getActiveSheet();

        // Set the title
        $sheet->setCellValue('A1', $title);

        // Apply style to the title
        $titleStyle = [
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '000000'], // Black color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);

        // Set data starting from cell A3
        $sheet->fromArray($data, null, 'A3');

        // Apply style to the table
        $tableHeaderStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '000000'], // Black color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'], // Black color
                ],
            ]
        ];
        $tableStyle = [
            'font' => [
                'bold' => false,
                'size' => 12,
                'color' => ['rgb' => '000000'], // Black color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'], // Black color
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'], // Yellow color
            ],
        ];
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $sheet->mergeCells("A1:" . $lastColumn . "1");

        // Apply style to the title
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $tableRange = "A3:" . $lastColumn . "3";
        $sheet->getStyle($tableRange)->applyFromArray($tableHeaderStyle);
        $tableRange = "A4:$lastColumn$lastRow";
        $sheet->getStyle($tableRange)->applyFromArray($tableStyle);

        // Set auto column width
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save the spreadsheet to a file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);

        // Download the file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $filename . '.xlsx');
        header('Cache-Control: max-age=0');

        return $writer->save('php://output');
    }
}
