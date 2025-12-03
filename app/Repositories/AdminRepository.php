<?php

namespace App\Repositories;

use App\Models\Admin;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AdminRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return Admin::query()
            //->where('email', '!=', config('app.superadmin.email'))
            ->with([
                'creatable',
                'media',
                'roles',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereLike('name', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('surname', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('email', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('username', '%' . $validated['search'] . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(?string $role = null): Collection
    {
        return Admin::query()
            ->when($role, function (Builder $query) use ($role) {
                $query->whereHas('roles', function (Builder $query) use ($role) {
                    $query->where('name', $role);
                });
            })
            ->orderBy('name')
            ->select([
                'id',
                'name',
                'surname',
            ])
            ->get();
    }

    public function show(Admin $admin): void
    {
        if ($admin->email === config('app.superadmin.email') && auth('admin')->user()->email !== config('app.superadmin.email')) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot delete the super admin!')
                    ->getLang('you_cannot_delete_the_super_admin')
            );
        }

        $admin->load([
            'roles',
            'media',
        ]);
    }

    public function store(FormRequest $request): Admin
    {
        $validated = $request->validated();

        $roles = $validated['roles'] ?? [];
        unset($validated['roles']);

        return DB::transaction(static function () use ($validated, $roles, $request) {
            $admin = Admin::query()->create($validated);

            $admin->roles()->sync($roles);

            FileUpload::upload($request, 'image', 'admins', $admin);

            return $admin;
        });
    }

    public function update(Admin $admin, FormRequest $request): Admin
    {
        if ($admin->email === config('app.superadmin.email') && auth('admin')->user()->email !== config('app.superadmin.email')) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot delete the super admin!')
                    ->getLang('you_cannot_delete_the_super_admin')
            );
        }

        $validated = $request->validated();

        $roles = $validated['roles'] ?? [];
        unset($validated['roles']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        return DB::transaction(static function () use ($validated, $roles, $admin, $request) {
            $admin->update($validated);

            $admin->roles()->sync($roles);

            FileUpload::upload($request, 'image', 'admins', $admin);

            return $admin;
        });
    }

    public function destroy(Admin $admin): void
    {
        if ($admin->email === config('app.superadmin.email')) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot delete the super admin!')
                    ->getLang('you_cannot_delete_the_super_admin')
            );
        }

        DB::transaction(static function () use ($admin) {
            $admin->delete();
        });
    }
}
