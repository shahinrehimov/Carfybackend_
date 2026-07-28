<?php

namespace App\Models;

use App\Casts\CompanyProductVariants\DiscountCast;
use App\Casts\CompanyProductVariants\PriceCast;
use App\Casts\CompanyProductVariants\PriceFinalCast;
use App\Enum\ProductSizeCategoryEnum;
use App\Enum\ProductVariantApprovalStatusEnum;
use App\Services\LangService;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\HasWebImageConversions;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $company_id
 * @property mixed $product_variant_id
 * @property mixed $price
 * @property mixed $discount
 * @property mixed $discount_type
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $stock
 * @property bool $is_active
 * @property mixed $approval_status
 * @property mixed|null $approval_status_message
 * @property string|null $listing_hover_image
 * @property string|null $listing_main_image
 * @property array $listing_gallery
 * @property mixed $productVariant
 */
class CompanyProductVariant extends Model implements HasMedia
{
    use ActionBy, ActionUser, CascadeSoftDeletes, InteractsWithMedia, Searchable, SoftDeletes, HasWebImageConversions;

    /**
     * Oldest year an open-ended fitment is expanded down to when indexing. Parts
     * for cars older than this are niche enough that the year filter serves them
     * better than free-text search.
     */
    private const SEARCHABLE_YEAR_FLOOR = 1950;

    protected $table = 'company_product_variant';

    protected $fillable = [
        'uuid',
        'company_id',
        'product_variant_id',
        'stock',
        'price',
        'discount_type',
        'discount',
        'discount_start_at',
        'discount_end_at',
        'city_id',
        'size_category',
        'weight_gram',
        'is_active',
        'approval_status',
        'approval_status_message',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'price_final' => PriceFinalCast::class,
        'discount' => DiscountCast::class,
        'discount_start_at' => 'datetime',
        'discount_end_at' => 'datetime',
        'is_active' => 'boolean',
        'size_category' => ProductSizeCategoryEnum::class,
    ];

    protected array $cascadeDeletes = [
        'media',
        'approvalStatuses',
        'baskets',
        'wishlists',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('company_product_variant_images');
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerWebImageConversions(['company_product_variant_images']);
    }

    // ─── Elasticsearch (Laravel Scout) ────────────────────────────────────────

    public function searchableAs(): string
    {
        return config('scout.prefix').'company_product_variants';
    }

    /**
     * Only active, approved listings whose variant and product are active are
     * indexed — mirrors the constraints in ProductVariantRepository::loadForSite().
     */
    public function shouldBeSearchable(): bool
    {
        $this->loadMissing('productVariant.product');

        return $this->is_active
            && $this->approval_status === ProductVariantApprovalStatusEnum::APPROVED->value
            && (bool) $this->productVariant?->is_active
            && (bool) $this->productVariant?->product?->is_active;
    }

    /**
     * Eager-load the relations needed for the document during `scout:import`.
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with($this->searchableRelations());
    }

    /**
     * Relations the driver eager-loads before building each document (covers
     * both bulk import and single-model sync).
     *
     * @return array<int, string>
     */
    public function searchableWith(): array
    {
        return $this->searchableRelations();
    }

