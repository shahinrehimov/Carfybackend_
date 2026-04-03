<?php

namespace Database\Seeders;

use App\Models\CarColor;
use App\Services\LangService;
use Illuminate\Database\Seeder;

class CarColorsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['hex' => '#FFFFFF', 'az' => 'Ağ',          'en' => 'White',        'ru' => 'Белый'],
            ['hex' => '#000000', 'az' => 'Qara',         'en' => 'Black',        'ru' => 'Чёрный'],
            ['hex' => '#C0C0C0', 'az' => 'Gümüşü',      'en' => 'Silver',       'ru' => 'Серебристый'],
            ['hex' => '#808080', 'az' => 'Boz',          'en' => 'Gray',         'ru' => 'Серый'],
            ['hex' => '#CC0000', 'az' => 'Qırmızı',     'en' => 'Red',          'ru' => 'Красный'],
            ['hex' => '#003399', 'az' => 'Mavi',         'en' => 'Blue',         'ru' => 'Синий'],
            ['hex' => '#336633', 'az' => 'Yaşıl',        'en' => 'Green',        'ru' => 'Зелёный'],
            ['hex' => '#CC6600', 'az' => 'Narıncı',     'en' => 'Orange',       'ru' => 'Оранжевый'],
            ['hex' => '#FFCC00', 'az' => 'Sarı',         'en' => 'Yellow',       'ru' => 'Жёлтый'],
            ['hex' => '#8B4513', 'az' => 'Qəhvəyi',     'en' => 'Brown',        'ru' => 'Коричневый'],
            ['hex' => '#800080', 'az' => 'Bənövşəyi',   'en' => 'Purple',       'ru' => 'Фиолетовый'],
            ['hex' => '#FFD1DC', 'az' => 'Çəhrayı',     'en' => 'Pink',         'ru' => 'Розовый'],
            ['hex' => '#F5E6CA', 'az' => 'Krem / Bej',  'en' => 'Cream / Beige','ru' => 'Кремовый / Бежевый'],
            ['hex' => '#B8860B', 'az' => 'Qızılı',      'en' => 'Gold',         'ru' => 'Золотой'],
            ['hex' => '#36454F', 'az' => 'Antrasit',    'en' => 'Anthracite',   'ru' => 'Антрацит'],
        ];

        foreach ($items as $sort => $item) {
            CarColor::createWithLangs([
                'hex_code'     => $item['hex'],
                'sort'         => $sort,
                'translations' => $this->buildTranslations($item),
            ]);
        }
    }

    private function buildTranslations(array $item): array
    {
        $langKeys  = ['az', 'en', 'ru'];
        $languages = LangService::instance()->getLanguages();

        $translations = [];
        foreach ($languages as $index => $lang) {
            $key = $langKeys[$index] ?? 'az';
            $translations[] = [
                'language_id' => $lang['id'],
                'title'       => $item[$key] ?? $item['az'],
            ];
        }

        return $translations;
    }
}
