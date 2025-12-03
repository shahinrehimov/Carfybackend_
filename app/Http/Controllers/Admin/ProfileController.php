<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\ChangePasswordRequest;
use App\Http\Requests\Admin\Profile\ProfileUpdateRequest;
use App\Http\Resources\Admin\Auth\UserResource;
use App\Http\Resources\GeneralResource;
use App\Services\AdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): GeneralResource
    {
        AdminService::instance()->changePassword($request->validated());

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Your password has been changed! You are logged out of other devices.')
                ->getLang('admin_password_changed'),
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
        AdminService::instance()->getUser()
            ->load([
                'roles',
                'media',
            ]);

        return UserResource::make(AdminService::instance()->getUser());
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        AdminService::instance()->updateProfile($request);

        AdminService::instance()->getUser()
            ->load([
                'roles',
                'media',
            ]);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('admin_form_saved_successfully'),
            'data' => UserResource::make(AdminService::instance()->getUser()),
        ]));
    }
}
