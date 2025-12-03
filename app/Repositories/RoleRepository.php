<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function list(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }
}
