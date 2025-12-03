<?php

namespace App\Traits;

use App\Models\ModelTranslation;
use App\Services\LangService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method morphMany(string $class, string $string)
 * @property mixed $translatable
 */
trait Translatable
{
//    protected $with = ['translatable'];

    private array $translatableFieldsData = [];

    public function translatable(): MorphMany
    {
        return $this->morphMany(ModelTranslation::class, 'translatable');
    }

    public function getTranslatableFields(): array
    {
        if (!isset($this->translatableFields)) {
            return [];
        }

        return $this->translatableFields;
    }

    public function getLang(string $column): string
    {
        $default = $this->translatableFieldsData[$column][LangService::instance()->getCurrentLangId()] ?? null;

        if ($default !== null) {
            return $default;
        }

        $text = $this->translatable
            ->where('language_id', LangService::instance()->getCurrentLangId())
            ->where('column', $column)
            ->select('text')
            ->first()['text'] ?? null;

        return $text ?? $this->translatable
            ->where('language_id', LangService::instance()->getDefaultLangId())
            ->where('column', $column)
            ->select('text')
            ->first()['text'] ?? ('__' . $this->table . '__' . $column . '__' . LangService::instance()->getCurrentLang());
    }

    public function setLang(string $column, string $text, int $language_id = null): static
    {
        $this->translatableFieldsData[$column][$language_id ?? LangService::instance()->getDefaultLangId()] = $text;

        return $this;
    }

    public function setLangs(array $translations, ?array $selectedTranslatableFields = null): void
    {
        if (!empty($translations) && count($translations) > 0) {
            $default = $translations[0];

            $translatableFields = $selectedTranslatableFields === null ? $this->getTranslatableFields() : $selectedTranslatableFields;

            foreach ($translations as $translation) {
                foreach ($translatableFields as $column) {
                    $this->setLang($column, $translation[$column] ?? ($default[$column] ?? ''), $translation['language_id']);
                }
            }
        }
    }

    public function saveLang(): static
    {
        if (count($this->translatableFieldsData)) {
            foreach ($this->translatableFieldsData as $column => $field) {
                foreach ($field as $lang => $text) {
                    $this->translatable()->updateOrCreate(
                        [
                            'language_id' => $lang,
                            'column' => $column,
                        ],
                        [
                            'text' => $text,
                        ]
                    );
                }
            }

            $this->touch();
        }

        return $this;
    }

    protected function translations(): Attribute
    {
        return Attribute::make(
            get: function () {
                $this->loadMissing('translatable');

                $translates = $this->translatable->groupBy('language_id');

                $data = [];

                foreach ($translates as $langId => $translate) {
                    $d = [
                        'language_id' => $langId,
                    ];

                    foreach ($translate as $tr) {
                        if (!in_array($tr->column, $this->getTranslatableFields())) {
                            continue;
                        }

                        $d[$tr->column] = $tr->text;
                    }

                    $data[] = $d;
                }

                return collect($data)->sortBy('language_id')->values()->all();
            },
        );
    }

    public static function createWithLangs(array $validated, ?array $selectedTranslatableFields = null, bool $withoutSlug = false, ?string $slugKey = null): static
    {
        $translations = $validated['translations'] ?? [];
        unset($validated['translations']);

        if (!$withoutSlug && static::hasSlugTrait()) {
            $slugText = !empty($validated['slug']) ? $validated['slug'] : static::getDefaultText($translations, $slugKey);

            if ($slugText) {
                $validated['slug'] = static::instanceSlug()
                    ->setText($slugText)
                    ->make()
                    ->getRealSlug();
            }
        }

        /** @var static $model */
        $model = static::query()->create($validated);

        $model->setLangs($translations, $selectedTranslatableFields);

        $model->saveLang();

        return $model;
    }

    public function updateWithLangs(array $validated, ?array $selectedTranslatableFields = null, bool $withoutSlug = false, ?string $slugKey = null): static
    {
        $translations = $validated['translations'] ?? [];
        unset($validated['translations']);

        if (!$withoutSlug && $this->hasSlugTrait()) {
            $slugText = !empty($validated['slug']) ? $validated['slug'] : $this->getDefaultText($translations, $slugKey);

            if ($slugText) {
                $validated['slug'] = $this
                    ->setText($slugText)
                    ->make()
                    ->getRealSlug();
            }
        }

        $this->update($validated);

        $this->setLangs($translations, $selectedTranslatableFields);

        $this->saveLang();

        return $this;
    }

    public static function getDefaultText(array $translations, ?string $key = null): ?string
    {
        $key = $key ?? 'title';

        if (!empty($translations) && count($translations) > 0) {
            $default = $translations[0];

            return $default[$key] ?? null;
        }

        return null;
    }

    public static function hasSlugTrait(): bool
    {
        return in_array(SlugTrait::class, class_uses_recursive(static::class));
    }
}
