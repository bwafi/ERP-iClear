<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelBank;

class Bank extends BaseController

{

    protected $AuthModel;
    protected $BankModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->BankModel = new ModelBank();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));

        $data =  array(
            'akun' => $akun,
            'bank' => $this->BankModel->getBankSemua(),
            'body'  => 'datamaster/bank'
        );
        return view('template', $data);
    }
}