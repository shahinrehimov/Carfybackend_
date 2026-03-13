<?php

namespace App\Repositories;

use App\Models\TransmissionType;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TransmissionTypeRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return TransmissionType::query()
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
        return TransmissionType::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function show(TransmissionType $transmissionType): TransmissionType
    {
        return $transmissionType;
    }

    public function loadRelations(TransmissionType $transmissionType): TransmissionType
    {
        return $transmissionType->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): TransmissionType
    {
        return DB::transaction(static function () use ($request) {
            return TransmissionType::createWithLangs($request->validated());
        });
    }

    public function update(TransmissionType $transmissionType, FormRequest $request): TransmissionType
    {
        return DB::transaction(static function () use ($transmissionType, $request) {
            $transmissionType->updateWithLangs($request->validated());
            return $transmissionType;
        });
    }

    public function destroy(TransmissionType $transmissionType): void
    {
        DB::transaction(static function () use ($transmissionType) {
            $transmissionType->delete();
        });
    }

    public function changeActiveStatus(TransmissionType $transmissionType): void
    {
        DB::transaction(function () use ($transmissionType) {
            $transmissionType->is_active = !$transmissionType->is_active;
            $transmissionType->save();
        });
    }

    public function changeSorting(TransmissionType $transmissionType, int $sort): void
    {
        $transmissionType->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = TransmissionType::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                TransmissionType::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
