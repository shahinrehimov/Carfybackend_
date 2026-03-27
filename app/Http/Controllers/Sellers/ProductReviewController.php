<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\Sellers\ProductReviews\ProductReviewsResource;
use App\Models\CompanyProductVariant;
use App\Repositories\ProductReviewRepository;
use App\Services\CompanyAdminService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProductReviewController extends Controller
{
    private ProductReviewRepository $repo;

    public function __construct(ProductReviewRepository $repo)
    {
        $this->repo = $repo;
    }

    public function load(GeneralListRequest $request, CompanyProductVariant $companyProductVariant): AnonymousResourceCollection
    {
        $companyId = CompanyAdminService::instance()->getUser()->company_id;

        if ($companyProductVariant->company_id !== $companyId) {
            throw new AccessDeniedHttpException();
        }

        return ProductReviewsResource::collection(
            $this->repo->loadForSite($companyProductVariant, $request->validated())
        );
    }

    public function loadAll(GeneralListRequest $request): AnonymousResourceCollection
    {
        $companyId = CompanyAdminService::instance()->getUser()->company_id;

        return ProductReviewsResource::collection(
            $this->repo->loadForCompany($companyId, $request->validated())
        );
    }
}
