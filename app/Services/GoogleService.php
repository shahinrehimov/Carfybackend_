<?php

namespace App\Services;


use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class GoogleService
{
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

    public function updateOrCreateUser(?User $user, $gUser): User
    {
        if (!$user) {
            return UserService::instance()->registerUser([
                'name' => $gUser->getName() ?? Str::before($gUser->getEmail(), '@'),
                'surname' => $gUser->getName() ?? Str::before($gUser->getEmail(), '@'), // TODO:: get surname
                'email' => $gUser->getEmail(),
                'email_verified_at' => Carbon::now(),
                'email_unique_key' => md5($gUser->getEmail()),
                'phone_unique_key' => md5(Str::uuid()),
                'google_id' => $gUser->getId(),
                'google_avatar' => $gUser->getAvatar(),
            ], false);
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
