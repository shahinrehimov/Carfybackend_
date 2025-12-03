<?php

namespace App\Repositories;

use App\Models\Social;
use App\Services\FileUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SocialRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Social::query()
            ->with([
                'media',
                'creatable',
            ])
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return Social::query()
            ->orderBy('id')
            ->get();
    }

    public function show(Social $social): Social
    {
        return $social->load([
            'media',
        ]);
    }

    public function loadRelations(Social $social): Social
    {
        return $social->load([
            'media',
            'creatable',
        ]);
    }

    public function store(FormRequest $request): Social
    {
        return DB::transaction(static function () use ($request) {
            $validated = $request->validated();

            $social = Social::query()->create($validated);

            FileUpload::upload($request, 'image', 'socials', $social);

            return $social;
        });
    }

    public function update(Social $social, FormRequest $request): Social
    {
        return DB::transaction(static function () use ($social, $request) {
            $validated = $request->validated();

            $social->update($validated);

            FileUpload::upload($request, 'image', 'socials', $social);
        });
    }

    public function destroy(Social $social): void
    {
        DB::transaction(static function () use ($social) {
            $social->delete();
        });
    }
}
