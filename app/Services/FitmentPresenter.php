<?php

namespace App\Services;

use App\Http\Resources\Fitments\FitmentResource;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Turns fitment rules into the shapes the API hands out.
 *
 * Two sources need the same output: loaded relations, and the raw arrays stored
 * inside a pending revision's JSON payload — which hold bare ids and would
 * otherwise reach the admin panel unreadable.
 */
class FitmentPresenter
{
    private static ?FitmentPresenter $instance = null;

    public static function instance(): FitmentPresenter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Format a variant's persisted fitment rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forVariant(?ProductVariant $productVariant): array
    {
        if (! $productVariant?->relationLoaded('fitments')) {
            return [];
        }

        return FitmentResource::collection($productVariant->fitments)->resolve();
    }

    /**
     * Format raw fitment rows — brand/model ids and year bounds — by resolving the
     * referenced titles in one pass.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function forRawRows(array $rows): array
    {
        if (! $rows) {
            return [];
        }

        $brands = $this->titlesById(Brand::class, $this->pluckIds($rows, 'brand_id'));
        $models = $this->titlesById(BrandModel::class, $this->pluckIds($rows, 'brand_model_id'));

        return array_map(static function (array $row) use ($brands, $models) {
            $brandId = $row['brand_id'] ?? null;
            $modelId = $row['brand_model_id'] ?? null;

            return [
                'brand' => $brandId === null
                    ? null
                    : ['id' => $brandId, 'title' => $brands[$brandId] ?? null],
                'brand_model' => $modelId === null
                    ? null
                    : ['id' => $modelId, 'title' => $models[$modelId] ?? null],
                'year_from' => $row['year_from'] ?? null,
                'year_to' => $row['year_to'] ?? null,
            ];
        }, $rows);
    }

    /**
     * Distinct car brands a product fits, gathered from its variants' fitment
     * rules. A product can now span several brands, and a universal one names
     * none — so this is a list, not a single value.
     *
     * @return array<int, array<string, mixed>>
     */
    public function brandsForProduct(?Product $product): array
    {
        if (! $product?->relationLoaded('fitments')) {
            return [];
        }

        return $product->fitments
            ->pluck('brand')
            ->filter()
            ->unique('id')
            ->values()
            ->map(static fn (Brand $brand) => [
                'id' => $brand->id,
                'uuid' => $brand->uuid,
                'slug' => $brand->slug,
                'title' => $brand->getLang('title'),
            ])
            ->all();
    }

    /**
     * Whether any of the product's variants fits every car. Lets a panel say
     * "all cars" instead of showing an empty brand list, which reads as missing
     * data rather than as a deliberate choice.
     */
    public function isUniversalProduct(?Product $product): bool
    {
        if (! $product?->relationLoaded('fitments')) {
            return false;
        }

        return $product->fitments->contains(fn ($fitment) => $fitment->brand_id === null);
    }

    /**
     * The product's brand for clients that still expect a single one.
     *
     * Only answered when the fitments agree on exactly one brand; a multi-brand
     * or universal product has no honest single answer, so it gets null rather
     * than an arbitrary pick.
     *
     * @return array<string, mixed>|null
     */
    public function singleBrandForProduct(?Product $product): ?array
    {
        $brands = $this->brandsForProduct($product);

        return count($brands) === 1 ? $brands[0] : null;
    }

    /**
     * The concrete models a fitment list names, for the deprecated brand_models
     * field. Universal and brand-wide rules name none.
     *
     * @return array<int, array<string, mixed>>
     */
    public function brandModelsFor(?ProductVariant $productVariant): array
    {
        if (! $productVariant?->relationLoaded('fitments')) {
            return [];
        }

        return $productVariant->fitments
            ->pluck('brandModel')
            ->filter()
            ->unique('id')
            ->values()
            ->map(static fn (BrandModel $model) => [
                'id' => $model->id,
                'title' => $model->getLang('title'),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function pluckIds(array $rows, string $key): array
    {
        return collect($rows)->pluck($key)->filter()->unique()->values()->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string|null>
     */
    private function titlesById(string $model, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return $model::query()
            ->whereKey($ids)
            ->with('translatable')
            ->get()
            ->mapWithKeys(static fn ($record) => [$record->id => $record->getLang('title')])
            ->all();
    }
}
