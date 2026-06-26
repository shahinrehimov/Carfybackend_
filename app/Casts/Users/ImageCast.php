<?php

namespace App\Casts\Users;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ImageCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param User|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(User|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('users')) {
            return $model->getFirstMediaUrl('users');
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
