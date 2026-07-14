<?php

namespace App\Casts\UserCars;

use App\Models\UserCar;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ImageCast implements CastsAttributes
{
    public function get(UserCar|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('user_car_photos')) {
            return $model->getWebImageUrl('user_car_photos');
        }

        return null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
