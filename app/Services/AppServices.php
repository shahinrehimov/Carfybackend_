<?php

namespace App\Services;

class AppServices
{
    private static ?AppServices $instance = null;
    private array $services = [
        'setting' => SettingService::class,
        'social' => SocialService::class,
        'genders' => GenderService::class,
        'address_type' => AddressTypeService::class,
        'country_and_city' => CountryAndCityService::class,
        'blogs' => BlogService::class,
        'testimonials' => TestimonialService::class,
    ];

    private function __construct() {

    }

    public static function instance(): AppServices
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getServicesList(): array
    {
        return array_keys($this->services);
    }

    public function loadAppServices(array $services): array
    {
        $data = [];

        foreach ($services as $service) {
            if (array_key_exists($service['key'], $this->services)) {
                $data[$service['key']] = $this->services[$service['key']]::instance()->setLimit($service['limit'] ?? null)->getItems();
            }
        }

        return $data;
    }
}
