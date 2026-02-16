<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BrandModelRepository
{
    public function load(Brand $brand, array $validated): LengthAwarePaginator
    {
        return $brand->brandModels()
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

    public function list(Brand $brand): Collection
    {
        return $brand->brandModels()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    private function checkBrand(Brand $brand, BrandModel $brandModel): void
    {
        if ($brandModel->brand_id !== $brand->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }
    }

    public function show(Brand $brand, BrandModel $brandModel): BrandModel
    {
        $this->checkBrand($brand, $brandModel);

        return $brandModel;
    }

    public function loadRelations(BrandModel $brandModel): BrandModel
    {
        return $brandModel
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(Brand $brand, FormRequest $request): BrandModel
    {
        return DB::transaction(static function () use ($brand, $request) {
            $validated = $request->validated();
            $validated['brand_id'] = $brand->id;

            return BrandModel::createWithLangs($validated);
        });
    }

    public function update(Brand $brand, BrandModel $brandModel, FormRequest $request): BrandModel
    {
        $this->checkBrand($brand, $brandModel);

        return DB::transaction(static function () use ($brandModel, $request) {
            $brandModel->updateWithLangs($request->validated());

            return $brandModel;
        });
    }

    public function destroy(Brand $brand, BrandModel $brandModel): void
    {
        $this->checkBrand($brand, $brandModel);

        DB::transaction(static function () use ($brandModel) {
            $brandModel->delete();
        });
    }

    public function changeActiveStatus(Brand $brand, BrandModel $brandModel): void
    {
        $this->checkBrand($brand, $brandModel);

        DB::transaction(function () use ($brandModel) {
            $brandModel->is_active = !$brandModel->is_active;
            $brandModel->save();
        });
    }

    public function changeSorting(Brand $brand, BrandModel $brandModel, int $sort): void
    {
        $this->checkBrand($brand, $brandModel);

        $brandModel->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(Brand $brand, array $validated): void
    {
        $items = $validated['items'];

        $count = $brand->brandModels()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($brand, $items) {
            foreach ($items as $item) {
                $brand->brandModels()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
