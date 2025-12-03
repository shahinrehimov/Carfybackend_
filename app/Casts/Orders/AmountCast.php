<?php

namespace App\Casts\Orders;

use App\Models\Product;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AmountCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Product|Model $model, string $key, mixed $value, array $attributes): float
    {
        return round($value / 100, 2);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Product|Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value * 100;
    }
}
