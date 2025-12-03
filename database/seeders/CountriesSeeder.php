<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Services\LangService;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Country::query()->truncate();

        $countries = [
            'Azerbaijan',
        ];

        foreach ($countries as $country) {
            $data = [];
            foreach (LangService::instance()->getLanguages() as $lang) {
                $data[] = [
                    'title' => $country,
                    'language_id' => $lang['id'],
                ];
            }

            Country::createWithLangs([
                'translations' => $data,
            ]);
        }
    }
}
