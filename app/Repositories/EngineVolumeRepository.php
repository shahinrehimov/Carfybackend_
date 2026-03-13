<?php

namespace App\Repositories;

use App\Models\EngineVolume;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EngineVolumeRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return EngineVolume::query()
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
        return EngineVolume::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('value_liters')
            ->get();
    }

    public function show(EngineVolume $engineVolume): EngineVolume
    {
        return $engineVolume;
    }

    public function loadRelations(EngineVolume $engineVolume): EngineVolume
    {
        return $engineVolume->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): EngineVolume
    {
        return DB::transaction(static function () use ($request) {
            return EngineVolume::createWithLangs($request->validated());
        });
    }

    public function update(EngineVolume $engineVolume, FormRequest $request): EngineVolume
    {
        return DB::transaction(static function () use ($engineVolume, $request) {
            $engineVolume->updateWithLangs($request->validated());
            return $engineVolume;
        });
    }

    public function destroy(EngineVolume $engineVolume): void
    {
        DB::transaction(static function () use ($engineVolume) {
            $engineVolume->delete();
        });
    }

    public function changeActiveStatus(EngineVolume $engineVolume): void
    {
        DB::transaction(function () use ($engineVolume) {
            $engineVolume->is_active = !$engineVolume->is_active;
            $engineVolume->save();
        });
    }

    public function changeSorting(EngineVolume $engineVolume, int $sort): void
    {
        $engineVolume->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = EngineVolume::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                EngineVolume::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
