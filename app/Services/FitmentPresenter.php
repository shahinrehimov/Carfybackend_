<?php

namespace App\Services;

use App\Http\Resources\Fitments\FitmentResource;
use App\Models\Brand;
use App\Models\BrandModel;
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
