<?php

namespace App\Casts\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FileCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param Setting|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(Setting|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('settings')) {
            return $model->getFirstMediaUrl('settings');
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
