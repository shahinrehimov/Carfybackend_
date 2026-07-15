<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Requests\Sellers\CompanyProductVariants\LoadRequest;
use App\Http\Requests\Sellers\ProductVariants\ProductVariantApprovalStatusChangeRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\CompanyProductVariants\CompanyProductVariantListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantApprovalStatusHistoryResource;
use App\Models\CompanyProductVariant;
use App\Repositories\CompanyProductVariantRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyProductVariantController extends Controller
{
    private CompanyProductVariantRepository $repo;

    public function __construct(CompanyProductVariantRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(LoadRequest $request): AnonymousResourceCollection
    {
        return CompanyProductVariantListResource::collection($this->repo->loadForAdmin($request->validated()));
    }

    public function approvalStatusHistory(GeneralListRequest $request, CompanyProductVariant $companyProductVariant): AnonymousResourceCollection
    {
        return ProductVariantApprovalStatusHistoryResource::collection(
            $this->repo->approvalStatusHistory($companyProductVariant, $request->validated())
        );
    }

    public function approvalStatusApprove(CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->approvalStatusApprove($companyProductVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function approvalStatusReject(ProductVariantApprovalStatusChangeRequest $request, CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->approvalStatusReject($companyProductVariant, $request->validated());

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function changeActiveStatus(CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->changeActiveStatus($companyProductVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }

    public function destroy(CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->detach($companyProductVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_deleted_successfully'),
        ]));
    }
}
