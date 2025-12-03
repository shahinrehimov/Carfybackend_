<?php

namespace App\Repositories;

use App\Models\Category;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CategoryRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Category::query()
            ->onlyParents()
            ->with([
                'translatable',
                'creatable',
                'media',
            ])
            ->withCount([
                'subs',
            ])
            ->when(($validated['with_subs'] ?? 'no') === 'yes', function ($query) {
                $query->with([
                    'subs',
                    'subs.translatable',
                ]);
            })
            ->when($validated['parent_id'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where('parent_id', $validated['parent_id']);
            })
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

    public function loadSubs(Category $category, array $validated): LengthAwarePaginator
    {
        return $category->subs()
            ->with([
                'translatable',
                'creatable',
                'media',
            ])
            ->withCount([
                'subs',
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
        return Category::query()
            ->active()
            ->onlyParents()
            ->with([
                'translatable',
            ])
            ->when(($validated['with_subs'] ?? 'no') === 'yes', function ($query) {
                $query->with([
                    'subs',
                    'subs.translatable',
                ]);
            })
            ->when(($validated['with_image'] ?? 'no') === 'yes', function ($query) {
                $query->with([
                    'media',
                ]);
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function listSubs(Category $category, array $validated = []): Collection
    {
        return $category->subs()
            ->active()
            ->with([
                'translatable',
            ])
            ->when(($validated['with_image'] ?? 'no') === 'yes', function ($query) {
                $query->with([
                    'media',
                ]);
            })
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(Category $category): Category
    {
        $category->load([
            'media',
        ]);

        return $category;
    }

    public function loadRelations(Category $category): Category
    {
        return $category
            ->load([
                'translatable',
                'creatable',
                'media',
                'parent',
                'parent.translatable',
            ])
            ->loadCount([
                'subs',
            ]);
    }

    public function store(FormRequest $request): Category
    {
        return DB::transaction(static function () use ($request) {
            $validated = $request->validated();

            $validated['uuid'] = Str::uuid();

            $category = Category::createWithLangs($validated);

            FileUpload::upload($request, 'image', 'categories', $category);

            return $category;
        });
    }

    public function update(Category $category, FormRequest $request): Category
    {
        return DB::transaction(static function () use ($category, $request) {
            $category->updateWithLangs($request->validated());

            FileUpload::upload($request, 'image', 'categories', $category);

            return $category;
        });
    }

    public function destroy(Category $category): void
    {
        DB::transaction(static function () use ($category) {
            $category->delete();
        });
    }

    public function deleteImage(Category $category): void
    {
        DB::transaction(static function () use ($category) {
            $category->clearMediaCollection('categories');
        });
    }

    public function changeActiveStatus(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $category->is_active = !$category->is_active;
            $category->save();
        });
    }

    public function changeSorting(Category $category, int $sort): void
    {
        $category->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $categories = $validated['items'];

        $categoriesCount = Category::query()->whereIn('id', data_get($categories, '*.id'))->count();

        if ($categoriesCount !== count($categories)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($categories) {
            foreach ($categories as $categoryData) {
                Category::query()->where('id', $categoryData['id'])->update(['sort' => $categoryData['sort']]);
            }
        });
    }
}
