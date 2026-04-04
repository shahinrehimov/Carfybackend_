<?php

namespace App\Repositories;

use App\Models\CompanyProductVariant;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Services\AdminService;
use App\Services\FileUpload;
use App\Services\ProductService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ProductReviewRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return ProductReview::query()
            ->with([
                'productVariant',
                'productVariant.product',
                'productVariant.product.translatable',
                'companyProductVariant',
                'companyProductVariant.company',
                'user',
                'approvedUser',
                'media',
            ])
            ->when($validated['approved'] ?? null, function ($builder) use ($validated) {
                if ($validated['approved'] === 'yes') {
                    $builder->approved();
                } else if ($validated['approved'] === 'no') {
                    $builder->notApproved();
                }
            })
            ->when($validated['product_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_id', $validated['product_id']);
            })
            ->when($validated['product_variant_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_variant_id', $validated['product_variant_id']);
            })
            ->when($validated['company_product_variant_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('company_product_variant_id', $validated['company_product_variant_id']);
            })
            ->when($validated['user_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('user_id', $validated['user_id']);
            })
            ->when($validated['product'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('product.translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'title');
                            $q->where('text', 'ILIKE', '%' . $validated['product'] . '%');
                        });
                    });
                });
            })
            ->when($validated['user'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->where('name', 'ILIKE', '%' . $validated['user'] . '%');
                    $builder->orWhere('surname', 'ILIKE', '%' . $validated['user'] . '%');
                    $builder->orWhere('email', 'ILIKE', '%' . $validated['user'] . '%');
                });
            })
            ->when($validated['review'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('review', 'ILIKE', '%' . $validated['review'] . '%');
            })
            ->when($validated['rating'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('rating', $validated['rating']);
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function loadForSite(CompanyProductVariant $cpv, array $validated = []): LengthAwarePaginator
    {
        return $cpv->reviews()
            ->with('media')
            ->approved()
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 5);
    }

    public function loadForCompany(int $companyId, array $validated = []): LengthAwarePaginator
    {
        return ProductReview::query()
            ->whereHas('companyProductVariant', fn($q) => $q->where('company_id', $companyId))
            ->with([
                'companyProductVariant',
                'productVariant',
                'productVariant.product',
                'productVariant.product.translatable',
                'user',
                'media',
            ])
            ->when($validated['approved'] ?? null, function ($q) use ($validated) {
                $validated['approved'] === 'yes' ? $q->approved() : $q->notApproved();
            })
            ->when($validated['rating'] ?? null, fn($q) => $q->where('rating', $validated['rating']))
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function store(CompanyProductVariant $cpv, FormRequest $request, array $validated): ProductReview
    {
        $cpv->loadMissing(['productVariant']);
        $productVariant = ProductService::instance()->setProductVariant($cpv->productVariant)->getActiveProductVariant();

        if (UserService::instance()->checkUser()) {
            $validated['user_id'] = UserService::instance()->getUser()->id;
        }

        $validated['product_id']                  = $productVariant->product_id;
        $validated['product_variant_id']           = $productVariant->id;
        $validated['company_product_variant_id']   = $cpv->id;

        $review = DB::transaction(static function () use ($cpv, $request, $validated) {
            $review = $cpv->reviews()->create($validated);
            FileUpload::multipleUpload($request, 'images', 'product_review_images', $review);
            return $review;
        });

        $review->load('media');

        return $review;
    }

    public function destroy(ProductReview $productReview): void
    {
        DB::transaction(static function () use ($productReview) {
            $productReview->delete();
        });
    }

    public function approveToggle(ProductReview $productReview): void
    {
        DB::transaction(function () use ($productReview) {
            if ($productReview->isApproved()) {
                $productReview->approved_at = null;
                $productReview->approved_by = null;
            } else {
                $productReview->approved_at = Carbon::now();
                $productReview->approved_by = AdminService::instance()->getUser()->id;
            }

            $productReview->save();
        });
    }
}
