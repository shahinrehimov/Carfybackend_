<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyProductVariant;

/**
 * Resolves the platform commission rate that applies to a seller listing.
 *
 * The rate is looked up in a chain, first non-null wins:
 *   1. the seller's own rate (companies.commission_percent) — negotiated deals
 *   2. the product's main category rate (categories.commission_percent)
 *   3. the platform default (default_commission_percent setting)
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

    /** @var array<int, float> resolved rates per CPV id, for the current request */
    private array $resolved = [];

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
     */
    public function percentFor(?CompanyProductVariant $cpv): float
    {
        if (!$cpv) {
            return $this->defaultPercent();
        }

        if (array_key_exists($cpv->id, $this->resolved)) {
            return $this->resolved[$cpv->id];
        }

        return $this->resolved[$cpv->id] = $this->resolvePercent($cpv);
    }

    /**
     * Commission owed on a line total, both given and returned in manats.
     */
    public function amountFor(?CompanyProductVariant $cpv, float $lineTotal): float
    {
        return $this->applyPercent($lineTotal, $this->percentFor($cpv));
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
        $this->resolved       = [];
        $this->defaultPercent = null;
    }

    private function resolvePercent(CompanyProductVariant $cpv): float
    {
        $companyPercent = $this->companyPercent($cpv);

        if ($companyPercent !== null) {
            return $companyPercent;
        }

        $categoryPercent = $this->categoryPercent($cpv);

        if ($categoryPercent !== null) {
            return $categoryPercent;
        }

        return $this->defaultPercent();
    }

    private function companyPercent(CompanyProductVariant $cpv): ?float
    {
        $company = $cpv->relationLoaded('company')
            ? $cpv->company
            : Company::withTrashed()->find($cpv->company_id);

        return $company?->commission_percent !== null
            ? (float) $company->commission_percent
            : null;
    }

    private function categoryPercent(CompanyProductVariant $cpv): ?float
    {
        $categoryId = $this->mainCategoryId($cpv);

        if (!$categoryId) {
            return null;
        }

        $category = Category::withTrashed()->find($categoryId);

        return $category?->commission_percent !== null
            ? (float) $category->commission_percent
            : null;
    }

    private function mainCategoryId(CompanyProductVariant $cpv): ?int
    {
        $variant = $cpv->relationLoaded('productVariant')
            ? $cpv->productVariant
            : $cpv->productVariant()->withTrashed()->first();

        $product = $variant?->relationLoaded('product')
            ? $variant->product
            : $variant?->product()->withTrashed()->first();

        return $product?->main_category_id;
    }
}
