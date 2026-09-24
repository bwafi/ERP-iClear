<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ModelStokBarang;
use Psr\Log\LoggerInterface;
use App\Models\ModelService;
use App\Models\ModelAuth;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;
    protected $ModelStokBarang;
    protected $stokMinimum;
    protected $ServiceModel;
    protected $proses_service;
    protected $bisa_diambil;

    protected $expired_service;

    protected $AuthModel;

    protected $Akun_Service;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);



        //notifikasi stok minimum
        $this->ModelStokBarang = new \App\Models\ModelStokBarang();


        $this->stokMinimum = $this->ModelStokBarang->getStokMinimum();

        \Config\Services::renderer()->setVar('stokMinimum', $this->stokMinimum);

        // end notifikasi stok minimum

        //notifikasi proses service
        $this->ServiceModel = new \App\Models\ModelService();


        $this->proses_service = $this->ServiceModel->ProsesServiceAktif();

        \Config\Services::renderer()->setVar('proses_service', $this->proses_service);

        // end notifikasi proses service

        //notifikasi bisa diambil service

        $this->bisa_diambil = $this->ServiceModel->ServiceBisaDiambil();

        \Config\Services::renderer()->setVar('bisa_diambil', $this->bisa_diambil);

        // end notifikasi bisa diambil service

        //notifikasi expired service

        $this->expired_service = $this->ServiceModel->getExpiredService();

        \Config\Services::renderer()->setVar('expired_service', $this->expired_service);

        // end notifikasi expired service


        $this->AuthModel = new ModelAuth();

        $this->Akun_Service = $this->AuthModel->getByIdWithJabatan(session('ID_AKUN'));
        \Config\Services::renderer()->setVar('akun_service', $this->Akun_Service);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
    }
}
