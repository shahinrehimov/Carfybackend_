<?php

namespace App\Traits;

use App\Models\Slug;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $slug
 */
trait SlugTrait
{
    private string $realSlug;
    private ?string $baseSlug = null;
    private int $usedCount = 0;
    private string $text;
    private ?string $modelNameSuffix = null;

    public static function instanceSlug(): static
    {
        return new self();
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setModelNameSuffix(string $modelNameSuffix): static
    {
        $this->modelNameSuffix = $modelNameSuffix;

        return $this;
    }

    public function getModelNameSuffix(): string
    {
        return $this->modelNameSuffix ?? '';
    }

    public function setRealSlug(string $realSlug): static
    {
        $this->realSlug = $realSlug;

        return $this;
    }

    public function getRealSlug(): string
    {
        return $this->realSlug;
    }

    public function hasExistsModel(): bool
    {
        return $this->id ?? false;
    }

    private function generateBaseSlugFromString(string $slug): string
    {
        $slugArray = explode('-', $slug);

        $count = count($slugArray);

        if ($count > 1 && is_numeric($slugArray[$count - 1])) {
            unset($slugArray[$count - 1]);
        }

        return implode('-', $slugArray);
    }

    public function getBaseSlug(): string
    {
        if ($this->baseSlug === null) {
            $this->baseSlug = $this->generateBaseSlugFromString($this->realSlug);
        }

        return $this->baseSlug;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function checkExists(): bool
    {
        $slug = Slug::query()
            ->where('model', $this->getModelName())
            ->where('slug', $this->getBaseSlug())
            ->orderByDesc('id')
            ->first();

        if ($slug) {
            $this->usedCount = $slug->used_count;

            return true;
        }

        return false;
    }

    public function modify(): static
    {
        if ($this->hasExistsModel() && $this->generateBaseSlugFromString($this->slug) === $this->getBaseSlug()) {
            // update model with its current slug
            $this->realSlug = $this->slug;

            return $this;
        }

        if ($this->checkExists()) {
            $this->usedCount = $this->usedCount + 1;
        }

        $this->realSlug = $this->usedCount > 0 ? ($this->getBaseSlug() . '-' . $this->usedCount) : $this->getBaseSlug();

        return $this->saveToSlugs();
    }

    public function make(): static
    {
        $this->realSlug = Str::slug($this->getText());

        return $this->modify();
    }

    public function saveToSlugs(): static
    {
        Slug::query()
            ->updateOrCreate(
            ['model' => $this->getModelName(), 'slug' => $this->getBaseSlug()],
            ['used_count' => $this->getUsedCount()]
        );

        return $this;
    }

    private function getModelName(): string
    {
        return class_basename(static::class) . $this->getModelNameSuffix();
    }
}
