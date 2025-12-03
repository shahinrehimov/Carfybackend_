<?php

namespace App\Services;

use App\Http\Resources\Admin\Countries\CountriesListResource;
use App\Repositories\CountryRepository;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CountryAndCityService
{
    use AppServicesTrait;

    private static ?CountryAndCityService $instance = null;
    private CountryRepository $repo;

    private function __construct() {
        $this->repo = new CountryRepository();
    }

    public static function instance(): CountryAndCityService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        return CountriesListResource::collection($this->repo->list(true));
    }
}
