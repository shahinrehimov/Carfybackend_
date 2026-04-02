<?php

namespace App\Repositories;

use App\Models\CarColor;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CarColorRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return CarColor::query()
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

        return CarColor::query()
            ->active()
            ->with(['translatable'])
            ->leftJoin('model_translations as mt', function (JoinClause $join) use ($langId) {
                $join->on('mt.translatable_id', '=', 'car_colors.id')
                    ->where('mt.translatable_type', '=', CarColor::class)
                    ->where('mt.column', '=', 'title')
                    ->where('mt.language_id', '=', $langId)
                    ->whereNull('mt.deleted_at');
            })
            ->orderBy('mt.text')
            ->select('car_colors.*')
            ->get();
    }

    public function loadRelations(CarColor $carColor): CarColor
    {
        return $carColor->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): CarColor
    {
        return DB::transaction(static function () use ($request) {
            return CarColor::createWithLangs($request->validated());
        });
    }

    public function update(CarColor $carColor, FormRequest $request): CarColor
    {
        return DB::transaction(static function () use ($carColor, $request) {
            $carColor->updateWithLangs($request->validated());
            return $carColor;
        });
    }

    public function destroy(CarColor $carColor): void
    {
        DB::transaction(static function () use ($carColor) {
            $carColor->delete();
        });
    }

    public function changeActiveStatus(CarColor $carColor): void
    {
        DB::transaction(function () use ($carColor) {
            $carColor->is_active = !$carColor->is_active;
            $carColor->save();
        });
    }

    public function changeSorting(CarColor $carColor, int $sort): void
    {
        $carColor->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = CarColor::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                CarColor::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
