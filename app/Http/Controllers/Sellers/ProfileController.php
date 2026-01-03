<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Auth\ChangePasswordRequest;
use App\Http\Requests\Sellers\Profile\ProfileUpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\Auth\UserResource;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): GeneralResource
    {
        CompanyAdminService::instance()->changePassword($request->validated());

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Your password has been changed! You are logged out of other devices.')
                ->getLang('company_admin_password_changed'),
        ]);
    }

    public function checkLoggedIn(): JsonResponse
    {
        return response()->json([
            'message' => 'LoggedIn',
        ]);
    }

    public function showProfile(): UserResource
    {
        CompanyAdminService::instance()->getUser()
            ->load([
                'company',
                'media',
            ]);

        return UserResource::make(CompanyAdminService::instance()->getUser());
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        CompanyAdminService::instance()->updateProfile($request);

        CompanyAdminService::instance()->getUser()
            ->load([
                'company',
                'media',
            ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('company_admin_form_saved_successfully'),
            'data' => UserResource::make(CompanyAdminService::instance()->getUser()),
        ]));
    }
}
