<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Company\CompanyStoreRequest;
use App\Http\Requests\Admin\Company\CompanyUpdateRequest;
use App\Http\Requests\Admin\Company\CompanyWorkingHoursRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\Companies\CompaniesListResource;
use App\Http\Resources\Admin\Companies\CompaniesResource;
use App\Http\Resources\Admin\Companies\CompanyResource;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\StoreProfile\StoreProfileWorkingHourResource;
use App\Models\Company;
use App\Repositories\CompanyRepository;
use App\Services\CompanyService;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyRepository $companyRepository
    ) {
    }

    public function index(GeneralListRequest $request): AnonymousResourceCollection
    {
        return CompaniesResource::collection(
            $this->companyRepository->load($request->validated())
        );
    }

    public function list(): AnonymousResourceCollection
    {
        return CompaniesListResource::collection(
            $this->companyRepository->list()
        );
    }

    public function show(Company $company): CompanyResource
    {
        $this->companyRepository->show($company);

        return CompanyResource::make($company);
    }

    public function store(CompanyStoreRequest $request): GeneralResource
    {
        $company = CompanyService::instance()->createByAdmin($request);

        $company = $this->companyRepository->loadRelations($company);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company created successfully!')
                ->getLang('company_created_successfully'),
            'data' => CompaniesResource::make($company),
        ]);
    }

    public function update(Company $company, CompanyUpdateRequest $request): GeneralResource
    {
        $company = $this->companyRepository->update($company, $request);

        $company = $this->companyRepository->loadRelations($company);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company updated successfully!')
                ->getLang('company_updated_successfully'),
            'data' => CompaniesResource::make($company),
        ]);
    }

    public function destroy(Company $company): GeneralResource
    {
        $this->companyRepository->destroy($company);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company deleted successfully!')
                ->getLang('company_deleted_successfully'),
        ]);
    }

    public function verify(Company $company): GeneralResource
    {
        $company = $this->companyRepository->verify($company);

        $company = $this->companyRepository->loadRelations($company);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company verified successfully!')
                ->getLang('company_verified_successfully'),
            'data' => CompaniesResource::make($company),
        ]);
    }

    public function changeActiveStatus(Company $company): GeneralResource
    {
        $company = $this->companyRepository->changeActiveStatus($company);

        $company = $this->companyRepository->loadRelations($company);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company status changed successfully!')
                ->getLang('company_status_changed_successfully'),
            'data' => CompaniesResource::make($company),
        ]);
    }

    public function updateWorkingHours(Company $company, CompanyWorkingHoursRequest $request): JsonResponse
    {
        $this->companyRepository->syncWorkingHours($company, $request->validated()['hours']);

        $company->load('workingHours');

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Saved successfully!')
                ->getLang('company_admin_form_saved_successfully'),
            'data' => [
                'working_hours' => StoreProfileWorkingHourResource::collection($company->workingHours),
            ],
        ]));
    }

    public function deleteLogo(Company $company): JsonResponse
    {
        $this->companyRepository->deleteLogo($company);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('deleted_successfully'),
        ]));
    }

    public function deleteBanner(Company $company): JsonResponse
    {
        $this->companyRepository->deleteBanner($company);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('deleted_successfully'),
        ]));
    }
}
