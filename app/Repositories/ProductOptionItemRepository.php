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

class ProductOptionItemRepository
{

    public function load(ProductOption $productOption, array $validated): LengthAwarePaginator
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        return $productOption->items()
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

    public function list(ProductOption $productOption): Collection
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        return $productOption->items()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function checkProductOption(ProductOption $productOption, ProductOptionItem $productOptionItem): void
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        if ($productOptionItem->product_option_id !== $productOption->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product option does not belong to the specified product option item!')
                    ->getLang('admin_product_option_not_belong_to_product_option_item')
            );
        }
    }

    public function show(ProductOption $productOption, ProductOptionItem $productOptionItem): ProductOptionItem
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $this->checkProductOption($productOption, $productOptionItem);

        return $productOptionItem;
    }

    public function loadRelations(ProductOptionItem $productOptionItem): ProductOptionItem
    {
        return $productOptionItem
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(ProductOption $productOption, FormRequest $request): ProductOptionItem
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        return DB::transaction(static function () use ($productOption, $request) {
            $validated = $request->validated();

            $validated['product_option_id'] = $productOption->id;
            $validated['uuid'] = Str::uuid();

            return ProductOptionItem::createWithLangs($validated);
        });
    }

    public function update(ProductOption $productOption, ProductOptionItem $productOptionItem, FormRequest $request): ProductOptionItem
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $this->checkProductOption($productOption, $productOptionItem);

        return DB::transaction(static function () use ($productOptionItem, $request) {
            $productOptionItem->updateWithLangs($request->validated());

            return $productOptionItem;
        });
    }

    public function destroy(ProductOption $productOption, ProductOptionItem $productOptionItem): void
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $this->checkProductOption($productOption, $productOptionItem);

        DB::transaction(static function () use ($productOptionItem) {
            $productOptionItem->delete();
        });
    }

    public function changeActiveStatus(ProductOption $productOption, ProductOptionItem $productOptionItem): void
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $this->checkProductOption($productOption, $productOptionItem);

        DB::transaction(function () use ($productOptionItem) {
            $productOptionItem->is_active = !$productOptionItem->is_active;
            $productOptionItem->save();
        });
    }

    public function changeSorting(ProductOption $productOption, ProductOptionItem $productOptionItem, int $sort): void
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $this->checkProductOption($productOption, $productOptionItem);

        $productOptionItem->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(ProductOption $productOption, array $validated): void
    {
        (new ProductOptionRepository())->checkCanItems($productOption);

        $productOptionItems = $validated['items'];

        $productOptionItemsCount = $productOption->items()
            ->whereIn('id', data_get($productOptionItems, '*.id'))
            ->count();

        if ($productOptionItemsCount !== count($productOptionItems)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($productOption, $productOptionItems) {
            foreach ($productOptionItems as $productOptionItemData) {
                $productOption->items()
                    ->where('id', $productOptionItemData['id'])
                    ->update(['sort' => $productOptionItemData['sort']]);
            }
        });
    }
}
