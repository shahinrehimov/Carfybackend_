<?php

namespace App\Services;


use App\Enum\UserRegisterTypesEnum;
use App\Models\User;
use App\Services\ReferralService;
use Carbon\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class GoogleService
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ALLOWED_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    private static ?GoogleService $instance = null;
    private ?string $name = null;

    private function __construct()
    {

    }

    public static function instance(): GoogleService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getLoginUrl(): string
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function getCallbackUser(Request $request)
    {
        $gUser = Socialite::driver('google')->stateless()->user();

        $this->assertEmailIsVerified($gUser->getEmail(), $gUser->getRaw()['email_verified'] ?? null);

        return $gUser;
    }

    /**
     * Google only vouches for the mailbox when email_verified is true. The email
     * is used to link an existing account, so an unverified one must never be
     * trusted. The claim is a bool in the ID token but may arrive as a string,
     * and PHP treats the string "false" as truthy — hence the explicit filter.
     */
    private function assertEmailIsVerified(?string $email, mixed $emailVerified): void
    {
        if (empty($email)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Unable to retrieve email from Google account!')
                    ->getLang('unable_to_retrieve_email_from_google_account')
            );
        }

        if (!filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
            LoggerService::instance()->log('Google login rejected: email is not verified.', [
                'email' => $email,
                'email_verified' => $emailVerified,
            ], true);

            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Google account email is not verified!')
                    ->getLang('google_account_email_is_not_verified')
            );
        }
    }

    /**
     * Native (mobile) flow: verify the ID token issued on-device and build a
     * Socialite user so the rest of the login pipeline can be reused as-is.
     */
    public function loginFromNative(array $validated): SocialiteUser
    {
        $payload = $this->verifyIdToken($validated['id_token']);

        $gUser = new SocialiteUser();

        $gUser->map([
            'id'     => $payload['sub'],
            'name'   => $payload['name'] ?? null,
            'email'  => $payload['email'] ?? null,
            'avatar' => $payload['picture'] ?? null,
        ]);

        return $gUser->setRaw($payload);
    }

    /**
     * Verify a Google ID token against Google's public JWKS.
     *
     * @return array Decoded token claims.
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            $keys = JWK::parseKeySet($this->getGooglePublicKeys());

            $payload = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            LoggerService::instance()->log('Google id token decode failed: ' . $e->getMessage(), [], true);

            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Invalid Google id token!')
                    ->getLang('invalid_google_id_token')
            );
        }

        $allowedAudiences = array_filter([
            config('services.google.client_id'),
            config('services.google.ios_client_id'),
            config('services.google.android_client_id'),
        ]);

        if (!in_array($payload['iss'] ?? null, self::ALLOWED_ISSUERS, true)
            || !in_array($payload['aud'] ?? null, $allowedAudiences, true)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Invalid Google id token!')
                    ->getLang('invalid_google_id_token')
            );
        }

        $this->assertEmailIsVerified($payload['email'] ?? null, $payload['email_verified'] ?? null);

        return $payload;
    }

    private function getGooglePublicKeys(): array
    {
        return cache()->remember('google_jwks', now()->addDay(), static function () {
            return Http::get(self::GOOGLE_CERTS_URL)->json();
        });
    }

    /**
     * Resolve first/last name from a Google profile. Socialite only maps the
     * full `name`, so given_name/family_name are read off the raw payload —
     * present both in the userinfo response and in the native ID token.
     *
     * @return array{0: string, 1: string} [name, surname]
     */
    private function resolveNames($gUser): array
    {
        $raw = $gUser->getRaw();

        $name = $raw['given_name'] ?? null;

        if (empty($name)) {
            $name = $gUser->getName() ?: Str::before($gUser->getEmail(), '@');
        }

        $surname = $raw['family_name'] ?? null;

        if (empty($surname)) {
            $surname = $name;
        }

        return [$name, $surname];
    }

    public function updateOrCreateUser(?User $user, $gUser, ?string $refCode = null): User
    {
        if (!$user) {
            [$name, $surname] = $this->resolveNames($gUser);

            $newUser = UserService::instance()->registerUser([
                'name' => $name,
                'surname' => $surname,
                'email' => $gUser->getEmail(),
                'email_verified_at' => Carbon::now(),
                'register_type' => UserRegisterTypesEnum::GOOGLE->value,
                'email_unique_key' => md5($gUser->getEmail()),
                'phone_unique_key' => md5(Str::uuid()),
                'google_id' => $gUser->getId(),
                'google_avatar' => $gUser->getAvatar(),
            ], false);

            ReferralService::instance()->handleRegistration($newUser, $refCode);

            return $newUser;
        }

        $data = [];

        if (!$user->isGoogleUser()) {
            $data['google_id'] = $gUser->getId();
            $data['google_avatar'] = $gUser->getAvatar();
        }

        if (!$user->isEmailVerified()) {
            $data['email_verified_at'] = Carbon::now();
        }

        $user->update($data);

        UserService::instance()->removeFakeEmails($user);

        return $user;
    }
}
