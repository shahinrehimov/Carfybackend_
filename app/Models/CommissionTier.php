<?php

namespace App\Models;

use App\Casts\CommissionTiers\AmountCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One amount range of a tiered commission rate, owned by a Company, a
 * Category, or the platform itself (both morph columns NULL = global tiers).
 *
 * Tier sets are config rows replaced wholesale from the admin forms, so the
 * model deliberately skips the SoftDeletes/ActionBy machinery.
 */
class CommissionTier extends Model
{
    protected $fillable = [
        'tierable_type',
        'tierable_id',
        'min_amount',
        'max_amount',
        'percent',
    ];

    protected $casts = [
        // Stored in cents, exposed in manats. min inclusive, max exclusive;
        // NULL max = no upper bound.
        'min_amount' => AmountCast::class,
        'max_amount' => AmountCast::class,
        'percent' => 'float',
    ];

    public function tierable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeGlobalTiers(Builder $builder): void
    {
        $builder->whereNull('tierable_type')->whereNull('tierable_id');
    }

    /**
     * Whether an amount (in manats) falls inside this tier.
     */
    public function matches(float $amount): bool
    {
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        return $this->max_amount === null || $amount < (float) $this->max_amount;
    }
}
