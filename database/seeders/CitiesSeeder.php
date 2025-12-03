<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Services\LangService;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $country = Country::query()->firstOrFail();

        City::query()->truncate();

        $cities = [
            'Akhalkalaki',
            'Akhaltsikhe',
            'Ambrolauri',
            'Batumi',
            'Bolnisi',
            'Chiatura',
            'Dedoplistskaro',
            'Dusheti',
            'Gardabani',
            'Gori',
            'Kaspi',
            'Khashuri',
            'Khoni',
            'Kobuleti',
            'Kutaisi',
            'Lanchkhuti',
            'Marneuli',
            'Mtskheta',
            'Ninotsminda',
            'Ozurgeti',
            'Poti',
            'Rustavi',
            'Sagarejo',
            'Samtredia',
            'Senaki',
            'Stepantsminda',
            'Telavi',
            'Tianeti',
            'Tbilisi',
            'Tskaltubo',
            'Zestaponi',
            'Zugdidi'
        ];

        foreach ($cities as $city) {
            $data = [];
            foreach (LangService::instance()->getLanguages() as $lang) {
                $data[] = [
                    'title' => $city,
                    'language_id' => $lang['id'],
                ];
            }

            City::createWithLangs([
                'country_id' => $country->id,
                'translations' => $data,
            ]);
        }
    }
}
