<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ProductReviews\ProductReviewStoreRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\ProductReviews\ProductReviewsResource;
use App\Models\CompanyProductVariant;
use App\Repositories\ProductReviewRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductReviewController extends Controller
{
    private ProductReviewRepository $repo;

    public function __construct(ProductReviewRepository $repo)
    {
        $this->repo = $repo;
    }

    public function store(ProductReviewStoreRequest $request, CompanyProductVariant $companyProductVariant): ProductReviewsResource
    {
        return ProductReviewsResource::make($this->repo->store($companyProductVariant, $request, $request->validated()));
    }

    public function load(GeneralListRequest $request, CompanyProductVariant $companyProductVariant): AnonymousResourceCollection
    {
        return ProductReviewsResource::collection($this->repo->loadForSite($companyProductVariant, $request->validated()));
    }
}
