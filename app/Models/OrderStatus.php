<?php

namespace App\Models;

use App\Enum\OrderStatusEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed|null $created_at
 */
class OrderStatus extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'order_id',
        'status',
    ];

    protected $casts = [
        'status' => OrderStatusEnum::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
