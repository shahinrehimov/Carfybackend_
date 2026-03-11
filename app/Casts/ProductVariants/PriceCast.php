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
        return round(($value ?? 0) / 100, 2);
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
