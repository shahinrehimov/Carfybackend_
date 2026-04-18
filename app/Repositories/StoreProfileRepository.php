<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\CompanyWorkingHour;
use App\Models\ProductReview;
use App\Services\FileUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreProfileRepository
{
    public function show(Company $company): Company
    {
        $company->load([
            'media',
            'city',
            'city.translatable',
            'workingHours',
            'translatable',
        ]);

        $stats = ProductReview::query()
            ->whereHas('companyProductVariant', fn($q) => $q->where('company_id', $company->id))
            ->approved()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        $company->avg_rating   = $stats->avg_rating ? round((float) $stats->avg_rating, 1) : null;
        $company->review_count = (int) $stats->review_count;

        return $company;
    }

    public function update(Company $company, FormRequest $request): Company
    {
        $validated = $request->validated();

        return DB::transaction(static function () use ($company, $validated, $request) {
            $fillable = array_filter([
                'phone'    => $validated['phone'] ?? $company->phone,
                'email'    => $validated['email'] ?? $company->email,
                'website'  => $validated['website'] ?? $company->website,
                'city_id'  => $validated['city_id'] ?? $company->city_id,
                'latitude' => $validated['latitude'] ?? $company->latitude,
                'longitude' => $validated['longitude'] ?? $company->longitude,
            ], fn($v) => $v !== null);

            if (isset($validated['translations'])) {
                $fillable['translations'] = $validated['translations'];
            }

            $company->updateWithLangs($fillable);

            FileUpload::upload($request, 'logo', 'companies_logo', $company);
            FileUpload::upload($request, 'banner', 'companies_banner', $company);

            return $company;
        });
    }

    public function deleteLogo(Company $company): void
    {
        $company->clearMediaCollection('companies_logo');
    }

    public function deleteBanner(Company $company): void
    {
        $company->clearMediaCollection('companies_banner');
    }

    public function syncWorkingHours(Company $company, array $hours): void
    {
        DB::transaction(static function () use ($company, $hours) {
            foreach ($hours as $hour) {
                $isClosed = (bool) $hour['is_closed'];

                CompanyWorkingHour::updateOrCreate(
                    ['company_id' => $company->id, 'day_of_week' => $hour['day_of_week']],
                    [
                        'is_closed'  => $isClosed,
                        'open_time'  => $isClosed ? null : ($hour['open_time'] ?? null),
                        'close_time' => $isClosed ? null : ($hour['close_time'] ?? null),
                    ]
                );
            }
        });
    }
}
