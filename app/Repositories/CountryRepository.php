<?php

namespace App\Repositories;

use App\Models\Country;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CountryRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Country::query()
            ->with([
                'translatable',
                'creatable',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'title');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(bool $withCities = false): Collection
    {
        return Country::query()
            ->active()
            ->with([
                'translatable',
            ])
            ->when($withCities, function (Builder $builder) {
                $builder->with([
                    'cities' => function ($query) {
                        $query->active();
                        $query->orderBy('sort');
                        $query->orderBy('id');
                    },
                    'cities.translatable',
                ]);
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function show(Country $country): Country
    {
        return $country;
    }

    public function loadRelations(Country $country): Country
    {
        return $country
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(FormRequest $request): Country
    {
        return DB::transaction(static function () use ($request) {
            return Country::createWithLangs($request->validated());
        });
    }

    public function update(Country $country, FormRequest $request): Country
    {
        return DB::transaction(static function () use ($country, $request) {
            $country->updateWithLangs($request->validated());

            return $country;
        });
    }

    public function destroy(Country $country): void
    {
        DB::transaction(static function () use ($country) {
            $country->delete();
        });
    }

    public function changeActiveStatus(Country $country): void
    {
        DB::transaction(function () use ($country) {
            $country->is_active = !$country->is_active;
            $country->save();
        });
    }

    public function changeSorting(Country $country, int $sort): void
    {
        $country->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $countries = $validated['items'];

        $countriesCount = Country::query()->whereIn('id', data_get($countries, '*.id'))->count();

        if ($countriesCount !== count($countries)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($countries) {
            foreach ($countries as $countryData) {
                Country::query()->where('id', $countryData['id'])->update(['sort' => $countryData['sort']]);
            }
        });
    }
}
