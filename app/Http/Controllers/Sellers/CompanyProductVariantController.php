<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Requests\Sellers\CompanyProductVariants\AddImagesRequest;
use App\Http\Requests\Sellers\CompanyProductVariants\AttachRequest;
use App\Http\Requests\Sellers\CompanyProductVariants\LoadRequest;
use App\Http\Requests\Sellers\CompanyProductVariants\UpdateRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\CompanyProductVariants\CompanyProductVariantListResource;
use App\Http\Resources\Sellers\CompanyProductVariants\CompanyProductVariantResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantApprovalStatusHistoryResource;
use App\Models\CompanyProductVariant;
use App\Models\ProductVariant;
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
        return CompanyProductVariantListResource::collection($this->repo->load($request->validated()));
    }

    public function show(CompanyProductVariant $companyProductVariant): CompanyProductVariantResource
    {
        $cpv = $this->repo->show($companyProductVariant);

        return CompanyProductVariantResource::make($cpv);
    }

    public function attach(AttachRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $cpv = $this->repo->attach($productVariant, $request);

        $cpv = $this->repo->show($cpv);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Listing created successfully!')
                ->getLang('company_product_variant_attached_successfully'),
            'data' => CompanyProductVariantResource::make($cpv),
        ]));
    }

    public function update(UpdateRequest $request, CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $cpv = $this->repo->update($companyProductVariant, $request);

        $cpv = $this->repo->show($cpv);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Listing updated successfully!')
                ->getLang('company_product_variant_updated_successfully'),
            'data' => CompanyProductVariantResource::make($cpv),
        ]));
    }

    public function detach(CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->detach($companyProductVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Listing removed successfully!')
                ->getLang('company_product_variant_detached_successfully'),
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

    public function addImages(AddImagesRequest $request, CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->addImages($companyProductVariant, $request);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Images uploaded successfully!')
                ->getLang('company_product_variant_images_uploaded_successfully'),
        ]));
    }

    public function deleteImage(CompanyProductVariant $companyProductVariant, int $mediaId): JsonResponse
    {
        $this->repo->deleteImage($companyProductVariant, $mediaId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Image deleted successfully!')
                ->getLang('product_image_deleted_successfully'),
        ]));
    }

    public function approvalStatusHistory(GeneralListRequest $request, CompanyProductVariant $companyProductVariant): AnonymousResourceCollection
    {
        return ProductVariantApprovalStatusHistoryResource::collection(
            $this->repo->approvalStatusHistory($companyProductVariant, $request->validated())
        );
    }

    public function approvalStatusResend(CompanyProductVariant $companyProductVariant): JsonResponse
    {
        $this->repo->approvalStatusResend($companyProductVariant);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }
}
