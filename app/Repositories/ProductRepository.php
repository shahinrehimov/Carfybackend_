<?php

namespace App\Repositories;

use App\Enum\ProductVariantApprovalStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\AdminService;
use App\Services\CompanyAdminService;
use App\Services\FileUpload;
use App\Services\LangService;
use App\Services\ProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProductRepository
{
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

        $currentLangId = LangService::instance()->getCurrentLangId();

        $relations = ProductService::instance()->getRelationsForSite('', false);

        return Product::query()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->when($isAdmin, function (Builder $builder) use ($validated) {
                // load for a control panel
                if (CompanyAdminService::instance()->checkUser()) {
                    // for sellers — own products + products where seller has attached CPV
                    $companyId = CompanyAdminService::instance()->getUser()->company_id;
                    $builder->where(function (Builder $q) use ($companyId) {
                        $q->where('company_id', $companyId)
                            ->orWhereHas('variants.companyListings', function (Builder $q2) use ($companyId) {
                                $q2->where('company_id', $companyId);
                            });
                    });
                } else if (AdminService::instance()->checkUser()) {
                    // for admins
                    $builder->withCount([
                        'variants as pending_variants_count' => function (Builder $query) {
                            $query->where('approval_status', ProductVariantApprovalStatusEnum::PENDING->value);
                        }
                    ]);

                    if ($validated['company_id'] ?? null) {
                        $builder->where('company_id', $validated['company_id']);
                    }
                }

                $builder
                    ->with([
                        'creatable',
                        'categories',
                        'categories.translatable',
                        'groups',
                        'groups.translatable',
                        'mainCategory.translatable',
                        'mainCategory.parent.translatable',
                    ])
                    ->withCount([
                        'variants',
                    ]);
            }, function ($builder) {
                // load for a site
                $builder->active();
            })
            ->when($validated['is_set'] ?? null, function ($builder) use ($validated) {
                if ($validated['is_set'] === 'yes') {
                    $builder->isSet();
                } elseif ($validated['is_set'] === 'no') {
                    $builder->isNotSet();
                }
            })
            ->when(count($brandsIds) > 0, function (Builder $builder) use ($brandsIds) {
                $builder->whereIn('products.brand_id', $brandsIds);
            })
            ->when(count($categoriesIds) > 0, function (Builder $builder) use ($categoriesIds) {
                $builder->whereHas('categoriesRel', function (Builder $query) use ($categoriesIds) {
                    $query->whereIn('product_categories.category_id', $categoriesIds);
                });
            })
            ->when(count($subCategoriesIds) > 0, function (Builder $builder) use ($subCategoriesIds) {
                $builder->whereHas('categoriesRel', function (Builder $query) use ($subCategoriesIds) {
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
                    $builder->whereHas('translatable', function (Builder $query) use ($validated) {
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
                isset($validated['sort']) && isset($validated['sort_type']),
                function (Builder $builder) use ($validated, $currentLangId) {
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

                        case 'date':
                            $builder->orderBy('products.id', $sortType);
                            break;

                        default:
                            $builder->orderByDesc('products.id');
                            break;
                    }
                },
                function (Builder $builder) {
                    $builder->orderByDesc('products.id');
                }
            )
            //->select('products.*')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(array $validated): LengthAwarePaginator
    {
        if ($validated['set_id'] ?? null) {
            $set = Product::query()
                ->where('id', $validated['set_id'])
                ->where('is_set', true)
                ->first();

            $query = $set->sets();
        } else {
            $query = Product::query();
        }

        return $query
            ->active()
            ->with([
                'translatable',
            ])
            ->when(CompanyAdminService::instance()->checkUser(), function (Builder $builder) {
                // for sellers
                $builder->where('company_id', CompanyAdminService::instance()->getUser()->company_id);
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
                    $builder->whereHas('translatable', function (Builder $query) use ($validated) {
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

    public function checkCompanyForSellers(Product $product): void
    {
        if (CompanyAdminService::instance()->checkUser()) {
            if ($product->company_id !== CompanyAdminService::instance()->getUser()->company_id) {
                throw new AccessDeniedHttpException(
                    LangService::instance()
                        ->setDefault('You do not have permission to access this product!')
                        ->getLang('you_do_not_have_permission_to_access_this_product')
                );
            }
        }
    }

    public function show(Product $product): Product
    {
        $this->checkCompanyForSellers($product);

        $product
            ->load([
                'media',
                'mainCategory',
                'mainCategory.translatable',
                'categories',
                'categories.translatable',
                'brand',
                'brand.translatable',
            ])
            ->loadCount([
                'variants',
            ]);

        if ($product->isSet()) {
            $product->load([
                'variants' => function ($query) {
                    $query->default();
                    $query->with([
                        'sets',
                        'sets.product',
                        'sets.product.translatable',
                        'sets.product.media',
                    ]);
                }
            ]);
        }

        return $product;
    }

    public function loadRelations(Product $product): Product
    {
        $product = ProductService::instance()->setProduct($product)->loadRelationsForSite();

        return $product
            ->load([
                'creatable',
                'categories',
                'categories.translatable',
                //'groups',
                //'groups.translatable',
                'mainCategory',
                'mainCategory.translatable',
            ])
            ->loadCount([
                'variants',
            ]);
    }

    public function store(FormRequest $request): Product
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $request) {
            $validated = $request->validated();

            $categories = $validated['categories'] ?? [];
            unset($validated['categories']);

            unset($validated['sets']);

            unset($validated['options']);

            $validated['uuid'] = Str::uuid();

            if (CompanyAdminService::instance()->checkUser()) {
                $validated['company_id'] = CompanyAdminService::instance()->getUser()->company_id;
            }

            $product = Product::createWithLangs($validated);

            $_this->syncCategories($product, $categories);

            $variantRepo = new ProductVariantRepository();
            $variantRepo->store($product, $request, true, true);

            FileUpload::upload($request, 'main_image', 'products_main', $product);
            FileUpload::upload($request, 'hover_image', 'products_hover', $product);
            FileUpload::multipleUpload($request, 'images', 'products_images', $product);

            return $product;
        });
    }

    public function update(Product $product, FormRequest $request): Product
    {
        $this->checkCompanyForSellers($product);

        $_this = $this;

        return DB::transaction(static function () use ($_this, $product, $request) {
            $validated = $request->validated();

            $categories = $validated['categories'] ?? [];
            unset($validated['categories']);

            $sets = $validated['sets'] ?? [];
            unset($validated['sets']);

            $product->updateWithLangs($validated);

            $_this->syncCategories($product, $categories);

            if ($product->isSet()) {
                $productVariantRepo = new ProductVariantRepository();

                $defaultProductVariant = $productVariantRepo->getDefaultVariantByProduct($product);
                $productVariantRepo->syncSets($defaultProductVariant, $sets, true);
            }

            FileUpload::upload($request, 'main_image', 'products_main', $product);
            FileUpload::upload($request, 'hover_image', 'products_hover', $product);
            FileUpload::multipleUpload($request, 'images', 'products_images', $product);

            return $product;
        });
    }

    public function destroy(Product $product): void
    {
        $this->checkCompanyForSellers($product);

        DB::transaction(static function () use ($product) {
            $product->delete();
        });
    }

    public function deleteHoverImage(Product $product): void
    {
        $this->checkCompanyForSellers($product);

        DB::transaction(static function () use ($product) {
            $product->clearMediaCollection('products_hover');
        });
    }

    public function deleteImage(Product $product, int $mediaId): void
    {
        $this->checkCompanyForSellers($product);

        DB::transaction(static function () use ($product, $mediaId) {
            $product->deleteMedia($mediaId);
        });
    }

    public function changeActiveStatus(Product $product): void
    {
        $this->checkCompanyForSellers($product);

        DB::transaction(function () use ($product) {
            $product->is_active = !$product->is_active;
            $product->save();
        });
    }

    public function syncCategories(Product $product, array $categories): void
    {
        $this->checkCompanyForSellers($product);

        if (!in_array($product->main_category_id, $categories)) {
            $categories[] = $product->main_category_id;
        }

        $product->categories()->sync($categories);
    }

    public function loadRelatedProducts(Product $product): Collection
    {
        $product->loadMissing('mainCategory');

        $relations = ProductService::instance()->getRelationsForSite();

        return $product->mainCategory
            ->products()
            ->active()
            ->where('products.id', '!=', $product->id)
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->limit(12)
            ->inRandomOrder()
            ->get();
    }
}
