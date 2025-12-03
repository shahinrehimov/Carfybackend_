<?php

namespace App\Models;

use App\Enum\OrderPaymentStatusEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPaymentStatus extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'order_id',
        'payment_status',
    ];

    protected $casts = [
        'payment_status' => OrderPaymentStatusEnum::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
