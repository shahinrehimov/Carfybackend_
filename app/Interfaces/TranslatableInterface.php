<?php

namespace App\Interfaces;

interface TranslatableInterface
{
    /**
     * Set a single translation value for a given column and language.
     *
     * @param string $column
     * @param string $text
     * @param int|null $language_id
     * @return static
     */
    public function setLang(string $column, string $text, int $language_id = null): static;

    /**
     * Set multiple translations for the model.
     *
     * @param array $translations
     * @param array|null $selectedTranslatableFields
     * @return void
     */
    public function setLangs(array $translations, ?array $selectedTranslatableFields = null): void;

    /**
     * Save the translation data to the database.
     *
     * @return static
     */
    public function saveLang(): static;

    /**
     * Get translation for a specific column in current language context.
     *
     * @param string $column
     * @return string
     */
    public function getLang(string $column): string;

    /**
     * Return a translatable fields array.
     *
     * @return array
     */
    public function getTranslatableFields(): array;

    /**
     * Create a model with translation data.
     *
     * @param array $validated
     * @return static
     */
    public static function createWithLangs(array $validated): static;

    /**
     * Update the model with translation data.
     *
     * @param array $validated
     * @return static
     */
    public function updateWithLangs(array $validated): static;
}