    /**
     * Denormalised, multilingual document. Text content (title, descriptions,
     * brand, categories) comes from the related product; only the text search
     * lives in Elasticsearch — all other filters stay in PostgreSQL.
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing($this->searchableRelations());

        $variant = $this->productVariant;
        $product = $variant?->product;

        $langKeyById = [];
        foreach (LangService::instance()->getLanguages() as $lang) {
            $langKeyById[$lang['id']] = $lang['key'];
        }

        $fitments = $variant?->relationLoaded('fitments') ? $variant->fitments : null;

        $document = [
            'oem_code' => $variant?->oem_code,
            'year' => $this->searchableYears($fitments),
        ];

        // Product title + descriptions per language (e.g. title_az, description_en).
        $document += $this->flattenTranslations($product, ['title', 'description_short', 'description'], $langKeyById);

        // Brand names per language (brand_az, brand_en, ...). Concatenated rather
        // than flattened because a listing can now fit several car brands.
        $document += $this->concatTranslatablesByLang(
            $fitments?->pluck('brand')->filter()->unique('id'),
            'title',
            'brand',
            $langKeyById
        );

        // Category names per language, concatenated (categories_az, ...).
        $document += $this->concatTranslatablesByLang(
            $product?->relationLoaded('categories') ? $product->categories : null,
            'title',
            'categories',
            $langKeyById
        );

        // Compatible car models per language, concatenated (model_az, ...).
        // Universal and brand-wide rows name no model and so add nothing here.
        $document += $this->concatTranslatablesByLang(
            $fitments?->pluck('brandModel')->filter()->unique('id'),
            'title',
            'model',
            $langKeyById
        );

        // Option values per language (color, condition, …), concatenated (options_az, ...).
        $document += $this->flattenOptionValues($variant, $langKeyById);

        return $document;
    }

    /**
     * Every year the listing covers, as space-separated tokens.
     *
     * The mapped `year` field is analyzed text, so expanding a range into tokens
     * keeps the existing exact-match query working — typing 2015 finds a part
     * sold as fitting 2012-2017 without the query needing range logic.
     *
     * Open-ended bounds are clamped to a plausible window; without a bound there
     * is nothing finite to expand, and a part that fits any year should still
     * turn up when someone types one.
     */
    private function searchableYears(?iterable $fitments): string
    {
        if ($fitments === null) {
            return '';
        }

        $floor = self::SEARCHABLE_YEAR_FLOOR;
        $ceiling = (int) date('Y') + 1;

        $years = [];

        foreach ($fitments as $fitment) {
            $from = max($fitment->year_from ?? $floor, $floor);
            $to = min($fitment->year_to ?? $ceiling, $ceiling);

            for ($year = $from; $year <= $to; $year++) {
                $years[$year] = true;
            }
        }

        ksort($years);

        return implode(' ', array_keys($years));
    }

    /**
     * @return array<int, string>
     */
    private function searchableRelations(): array
    {
        return [
            'productVariant',
            'productVariant.product.translatable',
            'productVariant.product.categories.translatable',
            'productVariant.fitments.brand.translatable',
            'productVariant.fitments.brandModel.translatable',
            'productVariant.options.option',
            'productVariant.options.item.translatable',
            'productVariant.options.translatable',
        ];
    }

    /**
     * Flatten a translatable model's translations into "{column}_{langKey}" fields.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $langKeyById
     * @return array<string, string>
     */
    private function flattenTranslations(?Model $model, array $columns, array $langKeyById, ?string $as = null): array
    {
        $out = [];

        if (! $model?->relationLoaded('translatable')) {
            return $out;
        }

        foreach ($model->translatable as $translation) {
            $key = $langKeyById[$translation->language_id] ?? null;

            if (! $key || ! in_array($translation->column, $columns, true)) {
                continue;
            }

            $out[($as ?? $translation->column).'_'.$key] = $translation->text;
        }

        return $out;
    }

    /**
     * Concatenate one translatable column from a collection of models into a
     * single per-language field (e.g. all category titles → categories_az).
     *
     * @param  iterable<int, Model>|null  $models
     * @param  array<int, string>  $langKeyById
     * @return array<string, string>
     */
    private function concatTranslatablesByLang(?iterable $models, string $column, string $as, array $langKeyById): array
    {
        if ($models === null) {
            return [];
        }

        $byLang = [];

        foreach ($models as $model) {
            if (! $model->relationLoaded('translatable')) {
                continue;
            }

            foreach ($model->translatable as $translation) {
                $key = $langKeyById[$translation->language_id] ?? null;

                if ($key && $translation->column === $column) {
                    $byLang[$key][] = $translation->text;
                }
            }
        }

        $out = [];
        foreach ($byLang as $key => $values) {
            $out[$as.'_'.$key] = implode(' ', $values);
        }

        return $out;
    }

