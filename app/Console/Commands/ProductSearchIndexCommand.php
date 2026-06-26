<?php

namespace App\Console\Commands;

use App\Models\CompanyProductVariant;
use Elastic\Adapter\Indices\IndexManager;
use Illuminate\Console\Command;

class ProductSearchIndexCommand extends Command
{
    protected $signature = 'product:search-index
                            {--fresh : Drop the index first, then recreate it}
                            {--delete : Drop the index and exit}';

    protected $description = 'Create (or recreate) the Elasticsearch index used for product search';

    /**
     * Custom analyzer: lowercase + asciifolding so AZ/RU diacritics
     * (ə, ı, ö, ü, ş, ç, ğ, …) match their folded forms.
     */
    private const SETTINGS = [
        'analysis' => [
            'analyzer' => [
                'carfy_text' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'asciifolding'],
                ],
            ],
            'normalizer' => [
                'carfy_keyword' => [
                    'type' => 'custom',
                    'filter' => ['lowercase', 'asciifolding'],
                ],
            ],
        ],
    ];

    public function handle(IndexManager $indexManager): int
    {
        $index = (new CompanyProductVariant)->searchableAs();

        if ($indexManager->exists($index) && ($this->option('fresh') || $this->option('delete'))) {
            $indexManager->drop($index);
            $this->warn("Dropped index [{$index}].");

            if ($this->option('delete')) {
                return self::SUCCESS;
            }
        }

        if ($indexManager->exists($index)) {
            $this->info("Index [{$index}] already exists. Use --fresh to recreate it.");

            return self::SUCCESS;
        }

        $settings = self::SETTINGS;
        $settings['index']['number_of_replicas'] = (int) config('elastic.client.index_replicas', 1);

        $indexManager->createRaw($index, ['properties' => $this->mappingProperties()], $settings);

        $this->info("Created index [{$index}].");
        $this->line('Next: php artisan scout:import "App\\Models\\CompanyProductVariant"');

        return self::SUCCESS;
    }

    /**
     * Field mappings. All searchable text uses the carfy_text analyzer; the
     * oem_code also keeps a normalized keyword sub-field for exact lookups.
     *
     * @return array<string, mixed>
     */
    private function mappingProperties(): array
    {
        $text = ['type' => 'text', 'analyzer' => 'carfy_text'];

        $properties = [];

        // Per-language text fields (az/en/ru).
        foreach (['title', 'description_short', 'description', 'brand', 'categories', 'model', 'options'] as $field) {
            foreach (['az', 'en', 'ru'] as $lang) {
                $properties["{$field}_{$lang}"] = $text;
            }
        }

        // Variant year — analyzed as a single token for exact match.
        $properties['year'] = $text;

        // OEM code: analyzed for partial matches + keyword for exact lookups.
        $properties['oem_code'] = [
            'type' => 'text',
            'analyzer' => 'carfy_text',
            'fields' => [
                'keyword' => ['type' => 'keyword', 'normalizer' => 'carfy_keyword'],
            ],
        ];

        return $properties;
    }
}
