<?php

namespace Database\Seeders;

use App\Models\ProductOption;
use App\Models\ProductOptionItem;
use App\Repositories\ProductOptionRepository;
use App\Services\LangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ColorProductOptionSeeder extends Seeder
{
    public function run(): void
    {
        $option = $this->findOrCreateOption();

        if (ProductOptionItem::query()->forOption('color')->count() > 0) {
            return;
        }

        $this->seedItems($option);
    }

    private function findOrCreateOption(): ProductOption
    {
        $existing = ProductOption::query()->where('name', 'color')->first();

        if ($existing) {
            return $existing;
        }

        $translations = [];
        foreach (LangService::instance()->getLanguages() as $lang) {
            $translations[] = [
                'language_id' => $lang['id'],
                'title'       => 'Color',
            ];
        }

        return ProductOption::createWithLangs([
            'uuid'            => Str::uuid(),
            'name'            => 'color',
            'is_required'     => false,
            'has_items'       => true,
            'is_translatable' => false,
            'can_multiple'    => false,
            'is_in_title'     => false,
            'is_active'       => true,
            'translations'    => $translations,
        ]);
    }

    private function seedItems(ProductOption $option): void
    {
        $colors = [
            ['hex' => '#FFFFFF', 'az' => 'Ağ',           'en' => 'White',          'ru' => 'Белый'],
            ['hex' => '#000000', 'az' => 'Qara',          'en' => 'Black',          'ru' => 'Чёрный'],
            ['hex' => '#C0C0C0', 'az' => 'Gümüşü',       'en' => 'Silver',         'ru' => 'Серебристый'],
            ['hex' => '#808080', 'az' => 'Boz',           'en' => 'Gray',           'ru' => 'Серый'],
            ['hex' => '#CC0000', 'az' => 'Qırmızı',      'en' => 'Red',            'ru' => 'Красный'],
            ['hex' => '#003399', 'az' => 'Mavi',          'en' => 'Blue',           'ru' => 'Синий'],
            ['hex' => '#336633', 'az' => 'Yaşıl',         'en' => 'Green',          'ru' => 'Зелёный'],
            ['hex' => '#CC6600', 'az' => 'Narıncı',      'en' => 'Orange',         'ru' => 'Оранжевый'],
            ['hex' => '#FFCC00', 'az' => 'Sarı',          'en' => 'Yellow',         'ru' => 'Жёлтый'],
            ['hex' => '#8B4513', 'az' => 'Qəhvəyi',      'en' => 'Brown',          'ru' => 'Коричневый'],
            ['hex' => '#800080', 'az' => 'Bənövşəyi',    'en' => 'Purple',         'ru' => 'Фиолетовый'],
            ['hex' => '#FFD1DC', 'az' => 'Çəhrayı',      'en' => 'Pink',           'ru' => 'Розовый'],
            ['hex' => '#F5E6CA', 'az' => 'Krem / Bej',   'en' => 'Cream / Beige',  'ru' => 'Кремовый / Бежевый'],
            ['hex' => '#B8860B', 'az' => 'Qızılı',       'en' => 'Gold',           'ru' => 'Золотой'],
            ['hex' => '#36454F', 'az' => 'Antrasit',     'en' => 'Anthracite',     'ru' => 'Антрацит'],
        ];

        $langKeys  = ['az', 'en', 'ru'];
        $languages = LangService::instance()->getLanguages();

        foreach ($colors as $sort => $color) {
            $translations = [];
            foreach ($languages as $index => $lang) {
                $key = $langKeys[$index] ?? 'az';
                $translations[] = [
                    'language_id' => $lang['id'],
                    'title'       => $color[$key] ?? $color['az'],
                ];
            }

            ProductOptionItem::createWithLangs([
                'product_option_id' => $option->id,
                'uuid'              => Str::uuid(),
                'details'           => ['hex_code' => $color['hex']],
                'sort'              => $sort + 1,
                'is_active'         => true,
                'translations'      => $translations,
            ]);
        }
    }
}
