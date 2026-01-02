<?php

namespace App\Casts\Companies;

use App\Models\Company;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class BannerCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param Company|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function get(Company|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('companies_banner')) {
            return $model->getFirstMediaUrl('companies_banner');
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
