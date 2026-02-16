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
        City::query()->truncate();

        $country = Country::query()->first();

        $cities = [
            'Bakı',
            'Ağcabədi',
            'Ağdam',
            'Ağdaş',
            'Ağstafa',
            'Ağsu',
            'Astara',
            'Balakən',
            'Bərdə',
            'Beyləqan',
            'Biləsuvar',
            'Cəbrayıl',
            'Cəlilabad',
            'Daşkəsən',
            'Füzuli',
            'Gədəbəy',
            'Gəncə',
            'Goranboy',
            'Göyçay',
            'Göygöl',
            'Hacıqabul',
            'Xaçmaz',
            'Xızı',
            'Xocavənd',
            'İmişli',
            'İsmayıllı',
            'Kəlbəcər',
            'Kürdəmir',
            'Qax',
            'Qazax',
            'Qəbələ',
            'Qobustan',
            'Quba',
            'Qubadlı',
            'Qusar',
            'Laçın',
            'Lənkəran',
            'Lerik',
            'Masallı',
            'Mingəçevir',
            'Naftalan',
            'Naxçıvan',
            'Neftçala',
            'Oğuz',
            'Saatlı',
            'Sabirabad',
            'Salyan',
            'Samux',
            'Siyəzən',
            'Sumqayıt',
            'Şamaxı',
            'Şəki',
            'Şəmkir',
            'Şirvan',
            'Şuşa',
            'Tərtər',
            'Tovuz',
            'Ucar',
            'Yardımlı',
            'Zaqatala',
            'Zəngilan',
            'Zərdab',
        ];

        foreach ($cities as $sort => $city) {
            $data = [];
            foreach (LangService::instance()->getLanguages() as $lang) {
                $data[] = [
                    'title' => $city,
                    'language_id' => $lang['id'],
                ];
            }

            City::createWithLangs([
                'country_id' => $country->id,
                'sort' => $sort + 1,
                'is_active' => true,
                'translations' => $data,
            ]);
        }
    }
}
