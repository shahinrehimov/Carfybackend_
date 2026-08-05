<?php

namespace Database\Seeders;

use App\Enum\TranslationGroupEnum;
use App\Models\Language;
use App\Models\Translation;
use App\Services\LangService;
use Illuminate\Database\Seeder;

/**
 * Translation keys for the tiered commission editor in the admin panel.
 *
 * Only missing keys are inserted, so re-running never overwrites a text an
 * admin has edited through the translations module.
 */
class CommissionTierTranslationsSeeder extends Seeder
{
    /**
     * [key => [az, en, ru]]
     */
    private const ADMIN = [
        'commission_tiers' => [
            'Komissiya pillələri',
            'Commission tiers',
            'Ступени комиссии',
        ],
        'commission_tiers_hint' => [
            'Məbləğ aralığına görə fərqli faiz. Pillə yoxdursa yuxarıdakı sabit faiz tətbiq olunur.',
            'A different percent per amount range. With no tiers the flat percent above applies.',
            'Разный процент по диапазону суммы. Без ступеней применяется фиксированный процент выше.',
        ],
        'global_commission_tiers' => [
            'Ümumi komissiya pillələri',
            'Global commission tiers',
            'Общие ступени комиссии',
        ],
        'global_commission_tiers_hint' => [
            'Satıcı və kateqoriya pillələri boş olanda bu pillələr işləyir.',
            'These tiers apply when the seller and category tiers are empty.',
            'Эти ступени применяются, когда ступени продавца и категории пусты.',
        ],
        'add_tier' => [
            'Pillə əlavə et',
            'Add tier',
            'Добавить ступень',
        ],
        'no_commission_tiers' => [
            'Pillə yoxdur — sabit faiz tətbiq olunur',
            'No tiers — the flat percent applies',
            'Ступеней нет — применяется фиксированный процент',
        ],
        'tier_min_amount' => [
            'Minimum məbləğ (₼)',
            'Min amount (₼)',
            'Мин. сумма (₼)',
        ],
        'tier_max_amount' => [
            'Maksimum məbləğ (₼)',
            'Max amount (₼)',
            'Макс. сумма (₼)',
        ],
        'tier_percent' => [
            'Faiz (%)',
            'Percent (%)',
            'Процент (%)',
        ],
        'tier_max_empty' => [
            'limitsiz',
            'no limit',
            'без предела',
        ],
        'tier_max_empty_hint' => [
            'Minimum daxildir, maksimum xaricdir. Sonuncu pillənin maksimumunu boş qoysanız yuxarı hədd olmur.',
            'Min is inclusive, max is exclusive. Leave the last tier max empty for no upper bound.',
            'Минимум включительно, максимум исключительно. Оставьте максимум последней ступени пустым — без верхней границы.',
        ],
        'commission_tiers_saved_successfully' => [
            'Komissiya pillələri yadda saxlanıldı',
            'Commission tiers saved',
            'Ступени комиссии сохранены',
        ],
        'commission_tier_fields_required' => [
            'Hər pillənin minimum məbləği və faizi doldurulmalıdır',
            'Every tier needs a min amount and a percent',
            'У каждой ступени должны быть мин. сумма и процент',
        ],
        'commission_tier_percent_out_of_range' => [
            'Faiz 0 ilə 100 arasında olmalıdır',
            'The percent must be between 0 and 100',
            'Процент должен быть от 0 до 100',
        ],
        'commission_tier_max_must_exceed_min' => [
            'Pillənin maksimum məbləği minimumdan böyük olmalıdır',
            'Each commission tier max amount must be greater than its min amount',
            'Максимальная сумма ступени должна быть больше минимальной',
        ],
        'commission_tier_open_ended_must_be_last' => [
            'Yalnız sonuncu pillənin maksimumu boş ola bilər',
            'Only the last commission tier can be open ended',
            'Только последняя ступень может быть без верхней границы',
        ],
        'commission_tiers_must_not_overlap' => [
            'Pillə aralıqları üst-üstə düşməməlidir',
            'Commission tier ranges must not overlap',
            'Диапазоны ступеней не должны пересекаться',
        ],
    ];

    public function run(): void
    {
        $languageIds = Language::query()->orderBy('id')->pluck('id', 'key')->all();

        $group   = TranslationGroupEnum::ADMIN->value;
        $created = 0;

        foreach (self::ADMIN as $key => $texts) {
            foreach (['az', 'en', 'ru'] as $index => $langKey) {
                if (!isset($languageIds[$langKey])) {
                    continue;
                }

                $translation = Translation::query()->firstOrCreate(
                    [
                        'group'       => $group,
                        'language_id' => $languageIds[$langKey],
                        'key'         => $key,
                    ],
                    [
                        'text' => $texts[$index],
                    ]
                );

                if ($translation->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        // Clients only refetch translations when the version changes, and the
        // per-group cache has to be rebuilt for the new keys to be served.
        LangService::instance()->setTranslationsCache($group, array_keys($languageIds), false);
        LangService::instance()->changeTranslationVersion();

        $this->command?->info("Commission tier translations: {$created} key(s) created.");
    }
}
