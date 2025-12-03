<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return User::query()
            ->withCount([
                'addresses',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->where(function (Builder $query) use ($validated) {
                        $query->where('full_name', 'ILIKE', '%' . $validated['search'] . '%');
                        $query->orWhere('email', 'ILIKE', '%' . $validated['search'] . '%');
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(): Collection
    {
        return User::query()
            ->active()
            ->orderBy('full_name')
            ->get();
    }

    public function show(User $user): User
    {
        $user->loadCount([
            'addresses',
        ]);

        return $user;
    }

    public function loadRelations(User $user): User
    {
        return $user
            ->loadCount([
                'addresses',
            ]);
    }

    public function destroy(User $user): void
    {
        DB::transaction(static function () use ($user) {
            $user->delete();
        });
    }

    public function changeActiveStatus(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->is_active = !$user->is_active;
            $user->save();
        });
    }
}
