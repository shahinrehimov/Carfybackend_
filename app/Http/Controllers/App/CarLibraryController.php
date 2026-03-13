<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CarTypes\CarTypesListResource;
use App\Http\Resources\Admin\EngineVolumes\EngineVolumesListResource;
use App\Http\Resources\Admin\FuelTypes\FuelTypesListResource;
use App\Http\Resources\Admin\TransmissionTypes\TransmissionTypesListResource;
use App\Models\CarType;
use App\Models\EngineVolume;
use App\Models\FuelType;
use App\Models\TransmissionType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CarLibraryController extends Controller
{
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
}
