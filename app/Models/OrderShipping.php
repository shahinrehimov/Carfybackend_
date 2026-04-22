<?php

namespace App\Models;

use App\Casts\Orders\AmountCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderShipping extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $table = 'order_shipping';

    protected $fillable = [
        'order_id',
        'company_id',
        'amount',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => AmountCast::class,
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }
}
