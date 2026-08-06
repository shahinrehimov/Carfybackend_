<?php

namespace App\Casts\ProductVariants;

use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PriceCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        $price = round(($value ?? 0) / 100, 2);

        // Customer-facing contexts swap in the seller listing; its platform
        // commission is baked into the price the buyer sees.
        $cpv = $model->seller_listing ?? null;

        return $cpv ? $cpv->addCommission($price) : $price;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(ProductVariant|Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value ? (int)($value * 100) : null;
    }
}
