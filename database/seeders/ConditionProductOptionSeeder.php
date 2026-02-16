<?php

namespace Database\Seeders;

use App\Models\ProductOption;
use App\Models\ProductOptionItem;
use App\Repositories\ProductOptionRepository;
use App\Services\LangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ConditionProductOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [];
        foreach (LangService::instance()->getLanguages() as $lang) {
            $data[] = [
                'title' => 'Condition',
                'language_id' => $lang['id'],
            ];
        }

        $option = $this->storeOption([
            'translations' => $data,
            'is_required' => true,
            'has_items' => true,
            'is_translatable' => false,
            'can_multiple' => false,
        ]);

        $this->storeOptionItems($option);
    }

    private function storeOption(array $validated): ProductOption
    {
        $defaultTitle = ProductOption::getDefaultText($validated['translations'] ?? []);

        if ($defaultTitle) {
            $validated['name'] = (new ProductOptionRepository())->generateName($defaultTitle);
        }

        $validated['uuid'] = Str::uuid();

        return ProductOption::createWithLangs($validated);
    }

    private function storeOptionItems(ProductOption $productOption): void
    {
        $items = [
            'New',
            'Used',
            'Refurbished',
            'Open box',
            'Like new',
        ];

        foreach ($items as $sort => $item) {
            $data = [];
            foreach (LangService::instance()->getLanguages() as $lang) {
                $data[] = [
                    'title' => $item,
                    'language_id' => $lang['id'],
                ];
            }

            ProductOptionItem::createWithLangs([
                'product_option_id' => $productOption->id,
                'sort' => $sort + 1,
                'uuid' => Str::uuid(),
                'is_active' => true,
                'translations' => $data,
            ]);
        }
    }
}
