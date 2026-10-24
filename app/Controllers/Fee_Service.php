<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use App\Models\ModelDetailPembelian;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelReturCustomer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Models\ModelService;
use App\Models\ModelHppKlaimLoss;

class Fee_Service extends BaseController

{

    protected $KategoriModel;
    protected $DetailPembelianModel;
    protected $AuthModel;
    protected $ReturCustomerModel;
    protected $ServiceModel;
    protected $HppKlaimLossModel;


    public function __construct()
    {
        $this->KategoriModel = new ModelKategori();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->AuthModel = new ModelAuth();
        $this->ReturCustomerModel = new ModelReturCustomer();
        $this->ServiceModel = new ModelService();
        $this->HppKlaimLossModel = new ModelHppKlaimLoss();
    }

    public function index()
    {
        $service = $this->ServiceModel->getServiceWithLaba();
        $hppKlaim = $this->HppKlaimLossModel->getLoss(date('Y-m-01'), date('Y-m-t'));
        $data = array(
            'service' => $service,
            'hpp_klaim' => $hppKlaim,
            'total_hpp_klaim' => array_sum(array_map(static fn ($row) => (int) $row->total, $hppKlaim)),
            'body' => 'riwayat/fee_service'
        );
        return view('template', $data);
    }
}