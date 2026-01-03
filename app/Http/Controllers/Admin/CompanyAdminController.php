<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Company\CompanyAdminStoreRequest;
use App\Http\Requests\Admin\Company\CompanyAdminUpdateRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Admin\CompanyAdmins\CompanyAdminResource;
use App\Http\Resources\Admin\CompanyAdmins\CompanyAdminsListResource;
use App\Http\Resources\Admin\CompanyAdmins\CompanyAdminsResource;
use App\Http\Resources\GeneralResource;
use App\Models\Company;
use App\Models\CompanyAdmin;
use App\Repositories\CompanyAdminRepository;
use App\Services\LangService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyAdminController extends Controller
{
    public function __construct(
        private readonly CompanyAdminRepository $companyAdminRepository
    ) {
    }

    public function index(GeneralListRequest $request, ?Company $company = null): AnonymousResourceCollection
    {
        return CompanyAdminsResource::collection(
            $this->companyAdminRepository->load($request->validated(), $company?->id)
        );
    }

    public function list(?Company $company = null): AnonymousResourceCollection
    {
        return CompanyAdminsListResource::collection(
            $this->companyAdminRepository->list($company?->id)
        );
    }

    public function show(Company $company, CompanyAdmin $companyAdmin): CompanyAdminResource
    {
        $this->companyAdminRepository->show($company, $companyAdmin);

        return CompanyAdminResource::make($companyAdmin);
    }

    public function store(CompanyAdminStoreRequest $request, Company $company): GeneralResource
    {
        $companyAdmin = $this->companyAdminRepository->store($company, $request);

        $companyAdmin = $this->companyAdminRepository->loadRelations($companyAdmin);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company admin created successfully!')
                ->getLang('company_admin_created_successfully'),
            'data' => CompanyAdminsResource::make($companyAdmin),
        ]);
    }

    public function update(Company $company, CompanyAdmin $companyAdmin, CompanyAdminUpdateRequest $request): GeneralResource
    {
        $companyAdmin = $this->companyAdminRepository->update($company, $companyAdmin, $request);

        $companyAdmin = $this->companyAdminRepository->loadRelations($companyAdmin);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company admin updated successfully!')
                ->getLang('company_admin_updated_successfully'),
            'data' => CompanyAdminsResource::make($companyAdmin),
        ]);
    }

    public function destroy(Company $company, CompanyAdmin $companyAdmin): GeneralResource
    {
        $this->companyAdminRepository->destroy($company, $companyAdmin);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company admin deleted successfully!')
                ->getLang('company_admin_deleted_successfully'),
        ]);
    }

    public function changeActiveStatus(Company $company, CompanyAdmin $companyAdmin): GeneralResource
    {
        $companyAdmin = $this->companyAdminRepository->changeActiveStatus($company, $companyAdmin);

        $companyAdmin = $this->companyAdminRepository->loadRelations($companyAdmin);

        return GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Company admin status changed successfully!')
                ->getLang('company_admin_status_changed_successfully'),
            'data' => CompanyAdminsResource::make($companyAdmin),
        ]);
    }
}
