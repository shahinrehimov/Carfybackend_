<?php

namespace App\Http\Controllers\Sellers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Auth\RegisterRequest;
use App\Http\Resources\Sellers\Auth\UserResource;
use App\Services\CompanyService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = CompanyService::instance()->register($request);

        return response()->json([
            'step' => 'login',
            'user' => UserResource::make($user),
            'message' => LangService::instance()
                ->setDefault('Register successfully!')
                ->getLang('register_successfully'),
        ]);
    }
}
