<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $user_id
 * @property bool|mixed $is_default
 */
class Address extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'user_id',
        'is_default',
        'address_type_id',
        'city_id',
        'address_line_1',
        'address_line_2', // nullable
        'latitude',
        'longitude',
        'phone',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function scopeIsDefault(Builder $builder): void
    {
        $builder->where('is_default', true);
    }

    public function isDefault(): bool
    {
        return $this->is_default ?? false;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addressType(): BelongsTo
    {
        return $this->belongsTo(AddressType::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
