<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\StoreProfile\UpdateStoreProfileRequest;
use App\Http\Requests\Sellers\StoreProfile\UpdateWorkingHoursRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\StoreProfile\StoreProfileResource;
use App\Repositories\StoreProfileRepository;
use App\Services\CompanyAdminService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class StoreProfileController extends Controller
{
    private StoreProfileRepository $repo;

    public function __construct(StoreProfileRepository $repo)
    {
        $this->repo = $repo;
    }

    public function show(): StoreProfileResource
    {
        $company = CompanyAdminService::instance()->getCompany();

        return StoreProfileResource::make($this->repo->show($company));
    }

    public function update(UpdateStoreProfileRequest $request): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();

        $this->repo->update($company, $request);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('company_admin_form_saved_successfully'),
            'data' => StoreProfileResource::make($this->repo->show($company)),
        ]));
    }

    public function deleteLogo(): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();

        $this->repo->deleteLogo($company);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('deleted_successfully'),
        ]));
    }

    public function deleteBanner(): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();

        $this->repo->deleteBanner($company);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('deleted_successfully'),
        ]));
    }

    public function updateWorkingHours(UpdateWorkingHoursRequest $request): JsonResponse
    {
        $company = CompanyAdminService::instance()->getCompany();

        $this->repo->syncWorkingHours($company, $request->validated()['hours']);

        $company->load('workingHours');

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('company_admin_form_saved_successfully'),
            'data' => [
                'working_hours' => \App\Http\Resources\Sellers\StoreProfile\StoreProfileWorkingHourResource::collection(
                    $company->workingHours
                ),
            ],
        ]));
    }
}
