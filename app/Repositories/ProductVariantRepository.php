<?php

namespace App\Repositories;

use App\Enum\ProductSearchStatusEnum;
use App\Enum\ProductVariantApprovalStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\ProductVariantProductOption;
use App\Services\AdminService;
use App\Services\CompanyAdminService;
use App\Services\FileUpload;
use App\Services\LangService;
use App\Services\ProductService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProductVariantRepository
{
    public function getProductsSearchStatusesList(): array
    {
        return ProductSearchStatusEnum::getList();
    }

    public function load(array $validated, bool $isAdmin = false): LengthAwarePaginator
    {
        $brands = $validated['brands'] ?? [];
        $brandsIds = [];

        if (count($brands)) {
            $brandsIds = Brand::query()
                ->whereIn('slug', $brands)
                ->active()
                ->pluck('id')
                ->toArray();
        }

        $categories = $validated['categories'] ?? [];
        $categoriesIds = [];

        if (count($categories)) {
            $categoriesIds = Category::query()
                ->whereIn('slug', $categories)
                ->active()
                ->onlyParents()
                ->pluck('id')
                ->toArray();
        }

        $subCategories = $validated['sub_categories'] ?? [];
        $subCategoriesIds = [];

        if (count($subCategories)) {
            $subCategoriesIds = Category::query()
                ->whereIn('slug', $subCategories)
                ->active()
                ->onlySubs()
                ->pluck('id')
                ->toArray();
        }

        if ($validated['set_id'] ?? null) {
            $set = ProductVariant::query()
                ->when(CompanyAdminService::instance()->checkUser(), function (Builder $query) {
                    // for sellers
                    $query->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                })
                ->where('id', $validated['set_id'])
                ->where('is_set', true)
                ->first();

            $query = $set->sets();
        } else {
            $query = ProductVariant::query();
        }

        $priceCalculation = ProductVariant::getPriceCalculationQuery();

        $currentLangId = LangService::instance()->getCurrentLangId();

        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        return $query
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->when($isAdmin, function ($builder) use ($validated) {
                // load for a control panel
                if (CompanyAdminService::instance()->checkUser()) {
                    // for sellers
                    $builder->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                } else if (AdminService::instance()->checkUser()) {
                    // for admins
                    if ($validated['company_id'] ?? null) {
                        $builder->where('company_id', $validated['company_id']);
                    }
                }

                $builder
                    ->with([
                        'creatable',
                        'groups',
                        'groups.translatable',
                        'product.creatable',
                        'product.categories',
                        'product.categories.translatable',
                        'product.mainCategory.translatable',
                        'product.mainCategory.parent.translatable',
                    ])
                    ->withCount([
                        'sets',
                    ]);
            }, function ($builder) {
                // load for a site
                $builder->active();
                $builder->whereHas('product', function ($query) {
                    $query->active();
                });
            })
            ->with([
                'sets' => function ($query) {
                    $query->active();
                }
            ])
            ->when($validated['product_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_id', $validated['product_id']);
            })
            ->when($validated['is_set'] ?? null, function ($builder) use ($validated) {
                if ($validated['is_set'] === 'yes') {
                    $builder->isSet();
                } elseif ($validated['is_set'] === 'no') {
                    $builder->isNotSet();
                }
            })
            ->when(count($brandsIds) > 0, function (Builder $builder) use ($brandsIds) {
                $builder->whereHas('product', function (Builder $query) use ($brandsIds) {
                    $query->whereIn('products.brand_id', $brandsIds);
                });
            })
            ->when(count($categoriesIds) > 0, function (Builder $builder) use ($categoriesIds) {
                $builder->whereHas('product.categoriesRel', function (Builder $query) use ($categoriesIds) {
                    $query->whereIn('product_categories.category_id', $categoriesIds);
                });
            })
            ->when(count($subCategoriesIds) > 0, function (Builder $builder) use ($subCategoriesIds) {
                $builder->whereHas('product.categoriesRel', function (Builder $query) use ($subCategoriesIds) {
                    $query->whereIn('product_categories.category_id', $subCategoriesIds);
                });
            })
            ->when($validated['group_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereHas('groupsRel', function (Builder $query) use ($validated) {
                    $query->where('group_products.group_id', $validated['group_id']);
                });
            })
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('product.translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'title');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'description_short');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'description');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                    });
                });
            })
            ->when(
                isset($validated['min_price']) || isset($validated['max_price']),
                function (Builder $builder) use ($validated, $priceCalculation) {
                    if (isset($validated['min_price'])) {
                        $builder->whereRaw("({$priceCalculation}) >= ?", [$validated['min_price']]);
                    }

                    if (isset($validated['max_price'])) {
                        $builder->whereRaw("({$priceCalculation}) <= ?", [$validated['max_price']]);
                    }
                }
            )
            ->when(
                isset($validated['sort']) && isset($validated['sort_type']),
                function (Builder $builder) use ($validated, $priceCalculation, $currentLangId) {
                    $sortType = strtolower($validated['sort_type'] ?? 'desc');

                    switch ($validated['sort']) {
                        case 'title':
                            $builder->leftJoin('model_translations as mt_sort', function ($join) use ($currentLangId) {
                                $join->on('mt_sort.translatable_id', '=', 'products.id')
                                    ->where('mt_sort.translatable_type', '=', Product::class)
                                    ->where('mt_sort.column', '=', 'title')
                                    ->where('mt_sort.language_id', '=', $currentLangId)
                                    ->whereNull('mt_sort.deleted_at');
                            })
                                ->orderBy('mt_sort.text', $sortType);
                            break;

                        case 'price':
                            $builder->orderByRaw("{$priceCalculation} {$sortType}");
                            break;

                        case 'date':
                            $builder->orderBy('product_variants.id', $sortType);
                            break;

                        default:
                            $builder->orderByDesc('product_variants.id');
                            break;
                    }
                },
                function (Builder $builder) {
                    $builder->orderByDesc('product_variants.id');
                }
            )
            //->select('products.*')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(array $validated): LengthAwarePaginator
    {
        if ($validated['set_id'] ?? null) {
            $set = ProductVariant::query()
                ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                    // for sellers
                    $builder->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                })
                ->where('id', $validated['set_id'])
                ->where('is_set', true)
                ->first();

            $query = $set->sets();
        } else {
            $query = ProductVariant::query();
        }

        return $query
            ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                // for sellers
                $builder->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
            })
            ->active()
            ->with([
                'product',
                'product.translatable',
            ])
            ->when($validated['product_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_id', $validated['product_id']);
            })
            ->when($validated['is_set'] ?? null, function ($builder) use ($validated) {
                if ($validated['is_set'] === 'yes') {
                    $builder->isSet();
                } elseif ($validated['is_set'] === 'no') {
                    $builder->isNotSet();
                }
            })
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('product.translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'title');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function checkCompanyForSellers(ProductVariant $productVariant): void
    {
        if (CompanyAdminService::instance()->checkUser()) {
            if ($productVariant->company_id !== CompanyAdminService::instance()->getUser()->company_id) {
                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('You do not have permission to access this product variant!')
                        ->getLang('you_do_not_have_permission_to_access_this_product_variant')
                );
            }
        }
    }

    public function show(ProductVariant $productVariant): ProductVariant
    {
        $this->checkCompanyForSellers($productVariant);

        $productVariant->load([
            'media',
            'sets',
            'sets.product',
            'sets.product.translatable',
            'sets.product.media',
            'options',
            'options.option',
            'options.option.translatable',
            'options.option.items',
            'options.option.items.translatable',
            'groups',
            'groups.translatable',

            'product',
            'product.translatable',
            'product.media',
        ]);

        return $productVariant;
    }

    public function loadRelations(ProductVariant $productVariant): ProductVariant
    {
        $productVariant = ProductService::instance()->setProductVariant($productVariant)->loadVariantRelationsForSite();

        return $productVariant
            ->load([
                'creatable',
                'groups',
                'groups.translatable',
                'product.creatable',
                'product.categories',
                'product.categories.translatable',
                'product.mainCategory.translatable',
                'product.mainCategory.parent.translatable',
                'sets' => function ($query) {
                    $query->active();
                }
            ])
            ->loadCount([
                'sets',
            ]);
    }

    public function store(Product $product, FormRequest $request, bool $isDefault = false, bool $withoutImages = false): ProductVariant
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $product, $request, $isDefault, $withoutImages) {
            $validated = $request->validated();

            $sets = $validated['sets'] ?? [];
            $options = $validated['options'] ?? [];

            $data = [];
            $data['uuid'] = Str::uuid();
            $data['is_default'] = $isDefault;
            $data['stock'] = $validated['stock'] ?? 0;
            $data['price'] = $validated['price'] ?? 0;
            $data['has_discount'] = $validated['has_discount'] ?? false;
            $data['discount'] = $validated['discount'] ?? null;
            $data['discount_type'] = $validated['discount_type'] ?? null;
            $data['discount_start_at'] = $validated['discount_start_at'] ?? null;
            $data['discount_end_at'] = $validated['discount_end_at'] ?? null;
            $data['is_set'] = (bool)($validated['is_set'] ?? false);
            $data['city_id'] = $validated['city_id'];
            $data['brand_model_id'] = $validated['brand_model_id'];
            $data['year'] = $validated['year'];

            if (CompanyAdminService::instance()->checkUser()) {
                $data['company_id'] = CompanyAdminService::instance()->getUser()->company_id;
            }

            /** @var ProductVariant $productVariant */
            $productVariant = $product->variants()->create($data);

            $_this->syncSets($productVariant, $sets);

            $_this->setOptions($productVariant, $options);

            if (!$withoutImages) {
                FileUpload::upload($request, 'main_image', 'product_variants_main', $productVariant);
                FileUpload::upload($request, 'hover_image', 'product_variants_hover', $productVariant);
                FileUpload::multipleUpload($request, 'images', 'product_variants_images', $productVariant);
            }

            return $productVariant;
        });
    }

    public function update(ProductVariant $productVariant, FormRequest $request, bool $withoutImages = false): ProductVariant
    {
        $this->checkCompanyForSellers($productVariant);

        $_this = $this;

        return DB::transaction(static function () use ($_this, $productVariant, $request, $withoutImages) {
            $validated = $request->validated();

            $sets = $validated['sets'] ?? [];
            unset($validated['sets']);

            $options = $validated['options'] ?? [];
            unset($validated['options']);

            $validated['discount'] = $validated['discount'] ?? null;
            $validated['discount_type'] = $validated['discount_type'] ?? null;
            $validated['discount_start_at'] = $validated['discount_start_at'] ?? null;
            $validated['discount_end_at'] = $validated['discount_end_at'] ?? null;

            $productVariant->update($validated);

            $_this->syncSets($productVariant, $sets, true);

            $_this->setOptions($productVariant, $options);

            if (!$withoutImages) {
                FileUpload::upload($request, 'main_image', 'product_variants_main', $productVariant);
                FileUpload::upload($request, 'hover_image', 'product_variants_hover', $productVariant);
                FileUpload::multipleUpload($request, 'images', 'product_variants_images', $productVariant);
            }

            return $productVariant;
        });
    }

    public function syncSets(ProductVariant $productVariant, array $sets, bool $detach = false): void
    {
        $this->checkCompanyForSellers($productVariant);

        if ($productVariant->isSet()) {
            if (!$productVariant->isDefault()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('A product set can only be created as the default variant!')
                        ->getLang('product_set_only_as_default_variant')
                );
            }

            if ($detach) {
                $productVariant->sets()->detach();
            }

            $productVariant->sets()->sync($sets);
        }
    }

    public function setOptions(ProductVariant $productVariant, array $options, $guest = false): void
    {
        $this->checkCompanyForSellers($productVariant);

        if (empty($options)) {
            return;
        }

        if ($guest) {
            $deletableId = null;
            $deletableType = null;
        } else {
            $deletableId = CompanyAdminService::instance()->checkUser()
                ? CompanyAdminService::instance()->getUser()->id
                : (AdminService::instance()->checkUser() ? AdminService::instance()->getUser()->id : null);

            $deletableType = CompanyAdminService::instance()->checkUser()
                ? CompanyAdminService::instance()->getUser()->getMorphClass()
                : (AdminService::instance()->checkUser() ? AdminService::instance()->getUser()->getMorphClass() : null);
        }

        $productVariant->options()
            ->update([
                'deletable_id' => $deletableId,
                'deletable_type' => $deletableType,
                'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                'deleted_at' => Carbon::now(),
            ]);

        $productOptions = ProductOption::query()
            ->whereIn('id', data_get($options, '*.option_id', []))
            ->with([
                'items' => function ($query) {
                    $query->active();
                    $query->with([
                        'translatable',
                    ]);
                },
            ])
            ->active()
            ->get();

        $productVariantSlugs = [];

        foreach ($options as $option) {
            /* @var ProductOption $productOption */
            $productOption = $productOptions->firstWhere('id', $option['option_id']);

            if (!$productOption) {
                continue;
            }

            $data = [
                'product_variant_id' => $productVariant->id,
                'product_option_id' => $option['option_id'],
            ];

            if ($productOption->hasItems()) {
                $data['product_option_item_id'] = (int)$option['value'];

                $productOptionSelectedItem = $productOption->items->firstWhere('id', $data['product_option_item_id']);

                if (!$productOptionSelectedItem) {
                    continue;
                }

                $data['slug'] = $productOptionSelectedItem->slug;
            } else if (!$productOption->isTranslatable()) {
                $data['value'] = $option['value'];

                $data['slug'] = ProductVariantProductOption::instanceSlug()
                    ->setText($data['value'])
                    ->make()
                    ->getRealSlug();
            }

            if ($productOption->isInTitle()) {
                $productVariantSlugs[] = $data['slug'] ?? null;
            }

            if ($productOption->isTranslatable() && !empty($option['translations']) && is_array($option['translations'])) {
                $data['translations'] = $option['translations'];

                ProductVariantProductOption::createWithLangs($data, null, false, 'translatable_value');
            } else {
                ProductVariantProductOption::query()->create($data);
            }
        }

        if (!empty($productVariantSlugs)) {
            $this->saveSlug($productVariant, implode('-', $productVariantSlugs));
        }
    }

    private function saveSlug(ProductVariant $productVariant, string $slug): void
    {
        $query = $productVariant->slug ? $productVariant : ProductVariant::instanceSlug();

        $productVariant->slug = $query
            ->setText($slug)
            ->setModelNameSuffix('_product_' . $productVariant->product_id)
            ->make()
            ->getRealSlug();

        $productVariant->save();
    }

    public function destroy(ProductVariant $productVariant): void
    {
        $this->checkCompanyForSellers($productVariant);

        DB::transaction(static function () use ($productVariant) {
            $productVariant->delete();
        });
    }

    public function deleteHoverImage(ProductVariant $productVariant): void
    {
        $this->checkCompanyForSellers($productVariant);

        DB::transaction(static function () use ($productVariant) {
            $productVariant->clearMediaCollection('product_variants_hover');
        });
    }

    public function deleteImage(ProductVariant $productVariant, int $mediaId): void
    {
        $this->checkCompanyForSellers($productVariant);

        DB::transaction(static function () use ($productVariant, $mediaId) {
            $productVariant->deleteMedia($mediaId);
        });
    }

    public function changeActiveStatus(ProductVariant $productVariant): void
    {
        $this->checkCompanyForSellers($productVariant);

        DB::transaction(function () use ($productVariant) {
            $productVariant->is_active = !$productVariant->is_active;
            $productVariant->save();
        });
    }

    public function getDefaultVariantByProduct(Product $product): ProductVariant
    {
        (new ProductRepository())->checkCompanyForSellers($product);

        $productVariant = $product->variants()
            ->where('is_default', true)
            ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                // for sellers
                $builder->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
            })
            ->active()
            ->first();

        if (!$productVariant) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The product does not have a default variant!')
                    ->getLang('product_does_not_have_default_variant')
            );
        }

        return $productVariant;
    }

    public function showBySlug(string $productSlug, string $subCategorySlug, ?string $categorySlug = null, array $validated = []): ProductVariant
    {
        $productVariantSlug = $validated['variant'] ?? null;

        $productVariant = ProductVariant::query()
            ->active()
            ->where('slug', $productVariantSlug)
            ->whereHas('product', function (Builder $query) use ($productSlug, $subCategorySlug) {
                $query->active();
                $query->where('slug', $productSlug);
                $query->whereHas('mainCategory', function (Builder $q) use ($subCategorySlug) {
                    $q->where('slug', $subCategorySlug);
                });
            })
            ->first();

        if (!$productVariant) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product not found!')
                    ->getLang('site_product_not_found')
            );
        }

        $productVariant = ProductService::instance()->setProductVariant($productVariant)->loadVariantRelationsForSite();

        $productVariant->loadMissing([
            'product.mainCategory.translatable',
            'product.mainCategory.parent.translatable',
        ]);

        $product = $productVariant->product;

        if ($product->mainCategory?->parent) {
            if ($categorySlug == null) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Product parent category slug is required!')
                        ->getLang('site_product_parent_category_slug_is_required')
                );
            } elseif ($product->mainCategory?->parent?->slug !== $categorySlug) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Product parent category slug is not valid!')
                        ->getLang('site_product_parent_category_slug_is_not_valid')
                );
            }
        }

        $productVariant->loadMissing([
            'product.categories',
            'product.categories.translatable',
            'product.categories.parent',
            'product.categories.parent.translatable',
            'options',
            'options.option',
            'options.option.translatable',
            'options.item',
            'options.item.translatable',
        ]);

        $productVariant->ratings = ProductService::instance()->setProductVariant($productVariant)->calculateVariantRating();

        if ($productVariant->isSet()) {
            $relations = ProductService::instance()->getVariantRelationsForSite();

            $productVariant->loadMissing([
                'sets' => function ($query) use ($relations) {
                    $query->active();
                    $query->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']));
                    $query->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']));
                    $query->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']));
                }
            ]);
        }

        if (($validated['with_related'] ?? 'no') === 'yes') {
            $productVariant->related_products = $this->loadRelatedProducts($productVariant);
        }

        return $productVariant;
    }

    public function loadRelatedProducts(ProductVariant $productVariant): Collection
    {
        $productVariant->loadMissing([
            'product',
            'product.mainCategory',
        ]);

        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        return ProductVariant::query()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->where('id', '!=', $productVariant->id)
            ->active()
            ->whereHas('product', function ($query) {
                $query->active();
            })
            ->with([
                'sets' => function ($query) {
                    $query->active();
                }
            ])
            ->limit(12)
            ->inRandomOrder()
            ->get();
    }

    public function approvalStatusHistory(ProductVariant $productVariant, array $validated = []): LengthAwarePaginator
    {
        $this->checkCompanyForSellers($productVariant);

        return $productVariant->approvalStatuses()
            ->with([
                'creatable',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function approvalStatusApprove(ProductVariant $productVariant): void
    {
        if ($productVariant->approval_status === ProductVariantApprovalStatusEnum::APPROVED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The product variant is already approved!')
                    ->getLang('product_variant_already_approved')
            );
        }

        DB::transaction(function () use ($productVariant) {
            $productVariant->approval_status = ProductVariantApprovalStatusEnum::APPROVED->value;
            $productVariant->approval_status_message = null;
            $productVariant->save();

            $productVariant->approvalStatuses()->create([
                'approval_status' => ProductVariantApprovalStatusEnum::APPROVED->value,
                'approval_status_message' => null,
            ]);
        });
    }

    public function approvalStatusReject(ProductVariant $productVariant, array $validated = []): void
    {
        if ($productVariant->approval_status === ProductVariantApprovalStatusEnum::REJECTED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The product variant is already rejected!')
                    ->getLang('product_variant_already_rejected')
            );
        }

        DB::transaction(function () use ($productVariant, $validated) {
            $productVariant->approval_status = ProductVariantApprovalStatusEnum::REJECTED->value;
            $productVariant->approval_status_message = $validated['approval_status_message'] ?? null;
            $productVariant->save();

            $productVariant->approvalStatuses()->create([
                'approval_status' => ProductVariantApprovalStatusEnum::REJECTED->value,
                'approval_status_message' => $validated['approval_status_message'] ?? null,
            ]);
        });
    }

    public function approvalStatusResend(ProductVariant $productVariant): void
    {
        $this->checkCompanyForSellers($productVariant);

        if ($productVariant->approval_status !== ProductVariantApprovalStatusEnum::REJECTED->value) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Only rejected product variants can be resent for approval!')
                    ->getLang('only_rejected_product_variants_can_be_resent_for_approval')
            );
        }

        DB::transaction(function () use ($productVariant) {
            $productVariant->approval_status = ProductVariantApprovalStatusEnum::PENDING->value;
            $productVariant->approval_status_message = null;
            $productVariant->save();

            $productVariant->approvalStatuses()->create([
                'approval_status' => ProductVariantApprovalStatusEnum::PENDING->value,
                'approval_status_message' => null,
            ]);
        });
    }
}
