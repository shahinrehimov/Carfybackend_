<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ApiTokenSeeder::class,
            DefaultLanguageSeeder::class,
            RolesSeeder::class,
            SuperAdminSeeder::class,
            CountriesSeeder::class,
            CitiesSeeder::class,
            ConditionProductOptionSeeder::class,
            ColorProductOptionSeeder::class,
            BrandsSeeder::class,
            CarLibrariesSeeder::class,
            DashboardTranslationsSeeder::class,
            DefaultCommissionPercentSeeder::class,
        ]);
    }
}
