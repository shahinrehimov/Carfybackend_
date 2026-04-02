<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BrandRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Brand::query()
            ->with([
                'translatable',
                'creatable',
                'media',
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

    public function list(bool $withModels = false): Collection
    {
        $langId = LangService::instance()->getCurrentLangId();

        return Brand::query()
            ->active()
            ->with(['translatable'])
            ->when($withModels, function (Builder $builder) use ($langId) {
                $builder->with([
                    'brandModels' => function ($query) use ($langId) {
                        $query->active();
                        $query->leftJoin('model_translations as mt_bm', function (JoinClause $join) use ($langId) {
                            $join->on('mt_bm.translatable_id', '=', 'brand_models.id')
                                ->where('mt_bm.translatable_type', '=', BrandModel::class)
                                ->where('mt_bm.column', '=', 'title')
                                ->where('mt_bm.language_id', '=', $langId)
                                ->whereNull('mt_bm.deleted_at');
                        });
                        $query->orderBy('mt_bm.text');
                        $query->select('brand_models.*');
                    },
                    'brandModels.translatable',
                ]);
            })
            ->leftJoin('model_translations as mt', function (JoinClause $join) use ($langId) {
                $join->on('mt.translatable_id', '=', 'brands.id')
                    ->where('mt.translatable_type', '=', Brand::class)
                    ->where('mt.column', '=', 'title')
                    ->where('mt.language_id', '=', $langId)
                    ->whereNull('mt.deleted_at');
            })
            ->orderBy('mt.text')
            ->select('brands.*')
            ->get();
    }

    public function show(Brand $brand): Brand
    {
        $brand->load([
            'media',
        ]);

        return $brand;
    }

    public function loadRelations(Brand $brand): Brand
    {
        return $brand
            ->load([
                'translatable',
                'creatable',
                'media',
            ]);
    }

    public function store(FormRequest $request): Brand
    {
        return DB::transaction(static function () use ($request) {
            $validated = $request->validated();

            $validated['uuid'] = Str::uuid();

            $brand = Brand::createWithLangs($validated);

            FileUpload::upload($request, 'image', 'brands', $brand);

            return $brand;
        });
    }

    public function update(Brand $brand, FormRequest $request): Brand
    {
        return DB::transaction(static function () use ($brand, $request) {
            $brand->updateWithLangs($request->validated());

            FileUpload::upload($request, 'image', 'brands', $brand);

            return $brand;
        });
    }

    public function destroy(Brand $brand): void
    {
        DB::transaction(static function () use ($brand) {
            $brand->delete();
        });
    }

    public function deleteImage(Brand $brand): void
    {
        DB::transaction(static function () use ($brand) {
            $brand->clearMediaCollection('brands');
        });
    }

    public function changeActiveStatus(Brand $brand): void
    {
        DB::transaction(function () use ($brand) {
            $brand->is_active = !$brand->is_active;
            $brand->save();
        });
    }

    public function changeSorting(Brand $brand, int $sort): void
    {
        $brand->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $brands = $validated['items'];

        $brandsCount = Brand::query()->whereIn('id', data_get($brands, '*.id'))->count();

        if ($brandsCount !== count($brands)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($brands) {
            foreach ($brands as $brandData) {
                Brand::query()->where('id', $brandData['id'])->update(['sort' => $brandData['sort']]);
            }
        });
    }
}
