<?php

namespace App\Services;

use App\Models\CompanyProductVariant;

class ProductSearchService
{
    private static ?ProductSearchService $instance = null;

    /**
     * Max number of relevance-ordered ids fetched from Elasticsearch. The
     * PostgreSQL query then applies the remaining facet filters + pagination
     * over this candidate set.
     */
    private const RESULT_CAP = 1000;

    public static function instance(): ProductSearchService
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Run a full-text search and return matching CompanyProductVariant ids
     * ordered by relevance (most relevant first).
     *
     * @return array<int, int>
     */
    public function searchCompanyProductVariantIds(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return CompanyProductVariant::searchQuery($this->buildQuery($term))
            ->size(self::RESULT_CAP)
            ->execute()
            ->documents()
            ->map(static fn ($document) => (int) $document->id())
            ->all();
    }

    /**
     * Re-index every CompanyProductVariant belonging to the given products.
     * Used when denormalised content (product text, brand, category) changes.
     * Active/approved listings are (re)indexed; the rest are removed.
     *
     * @param  array<int, int>  $productIds
     */
    public function reindexForProductIds(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $listings = CompanyProductVariant::query()
            ->whereHas('productVariant', fn ($query) => $query->whereIn('product_id', $productIds))
            ->with([
                'productVariant',
                'productVariant.product.translatable',
                'productVariant.product.brand.translatable',
                'productVariant.product.categories.translatable',
            ])
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        [$searchable, $notSearchable] = $listings->partition->shouldBeSearchable();

        $searchable->searchable();
        $notSearchable->unsearchable();
    }

    /**
     * Build a multi_match query. Current-language fields are boosted highest,
     * the other languages still match (cross-language fallback). Fuzziness
     * gives typo tolerance.
     *
     * @return array<string, mixed>
     */
    private function buildQuery(string $term): array
    {
        $lang = LangService::instance()->getCurrentLang();

        $textFields = [
            // Current language — highest boost.
            "title_{$lang}^6",
            "brand_{$lang}^4",
            'oem_code^4',
            "model_{$lang}^4",
            "categories_{$lang}^2",
            "options_{$lang}^2",
            "description_short_{$lang}^2",
            "description_{$lang}",
            // Other languages — lower boost, wildcard fallback.
            'title_*^2',
            'brand_*',
            'model_*',
            'categories_*',
            'options_*',
        ];

        return [
            'bool' => [
                'should' => [
                    // Fuzzy text match across all language fields.
                    [
                        'multi_match' => [
                            'query' => $term,
                            'type' => 'best_fields',
                            'fields' => $textFields,
                            'fuzziness' => 'AUTO',
                            'operator' => 'or',
                        ],
                    ],
                    // Exact year token match (no fuzziness, so 2015 ≠ 2016).
                    [
                        'match' => [
                            'year' => ['query' => $term],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
