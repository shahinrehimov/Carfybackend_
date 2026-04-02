<?php

namespace App\Repositories;

use App\Models\Page;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PageRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Page::query()
            ->with([
                'translatable',
                'creatable',
                'media',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                    $query->where(function (Builder $q) use ($validated) {
                        $q->where('column', 'title');
                        $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                    $query->orWhere(function (Builder $q) use ($validated) {
                        $q->where('column', 'description');
                        $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                    $query->orWhere(function (Builder $q) use ($validated) {
                        $q->where('column', 'body');
                        $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Page::query()
            ->active()
            ->with([
                'translatable',
                'media',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(Page $page): Page
    {
        $page->load(['media']);

        return $page;
    }

    public function loadRelations(Page $page): Page
    {
        return $page->load([
            'translatable',
            'creatable',
            'media',
        ]);
    }

    public function store(FormRequest $request): Page
    {
        return DB::transaction(static function () use ($request) {
            $page = Page::createWithLangs($request->validated());

            FileUpload::upload($request, 'main_image', 'pages_main', $page);
            FileUpload::upload($request, 'icon', 'pages_icon', $page);
            FileUpload::multipleUpload($request, 'images', 'pages_images', $page);

            return $page;
        });
    }

    public function update(Page $page, FormRequest $request): Page
    {
        return DB::transaction(static function () use ($page, $request) {
            $page->updateWithLangs($request->validated());

            FileUpload::upload($request, 'main_image', 'pages_main', $page);
            FileUpload::upload($request, 'icon', 'pages_icon', $page);
            FileUpload::multipleUpload($request, 'images', 'pages_images', $page);

            return $page;
        });
    }

    public function destroy(Page $page): void
    {
        DB::transaction(static function () use ($page) {
            $page->delete();
        });
    }

    public function deleteImage(Page $page, int $mediaId): void
    {
        DB::transaction(static function () use ($page, $mediaId) {
            $page->deleteMedia($mediaId);
        });
    }

    public function changeActiveStatus(Page $page): void
    {
        DB::transaction(function () use ($page) {
            $page->is_active = !$page->is_active;
            $page->save();
        });
    }

    public function changeSorting(Page $page, int $sort): void
    {
        $page->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $pages = $validated['items'];

        $pagesCount = Page::query()->whereIn('id', data_get($pages, '*.id'))->count();

        if ($pagesCount !== count($pages)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($pages) {
            foreach ($pages as $pageData) {
                Page::query()->where('id', $pageData['id'])->update(['sort' => $pageData['sort']]);
            }
        });
    }

    public function listForSite(array $validated): Collection
    {
        return Page::query()
            ->active()
            ->with([
                'translatable',
                'media',
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->limit($validated['limit'] ?? 10)
            ->get();
    }

    public function showBySlugForSite(string $slug): Page
    {
        return Page::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'translatable',
                'media',
            ])
            ->firstOrFail();
    }
}
