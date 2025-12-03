<?php

namespace App\Casts\ProductVariants;

use App\Enum\DiscountTypeEnum;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PriceFinalCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        $now = CarbonImmutable::now();

        if ($model->isSet()) {
            $model->loadMissing([
                'sets' => function ($query) {
                    $query->active();
                },
            ]);

            $totalFinalPrice = 0;
            foreach ($model->sets as $set) {
                /** @var ProductVariant $set */

                $price = $set->getRawOriginal('price', 0) * $set->pivot->quantity;

                if (
                    empty($set->discount) || $set->discount === 0 ||
                    empty($set->discount_type) ||
                    ($set->discount_start_at && $now->lt($set->discount_start_at)) ||
                    ($set->discount_end_at && $now->gt($set->discount_end_at))
                ) {
                    $totalFinalPrice += $price;

                    continue;
                }

                $discount = $set->getRawOriginal('discount');

                if ($set->discount_type === DiscountTypeEnum::BY_PERCENTAGE->value) {
                    $finalPrice = $price - (($price * $discount) / 100);
                } else if ($set->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
                    $finalPrice = $price - $discount;
                } else {
                    $finalPrice = $set->price;
                }

                $totalFinalPrice += $finalPrice;
            }

            if (
                empty($model->discount) || $model->discount === 0 ||
                empty($model->discount_type) ||
                ($model->discount_start_at && $now->lt($model->discount_start_at)) ||
                ($model->discount_end_at && $now->gt($model->discount_end_at))
            ) {
                return round($totalFinalPrice / 100, 2);
            }

            $discount = $model->getRawOriginal('discount');

            if ($model->discount_type === DiscountTypeEnum::BY_PERCENTAGE->value) {
                $totalFinalPrice = $totalFinalPrice - (($totalFinalPrice * $discount) / 100);
            } else if ($model->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
                $totalFinalPrice = $totalFinalPrice - $discount;
            }

            return round($totalFinalPrice / 100, 2);
        }

        if (
            empty($model->discount) || $model->discount === 0 ||
            empty($model->discount_type) ||
            ($model->discount_start_at && $now->lt($model->discount_start_at)) ||
            ($model->discount_end_at && $now->gt($model->discount_end_at))
        ) {
            return $model->price;
        }

        $price = $model->getRawOriginal('price');
        $discount = $model->getRawOriginal('discount');

        if ($model->discount_type === DiscountTypeEnum::BY_PERCENTAGE->value) {
            $finalPrice = $price - (($price * $discount) / 100);
        } else if ($model->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
            $finalPrice = $price - $discount;
        } else {
            return $model->price;
        }

        return round($finalPrice / 100, 2);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(ProductVariant|Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
