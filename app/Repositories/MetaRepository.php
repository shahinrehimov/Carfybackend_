<?php

namespace App\Repositories;

use App\Models\Meta;
use App\Services\MetaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MetaRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Meta::query()
            ->with([
                'translatable',
                'creatable',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Meta::query()
            ->with([
                'translatable',
            ])
            ->orderBy('page_title')
            ->get();
    }

    public function loadRelations(Meta $meta): Meta
    {
        return $meta->load([
            'translatable',
            'creatable',
        ]);
    }

    public function store(array $validated): Meta
    {
        return DB::transaction(static function () use ($validated) {
            $meta = Meta::createWithLangs($validated);

            MetaService::instance()->setCache($meta->page_key);

            return $meta;
        });
    }

    public function update(Meta $meta, array $validated): Meta
    {
        return DB::transaction(static function () use ($meta, $validated) {
            $meta->updateWithLangs($validated);

            MetaService::instance()->setCache($meta->page_key);

            return $meta;
        });
    }

    public function destroy(Meta $meta): void
    {
        DB::transaction(static function () use ($meta) {
            $pageKey = $meta->page_key;
            $meta->delete();

            MetaService::instance()->clearCache($pageKey);
        });
    }
}
