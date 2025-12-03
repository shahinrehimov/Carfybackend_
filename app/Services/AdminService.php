<?php

namespace App\Services;

use App\Http\Resources\Admin\Auth\UserResource;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AdminService
{
    private static ?AdminService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): AdminService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function createToken(Admin $user, $deviceType): UserResource
    {
        $user->token = $user->createToken($deviceType, ['admin'])->plainTextToken;

        $user->load([
            'roles',
            'media',
        ]);

        return new UserResource($user);
    }

    public function changePassword(array $validated): void
    {
        $user = $this->getUser();

        if (!Hash::check($validated['old_password'], $user->password)) {
            throw new HttpResponseException(response()->json([
                'message' => LangService::instance()
                    ->setDefault('Invalid old password!')
                    ->getLang('invalid_old_password'),
            ], 400));
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw new HttpResponseException(response()->json([
                'message' => LangService::instance()
                    ->setDefault('New password must be different from the old one!')
                    ->getLang('new_password_must_be_different'),
            ], 400));
        }

        $user->update($validated);

        $currentTokenId = $user->currentAccessToken()->id ?? 0;

        PersonalAccessToken::query()
            ->where('tokenable_id', auth()->id())
            ->where('tokenable_type', Admin::class)
            ->where('id', '<>', $currentTokenId)
            ->delete();
    }

    public function logout(): void
    {
        $this->getUser()->currentAccessToken()->delete();
    }

    public function logoutAll(): void
    {
        $this->getUser()->tokens()->delete();
    }

    public function getUser(): Admin
    {
        /** @var Admin $user */
        $user = auth("admin")->user();

        return $user;
    }

    public function checkUser(): bool
    {
        return auth("admin")->check();
    }

    public function updateProfile(FormRequest $request)
    {
        $_this = $this;

        return DB::transaction(static function () use ($request, $_this) {
            $_this->getUser()->update($request->validated());

            FileUpload::upload($request, 'image', 'admins', $_this->getUser());

            return $_this->getUser();
        });
    }
}
