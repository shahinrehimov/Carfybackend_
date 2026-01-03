<?php

namespace App\Http\Controllers\Sellers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class LogoutController extends Controller
{
    public function logout(): JsonResponse
    {
        CompanyAdminService::instance()->logout();

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Logged out successfully')
                ->getLang('logged_out_successfully'),
        ]);
    }

    public function logoutAll(): JsonResponse
    {
        CompanyAdminService::instance()->logoutAll();

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Logged out from all devices successfully')
                ->getLang('logged_out_all_successfully'),
        ]);
    }
}
