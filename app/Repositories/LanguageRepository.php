<?php

namespace App\Repositories;

use App\Models\Language;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LanguageRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Language::query()
            ->with([
                'creatable',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->whereLike('title', '%' . $validated['search'] . '%');
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Language::query()->orderBy('title')->get();
    }

    public function loadRelations(Language $language): void
    {
        $language
            ->load([
                'creatable',
            ]);
    }

    public function store(array $validated): Language
    {
        return DB::transaction(static function () use ($validated) {
            return Language::query()->create($validated);
        });
    }

    public function update(Language $language, array $validated): Language
    {
        return DB::transaction(static function () use ($validated, $language) {
            $language->update($validated);

            return $language;
        });
    }

    public function destroy(Language $language): void
    {
        DB::transaction(static function () use ($language) {
            $language->delete();
        });
    }

    public function changeActiveStatus(Language $language): void
    {
        $language->is_active = ! $language->is_active;
        $language->save();

        LangService::instance()->setLanguagesCache();
    }
}
