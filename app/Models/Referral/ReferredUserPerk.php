<?php

namespace App\Models\Referral;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferredUserPerk extends Model
{
    public $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $table = 'referred_user_perks';

    protected $fillable = [
        'user_id',
        'free_delivery_used_count',
        'discount_orders_count',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFreeDelivery(): bool
    {
        return $this->free_delivery_used_count < 3;
    }

    public function hasDiscount(): bool
    {
        return $this->discount_orders_count < 3;
    }

    public function remainingFreeDeliveries(): int
    {
        return max(0, 3 - $this->free_delivery_used_count);
    }

    public function remainingDiscountOrders(): int
    {
        return max(0, 3 - $this->discount_orders_count);
    }
}
