<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $approved_at
 * @property mixed|null $approved_by
 */
class ProductReview extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'user_id', // nullable
        'rating',
        'full_name', // 500
        'email', // 150
        'review', // 5000
        'approved_at', // nullable
        'approved_by', // nullable
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function scopeApproved(Builder $builder): void
    {
        $builder->whereNotNull('approved_at');
    }

    public function scopeNotApproved(Builder $builder): void
    {
        $builder->whereNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
