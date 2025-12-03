<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cities\CityChangeSortMultipleRequest;
use App\Http\Requests\Admin\Cities\CityChangeSortRequest;
use App\Http\Requests\Admin\Cities\CityStoreRequest;
use App\Http\Requests\Admin\Cities\CityUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\Admin\Cities\CitiesResource;
use App\Http\Resources\Admin\Cities\CityResource;
use App\Http\Resources\GeneralResource;
use App\Models\City;
use App\Models\Country;
use App\Repositories\CityRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CityController extends Controller
{
    private CityRepository $repo;

    public function __construct(CityRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request, Country $country): AnonymousResourceCollection
    {
        return CitiesResource::collection($this->repo->load($country, $request->validated()));
    }

    public function list(Country $country): AnonymousResourceCollection
    {
        return CitiesListResource::collection($this->repo->list($country));
    }

    public function show(Country $country, City $city): CityResource
    {
        $city = $this->repo->show($country, $city);

        return CityResource::make($city);
    }

    public function store(CityStoreRequest $request, Country $country): JsonResponse
    {
        $city = $this->repo->store($country, $request);

        $city = $this->repo->loadRelations($city);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('city_form_saved_successfully'),
            'data' => CitiesResource::make($city),
        ]));
    }

    public function update(CityUpdateRequest $request, Country $country, City $city): JsonResponse
    {
        $city = $this->repo->update($country, $city, $request);

        $city = $this->repo->loadRelations($city);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('city_form_saved_successfully'),
            'data' => CitiesResource::make($city),
        ]));
    }

    public function destroy(Country $country, City $city): JsonResponse
    {
        $this->repo->destroy($country, $city);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('city_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Country $country, City $city): JsonResponse
    {
        $this->repo->changeActiveStatus($country, $city);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(CityChangeSortRequest $request, Country $country, City $city): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($country, $city, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(CityChangeSortMultipleRequest $request, Country $country): JsonResponse
    {
        $this->repo->changeSortingMultiple($country, $request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
