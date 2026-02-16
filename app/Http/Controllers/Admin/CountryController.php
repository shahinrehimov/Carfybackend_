<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Countries\CountryChangeSortMultipleRequest;
use App\Http\Requests\Admin\Countries\CountryChangeSortRequest;
use App\Http\Requests\Admin\Countries\CountryListRequest;
use App\Http\Requests\Admin\Countries\CountryStoreRequest;
use App\Http\Requests\Admin\Countries\CountryUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Countries\CountriesListResource;
use App\Http\Resources\Admin\Countries\CountriesResource;
use App\Http\Resources\Admin\Countries\CountryResource;
use App\Http\Resources\GeneralResource;
use App\Models\Country;
use App\Repositories\CountryRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CountryController extends Controller
{
    private CountryRepository $repo;

    public function __construct(CountryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return CountriesResource::collection($this->repo->load($request->validated()));
    }

    public function list(CountryListRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return CountriesListResource::collection($this->repo->list(($validated['with_cities'] ?? 'no') === 'yes'));
    }

    public function show(Country $country): CountryResource
    {
        $country = $this->repo->show($country);

        return CountryResource::make($country);
    }

    public function store(CountryStoreRequest $request): JsonResponse
    {
        $country = $this->repo->store($request);

        $country = $this->repo->loadRelations($country);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('country_form_saved_successfully'),
            'data' => CountriesResource::make($country),
        ]));
    }

    public function update(CountryUpdateRequest $request, Country $country): JsonResponse
    {
        $country = $this->repo->update($country, $request);

        $country = $this->repo->loadRelations($country);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('country_form_saved_successfully'),
            'data' => CountriesResource::make($country),
        ]));
    }

    public function destroy(Country $country): JsonResponse
    {
        $this->repo->destroy($country);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('country_deleted_successfully'),
        ]));
    }

    public function changeActiveStatus(Country $country): JsonResponse
    {
        $this->repo->changeActiveStatus($country);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeSorting(CountryChangeSortRequest $request, Country $country): JsonResponse
    {
        $validated = $request->validated();

        $this->repo->changeSorting($country, $validated['sort']);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }

    public function changeSortingMultiple(CountryChangeSortMultipleRequest $request): JsonResponse
    {
        $this->repo->changeSortingMultiple($request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Sorting changed successfully!')
                ->getLang('admin_sorting_changed_successfully'),
        ]));
    }
}
