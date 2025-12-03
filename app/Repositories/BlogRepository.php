<?php

namespace App\Repositories;

use App\Models\Blog;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BlogRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Blog::query()
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
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'description');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'body');
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
        return Blog::query()
            ->active()
            ->with([
                'translatable',
                'media',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();
    }

    public function show(Blog $blog): Blog
    {
        $blog->load([
            'media',
        ]);

        return $blog;
    }

    public function loadRelations(Blog $blog): Blog
    {
        return $blog
            ->load([
                'translatable',
                'creatable',
                'media',
            ]);
    }

    public function store(FormRequest $request): Blog
    {
        return DB::transaction(static function () use ($request) {
            $blog = Blog::createWithLangs($request->validated());

            FileUpload::upload($request, 'main_image', 'blogs_main', $blog);
            FileUpload::upload($request, 'hover_image', 'blogs_hover', $blog);
            FileUpload::multipleUpload($request, 'images', 'blogs_images', $blog);

            return $blog;
        });
    }

    public function update(Blog $blog, FormRequest $request): Blog
    {
        return DB::transaction(static function () use ($blog, $request) {
            $blog->updateWithLangs($request->validated());

            FileUpload::upload($request, 'main_image', 'blogs_main', $blog);
            FileUpload::upload($request, 'hover_image', 'blogs_hover', $blog);
            FileUpload::multipleUpload($request, 'images', 'blogs_images', $blog);

            return $blog;
        });
    }

    public function destroy(Blog $blog): void
    {
        DB::transaction(static function () use ($blog) {
            $blog->delete();
        });
    }

    public function deleteImage(Blog $blog, int $mediaId): void
    {
        DB::transaction(static function () use ($blog, $mediaId) {
            $blog->deleteMedia($mediaId);
        });
    }

    public function changeActiveStatus(Blog $blog): void
    {
        DB::transaction(function () use ($blog) {
            $blog->is_active = !$blog->is_active;
            $blog->save();
        });
    }

    public function changeShowHomePage(Blog $blog): void
    {
        DB::transaction(function () use ($blog) {
            $blog->show_home_page = !$blog->show_home_page;
            $blog->save();
        });
    }

    public function changeSorting(Blog $blog, int $sort): void
    {
        $blog->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $blogs = $validated['items'];

        $blogsCount = Blog::query()->whereIn('id', data_get($blogs, '*.id'))->count();

        if ($blogsCount !== count($blogs)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($blogs) {
            foreach ($blogs as $blogData) {
                Blog::query()->where('id', $blogData['id'])->update(['sort' => $blogData['sort']]);
            }
        });
    }

    public function listForSite(int $limit): Collection
    {
        return Blog::query()
            ->active()
            ->showHomePage()
            ->with([
                'translatable',
                'media',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function loadForSite(array $validated): LengthAwarePaginator
    {
        return Blog::query()
            ->active()
            ->with([
                'translatable',
                'media',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function showBySlugForSite(string $slug): Blog
    {
        $blog = Blog::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'translatable',
                'media',
            ])
            ->firstOrFail();

        $blog->increment('view_count');

        return $blog;
    }
}
