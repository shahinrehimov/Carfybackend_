<?php

namespace App\Repositories;

use App\Models\ProductOption;
use App\Models\ProductOptionItem;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CarColorRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return ProductOptionItem::query()
            ->forOption('color')
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
        return ProductOptionItem::query()
            ->forOption('color')
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function loadRelations(ProductOptionItem $item): ProductOptionItem
    {
        return $item->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): ProductOptionItem
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $colorOptionId = ProductOption::query()->where('name', 'color')->value('id');

            $validated['product_option_id'] = $colorOptionId;
            $validated['details']           = ['hex_code' => $validated['hex_code'] ?? null];
            $validated['uuid']              = Str::uuid();
            unset($validated['hex_code']);

            return ProductOptionItem::createWithLangs($validated);
        });
    }

    public function update(ProductOptionItem $item, FormRequest $request): ProductOptionItem
    {
        return DB::transaction(static function () use ($item, $request) {
            $validated = $request->validated();
            $validated['details'] = array_merge($item->details ?? [], ['hex_code' => $validated['hex_code'] ?? null]);
            unset($validated['hex_code']);
            $item->updateWithLangs($validated);
            return $item;
        });
    }

    public function destroy(ProductOptionItem $item): void
    {
        DB::transaction(static function () use ($item) {
            $item->delete();
        });
    }

    public function changeActiveStatus(ProductOptionItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->is_active = !$item->is_active;
            $item->save();
        });
    }

    public function changeSorting(ProductOptionItem $item, int $sort): void
    {
        $item->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = ProductOptionItem::query()
            ->forOption('color')
            ->whereIn('id', data_get($items, '*.id'))
            ->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                ProductOptionItem::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
