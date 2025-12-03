<?php

namespace App\Repositories;

use App\Models\ProductOption;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProductOptionRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return ProductOption::query()
            ->with([
                'translatable',
                'creatable',
            ])
            ->withCount([
                'items',
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

    public function list(array $validated): Collection
    {
        return ProductOption::query()
            ->active()
            ->with([
                'translatable',
            ])
            ->when(($validated['with_items'] ?? 'no') === 'yes', function ($query) {
                $query->with([
                    'items',
                    'items.translatable',
                ]);
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(ProductOption $productOption): ProductOption
    {
        return $productOption;
    }

    public function loadRelations(ProductOption $productOption): ProductOption
    {
        return $productOption
            ->load([
                'translatable',
                'creatable',
            ])
            ->loadCount([
                'items',
            ]);
    }

    public function store(FormRequest $request): ProductOption
    {
        $_this = $this;

        return DB::transaction(static function () use ($_this, $request) {
            $validated = $request->validated();

            $defaultTitle = ProductOption::getDefaultText($validated['translations'] ?? []);

            if ($defaultTitle) {
                $validated['name'] = $_this->generateName($defaultTitle);
            }

            $validated['uuid'] = Str::uuid();

            return ProductOption::createWithLangs($validated);
        });
    }

    public function update(ProductOption $productOption, FormRequest $request): ProductOption
    {
        return DB::transaction(static function () use ($productOption, $request) {
            $productOption->updateWithLangs($request->validated());

            return $productOption;
        });
    }

    public function destroy(ProductOption $productOption): void
    {
        DB::transaction(static function () use ($productOption) {
            $productOption->delete();
        });
    }

    public function changeActiveStatus(ProductOption $productOption): void
    {
        DB::transaction(function () use ($productOption) {
            $productOption->is_active = !$productOption->is_active;
            $productOption->save();
        });
    }

    public function changeSorting(ProductOption $productOption, int $sort): void
    {
        $productOption->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $productOptions = $validated['items'];

        $productOptionsCount = ProductOption::query()->whereIn('id', data_get($productOptions, '*.id'))->count();

        if ($productOptionsCount !== count($productOptions)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($productOptions) {
            foreach ($productOptions as $productOptionData) {
                ProductOption::query()->where('id', $productOptionData['id'])->update(['sort' => $productOptionData['sort']]);
            }
        });
    }

    public function checkCanItems(ProductOption $productOption): void
    {
        if (!$productOption->hasItems()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product option can not have items!')
                    ->getLang('admin_product_option_can_not_have_items')
            );
        }
    }

    public function generateName(string $title): string
    {
        $name = Str::snake($title, '');

        while (ProductOption::query()->where('name', $name)->exists()) {
            $name = Str::snake($title . Str::random(5), '');
        }

        return $name;
    }
}
