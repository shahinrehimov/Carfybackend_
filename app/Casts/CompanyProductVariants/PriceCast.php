<?php

namespace App\Casts\CompanyProductVariants;

use App\Models\CompanyProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PriceCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(CompanyProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        return round(($value ?? 0) / 100, 2);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(CompanyProductVariant|Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value * 100;
    }
}
