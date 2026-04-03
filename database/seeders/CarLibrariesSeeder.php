<?php

namespace Database\Seeders;

use App\Models\CarColor;
use App\Models\CarType;
use App\Models\EngineVolume;
use App\Models\FuelType;
use App\Models\TransmissionType;
use App\Services\LangService;
use Illuminate\Database\Seeder;

class CarLibrariesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFuelTypes();
        $this->seedTransmissionTypes();
        $this->seedCarTypes();
        $this->seedEngineVolumes();
    }

    private function seedFuelTypes(): void
    {
        $items = [
            ['az' => 'Benzin',         'en' => 'Gasoline',       'ru' => 'Бензин'],
            ['az' => 'Premium benzin', 'en' => 'Premium gasoline','ru' => 'Премиум бензин'],
            ['az' => 'Dizel',          'en' => 'Diesel',         'ru' => 'Дизель'],
            ['az' => 'Qaz (LPG)',      'en' => 'Gas (LPG)',      'ru' => 'Газ (LPG)'],
            ['az' => 'Elektrik',       'en' => 'Electric',       'ru' => 'Электро'],
            ['az' => 'Hibrid',         'en' => 'Hybrid',         'ru' => 'Гибрид'],
        ];

        foreach ($items as $sort => $titles) {
            FuelType::createWithLangs([
                'sort'         => $sort,
                'translations' => $this->buildTranslations($titles),
            ]);
        }
    }

    private function seedTransmissionTypes(): void
    {
        $items = [
            ['az' => 'Mexaniki',  'en' => 'Manual',      'ru' => 'Механика'],
            ['az' => 'Avtomatik', 'en' => 'Automatic',   'ru' => 'Автомат'],
            ['az' => 'Variator',  'en' => 'CVT',         'ru' => 'Вариатор'],
            ['az' => 'Robot',     'en' => 'Robot (DCT)', 'ru' => 'Робот (DCT)'],
        ];

        foreach ($items as $sort => $titles) {
            TransmissionType::createWithLangs([
                'sort'         => $sort,
                'translations' => $this->buildTranslations($titles),
            ]);
        }
    }

    private function seedCarTypes(): void
    {
        $items = [
            ['az' => 'Sedan',         'en' => 'Sedan',       'ru' => 'Седан'],
            ['az' => 'Universal',     'en' => 'Wagon',       'ru' => 'Универсал'],
            ['az' => 'Hetçbek',       'en' => 'Hatchback',   'ru' => 'Хэтчбэк'],
            ['az' => 'Kupe',          'en' => 'Coupe',       'ru' => 'Купе'],
            ['az' => 'Kabriolet',     'en' => 'Convertible', 'ru' => 'Кабриолет'],
            ['az' => 'SUV / Krossover','en' => 'SUV / Crossover','ru' => 'Кроссовер / SUV'],
            ['az' => 'Pikap',         'en' => 'Pickup',      'ru' => 'Пикап'],
            ['az' => 'Mikroavtobus',  'en' => 'Minivan',     'ru' => 'Минивэн'],
            ['az' => 'Yük avtomobili','en' => 'Truck',       'ru' => 'Грузовик'],
            ['az' => 'Sport / Supercar','en' => 'Sport / Supercar','ru' => 'Спорт / Суперкар'],
        ];

        foreach ($items as $sort => $titles) {
            CarType::createWithLangs([
                'sort'         => $sort,
                'translations' => $this->buildTranslations($titles),
            ]);
        }
    }

    private function seedEngineVolumes(): void
    {
        $volumes = [
            ['liters' => 0.8,  'cc' => 800],
            ['liters' => 1.0,  'cc' => 1000],
            ['liters' => 1.2,  'cc' => 1200],
            ['liters' => 1.4,  'cc' => 1400],
            ['liters' => 1.6,  'cc' => 1600],
            ['liters' => 1.8,  'cc' => 1800],
            ['liters' => 2.0,  'cc' => 2000],
            ['liters' => 2.5,  'cc' => 2500],
            ['liters' => 3.0,  'cc' => 3000],
            ['liters' => 3.2,  'cc' => 3200],
            ['liters' => 3.5,  'cc' => 3500],
            ['liters' => 4.0,  'cc' => 4000],
            ['liters' => 4.4,  'cc' => 4400],
            ['liters' => 5.0,  'cc' => 5000],
            ['liters' => 6.0,  'cc' => 6000],
        ];

        foreach ($volumes as $sort => $volume) {
            $label = $volume['liters'] . 'L (' . number_format($volume['cc'], 0, '.', ',') . ' sm³)';

            EngineVolume::createWithLangs([
                'value_liters' => $volume['liters'],
                'value_cc'     => $volume['cc'],
                'sort'         => $sort,
                'translations' => $this->buildTranslations([
                    'az' => $label,
                    'en' => $label,
                    'ru' => $label,
                ]),
            ]);
        }
    }

    private function buildTranslations(array $titles): array
    {
        $langKeys = ['az', 'en', 'ru'];
        $languages = LangService::instance()->getLanguages();

        $translations = [];
        foreach ($languages as $index => $lang) {
            $key = $langKeys[$index] ?? 'az';
            $translations[] = [
                'language_id' => $lang['id'],
                'title'       => $titles[$key] ?? $titles['az'],
            ];
        }

        return $translations;
    }
}
