<?php

namespace App\Repositories;

use App\Models\FuelType;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FuelTypeRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return FuelType::query()
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
        return FuelType::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function show(FuelType $fuelType): FuelType
    {
        return $fuelType;
    }

    public function loadRelations(FuelType $fuelType): FuelType
    {
        return $fuelType->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): FuelType
    {
        return DB::transaction(static function () use ($request) {
            return FuelType::createWithLangs($request->validated());
        });
    }

    public function update(FuelType $fuelType, FormRequest $request): FuelType
    {
        return DB::transaction(static function () use ($fuelType, $request) {
            $fuelType->updateWithLangs($request->validated());
            return $fuelType;
        });
    }

    public function destroy(FuelType $fuelType): void
    {
        DB::transaction(static function () use ($fuelType) {
            $fuelType->delete();
        });
    }

    public function changeActiveStatus(FuelType $fuelType): void
    {
        DB::transaction(function () use ($fuelType) {
            $fuelType->is_active = !$fuelType->is_active;
            $fuelType->save();
        });
    }

    public function changeSorting(FuelType $fuelType, int $sort): void
    {
        $fuelType->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = FuelType::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                FuelType::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
