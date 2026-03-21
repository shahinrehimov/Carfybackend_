<?php

namespace App\Repositories;

use App\Enum\ProductSearchStatusEnum;
use App\Enum\ProductVariantApprovalStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Basket;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\CompanyProductVariant;
use App\Models\ProductVariantRevision;
use App\Models\SellerSearchToken;
use App\Models\Wishlist;
use App\Models\ProductVariantProductOption;
use App\Services\AdminService;
use App\Services\CompanyAdminService;
use App\Services\FileUpload;
use App\Services\LangService;
use App\Services\ProductService;
use App\Services\RouteService;
use App\Services\UserService;
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
                    // for sellers — filter by companyListings
                    $query->whereHas('companyListings', function (Builder $q) {
                        $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                    });
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

        $result = $query
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->when($isAdmin, function ($builder) use ($validated) {
                // load for a control panel
                if (CompanyAdminService::instance()->checkUser()) {
                    // for sellers — filter by companyListings
                    $builder->whereHas('companyListings', function (Builder $q) {
                        $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                    });
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
                        'city',
                        'city.translatable',
                        'brandModel',
                        'brandModel.translatable',
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
                $builder->withCount([
                    'companyListings' => function (Builder $q) {
                        $q->where('is_active', true)
                            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value);
                    },
                ]);
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
            ->when($validated['oem_code'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('oem_code', $validated['oem_code']);
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

        if (!$isAdmin) {
            $this->attachSellerDataToVariants($result);
        }

        return $result;
    }

    public function list(array $validated): LengthAwarePaginator
    {
        if ($validated['set_id'] ?? null) {
            $set = ProductVariant::query()
                ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                    // for sellers — filter by companyListings
                    $builder->whereHas('companyListings', function (Builder $q) {
                        $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                    });
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
                // for sellers — filter by companyListings
                $builder->whereHas('companyListings', function (Builder $q) {
                    $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                });
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

    /**
     * Read access: allows the variant's creator OR any seller who has attached to it.
     */
    public function checkCompanyForSellers(ProductVariant $productVariant): void
    {
        if (CompanyAdminService::instance()->checkUser()) {
            $companyId = CompanyAdminService::instance()->getUser()->company_id;

            $hasListing = $productVariant->companyListings()
                ->where('company_id', $companyId)
                ->exists();

            if (!$hasListing && $productVariant->company_id !== $companyId) {
                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('You do not have permission to access this product variant!')
                        ->getLang('you_do_not_have_permission_to_access_this_product_variant')
                );
            }
        }
    }

    /**
     * Write access: only the seller who originally created (owns) the variant.
     */
    public function checkCompanyOwnership(ProductVariant $productVariant): void
    {
        if (CompanyAdminService::instance()->checkUser()) {
            $companyId = CompanyAdminService::instance()->getUser()->company_id;

            if ($productVariant->company_id !== $companyId) {
                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('You do not have permission to modify this product variant!')
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
            'city',
            'city.translatable',
            'brandModel',
            'brandModel.translatable',

            'product',
            'product.translatable',
            'product.media',
            'product.brand',
            'product.brand.translatable',
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
                'city',
                'city.translatable',
                'brandModel',
                'brandModel.translatable',
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
        if (CompanyAdminService::instance()->checkUser()) {
            $token = request()->header('X-Search-Token');
            if (!$token) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Search token is required. Please search the catalog first.')
                        ->getLang('invalid_search_token')
                );
            }
            $this->consumeSearchToken($token);
        }

        $_this = $this;

        return DB::transaction(static function () use ($_this, $product, $request, $isDefault, $withoutImages) {
            $validated = $request->validated();

            $sets = $validated['sets'] ?? [];
            $options = $validated['options'] ?? [];

            $data = [];
            $data['uuid'] = Str::uuid();
            $data['is_default'] = $isDefault;
            $data['oem_code'] = $validated['oem_code'] ?? null;
            $data['is_set'] = (bool)($validated['is_set'] ?? false);
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

            // Auto-create CompanyProductVariant for the seller
            if (CompanyAdminService::instance()->checkUser()) {
                $cpv = CompanyProductVariant::query()->create([
                    'company_id' => CompanyAdminService::instance()->getUser()->company_id,
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
            }

            return $productVariant;
        });
    }

    public function update(ProductVariant $productVariant, FormRequest $request, bool $withoutImages = false): ProductVariant
    {
        $this->checkCompanyOwnership($productVariant);

        // Sellers submit a revision — changes are applied only after admin approval
        if (CompanyAdminService::instance()->checkUser()) {
            $this->createRevision($productVariant, $request, $withoutImages);
            return $productVariant;
        }

        $_this = $this;

        return DB::transaction(static function () use ($_this, $productVariant, $request, $withoutImages) {
            $validated = $request->validated();

            $sets = $validated['sets'] ?? [];
            unset($validated['sets']);

            $options = $validated['options'] ?? [];
            unset($validated['options']);

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

    private function createRevision(ProductVariant $productVariant, FormRequest $request, bool $withoutImages = false): ProductVariantRevision
    {
        $companyId = CompanyAdminService::instance()->getUser()->company_id;

        return DB::transaction(function () use ($productVariant, $request, $withoutImages, $companyId) {
            $validated = $request->validated();

            // Delete existing pending revision for this variant+company
            ProductVariantRevision::query()
                ->where('product_variant_id', $productVariant->id)
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->get()
                ->each(function (ProductVariantRevision $r) {
                    $r->clearMediaCollection('revision_main_image');
                    $r->clearMediaCollection('revision_hover_image');
                    $r->clearMediaCollection('revision_images');
                    $r->delete();
                });

            $revision = ProductVariantRevision::query()->create([
                'product_variant_id' => $productVariant->id,
                'company_id'         => $companyId,
                'status'             => 'pending',
                'data'               => [
                    'oem_code'       => $validated['oem_code'] ?? null,
                    'brand_model_id' => $validated['brand_model_id'] ?? null,
                    'year'           => $validated['year'] ?? null,
                    'options'        => $validated['options'] ?? [],
                ],
            ]);

            if (!$withoutImages) {
                FileUpload::upload($request, 'main_image', 'revision_main_image', $revision);
                FileUpload::upload($request, 'hover_image', 'revision_hover_image', $revision);
                FileUpload::multipleUpload($request, 'images', 'revision_images', $revision);
            }

            return $revision;
        });
    }

    public function approveRevision(ProductVariantRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $productVariant = $revision->productVariant;
            $data = $revision->data;

            // Apply scalar fields
            $productVariant->update([
                'oem_code'       => $data['oem_code'] ?? $productVariant->oem_code,
                'brand_model_id' => $data['brand_model_id'] ?? $productVariant->brand_model_id,
                'year'           => $data['year'] ?? $productVariant->year,
            ]);

            // Apply options
            if (!empty($data['options'])) {
                $this->setOptionsFromArray($productVariant, $data['options']);
            }

            // Move images from revision to product variant
            foreach ($revision->getMedia('revision_main_image') as $media) {
                $media->move($productVariant, 'product_variants_main');
            }
            foreach ($revision->getMedia('revision_hover_image') as $media) {
                $media->move($productVariant, 'product_variants_hover');
            }
            foreach ($revision->getMedia('revision_images') as $media) {
                $media->move($productVariant, 'product_variants_images');
            }

            $revision->update(['status' => 'approved']);
        });
    }

    public function rejectRevision(ProductVariantRevision $revision, ?string $message): void
    {
        $revision->update([
            'status'             => 'rejected',
            'rejection_message'  => $message,
        ]);
    }

    private function setOptionsFromArray(ProductVariant $productVariant, array $options): void
    {
        if (empty($options)) {
            return;
        }

        $productVariant->options()
            ->update([
                'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                'deleted_at' => Carbon::now(),
            ]);

        $productOptions = ProductOption::query()
            ->whereIn('id', data_get($options, '*.option_id', []))
            ->with([
                'items' => function ($query) {
                    $query->active()->with('translatable');
                },
            ])
            ->active()
            ->get();

        $productVariantSlugs = [];

        foreach ($options as $option) {
            $productOption = $productOptions->firstWhere('id', $option['option_id']);
            if (!$productOption) {
                continue;
            }

            $data = [
                'product_variant_id' => $productVariant->id,
                'product_option_id'  => $option['option_id'],
            ];

            if ($productOption->hasItems()) {
                $data['product_option_item_id'] = (int)$option['value'];

                $selectedItem = $productOption->items->firstWhere('id', $data['product_option_item_id']);
                if (!$selectedItem) {
                    continue;
                }

                $data['slug'] = $selectedItem->slug;

                if ($productOption->isInTitle()) {
                    $productVariantSlugs[] = $data['slug'];
                }

                ProductVariantProductOption::query()->create($data);
            } elseif ($productOption->isTranslatable() && !empty($option['translations']) && is_array($option['translations'])) {
                $data['translations'] = $option['translations'];
                ProductVariantProductOption::createWithLangs($data, null, false, 'translatable_value');
            } else {
                $data['value'] = $option['value'];
                $data['slug'] = ProductVariantProductOption::instanceSlug()
                    ->setText($data['value'])
                    ->make()
                    ->getRealSlug();

                if ($productOption->isInTitle()) {
                    $productVariantSlugs[] = $data['slug'];
                }

                ProductVariantProductOption::query()->create($data);
            }
        }

        if (!empty($productVariantSlugs)) {
            $this->saveSlug($productVariant, implode('-', $productVariantSlugs));
        }
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
        $this->checkCompanyOwnership($productVariant);

        DB::transaction(static function () use ($productVariant) {
            $productVariant->delete();
        });
    }

    public function deleteHoverImage(ProductVariant $productVariant): void
    {
        $this->checkCompanyOwnership($productVariant);

        DB::transaction(static function () use ($productVariant) {
            $productVariant->clearMediaCollection('product_variants_hover');
        });
    }

    public function deleteImage(ProductVariant $productVariant, int $mediaId): void
    {
        $this->checkCompanyOwnership($productVariant);

        DB::transaction(static function () use ($productVariant, $mediaId) {
            $productVariant->deleteMedia($mediaId);
        });
    }

    public function changeActiveStatus(ProductVariant $productVariant): void
    {
        $this->checkCompanyOwnership($productVariant);

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
                // for sellers — filter by companyListings
                $builder->whereHas('companyListings', function (Builder $q) {
                    $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                });
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
        $sellerUuid = $validated['seller'] ?? null;

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
            'brandModel',
            'brandModel.translatable',
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

        // Load seller-specific data
        $sellerListing = $this->resolveSellerListing($productVariant, $sellerUuid);

        if ($sellerListing) {
            $this->overrideWithSellerData($productVariant, $sellerListing);
        }

        // CPV-level in_wishlist / in_basket for detail page
        if ($sellerListing && RouteService::instance()->getName() === 'app') {
            $user = UserService::instance()->getUserOrGuest();

            $productVariant->in_wishlist = Wishlist::query()
                ->where('userable_id', $user->id)
                ->where('userable_type', $user::class)
                ->whereNull('deleted_at')
                ->where('company_product_variant_id', $sellerListing->id)
                ->exists();

            $productVariant->in_basket = Basket::query()
                ->where('userable_id', $user->id)
                ->where('userable_type', $user::class)
                ->whereNull('deleted_at')
                ->where('company_product_variant_id', $sellerListing->id)
                ->exists();
        }

        // Load other sellers count
        $productVariant->sellers_count = $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->count();

        // Load other sellers if requested
        if (($validated['other_sellers'] ?? 'no') === 'yes' && $sellerListing) {
            $otherSellersQuery = $productVariant->companyListings()
                ->where('id', '!=', $sellerListing->id)
                ->where('is_active', true)
                ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
                ->with(['city', 'city.translatable', 'media'])
                ->orderBy('price');

            $otherSellersTotal = $otherSellersQuery->count();
            $otherSellers = $otherSellersQuery->limit(50)->get();

            $productVariant->other_sellers = $otherSellers;
            $productVariant->other_sellers_total = $otherSellersTotal;
        }

        return $productVariant;
    }

    private function resolveSellerListing(ProductVariant $productVariant, ?string $sellerUuid): ?CompanyProductVariant
    {
        $query = $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media']);

        if ($sellerUuid) {
            $listing = $query->where('uuid', $sellerUuid)->first();
            if ($listing) {
                return $listing;
            }
        }

        // Fallback: cheapest active seller
        return $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media'])
            ->orderBy('price')
            ->first();
    }

    public function loadRelatedProducts(ProductVariant $productVariant): Collection
    {
        $productVariant->loadMissing([
            'product',
            'product.mainCategory',
        ]);

        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        $relatedVariants = ProductVariant::query()
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

        // Batch load cheapest CPV for related products
        if ($relatedVariants->isNotEmpty()) {
            $variantIds = $relatedVariants->pluck('id')->unique()->values();

            $cpvs = CompanyProductVariant::query()
                ->whereIn('product_variant_id', $variantIds)
                ->where('is_active', true)
                ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
                ->with(['city', 'city.translatable', 'media'])
                ->orderBy('price')
                ->get()
                ->groupBy('product_variant_id')
                ->map->first();

            foreach ($relatedVariants as $variant) {
                $cpv = $cpvs->get($variant->id);
                if ($cpv) {
                    $this->overrideWithSellerData($variant, $cpv);
                }
            }
        }

        return $relatedVariants;
    }

    public function otherSellers(ProductVariant $productVariant, array $validated = []): LengthAwarePaginator
    {
        return $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media'])
            ->orderBy('price')
            ->paginate($validated['limit'] ?? 20);
    }

    public function generateSearchToken(): string
    {
        $user = CompanyAdminService::instance()->getUser();

        $token = SellerSearchToken::query()->create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'admin_id'   => $user->id,
            'created_at' => now(),
        ]);

        return $token->uuid;
    }

    public function consumeSearchToken(string $token): void
    {
        $user = CompanyAdminService::instance()->getUser();

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
    }

    public function searchCatalog(array $validated): LengthAwarePaginator
    {
        $companyId = CompanyAdminService::instance()->checkUser()
            ? CompanyAdminService::instance()->getUser()->company_id
            : null;

        return ProductVariant::query()
            ->active()
            ->whereHas('product', function ($q) {
                $q->active();
            })
            ->with([
                'media',
                'product',
                'product.translatable',
                'product.media',
                'options',
                'options.option',
                'options.option.translatable',
                'options.item',
                'options.item.translatable',
                'brandModel',
                'brandModel.translatable',
            ])
            ->when($companyId, function (Builder $builder) use ($companyId) {
                $builder->with([
                    'companyListings' => function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    },
                ]);
            })
            ->when($validated['oem_code'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('oem_code', 'ILIKE', '%' . $validated['oem_code'] . '%');
            })
            ->when($validated['brand_model_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('brand_model_id', $validated['brand_model_id']);
            })
            ->when($validated['year'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('year', $validated['year']);
            })
            ->when($validated['product_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('product_id', $validated['product_id']);
            })
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $words = preg_split('/\s+/', trim($validated['search']), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $builder->where(function (Builder $b) use ($word) {
                        // OEM code
                        $b->where('oem_code', 'ILIKE', '%' . $word . '%');

                        // Product title
                        $b->orWhereHas('product.translatable', function (Builder $q) use ($word) {
                            $q->where('column', 'title')
                              ->where('text', 'ILIKE', '%' . $word . '%');
                        });

                        // Option item title (has_items=true, is_in_title=true)
                        $b->orWhereHas('options', function (Builder $q) use ($word) {
                            $q->whereHas('option', fn($q2) => $q2->where('is_in_title', true)->where('has_items', true))
                              ->whereHas('item.translatable', function (Builder $q3) use ($word) {
                                  $q3->where('column', 'title')
                                     ->where('text', 'ILIKE', '%' . $word . '%');
                              });
                        });

                        // Option translatable value (is_translatable=true, is_in_title=true)
                        $b->orWhereHas('options', function (Builder $q) use ($word) {
                            $q->whereHas('option', fn($q2) => $q2->where('is_in_title', true)->where('is_translatable', true))
                              ->whereHas('translatable', function (Builder $q3) use ($word) {
                                  $q3->where('column', 'translatable_value')
                                     ->where('text', 'ILIKE', '%' . $word . '%');
                              });
                        });

                        // Option raw value (not items, not translatable, is_in_title=true)
                        $b->orWhereHas('options', function (Builder $q) use ($word) {
                            $q->whereHas('option', fn($q2) => $q2->where('is_in_title', true)
                                  ->where('has_items', false)
                                  ->where('is_translatable', false))
                              ->where('value', 'ILIKE', '%' . $word . '%');
                        });
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    /**
     * Attach cheapest seller CPV data to a paginated list of product variants.
     * Overrides price/stock/discount attrs and sets in_wishlist/in_basket at CPV level.
     */
    public function attachSellerDataToVariants(LengthAwarePaginator $paginator): void
    {
        $variants = collect($paginator->items());

        if ($variants->isEmpty()) {
            return;
        }

        $variantIds = $variants->pluck('id')->unique()->values();

        // Batch load cheapest active approved CPV per variant (1 query)
        $cpvs = CompanyProductVariant::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media'])
            ->orderBy('price')
            ->get()
            ->groupBy('product_variant_id')
            ->map->first();

        // Override each variant's attrs with cheapest CPV data
        foreach ($variants as $variant) {
            /** @var CompanyProductVariant|null $cpv */
            $cpv = $cpvs->get($variant->id);

            if ($cpv) {
                $variant->seller_listing = $cpv;
                $variant->setRawAttributes(array_merge(
                    $variant->getAttributes(),
                    [
                        'stock' => $cpv->getRawOriginal('stock'),
                        'price' => $cpv->getRawOriginal('price'),
                        'discount_type' => $cpv->getRawOriginal('discount_type'),
                        'discount' => $cpv->getRawOriginal('discount'),
                        'discount_start_at' => $cpv->getRawOriginal('discount_start_at'),
                        'discount_end_at' => $cpv->getRawOriginal('discount_end_at'),
                    ]
                ), true);
            }
        }

        // CPV-level in_wishlist / in_basket check
        if (RouteService::instance()->getName() === 'app') {
            $user = UserService::instance()->getUserOrGuest();
            $cpvIds = $variants->map(fn($v) => $v->seller_listing?->id)->filter()->unique()->values();

            if ($cpvIds->isNotEmpty()) {
                $wishlistedCpvIds = Wishlist::query()
                    ->where('userable_id', $user->id)
                    ->where('userable_type', $user::class)
                    ->whereNull('deleted_at')
                    ->whereIn('company_product_variant_id', $cpvIds)
                    ->pluck('company_product_variant_id');

                $basketedCpvIds = Basket::query()
                    ->where('userable_id', $user->id)
                    ->where('userable_type', $user::class)
                    ->whereNull('deleted_at')
                    ->whereIn('company_product_variant_id', $cpvIds)
                    ->pluck('company_product_variant_id');

                foreach ($variants as $variant) {
                    $cpvId = $variant->seller_listing?->id;
                    $variant->in_wishlist = $cpvId ? $wishlistedCpvIds->contains($cpvId) : false;
                    $variant->in_basket = $cpvId ? $basketedCpvIds->contains($cpvId) : false;
                }
            }
        }
    }

    /**
     * Override a single ProductVariant's price/stock attrs with CPV seller data.
     */
    public function overrideWithSellerData(ProductVariant $productVariant, CompanyProductVariant $cpv): void
    {
        $productVariant->seller_listing = $cpv;
        $productVariant->setRawAttributes(array_merge(
            $productVariant->getAttributes(),
            [
                'stock' => $cpv->getRawOriginal('stock'),
                'price' => $cpv->getRawOriginal('price'),
                'discount_type' => $cpv->getRawOriginal('discount_type'),
                'discount' => $cpv->getRawOriginal('discount'),
                'discount_start_at' => $cpv->getRawOriginal('discount_start_at'),
                'discount_end_at' => $cpv->getRawOriginal('discount_end_at'),
            ]
        ), true);
    }
}
