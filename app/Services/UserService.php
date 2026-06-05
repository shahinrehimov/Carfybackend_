<?php

namespace App\Services;

use App\Enum\UserOtpProvidersEnum;
use App\Enum\UserOtpTypesEnum;
use App\Enum\UserRegisterTypesEnum;
use App\Models\Guest;
use App\Services\ReferralService;
use App\Models\ThirdPartyProviderLoginToken;
use App\Models\User;
use App\Models\UserOtpCode;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    public function createToken(User $user, ?string $device_type = null): User
    {
        $deviceName = TokenService::instance()->resolveDeviceName($device_type);
        $newToken = $user->createToken($deviceName, ['user']);
        $user->token = $newToken->plainTextToken;

        TokenService::instance()->fillTokenMeta($newToken);

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

        if ($user->register_type !== UserRegisterTypesEnum::EMAIL->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid register type')
                    ->getLang('change_password_invalid_register_type')
            );
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

    public function registerWithEmail(FormRequest $request): User
    {
        $validated = $request->validated();
        $refCode   = $validated['ref_code'] ?? null;

        $validated['register_type'] = UserRegisterTypesEnum::EMAIL->value;

        $user = $this->registerUser($validated);

        UserService::instance()->sendEmailVerificationNotification($user);

        ReferralService::instance()->handleRegistration($user, $refCode, $validated['device_fingerprint'] ?? null);

        return $user;
    }

    public function registerWithPhone(FormRequest $request): User
    {
        $validated = $request->validated();
        $refCode   = $validated['ref_code'] ?? null;

        $this->checkOtpConfirmationToken($validated['phone_confirmation_token'], UserOtpTypesEnum::REGISTER, $validated['otp'], null, UserOtpProvidersEnum::SMS, $validated['phone']);

        $validated['register_type'] = UserRegisterTypesEnum::PHONE->value;
        $validated['phone_unique_key'] = md5($validated['phone']);
        $validated['phone_verified_at'] = Carbon::now();

        $_this = $this;

        $user = DB::transaction(function () use ($validated, $_this) {
            $user = $_this->registerUser($validated);

            $_this->removeFakePhones($user);

            return $user;
        });

        ReferralService::instance()->handleRegistration($user, $refCode, $validated['device_fingerprint'] ?? null);

        return $user;
    }

    public function registerUser(array $validated, bool $checkEmail = true, bool $removeFake = false): User
    {
        if ($checkEmail) {
            $this->checkEmail($validated['email'], null, $removeFake);
        }

        return DB::transaction(function () use ($validated) {
            $validated['uuid'] = Str::uuid();

            $validated['email_old'] = $validated['email'] ?? null;
            $validated['phone_old'] = $validated['phone'] ?? null;

            $validated['email_unique_key'] = $validated['email_unique_key'] ?? md5(Str::uuid());
            $validated['phone_unique_key'] = $validated['phone_unique_key'] ?? md5(Str::uuid());

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

    public function checkPhoneForLogin(string $phone): User
    {
        $user = User::query()
            ->where('register_type', UserRegisterTypesEnum::PHONE->value)
            ->where('phone', $phone)
            ->whereNotNull('phone_verified_at')
            ->first();

        if (!$user) {
            throw new HttpResponseException(response()->json([
                'message' => LangService::instance()
                    ->setDefault('Invalid phone!')
                    ->getLang('login_invalid_phone'),
            ], 401));
        }

        return $user;
    }

    public function checkPhone(string $phone, ?int $userId = null, bool $removeFake = false): void
    {
        $checkPhone = User::query()
            ->when($userId, function ($query) use ($userId) {
                $query->where('id', '!=', $userId);
            })
            ->where('phone', $phone)
            ->orderByDesc('id')
            ->first();

        if ($checkPhone) {
            if ($checkPhone->phone_verified_at !== null) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Phone already used')
                        ->getLang('phone_already_used')
                );
            }

            if ($removeFake) {
                LoggerService::instance()->log('User deleted because phone fake. ID: ' . $checkPhone->id, [], true);

                DB::transaction(function () use ($checkPhone) {
                    $checkPhone->update([
                        'phone' => null,
                        'phone_unique_key' => md5(Str::uuid()),
                    ]);
                });
            }
        }
    }

    public function checkEmail(string $email, ?int $userId = null, bool $removeFake = false): void
    {
        $checkEmail = User::query()
            ->when($userId, function ($query) use ($userId) {
                $query->where('id', '!=', $userId);
            })
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();

        if ($checkEmail) {
            if ($checkEmail->email_verified_at !== null || !$checkEmail->created_at->addMinutes(10)->isPast()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Email already used')
                        ->getLang('email_already_used')
                );
            }

            if ($removeFake) {
                LoggerService::instance()->log('User deleted because email fake. ID: ' . $checkEmail->id, [], true);

                DB::transaction(function () use ($checkEmail) {
                    $checkEmail->update([
                        'email' => null,
                        'email_unique_key' => md5(Str::uuid()),
                    ]);
                });
            }
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
            $user->sendEmailVerificationNotificationOtp($this->generateOtp(UserOtpTypesEnum::REGISTER, UserOtpProvidersEnum::EMAIL, $user));
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
            $user->sendChangePasswordNotificationOtp($this->generateOtp(UserOtpTypesEnum::CHANGE_PASSWORD, UserOtpProvidersEnum::EMAIL, $user));
        }
    }

    public function generateOtp(UserOtpTypesEnum $type, UserOtpProvidersEnum $provider, ?User $user = null, ?string $receiver = null): int
    {
        $otp = config('app.otp_debug_mode') ? 1907 : rand(1000, 9999);

        if ($user) {
            $user->otpCodes()
                ->create([
                    'receiver' => $provider->value === UserOtpProvidersEnum::SMS->value ? $user->phone : $user->email,
                    'code' => $otp,
                    'type' => $type->value,
                    'provider' => $provider->value,
                ]);
        } else if ($receiver) {
            UserOtpCode::query()->create([
                'receiver' => $receiver,
                'code' => $otp,
                'type' => $type->value,
                'provider' => $provider->value,
            ]);
        } else {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Receiver not found')
                    ->getLang('otp_receiver_not_found')
            );
        }

        return $otp;
    }

    public function checkOtp(UserOtpTypesEnum $type, int $otp, ?User $user = null, ?UserOtpProvidersEnum $providerEnum = null, ?string $receiver = null): string
    {
        if ($providerEnum === null) {
            $provider = match ($user->register_type) {
                UserRegisterTypesEnum::PHONE->value => UserOtpProvidersEnum::SMS->value,
                default => UserOtpProvidersEnum::EMAIL->value,
            };
        } else {
            $provider = $providerEnum->value;
        }

        if ($user) {
            $checkOtp = $user->otpCodes()
                ->where('type', $type->value)
                ->where('provider', $provider)
                ->where('created_at', '>=', Carbon::now()->subMinutes(UserOtpCode::EXPIRED_MINUTE))
                ->orderByDesc('id')
                ->first();
        } else if ($receiver) {
            $checkOtp = UserOtpCode::query()
                ->whereNull('user_id')
                ->where('receiver', $receiver)
                ->where('type', $type->value)
                ->where('provider', $provider)
                ->where('created_at', '>=', Carbon::now()->subMinutes(UserOtpCode::EXPIRED_MINUTE))
                ->orderByDesc('id')
                ->first();
        } else {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Receiver not found')
                    ->getLang('otp_receiver_not_found')
            );
        }

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

        $confirmationToken = sha1(md5(sha1(Str::uuid())));

        $checkOtp->update([
            'used_at' => Carbon::now(),
            'confirmation_token' => $confirmationToken,
        ]);

        return $confirmationToken;
    }

    public function checkOtpConfirmationToken(string $confirmationToken, UserOtpTypesEnum $type, int $otp, ?User $user = null, ?UserOtpProvidersEnum $providerEnum = null, ?string $receiver = null): void
    {
        if ($providerEnum === null) {
            $provider = match ($user->register_type) {
                UserRegisterTypesEnum::PHONE->value => UserOtpProvidersEnum::SMS->value,
                default => UserOtpProvidersEnum::EMAIL->value,
            };
        } else {
            $provider = $providerEnum->value;
        }

        if ($user) {
            $checkOtp = $user->otpCodes()
                ->where('confirmation_token', $confirmationToken)
                ->where('type', $type->value)
                ->where('provider', $provider)
                ->whereNotNull('used_at')
                ->orderByDesc('id')
                ->first();
        } else if ($receiver) {
            $checkOtp = UserOtpCode::query()
                ->where('confirmation_token', $confirmationToken)
                ->whereNull('user_id')
                ->where('receiver', $receiver)
                ->where('type', $type->value)
                ->where('provider', $provider)
                ->whereNotNull('used_at')
                ->orderByDesc('id')
                ->first();
        } else {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Receiver not found')
                    ->getLang('otp_receiver_not_found')
            );
        }

        if (!$checkOtp) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('OTP confirmation not found')
                    ->getLang('otp_confirmation_not_found')
            );
        }

        if ($checkOtp->confirmation_token_used_at) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('OTP confirmation already used')
                    ->getLang('otp_confirmation_already_used')
            );
        }

        if ($checkOtp->used_at->addHour()->isPast()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('OTP confirmation expired')
                    ->getLang('otp_confirmation_expired')
            );
        }

        $checkOtp->update([
            'confirmation_token_used_at' => Carbon::now(),
        ]);
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

    public function removeFakeEmails(User $user): void
    {
        $emails = User::query()
            ->where('email', $user->email)
            ->whereNull('email_verified_at')
            ->where('id', '!=', $user->id)
            ->get();

        foreach ($emails as $email) {
            $email->update([
                'email' => null,
                'email_unique_key' => md5(Str::uuid()),
            ]);
        }

        LoggerService::instance()->log('Removed fake emails: ' . $user->email, [], true);
    }

    public function removeFakePhones(User $user): void
    {
        $emails = User::query()
            ->where('phone', $user->phone)
            ->whereNull('phone_verified_at')
            ->where('id', '!=', $user->id)
            ->get();

        foreach ($emails as $email) {
            $email->update([
                'phone' => null,
                'phone_unique_key' => md5(Str::uuid()),
            ]);
        }

        LoggerService::instance()->log('Removed fake phones: ' . $user->phone, [], true);
    }
}
