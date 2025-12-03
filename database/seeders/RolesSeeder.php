<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Role::query()->firstOrCreate(["name" => RoleEnum::ADMIN->value, "guard_name" => "admin"]);

        Role::query()->firstOrCreate(["name" => RoleEnum::MARKETING->value, "guard_name" => "admin"]);
    }
}
