<?php

namespace App\Repositories;

use App\Models\Translation;
use App\Services\LangService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TranslationRepository
{
    public function load(array $languages, array $validated): Collection
    {
        $sql_arr = [];
        foreach ($languages as $language) {
            $sql_arr[] = 'MAX(CASE WHEN language_id = ' . $language['id'] . ' THEN text END) AS lang_' . $language['key'];
        }
        $sql = implode(', ', $sql_arr);

        return Translation::query()
            ->when($validated['group'] ?? null, function ($query) use ($validated) {
                $query->where('group', $validated['group']);
            })
            ->when($validated['keyword'] ?? null, function ($query) use ($validated) {
                $query->where('key', 'like', '%' . $validated['keyword'] . '%');
            })
            ->when($validated['text'] ?? null, function ($query) use ($validated) {
                $query->where('text', 'like', '%' . $validated['text'] . '%');
            })
            ->groupBy(['group', 'key'])
            ->orderBy('key')
            ->orderBy('group')
            ->select([
                'group',
                'key',
                DB::raw($sql),
            ])
            ->get();
    }

    public function findByKey(string $group, string $key): Collection
    {
        return Translation::query()
            ->leftJoin('languages', 'translations.language_id', '=', 'languages.id')
            ->where('translations.group', $group)
            ->where('translations.key', $key)
            ->select([
                'translations.language_id',
                'translations.group',
                'translations.key',
                'translations.text',
                'languages.title as language',
            ])
            ->get();
    }

    public function getByKey(string $group, string $key): Collection
    {
        return Translation::query()
            ->where('group', $group)
            ->where('key', $key)
            ->get();
    }

    public function checkByKey(string $group, string $key): bool
    {
        return Translation::query()
            ->where('group', $group)
            ->where('key', $key)
            ->select('id')
            ->count();
    }

    public function isExists(string $group, string $key, array $languages): bool
    {
        return Translation::query()
            ->where([
                'group' => $group,
                'key' => $key,
            ])
            ->whereIn('language_id', $languages)
            ->exists();
    }

    public function create(): array
    {
        $translations = [];

        $languages = LangService::instance()->getLanguages();
        $required = true;

        foreach ($languages as $language) {
            $translations[] = [
                'language_id' => $language['id'],
                'language' => $language['key'],
                'text' => '',
                'required' => $required
            ];

            if ($required) {
                $required = false;
            }
        }

        return $translations;
    }

    public function store(array $validated): void
    {
        DB::transaction(static function () use ($validated) {
            $defaultText = $validated['translations'][0]['text'];

            foreach ($validated['translations'] as $translation) {
                Translation::query()
                    ->create([
                        'group' => $validated['group'],
                        'language_id' => $translation['language_id'],
                        'key' => $validated['key'],
                        'text' => $translation['text'] ?? $defaultText,
                    ]);

                LangService::instance()->setTranslationsCache($validated['group'], $translation['language_id'], false);
            }
        });

        LangService::instance()->changeTranslationVersion();
    }

    public function update(string $group, string $key, array $validated): void
    {
        $translationsData = $validated['translations'];

        DB::transaction(static function () use ($translationsData, $group, $key) {
            foreach ($translationsData as $translation) {
                if ($translation['group'] !== $group) {
                    DB::rollBack();
                    throw new BadRequestHttpException(
                        LangService::instance()
                            ->setDefault('Wrong translation group!')
                            ->getLang('wrong_translation_group')
                    );
                }

                if ($translation['key'] !== $key) {
                    DB::rollBack();
                    throw new BadRequestHttpException(
                        LangService::instance()
                            ->setDefault('Wrong key word!')
                            ->getLang('wrong_translation_key_word')
                    );
                }

                Translation::query()
                    ->updateOrCreate(
                        [
                            'group' => $group,
                            'key' => $key,
                            'language_id' => $translation['language_id'],
                        ],
                        [
                            'text' => $translation['text'] ?? '',
                        ]
                    );

                LangService::instance()->setTranslationsCache($group, $translation['language_id'], false);
            }
        });

        LangService::instance()->changeTranslationVersion();
    }

    public function destroy(Collection $translations): void
    {
        DB::transaction(static function () use ($translations) {
            foreach ($translations as $translation) {
                $group = $translation->group;
                $language_id = $translation->language_id;

                $translation->delete();

                LangService::instance()->setTranslationsCache($group, $language_id, false);
            }
        });

        LangService::instance()->changeTranslationVersion();
    }
}
