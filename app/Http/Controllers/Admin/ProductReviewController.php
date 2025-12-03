<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductReviews\ProductReviewSearchRequest;
use App\Http\Resources\Admin\ProductReviews\ProductReviewsResource;
use App\Http\Resources\GeneralResource;
use App\Models\ProductReview;
use App\Repositories\ProductReviewRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductReviewController extends Controller
{
    private ProductReviewRepository $repo;

    public function __construct(ProductReviewRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ProductReviewSearchRequest $request): AnonymousResourceCollection
    {
        return ProductReviewsResource::collection($this->repo->load($request->validated()));
    }

    public function destroy(ProductReview $productReview): JsonResponse
    {
        $this->repo->destroy($productReview);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Deleted successfully!')
                ->getLang('product_review_deleted_successfully'),
        ]));
    }

    public function approveToggle(ProductReview $productReview): JsonResponse
    {
        $this->repo->approveToggle($productReview);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Status changed successfully!')
                ->getLang('admin_status_changed_successfully'),
        ]));
    }
}
