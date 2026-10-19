<?php

namespace App\Filters;

use App\Security\MenuRoutePermissions;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class MenuPermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $permission = MenuRoutePermissions::resolve(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if ($permission === null) {
            return null;
        }

        if (session()->get('logged_in') !== true) {
            return redirect()->to(base_url('/login'))->with('error', 'Invalid Credential Login');
        }

        $accountId = session()->get('ID_AKUN');
        if (!$accountId) {
            return service('response')->setStatusCode(403)->setBody('Akses ditolak. Permission menu tidak ditemukan.');
        }

        $db = db_connect();
        $account = $db->table('akun')->select('ID_JABATAN, ROLES')->where('ID_AKUN', $accountId)->get()->getRowArray();
        if (!$account) {
            return service('response')->setStatusCode(403)->setBody('Akses ditolak. Akun tidak valid.');
        }

        $roleIds = $this->decodeRoleIds($account['ROLES'] ?? null);
        if (!empty($account['ID_JABATAN'])) {
            $job = $db->table('jabatan')->select('ROLES_JABATAN')
                ->where('ID_JABATAN', $account['ID_JABATAN'])->get()->getRowArray();
            if ($job) {
                $roleIds = array_merge($roleIds, $this->decodeRoleIds($job['ROLES_JABATAN'] ?? null));
            }
        }

        $roleIds = array_values(array_unique(array_map('strval', $roleIds)));
        if ($roleIds === []) {
            return service('response')->setStatusCode(403)->setBody('Akses ditolak. Permission menu tidak ditemukan.');
        }

        $menus = $db->table('menu')->select('idmenu, roles')->whereIn('idmenu', $roleIds)->get()->getResultArray();
        if (!MenuRoutePermissions::isGranted($permission, $roleIds, $menus)) {
            return service('response')->setStatusCode(403)->setBody('Akses ditolak. Anda tidak memiliki izin untuk fitur ini.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    private function decodeRoleIds($json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $roles = json_decode($json, true);
        if (!is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, static function ($role): bool {
            return is_string($role) || is_int($role);
        }));
    }
}
