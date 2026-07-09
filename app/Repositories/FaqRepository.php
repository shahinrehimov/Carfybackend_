<?php

namespace App\Repositories;

use App\Models\Faq;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FaqRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Faq::query()
            ->with(['translatable', 'creatable'])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereHas('translatable', function (Builder $query) use ($validated) {
                    $query->whereIn('column', ['question', 'answer'])
                        ->where('text', 'ILIKE', '%' . $validated['search'] . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Faq::query()
            ->active()
            ->with(['translatable'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public function show(Faq $faq): Faq
    {
        return $faq;
    }

    public function loadRelations(Faq $faq): Faq
    {
        return $faq->load(['translatable', 'creatable']);
    }

    public function store(FormRequest $request): Faq
    {
        return DB::transaction(static function () use ($request) {
            return Faq::createWithLangs($request->validated());
        });
    }

    public function update(Faq $faq, FormRequest $request): Faq
    {
        return DB::transaction(static function () use ($faq, $request) {
            $faq->updateWithLangs($request->validated());
            return $faq;
        });
    }

    public function destroy(Faq $faq): void
    {
        DB::transaction(static function () use ($faq) {
            $faq->delete();
        });
    }

    public function changeActiveStatus(Faq $faq): void
    {
        DB::transaction(function () use ($faq) {
            $faq->is_active = !$faq->is_active;
            $faq->save();
        });
    }

    public function changeSorting(Faq $faq, int $sort): void
    {
        $faq->update(['sort' => $sort]);
    }

    public function changeSortingMultiple(array $validated): void
    {
        $items = $validated['items'];

        $count = Faq::query()->whereIn('id', data_get($items, '*.id'))->count();

        if ($count !== count($items)) {
            throw new BadRequestHttpException(
                LangService::instance()->setDefault('Invalid data!')->getLang('admin_invalid_data')
            );
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Faq::query()->where('id', $item['id'])->update(['sort' => $item['sort']]);
            }
        });
    }
}
