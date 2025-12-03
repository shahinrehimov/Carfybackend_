<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class DefaultLanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Language::query()->create([
            'title' => 'Az',
            'key' => 'az',
        ]);

        Language::query()->create([
            'title' => 'En',
            'key' => 'en',
        ]);

        Language::query()->create([
            'title' => 'Ru',
            'key' => 'ru',
        ]);
    }
}
