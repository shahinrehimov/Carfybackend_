<?php

namespace App\Casts\Orders;

use App\Models\Order;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CodeCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param Order|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string
     */
    public function get(Order|Model $model, string $key, mixed $value, array $attributes): string
    {
        return 'ORD-' . str_pad($model->id, 6, '0', STR_PAD_LEFT);
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
