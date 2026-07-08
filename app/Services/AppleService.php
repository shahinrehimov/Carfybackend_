<?php

namespace App\Services;


use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AppleService
{
    private const APPLE_URL = 'https://appleid.apple.com';

    private static ?AppleService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): AppleService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Web redirect flow: build the Apple authorization URL.
     */
    public function getLoginUrl(): string
    {
        return Socialite::driver('apple')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Web redirect flow: resolve the user from Apple's form_post callback.
     *
     * @return array{sub: string, email: ?string, name: ?string, surname: ?string, refresh_token: ?string}
     */
    public function getCallbackUser(): array
    {
        $appleUser = Socialite::driver('apple')->stateless()->user();

        $email = $appleUser->getEmail();

        if (empty($email)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Unable to retrieve email from Apple account!')
                    ->getLang('unable_to_retrieve_email_from_apple_account')
            );
        }

        $raw = $appleUser->getRaw();

        return [
            'sub'           => $appleUser->getId(),
            'email'         => $email,
            'name'          => $raw['name']['firstName'] ?? null,
            'surname'       => $raw['name']['lastName'] ?? null,
            'refresh_token' => $appleUser->refreshToken ?? null,
        ];
    }

    /**
     * Native (mobile) flow: verify the identity token issued on-device and,
     * when supplied, exchange the authorization code for a refresh token.
     *
     * @return array{sub: string, email: ?string, name: ?string, surname: ?string, refresh_token: ?string}
     */
    public function loginFromNative(array $validated): array
    {
        $payload = $this->verifyIdentityToken($validated['identity_token']);

        $refreshToken = null;

        if (!empty($validated['authorization_code'])) {
            $refreshToken = $this->exchangeAuthorizationCode($validated['authorization_code']);
        }

        return [
            'sub'           => $payload['sub'],
            'email'         => $payload['email'] ?? null,
            'name'          => $validated['name'] ?? null,
            'surname'       => $validated['surname'] ?? null,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Verify an Apple identity token against Apple's public JWKS.
     *
     * @return array Decoded token claims.
     */
    public function verifyIdentityToken(string $identityToken): array
    {
        try {
            $keys = JWK::parseKeySet($this->getApplePublicKeys());

            $payload = (array) JWT::decode($identityToken, $keys);
        } catch (\Throwable $e) {
            LoggerService::instance()->log('Apple identity token decode failed: ' . $e->getMessage(), [], true);

            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Invalid Apple identity token!')
                    ->getLang('invalid_apple_identity_token')
            );
        }

        $allowedAudiences = array_filter([
            config('services.apple.bundle_id'),
            config('services.apple.client_id'),
        ]);

        if (($payload['iss'] ?? null) !== self::APPLE_URL || !in_array($payload['aud'] ?? null, $allowedAudiences, true)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Invalid Apple identity token!')
                    ->getLang('invalid_apple_identity_token')
            );
        }

        if (empty($payload['email'])) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Unable to retrieve email from Apple account!')
                    ->getLang('unable_to_retrieve_email_from_apple_account')
            );
        }

        return $payload;
    }

    public function updateOrCreateUser(?User $user, array $appleUser, ?string $refCode = null): User
    {
        if (!$user) {
            $name = $appleUser['name'] ?? Str::before($appleUser['email'], '@');
            $surname = $appleUser['surname'] ?? $name;

            $newUser = UserService::instance()->registerUser([
                'register_type' => 'apple',
                'name' => $name,
                'surname' => $surname,
                'email' => $appleUser['email'],
                'email_verified_at' => Carbon::now(),
                'email_unique_key' => md5($appleUser['email']),
                'phone_unique_key' => md5(Str::uuid()),
                'apple_id' => $appleUser['sub'],
                'apple_refresh_token' => $appleUser['refresh_token'] ?? null,
            ], false);

            ReferralService::instance()->handleRegistration($newUser, $refCode);

            return $newUser;
        }

        $data = [];

        if (!$user->isAppleUser()) {
            $data['apple_id'] = $appleUser['sub'];
        }

        // Apple only returns the name on the first authorization; keep whatever we have.
        if (empty($user->name) && !empty($appleUser['name'])) {
            $data['name'] = $appleUser['name'];
            $data['surname'] = $appleUser['surname'] ?? $appleUser['name'];
        }

        if (!empty($appleUser['refresh_token'])) {
            $data['apple_refresh_token'] = $appleUser['refresh_token'];
        }

        if (!$user->isEmailVerified()) {
            $data['email_verified_at'] = Carbon::now();
        }

        if (!empty($data)) {
            $user->update($data);
        }

        UserService::instance()->removeFakeEmails($user);

        return $user;
    }

    /**
     * Exchange an authorization code for a refresh token (native flow).
     */
    public function exchangeAuthorizationCode(string $code): ?string
    {
        try {
            $response = Http::asForm()->post(self::APPLE_URL . '/auth/token', [
                'client_id'     => $this->nativeClientId(),
                'client_secret' => $this->generateClientSecret($this->nativeClientId()),
                'grant_type'    => 'authorization_code',
                'code'          => $code,
            ]);

            if ($response->failed()) {
                LoggerService::instance()->log('Apple authorization code exchange failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ], true);

                return null;
            }

            return $response->json('refresh_token');
        } catch (\Throwable $e) {
            LoggerService::instance()->log('Apple authorization code exchange error: ' . $e->getMessage(), [], true);

            return null;
        }
    }

    /**
     * Revoke a user's Apple refresh token (called on account deletion).
     */
    public function revokeToken(string $refreshToken): void
    {
        try {
            $response = Http::asForm()->post(self::APPLE_URL . '/auth/revoke', [
                'client_id'       => $this->nativeClientId(),
                'client_secret'   => $this->generateClientSecret($this->nativeClientId()),
                'token'           => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ]);

            if ($response->failed()) {
                LoggerService::instance()->log('Apple token revoke failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ], true);
            }
        } catch (\Throwable $e) {
            // Never block account deletion because of a revoke failure.
            LoggerService::instance()->log('Apple token revoke error: ' . $e->getMessage(), [], true);
        }
    }

    /**
     * Generate the Apple client secret (ES256 JWT) for the given client id.
     */
    private function generateClientSecret(string $clientId): string
    {
        $now = Carbon::now();

        $payload = [
            'iss' => config('services.apple.team_id'),
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(30)->timestamp,
            'aud' => self::APPLE_URL,
            'sub' => $clientId,
        ];

        return JWT::encode(
            $payload,
            $this->privateKey(),
            'ES256',
            config('services.apple.key_id')
        );
    }

    /**
     * Resolve the Apple private key, supporting both an inline PEM string and
     * a path to the .p8 file.
     */
    private function privateKey(): string
    {
        $key = (string) config('services.apple.private_key');

        if ($key !== '' && is_file($key)) {
            return (string) file_get_contents($key);
        }

        return $key;
    }

    /**
     * Client id used for native token exchange/revoke (the app bundle id).
     */
    private function nativeClientId(): string
    {
        return config('services.apple.bundle_id') ?? config('services.apple.client_id');
    }

    private function getApplePublicKeys(): array
    {
        return cache()->remember('apple_jwks', now()->addDay(), static function () {
            return Http::get(self::APPLE_URL . '/auth/keys')->json();
        });
    }
}
