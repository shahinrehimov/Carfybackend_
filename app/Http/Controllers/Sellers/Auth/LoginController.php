<?php

namespace App\Http\Controllers\Sellers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Auth\LoginRequest;
use App\Http\Resources\Sellers\Auth\UserResource;
use App\Models\CompanyAdmin;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $fields = $request->validated();

        $user = CompanyAdmin::query()
            ->where(function ($query) use ($fields) {
                $query->where('username', $fields['username']);
                $query->orWhere('email', $fields['username']);
            })
            ->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            throw new HttpResponseException(response()->json([
                'message' => LangService::instance()
                    ->setDefault('Invalid username or password!')
                    ->getLang('invalid_username_or_password'),
            ], 401));
        }

        return CompanyAdminService::instance()->createToken($user, $fields['device_type'] ?? null);
    }
}
