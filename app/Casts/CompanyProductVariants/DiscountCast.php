<?php

namespace App\Casts\CompanyProductVariants;

use App\Enum\DiscountTypeEnum;
use App\Models\CompanyProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DiscountCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(CompanyProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        if ($model->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
            return round(($value ?? 0) / 100, 2);
        }

        return $value ?? 0;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(CompanyProductVariant|Model $model, string $key, mixed $value, array $attributes): int
    {
        if ($model->discount_type === DiscountTypeEnum::BY_AMOUNT->value) {
            return $value * 100;
        }

        return round($value);
    }
}
