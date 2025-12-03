<?php

namespace App\Services;

use App\Enum\UserOtpTypesEnum;
use App\Models\Guest;
use App\Models\ThirdPartyProviderLoginToken;
use App\Models\User;
use App\Models\UserOtpCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UserService
{
    private static ?UserService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): UserService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function createToken(User $user, string $device_type): User
    {
        $user->token = $user->createToken($device_type, ['user'])->plainTextToken;

        $this->getDataFromGuest($user);

        return $user;
    }

    public function changePassword(array $validated, ?User $user = null): void
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
            $user->update($validated);

            $current_token_id = $user->currentAccessToken()->id ?? 0;

            PersonalAccessToken::query()
                ->where('tokenable_id', auth()->id())
                ->where('tokenable_type', User::class)
                ->where('id', '<>', $current_token_id)
                ->delete();
        });
    }

    public function logout(): void
    {
        DB::transaction(function () {
            $this->getUser()->currentAccessToken()->delete();
        });
    }

    public function logoutAll(): void
    {
        DB::transaction(function () {
            $this->getUser()->tokens()->delete();
        });
    }

    public function registerUser(array $validated, bool $checkEmail = true): User
    {
        if ($checkEmail) {
            $this->checkEmail($validated['email']);
        }

        return DB::transaction(function () use ($validated) {
            return User::query()->create($validated);
        });
    }

    public function updateProfile(User $user, array $validated): User
    {
        if (isset($validated['email'])) {
            $this->checkEmail($validated['email'], $user->id);
        }

        return DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            return $user;
        });
    }

    public function checkEmail(string $email, int $userId = null): void
    {
        $checkEmail = User::query()
            ->when($userId, function ($query) use ($userId) {
                $query->where('id', '!=', $userId);
            })
            ->where('email', $email)
            ->first();

        if ($checkEmail) {
            if ($checkEmail->email_verified === 'yes' || !$checkEmail->created_at->addMinutes(10)->isPast()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Email already used')
                        ->getLang('email_already_used')
                );
            }

            LoggerService::instance()->log('User deleted because email fake. ID: ' . $checkEmail->id, [], true);

            DB::transaction(function () use ($checkEmail) {
                $checkEmail->delete();
            });
        }
    }

    public function sendEmailVerificationNotification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Email already verified')
                    ->getLang('email_already_verified')
            );
        }

        if (User::EMAIL_VERIFICATION_TYPE === 'otp') {
            $user->sendEmailVerificationNotificationOtp($this->generateOtp($user, UserOtpTypesEnum::REGISTER));
        } else {
            // link
            $user->sendEmailVerificationNotification();
        }
    }

    public function sendChangePasswordNotification(User $user, array $validated = []): void
    {
        if (!empty($validated['old_password']) && !Hash::check($validated['old_password'], $user->password)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid password')
                    ->getLang('invalid_password')
            );
        }

        if (!empty($validated['password']) && Hash::check($validated['password'], $user->password)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Your new password cannot be the same as your current password!')
                    ->getLang('user_new_password_same_as_current')
            );
        }

        if (User::EMAIL_VERIFICATION_TYPE === 'otp') {
            $user->sendChangePasswordNotificationOtp($this->generateOtp($user, UserOtpTypesEnum::CHANGE_PASSWORD));
        }
    }

    public function generateOtp(User $user, UserOtpTypesEnum $type): int
    {
        $otp = config('app.otp_debug_mode') ? 1907 : rand(1000, 9999);

        $user->otpCodes()->create([
            'code' => $otp,
            'type' => $type->value,
        ]);

        return $otp;
    }

    public function checkOtp(User $user, UserOtpTypesEnum $type, int $otp): void
    {
        $checkOtp = $user->otpCodes()
            ->where('type', $type->value)
            ->where('created_at', '>=', Carbon::now()->subMinutes(UserOtpCode::EXPIRED_MINUTE))
            ->orderByDesc('id')
            ->first();

        if (!$checkOtp) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('OTP not found')
                    ->getLang('otp_not_found')
            );
        }

        if ($checkOtp->used_at) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('OTP already used')
                    ->getLang('otp_already_used')
            );
        }

        if ($checkOtp->attempts >= UserOtpCode::MAX_ATTEMPTS) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Maximum OTP attempts exceeded')
                    ->getLang('maximum_otp_attempts_exceeded')
            );
        }

        $checkOtp->increment('attempts');

        if ($checkOtp->code !== $otp) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid OTP')
                    ->getLang('invalid_otp')
            );
        }

        $checkOtp->update(['used_at' => Carbon::now()]);
    }

    public function getUser(): User
    {
        /** @var User $user */
        $user = auth("user")->user();

        return $user;
    }

    public function checkUser(): bool
    {
        return auth("user")->check();
    }

    public function getUserOrGuest(): Guest|User
    {
        if ($this->checkUser()) {
            return $this->getUser();
        }

        if (GuestService::instance()->checkGuest()) {
            return GuestService::instance()->getGuest();
        }

        throw new AccessDeniedHttpException(
            LangService::instance()
                ->setDefault('User or Guest not found!')
                ->getLang('user_or_guest_not_found')
        );
    }

    public function getDataFromGuest(User $user): void
    {
        if (GuestService::instance()->checkGuest()) {
            $guest = GuestService::instance()->getGuest();

            DB::transaction(function () use ($guest, $user) {
                WishlistService::instance()->moveToUser($guest, $user);
                BasketService::instance()->moveToUser($guest, $user);

                $guest->delete();
            });
        }
    }

    public function getUserByGoogle($gUser): ?User
    {
        $user = User::query()->where('google_id', $gUser->getId())->first();

        if ($user) {
            if ($user->email !== $gUser->getEmail()) {
                LoggerService::instance()->log('Google account email does not match.', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'google_email' => $gUser->getEmail(),
                ], true);

                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('The Google account email does not match our records! Please contact support.')
                        ->getLang('google_account_email_does_not_match')
                );
            }
        } else {
            $user = User::query()->where('email', $gUser->getEmail())->first();
        }

        return $user;
    }

    public function generateLoginTokenForThirdPartyApp(User $user, string $provider, string $providerUserId): string
    {
        $token = sha1(md5(sha1(Str::uuid())));

        $user->thirdPartyProviderLoginTokens()->create([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'token' => $token,
        ]);

        return $token;
    }

    public function loginWithThirdPartyProvider(array $validated): User
    {
        $checkToken = ThirdPartyProviderLoginToken::query()
            ->where('provider', $validated['provider'])
            ->where('token', $validated['token'])
            ->whereNull('used_at')
            ->where('created_at', '>=', Carbon::now()->subMinute())
            ->orderByDesc('id')
            ->first();

        if (!$checkToken) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid or expired token')
                    ->getLang('invalid_or_expired_token')
            );
        }

        $user = User::query()
            ->where('id', $checkToken->user_id)
            ->when($validated['provider'], function ($query) use ($validated, $checkToken) {
                switch ($validated['provider']) {
                    case 'google':
                        $query->where('google_id', $checkToken->provider_user_id);
                        break;
                    default:
                        throw new BadRequestHttpException(
                            LangService::instance()
                                ->setDefault('Unsupported provider')
                                ->getLang('unsupported_provider')
                        );
                }
            })
            ->first();

        if (!$user) {
            throw new UnauthorizedHttpException(
                LangService::instance()
                    ->setDefault('User not found for the given provider')
                    ->getLang('user_not_found_for_given_provider')
            );
        }

        $checkToken->update(['used_at' => Carbon::now()]);

        return UserService::instance()->createToken($user, $validated['device_type']);
    }
}
