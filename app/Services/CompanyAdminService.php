<?php

namespace App\Services;

use App\Http\Resources\Sellers\Auth\UserResource;
use App\Models\Company;
use App\Models\CompanyAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyAdminService
{
    private static ?CompanyAdminService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): CompanyAdminService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function createToken(CompanyAdmin $user, $deviceType): UserResource
    {
        $newToken = $user->createToken($deviceType, ['company_admin']);
        $user->token = $newToken->plainTextToken;

        $ip = request()->ip();
        $locationData = Location::get($ip);
        $location = null;

        if ($locationData) {
            $parts = array_filter([$locationData->cityName, $locationData->countryName]);
            $location = implode(', ', $parts) ?: null;
        }

        $newToken->accessToken->update([
            'ip_address' => $ip,
            'location'   => $location,
        ]);

        return new UserResource($user);
    }

    public function changePassword(array $validated, ?CompanyAdmin $user = null): void
    {
        if ($user === null) {
            $user = $this->getUser();

            if (!empty($validated['old_password'])) {
                Hash::check($validated['old_password'], $user->password) ?: throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Invalid password')
                        ->getLang('invalid_password')
                );
            }
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Your new password cannot be the same as your current password!')
                    ->getLang('user_new_password_same_as_current')
            );
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'password' => $validated['password'],
                'password_must_change' => false,
            ]);

            $currentTokenId = $user->currentAccessToken()->id ?? 0;

            PersonalAccessToken::query()
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', CompanyAdmin::class)
                ->where('id', '<>', $currentTokenId)
                ->delete();
        });
    }

    public function logout(): void
    {
        $this->getUser()->currentAccessToken()->delete();
    }

    public function logoutAll(): void
    {
        $this->getUser()->tokens()->delete();
    }

    public function getUser(): CompanyAdmin
    {
        /** @var CompanyAdmin $user */
        $user = auth("company_admin")->user();

        return $user;
    }

    public function checkUser(): bool
    {
        return auth("company_admin")->check();
    }

    public function updateProfile(FormRequest $request)
    {
        $_this = $this;

        return DB::transaction(static function () use ($request, $_this) {
            $_this->getUser()->update($request->validated());

            FileUpload::upload($request, 'image', 'company_admins', $_this->getUser());

            return $_this->getUser();
        });
    }

    public function getCompany(): Company
    {
        $this->getUser()->loadMissing([
            'company',
        ]);

        return $this->getUser()->company;
    }
}
