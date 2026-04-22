<?php

namespace App\Models;

use App\Enum\OrderItemStatusEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItemStatus extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'order_item_id',
        'status',
    ];

    protected $casts = [
        'status' => OrderItemStatusEnum::class,
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
