<?php

namespace App\Rules;

use App\Services\LangService;
use Illuminate\Contracts\Validation\Rule;

/**
 * Validates a commission tier set as a whole: ranges must not overlap and an
 * open ended tier (max_amount = null) may only be the last one. Gaps between
 * tiers are allowed — an uncovered amount falls back to the flat rate chain.
 */
class CommissionTiersRule implements Rule
{
    private string $message = '';

    public function passes($attribute, $value): bool
    {
        if (!is_array($value)) {
            return true;
        }

        $tiers = collect($value)
            ->filter(static fn ($tier) => is_array($tier) && isset($tier['min_amount']))
            ->sortBy(static fn ($tier) => (float) $tier['min_amount'])
            ->values();

        foreach ($tiers as $index => $tier) {
            $min = (float) $tier['min_amount'];
            $max = isset($tier['max_amount']) ? (float) $tier['max_amount'] : null;

            if ($max !== null && $max <= $min) {
                $this->message = LangService::instance()
                    ->setDefault('Each commission tier max amount must be greater than its min amount.')
                    ->getLang('commission_tier_max_must_exceed_min');

                return false;
            }

            $next = $tiers->get($index + 1);

            if ($next === null) {
                continue;
            }

            if ($max === null) {
                $this->message = LangService::instance()
                    ->setDefault('Only the last commission tier can be open ended.')
                    ->getLang('commission_tier_open_ended_must_be_last');

                return false;
            }

            if ((float) $next['min_amount'] < $max) {
                $this->message = LangService::instance()
                    ->setDefault('Commission tier ranges must not overlap.')
                    ->getLang('commission_tiers_must_not_overlap');

                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }
}
