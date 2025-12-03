<?php

namespace App\Casts\ProductVariants;

use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PriceInBasketCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): float
    {
        if ($model->isSet()) {
            $model->loadMissing([
                'sets' => function ($query) {
                    $query->active();
                },
            ]);

            $price = 0;
            foreach ($model->sets as $set) {
                /** @var ProductVariant $set */

                $price += $set->price_in_basket;
            }

            return $price * $model->basket_quantity;
        }

        return $model->price * $model->basket_quantity;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(ProductVariant|Model $model, string $key, mixed $value, array $attributes): null
    {
        return null;
    }
}
