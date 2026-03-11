<?php

namespace App\Casts\ProductVariants;

use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PriceFinalInBasketCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        return $model->price_final * ($model->basket_quantity ?? 0);
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
