<?php

namespace App\Models;

use App\Enum\PaymentLogActionEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentLog extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'order_id',
        'payment_method',
        'action',
        'transaction_id', // nullable
        'status',
        'order_status', // nullable
        'request', // nullable
        'response', // nullable
    ];

    protected $casts = [
        'action' => PaymentLogActionEnum::class,
        'request' => 'array',
        'response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
