<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CommissionTier;
use App\Models\Company;
use App\Models\CompanyProductVariant;
use Illuminate\Support\Collection;

/**
 * Resolves the platform commission rate that applies to a seller listing.
 *
 * The rate is looked up in a chain, first match wins:
 *   1. the seller's own rates — amount tiers first, then the flat
 *      companies.commission_percent
 *   2. the product's main category rates — tiers first, then the flat
 *      categories.commission_percent
 *   3. the platform rates — global tiers first, then the
 *      default_commission_percent setting
 *
 * Tiers pick the percent from the line amount (min inclusive, max exclusive,
 * NULL max = open ended); the chosen percent then applies to the whole
 * amount. An amount no tier covers falls through to the level's flat rate,
 * and a level with neither falls through to the next level.
 *
 * The resolved rate is only a starting point: once an order is placed the
 * percent and the resulting amount are frozen on the order item, so changing a
 * rate here never rewrites past orders.
 */
class CommissionService
{
    private static ?CommissionService $instance = null;

    public const DEFAULT_PERCENT_SETTING = 'default_commission_percent';

    /** Used when the setting row is missing entirely. */
    public const FALLBACK_PERCENT = 0.0;

    /** @var array<int, array{tiers: Collection, percent: float|null}> per company id */
    private array $companyRates = [];

    /** @var array<int, array{tiers: Collection, percent: float|null}> per category id */
    private array $categoryRates = [];

    /** @var array<int, int|null> resolved main category id per CPV id */
    private array $cpvCategoryIds = [];

    private ?Collection $globalTiers = null;

    private ?float $defaultPercent = null;

    private function __construct()
    {
    }

    public static function instance(): CommissionService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Commission rate in percent for a listing, e.g. 12.5 for 12.5%.
     * The line amount (in manats) decides which tier applies.
     */
    public function percentFor(?CompanyProductVariant $cpv, float $amount = 0.0): float
    {
        if ($cpv) {
            $percent = $this->ratePercent($this->companyRates($cpv), $amount)
                ?? $this->ratePercent($this->categoryRates($cpv), $amount);

            if ($percent !== null) {
                return $percent;
            }
        }

        return $this->tierPercent($this->globalTiers(), $amount) ?? $this->defaultPercent();
    }

    /**
     * Commission owed on a line total, both given and returned in manats.
     */
    public function amountFor(?CompanyProductVariant $cpv, float $lineTotal): float
    {
        return $this->applyPercent($lineTotal, $this->percentFor($cpv, $lineTotal));
    }

    /**
     * Commission owed on a line total for an already resolved percent.
     * Rounded to whole cents so the stored snapshot is exact.
     */
    public function applyPercent(float $lineTotal, float $percent): float
    {
        return round($lineTotal * $percent) / 100;
    }

    public function defaultPercent(): float
    {
        if ($this->defaultPercent !== null) {
            return $this->defaultPercent;
        }

        $value = SettingService::instance()->getItem(self::DEFAULT_PERCENT_SETTING);

        return $this->defaultPercent = $value === null
            ? self::FALLBACK_PERCENT
            : (float) $value;
    }

    /**
     * Forget cached lookups. Only needed by long running processes (backfill
     * commands, queue workers) that must see rate edits made meanwhile.
     */
    public function flush(): void
    {
        $this->companyRates   = [];
        $this->categoryRates  = [];
        $this->cpvCategoryIds = [];
        $this->globalTiers    = null;
        $this->defaultPercent = null;
    }

    /**
     * Percent from one level: a matching tier wins, otherwise the level's
     * flat rate, otherwise null to fall through to the next level.
     *
     * @param array{tiers: Collection, percent: float|null} $rates
     */
    private function ratePercent(array $rates, float $amount): ?float
    {
        return $this->tierPercent($rates['tiers'], $amount) ?? $rates['percent'];
    }

    private function tierPercent(Collection $tiers, float $amount): ?float
    {
        $tier = $tiers->first(static fn (CommissionTier $tier) => $tier->matches($amount));

        return $tier?->percent;
    }

    /**
     * @return array{tiers: Collection, percent: float|null}
     */
    private function companyRates(CompanyProductVariant $cpv): array
    {
        if (array_key_exists($cpv->company_id, $this->companyRates)) {
            return $this->companyRates[$cpv->company_id];
        }

        $company = $cpv->relationLoaded('company')
            ? $cpv->company
            : Company::withTrashed()->find($cpv->company_id);

        return $this->companyRates[$cpv->company_id] = [
            'tiers' => $this->tiersFor(Company::class, $company?->id),
            'percent' => $company?->commission_percent !== null
                ? (float) $company->commission_percent
                : null,
        ];
    }

    /**
     * @return array{tiers: Collection, percent: float|null}
     */
    private function categoryRates(CompanyProductVariant $cpv): array
    {
        $categoryId = $this->mainCategoryId($cpv);

        if (!$categoryId) {
            return ['tiers' => collect(), 'percent' => null];
        }

        if (array_key_exists($categoryId, $this->categoryRates)) {
            return $this->categoryRates[$categoryId];
        }

        $category = Category::withTrashed()->find($categoryId);

        return $this->categoryRates[$categoryId] = [
            'tiers' => $this->tiersFor(Category::class, $category?->id),
            'percent' => $category?->commission_percent !== null
                ? (float) $category->commission_percent
                : null,
        ];
    }

    private function tiersFor(string $type, ?int $id): Collection
    {
        if (!$id) {
            return collect();
        }

        return CommissionTier::query()
            ->where('tierable_type', $type)
            ->where('tierable_id', $id)
            ->orderBy('min_amount')
            ->get();
    }

    private function globalTiers(): Collection
    {
        return $this->globalTiers ??= CommissionTier::query()
            ->globalTiers()
            ->orderBy('min_amount')
            ->get();
    }

    private function mainCategoryId(CompanyProductVariant $cpv): ?int
    {
        if (array_key_exists($cpv->id, $this->cpvCategoryIds)) {
            return $this->cpvCategoryIds[$cpv->id];
        }

        $variant = $cpv->relationLoaded('productVariant')
            ? $cpv->productVariant
            : $cpv->productVariant()->withTrashed()->first();

        $product = $variant?->relationLoaded('product')
            ? $variant->product
            : $variant?->product()->withTrashed()->first();

        return $this->cpvCategoryIds[$cpv->id] = $product?->main_category_id;
    }
}
