<?php

namespace App\Repositories;

use App\Enum\ProductVariantApprovalStatusEnum;
use App\Models\CompanyProductVariant;
use App\Models\ProductVariant;
use App\Models\SellerSearchToken;
use App\Services\CompanyAdminService;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyProductVariantRepository
{
    public function checkCompanyOwnership(CompanyProductVariant $cpv): void
    {
        if (CompanyAdminService::instance()->checkUser()) {
            if ($cpv->company_id !== CompanyAdminService::instance()->getUser()->company_id) {
                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('You do not have permission to access this listing!')
                        ->getLang('you_do_not_have_permission_to_access_this_listing')
                );
            }
        }
    }

    public function attach(ProductVariant $productVariant, FormRequest $request): CompanyProductVariant
    {
        $user = CompanyAdminService::instance()->getUser();
        $token = request()->header('X-Search-Token');

        if (!$token) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Search token is required. Please search the catalog first.')
                    ->getLang('invalid_search_token')
            );
        }

        $record = SellerSearchToken::query()
            ->where('uuid', $token)
            ->where('company_id', $user->company_id)
            ->where('admin_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(60))
            ->first();

        if (!$record) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid or missing search token. Please search the catalog first.')
                    ->getLang('invalid_search_token')
            );
        }

        $record->delete();

        return DB::transaction(static function () use ($productVariant, $request) {
            $validated = $request->validated();

            $companyId = CompanyAdminService::instance()->getUser()->company_id;

            $existing = CompanyProductVariant::query()
                ->where('company_id', $companyId)
                ->where('product_variant_id', $productVariant->id)
                ->first();

            if ($existing) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('You already have a listing for this product variant!')
                        ->getLang('company_product_variant_already_attached')
                );
            }

            /** @var CompanyProductVariant $cpv */
            $cpv = CompanyProductVariant::query()->create([
                'company_id' => $companyId,
                'product_variant_id' => $productVariant->id,
                'stock' => $validated['stock'] ?? 0,
                'price' => $validated['price'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_start_at' => $validated['discount_start_at'] ?? null,
                'discount_end_at' => $validated['discount_end_at'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'is_active' => true,
                'approval_status' => ProductVariantApprovalStatusEnum::PENDING->value,
            ]);

            $cpv->approvalStatuses()->create([
                'product_variant_id' => $productVariant->id,
                'approval_status' => ProductVariantApprovalStatusEnum::PENDING->value,
            ]);

            FileUpload::multipleUpload($request, 'images', 'company_product_variant_images', $cpv);

            return $cpv;
        });
    }

    public function update(CompanyProductVariant $cpv, FormRequest $request): CompanyProductVariant
    {
        $this->checkCompanyOwnership($cpv);

        return DB::transaction(static function () use ($cpv, $request) {
            $validated = $request->validated();

            $cpv->update([
                'stock' => $validated['stock'] ?? $cpv->getRawOriginal('stock'),
                'price' => $validated['price'] ?? $cpv->price,
                'discount_type' => $validated['discount_type'] ?? $cpv->discount_type,
                'discount' => $validated['discount'] ?? $cpv->discount,
                'discount_start_at' => $validated['discount_start_at'] ?? $cpv->discount_start_at,
                'discount_end_at' => $validated['discount_end_at'] ?? $cpv->discount_end_at,
                'city_id' => $validated['city_id'] ?? $cpv->city_id,
            ]);

            return $cpv;
        });
    }

    public function detach(CompanyProductVariant $cpv): void
    {
        $this->checkCompanyOwnership($cpv);

        DB::transaction(static function () use ($cpv) {
            $cpv->delete();
        });
    }

    public function load(array $validated): LengthAwarePaginator
    {
        $companyId = CompanyAdminService::instance()->getUser()->company_id;

        return CompanyProductVariant::query()
            ->where('company_id', $companyId)
            ->with([
                'productVariant',
                'productVariant.media',
                'productVariant.product',
                'productVariant.product.translatable',
                'productVariant.product.media',
                'productVariant.options',
                'productVariant.options.option',
                'productVariant.options.option.translatable',
                'productVariant.options.item',
                'productVariant.options.item.translatable',
                'productVariant.brandModel',
                'productVariant.brandModel.translatable',
                'city',
                'city.translatable',
                'media',
            ])
            ->when($validated['product_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereHas('productVariant', function (Builder $q) use ($validated) {
                    $q->where('product_id', $validated['product_id']);
                });
            })
            ->when($validated['product_variant_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_variant_id', $validated['product_variant_id']);
            })
            ->when($validated['approval_status'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('approval_status', $validated['approval_status']);
            })
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('productVariant', function (Builder $q) use ($validated) {
                        $q->where('oem_code', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                    $builder->orWhereHas('productVariant.product.translatable', function (Builder $q) use ($validated) {
                        $q->where('column', 'title');
                        $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function show(CompanyProductVariant $cpv): CompanyProductVariant
    {
        $this->checkCompanyOwnership($cpv);

        $cpv->load([
            'productVariant',
            'productVariant.media',
            'productVariant.product',
            'productVariant.product.translatable',
            'productVariant.product.media',
            'productVariant.product.brand',
            'productVariant.product.brand.translatable',
            'productVariant.options',
            'productVariant.options.option',
            'productVariant.options.option.translatable',
            'productVariant.options.option.items',
            'productVariant.options.option.items.translatable',
            'productVariant.brandModel',
            'productVariant.brandModel.translatable',
            'productVariant.sets',
            'productVariant.sets.product',
            'productVariant.sets.product.translatable',
            'productVariant.sets.product.media',
            'productVariant.groups',
            'productVariant.groups.translatable',
            'city',
            'city.translatable',
            'media',
            'company',
        ]);

        return $cpv;
    }

    public function changeActiveStatus(CompanyProductVariant $cpv): void
    {
        $this->checkCompanyOwnership($cpv);

        DB::transaction(function () use ($cpv) {
            $cpv->is_active = !$cpv->is_active;
            $cpv->save();
        });
    }

    public function addImages(CompanyProductVariant $cpv, FormRequest $request): void
    {
        $this->checkCompanyOwnership($cpv);

        DB::transaction(static function () use ($cpv, $request) {
            FileUpload::multipleUpload($request, 'images', 'company_product_variant_images', $cpv);
        });
    }

    public function deleteImage(CompanyProductVariant $cpv, int $mediaId): void
    {
        $this->checkCompanyOwnership($cpv);

        DB::transaction(static function () use ($cpv, $mediaId) {
            $cpv->deleteMedia($mediaId);
        });
    }

    public function approvalStatusHistory(CompanyProductVariant $cpv, array $validated = []): LengthAwarePaginator
    {
        $this->checkCompanyOwnership($cpv);

        return $cpv->approvalStatuses()
            ->with([
                'creatable',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function approvalStatusResend(CompanyProductVariant $cpv): void
    {
        $this->checkCompanyOwnership($cpv);

        if ($cpv->approval_status !== ProductVariantApprovalStatusEnum::REJECTED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Only rejected listings can be resent for approval!')
                    ->getLang('only_rejected_listings_can_be_resent_for_approval')
            );
        }

        DB::transaction(function () use ($cpv) {
            $cpv->approval_status = ProductVariantApprovalStatusEnum::PENDING->value;
            $cpv->approval_status_message = null;
            $cpv->save();

            $cpv->approvalStatuses()->create([
                'product_variant_id' => $cpv->product_variant_id,
                'approval_status' => ProductVariantApprovalStatusEnum::PENDING->value,
            ]);
        });
    }

    public function approvalStatusApprove(CompanyProductVariant $cpv): void
    {
        if ($cpv->approval_status === ProductVariantApprovalStatusEnum::APPROVED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The listing is already approved!')
                    ->getLang('company_product_variant_already_approved')
            );
        }

        DB::transaction(function () use ($cpv) {
            $cpv->approval_status = ProductVariantApprovalStatusEnum::APPROVED->value;
            $cpv->approval_status_message = null;
            $cpv->save();

            $cpv->approvalStatuses()->create([
                'product_variant_id' => $cpv->product_variant_id,
                'approval_status' => ProductVariantApprovalStatusEnum::APPROVED->value,
            ]);
        });
    }

    public function approvalStatusReject(CompanyProductVariant $cpv, array $validated = []): void
    {
        if ($cpv->approval_status === ProductVariantApprovalStatusEnum::REJECTED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The listing is already rejected!')
                    ->getLang('company_product_variant_already_rejected')
            );
        }

        DB::transaction(function () use ($cpv, $validated) {
            $cpv->approval_status = ProductVariantApprovalStatusEnum::REJECTED->value;
            $cpv->approval_status_message = $validated['approval_status_message'] ?? null;
            $cpv->save();

            $cpv->approvalStatuses()->create([
                'product_variant_id' => $cpv->product_variant_id,
                'approval_status' => ProductVariantApprovalStatusEnum::REJECTED->value,
                'approval_status_message' => $validated['approval_status_message'] ?? null,
            ]);
        });
    }

    public function loadForAdmin(array $validated): LengthAwarePaginator
    {
        return CompanyProductVariant::query()
            ->with([
                'productVariant',
                'productVariant.media',
                'productVariant.product',
                'productVariant.product.translatable',
                'productVariant.brandModel',
                'productVariant.brandModel.translatable',
                'company',
                'company.translatable',
                'city',
                'city.translatable',
                'media',
                'creatable',
            ])
            ->when($validated['company_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('company_id', $validated['company_id']);
            })
            ->when($validated['product_variant_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_variant_id', $validated['product_variant_id']);
            })
            ->when($validated['approval_status'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('approval_status', $validated['approval_status']);
            })
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('productVariant', function (Builder $q) use ($validated) {
                        $q->where('oem_code', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                    $builder->orWhereHas('productVariant.product.translatable', function (Builder $q) use ($validated) {
                        $q->where('column', 'title');
                        $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }
}
