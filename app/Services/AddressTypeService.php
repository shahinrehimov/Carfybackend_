<?php

namespace App\Services;

use App\Http\Resources\Admin\AddressTypes\AddressTypesListResource;
use App\Repositories\AddressTypeRepository;
use App\Traits\AppServicesTrait;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressTypeService
{
    use AppServicesTrait;

    private static ?AddressTypeService $instance = null;
    private AddressTypeRepository $repo;

    private function __construct()
    {
        $this->repo = new AddressTypeRepository();
    }

    public static function instance(): AddressTypeService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): AnonymousResourceCollection
    {
        return AddressTypesListResource::collection($this->repo->list());
    }
}
