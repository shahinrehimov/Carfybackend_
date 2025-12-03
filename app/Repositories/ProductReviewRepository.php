<?php

namespace App\Repositories;

use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Services\AdminService;
use App\Services\ProductService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
                'user',
                'approvedUser',
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

    public function loadForSite(ProductVariant $productVariant, array $validated = []): LengthAwarePaginator
    {
        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        return $productVariant->reviews()
            ->approved()
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 5);
    }

    public function store(ProductVariant $productVariant, array $validated): ProductReview
    {
        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        if (UserService::instance()->checkUser()) {
            $validated['user_id'] = UserService::instance()->getUser()->id;
        }

        $validated['product_id'] = $productVariant->product_id;

        return DB::transaction(static function () use ($productVariant, $validated) {
            return $productVariant->reviews()->create($validated);
        });
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
