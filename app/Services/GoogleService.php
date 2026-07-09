<?php

namespace App\Services;


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

        if (empty($gUser->getEmail())) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Unable to retrieve email from Google account!')
                    ->getLang('unable_to_retrieve_email_from_google_account')
            );
        }

        return $gUser;
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

        if (empty($payload['email'])) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Unable to retrieve email from Google account!')
                    ->getLang('unable_to_retrieve_email_from_google_account')
            );
        }

        return $payload;
    }

    private function getGooglePublicKeys(): array
    {
        return cache()->remember('google_jwks', now()->addDay(), static function () {
            return Http::get(self::GOOGLE_CERTS_URL)->json();
        });
    }

    public function updateOrCreateUser(?User $user, $gUser, ?string $refCode = null): User
    {
        if (!$user) {
            $newUser = UserService::instance()->registerUser([
                'name' => $gUser->getName() ?? Str::before($gUser->getEmail(), '@'),
                'surname' => $gUser->getName() ?? Str::before($gUser->getEmail(), '@'), // TODO:: get surname
                'email' => $gUser->getEmail(),
                'email_verified_at' => Carbon::now(),
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
