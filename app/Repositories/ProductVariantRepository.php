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
use App\Models\ProductReview;
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
use Illuminate\Support\Collection as SupportCollection;
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
        if ($isAdmin) {
            return $this->loadForAdmin($validated);
        }

        return $this->loadForSite($validated);
    }

    private function loadForAdmin(array $validated): LengthAwarePaginator
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
        $currentLangId    = LangService::instance()->getCurrentLangId();
        $relations        = ProductService::instance()->getVariantRelationsForSite('', true);

        return $query
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                $builder->whereHas('companyListings', function (Builder $q) {
                    $q->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
                });
            })
            ->when(AdminService::instance()->checkUser() && ($validated['company_id'] ?? null), function (Builder $builder) use ($validated) {
                $builder->where('company_id', $validated['company_id']);
            })
            ->with([
                'creatable',
                'groups',
                'groups.translatable',
                'city',
                'city.translatable',
                'brandModels',
                'brandModels.translatable',
                'product.creatable',
                'product.categories',
                'product.categories.translatable',
                'product.mainCategory.translatable',
                'product.mainCategory.parent.translatable',
                'sets' => fn($q) => $q->active(),
            ])
            ->withCount(['sets'])
            ->when($validated['product_id'] ?? null, fn(Builder $b) => $b->where('product_id', $validated['product_id']))
            ->when($validated['is_set'] ?? null, function (Builder $b) use ($validated) {
                if ($validated['is_set'] === 'yes') $b->isSet();
                elseif ($validated['is_set'] === 'no') $b->isNotSet();
            })
            ->when($validated['oem_code'] ?? null, fn(Builder $b) => $b->where('oem_code', $validated['oem_code']))
            ->when(count($brandsIds) > 0, fn(Builder $b) => $b->whereHas('product', fn(Builder $q) => $q->whereIn('products.brand_id', $brandsIds)))
            ->when(count($categoriesIds) > 0, fn(Builder $b) => $b->whereHas('product.categoriesRel', fn(Builder $q) => $q->whereIn('product_categories.category_id', $categoriesIds)))
            ->when(count($subCategoriesIds) > 0, fn(Builder $b) => $b->whereHas('product.categoriesRel', fn(Builder $q) => $q->whereIn('product_categories.category_id', $subCategoriesIds)))
            ->when($validated['group_id'] ?? null, fn(Builder $b) => $b->whereHas('groupsRel', fn(Builder $q) => $q->where('group_products.group_id', $validated['group_id'])))
            ->when($validated['search'] ?? null, function (Builder $b) use ($validated) {
                $b->whereHas('product.translatable', function (Builder $q) use ($validated) {
                    $q->where(fn($i) => $i->where('column', 'title')->where('text', 'ILIKE', '%' . $validated['search'] . '%'))
                      ->orWhere(fn($i) => $i->where('column', 'description_short')->where('text', 'ILIKE', '%' . $validated['search'] . '%'))
                      ->orWhere(fn($i) => $i->where('column', 'description')->where('text', 'ILIKE', '%' . $validated['search'] . '%'));
                });
            })
            ->when(isset($validated['min_price']) || isset($validated['max_price']), function (Builder $b) use ($validated, $priceCalculation) {
                if (isset($validated['min_price'])) $b->whereRaw("({$priceCalculation}) >= ?", [$validated['min_price']]);
                if (isset($validated['max_price'])) $b->whereRaw("({$priceCalculation}) <= ?", [$validated['max_price']]);
            })
            ->when(
                isset($validated['sort']) && isset($validated['sort_type']),
                function (Builder $b) use ($validated, $priceCalculation, $currentLangId) {
                    $sortType = strtolower($validated['sort_type'] ?? 'desc');
                    switch ($validated['sort']) {
                        case 'title':
                            $b->leftJoin('model_translations as mt_sort', function ($join) use ($currentLangId) {
                                $join->on('mt_sort.translatable_id', '=', 'products.id')
                                    ->where('mt_sort.translatable_type', '=', Product::class)
                                    ->where('mt_sort.column', '=', 'title')
                                    ->where('mt_sort.language_id', '=', $currentLangId)
                                    ->whereNull('mt_sort.deleted_at');
                            })->orderBy('mt_sort.text', $sortType);
                            break;
                        case 'price':
                            $b->orderByRaw("{$priceCalculation} {$sortType}");
                            break;
                        case 'date':
                            $b->orderBy('product_variants.id', $sortType);
                            break;
                        default:
                            $b->orderByDesc('product_variants.id');
                    }
                },
                fn(Builder $b) => $b->orderByDesc('product_variants.id')
            )
            ->paginate($validated['limit'] ?? 10);
    }

    private function loadForSite(array $validated): LengthAwarePaginator
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

        $currentLangId = LangService::instance()->getCurrentLangId();

        $result = CompanyProductVariant::query()
            ->where('company_product_variant.is_active', true)
            ->where('company_product_variant.approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->whereHas('productVariant', fn(Builder $q) => $q->active()->whereHas('product', fn(Builder $pq) => $pq->active()))
            ->with([
                'media',
                'city',
                'city.translatable',
                'company',
                'productVariant',
                'productVariant.media',
                'productVariant.product',
                'productVariant.product.translatable',
                'productVariant.product.media',
                'productVariant.product.mainCategory',
                'productVariant.product.mainCategory.translatable',
                'productVariant.product.mainCategory.parent',
                'productVariant.product.mainCategory.parent.translatable',
                'productVariant.product.brand',
                'productVariant.product.brand.translatable',
                'productVariant.options',
                'productVariant.options.option',
                'productVariant.options.option.translatable',
                'productVariant.options.item',
                'productVariant.options.item.translatable',
            ])
            ->when($validated['city_id'] ?? null, fn(Builder $b) => $b->where('company_product_variant.city_id', $validated['city_id']))
            ->when($validated['seller_store_code'] ?? null, fn(Builder $b) => $b->whereHas('company', fn(Builder $cq) => $cq->where('store_code', $validated['seller_store_code'])))
            ->when($validated['product_id'] ?? null, fn(Builder $b) => $b->whereHas('productVariant', fn(Builder $pq) => $pq->where('product_id', $validated['product_id'])))
            ->when($validated['is_set'] ?? null, function (Builder $b) use ($validated) {
                if ($validated['is_set'] === 'yes') $b->whereHas('productVariant', fn(Builder $q) => $q->isSet());
                elseif ($validated['is_set'] === 'no') $b->whereHas('productVariant', fn(Builder $q) => $q->isNotSet());
            })
            ->when($validated['oem_code'] ?? null, fn(Builder $b) => $b->whereHas('productVariant', fn(Builder $q) => $q->where('oem_code', $validated['oem_code'])))
            ->when(count($brandsIds) > 0, fn(Builder $b) => $b->whereHas('productVariant.product', fn(Builder $q) => $q->whereIn('brand_id', $brandsIds)))
            ->when(count($categoriesIds) > 0, fn(Builder $b) => $b->whereHas('productVariant.product.categoriesRel', fn(Builder $q) => $q->whereIn('product_categories.category_id', $categoriesIds)))
            ->when(count($subCategoriesIds) > 0, fn(Builder $b) => $b->whereHas('productVariant.product.categoriesRel', fn(Builder $q) => $q->whereIn('product_categories.category_id', $subCategoriesIds)))
            ->when($validated['group_id'] ?? null, fn(Builder $b) => $b->whereHas('productVariant.groupsRel', fn(Builder $q) => $q->where('group_products.group_id', $validated['group_id'])))
            ->when($validated['search'] ?? null, function (Builder $b) use ($validated) {
                $b->whereHas('productVariant.product.translatable', function (Builder $q) use ($validated) {
                    $q->where(fn($i) => $i->where('column', 'title')->where('text', 'ILIKE', '%' . $validated['search'] . '%'))
                      ->orWhere(fn($i) => $i->where('column', 'description_short')->where('text', 'ILIKE', '%' . $validated['search'] . '%'))
                      ->orWhere(fn($i) => $i->where('column', 'description')->where('text', 'ILIKE', '%' . $validated['search'] . '%'));
                });
            })
            ->when(isset($validated['min_price']), fn(Builder $b) => $b->where('company_product_variant.price', '>=', (int) ($validated['min_price'] * 100)))
            ->when(isset($validated['max_price']), fn(Builder $b) => $b->where('company_product_variant.price', '<=', (int) ($validated['max_price'] * 100)))
            ->when(!empty($validated['color_ids']), fn(Builder $b) => $b->whereHas('productVariant.options', fn(Builder $q) => $q
                ->whereHas('option', fn(Builder $oq) => $oq->where('name', 'color'))
                ->whereIn('product_option_item_id', $validated['color_ids'])
            ))
            ->when(!empty($validated['conditions']), fn(Builder $b) => $b->whereHas('productVariant.options', fn(Builder $q) => $q
                ->whereHas('option', fn(Builder $oq) => $oq->where('name', 'condition'))
                ->whereHas('item', fn(Builder $iq) => $iq->whereIn('slug', $validated['conditions']))
            ))
            ->when(isset($validated['min_year']), fn(Builder $b) => $b->whereHas('productVariant', fn(Builder $q) => $q->where('year', '>=', $validated['min_year'])))
            ->when(isset($validated['max_year']), fn(Builder $b) => $b->whereHas('productVariant', fn(Builder $q) => $q->where('year', '<=', $validated['max_year'])))
            ->when(!empty($validated['models']), fn(Builder $b) => $b->whereHas('productVariant.brandModels', fn(Builder $q) => $q->whereIn('brand_models.id', $validated['models'])))
            ->when(
                isset($validated['sort']) && isset($validated['sort_type']),
                function (Builder $b) use ($validated, $currentLangId) {
                    $sortType = strtolower($validated['sort_type'] ?? 'desc');
                    switch ($validated['sort']) {
                        case 'price':
                            $b->orderBy('company_product_variant.price', $sortType);
                            break;
                        case 'title':
                            $b->leftJoin('product_variants as pv_sort', 'pv_sort.id', '=', 'company_product_variant.product_variant_id')
                              ->leftJoin('products as p_sort', 'p_sort.id', '=', 'pv_sort.product_id')
                              ->leftJoin('model_translations as mt_sort', function ($join) use ($currentLangId) {
                                  $join->on('mt_sort.translatable_id', '=', 'p_sort.id')
                                       ->where('mt_sort.translatable_type', '=', Product::class)
                                       ->where('mt_sort.column', '=', 'title')
                                       ->where('mt_sort.language_id', '=', $currentLangId)
                                       ->whereNull('mt_sort.deleted_at');
                              })
                              ->orderBy('mt_sort.text', $sortType);
                            break;
                        case 'date':
                            $b->orderBy('company_product_variant.id', $sortType);
                            break;
                        default:
                            $b->orderByDesc('company_product_variant.id');
                    }
                },
                fn(Builder $b) => $b->orderByDesc('company_product_variant.id')
            )
            ->paginate($validated['limit'] ?? 10);

        $this->attachWishlistAndBasketToCpvs($result);
        $this->attachRatingsToCpvs($result);

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
            'brandModels',
            'brandModels.translatable',

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
                'brandModels',
                'brandModels.translatable',
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
            $data['year'] = $validated['year'];

            if (CompanyAdminService::instance()->checkUser()) {
                $data['company_id'] = CompanyAdminService::instance()->getUser()->company_id;
            }

            /** @var ProductVariant $productVariant */
            $productVariant = $product->variants()->create($data);

            $productVariant->brandModels()->sync($validated['brand_model_ids'] ?? []);

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

            $brandModelIds = $validated['brand_model_ids'] ?? null;
            unset($validated['brand_model_ids']);

            $productVariant->update($validated);

            if ($brandModelIds !== null) {
                $productVariant->brandModels()->sync($brandModelIds);
            }

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
                    'oem_code'        => $validated['oem_code'] ?? null,
                    'brand_model_ids' => $validated['brand_model_ids'] ?? [],
                    'year'            => $validated['year'] ?? null,
                    'options'         => $validated['options'] ?? [],
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
                'oem_code' => $data['oem_code'] ?? $productVariant->oem_code,
                'year'     => $data['year'] ?? $productVariant->year,
            ]);

            // Apply brand models
            if (!empty($data['brand_model_ids'])) {
                $productVariant->brandModels()->sync($data['brand_model_ids']);
            }

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
        $productVariantSlug  = $validated['variant'] ?? null;
        $sellerStoreCode     = $validated['seller'] ?? null;

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
            'brandModels',
            'brandModels.translatable',
        ]);

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
        $sellerListing = $this->resolveSellerListing($productVariant, $sellerStoreCode);

        if ($sellerListing) {
            $this->overrideWithSellerData($productVariant, $sellerListing);
            $productVariant->ratings = $this->calculateCpvRating($sellerListing);
        }

        // CPV-level in_wishlist / in_basket for detail page
        if ($sellerListing && in_array(RouteService::instance()->getName(), ['app', 'site'])) {
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
                ->with(['city', 'city.translatable', 'media', 'company'])
                ->orderBy('price');

            $otherSellersTotal = $otherSellersQuery->count();
            $otherSellers = $otherSellersQuery->limit(50)->get();

            $productVariant->other_sellers = $otherSellers;
            $productVariant->other_sellers_total = $otherSellersTotal;
        }

        return $productVariant;
    }

    private function resolveSellerListing(ProductVariant $productVariant, ?string $sellerStoreCode): ?CompanyProductVariant
    {
        $baseQuery = fn() => $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media', 'company']);

        if ($sellerStoreCode) {
            $listing = $baseQuery()
                ->whereHas('company', fn(Builder $q) => $q->where('store_code', $sellerStoreCode))
                ->first();

            if (!$listing) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                    LangService::instance()
                        ->setDefault('Seller listing not found!')
                        ->getLang('seller_listing_not_found')
                );
            }

            return $listing;
        }

        return $baseQuery()->orderBy('price')->first();
    }

    public function loadRelatedProducts(ProductVariant $productVariant): Collection
    {
        $relatedCpvs = CompanyProductVariant::query()
            ->where('company_product_variant.is_active', true)
            ->where('company_product_variant.approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->whereHas('productVariant', fn(Builder $q) => $q
                ->active()
                ->where('id', '!=', $productVariant->id)
                ->whereHas('product', fn(Builder $pq) => $pq->active())
            )
            ->with([
                'media',
                'city',
                'city.translatable',
                'company',
                'productVariant',
                'productVariant.media',
                'productVariant.product',
                'productVariant.product.translatable',
                'productVariant.product.media',
                'productVariant.product.mainCategory',
                'productVariant.product.mainCategory.translatable',
                'productVariant.product.mainCategory.parent',
                'productVariant.product.mainCategory.parent.translatable',
                'productVariant.options',
                'productVariant.options.option',
                'productVariant.options.option.translatable',
                'productVariant.options.item',
                'productVariant.options.item.translatable',
            ])
            ->limit(12)
            ->inRandomOrder()
            ->get();

        $this->attachWishlistAndBasketToCpvsCollection($relatedCpvs);

        return $relatedCpvs;
    }

    public function otherSellers(ProductVariant $productVariant, array $validated = []): LengthAwarePaginator
    {
        return $productVariant->companyListings()
            ->where('is_active', true)
            ->where('approval_status', ProductVariantApprovalStatusEnum::APPROVED->value)
            ->with(['city', 'city.translatable', 'media', 'company'])
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
                'brandModels',
                'brandModels.translatable',
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
                $builder->whereHas('brandModels', function (Builder $q) use ($validated) {
                    $q->where('brand_models.id', $validated['brand_model_id']);
                });
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

        // Batch load ratings (avg + count) for all CPVs in one query
        $cpvIds = $cpvs->pluck('id');
        $ratingsMap = ProductReview::query()
            ->approved()
            ->whereIn('company_product_variant_id', $cpvIds)
            ->selectRaw('company_product_variant_id, ROUND(AVG(rating)::numeric, 1) as average, COUNT(*) as count')
            ->groupBy('company_product_variant_id')
            ->get()
            ->keyBy('company_product_variant_id');

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

                $ratingRow = $ratingsMap->get($cpv->id);
                $variant->ratings = [
                    'average' => $ratingRow ? (float) $ratingRow->average : 0,
                    'count'   => $ratingRow ? (int) $ratingRow->count : 0,
                ];
            }
        }

        // CPV-level in_wishlist / in_basket check
        if (in_array(RouteService::instance()->getName(), ['app', 'site'])) {
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
     * Attach in_wishlist / in_basket flags to a Collection of CPV models.
     * Can be called from any context that builds a CPV list (listing, groups, related).
     */
    public function attachWishlistAndBasketToCpvsCollection(SupportCollection $cpvs): void
    {
        if (!in_array(RouteService::instance()->getName(), ['app', 'site'])) {
            return;
        }

        if ($cpvs->isEmpty()) {
            return;
        }

        $user   = UserService::instance()->getUserOrGuest();
        $cpvIds = $cpvs->pluck('id')->unique()->values();

        $wishlistedIds = Wishlist::query()
            ->where('userable_id', $user->id)
            ->where('userable_type', $user::class)
            ->whereNull('deleted_at')
            ->whereIn('company_product_variant_id', $cpvIds)
            ->pluck('company_product_variant_id');

        $basketedIds = Basket::query()
            ->where('userable_id', $user->id)
            ->where('userable_type', $user::class)
            ->whereNull('deleted_at')
            ->whereIn('company_product_variant_id', $cpvIds)
            ->pluck('company_product_variant_id');

        foreach ($cpvs as $cpv) {
            $cpv->in_wishlist = $wishlistedIds->contains($cpv->id);
            $cpv->in_basket   = $basketedIds->contains($cpv->id);
        }
    }

    private function attachWishlistAndBasketToCpvs(LengthAwarePaginator $paginator): void
    {
        $this->attachWishlistAndBasketToCpvsCollection(collect($paginator->items()));
    }

    private function attachRatingsToCpvs(LengthAwarePaginator $paginator): void
    {
        $cpvs = collect($paginator->items());

        if ($cpvs->isEmpty()) {
            return;
        }

        $cpvIds = $cpvs->pluck('id');

        $ratingsMap = ProductReview::query()
            ->approved()
            ->whereIn('company_product_variant_id', $cpvIds)
            ->selectRaw('company_product_variant_id, ROUND(AVG(rating)::numeric, 1) as average, COUNT(*) as count')
            ->groupBy('company_product_variant_id')
            ->get()
            ->keyBy('company_product_variant_id');

        foreach ($cpvs as $cpv) {
            $row = $ratingsMap->get($cpv->id);
            $cpv->ratings = [
                'average' => $row ? (float) $row->average : 0,
                'count'   => $row ? (int) $row->count : 0,
            ];
        }
    }

    private function calculateCpvRating(CompanyProductVariant $cpv): array
    {
        $avg    = $cpv->reviews()->approved()->avg('rating');
        $counts = $cpv->reviews()->approved()->count();

        $list = $cpv->reviews()
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get();

        for ($i = 1; $i <= 5; $i++) {
            if (!$list->firstWhere('rating', $i)) {
                $list->push((object) ['rating' => $i, 'count' => 0]);
            }
        }

        return [
            'average' => $avg ? round((float) $avg, 1) : 0,
            'count'   => $counts,
            'list'    => $list->sortByDesc('rating')->values()->toArray(),
        ];
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
                'uuid' => $cpv->getRawOriginal('uuid'),
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
