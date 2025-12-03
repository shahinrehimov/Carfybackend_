<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\Country;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CityRepository
{
    public function load(Country $country, array $validated): LengthAwarePaginator
    {
        return $country->cities()
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

    public function list(Country $country): Collection
    {
        return $country->cities()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    private function checkCountry(Country $country, City $city): void
    {
        if ($city->country_id !== $country->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }
    }

    public function show(Country $country, City $city): City
    {
        $this->checkCountry($country, $city);

        return $city;
    }

    public function loadRelations(City $city): City
    {
        return $city
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(Country $country, FormRequest $request): City
    {
        return DB::transaction(static function () use ($country, $request) {
            $validated = $request->validated();
            $validated['country_id'] = $country->id;

            return City::createWithLangs($validated);
        });
    }

    public function update(Country $country, City $city, FormRequest $request): City
    {
        $this->checkCountry($country, $city);

        return DB::transaction(static function () use ($city, $request) {
            $city->updateWithLangs($request->validated());

            return $city;
        });
    }

    public function destroy(Country $country, City $city): void
    {
        $this->checkCountry($country, $city);

        DB::transaction(static function () use ($city) {
            $city->delete();
        });
    }

    public function changeActiveStatus(Country $country, City $city): void
    {
        $this->checkCountry($country, $city);

        DB::transaction(function () use ($city) {
            $city->is_active = !$city->is_active;
            $city->save();
        });
    }

    public function changeSorting(Country $country, City $city, int $sort): void
    {
        $this->checkCountry($country, $city);

        $city->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(Country $country, array $validated): void
    {
        $cities = $validated['items'];

        $citiesCount = $country->cities()->whereIn('id', data_get($cities, '*.id'))->count();

        if ($citiesCount !== count($cities)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($country, $cities) {
            foreach ($cities as $cityData) {
                $country->cities()->where('id', $cityData['id'])->update(['sort' => $cityData['sort']]);
            }
        });
    }
}
