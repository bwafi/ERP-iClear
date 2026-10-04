<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Enable/disable backward compatibility breaking features.
 */
class Feature extends BaseConfig
{
    /**
     * Enable multiple filters for a route or not.
     *
     * If you enable this:
     *   - CodeIgniter\CodeIgniter::handleRequest() uses:
     *     - CodeIgniter\Filters\Filters::enableFilters(), instead of enableFilter()
     *   - CodeIgniter\CodeIgniter::tryToRouteIt() uses:
     *     - CodeIgniter\Router\Router::getFilters(), instead of getFilter()
     *   - CodeIgniter\Router\Router::handle() uses:
     *     - property $filtersInfo, instead of $filterInfo
*   - CodeIgniter\Router\RouteCollection::getFiltersForRoute(), instead of getFilterForRoute()
     *
     * WAJIB true di aplikasi ini. Tanpa flag ini, RouteCollection::getFilterForRoute()
     * (path lama) hanya bisa menyimpan SATU filter per route dan tidak memecah
     * string koma, sehingga route seperti
     *     $routes->post('setor-tunai/save', 'KasBank::saveSetorTunai', ['filter' => ['auth', 'csrf']]);
     * akan gagal dengan FilterException "auth,csrf filter must have a matching alias
     * defined". Konsekuensinya route yang butuh auth + csrf sekaligus (Setor Tunai,
     * Penarikan Tunai) tidak bisa dilindungi CSRF.
     *
     * Tidak mengubah route yang sudah ada: untuk route dengan filter string tunggal,
     * getFiltersForRoute() mengembalikan array satu elemen dan
     * Filters::enableFilters() hanya memanggil enableFilter() yang sama.
     */
    public bool $multipleFilters = true;

    /**
     * Use improved new auto routing instead of the default legacy version.
     */
    public bool $autoRoutesImproved = false;
}
