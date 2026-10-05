<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\ProductVariant;
use App\Models\ProductVariantFitment;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The car a customer buys a part for, on listings whose seller stocks a separate
 * physical part per car but sells them as one product at one price and stock.
 *
 * The choices come from the variant's fitment rules, read the same way search
 * reads them: a universal rule allows any brand, a brand rule without a model
 * allows any model of that brand, and rules naming models limit that brand to
 * exactly those models. Year bounds play no part in the choice.
 *
 * Both brand and model must be chosen. The one exception is a brand with no
 * active model in the car library, where there is no model left to pick.
 */
class FitmentSelectionService
{
    private static ?FitmentSelectionService $instance = null;

    public static function instance(): FitmentSelectionService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Sets bundle several variants into one basket line, which has no room for
     * a per-item choice, so they never ask for one.
     */
    public function requires(ProductVariant $productVariant): bool
    {
        return (bool) $productVariant->requires_fitment_selection && ! $productVariant->isSet();
    }

    /**
     * What the selection modal offers, or null when the listing asks for nothing.
     *
     * any_brand means every active brand is allowed and the client lists them from
     * the car library; otherwise `brands` is the closed list. Per brand, any_model
     * means every model of it is allowed (listed from the car library), otherwise
     * `models` is the closed list. Either way a model must be chosen.
     */
    public function optionsFor(ProductVariant $productVariant): ?array
    {
        if (! $this->requires($productVariant)) {
            return null;
        }

        $fitments = $this->loadFitments($productVariant);

        if ($fitments->contains(fn (ProductVariantFitment $fitment) => $fitment->brand_id === null)) {
            return [
                'any_brand' => true,
                'brands' => [],
            ];
        }

        $brands = $fitments
            ->filter(fn (ProductVariantFitment $fitment) => $fitment->brand?->isActive())
            ->groupBy('brand_id')
            ->map(function (Collection $rows) {
                /** @var Brand $brand */
                $brand = $rows->first()->brand;
                $anyModel = $rows->contains(fn (ProductVariantFitment $fitment) => $fitment->brand_model_id === null);

                $models = $anyModel ? collect() : $rows
                    ->pluck('brandModel')
                    ->filter(fn (?BrandModel $model) => $model?->isActive())
                    ->unique('id')
                    ->sortBy(fn (BrandModel $model) => $model->getLang('title'))
                    ->values();

                return [
                    'id' => $brand->id,
                    'title' => $brand->getLang('title'),
                    'any_model' => $anyModel,
                    'models' => $models
                        ->map(fn (BrandModel $model) => [
                            'id' => $model->id,
                            'title' => $model->getLang('title'),
                        ])
                        ->all(),
                ];
            })
            // A brand whose every named model has since been disabled has
            // nothing left to pick.
            ->filter(fn (array $brand) => $brand['any_model'] || $brand['models'])
            ->sortBy('title')
            ->values()
            ->all();

        return [
            'any_brand' => false,
            'brands' => $brands,
        ];
    }

    /**
     * Check a customer's choice against the listing and return what to store.
     * Listings that ask for nothing store nothing, whatever was sent.
     *
     * @return array{0: int|null, 1: int|null} [brand id, brand model id]
     */
    public function resolve(ProductVariant $productVariant, ?int $brandId, ?int $brandModelId): array
    {
        if (! $this->requires($productVariant)) {
            return [null, null];
        }

        if ($brandId === null) {
            throw $this->error('Please choose the car brand this product is for.', 'fitment_selection_brand_required');
        }

        $brand = Brand::query()->active()->find($brandId);

        if (! $brand) {
            throw $this->error('The selected car brand is not available for this product.', 'fitment_selection_brand_invalid');
        }

        $model = null;

        if ($brandModelId !== null) {
            $model = BrandModel::query()->active()->where('brand_id', $brand->id)->find($brandModelId);

            if (! $model) {
                throw $this->error('The selected car model is not available for this product.', 'fitment_selection_model_invalid');
            }
        }

        $fitments = $this->loadFitments($productVariant);

        $universal = $fitments->contains(fn (ProductVariantFitment $fitment) => $fitment->brand_id === null);
        $brandRows = $fitments->where('brand_id', $brand->id);

        if (! $universal && $brandRows->isEmpty()) {
            throw $this->error('The selected car brand is not available for this product.', 'fitment_selection_brand_invalid');
        }

        $anyModel = $universal
            || $brandRows->contains(fn (ProductVariantFitment $fitment) => $fitment->brand_model_id === null);

        if ($model === null) {
            // Nothing to choose from: the brand has no active model at all.
            if ($anyModel && ! BrandModel::query()->active()->where('brand_id', $brand->id)->exists()) {
                return [$brand->id, null];
            }

            throw $this->error('Please choose the car model this product is for.', 'fitment_selection_model_required');
        }

        if (! $anyModel && ! $brandRows->contains('brand_model_id', $model->id)) {
            throw $this->error('The selected car model is not available for this product.', 'fitment_selection_model_invalid');
        }

        return [$brand->id, $model->id];
    }

    /**
     * The stored choice as clients render it, or null when there was none.
     */
    public function present(?Brand $brand, ?BrandModel $brandModel): ?array
    {
        if (! $brand) {
            return null;
        }

        return [
            'brand' => [
                'id' => $brand->id,
                'title' => $brand->getLang('title'),
            ],
            'brand_model' => $brandModel ? [
                'id' => $brandModel->id,
                'title' => $brandModel->getLang('title'),
            ] : null,
        ];
    }

    private function loadFitments(ProductVariant $productVariant): Collection
    {
        $productVariant->loadMissing([
            'fitments.brand.translatable',
            'fitments.brandModel.translatable',
        ]);

        return $productVariant->fitments;
    }

    private function error(string $default, string $key): BadRequestHttpException
    {
        return new BadRequestHttpException(
            LangService::instance()->setDefault($default)->getLang($key)
        );
    }
}