    /**
     * Flatten a variant's option values into per-language "options_{lang}"
     * fields. Handles the three option shapes used across the app: item-based
     * options (translatable item title), free translatable values, and plain
     * language-neutral values (added to every language).
     *
     * @param  array<int, string>  $langKeyById
     * @return array<string, string>
     */
    private function flattenOptionValues(?Model $variant, array $langKeyById): array
    {
        if (! $variant?->relationLoaded('options')) {
            return [];
        }

        $byLang = [];
        $neutral = [];

        foreach ($variant->options as $optionValue) {
            $option = $optionValue->option;

            if ($option?->hasItems()) {
                // Item-based (e.g. color): translatable item title per language.
                $item = $optionValue->item;

                if ($item?->relationLoaded('translatable')) {
                    foreach ($item->translatable as $translation) {
                        $key = $langKeyById[$translation->language_id] ?? null;

                        if ($key && $translation->column === 'title') {
                            $byLang[$key][] = $translation->text;
                        }
                    }
                }
            } elseif ($option?->isTranslatable()) {
                // Free translatable value stored on the pivot row.
                foreach ($optionValue->translatable as $translation) {
                    $key = $langKeyById[$translation->language_id] ?? null;

                    if ($key && $translation->column === 'translatable_value') {
                        $byLang[$key][] = $translation->text;
                    }
                }
            } elseif (! empty($optionValue->value)) {
                // Plain, language-neutral value.
                $neutral[] = $optionValue->value;
            }
        }

        $out = [];
        foreach ($langKeyById as $key) {
            $values = array_merge($byLang[$key] ?? [], $neutral);

            if ($values) {
                $out['options_'.$key] = implode(' ', $values);
            }
        }

        return $out;
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function approvalStatuses(): HasMany
    {
        return $this->hasMany(ProductVariantApprovalStatus::class);
    }

    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(CompanyProductVariantView::class);
    }

    // ─── Image cascade accessors ──────────────────────────────────────────────

    /**
     * Hover image: CPV images[0] → variant hover_image → product hover_image
     */
    public function getListingHoverImageAttribute(): ?string
    {
        if ($this->relationLoaded('media') && $this->hasMedia('company_product_variant_images')) {
            return $this->getWebImageUrl('company_product_variant_images');
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media') && $variant->hover_image) {
                return $variant->hover_image;
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                return $variant->product->hover_image;
            }
        }

        return null;
    }

    /**
     * Main image: CPV images[0] → variant main_image → product main_image
     */
    public function getListingMainImageAttribute(): ?string
    {
        if ($this->relationLoaded('media') && $this->hasMedia('company_product_variant_images')) {
            return $this->getWebImageUrl('company_product_variant_images');
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media') && $variant->main_image) {
                return $variant->main_image;
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                return $variant->product->main_image;
            }
        }

        return null;
    }

    /**
     * Gallery: CPV images + variant images + product images (CPV önce)
     * Her element: ['source' => 'seller'|'variant'|'product', 'url' => '...']
     */
    public function getListingGalleryAttribute(): array
    {
        $gallery = [];

        if ($this->relationLoaded('media')) {
            foreach ($this->getMedia('company_product_variant_images') as $media) {
                $gallery[] = ['source' => 'seller', 'url' => self::webUrlForMedia($media)];
            }
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media')) {
                foreach ($variant->getMedia('product_variants_images') as $media) {
                    $gallery[] = ['source' => 'variant', 'url' => self::webUrlForMedia($media)];
                }
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                foreach ($variant->product->getMedia('products_images') as $media) {
                    $gallery[] = ['source' => 'product', 'url' => self::webUrlForMedia($media)];
                }
            }
        }

        return $gallery;
    }
}
