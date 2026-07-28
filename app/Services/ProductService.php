<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSet;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProductService
{
    private static ?ProductService $instance = null;
    private Product $product;
    private ProductVariant $productVariant;

    private function __construct()
    {

    }

    public static function instance(): ProductService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getProduct(): Product
    {
        if (empty($this->product)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product not set!')
                    ->getLang('product_not_set')
            );
        }

        return $this->product;
    }

    public function setProductVariant(ProductVariant $productVariant): static
    {
        $this->productVariant = $productVariant;

        $this->productVariant->loadMissing([
            'product'
        ]);

        $this->setProduct($this->productVariant->product);

        return $this;
    }

    public function getProductVariant(): ProductVariant
    {
        if (empty($this->productVariant)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product variant not set!')
                    ->getLang('product_variant_not_set')
            );
        }

        return $this->productVariant;
    }

    public function getActiveProduct(): Product
    {
        if (!$this->getProduct()->isActive()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product is not active!')
                    ->getLang('product_not_active')
            );
        }

        return $this->getProduct();
    }

    public function getActiveProductVariant(): ProductVariant
    {
        $this->getActiveProduct();

        if (!$this->getProductVariant()->isActive()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product variant is not active!')
                    ->getLang('product_variant_not_active')
            );
        }

        return $this->getProductVariant();
    }

    public function getRelationsForSite(string $prefix = '', bool $withVariants = true): array
    {
        $arr = [];

        if ($withVariants) {
            $arr['with'] = [
                $prefix . 'variants' => function ($query) {
                    $query->active();
                    $query->with([
                        'media',
                    ]);
                },
                $prefix . 'translatable',
                $prefix . 'media',
                $prefix . 'mainCategory',
                $prefix . 'mainCategory.parent',
            ];
        } else {
            $arr['with'] = [
                $prefix . 'translatable',
                $prefix . 'media',
                $prefix . 'mainCategory',
                $prefix . 'mainCategory.parent',
            ];
        }

        $arr['exists'] = [];
        $arr['counts'] = [];

        return $arr;
    }

    public function getVariantRelationsForSite(string $prefix = '', bool $withProduct = false): array
    {
        $arr = [];

        $arr['with'] = [
            $prefix . 'sets' => function ($query) {
                $query->active();
            },
            $prefix . 'media',
            $prefix . 'product',
            $prefix . 'product.translatable',
            $prefix . 'options',
            $prefix . 'options.option',
            $prefix . 'options.option.translatable',
            $prefix . 'options.item',
            $prefix . 'options.item.translatable',
        ];

        if ($withProduct) {
            $arr['with'] = array_merge(
                $arr['with'],
                $this->getRelationsForSite('product.', false)['with'] ?? []
            );
        }

        $arr['exists'] = [];

        $arr['counts'] = [];

        return $arr;
    }

    public function loadRelationsForSite(string $prefix = ''): Product
    {
        $relations = $this->getRelationsForSite($prefix);
        $product = $this->getProduct();

        if (count($relations['with']) > 0) {
            $product->load($relations['with']);
        }

        if (!empty($prefix)) {
            $relationName = rtrim($prefix, '.');

            if (count($relations['exists']) > 0) {
                $product->load($relationName);
                if ($product->relationLoaded($relationName)) {
                    $product->getRelation($relationName)->each(function ($relatedModel) use ($relations) {
                        $relatedModel->loadExists($relations['exists']);
                    });
                }
            }

            if (count($relations['counts']) > 0) {
                $product->load($relationName);
                if ($product->relationLoaded($relationName)) {
                    $product->getRelation($relationName)->each(function ($relatedModel) use ($relations) {
                        $relatedModel->loadCount($relations['counts']);
                    });
                }
            }
        } else {
            if (count($relations['exists']) > 0) {
                $product->loadExists($relations['exists']);
            }

            if (count($relations['counts']) > 0) {
                $product->loadCount($relations['counts']);
            }
        }

        return $product;
    }

    public function loadVariantRelationsForSite(string $prefix = ''): ProductVariant
    {
        $relations = $this->getVariantRelationsForSite($prefix, true);
        $productVariant = $this->getProductVariant();

        if (count($relations['with']) > 0) {
            $productVariant->loadMissing($relations['with']);
        }

        if (!empty($prefix)) {
            $relationName = rtrim($prefix, '.');

            if (count($relations['exists']) > 0) {
                $productVariant->loadMissing($relationName);
                if ($productVariant->relationLoaded($relationName)) {
                    $productVariant->getRelation($relationName)->each(function ($relatedModel) use ($relations) {
                        $relatedModel->loadExists($relations['exists']);
                    });
                }
            }

            if (count($relations['counts']) > 0) {
                $productVariant->loadMissing($relationName);
                if ($productVariant->relationLoaded($relationName)) {
                    $productVariant->getRelation($relationName)->each(function ($relatedModel) use ($relations) {
                        $relatedModel->loadCount($relations['counts']);
                    });
                }
            }
        } else {
            if (count($relations['exists']) > 0) {
                $productVariant->loadExists($relations['exists']);
            }

            if (count($relations['counts']) > 0) {
                $productVariant->loadCount($relations['counts']);
            }
        }

        return $productVariant;
    }

    public function checkSetItem(ProductVariant $productVariant, ProductVariant $setItem): void
    {
        if (!$productVariant->isActive() || !$productVariant->isSet() || !$setItem->isActive() || ProductVariantSet::class::query()->where('set_id', $productVariant->id)->where('product_variant_id', $setItem->id)->doesntExist()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid set item!')
                    ->getLang('invalid_set_item')
            );
        }
    }

    public function calculateVariantRating(): array
    {
        $productVariant = $this->getActiveProductVariant();

        $avg = $productVariant->reviews()->approved()->avg('rating');
        $counts = $productVariant->reviews()->approved()->count();

        $list = $productVariant->reviews()
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get();

        for ($i = 1; $i <= 5; $i++) {
            if (!$list->firstWhere('rating', $i)) {
                $list->push((object)['rating' => $i, 'count' => 0]);
            }
        }

        return [
            'average' => $avg ? round($avg, 1) : 0,
            'count' => $counts,
            'list' => $list->sortByDesc('rating')->toArray(),
        ];
    }

    public function calculateRating(): array
    {
        $product = $this->getActiveProduct();

        $avg = $product->reviews()->approved()->avg('rating');
        $counts = $product->reviews()->approved()->count();

        $list = $product->reviews()
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get();

        for ($i = 1; $i <= 5; $i++) {
            if (!$list->firstWhere('rating', $i)) {
                $list->push((object)['rating' => $i, 'count' => 0]);
            }
        }

        return [
            'average' => $avg ? round($avg, 1) : 0,
            'count' => $counts,
            'list' => $list->sortByDesc('rating')->toArray(),
        ];
    }
}
