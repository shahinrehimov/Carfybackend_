<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BrandModels\BrandModelsListResource;
use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\CarColors\CarColorsListResource;
use App\Http\Resources\Admin\CarTypes\CarTypesListResource;
use App\Models\ProductOptionItem;
use App\Http\Resources\Admin\EngineVolumes\EngineVolumesListResource;
use App\Http\Resources\Admin\FuelTypes\FuelTypesListResource;
use App\Http\Resources\Admin\TransmissionTypes\TransmissionTypesListResource;
use App\Models\Brand;
use App\Models\CarType;
use App\Models\EngineVolume;
use App\Models\FuelType;
use App\Models\TransmissionType;
use App\Repositories\BrandModelRepository;
use App\Repositories\BrandRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarLibraryController extends Controller
{
    public function brands(Request $request): AnonymousResourceCollection
    {
        $withModels = $request->query('with_models', 'no') === 'yes';
        $brands = (new BrandRepository())->list($withModels);

        return BrandsListResource::collection($brands);
    }

    public function brandModels(Brand $brand): AnonymousResourceCollection
    {
        $models = (new BrandModelRepository())->list($brand);

        return BrandModelsListResource::collection($models);
    }

    public function fuelTypes(): AnonymousResourceCollection
    {
        $items = FuelType::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return FuelTypesListResource::collection($items);
    }

    public function transmissionTypes(): AnonymousResourceCollection
    {
        $items = TransmissionType::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return TransmissionTypesListResource::collection($items);
    }

    public function carTypes(): AnonymousResourceCollection
    {
        $items = CarType::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return CarTypesListResource::collection($items);
    }

    public function engineVolumes(): AnonymousResourceCollection
    {
        $items = EngineVolume::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('value_liters')
            ->get();

        return EngineVolumesListResource::collection($items);
    }

    public function carColors(): AnonymousResourceCollection
    {
        $items = ProductOptionItem::query()
            ->forOption('color')
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return CarColorsListResource::collection($items);
    }
}
