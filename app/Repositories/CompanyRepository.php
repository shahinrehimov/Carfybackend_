<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\CompanyWorkingHour;
use App\Models\ProductReview;
use App\Services\FileUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Company::query()
            ->with([
                'creatable',
                'media',
                'city',
                'city.translatable',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereLike('email', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('phone', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('voen', '%' . $validated['search'] . '%');
                    $builder->orWhereHas('translatable', function (Builder $query) use ($validated) {
                        $query->whereLike('text', '%' . $validated['search'] . '%');
                    });
                });
            })
            ->when(isset($validated['is_active']), function (Builder $builder) use ($validated) {
                $builder->where('is_active', $validated['is_active']);
            })
            ->when(isset($validated['verified']), function (Builder $builder) use ($validated) {
                if ($validated['verified']) {
                    $builder->whereNotNull('verified_at');
                } else {
                    $builder->whereNull('verified_at');
                }
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Company::query()
            ->with('media')
            ->whereNotNull('verified_at')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function show(Company $company): void
    {
        $company->load([
            'media',
            'city',
            'city.translatable',
            'workingHours',
            'commissionTiers',
        ]);

        $stats = ProductReview::query()
            ->whereHas('companyProductVariant', fn($q) => $q->where('company_id', $company->id))
            ->approved()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        $company->avg_rating   = $stats->avg_rating ? round((float) $stats->avg_rating, 1) : null;
        $company->review_count = (int) $stats->review_count;
    }

    public function createFromData(array $data, ?FormRequest $request = null): Company
    {
        return DB::transaction(static function () use ($data, $request) {
            if (!isset($data['uuid'])) {
                $data['uuid'] = Str::uuid();
            }

            $company = Company::createWithLangs($data);

            if ($request) {
                FileUpload::upload($request, 'logo', 'companies_logo', $company);
                FileUpload::upload($request, 'banner', 'companies_banner', $company);
            }

            return $company;
        });
    }

    public function store(FormRequest $request): Company
    {
        $validated = $request->validated();

        $data = [
            'voen' => $validated['voen'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'website' => $validated['website'] ?? null,
            'root_admin_email' => $validated['root_admin_email'],
            'verified_at' => $validated['verified_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'commission_percent' => $validated['commission_percent'] ?? null,
            'translations' => $validated['translations'] ?? [],
        ];

        $company = $this->createFromData($data, $request);

        if (!empty($validated['commission_tiers'])) {
            (new CommissionTierRepository())->replaceFor($company, $validated['commission_tiers']);
        }

        return $company;
    }

    public function update(Company $company, FormRequest $request): Company
    {
        $validated = $request->validated();

        return DB::transaction(static function () use ($validated, $company, $request) {
            $data = [
                'voen' => $validated['voen'] ?? $company->voen,
                'phone' => $validated['phone'] ?? $company->phone,
                'email' => $validated['email'] ?? $company->email,
                'city_id' => $validated['city_id'] ?? $company->city_id,
                'latitude' => $validated['latitude'] ?? $company->latitude,
                'longitude' => $validated['longitude'] ?? $company->longitude,
                'website' => $validated['website'] ?? $company->website,
                //'root_admin_email' => $validated['root_admin_email'] ?? $company->root_admin_email,
                'is_active' => $validated['is_active'] ?? $company->is_active,
            ];

            // Sent as null on purpose = drop the seller override and inherit again,
            // so array_key_exists rather than ?? here.
            if (array_key_exists('commission_percent', $validated)) {
                $data['commission_percent'] = $validated['commission_percent'];
            }

            if (isset($validated['translations'])) {
                $data['translations'] = $validated['translations'];
            }

            $company->updateWithLangs($data);

            // Same convention as commission_percent: an explicit empty array
            // drops the tiers so the seller inherits again.
            if (array_key_exists('commission_tiers', $validated)) {
                (new CommissionTierRepository())->replaceFor($company, $validated['commission_tiers'] ?? []);
            }

            FileUpload::upload($request, 'logo', 'companies_logo', $company);
            FileUpload::upload($request, 'banner', 'companies_banner', $company);

            return $company;
        });
    }

    public function destroy(Company $company): void
    {
        DB::transaction(static function () use ($company) {
            $company->delete();
        });
    }

    public function verify(Company $company): Company
    {
        return DB::transaction(static function () use ($company) {
            $company->update([
                'verified_at' => now(),
            ]);

            return $company;
        });
    }

    public function changeActiveStatus(Company $company): Company
    {
        return DB::transaction(static function () use ($company) {
            $company->update([
                'is_active' => !$company->is_active,
            ]);

            return $company;
        });
    }

    public function loadRelations(Company $company): Company
    {
        return $company->load([
            'media',
            'creatable',
            'city',
            'city.translatable',
        ]);
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

    public function deleteLogo(Company $company): void
    {
        $company->clearMediaCollection('companies_logo');
    }

    public function deleteBanner(Company $company): void
    {
        $company->clearMediaCollection('companies_banner');
    }
}
