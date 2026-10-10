<?php

use App\Models\Company;
use App\Models\CompanyProductVariant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Companies deleted before listings were part of their cascade left live
     * listings behind, so those listings stayed in customers' baskets and broke
     * checkout. Delete them through the model so the cascade also removes their
     * basket and wishlist rows and stamps is_deleted.
     */
    public function up(): void
    {
        $deletedCompanyIds = Company::onlyTrashed()->pluck('id');

        if ($deletedCompanyIds->isEmpty()) {
            return;
        }

        CompanyProductVariant::query()
            ->whereIn('company_id', $deletedCompanyIds)
            ->each(fn (CompanyProductVariant $listing) => $listing->delete());
    }

    public function down(): void
    {
        // Data cleanup; nothing to restore
    }
};
