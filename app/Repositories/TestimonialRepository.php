<?php

namespace App\Repositories;

use App\Models\Testimonial;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TestimonialRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Testimonial::query()
            ->with([
                'translatable',
                'creatable',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                        $query->where(function (Builder $q) use ($validated) {
                            $q->where('column', 'message');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                        $query->orWhere(function (Builder $q) use ($validated) {
                            $q->where('column', 'user');
                            $q->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                        });
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(?int $limit = null): Collection
    {
        return Testimonial::query()
            ->active()
            ->with([
                'translatable',
            ])
            ->orderBy('sort')
            ->orderByDesc('id')
            ->when($limit, function (Builder $builder) use ($limit) {
                $builder->limit($limit);
            })
            ->get();
    }

    public function show(Testimonial $testimonial): Testimonial
    {
        return $testimonial;
    }

    public function loadRelations(Testimonial $testimonial): Testimonial
    {
        return $testimonial
            ->load([
                'translatable',
                'creatable',
            ]);
    }

    public function store(FormRequest $request): Testimonial
    {
        return DB::transaction(static function () use ($request) {
            return Testimonial::createWithLangs($request->validated());
        });
    }

    public function update(Testimonial $testimonial, FormRequest $request): Testimonial
    {
        return DB::transaction(static function () use ($testimonial, $request) {
            $testimonial->updateWithLangs($request->validated());

            return $testimonial;
        });
    }

    public function destroy(Testimonial $testimonial): void
    {
        DB::transaction(static function () use ($testimonial) {
            $testimonial->delete();
        });
    }

    public function changeActiveStatus(Testimonial $testimonial): void
    {
        DB::transaction(function () use ($testimonial) {
            $testimonial->is_active = !$testimonial->is_active;
            $testimonial->save();
        });
    }

    public function changeSorting(Testimonial $testimonial, int $sort): void
    {
        $testimonial->update([
            'sort' => $sort,
        ]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $testimonials = $validated['items'];

        $testimonialsCount = Testimonial::query()->whereIn('id', data_get($testimonials, '*.id'))->count();

        if ($testimonialsCount !== count($testimonials)) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid data!')
                    ->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($testimonials) {
            foreach ($testimonials as $testimonialData) {
                Testimonial::query()->where('id', $testimonialData['id'])->update(['sort' => $testimonialData['sort']]);
            }
        });
    }
}
