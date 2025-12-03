<?php

namespace App\Casts\Products;

use App\Models\Product;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class HoverImageCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param Product|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(Product|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('products_hover')) {
            return $model->getFirstMediaUrl('products_hover');
        }

        return $model->main_image;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return bool
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
