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

        if (
            empty($model->discount) || $model->discount === 0 ||
            empty($model->discount_type) ||
            ($model->discount_start_at && $now->lt($model->discount_start_at)) ||
            ($model->discount_end_at && $now->gt($model->discount_end_at))
        ) {
            return $model->price;
        }

        $price = $model->getRawOriginal('price') ?? 0;
        $discount = $model->getRawOriginal('discount') ?? 0;

        if ($model->discount_type === DiscountTypeEnum::BY_PERCENTAGE->value) {
            $finalPrice = $price - (($price * $discount) / 100);
        } else if ($model->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
            $finalPrice = $price - $discount;
        } else {
            return $model->price;
        }

        $final = round($finalPrice / 100, 2);

        // Commission goes on top of the discounted price, mirroring PriceCast.
        $cpv = $model->seller_listing ?? null;

        return $cpv ? $cpv->addCommission($final) : $final;
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
