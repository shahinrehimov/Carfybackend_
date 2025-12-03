<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\GeneralResource;
use App\Services\AdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class LogoutController extends Controller
{
    public function logout(): JsonResponse
    {
        AdminService::instance()->logout();

        return response()->json(GeneralResource::make([
            'code' => 200,
            'message' => LangService::instance()
                ->setDefault('Logged out from current device.')
                ->getLang('admin_logged_out_from_current_device'),
        ]));
    }

    public function logoutAll(): JsonResponse
    {
        AdminService::instance()->logoutAll();

        return response()->json(GeneralResource::make([
            'code' => 200,
            'message' => LangService::instance()
                ->setDefault('Logged out from all devices.')
                ->getLang('admin_logged_out_from_all_devices'),
        ]));
    }
}
