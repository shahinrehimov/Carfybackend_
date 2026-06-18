<?php

namespace App\Services;

use App\Models\Guest;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GuestService
{
    private static ?GuestService $instance = null;
    private Guest $guest;

    private function __construct()
    {

    }

    public static function instance(): GuestService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function storeGuest(?string $deviceType): string
    {
        if (UserService::instance()->checkUser()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Authenticated users cannot create guest identifiers!')
                    ->getLang('authenticated_users_cannot_create_guest_identifiers')
            );
        }

        $identifier = Str::uuid();

        Guest::query()->create([
            'device_type' => TokenService::instance()->resolveDeviceName($deviceType),
            'identifier' => $identifier,
        ]);

        return $identifier;
    }

    public function setGuestByIdentifier(string $identifier): static
    {
        //logger("identifier");
        //logger($identifier);
        if (!Str::isUuid($identifier)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Invalid guest identifier!')
                    ->getLang('invalid_guest_identifier')
            );
        }

        $guest = Guest::query()
            ->where('identifier', $identifier)
            ->orderByDesc('id')
            ->first();

        if (!$guest) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Guest not found!')
                    ->getLang('guest_not_found')
            );
        }

        return $this->setGuest($guest);
    }

    public function setGuest(Guest $guest): static
    {
        $this->guest = $guest;

        return $this;
    }

    public function checkGuest(): bool
    {
        return !empty($this->guest);
    }

    public function getGuest(): Guest
    {
        if (empty($this->guest)) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Guest identifier not set!')
                    ->getLang('guest_identifier_not_set')
            );
        }

        return $this->guest;
    }

    public function getIdentifier()
    {
        return $this->getGuest()->identifier;
    }
}
