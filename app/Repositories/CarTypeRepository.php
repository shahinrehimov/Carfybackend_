<?php

namespace App\Repositories;

use App\Models\CarType;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Illuminate\Database\Query\JoinClause;

class CarTypeRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return CarType::query()
            ->with(['translatable', 'creatable'])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                    $query->where('column', 'title')
                        ->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        $langId = LangService::instance()->getCurrentLangId();

        return CarType::query()
            ->active()
            ->with(['translatable'])
            ->leftJoin('model_translations as mt', function (JoinClause $join) use ($langId) {
                $join->on('mt.translatable_id', '=', 'car_types.id')
                    ->where('mt.translatable_type', '=', CarType::class)
                    ->where('mt.column', '=', 'title')
                    ->where('mt.language_id', '=', $langId)
                    ->whereNull('mt.deleted_at');
            })
            ->orderBy('mt.text')
            ->select('car_types.*')
            ->get();
    }

    public function show(CarType $carType): CarType
    {
        return $carType;
    }

    public function loadRelations(CarType $carType): CarType
    {
        return $carType->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): CarType
    {
        return DB::transaction(static function () use ($request) {
            return CarType::createWithLangs($request->validated());
        });
    }

    public function update(CarType $carType, FormRequest $request): CarType
    {
        return DB::transaction(static function () use ($carType, $request) {
            $carType->updateWithLangs($request->validated());
            return $carType;
        });
    }

    public function destroy(CarType $carType): void
    {
        DB::transaction(static function () use ($carType) {
            $carType->delete();
        });
    }

    public function changeActiveStatus(CarType $carType): void
    {
        DB::transaction(function () use ($carType) {
            $carType->is_active = !$carType->is_active;
            $carType->save();
        });
    }

    public function changeSorting(CarType $carType, int $sort): void
    {
        $carType->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = CarType::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                CarType::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
