<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $token = 'development-token-test-1907-fb';

        DB::table('api_tokens')->insert([
            'name' => 'admin',
            'token' => config('app.debug') ? $token : Str::random(50),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('api_tokens')->insert([
            'name' => 'site',
            'token' => config('app.debug') ? $token : Str::random(50),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('api_tokens')->insert([
            'name' => 'app',
            'token' => config('app.debug') ? $token : Str::random(50),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
