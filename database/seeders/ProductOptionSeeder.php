<?php

namespace Database\Seeders;

use App\Models\ProductOption;
use App\Repositories\ProductOptionRepository;
use App\Services\LangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $options = [
            [
                'title' => 'Color',
                'is_required' => true,
                'has_items' => true,
                'is_translatable' => false,
                'can_multiple' => false,
            ],
            [
                'title' => 'Size',
                'is_required' => true,
                'has_items' => true,
                'is_translatable' => false,
                'can_multiple' => false,
            ],
            [
                'title' => 'Material',
                'is_required' => false,
                'has_items' => true,
                'is_translatable' => false,
                'can_multiple' => true,
            ],
        ];

        foreach ($options as $option) {
            $data = [];
            foreach (LangService::instance()->getLanguages() as $lang) {
                $data[] = [
                    'title' => $option['title'],
                    'language_id' => $lang['id'],
                ];
            }

            $this->store([
                'translations' => $data,
                'is_required' => $option['is_required'] ?? false,
                'has_items' => $option['has_items'] ?? false,
                'is_translatable' => $option['is_translatable'] ?? false,
                'can_multiple' => $option['can_multiple'] ?? false,
            ]);
        }
    }

    private function store(array $validated): void
    {
        $defaultTitle = ProductOption::getDefaultText($validated['translations'] ?? []);

        if ($defaultTitle) {
            $validated['name'] = (new ProductOptionRepository())->generateName($defaultTitle);
        }

        $validated['uuid'] = Str::uuid();

        ProductOption::createWithLangs($validated);
    }
}
