<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $role = Role::query()->firstOrCreate(["name" => RoleEnum::ADMIN, "guard_name" => "admin"]);

        $admin = Admin::query()->firstOrCreate(
            [
                'username' => config('app.superadmin.username'),
            ],
            [
                'email' => config('app.superadmin.email'),
                'name' => config('app.superadmin.name'),
                'surname' => config('app.superadmin.surname'),
                'password' => config('app.superadmin.password'),
            ]
        );

        $admin->assignRole($role);
    }
}
