<?php

namespace App\Repositories;

use App\Models\AddressType;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AddressTypeRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return AddressType::query()
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

    public function list(): Collection
    {
        return AddressType::query()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function show(AddressType $addressType): AddressType
    {
        return $addressType;
    }

    public function loadRelations(AddressType $addressType): AddressType
    {
        return $addressType
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(FormRequest $request): AddressType
    {
        return DB::transaction(static function () use ($request) {
            return AddressType::createWithLangs($request->validated());
        });
    }

    public function update(AddressType $addressType, FormRequest $request): AddressType
    {
        return DB::transaction(static function () use ($addressType, $request) {
            $addressType->updateWithLangs($request->validated());

            return $addressType;
        });
    }

    public function destroy(AddressType $addressType): void
    {
        DB::transaction(static function () use ($addressType) {
            $addressType->delete();
        });
    }

    public function changeActiveStatus(AddressType $addressType): void
    {
        DB::transaction(function () use ($addressType) {
            $addressType->is_active = !$addressType->is_active;
            $addressType->save();
        });
    }

    public function changeSorting(AddressType $addressType, int $sort): void
    {
        $addressType->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $addressTypes = $validated['items'];

        $addressTypesCount = AddressType::query()->whereIn('id', data_get($addressTypes, '*.id'))->count();

        if ($addressTypesCount !== count($addressTypes)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($addressTypes) {
            foreach ($addressTypes as $addressTypeData) {
                AddressType::query()->where('id', $addressTypeData['id'])->update(['sort' => $addressTypeData['sort']]);
            }
        });
    }
}
