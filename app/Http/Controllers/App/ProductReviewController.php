<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\ProductReviews\ProductReviewStoreRequest;
use App\Http\Requests\GeneralListRequest;
use App\Http\Resources\App\ProductReviews\ProductReviewsResource;
use App\Models\ProductVariant;
use App\Repositories\ProductReviewRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductReviewController extends Controller
{
    private ProductReviewRepository $repo;

    public function __construct(ProductReviewRepository $repo)
    {
        $this->repo = $repo;
    }

    public function store(ProductReviewStoreRequest $request, ProductVariant $productVariant): ProductReviewsResource
    {
        return ProductReviewsResource::make($this->repo->store($productVariant, $request->validated()));
    }

    public function load(GeneralListRequest $request, ProductVariant $productVariant): AnonymousResourceCollection
    {
        return ProductReviewsResource::collection($this->repo->loadForSite($productVariant, $request->validated()));
    }
}
