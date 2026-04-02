<?php

namespace App\Casts\Pages;

use App\Models\Page;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MainImageCast implements CastsAttributes
{
    public function get(Page|Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->hasMedia('pages_main')) {
            return $model->getFirstMediaUrl('pages_main');
        }

        return null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
