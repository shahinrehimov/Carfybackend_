<?php

namespace App\Casts\Sliders;

use App\Models\SliderLayer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ImageCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param SliderLayer|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(SliderLayer|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('slider_layer_images')) {
            return $model->getFirstMediaUrl('slider_layer_images');
        }

        return null;
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
