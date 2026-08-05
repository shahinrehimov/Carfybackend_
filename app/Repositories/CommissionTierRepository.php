<?php

namespace App\Repositories;

use App\Models\CommissionTier;
use App\Rules\CommissionTiersRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommissionTierRepository
{
    public function globalTiers(): Collection
    {
        return CommissionTier::query()
            ->globalTiers()
            ->orderBy('min_amount')
            ->get();
    }

    /**
     * Replace the whole tier set of an owner (Company, Category, or null for
     * the platform-wide tiers). An empty array simply drops the tiers, so the
     * owner inherits again through the usual chain.
     */
    public function replaceFor(?Model $tierable, array $tiers): void
    {
        DB::transaction(static function () use ($tierable, $tiers) {
            CommissionTier::query()
                ->when($tierable, static function ($query) use ($tierable) {
                    $query->where('tierable_type', $tierable->getMorphClass())
                        ->where('tierable_id', $tierable->getKey());
                }, static function ($query) {
                    $query->globalTiers();
                })
                ->delete();

            foreach ($tiers as $tier) {
                CommissionTier::query()->create([
                    'tierable_type' => $tierable?->getMorphClass(),
                    'tierable_id' => $tierable?->getKey(),
                    'min_amount' => $tier['min_amount'],
                    // Blank comes from the multipart forms, which cannot send
                    // a real null, and means "no upper bound".
                    'max_amount' => CommissionTiersRule::openEnded($tier) ? null : $tier['max_amount'],
                    'percent' => $tier['percent'],
                ]);
            }
        });
    }
}
