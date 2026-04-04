<?php

namespace App\Models;

use App\Casts\ProductReviews\ImagesCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CompanyProductVariant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $approved_at
 * @property mixed|null $approved_by
 */
class ProductReview extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'company_product_variant_id',
        'user_id', // nullable
        'rating',
        'name', // 150
        'surname', // 150
        'email', // 150
        'review', // 5000
        'approved_at', // nullable
        'approved_by', // nullable
    ];

    protected $casts = [
        'rating'      => 'integer',
        'approved_at' => 'datetime',
        'images'      => ImagesCast::class,
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

    public function companyProductVariant(): BelongsTo
    {
        return $this->belongsTo(CompanyProductVariant::class);
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
